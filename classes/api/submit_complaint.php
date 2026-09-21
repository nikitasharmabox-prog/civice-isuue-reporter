<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Complaint.php';
require_once __DIR__ . '/../classes/GeoCluster.php';
require_once __DIR__ . '/../classes/Notifier.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Sanitize input
function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$data = [
    'category'      => sanitize($_POST['category'] ?? ''),
    'description'   => sanitize($_POST['description'] ?? ''),
    'lat'           => filter_var($_POST['lat'] ?? '', FILTER_VALIDATE_FLOAT),
    'lng'           => filter_var($_POST['lng'] ?? '', FILTER_VALIDATE_FLOAT),
    'address'       => sanitize($_POST['address'] ?? ''),
    'citizen_name'  => sanitize($_POST['citizen_name'] ?? ''),
    'citizen_email' => filter_var($_POST['citizen_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
    'citizen_phone' => sanitize($_POST['citizen_phone'] ?? ''),
];

// Validate
$validCategories = ['pothole', 'garbage', 'streetlight', 'water_leak', 'sewage', 'road_damage', 'encroachment', 'other'];
if (!in_array($data['category'], $validCategories)) {
    echo json_encode(['success' => false, 'message' => 'Invalid category.']);
    exit;
}
if ($data['lat'] === false || $data['lng'] === false) {
    echo json_encode(['success' => false, 'message' => 'Valid GPS coordinates required.']);
    exit;
}
if ($data['lat'] < -90 || $data['lat'] > 90 || $data['lng'] < -180 || $data['lng'] > 180) {
    echo json_encode(['success' => false, 'message' => 'Coordinates out of range.']);
    exit;
}

$complaintObj = new Complaint();
$clusterObj   = new GeoCluster();
$notifier     = new Notifier();

// Handle photo upload
$photoPath = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploadResult = $complaintObj->uploadPhoto($_FILES['photo']);
    if (!$uploadResult['success']) {
        echo json_encode($uploadResult);
        exit;
    }
    $photoPath = $uploadResult['path'];
}

// Find ward
$ward = $clusterObj->findWardForLocation($data['lat'], $data['lng']);
$data['ward_id']    = $ward['id'] ?? null;
$data['photo_path'] = $photoPath;

// Determine priority from description keywords
$desc = strtolower($data['description']);
$data['priority'] = 'low';
if (str_contains($desc, 'urgent') || str_contains($desc, 'danger') || str_contains($desc, 'accident')) {
    $data['priority'] = 'high';
} elseif (str_contains($desc, 'severe') || str_contains($desc, 'major')) {
    $data['priority'] = 'medium';
}

// Create complaint
$result = $complaintObj->create($data);
if (!$result['success']) {
    echo json_encode($result);
    exit;
}

// Cluster assignment
$clusterResult = $clusterObj->assignComplaint(
    $result['id'],
    $data['lat'],
    $data['lng'],
    $data['category'],
    $data['ward_id']
);

// Update ward_id on cluster if newly created
if ($clusterResult['is_new_cluster'] && $data['ward_id']) {
    $db = Database::getConnection();
    $stmt = $db->prepare("UPDATE complaint_clusters SET ward_id = :wid WHERE id = :cid");
    $stmt->execute(['wid' => $data['ward_id'], 'cid' => $clusterResult['cluster_id']]);
}

// Send notification if threshold reached
$notified = false;
if ($clusterResult['should_notify']) {
    $notified = $notifier->maybeNotify($clusterResult['cluster_id'], $clusterResult['complaint_count']);
}

echo json_encode([
    'success'          => true,
    'tracking_id'      => $result['tracking_id'],
    'complaint_id'     => $result['id'],
    'cluster_id'       => $clusterResult['cluster_id'],
    'cluster_count'    => $clusterResult['complaint_count'],
    'severity'         => $clusterResult['severity'],
    'ward'             => $ward['ward_name'] ?? 'Unknown',
    'officer_notified' => $notified,
    'message'          => 'Complaint submitted successfully. Your tracking ID is ' . $result['tracking_id'],
]);
