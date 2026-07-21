<?php
$pageTitle = 'Print Customer Ledger';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$cid = intval($_GET['id'] ?? 0);
if ($cid <= 0) {
    header('Location: customers.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$cid]);
$customer = $stmt->fetch();
if (!$customer) {
    header('Location: customers.php');
    exit;
}

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$entries = getCustomerLedger($cid, $from, $to);
$opening = floatval($customer['opening_balance']);
$running = $opening;
$debit = 0; $credit = 0;
foreach ($entries as &$entry) {
    if (in_array($entry['entry_type'], ['cash_out', 'adjustment_out'])) {
        $running += $entry['amount'];
        $debit += $entry['amount'];
    } else {
        $running -= $entry['amount'];
        $credit += $entry['amount'];
    }
    $entry['running_balance'] = $running;
}
unset($entry);
$net = $running;

$companyName = getSetting('company_name');
$companyAddress = getSetting('company_address');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($companyName) ?> - Customer Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        @media print { .no-print { display:none !important; } body { font-size:12px; } table { font-size:11px; } }
        .print-header { text-align:center; margin-bottom:20px; }
        .print-header h3 { margin-bottom:2px; }
        .print-header small { color:#666; }
    </style>
</head>
<body>
    <div class="container mt-3 mb-5">
        <div class="no-print mb-3">
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            <a href="customers.php?id=<?= $cid ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="print-header">
            <h3><?= sanitize($companyName) ?></h3>
            <small><?= sanitize($companyAddress) ?></small>
            <h5 class="mt-3">Customer Ledger: <?= sanitize($customer['name']) ?></h5>
            <div>
                Phone: <?= sanitize($customer['phone'] ?: '-') ?> |
                Opening: <?= formatCurrency($opening) ?> |
                Current Balance: <?= formatCurrency(abs($net)) ?>
            </div>
            <?php if ($from || $to): ?>
            <small>Period: <?= $from ?: 'Start' ?> to <?= $to ?: 'End' ?></small>
            <?php endif; ?>
        </div>

        <?php if (empty($entries)): ?>
        <p class="text-center text-muted">No entries found</p>
        <?php else: ?>
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php $sno = 0; foreach ($entries as $entry): $sno++; ?>
                <tr>
                    <td><?= $sno ?></td>
                    <td><?= date('d M Y', strtotime($entry['entry_date'])) ?></td>
                    <td>
                        <?php if ($entry['entry_type'] === 'cash_in'): ?>Cash In
                        <?php elseif ($entry['entry_type'] === 'cash_out'): ?>Cash Out
                        <?php elseif ($entry['entry_type'] === 'adjustment_in'): ?>Adj (+)
                        <?php else: ?>Adj (-)
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($entry['description'] ?: '-') ?></td>
                    <td class="text-end"><?= in_array($entry['entry_type'], ['cash_out', 'adjustment_out']) ? formatCurrency($entry['amount']) : '-' ?></td>
                    <td class="text-end"><?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? formatCurrency($entry['amount']) : '-' ?></td>
                    <td class="text-end"><?= formatCurrency(abs($entry['running_balance'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end">Total:</td>
                    <td class="text-end"><?= formatCurrency($debit) ?></td>
                    <td class="text-end"><?= formatCurrency($credit) ?></td>
                    <td class="text-end"><?= formatCurrency(abs($net)) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
