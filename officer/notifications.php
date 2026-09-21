<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../classes/Notifier.php';

$notifier = new Notifier();
$wardId   = ($_SESSION['officer_role'] === 'admin') ? null : $_SESSION['officer_ward_id'];
$notifications = $notifier->getNotifications($wardId, 100);

$pageTitle = 'Notifications';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h3><?= APP_NAME ?></h3></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="complaints.php"><i class="fas fa-clipboard-list"></i> All Complaints</a>
            <a href="clusters.php"><i class="fas fa-layer-group"></i> Clusters</a>
            <a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a>
            <?php if ($_SESSION['officer_role'] === 'admin'): ?>
            <a href="wards.php"><i class="fas fa-map"></i> Wards</a>
            <?php endif; ?>
            <a href="/civic-reporter/index.php"><i class="fas fa-home"></i> Citizen Portal</a>
            <a href="logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <h1 style="font-size:1.5rem; font-weight:700;"><i class="fas fa-bell"></i> Officer Notifications Log</h1>
            <span class="badge badge-high" style="font-size:0.9rem; padding:0.4rem 1rem;"><?= count($notifications) ?> total</span>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ward</th>
                            <th>Officer</th>
                            <th>Category</th>
                            <th>Reports</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td><code>#<?= $n['id'] ?></code></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($n['ward_name'] ?? '—') ?></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($n['officer_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $n['category'] ?>"><?= ucwords(str_replace('_',' ',$n['category'])) ?></span></td>
                            <td><strong><?= $n['complaint_count'] ?></strong></td>
                            <td>
                                <span class="badge <?= $n['status'] === 'sent' ? 'badge-resolved' : 'badge-high' ?>">
                                    <?= $n['status'] ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem; color:var(--gray-600);">
                                <?= date('d M Y H:i', strtotime($n['sent_at'])) ?>
                            </td>
                            <td>
                                <?php if ($n['cluster_id']): ?>
                                <a href="complaints.php?cluster_id=<?= $n['cluster_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-layer-group"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($notifications)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">No notifications sent yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
