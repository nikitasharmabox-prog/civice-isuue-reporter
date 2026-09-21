<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$msg = '';
$msgType = 'success';

// Add/Edit ward
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $ward_number = htmlspecialchars(strip_tags(trim($_POST['ward_number'] ?? '')));
    $ward_name   = htmlspecialchars(strip_tags(trim($_POST['ward_name'] ?? '')));
    $officer_name  = htmlspecialchars(strip_tags(trim($_POST['officer_name'] ?? '')));
    $officer_email = filter_var(trim($_POST['officer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $officer_phone = htmlspecialchars(strip_tags(trim($_POST['officer_phone'] ?? '')));
    $lat_center  = filter_var($_POST['lat_center'] ?? '', FILTER_VALIDATE_FLOAT) ?: null;
    $lng_center  = filter_var($_POST['lng_center'] ?? '', FILTER_VALIDATE_FLOAT) ?: null;
    $radius_km   = filter_var($_POST['radius_km'] ?? 2.5, FILTER_VALIDATE_FLOAT) ?: 2.5;

    if (!$ward_number || !$ward_name || !$officer_email) {
        $msg = 'Ward number, name and officer email are required.';
        $msgType = 'danger';
    } else {
        if ($id) {
            $stmt = $db->prepare("UPDATE wards SET ward_number=:wn, ward_name=:wname, officer_name=:on, officer_email=:oe, officer_phone=:op, lat_center=:lat, lng_center=:lng, radius_km=:r WHERE id=:id");
            $stmt->execute(['wn'=>$ward_number,'wname'=>$ward_name,'on'=>$officer_name,'oe'=>$officer_email,'op'=>$officer_phone,'lat'=>$lat_center,'lng'=>$lng_center,'r'=>$radius_km,'id'=>$id]);
            $msg = "Ward #{$id} updated successfully.";
        } else {
            $stmt = $db->prepare("INSERT INTO wards (ward_number, ward_name, officer_name, officer_email, officer_phone, lat_center, lng_center, radius_km) VALUES (:wn,:wname,:on,:oe,:op,:lat,:lng,:r)");
            $stmt->execute(['wn'=>$ward_number,'wname'=>$ward_name,'on'=>$officer_name,'oe'=>$officer_email,'op'=>$officer_phone,'lat'=>$lat_center,'lng'=>$lng_center,'r'=>$radius_km]);
            $msg = 'Ward added successfully.';
        }
    }
}

$wards = $db->query("SELECT w.*, COUNT(c.id) as complaint_count FROM wards w LEFT JOIN complaints c ON c.ward_id = w.id GROUP BY w.id ORDER BY w.ward_number")->fetchAll();

$editWard = null;
if (!empty($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM wards WHERE id=:id");
    $stmt->execute(['id' => (int)$_GET['edit']]);
    $editWard = $stmt->fetch();
}

$pageTitle = 'Manage Wards';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><h3><?= APP_NAME ?></h3></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="complaints.php"><i class="fas fa-clipboard-list"></i> All Complaints</a>
            <a href="clusters.php"><i class="fas fa-layer-group"></i> Clusters</a>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a href="wards.php" class="active"><i class="fas fa-map"></i> Wards</a>
            <a href="officers.php"><i class="fas fa-users"></i> Officers</a>
            <a href="/civic-reporter/index.php"><i class="fas fa-home"></i> Citizen Portal</a>
            <a href="logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:1.5rem;"><i class="fas fa-map"></i> Manage Wards</h1>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:1.5rem;">
            <!-- Wards Table -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-list"></i> All Wards</span>
                    <span class="badge badge-high"><?= count($wards) ?> wards</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Officer</th>
                                <th>Email</th>
                                <th>Complaints</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wards as $w): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($w['ward_number']) ?></strong></td>
                                <td><?= htmlspecialchars($w['ward_name']) ?></td>
                                <td style="font-size:0.82rem;"><?= htmlspecialchars($w['officer_name']) ?></td>
                                <td style="font-size:0.78rem; color:var(--primary);"><?= htmlspecialchars($w['officer_email']) ?></td>
                                <td><strong><?= $w['complaint_count'] ?></strong></td>
                                <td>
                                    <a href="wards.php?edit=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fas fa-<?= $editWard ? 'edit' : 'plus' ?>"></i>
                        <?= $editWard ? 'Edit Ward' : 'Add New Ward' ?>
                    </span>
                    <?php if ($editWard): ?>
                    <a href="wards.php" class="btn btn-sm" style="background:var(--gray-200); color:var(--gray-700);">Cancel</a>
                    <?php endif; ?>
                </div>
                <form method="POST">
                    <?php if ($editWard): ?>
                    <input type="hidden" name="id" value="<?= $editWard['id'] ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Ward Number <span class="required">*</span></label>
                        <input type="text" class="form-control" name="ward_number" placeholder="W001" required
                               value="<?= htmlspecialchars($editWard['ward_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ward Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="ward_name" placeholder="Ward 1 - Central" required
                               value="<?= htmlspecialchars($editWard['ward_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Officer Name</label>
                        <input type="text" class="form-control" name="officer_name" placeholder="Full name"
                               value="<?= htmlspecialchars($editWard['officer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Officer Email <span class="required">*</span></label>
                        <input type="email" class="form-control" name="officer_email" placeholder="officer@civic.local" required
                               value="<?= htmlspecialchars($editWard['officer_email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Officer Phone</label>
                        <input type="tel" class="form-control" name="officer_phone" placeholder="+91 98765 43210"
                               value="<?= htmlspecialchars($editWard['officer_phone'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Center Latitude</label>
                            <input type="number" class="form-control" name="lat_center" step="any" placeholder="28.6139"
                                   value="<?= htmlspecialchars($editWard['lat_center'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Center Longitude</label>
                            <input type="number" class="form-control" name="lng_center" step="any" placeholder="77.2090"
                                   value="<?= htmlspecialchars($editWard['lng_center'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Radius (km)</label>
                        <input type="number" class="form-control" name="radius_km" step="0.1" min="0.5" max="20"
                               value="<?= htmlspecialchars($editWard['radius_km'] ?? '2.5') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> <?= $editWard ? 'Update Ward' : 'Add Ward' ?>
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
