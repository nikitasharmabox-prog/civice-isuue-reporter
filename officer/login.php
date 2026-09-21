<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: /civic-reporter/officer/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $result = login($username, $password);
        if ($result['success']) {
            header('Location: /civic-reporter/officer/dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
$pageTitle = 'Officer Login';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <i class="fas fa-user-shield"></i>
            <h2>Officer Portal</h2>
            <p>Ward Officer &amp; Admin Login</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-2">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="Enter username" autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div style="position:relative;">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Enter password" autocomplete="current-password" style="padding-right:2.75rem;">
                    <button type="button" onclick="togglePass()" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);">
                        <i class="fas fa-eye" id="passIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:0.5rem;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <p style="text-align:center; margin-top:1.5rem; font-size:0.8rem; color:var(--gray-400);">
            Default admin: <code>admin</code> / <code>password</code><br>
            <em>Change in production!</em>
        </p>

        <div style="text-align:center; margin-top:1rem;">
            <a href="/civic-reporter/index.php" style="font-size:0.875rem; color:var(--gray-500);">
                <i class="fas fa-arrow-left"></i> Back to Citizen Portal
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function togglePass() {
    const p = document.getElementById('password');
    const i = document.getElementById('passIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash'; }
    else { p.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
