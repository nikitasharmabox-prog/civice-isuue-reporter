<?php
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>" class="nav-brand">
            <i class="fas fa-city"></i>
            <span><?= APP_NAME ?></span>
        </a>
        <div class="nav-links">
            <a href="<?= BASE_URL ?>/index.php"><i class="fas fa-home"></i> Home</a>
            <a href="<?= BASE_URL ?>/report.php"><i class="fas fa-plus-circle"></i> Report Issue</a>
            <a href="<?= BASE_URL ?>/track.php"><i class="fas fa-search"></i> Track</a>
            <a href="<?= BASE_URL ?>/map.php"><i class="fas fa-map-marked-alt"></i> Live Map</a>
            <a href="<?= BASE_URL ?>/officer/login.php" class="btn-nav-login"><i class="fas fa-user-shield"></i> Officer Login</a>
        </div>
        <button class="nav-toggle" id="navToggle"><i class="fas fa-bars"></i></button>
    </div>
</nav>
