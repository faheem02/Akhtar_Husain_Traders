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
$isPreview = (isset($_GET['preview']) && $_GET['preview'] === '1');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'Ledger_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['name']) . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($output, [$companyName]);
    fputcsv($output, ['Customer Ledger: ' . $customer['name']]);
    fputcsv($output, ['Phone: ' . ($customer['phone'] ?: '-')]);
    fputcsv($output, ['Opening Balance: ' . $opening]);
    fputcsv($output, ['Period: ' . ($from ?: 'Start') . ' to ' . ($to ?: 'End')]);
    fputcsv($output, []);
    fputcsv($output, ['#', 'Date', 'Type', 'Description', 'Debit (PKR)', 'Credit (PKR)', 'Balance (PKR)']);

    $sno = 0;
    foreach ($entries as $entry) {
        $sno++;
        $typeLabel = match($entry['entry_type']) {
            'cash_in' => 'Cash In',
            'cash_out' => 'Cash Out',
            'adjustment_in' => 'Adjustment (+)',
            'adjustment_out' => 'Adjustment (-)',
            default => $entry['entry_type']
        };
        $debitVal = in_array($entry['entry_type'], ['cash_out', 'adjustment_out']) ? $entry['amount'] : 0;
        $creditVal = in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? $entry['amount'] : 0;

        fputcsv($output, [
            $sno,
            date('d-m-Y', strtotime($entry['entry_date'])),
            $typeLabel,
            $entry['description'] ?: '-',
            $debitVal > 0 ? $debitVal : '',
            $creditVal > 0 ? $creditVal : '',
            abs($entry['running_balance'])
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Total', '', '', '', $debit, $credit, abs($net)]);
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($companyName) ?> - Customer Ledger - <?= sanitize($customer['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { background: <?= $isPreview ? '#fff' : '#f8f9fa' ?>; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .ledger-sheet {
            background: #fff;
            <?= $isPreview ? 'padding: 15px 20px;' : 'padding: 25px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 8px;' ?>
        }
        .print-header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #1a5276; padding-bottom: 10px; }
        .company-title { font-size: 1.45rem; font-weight: 800; color: #1a5276; margin-bottom: 2px; }
        .company-contact { font-size: 0.85rem; color: #555; }
        .doc-title { font-size: 1rem; font-weight: 700; color: #2c3e50; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; }
        
        .customer-info-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 0.85rem;
        }
        .customer-name-text {
            font-weight: 700;
            color: #1a5276;
        }

        /* Summary Widget matching screenshot */
        .summary-card-widget {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            width: 100%;
            max-width: 320px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .summary-widget-heading {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            color: #198754;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .summary-widget-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 3px 0;
            font-size: 0.86rem;
        }
        .summary-widget-lbl {
            color: #6c757d;
            font-weight: 400;
        }
        .summary-widget-num {
            font-weight: 600;
            font-size: 0.88rem;
        }
        .num-dark { color: #212529; }
        .num-paid { color: #198754; }
        .num-debit { color: #dc3545; }
        .num-credit { color: #198754; }

        .table-custom-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
        }
        .table-custom-print thead th {
            background: #1a5276;
            color: #fff;
            font-weight: 600;
            padding: 6px 8px;
            border: 1px solid #1a5276;
            font-size: 0.8rem;
        }
        .table-custom-print tbody td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .table-custom-print tfoot td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-weight: 700;
        }

        @media print { 
            body { background: #fff !important; }
            .no-print { display:none !important; } 
            .ledger-sheet { padding: 0 !important; box-shadow: none !important; }
            body { font-size: 11px; } 
            .table-custom-print { font-size: 10.5px; }
            .table-custom-print thead th { background: #333 !important; color: #fff !important; border-color: #333 !important; }
            .summary-card-widget { border-color: #ccc !important; max-width: 260px; padding: 6px 10px; margin-bottom: 10px; }
            .summary-widget-line { font-size: 9pt; padding: 2px 0; }
        }
    </style>
</head>
<body>
    <div class="container <?= $isPreview ? 'mt-1 p-1' : 'mt-3 mb-5' ?>">
        <?php if (!$isPreview): ?>
        <div class="no-print mb-3 d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            <button class="btn btn-danger btn-sm" onclick="downloadPDF()"><i class="bi bi-file-earmark-pdf"></i> Download PDF</button>
            <a href="print-customers.php?id=<?= $cid ?>&export=csv&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Download CSV/Excel</a>
            <a href="customers.php?id=<?= $cid ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Ledger</a>
        </div>
        <?php endif; ?>

        <div class="ledger-sheet">
            <!-- Print Header -->
            <div class="print-header">
                <div class="company-title"><?= sanitize($companyName) ?></div>
                <div class="company-contact">
                    <?php if (!empty($companyAddress)): ?><?= sanitize($companyAddress) ?><?php endif; ?>
                </div>
                <div class="doc-title">Customer Ledger Statement</div>
            </div>

            <!-- Customer Details Info Bar -->
            <div class="customer-info-bar">
                <div class="row g-1">
                    <div class="col-sm-6">
                        <div><strong>Customer:</strong> <span class="customer-name-text"><?= sanitize($customer['name']) ?></span></div>
                        <div><strong>Phone:</strong> <?= sanitize($customer['phone'] ?: '-') ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div><strong>Period:</strong> <?= ($from || $to) ? (($from ?: 'Start') . ' to ' . ($to ?: 'End')) : 'Complete History' ?></div>
                        <div><strong>Printed Date:</strong> <?= date('d M Y, h:i A') ?></div>
                    </div>
                </div>
            </div>

            <!-- Summary Widget as Requested -->
            <div class="summary-card-widget">
                <div class="summary-widget-heading">SUMMARY</div>
                <div class="summary-widget-line">
                    <span class="summary-widget-lbl">Total Amount</span>
                    <span class="summary-widget-num num-dark"><?= formatCurrency($opening + $debit) ?></span>
                </div>
                <div class="summary-widget-line">
                    <span class="summary-widget-lbl">Total Paid</span>
                    <span class="summary-widget-num num-paid"><?= formatCurrency($credit) ?></span>
                </div>
                <div class="summary-widget-line">
                    <span class="summary-widget-lbl">Total Debit</span>
                    <span class="summary-widget-num num-debit"><?= formatCurrency($debit) ?></span>
                </div>
                <div class="summary-widget-line">
                    <span class="summary-widget-lbl">Total Credit</span>
                    <span class="summary-widget-num num-credit"><?= formatCurrency($credit) ?></span>
                </div>
            </div>

            <?php if (empty($entries)): ?>
            <p class="text-center text-muted py-4">No ledger entries found for this period.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-custom-print">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="width: 105px;">Date</th>
                            <th style="width: 100px;">Type</th>
                            <th>Description</th>
                            <th style="width: 120px; text-align: right;">Debit (Out)</th>
                            <th style="width: 120px; text-align: right;">Credit (Paid)</th>
                            <th style="width: 130px; text-align: right;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sno = 0; foreach ($entries as $entry): $sno++; ?>
                        <tr>
                            <td style="text-align: center; color: #666;"><?= $sno ?></td>
                            <td><?= date('d M Y', strtotime($entry['entry_date'])) ?></td>
                            <td>
                                <?php if ($entry['entry_type'] === 'cash_in'): ?><span class="text-success fw-semibold">Cash In</span>
                                <?php elseif ($entry['entry_type'] === 'cash_out'): ?><span class="text-danger fw-semibold">Cash Out</span>
                                <?php elseif ($entry['entry_type'] === 'adjustment_in'): ?><span class="text-warning fw-semibold">Adj (+)</span>
                                <?php else: ?><span class="text-secondary fw-semibold">Adj (-)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($entry['description'] ?: '-') ?></td>
                            <td style="text-align: right; color: #c0392b; font-weight: 500;">
                                <?= in_array($entry['entry_type'], ['cash_out', 'adjustment_out']) ? formatCurrency($entry['amount']) : '-' ?>
                            </td>
                            <td style="text-align: right; color: #27ae60; font-weight: 500;">
                                <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? formatCurrency($entry['amount']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: <?= $entry['running_balance'] >= 0 ? '#c0392b' : '#27ae60' ?>;">
                                <?= formatCurrency(abs($entry['running_balance'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align: right;">Total:</td>
                            <td style="text-align: right; color: #c0392b;"><?= formatCurrency($debit) ?></td>
                            <td style="text-align: right; color: #27ae60;"><?= formatCurrency($credit) ?></td>
                            <td style="text-align: right; color: <?= $net >= 0 ? '#c0392b' : '#27ae60' ?>;">
                                <?= formatCurrency(abs($net)) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function downloadPDF() {
        const element = document.body;
        const noPrintElements = document.querySelectorAll('.no-print');
        noPrintElements.forEach(el => el.style.display = 'none');

        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     'Customer_Ledger_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['name']) ?>_<?= date('Ymd') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            noPrintElements.forEach(el => el.style.display = '');
        }).catch(err => {
            console.error('PDF error:', err);
            noPrintElements.forEach(el => el.style.display = '');
            window.print();
        });
    }

    window.addEventListener('load', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            setTimeout(() => window.print(), 300);
        } else if (urlParams.get('download_pdf') === '1') {
            setTimeout(() => downloadPDF(), 300);
        }
    });
    </script>
</body>
</html>
