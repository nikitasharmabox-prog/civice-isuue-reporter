<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Complaint.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$complaintId = (int)($input['complaint_id'] ?? 0);
$status      = trim($input['status'] ?? '');
$notes       = htmlspecialchars(strip_tags(trim($input['notes'] ?? '')), ENT_QUOTES, 'UTF-8');

$validStatuses = ['pending', 'acknowledged', 'in_progress', 'resolved', 'rejected'];
if (!$complaintId || !in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$complaintObj = new Complaint();

// Officers can only update complaints in their ward
if ($_SESSION['officer_role'] !== 'admin') {
    $c = $complaintObj->getById($complaintId);
    if (!$c || $c['ward_id'] != $_SESSION['officer_ward_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied: complaint not in your ward']);
        exit;
    }
}

$ok = $complaintObj->updateStatus($complaintId, $status, $notes, $_SESSION['officer_id']);

if ($ok) {
    // If all complaints in a cluster are resolved, update cluster status
    $db = Database::getConnection();
    $stmt = $db->prepare(
        "SELECT cluster_id FROM complaints WHERE id = :id"
    );
    $stmt->execute(['id' => $complaintId]);
    $row = $stmt->fetch();

    if ($row && $row['cluster_id'] && $status === 'resolved') {
        $checkStmt = $db->prepare(
            "SELECT COUNT(*) as pending FROM complaints
             WHERE cluster_id = :cid AND status NOT IN ('resolved', 'rejected')"
        );
        $checkStmt->execute(['cid' => $row['cluster_id']]);
        $pending = (int)$checkStmt->fetch()['pending'];
        if ($pending === 0) {
            $db->prepare("UPDATE complaint_clusters SET status = 'resolved' WHERE id = :id")
               ->execute(['id' => $row['cluster_id']]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Status updated to ' . $status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
