<?php
$pageTitle = 'Cash Book';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

// Delete entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $deleteId = intval($_POST['entry_id'] ?? 0);
    if ($deleteId > 0) {
        $stmt = $pdo->prepare("DELETE FROM cash_entries WHERE id = ?");
        $stmt->execute([$deleteId]);
        setFlash('success', 'Entry deleted successfully.');
    }
    header('Location: cash-book.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// Add entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $entryType    = $_POST['entry_type'] ?? 'cash_in';
    $customerName = trim($_POST['customer_name'] ?? '');
    $amount       = floatval($_POST['amount'] ?? 0);
    $entryDate    = $_POST['entry_date'] ?? getTodayDate();
    $description  = trim($_POST['description'] ?? '');

    if ($entryType === 'adjustment') {
        $adjustDir = $_POST['adjustment_direction'] ?? 'in';
        $entryType = ($adjustDir === 'in') ? 'adjustment_in' : 'adjustment_out';
        $customerName = 'Balance Adjustment';
    }

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO cash_entries (entry_type, customer_name, amount, entry_date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$entryType, $customerName, $amount, $entryDate, $description]);
        if ($entryType === 'adjustment_in') {
            setFlash('success', 'Adjustment (+) of ' . formatCurrency($amount) . ' saved.');
        } elseif ($entryType === 'adjustment_out') {
            setFlash('success', 'Adjustment (-) of ' . formatCurrency($amount) . ' saved.');
        } else {
            setFlash('success', ($entryType === 'cash_in' ? 'Cash In' : 'Cash Out') . ' of ' . formatCurrency($amount) . ' saved.');
        }
    } else {
        setFlash('error', 'Please enter a valid amount.');
    }
    header('Location: cash-book.php');
    exit;
}

// Filters
$filterFrom = $_GET['from'] ?? '';
$filterTo   = $_GET['to'] ?? '';
$filterCust = $_GET['customer'] ?? '';

$today = getTodayDate();

$summarySQL = "SELECT
    COALESCE(SUM(CASE WHEN entry_type IN ('cash_in','adjustment_in') THEN amount ELSE 0 END), 0) as total_in,
    COALESCE(SUM(CASE WHEN entry_type IN ('cash_out','adjustment_out') THEN amount ELSE 0 END), 0) as total_out,
    COUNT(*) as total_entries
    FROM cash_entries";
$summaryWhere = [];
$summaryParams = [];

if ($filterFrom !== '') {
    $summaryWhere[] = "entry_date >= ?";
    $summaryParams[] = $filterFrom;
}
if ($filterTo !== '') {
    $summaryWhere[] = "entry_date <= ?";
    $summaryParams[] = $filterTo;
}
if ($filterCust !== '') {
    $summaryWhere[] = "customer_name LIKE ?";
    $summaryParams[] = "%$filterCust%";
}

$summarySQL .= !empty($summaryWhere) ? ' WHERE ' . implode(' AND ', $summaryWhere) : '';
$stmtF = $pdo->prepare($summarySQL);
$stmtF->execute($summaryParams);
$summary = $stmtF->fetch();

// Opening balance based on filter scope
if ($filterFrom !== '') {
    $periodOpening = getOpeningBalance($filterFrom);
} elseif ($filterCust !== '') {
    $periodOpening = getOpeningBalance($today);
} elseif ($summary['total_entries'] > 0) {
    $firstDateStmt = $pdo->prepare("SELECT MIN(entry_date) as first_date FROM cash_entries" . (!empty($summaryWhere) ? ' WHERE ' . implode(' AND ', $summaryWhere) : ''));
    $firstDateStmt->execute($summaryParams);
    $firstDateRow = $firstDateStmt->fetch();
    $periodOpening = getOpeningBalance($firstDateRow['first_date']);
} else {
    $periodOpening = 0;
}
$periodClosing = $periodOpening + $summary['total_in'] - $summary['total_out'];

// Fetch entries (chronological ASC for running balance)
$entriesSQL = "SELECT * FROM cash_entries WHERE 1=1";
$params = [];
if ($filterFrom !== '') { $entriesSQL .= " AND entry_date >= ?"; $params[] = $filterFrom; }
if ($filterTo !== '')   { $entriesSQL .= " AND entry_date <= ?"; $params[] = $filterTo; }
if ($filterCust !== '') { $entriesSQL .= " AND customer_name LIKE ?"; $params[] = "%$filterCust%"; }
$entriesSQL .= " ORDER BY entry_date ASC, created_at ASC";
$stmt = $pdo->prepare($entriesSQL);
$stmt->execute($params);
$allEntries = $stmt->fetchAll();

$runningBalance = $periodOpening;
foreach ($allEntries as &$entry) {
    if (in_array($entry['entry_type'], ['cash_in', 'adjustment_in'])) {
        $runningBalance += $entry['amount'];
    } else {
        $runningBalance -= $entry['amount'];
    }
    $entry['running_balance'] = $runningBalance;
}
unset($entry);

$displayEntries = array_reverse($allEntries);

$customers = getUniqueCustomers();

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-wrapper">
    <?php
        $printParams = [];
        if ($filterFrom !== '') { $printParams['from'] = $filterFrom; }
        if ($filterTo !== '') { $printParams['to'] = $filterTo; }
        if ($filterCust !== '') { $printParams['customer'] = $filterCust; }
        $printQuery = http_build_query($printParams);
    ?>
    <div class="page-header">
        <h2 style="width:100%;">
            <i class="bi bi-journal-bookmark"></i> Cash Book
            <span style="margin-left:auto; display:flex; gap:6px;">
                <a href="index.php" class="btn btn-outline-primary btn-sm" style="flex-shrink:0;">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="print-cashbook.php?<?= $printQuery ?>" class="btn btn-print btn-sm" style="flex-shrink:0;" target="_blank">
                    <i class="bi bi-printer"></i> Print
                </a>
            </span>
        </h2>
    </div>

    <!-- Entry Form -->
    <div class="card-custom mb-4">
        <div class="card-header"><i class="bi bi-plus-circle"></i> New Entry</div>
        <div class="card-body">
            <form method="POST" action="" id="entryForm">
                <div class="mb-3">
                    <label class="form-label">Entry Type</label>
                    <div class="d-flex gap-4 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="entry_type" id="typeCashIn" value="cash_in" checked onchange="updateFormColor()">
                            <label class="form-check-label fw-bold text-success" for="typeCashIn"><i class="bi bi-arrow-down-circle"></i> Cash In</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="entry_type" id="typeCashOut" value="cash_out" onchange="updateFormColor()">
                            <label class="form-check-label fw-bold text-danger" for="typeCashOut"><i class="bi bi-arrow-up-circle"></i> Cash Out</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="entry_type" id="typeAdjustment" value="adjustment" onchange="updateFormColor()">
                            <label class="form-check-label fw-bold" for="typeAdjustment" style="color:#e67e22;"><i class="bi bi-sliders"></i> Balance Adjustment</label>
                        </div>
                    </div>
                    <div id="adjustmentDir" class="mt-2" style="display:none;">
                        <label class="form-label fw-bold">Direction</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_direction" id="adjIn" value="in" checked>
                                <label class="form-check-label fw-bold text-success" for="adjIn"><i class="bi bi-plus-circle"></i> Increase (+)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_direction" id="adjOut" value="out">
                                <label class="form-check-label fw-bold text-danger" for="adjOut"><i class="bi bi-dash-circle"></i> Decrease (-)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4 customer-field">
                        <label class="form-label">Customer Name</label>
                        <input type="text" name="customer_name" id="customerNameInput" class="form-control" placeholder="e.g. Ahmed Khan (optional)" list="customerList">
                        <datalist id="customerList">
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= sanitize($c) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Amount (PKR) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date *</label>
                        <input type="date" name="entry_date" class="form-control" value="<?= $today ?>" required>
                    </div>
                    <div class="col-md-3 desc-field">
                        <label class="form-label">Description (optional)</label>
                        <input type="text" name="description" class="form-control" placeholder="Note or reference...">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" name="add_entry" id="submitBtn" class="btn btn-success">
                            <i class="bi bi-arrow-down-circle"></i> Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filter + Entries -->
    <div class="card-custom">
        <div class="card-header"><i class="bi bi-funnel"></i> Filters & Entries</div>
        <div class="card-body">
            <form method="GET" action="" class="filter-bar mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-6">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from" class="form-control" value="<?= sanitize($filterFrom) ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to" class="form-control" value="<?= sanitize($filterTo) ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">Customer</label>
                        <input type="text" name="customer" class="form-control" value="<?= sanitize($filterCust) ?>" placeholder="Search name...">
                    </div>
                    <div class="col-md-3 col-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
                        <?php if ($filterFrom || $filterTo || $filterCust): ?>
                        <a href="cash-book.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if (empty($displayEntries)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No entries found</p>
            </div>
            <?php else: ?>

            <?php $netTotal = $summary['total_in'] - $summary['total_out']; ?>
            <div class="d-flex justify-content-between align-items-center mb-2 py-2 px-3" style="background:#f0f2f5; border-radius:8px; font-size:0.95rem; flex-wrap:wrap; gap:4px;">
                <span style="font-weight:600;">Opening: <?= formatCurrency($periodOpening) ?></span>
                <span style="font-weight:600;">Entries: <?= $summary['total_entries'] ?></span>
                <span style="font-weight:700; color:#1a5276;">
                    Total Amount: <?= $netTotal >= 0 ? '+' : '-' ?><?= formatCurrency(abs($netTotal)) ?>
                </span>
            </div>

            <!-- Desktop Table -->
            <div class="desktop-table table-responsive">
                <table class="table-custom table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Balance</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayEntries as $i => $entry): ?>
                        <tr>
                            <td class="text-muted"><?= count($displayEntries) - $i ?></td>
                            <td class="text-muted"><?= date('d M Y', strtotime($entry['entry_date'])) ?></td>
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
                            <td class="text-muted" style="max-width:200px; white-space:normal;"><?= sanitize($entry['description'] ?: '-') ?></td>
                            <td class="amount-cell <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? 'amount-in' : 'amount-out' ?>">
                                <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? '+' : '-' ?><?= formatCurrency($entry['amount']) ?>
                            </td>
                            <td class="amount-cell" style="font-weight:700; color: <?= $entry['running_balance'] >= 0 ? '#2e86c1' : '#e74c3c' ?>;">
                                <?= formatCurrency($entry['running_balance']) ?>
                            </td>
                            <td class="no-print">
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this entry?');">
                                    <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                    <button type="submit" name="delete_entry" class="btn btn-outline-danger btn-sm" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="mobile-card-view">
                <?php foreach ($displayEntries as $i => $entry): ?>
                <div class="entry-card <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? 'cash-in-card' : 'cash-out-card' ?>">
                    <div class="entry-card-top">
                        <div>
                            <span class="entry-card-customer"><?= sanitize($entry['customer_name']) ?></span>
                            <br><small class="text-muted"><?= date('d M Y', strtotime($entry['entry_date'])) ?></small>
                        </div>
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
                        <span class="amount-cell" style="color: <?= $entry['running_balance'] >= 0 ? '#2e86c1' : '#e74c3c' ?>;">
                            Bal: <?= formatCurrency($entry['running_balance']) ?>
                        </span>
                    </div>
                    <?php if (!empty($entry['description'])): ?>
                    <div class="entry-card-desc"><?= sanitize($entry['description']) ?></div>
                    <?php endif; ?>
                    <div class="entry-card-actions no-print">
                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this entry?');">
                            <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                            <button type="submit" name="delete_entry" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateFormColor() {
    var isIn = document.getElementById('typeCashIn').checked;
    var isOut = document.getElementById('typeCashOut').checked;
    var isAdj = document.getElementById('typeAdjustment').checked;
    var btn = document.getElementById('submitBtn');
    var adjDir = document.getElementById('adjustmentDir');
    var customerFields = document.querySelectorAll('.customer-field');
    var descFields = document.querySelectorAll('.desc-field');

    if (isAdj) {
        adjDir.style.display = 'block';
        btn.className = 'btn btn-warning';
        btn.innerHTML = '<i class="bi bi-sliders"></i> Save';
        customerFields.forEach(function(el) { el.style.display = 'none'; });
    } else {
        adjDir.style.display = 'none';
        customerFields.forEach(function(el) { el.style.display = ''; });
        descFields.forEach(function(el) { el.style.display = ''; });
        if (isIn) {
            btn.className = 'btn btn-success';
            btn.innerHTML = '<i class="bi bi-arrow-down-circle"></i> Save';
        } else {
            btn.className = 'btn btn-danger';
            btn.innerHTML = '<i class="bi bi-arrow-up-circle"></i> Save';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
