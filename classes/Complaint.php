<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Complaint {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function generateTrackingId(): string {
        return 'CMP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    public function create(array $data): array {
        // Validate required fields
        $required = ['category', 'lat', 'lng'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: $field"];
            }
        }

        $trackingId = $this->generateTrackingId();
        // Ensure unique
        while ($this->getByTrackingId($trackingId)) {
            $trackingId = $this->generateTrackingId();
        }

        $stmt = $this->db->prepare(
            "INSERT INTO complaints
             (tracking_id, citizen_name, citizen_email, citizen_phone, category, description,
              lat, lng, address, photo_path, ward_id, status, priority)
             VALUES
             (:tracking_id, :citizen_name, :citizen_email, :citizen_phone, :category, :description,
              :lat, :lng, :address, :photo_path, :ward_id, 'pending', :priority)"
        );

        $stmt->execute([
            'tracking_id'   => $trackingId,
            'citizen_name'  => $data['citizen_name'] ?? null,
            'citizen_email' => $data['citizen_email'] ?? null,
            'citizen_phone' => $data['citizen_phone'] ?? null,
            'category'      => $data['category'],
            'description'   => $data['description'] ?? null,
            'lat'           => $data['lat'],
            'lng'           => $data['lng'],
            'address'       => $data['address'] ?? null,
            'photo_path'    => $data['photo_path'] ?? null,
            'ward_id'       => $data['ward_id'] ?? null,
            'priority'      => $data['priority'] ?? 'low',
        ]);

        $id = (int)$this->db->lastInsertId();
        return ['success' => true, 'id' => $id, 'tracking_id' => $trackingId];
    }

    public function updateStatus(int $id, string $status, string $notes = '', int $changedBy = null): bool {
        $stmt = $this->db->prepare("SELECT status FROM complaints WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $old = $stmt->fetch();
        if (!$old) return false;

        $upd = $this->db->prepare(
            "UPDATE complaints SET status = :status, resolution_notes = :notes,
             resolved_at = IF(:status = 'resolved', NOW(), resolved_at),
             updated_at = NOW() WHERE id = :id"
        );
        $upd->execute(['status' => $status, 'notes' => $notes, 'id' => $id]);

        // Log history
        $hist = $this->db->prepare(
            "INSERT INTO complaint_history (complaint_id, changed_by, old_status, new_status, notes)
             VALUES (:cid, :by, :old, :new, :notes)"
        );
        $hist->execute([
            'cid'   => $id,
            'by'    => $changedBy,
            'old'   => $old['status'],
            'new'   => $status,
            'notes' => $notes,
        ]);

        return true;
    }

    public function getByTrackingId(string $trackingId): ?array {
        $stmt = $this->db->prepare(
            "SELECT c.*, w.ward_name, w.officer_name
             FROM complaints c LEFT JOIN wards w ON c.ward_id = w.id
             WHERE c.tracking_id = :tid"
        );
        $stmt->execute(['tid' => $trackingId]);
        return $stmt->fetch() ?: null;
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT c.*, w.ward_name, w.ward_number, w.officer_name, w.officer_email
             FROM complaints c LEFT JOIN wards w ON c.ward_id = w.id
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(array $filters = [], int $limit = 100, int $offset = 0): array {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = "c.category = :category";
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['ward_id'])) {
            $where[] = "c.ward_id = :ward_id";
            $params['ward_id'] = $filters['ward_id'];
        }

        $whereStr = $where ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->db->prepare(
            "SELECT c.*, w.ward_name, w.officer_name
             FROM complaints c LEFT JOIN wards w ON c.ward_id = w.id
             $whereStr
             ORDER BY c.created_at DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStats(): array {
        $stats = [];

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM complaints");
        $stats['total'] = (int)$stmt->fetch()['total'];

        $stmt = $this->db->query("SELECT status, COUNT(*) as cnt FROM complaints GROUP BY status");
        $byStatus = $stmt->fetchAll();
        $stats['by_status'] = array_column($byStatus, 'cnt', 'status');

        $stmt = $this->db->query("SELECT category, COUNT(*) as cnt FROM complaints GROUP BY category ORDER BY cnt DESC");
        $byCategory = $stmt->fetchAll();
        $stats['by_category'] = array_column($byCategory, 'cnt', 'category');

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM complaint_clusters WHERE status != 'resolved'");
        $stats['active_clusters'] = (int)$stmt->fetch()['total'];

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM complaint_clusters WHERE severity IN ('high', 'critical')");
        $stats['critical_clusters'] = (int)$stmt->fetch()['total'];

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM notifications WHERE DATE(sent_at) = CURDATE()");
        $stats['notifications_today'] = (int)$stmt->fetch()['total'];

        return $stats;
    }

    public function uploadPhoto(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File too large. Max 5MB allowed.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP.'];
        }

        // Verify it's actually an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!str_starts_with($mimeType, 'image/')) {
            return ['success' => false, 'message' => 'File must be an image.'];
        }

        $filename = uniqid('complaint_', true) . '.' . $ext;
        $dest = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file.'];
        }

        return ['success' => true, 'path' => 'uploads/complaints/' . $filename, 'url' => UPLOAD_URL . $filename];
    }
}
