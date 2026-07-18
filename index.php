<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_day'])) {
    $today = getTodayDate();
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
    $todayClosing = getClosingBalance($today);

    $check = $pdo->prepare("SELECT id FROM daily_opening WHERE opening_date = ?");
    $check->execute([$tomorrow]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE daily_opening SET opening_balance = ? WHERE opening_date = ?");
        $stmt->execute([$todayClosing, $tomorrow]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO daily_opening (opening_date, opening_balance) VALUES (?, ?)");
        $stmt->execute([$tomorrow, $todayClosing]);
    }

    setFlash('success', "Day closed! " . formatCurrency($todayClosing) . " saved as " . date('d M Y', strtotime($tomorrow)) . " opening.");
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_opening'])) {
    $openingDate = $_POST['opening_date'] ?? getTodayDate();
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

$today = getTodayDate();
// Auto carry-forward: if no daily_opening record for today, create one from yesterday's closing
$checkToday = $pdo->prepare("SELECT id FROM daily_opening WHERE opening_date = ?");
$checkToday->execute([$today]);
if (!$checkToday->fetch()) {
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    $yesterdayClosing = getClosingBalance($yesterday);
    $stmt = $pdo->prepare("INSERT INTO daily_opening (opening_date, opening_balance) VALUES (?, ?)");
    $stmt->execute([$today, $yesterdayClosing]);
}

$summary = getTodaySummary($today);
$openingBalance = getOpeningBalance($today);
$closingBalance = getClosingBalance($today);

$stmt = $pdo->prepare("SELECT * FROM cash_entries WHERE entry_date = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$today]);
$recentEntries = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <div class="page-header">
        <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
        <span class="text-muted"><?= date('l, d M Y') ?></span>
    </div>

    <!-- Quick Actions + Close Today -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-custom">
                <div class="card-header"><i class="bi bi-lightning"></i> Quick Actions</div>
                <div class="card-body">
                    <a href="cash-book.php" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-plus-circle"></i> New Entry
                    </a>
                    <a href="print-cashbook.php?from=<?= $today ?>&to=<?= $today ?>" class="btn btn-print w-100" target="_blank">
                        <i class="bi bi-printer"></i> Print Cash Book
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="report-section text-center" style="border: 2px solid #27ae60;">
                <h5 style="color: #27ae60;"><i class="bi bi-sunrise"></i> Today's Opening</h5>
                <div style="font-size: 1.5rem; font-weight: 800; color: #27ae60;"><?= formatCurrency($openingBalance) ?></div>
                <small class="text-muted"><?= date('l, d M Y') ?></small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="report-section text-center" style="border: 2px solid #1a5276;">
                <form method="POST" action="" onsubmit="return confirm('Close today? Tomorrow\'s opening will be set to <?= formatCurrency($closingBalance) ?>');">
                    <h5><i class="bi bi-lock"></i> End of Day</h5>
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">
                        Save today's closing as tomorrow's opening
                    </div>
                    <button type="submit" name="close_day" class="btn btn-primary btn-lg">
                        <i class="bi bi-check2-circle"></i> Close Today (<?= formatCurrency($closingBalance) ?>)
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Set Opening Balance -->
    <div class="card-custom mb-4">
        <div class="card-header"><i class="bi bi-pencil-square"></i> Set Opening Balance</div>
        <div class="card-body">
            <form method="POST" action="" class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label">Date</label>
                    <input type="date" name="opening_date" class="form-control" value="<?= $today ?>" required>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">Amount (PKR)</label>
                    <input type="number" name="opening_amount" class="form-control" step="0.01" value="0" min="0" required>
                </div>
                <div class="col-md-3 col-12">
                    <button type="submit" name="set_opening" class="btn btn-warning"><i class="bi bi-check-circle"></i> Save Opening Balance</button>
                </div>
            </form>
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
