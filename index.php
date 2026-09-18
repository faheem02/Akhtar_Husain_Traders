<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$today = getTodayDate();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_day'])) {
    $todayOpening = getOpeningBalance($today);
    $todayClosing = getClosingBalance($today);
    $todaySummary = getTodaySummary($today);
    $notes        = trim($_POST['closing_notes'] ?? '');

    saveDailyClosing(
        $today,
        $todayOpening,
        floatval($todaySummary['total_in']),
        floatval($todaySummary['total_out']),
        $todayClosing,
        $notes
    );

    setFlash('success', "Day closed successfully! Closing balance of " . formatCurrency($todayClosing) . " saved to Closing History.");
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_opening'])) {
    $openingDate   = $_POST['opening_date'] ?? getTodayDate();
    $openingAmount = floatval($_POST['opening_amount'] ?? 0);

    $check = $pdo->prepare("SELECT id FROM daily_opening WHERE opening_date = ?");
    $check->execute([$openingDate]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE daily_opening SET opening_balance = ? WHERE opening_date = ?");
        $stmt->execute([$openingAmount, $openingDate]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO daily_opening (opening_date, opening_balance) VALUES (?, ?)");
        $stmt->execute([$openingDate, $openingAmount]);
    }

    setFlash('success', "Opening balance of " . formatCurrency($openingAmount) . " set for " . date('d M Y', strtotime($openingDate)));
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

$summary = getTodaySummary($today);
$openingBalance = getOpeningBalance($today);
$closingBalance = getClosingBalance($today);
$todayClosed = getDayClosingRecord($today);
$closingHistory = getDailyClosings(5);

$stmt = $pdo->prepare("SELECT * FROM cash_entries WHERE entry_date = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$today]);
$recentEntries = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <div class="page-header">
        <h2>
            <i class="bi bi-speedometer2"></i> Dashboard
        </h2>
        <div class="header-actions">
            <a href="cash-book.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-journal-bookmark"></i> Cash Book
            </a>
            <a href="customers.php" class="btn btn-outline-info btn-sm">
                <i class="bi bi-people"></i> Customers
            </a>
            <a href="closings-history.php" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-journal-check"></i> Closing History
            </a>
            <span class="text-muted d-none d-md-inline" style="font-size:0.85rem; white-space:nowrap; padding-left:4px;"><?= date('l, d M Y') ?></span>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-2 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card opening">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Opening Balance</div>
                        <div class="value" style="font-size:1.1rem;"><?= formatCurrency($openingBalance) ?></div>
                    </div>
                    <div class="icon yellow"><i class="bi bi-sunrise"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card cash-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Total Cash In</div>
                        <div class="value amount-in" style="font-size:1.1rem;"><?= formatCurrency($summary['total_in']) ?></div>
                    </div>
                    <div class="icon green"><i class="bi bi-arrow-down-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card cash-out">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Total Cash Out</div>
                        <div class="value amount-out" style="font-size:1.1rem;"><?= formatCurrency($summary['total_out']) ?></div>
                    </div>
                    <div class="icon red"><i class="bi bi-arrow-up-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card balance">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label">Closing Balance</div>
                        <div class="value" style="color:#2e86c1;font-size:1.1rem;"><?= formatCurrency($closingBalance) ?></div>
                    </div>
                    <div class="icon blue"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions + Today's Opening + End of Day -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-custom h-100">
                <div class="card-header"><i class="bi bi-lightning"></i> Quick Actions</div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <a href="customers.php" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-person-plus"></i> Customer Entry (Add Entry)
                    </a>
                    <a href="cash-book.php" class="btn btn-outline-success w-100 mb-2">
                        <i class="bi bi-journal-bookmark"></i> View Cash Book
                    </a>
                    <a href="closings-history.php" class="btn btn-outline-warning w-100 mb-2 text-dark">
                        <i class="bi bi-journal-check"></i> View Closing History
                    </a>
                    <a href="print-cashbook.php?from=<?= $today ?>&to=<?= $today ?>" class="btn btn-print w-100" target="_blank">
                        <i class="bi bi-printer"></i> Print Today's Cash Book
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="report-section text-center h-100 d-flex flex-column justify-content-center" style="border: 2px solid #27ae60;">
                <h5 style="color: #27ae60;"><i class="bi bi-sunrise"></i> Today's Opening</h5>
                <div style="font-size: 1.5rem; font-weight: 800; color: #27ae60;"><?= formatCurrency($openingBalance) ?></div>
                <small class="text-muted"><?= date('l, d M Y') ?></small>
                <div class="mt-2 text-muted" style="font-size:0.8rem;">
                    <?= $openingBalance > 0 ? 'Manually set opening' : 'Default opening (0.00)' ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="report-section text-center h-100 d-flex flex-column justify-content-center" style="border: 2px solid #1a5276;">
                <form method="POST" action="" onsubmit="return confirm('Close today? Closing balance of <?= formatCurrency($closingBalance) ?> will be saved to history.');">
                    <h5><i class="bi bi-lock"></i> End of Day</h5>
                    <?php if ($todayClosed): ?>
                        <div class="mb-2">
                            <span class="badge bg-success" style="font-size:0.85rem;">
                                <i class="bi bi-check2-circle"></i> Closed at <?= date('h:i A', strtotime($todayClosed['closed_at'])) ?>
                            </span>
                        </div>
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                            Saved Closing: <strong><?= formatCurrency($todayClosed['closing_balance']) ?></strong>
                        </div>
                        <button type="submit" name="close_day" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-repeat"></i> Update Closing (<?= formatCurrency($closingBalance) ?>)
                        </button>
                    <?php else: ?>
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                            Save today's closing balance to permanent history
                        </div>
                        <button type="submit" name="close_day" class="btn btn-primary btn-lg">
                            <i class="bi bi-check2-circle"></i> Close Today (<?= formatCurrency($closingBalance) ?>)
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Set Opening Balance -->
    <div class="card-custom mb-4">
        <div class="card-header"><i class="bi bi-pencil-square"></i> Set Opening Balance (Optional)</div>
        <div class="card-body">
            <form method="POST" action="" class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label">Date</label>
                    <input type="date" name="opening_date" class="form-control" value="<?= $today ?>" required>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">Opening Amount</label>
                    <input type="number" name="opening_amount" class="form-control" step="0.01" value="<?= $openingBalance ?>" min="0" required>
                </div>
                <div class="col-md-3 col-12">
                    <button type="submit" name="set_opening" class="btn btn-warning"><i class="bi bi-check-circle"></i> Save Opening Balance</button>
                </div>
            </form>
            <div class="form-text mt-1 text-muted" style="font-size:0.8rem;">
                Agar aap opening balance 0 rakhna chahte hain to 0 save karein ya chor dein. Naya din hamesha 0 se start hoga.
            </div>
        </div>
    </div>

    <!-- Recent Daily Closings Card -->
    <div class="card-custom mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-journal-check"></i> Recent Daily Closings</span>
            <a href="closings-history.php" class="btn btn-sm btn-primary">
                View All History & Reports <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($closingHistory)): ?>
            <div class="empty-state py-3">
                <i class="bi bi-inbox" style="font-size:1.8rem;"></i>
                <p class="mb-0 text-muted">No closing records saved yet. Click "Close Today" to save end-of-day balance.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-custom table">
                    <thead>
                        <tr>
                            <th>Closing Date</th>
                            <th>Opening</th>
                            <th>Total Cash In</th>
                            <th>Total Cash Out</th>
                            <th>Closing Balance</th>
                            <th>Closed At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($closingHistory as $ch): ?>
                        <tr>
                            <td><strong><?= date('d M Y', strtotime($ch['closing_date'])) ?></strong></td>
                            <td class="amount-cell"><?= formatCurrency($ch['opening_balance']) ?></td>
                            <td class="amount-cell amount-in"><?= formatCurrency($ch['total_in']) ?></td>
                            <td class="amount-cell amount-out"><?= formatCurrency($ch['total_out']) ?></td>
                            <td class="amount-cell" style="font-weight:700; color:#1a5276;">
                                <?= formatCurrency($ch['closing_balance']) ?>
                            </td>
                            <td class="text-muted"><?= date('d M Y, h:i A', strtotime($ch['closed_at'])) ?></td>
                            <td>
                                <a href="cash-book.php?from=<?= $ch['closing_date'] ?>&to=<?= $ch['closing_date'] ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-eye"></i> View Day
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Entries -->
    <div class="card-custom">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock-history"></i> Recent Entries Today</span>
            <a href="cash-book.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentEntries)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No entries today</p>
            </div>
            <?php else: ?>
            <div class="desktop-table table-responsive">
                <table class="table-custom table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEntries as $entry): ?>
                        <tr>
                            <td><span class="customer-tag"><?= sanitize($entry['customer_name']) ?></span></td>
                            <td>
                                <?php if ($entry['entry_type'] === 'cash_in'): ?>
                                    <span class="badge-cash-in"><i class="bi bi-arrow-down"></i> Cash In</span>
                                <?php elseif ($entry['entry_type'] === 'adjustment_in'): ?>
                                    <span class="badge-adjustment-in"><i class="bi bi-plus-circle"></i> Adjustment (+)</span>
                                <?php elseif ($entry['entry_type'] === 'adjustment_out'): ?>
                                    <span class="badge-adjustment-out"><i class="bi bi-dash-circle"></i> Adjustment (-)</span>
                                <?php else: ?>
                                    <span class="badge-cash-out"><i class="bi bi-arrow-up"></i> Cash Out</span>
                                <?php endif; ?>
                            </td>
                            <td class="amount-cell <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? 'amount-in' : 'amount-out' ?>">
                                <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? '+' : '-' ?><?= formatCurrency($entry['amount']) ?>
                            </td>
                            <td class="text-muted"><?= date('h:i A', strtotime($entry['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-view">
                <?php foreach ($recentEntries as $entry): ?>
                <div class="entry-card <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? 'cash-in-card' : 'cash-out-card' ?>">
                    <div class="entry-card-top">
                        <span class="entry-card-customer"><?= sanitize($entry['customer_name']) ?></span>
                        <span class="entry-card-amount <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? 'amount-in' : 'amount-out' ?>">
                            <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? '+' : '-' ?><?= formatCurrency($entry['amount']) ?>
                        </span>
                    </div>
                    <div class="entry-card-meta">
                        <span>
                            <?php if ($entry['entry_type'] === 'cash_in'): ?>
                                <span class="badge-cash-in"><i class="bi bi-arrow-down"></i> Cash In</span>
                            <?php elseif ($entry['entry_type'] === 'adjustment_in'): ?>
                                <span class="badge-adjustment-in"><i class="bi bi-plus-circle"></i> Adjustment (+)</span>
                            <?php elseif ($entry['entry_type'] === 'adjustment_out'): ?>
                                <span class="badge-adjustment-out"><i class="bi bi-dash-circle"></i> Adjustment (-)</span>
                            <?php else: ?>
                                <span class="badge-cash-out"><i class="bi bi-arrow-up"></i> Cash Out</span>
                            <?php endif; ?>
                        </span>
                        <span><?= date('h:i A', strtotime($entry['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

