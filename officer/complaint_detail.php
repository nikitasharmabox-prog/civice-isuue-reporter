<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../classes/Complaint.php';
require_once __DIR__ . '/../classes/GeoCluster.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: complaints.php');
    exit;
}

$complaintObj = new Complaint();
$complaint = $complaintObj->getById($id);

if (!$complaint) {
    header('Location: complaints.php');
    exit;
}

// Officers can only see their ward's complaints
if ($_SESSION['officer_role'] !== 'admin' && $complaint['ward_id'] != $_SESSION['officer_ward_id']) {
    header('Location: complaints.php');
    exit;
}

// Get cluster info
$clusterObj = new GeoCluster();
$clusterComplaints = [];
if ($complaint['cluster_id']) {
    $clusterComplaints = $clusterObj->getClusterComplaints($complaint['cluster_id']);
}

$pageTitle = 'Complaint ' . htmlspecialchars($complaint['tracking_id']);
$mapsLink = "https://www.google.com/maps?q={$complaint['lat']},{$complaint['lng']}";
$statusColors = [
    'pending' => '#f59e0b', 'acknowledged' => '#3b82f6',
    'in_progress' => '#8b5cf6', 'resolved' => '#16a34a', 'rejected' => '#ef4444'
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h3><?= APP_NAME ?></h3></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="complaints.php" class="active"><i class="fas fa-clipboard-list"></i> All Complaints</a>
            <a href="clusters.php"><i class="fas fa-layer-group"></i> Clusters</a>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a href="logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div style="margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
            <a href="complaints.php" class="btn btn-sm" style="background:var(--gray-200); color:var(--gray-700);">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h1 style="font-size:1.5rem; font-weight:700; flex:1;">
                Complaint: <code><?= htmlspecialchars($complaint['tracking_id']) ?></code>
            </h1>
            <span class="badge badge-<?= $complaint['status'] ?>" style="font-size:0.9rem; padding:0.4rem 1rem;">
                <?= ucwords(str_replace('_',' ',$complaint['status'])) ?>
            </span>
        </div>

        <div style="display:grid; grid-template-columns:1.2fr 0.8fr; gap:1.5rem;">
            <!-- Left Column -->
            <div>
                <!-- Photo -->
                <?php if ($complaint['photo_path']): ?>
                <div class="card" style="padding:0; overflow:hidden;">
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($complaint['photo_path']) ?>"
                         alt="Issue Photo" style="width:100%; max-height:380px; object-fit:cover;">
                </div>
                <?php endif; ?>

                <!-- Details -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-info-circle"></i> Issue Details</span>
                        <span class="badge badge-<?= $complaint['category'] ?>"><?= ucwords(str_replace('_',' ',$complaint['category'])) ?></span>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item"><label>Tracking ID</label><p style="font-family:monospace;"><?= htmlspecialchars($complaint['tracking_id']) ?></p></div>
                        <div class="detail-item"><label>Category</label><p><?= ucwords(str_replace('_',' ',$complaint['category'])) ?></p></div>
                        <div class="detail-item"><label>Ward</label><p><?= htmlspecialchars($complaint['ward_name'] ?? '—') ?></p></div>
                        <div class="detail-item"><label>Ward Number</label><p><?= htmlspecialchars($complaint['ward_number'] ?? '—') ?></p></div>
                        <div class="detail-item"><label>Priority</label><p><span class="badge badge-<?= $complaint['priority'] ?>"><?= $complaint['priority'] ?></span></p></div>
                        <div class="detail-item"><label>Submitted</label><p><?= date('d M Y H:i', strtotime($complaint['created_at'])) ?></p></div>
                        <?php if ($complaint['resolved_at']): ?>
                        <div class="detail-item"><label>Resolved At</label><p><?= date('d M Y H:i', strtotime($complaint['resolved_at'])) ?></p></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($complaint['description']): ?>
                    <div style="margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--gray-200);">
                        <label class="form-label">Description</label>
                        <p style="color:var(--gray-700);"><?= nl2br(htmlspecialchars($complaint['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($complaint['address']): ?>
                    <div style="margin-top:0.75rem;">
                        <label class="form-label">Address</label>
                        <p style="color:var(--gray-700);"><?= htmlspecialchars($complaint['address']) ?></p>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--gray-200);">
                        <label class="form-label">GPS Coordinates</label>
                        <p style="font-family:monospace; font-size:0.875rem;">
                            <?= $complaint['lat'] ?>, <?= $complaint['lng'] ?>
                            <a href="<?= $mapsLink ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="margin-left:0.75rem;">
                                <i class="fas fa-map-marker-alt"></i> Google Maps
                            </a>
                        </p>
                    </div>
                </div>

                <!-- Citizen Details -->
                <?php if ($complaint['citizen_name'] || $complaint['citizen_email']): ?>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-user"></i> Citizen Details</span>
                    </div>
                    <div class="detail-grid">
                        <?php if ($complaint['citizen_name']): ?>
                        <div class="detail-item"><label>Name</label><p><?= htmlspecialchars($complaint['citizen_name']) ?></p></div>
                        <?php endif; ?>
                        <?php if ($complaint['citizen_email']): ?>
                        <div class="detail-item"><label>Email</label><p><?= htmlspecialchars($complaint['citizen_email']) ?></p></div>
                        <?php endif; ?>
                        <?php if ($complaint['citizen_phone']): ?>
                        <div class="detail-item"><label>Phone</label><p><?= htmlspecialchars($complaint['citizen_phone']) ?></p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Update Status -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-edit"></i> Update Status</span>
                    </div>
                    <form id="statusForm" onsubmit="saveStatus(event)">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="newStatus" name="status">
                                <?php foreach (['pending','acknowledged','in_progress','resolved','rejected'] as $s): ?>
                                <option value="<?= $s ?>" <?= $complaint['status'] === $s ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_',' ',$s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="statusNotes" name="notes" rows="3"
                                placeholder="Resolution notes or comments..."><?= htmlspecialchars($complaint['resolution_notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Save Status
                        </button>
                    </form>
                    <div id="statusMsg" style="margin-top:0.75rem;"></div>
                </div>

                <!-- Cluster info -->
                <?php if ($complaint['cluster_id']): ?>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-layer-group"></i> Cluster Info</span>
                        <span class="badge badge-high"><?= count($clusterComplaints) ?> reports</span>
                    </div>
                    <p style="font-size:0.82rem; color:var(--gray-600); margin-bottom:1rem;">
                        This complaint is part of a cluster with <?= count($clusterComplaints) ?> nearby reports of the same type.
                    </p>
                    <?php foreach (array_slice($clusterComplaints, 0, 4) as $cc): ?>
                    <?php if ($cc['id'] == $id) continue; ?>
                    <div style="padding:0.6rem 0; border-bottom:1px solid var(--gray-100); font-size:0.82rem;">
                        <a href="complaint_detail.php?id=<?= $cc['id'] ?>" style="font-family:monospace; color:var(--primary);"><?= htmlspecialchars($cc['tracking_id']) ?></a>
                        <span class="badge badge-<?= $cc['status'] ?>" style="font-size:0.68rem; margin-left:0.5rem;"><?= $cc['status'] ?></span>
                        <p style="color:var(--gray-500); margin-top:0.2rem;"><?= htmlspecialchars(substr($cc['description'] ?? '—', 0, 80)) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <a href="complaints.php?cluster_id=<?= $complaint['cluster_id'] ?>" class="btn btn-sm btn-outline-primary" style="margin-top:0.75rem;">
                        View All Cluster Complaints
                    </a>
                </div>
                <?php endif; ?>

                <!-- Mini Map -->
                <div class="card" style="padding:0; overflow:hidden;">
                    <div id="miniMap" style="height:220px;"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const BASE_URL = '<?= BASE_URL ?>';

// Mini map
const map = L.map('miniMap').setView([<?= $complaint['lat'] ?>, <?= $complaint['lng'] ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= $complaint['lat'] ?>, <?= $complaint['lng'] ?>]).addTo(map)
    .bindPopup('<?= htmlspecialchars(addslashes($complaint['tracking_id'])) ?>').openPopup();

// Status update
async function saveStatus(e) {
    e.preventDefault();
    const status = document.getElementById('newStatus').value;
    const notes  = document.getElementById('statusNotes').value;
    const msg    = document.getElementById('statusMsg');

    try {
        const res = await fetch(`${BASE_URL}/api/update_status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ complaint_id: <?= $id ?>, status, notes })
        });
        const data = await res.json();
        if (data.success) {
            msg.innerHTML = `<div class="alert alert-success"><i class="fas fa-check"></i> ${data.message}</div>`;
            ToastManager.show('Status updated!', 'success');
        } else {
            msg.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    } catch {
        ToastManager.show('Network error', 'error');
    }
}
</script>
