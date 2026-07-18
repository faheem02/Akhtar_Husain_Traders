<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}

function formatCurrency($amount) {
    return 'PKR ' . number_format($amount, 2);
}

function getTodayDate() {
    return date('Y-m-d');
}

function getOpeningBalance($date) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT opening_date, opening_balance FROM daily_opening WHERE opening_date <= ? ORDER BY opening_date DESC LIMIT 1");
    $stmt->execute([$date]);
    $row = $stmt->fetch();
    $baseBalance = $row ? floatval($row['opening_balance']) : 0;

    $baseDate = $row ? $row['opening_date'] : '2000-01-01';

    $stmt2 = $pdo->prepare("SELECT 
        COALESCE(SUM(CASE WHEN entry_type IN ('cash_in','adjustment_in') THEN amount ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN entry_type IN ('cash_out','adjustment_out') THEN amount ELSE 0 END), 0) as total_out
        FROM cash_entries WHERE entry_date >= ? AND entry_date < ?");
    $stmt2->execute([$baseDate, $date]);
    $row2 = $stmt2->fetch();

    return $baseBalance + $row2['total_in'] - $row2['total_out'];
}

function getClosingBalance($date) {
    global $pdo;

    $opening = getOpeningBalance($date);

    $stmt = $pdo->prepare("SELECT 
        COALESCE(SUM(CASE WHEN entry_type IN ('cash_in','adjustment_in') THEN amount ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN entry_type IN ('cash_out','adjustment_out') THEN amount ELSE 0 END), 0) as total_out
        FROM cash_entries WHERE entry_date = ?");
    $stmt->execute([$date]);
    $row = $stmt->fetch();

    return $opening + $row['total_in'] - $row['total_out'];
}

function getTodaySummary($date) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 
        COALESCE(SUM(CASE WHEN entry_type IN ('cash_in','adjustment_in') THEN amount ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN entry_type IN ('cash_out','adjustment_out') THEN amount ELSE 0 END), 0) as total_out,
        COUNT(*) as total_entries
        FROM cash_entries WHERE entry_date = ?");
    $stmt->execute([$date]);
    return $stmt->fetch();
}

function getUniqueCustomers() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT customer_name FROM cash_entries ORDER BY customer_name ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getLatestClosingBalance() {
    global $pdo;
    $stmt = $pdo->query("SELECT opening_date FROM daily_opening ORDER BY opening_date DESC LIMIT 1");
    $row = $stmt->fetch();
    if ($row) {
        return getClosingBalance($row['opening_date']);
    }
    return 0;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
