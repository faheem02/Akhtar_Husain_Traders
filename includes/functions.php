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
    return number_format(round($amount, 2), 2);
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

    return round($baseBalance + $row2['total_in'] - $row2['total_out'], 2);
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

    return round($opening + $row['total_in'] - $row['total_out'], 2);
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

function findOrCreateCustomer($name, $phone = '', $openingBalance = 0) {
    global $pdo;
    $name = trim($name);
    if ($name === '') return null;
    $stmt = $pdo->prepare("SELECT id, name, phone, opening_balance FROM customers WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, opening_balance) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, floatval($openingBalance)]);
    return [
        'id' => $pdo->lastInsertId(),
        'name' => $name,
        'phone' => $phone,
        'opening_balance' => floatval($openingBalance),
    ];
}

function getAllCustomers() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT c.*,
            COALESCE(SUM(CASE WHEN e.entry_type IN ('cash_out','adjustment_out') THEN e.amount ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN e.entry_type IN ('cash_in','adjustment_in') THEN e.amount ELSE 0 END), 0) as total_credit,
            COUNT(e.id) as entry_count,
            MAX(e.entry_date) as last_entry_date
        FROM customers c
        LEFT JOIN cash_entries e ON e.customer_id = c.id
        GROUP BY c.id
        ORDER BY last_entry_date DESC, c.name ASC
    ");
    return $stmt->fetchAll();
}

function getCustomerLedger($customerId, $from = '', $to = '') {
    global $pdo;
    $customerId = intval($customerId);
    $sql = "SELECT * FROM cash_entries WHERE customer_id = ?";
    $params = [$customerId];
    if ($from !== '') {
        $sql .= " AND entry_date >= ?";
        $params[] = $from;
    }
    if ($to !== '') {
        $sql .= " AND entry_date <= ?";
        $params[] = $to;
    }
    $sql .= " ORDER BY entry_date ASC, created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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
