<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$opening = floatval($_POST['opening'] ?? 0);

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

$existing = $pdo->prepare("SELECT id FROM customers WHERE name = ?");
$existing->execute([$name]);
if ($existing->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Customer already exists']);
    exit;
}

$ins = $pdo->prepare("INSERT INTO customers (name, phone, opening_balance) VALUES (?, ?, ?)");
$ins->execute([$name, $phone, $opening]);
$id = $pdo->lastInsertId();

echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
