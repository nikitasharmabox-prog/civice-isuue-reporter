<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../classes/GeoCluster.php';
require_once __DIR__ . '/../classes/Notifier.php';

$clusterObj = new GeoCluster();
$notifier   = new Notifier();

$wardId   = ($_SESSION['officer_role'] === 'admin') ? null : $_SESSION['officer_ward_id'];
$clusters = $clusterObj->getAllClusters();
if ($wardId) {
    $clusters = array_filter(array_values($clusters), fn($c) => $c['ward_id'] == $wardId);
}

// Manual re-notify
if (isset($_POST['notify_cluster'])) {
    $cid = (int)$_POST['cluster_id'];
    $notifier->sendClusterNotification($cid);
    $notifyMsg = 'Notification sent to ward officer.';
}

$pageTitle = 'Clusters';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h3><?= APP_NAME ?></h3></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="complaints.php"><i class="fas fa-clipboard-list"></i> All Complaints</a>
            <a href="clusters.php" class="active"><i class="fas fa-layer-group"></i> Clusters</a>
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
            <h1 style="font-size:1.5rem; font-weight:700;"><i class="fas fa-layer-group"></i> Complaint Clusters</h1>
            <a href="/civic-reporter/map.php" class="btn btn-outline-primary btn-sm" target="_blank">
                <i class="fas fa-map-marked-alt"></i> View on Map
            </a>
        </div>

        <?php if (!empty($notifyMsg)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $notifyMsg ?></div>
        <?php endif; ?>

        <!-- Severity summary -->
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.5rem;">
            <?php
            $sevCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            foreach ($clusters as $c) {
                if ($c['status'] !== 'resolved') $sevCounts[$c['severity']] = ($sevCounts[$c['severity']] ?? 0) + 1;
            }
            $sevConf = [
                'critical' => ['#7f1d1d', '#fee2e2', 'Critical'],
                'high'     => ['var(--danger)', '#fee2e2', 'High'],
                'medium'   => ['var(--warning)', '#fef3c7', 'Medium'],
                'low'      => ['var(--gray-500)', 'var(--gray-100)', 'Low'],
            ];
            foreach ($sevConf as $sev => [$color, $bg, $label]):
            ?>
            <div class="stat-card" style="border-top-color:<?= $color ?>; background:<?= $bg ?>;">
                <div class="stat-number" style="color:<?= $color ?>;"><?= $sevCounts[$sev] ?></div>
                <div class="stat-label"><?= $label ?> Severity</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Ward</th>
                            <th>Reports</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Notified</th>
                            <th>Last Report</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clusters as $cl): ?>
                        <tr>
                            <td><code>#<?= $cl['id'] ?></code></td>
                            <td><span class="badge badge-<?= $cl['category'] ?>"><?= ucwords(str_replace('_',' ',$cl['category'])) ?></span></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($cl['ward_name'] ?? '—') ?></td>
                            <td>
                                <strong style="font-size:1.1rem; color:var(--primary);"><?= $cl['complaint_count'] ?></strong>
                            </td>
                            <td><span class="badge badge-<?= $cl['severity'] ?>"><?= $cl['severity'] ?></span></td>
                            <td><span class="badge badge-<?= $cl['status'] ?>"><?= str_replace('_',' ',$cl['status']) ?></span></td>
                            <td>
                                <?php if ($cl['notification_sent']): ?>
                                <span style="color:var(--success); font-size:0.82rem;">
                                    <i class="fas fa-check-circle"></i>
                                    <?= $cl['notification_sent_at'] ? date('d M H:i', strtotime($cl['notification_sent_at'])) : 'Yes' ?>
                                </span>
                                <?php else: ?>
                                <span style="color:var(--gray-400); font-size:0.82rem;"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem; color:var(--gray-500);">
                                <?= date('d M Y H:i', strtotime($cl['last_complaint_at'])) ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                    <a href="complaints.php?cluster_id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-primary" title="View complaints">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($cl['status'] !== 'resolved'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="cluster_id" value="<?= $cl['id'] ?>">
                                        <button type="submit" name="notify_cluster" class="btn btn-sm btn-warning" title="Re-send notification"
                                                onclick="return confirm('Send notification to ward officer?')">
                                            <i class="fas fa-bell"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="https://www.google.com/maps?q=<?= $cl['center_lat'] ?>,<?= $cl['center_lng'] ?>"
                                       target="_blank" class="btn btn-sm" style="background:var(--gray-200); color:var(--gray-700);" title="View on map">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clusters)): ?>
                        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem;">No clusters found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
