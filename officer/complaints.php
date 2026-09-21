<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../classes/Complaint.php';

$complaintObj = new Complaint();
$wardId = ($_SESSION['officer_role'] === 'admin') ? null : $_SESSION['officer_ward_id'];

$filters = [];
if ($wardId) $filters['ward_id'] = $wardId;
if (!empty($_GET['status']))     $filters['status']   = $_GET['status'];
if (!empty($_GET['category']))   $filters['category'] = $_GET['category'];
if (!empty($_GET['cluster_id'])) {
    // handled separately below
}

$complaints = $complaintObj->getAll($filters, 200);

// Filter by cluster_id if requested
if (!empty($_GET['cluster_id'])) {
    $cid = (int)$_GET['cluster_id'];
    $complaints = array_filter($complaints, fn($c) => $c['cluster_id'] == $cid);
}

$pageTitle = 'Complaints';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h3><?= APP_NAME ?></h3>
            <p><?= htmlspecialchars($_SESSION['officer_name'] ?? 'Officer') ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="complaints.php" class="active"><i class="fas fa-clipboard-list"></i> All Complaints</a>
            <a href="clusters.php"><i class="fas fa-layer-group"></i> Clusters</a>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <?php if ($_SESSION['officer_role'] === 'admin'): ?>
            <a href="wards.php"><i class="fas fa-map"></i> Wards</a>
            <?php endif; ?>
            <a href="/civic-reporter/index.php"><i class="fas fa-home"></i> Citizen Portal</a>
            <a href="logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <h1 style="font-size:1.5rem; font-weight:700;"><i class="fas fa-clipboard-list"></i> Complaints</h1>
            <span class="badge badge-high" style="font-size:0.9rem; padding:0.4rem 1rem;"><?= count($complaints) ?> results</span>
        </div>

        <!-- Filters -->
        <div class="card" style="padding:1rem;">
            <form method="GET" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                <div>
                    <label class="form-label" style="font-size:0.78rem; margin-bottom:0.3rem;">Status</label>
                    <select class="form-control" name="status" style="min-width:130px;">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','acknowledged','in_progress','resolved','rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_',' ',$s)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:0.78rem; margin-bottom:0.3rem;">Category</label>
                    <select class="form-control" name="category" style="min-width:140px;">
                        <option value="">All Categories</option>
                        <?php foreach (['pothole','garbage','streetlight','water_leak','sewage','road_damage','encroachment','other'] as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($_GET['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_',' ',$cat)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="complaints.php" class="btn btn-sm" style="background:var(--gray-200); color:var(--gray-700);">Reset</a>
            </form>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Category</th>
                            <th>Citizen</th>
                            <th>Address</th>
                            <th>Ward</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($c['tracking_id']) ?></code></td>
                            <td><span class="badge badge-<?= $c['category'] ?>"><?= ucwords(str_replace('_',' ',$c['category'])) ?></span></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($c['citizen_name'] ?? 'Anonymous') ?></td>
                            <td><span class="truncate" title="<?= htmlspecialchars($c['address'] ?? '') ?>"><?= htmlspecialchars($c['address'] ?? '—') ?></span></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($c['ward_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $c['priority'] ?>"><?= $c['priority'] ?></span></td>
                            <td>
                                <select class="form-control" style="min-width:130px; font-size:0.78rem; padding:0.3rem 0.5rem;"
                                        onchange="updateStatus(<?= $c['id'] ?>, this.value, this)">
                                    <?php foreach (['pending','acknowledged','in_progress','resolved','rejected'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>>
                                        <?= ucwords(str_replace('_',' ',$s)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="font-size:0.78rem; color:var(--gray-500);"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <a href="complaint_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($complaints)): ?>
                        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem;">No complaints found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const BASE_URL = '<?= BASE_URL ?>';
async function updateStatus(id, status, el) {
    const notes = status === 'resolved' ? prompt('Resolution notes (optional):') : '';
    try {
        const res = await fetch(`${BASE_URL}/api/update_status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ complaint_id: id, status, notes: notes || '' })
        });
        const data = await res.json();
        if (data.success) {
            ToastManager.show(`Status updated to ${status.replace('_',' ')}`, 'success');
        } else {
            ToastManager.show(data.message || 'Update failed', 'error');
        }
    } catch {
        ToastManager.show('Network error', 'error');
    }
}
</script>
