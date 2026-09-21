<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Notifier {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Trigger notification for a cluster if conditions met
     */
    public function maybeNotify(int $clusterId, int $complaintCount): bool {
        if ($complaintCount < CLUSTER_THRESHOLD) {
            return false;
        }

        // Check cooldown
        $stmt = $this->db->prepare(
            "SELECT notification_sent, notification_sent_at FROM complaint_clusters WHERE id = :id"
        );
        $stmt->execute(['id' => $clusterId]);
        $cluster = $stmt->fetch();

        if (!$cluster) return false;

        if ($cluster['notification_sent'] && $cluster['notification_sent_at']) {
            $lastSent = strtotime($cluster['notification_sent_at']);
            $cooldownSecs = NOTIFICATION_COOLDOWN_HOURS * 3600;
            if ((time() - $lastSent) < $cooldownSecs) {
                return false; // Still within cooldown
            }
        }

        return $this->sendClusterNotification($clusterId);
    }

    /**
     * Send notification email to ward officer
     */
    public function sendClusterNotification(int $clusterId): bool {
        $stmt = $this->db->prepare(
            "SELECT cc.*, w.officer_name, w.officer_email, w.ward_name, w.ward_number
             FROM complaint_clusters cc
             LEFT JOIN wards w ON cc.ward_id = w.id
             WHERE cc.id = :id"
        );
        $stmt->execute(['id' => $clusterId]);
        $cluster = $stmt->fetch();

        if (!$cluster || !$cluster['officer_email']) {
            return false;
        }

        $subject = "[" . APP_NAME . "] ALERT: {$cluster['complaint_count']} {$cluster['category']} complaints in {$cluster['ward_name']}";
        $body = $this->buildEmailBody($cluster);

        $sent = $this->sendEmail($cluster['officer_email'], $cluster['officer_name'], $subject, $body);

        // Log notification
        $logStmt = $this->db->prepare(
            "INSERT INTO notifications (ward_id, cluster_id, officer_email, officer_name, subject, message, complaint_count, category, status)
             VALUES (:ward_id, :cluster_id, :email, :name, :subject, :message, :count, :category, :status)"
        );
        $logStmt->execute([
            'ward_id'    => $cluster['ward_id'],
            'cluster_id' => $clusterId,
            'email'      => $cluster['officer_email'],
            'name'       => $cluster['officer_name'],
            'subject'    => $subject,
            'message'    => $body,
            'count'      => $cluster['complaint_count'],
            'category'   => $cluster['category'],
            'status'     => $sent ? 'sent' : 'failed',
        ]);

        if ($sent) {
            // Mark cluster as notified
            $upd = $this->db->prepare(
                "UPDATE complaint_clusters SET notification_sent = 1, notification_sent_at = NOW() WHERE id = :id"
            );
            $upd->execute(['id' => $clusterId]);
        }

        return $sent;
    }

    private function buildEmailBody(array $cluster): string {
        $categoryLabel = ucfirst(str_replace('_', ' ', $cluster['category']));
        $severity = strtoupper($cluster['severity']);
        $mapsLink = "https://www.google.com/maps?q={$cluster['center_lat']},{$cluster['center_lng']}";
        $dashboardLink = BASE_URL . "/officer/dashboard.php";

        return "
Dear {$cluster['officer_name']},

This is an automated alert from the " . APP_NAME . " system.

CLUSTER ALERT DETAILS
=====================
Ward:         {$cluster['ward_name']} ({$cluster['ward_number']})
Issue Type:   {$categoryLabel}
Reports:      {$cluster['complaint_count']} complaints
Severity:     {$severity}
Location:     {$cluster['center_lat']}, {$cluster['center_lng']}
View on Map:  {$mapsLink}

Multiple residents have reported {$categoryLabel} issues within a {$cluster['radius_meters']}-meter radius in your ward. 
Immediate attention is requested.

Action Required:
- Log in to the officer portal: {$dashboardLink}
- Review and acknowledge the complaints
- Assign field team for inspection
- Update resolution status

This is an automated message. Do not reply directly to this email.
— " . APP_NAME . " System
        ";
    }

    private function sendEmail(string $toEmail, string $toName, string $subject, string $body): bool {
        if (defined('MAIL_USE_SMTP') && MAIL_USE_SMTP && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendSmtpEmail($toEmail, $toName, $subject, $body);
        }
        // Fallback: PHP mail()
        $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail("{$toName} <{$toEmail}>", $subject, $body, $headers);
    }

    private function sendSmtpEmail(string $toEmail, string $toName, string $subject, string $body): bool {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_SMTP_USER;
            $mail->Password   = MAIL_SMTP_PASS;
            $mail->SMTPSecure = MAIL_SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = MAIL_SMTP_PORT;
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->isHTML(false);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all notifications for display
     */
    public function getNotifications(int $wardId = null, int $limit = 50): array {
        if ($wardId) {
            $stmt = $this->db->prepare(
                "SELECT n.*, w.ward_name FROM notifications n
                 LEFT JOIN wards w ON n.ward_id = w.id
                 WHERE n.ward_id = :ward_id ORDER BY n.sent_at DESC LIMIT :lim"
            );
            $stmt->bindValue(':ward_id', $wardId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare(
                "SELECT n.*, w.ward_name FROM notifications n
                 LEFT JOIN wards w ON n.ward_id = w.id
                 ORDER BY n.sent_at DESC LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
}
