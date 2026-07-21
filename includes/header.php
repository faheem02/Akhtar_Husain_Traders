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
    <link href="assets/css/style.css?v=2" rel="stylesheet">
</head>
<body>

<div class="main-content" id="mainContent">
    <nav class="topbar no-print">
        <div class="topbar-date">
            <i class="bi bi-calendar3"></i> <?= date('l, d M Y') ?>
        </div>
        <div class="topbar-user">
            <i class="bi bi-person-circle"></i> <?= sanitize($_SESSION['username'] ?? 'Admin') ?>
            <a href="logout.php" class="topbar-logout"><i class="bi bi-box-arrow-right"></i></a>
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
