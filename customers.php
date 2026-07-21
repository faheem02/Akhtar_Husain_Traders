<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_customer'])) {
    $name = trim($_POST['quick_name'] ?? '');
    $phone = trim($_POST['quick_phone'] ?? '');
    $opening = floatval($_POST['quick_opening'] ?? 0);
    if ($name !== '') {
        $existing = $pdo->prepare("SELECT id FROM customers WHERE name = ?");
        $existing->execute([$name]);
        if (!$existing->fetch()) {
            $ins = $pdo->prepare("INSERT INTO customers (name, phone, opening_balance) VALUES (?, ?, ?)");
            $ins->execute([$name, $phone, $opening]);
            setFlash('success', 'Customer "' . sanitize($name) . '" added successfully.');
        } else {
            setFlash('error', 'Customer "' . sanitize($name) . '" already exists.');
        }
    }
    header('Location: customers.php');
    exit;
}

$allCustomers = getAllCustomers();

$customerInfo = null;
$customerLedger = [];
$ledgerOpening = 0;
$ledgerNet = 0;

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $cid = intval($_GET['id']);
    $cstmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $cstmt->execute([$cid]);
    $customerInfo = $cstmt->fetch();

    if ($customerInfo) {
        $customerLedger = getCustomerLedger($cid, $from, $to);
        $ledgerOpening = floatval($customerInfo['opening_balance']);
        $running = $ledgerOpening;
        foreach ($customerLedger as &$entry) {
            if (in_array($entry['entry_type'], ['cash_out', 'adjustment_out'])) {
                $running += $entry['amount'];
            } else {
                $running -= $entry['amount'];
            }
            $entry['running_balance'] = $running;
        }
        unset($entry);
        $ledgerNet = $running;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-wrapper">
    <div class="page-header">
        <h2 style="width:100%;">
            <i class="bi bi-people"></i> <?= $customerInfo ? sanitize($customerInfo['name']) . ' - Ledger' : 'Customers' ?>
            <span style="margin-left:auto; display:flex; gap:6px;">
                <?php if ($customerInfo): ?>
                <a href="customers.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> All Customers
                </a>
                <a href="print-customers.php?id=<?= $customerInfo['id'] ?>" class="btn btn-print btn-sm" target="_blank">
                    <i class="bi bi-printer"></i> Print
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="cash-book.php" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-journal-bookmark"></i> Cash Book
                </a>
            </span>
        </h2>
    </div>

    <?php if ($customerInfo): ?>

    <div class="card-custom mb-4">
        <div class="card-header">
            <i class="bi bi-person-lines-fill"></i> <?= sanitize($customerInfo['name']) ?> - Ledger
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <strong>Phone:</strong> <?= sanitize($customerInfo['phone'] ?: '-') ?>
                </div>
                <div class="col-md-3">
                    <strong>Opening Balance:</strong> <?= formatCurrency($ledgerOpening) ?>
                </div>
                <div class="col-md-3">
                    <strong>Current Balance:</strong>
                    <span style="color:<?= $ledgerNet >= 0 ? '#e74c3c' : '#28b463' ?>; font-weight:700;">
                        <?= formatCurrency(abs($ledgerNet)) ?>
                    </span>
                </div>
            </div>

            <form method="GET" action="" class="filter-bar mb-3">
                <input type="hidden" name="id" value="<?= $customerInfo['id'] ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-6">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
                    </div>
                    <div class="col-md-3 col-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
                        <?php if ($from || $to): ?>
                        <a href="customers.php?id=<?= $customerInfo['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 col-6 d-flex gap-2">
                        <a href="cash-book.php?customer=<?= urlencode($customerInfo['name']) ?>" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-journal-bookmark"></i> Cash Book
                        </a>
                    </div>
                </div>
            </form>

            <?php if (empty($customerLedger)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No entries found</p>
            </div>
            <?php else:
            $debit = 0; $credit = 0;
            foreach ($customerLedger as $entry) {
                if (in_array($entry['entry_type'], ['cash_out', 'adjustment_out'])) {
                    $debit += $entry['amount'];
                } else {
                    $credit += $entry['amount'];
                }
            }
            ?>
            <div class="table-responsive">
                <table class="table-custom table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sno = 0; foreach ($customerLedger as $entry): $sno++; ?>
                        <tr>
                            <td class="text-muted"><?= $sno ?></td>
                            <td class="text-muted"><?= date('d M Y', strtotime($entry['entry_date'])) ?></td>
                            <td>
                                <?php if ($entry['entry_type'] === 'cash_in'): ?>
                                    <span class="badge-cash-in"><i class="bi bi-arrow-down"></i> Cash In</span>
                                <?php elseif ($entry['entry_type'] === 'adjustment_in'): ?>
                                    <span class="badge-adjustment-in"><i class="bi bi-plus-circle"></i> Adj (+)</span>
                                <?php elseif ($entry['entry_type'] === 'adjustment_out'): ?>
                                    <span class="badge-adjustment-out"><i class="bi bi-dash-circle"></i> Adj (-)</span>
                                <?php else: ?>
                                    <span class="badge-cash-out"><i class="bi bi-arrow-up"></i> Cash Out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="white-space:normal;"><?= sanitize($entry['description'] ?: '-') ?></td>
                            <td class="amount-cell amount-out">
                                <?= in_array($entry['entry_type'], ['cash_out', 'adjustment_out']) ? formatCurrency($entry['amount']) : '-' ?>
                            </td>
                            <td class="amount-cell amount-in">
                                <?= in_array($entry['entry_type'], ['cash_in', 'adjustment_in']) ? formatCurrency($entry['amount']) : '-' ?>
                            </td>
                            <td class="amount-cell" style="font-weight:700; color:<?= $entry['running_balance'] >= 0 ? '#e74c3c' : '#28b463' ?>;">
                                <?= formatCurrency(abs($entry['running_balance'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Total:</td>
                            <td class="amount-cell amount-out"><?= formatCurrency($debit) ?></td>
                            <td class="amount-cell amount-in"><?= formatCurrency($credit) ?></td>
                            <td class="amount-cell" style="color:<?= $ledgerNet >= 0 ? '#e74c3c' : '#28b463' ?>;">
                                <?= formatCurrency(abs($ledgerNet)) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>

    <div class="card-custom">
        <div class="card-header"><i class="bi bi-list-ul"></i> All Customers</div>
        <div class="card-body">
            <!-- Search -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <input type="text" id="customerSearch" class="form-control" placeholder="Search by name, phone..." onkeyup="filterCustomers()">
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-success btn-sm" onclick="document.getElementById('quickAddForm').reset();new bootstrap.Modal(document.getElementById('quickAddModal')).show();"><i class="bi bi-plus-circle"></i> Add Customer</button>
                </div>
            </div>
            <?php if (empty($allCustomers)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No customers yet</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-custom table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Opening</th>
                            <th>Total Debit</th>
                            <th>Total Credit</th>
                            <th>Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allCustomers as $cust):
                            $bal = floatval($cust['opening_balance']) + floatval($cust['total_debit']) - floatval($cust['total_credit']);
                        ?>
                        <tr>
                            <td><strong><?= sanitize($cust['name']) ?></strong></td>
                            <td><?= sanitize($cust['phone'] ?: '-') ?></td>
                            <td class="amount-cell"><?= floatval($cust['opening_balance']) > 0 ? formatCurrency($cust['opening_balance']) : '-' ?></td>
                            <td class="amount-cell amount-out"><?= floatval($cust['total_debit']) > 0 ? formatCurrency($cust['total_debit']) : '-' ?></td>
                            <td class="amount-cell amount-in"><?= floatval($cust['total_credit']) > 0 ? formatCurrency($cust['total_credit']) : '-' ?></td>
                            <td class="amount-cell" style="font-weight:700; color:<?= $bal >= 0 ? '#e74c3c' : '#28b463' ?>;">
                                <?= formatCurrency(abs($bal)) ?>
                            </td>
                            <td>
                                <a href="customers.php?id=<?= intval($cust['id']) ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-book"></i> Ledger
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
    <?php endif; ?>
</div>

<div class="modal fade" id="quickAddModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="customers.php" id="quickAddForm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name *</label>
            <input type="text" name="quick_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="quick_phone" class="form-control" placeholder="03XX-XXXXXXX">
          </div>
          <div class="mb-3">
            <label class="form-label">Opening Balance</label>
            <input type="number" name="quick_opening" class="form-control" step="0.01" value="0">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="quick_add_customer" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function filterCustomers() {
  var input = document.getElementById('customerSearch').value.toLowerCase();
  var rows = document.querySelectorAll('.table-custom tbody tr');
  for (var i = 0; i < rows.length; i++) {
    rows[i].style.display = rows[i].textContent.toLowerCase().indexOf(input) > -1 ? '' : 'none';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
