<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once DIR . '/../config/config.php';
require_once DIR . '/../config/database.php';
require_once DIR . '/../classes/Complaint.php';
require_once DIR . '/../classes/GeoCluster.php';

$complaintObj = new Complaint();
$clusterObj   = new GeoCluster();

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'track':
        $tid = trim($_GET['tracking_id'] ?? '');
        if (!$tid) {
            echo json_encode(['success' => false, 'message' => 'Tracking ID required']);
            break;
        }
        $complaint = $complaintObj->getByTrackingId($tid);
        if (!$complaint) {
            echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        } else {
            // Add photo URL
            if ($complaint['photo_path']) {
                $complaint['photo_url'] = BASE_URL . '/' . $complaint['photo_path'];
            }
            echo json_encode(['success' => true, 'data' => $complaint]);
        }
        break;

    case 'clusters':
        $status = $_GET['status'] ?? null;
        $clusters = $clusterObj->getAllClusters($status);
        echo json_encode(['success' => true, 'data' => $clusters, 'count' => count($clusters)]);
        break;

    case 'cluster_detail':
        $cid = (int)($_GET['cluster_id'] ?? 0);
        if (!$cid) {
            echo json_encode(['success' => false, 'message' => 'Cluster ID required']);
            break;
        }
        $complaints = $clusterObj->getClusterComplaints($cid);
        foreach ($complaints as &$c) {
            if ($c['photo_path']) {
                $c['photo_url'] = BASE_URL . '/' . $c['photo_path'];
            }
        }
        echo json_encode(['success' => true, 'data' => $complaints, 'count' => count($complaints)]);
        break;

    case 'stats':
        $stats = $complaintObj->getStats();
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    case 'list':
    default:
        $filters = [];
        if (!empty($_GET['status']))   $filters['status']   = $_GET['status'];
        if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
        if (!empty($_GET['ward_id']))  $filters['ward_id']  = (int)$_GET['ward_id'];

        $limit  = min((int)($_GET['limit'] ?? 50), 200);
        $offset = (int)($_GET['offset'] ?? 0);

        $complaints = $complaintObj->getAll($filters, $limit, $offset);
        foreach ($complaints as &$c) {
            if ($c['photo_path']) {
                $c['photo_url'] = BASE_URL . '/' . $c['photo_path'];
            }
        }
        echo json_encode(['success' => true, 'data' => $complaints, 'count' => count($complaints)]);
        break;
}