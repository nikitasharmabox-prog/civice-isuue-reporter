<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../classes/Complaint.php';
require_once __DIR__ . '/../classes/GeoCluster.php';
require_once __DIR__ . '/../classes/Notifier.php';

$complaintObj = new Complaint();
$clusterObj   = new GeoCluster();
$notifier     = new Notifier();

$wardId = ($_SESSION['officer_role'] === 'admin') ? null : $_SESSION['officer_ward_id'];

$filters = [];
if ($wardId) $filters['ward_id'] = $wardId;
if (!empty($_GET['status']))   $filters['status'] = $_GET['status'];
if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];

$complaints  = $complaintObj->getAll($filters, 100);
$clusters    = $clusterObj->getAllClusters();
if ($wardId) {
    $clusters = array_filter($clusters, fn($c) => $c['ward_id'] == $wardId);
}
$stats       = $complaintObj->getStats();
$notifications = $notifier->getNotifications($wardId, 10);

$pageTitle = 'Officer Dashboard';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h3><?= APP_NAME ?></h3>
            <p><?= htmlspecialchars($_SESSION['officer_name'] ?? 'Officer') ?></p>
            <span style="font-size:0.72rem; color:rgba(255,255,255,0.4); text-transform:uppercase;">
                <?= $_SESSION['officer_role'] === 'admin' ? 'Admin' : ($_SESSION['officer_ward'] ?? 'Ward Officer') ?>
            </span>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Main</div>
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="complaints.php"><i class="fas fa-clipboard-list"></i> All Complaints</a>
            <a href="clusters.php"><i class="fas fa-layer-group"></i> Clusters</a>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>

            <?php if ($_SESSION['officer_role'] === 'admin'): ?>
            <div class="sidebar-section-label">Admin</div>
            <a href="wards.php"><i class="fas fa-map"></i> Wards</a>
            <a href="officers.php"><i class="fas fa-users"></i> Officers</a>
            <?php endif; ?>

            <div class="sidebar-section-label">Links</div>
            <a href="/civic-reporter/index.php"><i class="fas fa-home"></i> Citizen Portal</a>
            <a href="/civic-reporter/map.php"><i class="fas fa-map-marked-alt"></i> Live Map</a>
            <a href="logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div style="margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.2rem;">Dashboard</h1>
                <p class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['officer_name'] ?? 'Officer') ?> &bull; <?= date('D, d M Y') ?></p>
            </div>
            <?php if ($_SESSION['officer_role'] === 'admin'): ?>
            <a href="/civic-reporter/officer/complaints.php" class="btn btn-primary btn-sm">
                <i class="fas fa-list"></i> View All Complaints
            </a>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--primary)"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-icon" style="color:var(--warning)"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $stats['by_status']['pending'] ?? 0 ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card" style="border-top-color:#8b5cf6">
                <div class="stat-icon" style="color:#8b5cf6"><i class="fas fa-tools"></i></div>
                <div class="stat-number"><?= $stats['by_status']['in_progress'] ?? 0 ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon" style="color:var(--success)"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $stats['by_status']['resolved'] ?? 0 ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card stat-danger">
                <div class="stat-icon" style="color:var(--danger)"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?= $stats['critical_clusters'] ?></div>
                <div class="stat-label">Critical Clusters</div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-icon" style="color:var(--info)"><i class="fas fa-bell"></i></div>
                <div class="stat-number"><?= $stats['notifications_today'] ?></div>
                <div class="stat-label">Alerts Today</div>
            </div>
        </div>

        <!-- Recent Clusters & Notifications Grid -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
            <!-- Active Clusters -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-layer-group"></i> Active Clusters</span>
                    <a href="clusters.php" style="font-size:0.8rem; color:var(--primary);">View all</a>
                </div>
                <?php
                $activeClusters = array_filter($clusters, fn($c) => $c['status'] !== 'resolved');
                $activeClusters = array_slice(array_values($activeClusters), 0, 5);
                if ($activeClusters): ?>
                <?php foreach ($activeClusters as $cl): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0; border-bottom:1px solid var(--gray-100);">
                    <div>
                        <span style="font-weight:600; font-size:0.875rem;"><?= ucwords(str_replace('_',' ',$cl['category'])) ?></span>
                        <p style="font-size:0.75rem; color:var(--gray-500);"><?= htmlspecialchars($cl['ward_name'] ?? '—') ?> &bull; <?= $cl['complaint_count'] ?> reports</p>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge-<?= $cl['severity'] ?>"><?= $cl['severity'] ?></span><br>
                        <a href="complaints.php?cluster_id=<?= $cl['id'] ?>" style="font-size:0.72rem; color:var(--primary);">View</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-muted" style="padding:1rem;">No active clusters.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Notifications -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-bell"></i> Recent Alerts</span>
                    <a href="notifications.php" style="font-size:0.8rem; color:var(--primary);">View all</a>
                </div>
                <?php if ($notifications): ?>
                <?php foreach (array_slice($notifications, 0, 5) as $n): ?>
                <div style="padding:0.75rem 0; border-bottom:1px solid var(--gray-100);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <span style="font-size:0.82rem; font-weight:600;"><?= htmlspecialchars($n['ward_name'] ?? '—') ?></span>
                        <span class="badge <?= $n['status'] === 'sent' ? 'badge-resolved' : 'badge-high' ?>"><?= $n['status'] ?></span>
                    </div>
                    <p style="font-size:0.75rem; color:var(--gray-500);"><?= $n['complaint_count'] ?> <?= $n['category'] ?> reports &bull; <?= date('d M, H:i', strtotime($n['sent_at'])) ?></p>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-muted" style="padding:1rem;">No notifications yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Complaints Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-clipboard-list"></i> Recent Complaints</span>
                <a href="complaints.php" style="font-size:0.8rem; color:var(--primary);">View all</a>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Category</th>
                            <th>Address</th>
                            <th>Ward</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($complaints, 0, 15) as $c): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($c['tracking_id']) ?></code></td>
                            <td><span class="badge badge-<?= $c['category'] ?>"><?= ucwords(str_replace('_',' ',$c['category'])) ?></span></td>
                            <td><span class="truncate" title="<?= htmlspecialchars($c['address'] ?? '') ?>"><?= htmlspecialchars($c['address'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($c['ward_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $c['priority'] ?>"><?= $c['priority'] ?></span></td>
                            <td><span class="badge badge-<?= $c['status'] ?>"><?= str_replace('_',' ',$c['status']) ?></span></td>
                            <td style="font-size:0.78rem; color:var(--gray-500);"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <a href="complaint_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($complaints)): ?>
                        <tr><td colspan="8" class="text-muted text-center" style="padding:2rem;">No complaints found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
