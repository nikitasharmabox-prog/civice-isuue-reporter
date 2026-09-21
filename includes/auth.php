<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(false);
}

function isLoggedIn(): bool {
    return !empty($_SESSION['officer_id']);
}

function requireLogin(string $redirect = '/civic-reporter/officer/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['officer_role'] !== 'admin') {
        header('Location: /civic-reporter/officer/dashboard.php');
        exit;
    }
}

function login(string $username, string $password): array {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT o.*, w.ward_name FROM officers o LEFT JOIN wards w ON o.ward_id = w.id WHERE o.username = :u");
    $stmt->execute(['u' => $username]);
    $officer = $stmt->fetch();

    if (!$officer || !password_verify($password, $officer['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    $_SESSION['officer_id']      = $officer['id'];
    $_SESSION['officer_name']    = $officer['full_name'];
    $_SESSION['officer_email']   = $officer['email'];
    $_SESSION['officer_role']    = $officer['role'];
    $_SESSION['officer_ward_id'] = $officer['ward_id'];
    $_SESSION['officer_ward']    = $officer['ward_name'] ?? 'All Wards';

    // Update last login
    $db->prepare("UPDATE officers SET last_login = NOW() WHERE id = :id")->execute(['id' => $officer['id']]);

    return ['success' => true, 'role' => $officer['role']];
}

function logout(): void {
    session_destroy();
    header('Location: /civic-reporter/officer/login.php');
    exit;
}
