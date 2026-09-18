<?php
$pageTitle = 'Daily Closings History';
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
$sql .= " ORDER BY closing_date DESC, closed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$closings = $stmt->fetchAll();

// Summary calculations
$totalDays = count($closings);
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

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-wrapper">
    <?php
        $printParams = [];
        if ($from !== '') { $printParams['from'] = $from; }
        if ($to !== '') { $printParams['to'] = $to; }
        $printQuery = http_build_query($printParams);
    ?>
    <div class="page-header">
        <h2>
            <i class="bi bi-journal-check"></i> Daily Closings History
        </h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="cash-book.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-journal-bookmark"></i> Cash Book
            </a>
            <a href="customers.php" class="btn btn-outline-info btn-sm">
                <i class="bi bi-people"></i> Customers
            </a>
            <a href="print-closings.php?<?= $printQuery ?>" class="btn btn-print btn-sm" target="_blank">
                <i class="bi bi-printer"></i> Print Report
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card-custom mb-4">
        <div class="card-header"><i class="bi bi-funnel"></i> Filter Closings by Date</div>
        <div class="card-body">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
                </div>
                <div class="col-md-4 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply Filter</button>
                    <?php if ($from || $to): ?>
                    <a href="closings-history.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row g-2 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card opening">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Total Closed Days</div>
                        <div class="value" style="font-size:1.2rem;"><?= $totalDays ?> Days</div>
                    </div>
                    <div class="icon yellow"><i class="bi bi-calendar-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card cash-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Total Cash In (Period)</div>
                        <div class="value amount-in" style="font-size:1.1rem;"><?= formatCurrency($sumIn) ?></div>
                    </div>
                    <div class="icon green"><i class="bi bi-arrow-down-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card cash-out">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Total Cash Out (Period)</div>
                        <div class="value amount-out" style="font-size:1.1rem;"><?= formatCurrency($sumOut) ?></div>
                    </div>
                    <div class="icon red"><i class="bi bi-arrow-up-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card balance">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Net Period Difference</div>
                        <div class="value" style="color:#2e86c1;font-size:1.1rem;">
                            <?= ($sumIn - $sumOut) >= 0 ? '+' : '-' ?><?= formatCurrency(abs($sumIn - $sumOut)) ?>
                        </div>
                    </div>
                    <div class="icon blue"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Closings Table -->
    <div class="card-custom">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-stars"></i> Recorded Closings List</span>
            <span class="text-muted" style="font-size:0.85rem;">Showing <?= $totalDays ?> records</span>
        </div>
        <div class="card-body">
            <?php if (empty($closings)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox" style="font-size:2rem;"></i>
                <p>No closing records found for the selected dates.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-custom table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Closing Date</th>
                            <th>Opening Balance</th>
                            <th>Total Cash In</th>
                            <th>Total Cash Out</th>
                            <th>Closing Balance</th>
                            <th>Closed Time</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sno = 0; foreach ($closings as $c): $sno++; ?>
                        <tr>
                            <td class="text-muted"><?= $sno ?></td>
                            <td>
                                <strong><?= date('d M Y', strtotime($c['closing_date'])) ?></strong>
                                <br><small class="text-muted"><?= date('l', strtotime($c['closing_date'])) ?></small>
                            </td>
                            <td class="amount-cell"><?= formatCurrency($c['opening_balance']) ?></td>
                            <td class="amount-cell amount-in"><?= formatCurrency($c['total_in']) ?></td>
                            <td class="amount-cell amount-out"><?= formatCurrency($c['total_out']) ?></td>
                            <td class="amount-cell" style="font-weight:700; color:#1a5276; font-size:0.95rem;">
                                <?= formatCurrency($c['closing_balance']) ?>
                            </td>
                            <td class="text-muted" style="font-size:0.85rem;">
                                <?= date('h:i A', strtotime($c['closed_at'])) ?>
                            </td>
                            <td class="text-muted" style="font-size:0.85rem; max-width:180px; white-space:normal;">
                                <?= sanitize($c['notes'] ?: '-') ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="cash-book.php?from=<?= $c['closing_date'] ?>&to=<?= $c['closing_date'] ?>" class="btn btn-outline-primary btn-sm" title="View Day Cash Book">
                                        <i class="bi bi-journal-text"></i> Entries
                                    </a>
                                    <a href="print-cashbook.php?from=<?= $c['closing_date'] ?>&to=<?= $c['closing_date'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank" title="Print Day">
                                        <i class="bi bi-printer"></i> Print
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold" style="background:#f8f9fa;">
                            <td colspan="3" class="text-end">Total (Period):</td>
                            <td class="amount-cell amount-in"><?= formatCurrency($sumIn) ?></td>
                            <td class="amount-cell amount-out"><?= formatCurrency($sumOut) ?></td>
                            <td class="amount-cell" style="color:#1a5276;"><?= formatCurrency($sumClosing) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
