<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$msg = '';
$msgType = 'success';

// Add officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_officer'])) {
    $username  = htmlspecialchars(strip_tags(trim($_POST['username'] ?? '')));
    $password  = $_POST['password'] ?? '';
    $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $full_name = htmlspecialchars(strip_tags(trim($_POST['full_name'] ?? '')));
    $ward_id   = !empty($_POST['ward_id']) ? (int)$_POST['ward_id'] : null;
    $role      = in_array($_POST['role'] ?? '', ['officer','admin']) ? $_POST['role'] : 'officer';

    if (!$username || !$password || !$email) {
        $msg = 'Username, password and email are required.';
        $msgType = 'danger';
    } elseif (strlen($password) < 8) {
        $msg = 'Password must be at least 8 characters.';
        $msgType = 'danger';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $db->prepare("INSERT INTO officers (ward_id, username, password_hash, email, full_name, role) VALUES (:wid,:u,:p,:e,:fn,:r)");
            $stmt->execute(['wid'=>$ward_id,'u'=>$username,'p'=>$hash,'e'=>$email,'fn'=>$full_name,'r'=>$role]);
            $msg = "Officer '{$username}' added successfully.";
        } catch (PDOException $e) {
            $msg = 'Username already exists.';
            $msgType = 'danger';
        }
    }
}

$officers = $db->query("SELECT o.*, w.ward_name FROM officers o LEFT JOIN wards w ON o.ward_id = w.id ORDER BY o.role DESC, o.full_name")->fetchAll();
$wards    = $db->query("SELECT id, ward_number, ward_name FROM wards ORDER BY ward_number")->fetchAll();

$pageTitle = 'Manage Officers';
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
            <a href="wards.php"><i class="fas fa-map"></i> Wards</a>
            <a href="officers.php" class="active"><i class="fas fa-users"></i> Officers</a>
            <a href="/civic-reporter/index.php"><i class="fas fa-home"></i> Citizen Portal</a>
            <a href="logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:1.5rem;"><i class="fas fa-users"></i> Manage Officers</h1>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:1.5rem;">
            <!-- Officers List -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-list"></i> Officer Accounts</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Ward</th>
                                <th>Role</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($officers as $o): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($o['username']) ?></code></td>
                                <td><?= htmlspecialchars($o['full_name'] ?? '—') ?></td>
                                <td style="font-size:0.78rem; color:var(--primary);"><?= htmlspecialchars($o['email']) ?></td>
                                <td style="font-size:0.82rem;"><?= htmlspecialchars($o['ward_name'] ?? 'All Wards') ?></td>
                                <td>
                                    <span class="badge <?= $o['role'] === 'admin' ? 'badge-critical' : 'badge-acknowledged' ?>">
                                        <?= $o['role'] ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem; color:var(--gray-500);">
                                    <?= $o['last_login'] ? date('d M Y H:i', strtotime($o['last_login'])) : 'Never' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Officer Form -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-user-plus"></i> Add Officer</span>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username <span class="required">*</span></label>
                        <input type="text" class="form-control" name="username" placeholder="ward1_officer" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" placeholder="Officer Full Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" class="form-control" name="email" placeholder="officer@civic.local" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <input type="password" class="form-control" name="password" placeholder="Min 8 characters" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assign Ward</label>
                        <select class="form-control" name="ward_id">
                            <option value="">-- No specific ward (admin) --</option>
                            <?php foreach ($wards as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['ward_number'] . ' - ' . $w['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role">
                            <option value="officer">Ward Officer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="add_officer" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Add Officer
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
