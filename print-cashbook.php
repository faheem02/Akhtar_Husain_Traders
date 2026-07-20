<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$filterDateFrom = $_GET['from'] ?? '';
$filterDateTo = $_GET['to'] ?? '';
$filterCustomer = trim($_GET['customer'] ?? '');

$where = [];
$params = [];

if (!empty($filterDateFrom)) {
    $where[] = "entry_date >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $where[] = "entry_date <= ?";
    $params[] = $filterDateTo;
}
if (!empty($filterCustomer)) {
    $where[] = "customer_name LIKE ?";
    $params[] = '%' . $filterCustomer . '%';
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM cash_entries $whereSQL ORDER BY entry_date ASC, created_at ASC");
$stmt->execute($params);
$entries = $stmt->fetchAll();

$summaryStmt = $pdo->prepare("SELECT 
    COALESCE(SUM(CASE WHEN entry_type IN ('cash_in','adjustment_in') THEN amount ELSE 0 END), 0) as total_in,
    COALESCE(SUM(CASE WHEN entry_type IN ('cash_out','adjustment_out') THEN amount ELSE 0 END), 0) as total_out,
    COUNT(*) as total_entries
    FROM cash_entries $whereSQL");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$periodOpening = 0;
if (!empty($filterDateFrom)) {
    $periodOpening = getOpeningBalance($filterDateFrom);
} elseif (!empty($filterCustomer)) {
    $periodOpening = getOpeningBalance(getTodayDate());
} elseif (!empty($entries)) {
    $periodOpening = getOpeningBalance($entries[0]['entry_date']);
}

$runningBalance = $periodOpening;
foreach ($entries as &$entry) {
    if (in_array($entry['entry_type'], ['cash_in', 'adjustment_in'])) {
        $runningBalance += floatval($entry['amount']);
    } else {
        $runningBalance -= floatval($entry['amount']);
    }
    $entry['running_balance'] = $runningBalance;
}
unset($entry);

$periodClosing = $periodOpening + $summary['total_in'] - $summary['total_out'];

$companyName = getSetting('company_name');
$companyPhone = getSetting('company_phone');
$companyAddress = getSetting('company_address');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Book - <?= $companyName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #fff; margin: 0; padding: 0; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 10mm; font-size: 10pt; }
            .print-container { max-width: 100%; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="padding: 15px 30px; background: #f0f2f5; display: flex; justify-content: center; align-items: center; gap: 12px;">
        <button onclick="window.print()" class="btn btn-print"><i class="bi bi-printer"></i> Print Now</button>
        <button onclick="window.close()" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Close</button>
    </div>

    <div class="print-container" style="padding: 20px 30px;">
        <div class="print-header">
            <h1><?= sanitize($companyName) ?></h1>
            <div class="phone"><?= sanitize($companyPhone) ?></div>
            <div class="address"><?= sanitize($companyAddress) ?></div>
        </div>

        <div class="print-title">CASH BOOK</div>
        <div class="print-date">
            <?php if (!empty($filterDateFrom) || !empty($filterDateTo)): ?>
                <?php if (!empty($filterDateFrom) && !empty($filterDateTo)): ?>
                    From <?= date('d M Y', strtotime($filterDateFrom)) ?> to <?= date('d M Y', strtotime($filterDateTo)) ?>
                <?php elseif (!empty($filterDateFrom)): ?>
                    From <?= date('d M Y', strtotime($filterDateFrom)) ?> onwards
                <?php else: ?>
                    Up to <?= date('d M Y', strtotime($filterDateTo)) ?>
                <?php endif; ?>
            <?php else: ?>
                All Entries
            <?php endif; ?>
            <?php if (!empty($filterCustomer)): ?>
                | Customer: <?= sanitize($filterCustomer) ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($entries)): ?>
        <?php $netTotal = $summary['total_in'] - $summary['total_out']; ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f0f2f5; border-radius:6px; margin-top:6px; font-size:0.9rem; flex-wrap:wrap; gap:4px;">
            <span style="font-weight:600;">Opening: <?= formatCurrency($periodOpening) ?></span>
            <span style="font-weight:600;">Entries: <?= $summary['total_entries'] ?></span>
            <span style="font-weight:700; color:#1a5276;">
                Total Amount: <?= $netTotal >= 0 ? '+' : '-' ?><?= formatCurrency(abs($netTotal)) ?>
            </span>
        </div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
            <thead>
                <tr style="background: #1a5276; color: #fff;">
                    <th style="padding: 8px 10px; text-align: center;">#</th>
                    <th style="padding: 8px 10px;">Date</th>
                    <th style="padding: 8px 10px;">Customer</th>
                    <th style="padding: 8px 10px;">Type</th>
                    <th style="padding: 8px 10px; text-align: right;">Amount (PKR)</th>
                    <th style="padding: 8px 10px;">Description</th>
                    <th style="padding: 8px 10px; text-align: right;">Balance (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $i => $entry): ?>
                <tr style="border-bottom: 1px solid #eee; <?= $i % 2 === 0 ? 'background: #fafafa;' : '' ?>">
                    <td style="padding: 8px 10px; text-align: center;"><?= $i + 1 ?></td>
                    <td style="padding: 8px 10px;"><?= date('d/m/Y', strtotime($entry['entry_date'])) ?></td>
                    <td style="padding: 8px 10px; font-weight: 600;"><?= sanitize($entry['customer_name']) ?></td>
                    <td style="padding: 8px 10px;">
                        <?php if ($entry['entry_type'] === 'cash_in'): ?>
                            Cash In
                        <?php elseif ($entry['entry_type'] === 'adjustment_in'): ?>
                            <span style="color:#e67e22; font-weight:600;">Adjustment (+)</span>
                        <?php elseif ($entry['entry_type'] === 'adjustment_out'): ?>
                            <span style="color:#c0392b; font-weight:600;">Adjustment (-)</span>
                        <?php else: ?>
                            Cash Out
                        <?php endif; ?>
                    </td>
                    <td style="padding: 8px 10px; text-align: right; font-weight: 600; color: <?= in_array($entry['entry_type'], ['cash_in','adjustment_in']) ? '#27ae60' : '#e74c3c' ?>;">
                        <?= in_array($entry['entry_type'], ['cash_in','adjustment_in']) ? '+' : '-' ?><?= number_format($entry['amount'], 2) ?>
                    </td>
                    <td style="padding: 8px 10px; color: #666;"><?= sanitize($entry['description'] ?: '-') ?></td>
                    <td style="padding: 8px 10px; text-align: right; font-weight: 800; color: <?= $entry['running_balance'] >= 0 ? '#2c3e50' : '#e74c3c' ?>;">
                        <?= number_format($entry['running_balance'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 30px; color: #aaa; border: 1px dashed #ddd; margin-top: 20px;">
            No entries found
        </div>
        <?php endif; ?>

        <div style="margin-top: 40px; border-top: 2px solid #eee; padding-top: 15px; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 45%; border-top: 1px solid #333; padding-top: 10px; font-size: 0.82rem; color: #555;">Authorized Signature</div>
            <div style="text-align: right; width: 45%;">
                <div style="border-top: none; text-align: right; font-size: 0.82rem; color: #555;">Prepared By</div>
                <div style="font-size: 0.78rem; color: #999; margin-top: 5px;">Printed on: <?= date('d M Y h:i A') ?></div>
            </div>
        </div>
    </div>
</body>
</html>
