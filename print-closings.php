<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$sql = "SELECT * FROM daily_closings WHERE 1=1";
$params = [];

if ($from !== '') {
    $sql .= " AND closing_date >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $sql .= " AND closing_date <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY closing_date ASC, closed_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$closings = $stmt->fetchAll();

$sumOpening = 0;
$sumIn = 0;
$sumOut = 0;
$sumClosing = 0;

foreach ($closings as $c) {
    $sumOpening += floatval($c['opening_balance']);
    $sumIn += floatval($c['total_in']);
    $sumOut += floatval($c['total_out']);
    $sumClosing += floatval($c['closing_balance']);
}

$companyName = getSetting('company_name');
$companyPhone = getSetting('company_phone');
$companyAddress = getSetting('company_address');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Closings Report - <?= $companyName ?></title>
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

        <div class="print-title">DAILY CLOSINGS REPORT</div>
        <div class="print-date">
            <?php if (!empty($from) || !empty($to)): ?>
                <?php if (!empty($from) && !empty($to)): ?>
                    From <?= date('d M Y', strtotime($from)) ?> to <?= date('d M Y', strtotime($to)) ?>
                <?php elseif (!empty($from)): ?>
                    From <?= date('d M Y', strtotime($from)) ?> onwards
                <?php else: ?>
                    Up to <?= date('d M Y', strtotime($to)) ?>
                <?php endif; ?>
            <?php else: ?>
                All Recorded Closings (<?= count($closings) ?> Days)
            <?php endif; ?>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="background: #1a5276; color: #fff;">
                    <th style="padding: 8px 10px; text-align: center;">#</th>
                    <th style="padding: 8px 10px;">Date</th>
                    <th style="padding: 8px 10px; text-align: right;">Opening</th>
                    <th style="padding: 8px 10px; text-align: right;">Total In</th>
                    <th style="padding: 8px 10px; text-align: right;">Total Out</th>
                    <th style="padding: 8px 10px; text-align: right;">Closing Balance</th>
                    <th style="padding: 8px 10px; text-align: center;">Closed At</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 0; foreach ($closings as $c): $i++; ?>
                <tr style="border-bottom: 1px solid #eee; <?= $i % 2 === 0 ? 'background: #fafafa;' : '' ?>">
                    <td style="padding: 8px 10px; text-align: center;"><?= $i ?></td>
                    <td style="padding: 8px 10px; font-weight: 600;"><?= date('d/m/Y (D)', strtotime($c['closing_date'])) ?></td>
                    <td style="padding: 8px 10px; text-align: right;"><?= formatCurrency($c['opening_balance']) ?></td>
                    <td style="padding: 8px 10px; text-align: right; color: #27ae60; font-weight: 600;"><?= formatCurrency($c['total_in']) ?></td>
                    <td style="padding: 8px 10px; text-align: right; color: #e74c3c; font-weight: 600;"><?= formatCurrency($c['total_out']) ?></td>
                    <td style="padding: 8px 10px; text-align: right; font-weight: 700; color: #1a5276;"><?= formatCurrency($c['closing_balance']) ?></td>
                    <td style="padding: 8px 10px; text-align: center; font-size: 0.85rem; color: #666;"><?= date('h:i A', strtotime($c['closed_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid #1a5276; font-weight: 700; background: #eaf2f8;">
                    <td colspan="2" style="padding: 8px 10px; text-align: right;">Total (Period):</td>
                    <td style="padding: 8px 10px; text-align: right;"><?= formatCurrency($sumOpening) ?></td>
                    <td style="padding: 8px 10px; text-align: right; color: #27ae60;"><?= formatCurrency($sumIn) ?></td>
                    <td style="padding: 8px 10px; text-align: right; color: #e74c3c;"><?= formatCurrency($sumOut) ?></td>
                    <td style="padding: 8px 10px; text-align: right; color: #1a5276;"><?= formatCurrency($sumClosing) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 30px; font-size: 0.8rem; color: #999; text-align: right;">
            Printed on <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</body>
</html>
