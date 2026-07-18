<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';

$companyName = getSetting('company_name');
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? $companyName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="sidebar no-print" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-shop"></i>
        <span><?= sanitize($companyName) ?></span>
    </div>

    <ul class="sidebar-nav">
        <li>
            <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="cash-book.php" class="<?= $currentPage === 'cash-book.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark"></i> Cash Book
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<div class="main-content" id="mainContent">
    <nav class="topbar no-print">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-date">
            <i class="bi bi-calendar3"></i> <?= date('l, d M Y') ?>
        </div>
        <div class="topbar-user">
            <i class="bi bi-person-circle"></i> <?= sanitize($_SESSION['username'] ?? 'Admin') ?>
        </div>
    </nav>

    <div class="flash-container no-print">
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> flash-alert alert-dismissible fade show">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= sanitize($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    </div>
