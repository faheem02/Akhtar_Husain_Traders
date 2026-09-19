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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer_entry'])) {
    $customerId   = intval($_POST['customer_id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $entryType    = $_POST['entry_type'] ?? 'cash_in';
    $amount       = floatval($_POST['amount'] ?? 0);
    $entryDate    = $_POST['entry_date'] ?? getTodayDate();
    $description  = trim($_POST['description'] ?? '');

    if ($customerId > 0 && empty($customerName)) {
        $cstmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
        $cstmt->execute([$customerId]);
        $cRow = $cstmt->fetch();
        if ($cRow) {
            $customerName = $cRow['name'];
        }
    }

    if ($entryType === 'adjustment') {
        $adjustDir = $_POST['adjustment_direction'] ?? 'in';
        $entryType = ($adjustDir === 'in') ? 'adjustment_in' : 'adjustment_out';
    }

    if ($amount > 0 && ($customerId > 0 || $customerName !== '')) {
        $stmt = $pdo->prepare("INSERT INTO cash_entries (entry_type, customer_name, customer_id, amount, entry_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$entryType, $customerName, $customerId ?: null, $amount, $entryDate, $description]);

        $typeLabel = 'Cash In';
        if ($entryType === 'cash_out') $typeLabel = 'Cash Out';
        elseif ($entryType === 'adjustment_in') $typeLabel = 'Adjustment (+)';
        elseif ($entryType === 'adjustment_out') $typeLabel = 'Adjustment (-)';

        setFlash('success', "$typeLabel of " . formatCurrency($amount) . " saved for \"" . sanitize($customerName) . "\".");
    } else {
        setFlash('error', 'Please enter a valid amount.');
    }

    $redirect = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : 'customers.php';
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $cid = intval($_POST['customer_id'] ?? 0);
    if ($cid > 0) {
        $cstmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
        $cstmt->execute([$cid]);
        $cRow = $cstmt->fetch();
        $cName = $cRow ? $cRow['name'] : 'Customer';

        $pdo->beginTransaction();
        try {
            // Delete customer cash entries
            $delEntries = $pdo->prepare("DELETE FROM cash_entries WHERE customer_id = ?");
            $delEntries->execute([$cid]);

            // Delete customer record
            $delCust = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $delCust->execute([$cid]);

            $pdo->commit();
            setFlash('success', 'Customer "' . sanitize($cName) . '" and all related records deleted successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error deleting customer: ' . $e->getMessage());
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
        <h2>
            <i class="bi bi-people"></i> <?= $customerInfo ? sanitize($customerInfo['name']) . ' - Ledger' : 'Customers' ?>
        </h2>
        <div class="header-actions">
            <?php if ($customerInfo): ?>
            <button type="button" class="btn btn-success btn-sm" onclick="openCustomerEntryModal(<?= intval($customerInfo['id']) ?>, <?= htmlspecialchars(json_encode($customerInfo['name']), ENT_QUOTES, 'UTF-8') ?>)">
                <i class="bi bi-plus-circle"></i> New Entry
            </button>
            <a href="customers.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> All Customers
            </a>
            <button type="button" class="btn btn-print btn-sm" onclick="openPrintModal(<?= intval($customerInfo['id']) ?>, <?= htmlspecialchars(json_encode($customerInfo['name']), ENT_QUOTES, 'UTF-8') ?>, '<?= sanitize($from) ?>', '<?= sanitize($to) ?>')">
                <i class="bi bi-printer"></i> Print / Download
            </button>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="cash-book.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-journal-bookmark"></i> Cash Book
            </a>
            <a href="closings-history.php" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-journal-check"></i> Closing History
            </a>
        </div>
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
                <div class="col-md-6" style="position:relative;">
                    <input type="text" id="customerSearch" class="form-control" placeholder="Search by name, phone..." oninput="filterCustomers()" autocomplete="off">
                    <div id="searchDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ddd; border-radius:0 0 8px 8px; max-height:200px; overflow-y:auto; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
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
                            <th class="text-center" style="white-space: nowrap; width: 1%;">Action</th>
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
                            <td style="white-space: nowrap; width: 1%;">
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-outline-success btn-sm action-btn" onclick="openCustomerEntryModal(<?= intval($cust['id']) ?>, <?= htmlspecialchars(json_encode($cust['name']), ENT_QUOTES, 'UTF-8') ?>)" title="Add Cash Entry">
                                        <i class="bi bi-plus-circle"></i> <span>Entry</span>
                                    </button>
                                    <a href="customers.php?id=<?= intval($cust['id']) ?>" class="btn btn-outline-info btn-sm action-btn" title="View Customer Ledger">
                                        <i class="bi bi-book"></i> <span>Ledger</span>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm action-btn" onclick="confirmDeleteCustomer(<?= intval($cust['id']) ?>, <?= htmlspecialchars(json_encode($cust['name']), ENT_QUOTES, 'UTF-8') ?>)" title="Delete Customer">
                                        <i class="bi bi-trash"></i> <span>Delete</span>
                                    </button>
                                </div>
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

<!-- Customer Entry Modal -->
<div class="modal fade" id="customerEntryModal" tabindex="-1" aria-labelledby="customerEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="customers.php" id="customerEntryForm">
        <input type="hidden" name="add_customer_entry" value="1">
        <input type="hidden" name="customer_id" id="modal_entry_customer_id" value="">
        <input type="hidden" name="customer_name" id="modal_entry_customer_name" value="">
        <input type="hidden" name="redirect_url" id="modal_entry_redirect_url" value="">
        
        <div class="modal-header">
          <h5 class="modal-title" id="customerEntryModalLabel"><i class="bi bi-journal-plus"></i> Customer Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3 p-2 bg-light rounded border d-flex align-items-center">
            <i class="bi bi-person-circle fs-4 text-primary me-2"></i>
            <div>
              <div class="text-muted small">Customer Name</div>
              <strong id="modalCustomerNameDisplay" class="text-dark fs-6"></strong>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Entry Type</label>
            <div class="d-flex gap-3 flex-wrap">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="entry_type" id="modalTypeCashIn" value="cash_in" checked onchange="updateModalFormColor()">
                <label class="form-check-label fw-bold text-success" for="modalTypeCashIn"><i class="bi bi-arrow-down-circle"></i> Cash In</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="entry_type" id="modalTypeCashOut" value="cash_out" onchange="updateModalFormColor()">
                <label class="form-check-label fw-bold text-danger" for="modalTypeCashOut"><i class="bi bi-arrow-up-circle"></i> Cash Out</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="entry_type" id="modalTypeAdjustment" value="adjustment" onchange="updateModalFormColor()">
                <label class="form-check-label fw-bold" for="modalTypeAdjustment" style="color:#e67e22;"><i class="bi bi-sliders"></i> Balance Adjustment</label>
              </div>
            </div>

            <div id="modalAdjustmentDir" class="mt-2" style="display:none;">
              <label class="form-label fw-bold small text-muted">Direction</label>
              <div class="d-flex gap-4">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="adjustment_direction" id="modalAdjIn" value="in" checked>
                  <label class="form-check-label fw-bold text-success" for="modalAdjIn"><i class="bi bi-plus-circle"></i> Increase (+)</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="adjustment_direction" id="modalAdjOut" value="out">
                  <label class="form-check-label fw-bold text-danger" for="modalAdjOut"><i class="bi bi-dash-circle"></i> Decrease (-)</label>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Amount *</label>
            <div class="input-group">
              <span class="input-group-text">PKR</span>
              <input type="number" name="amount" id="modalEntryAmount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Date *</label>
            <input type="date" name="entry_date" id="modalEntryDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Description (optional)</label>
            <input type="text" name="description" id="modalEntryDescription" class="form-control" placeholder="Note or reference...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="modalSubmitBtn" class="btn btn-success">
            <i class="bi bi-arrow-down-circle"></i> Save Cash In
          </button>
        </div>
      </form>
    </div>
  </div>
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
var customerNames = <?= json_encode(array_map(function($c) { return $c['name']; }, $allCustomers)) ?>;
var searchInput = document.getElementById('customerSearch');
var searchDropdown = document.getElementById('searchDropdown');

function filterCustomers() {
  if (!searchInput) return;
  var input = searchInput.value.toLowerCase();

  var rows = document.querySelectorAll('.table-custom tbody tr');
  for (var i = 0; i < rows.length; i++) {
    rows[i].style.display = rows[i].textContent.toLowerCase().indexOf(input) > -1 ? '' : 'none';
  }

  if (input.length === 0) {
    if (searchDropdown) searchDropdown.style.display = 'none';
    return;
  }

  var matches = customerNames.filter(function(name) {
    return name.toLowerCase().indexOf(input) > -1;
  });

  if (matches.length === 0) {
    if (searchDropdown) searchDropdown.style.display = 'none';
    return;
  }

  var html = '';
  for (var i = 0; i < matches.length; i++) {
    html += '<div class="search-suggestion" onmousedown="selectSuggestion(\'' + matches[i].replace(/'/g, "\\'") + '\')" style="padding:8px 14px; cursor:pointer; font-size:0.9rem; border-bottom:1px solid #f0f0f0;">' + matches[i] + '</div>';
  }
  if (searchDropdown) {
    searchDropdown.innerHTML = html;
    searchDropdown.style.display = 'block';
  }
}

function selectSuggestion(name) {
  if (!searchInput) return;
  searchInput.value = name;
  if (searchDropdown) searchDropdown.style.display = 'none';
  filterCustomers();
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('#customerSearch') && !e.target.closest('#searchDropdown')) {
    if (searchDropdown) searchDropdown.style.display = 'none';
  }
});

function openCustomerEntryModal(id, name) {
  document.getElementById('modal_entry_customer_id').value = id;
  document.getElementById('modal_entry_customer_name').value = name;
  document.getElementById('modalCustomerNameDisplay').textContent = name;
  document.getElementById('modal_entry_redirect_url').value = window.location.href;
  
  // reset inputs
  document.getElementById('modalEntryAmount').value = '';
  document.getElementById('modalEntryDate').value = '<?= date('Y-m-d') ?>';
  document.getElementById('modalEntryDescription').value = '';
  document.getElementById('modalTypeCashIn').checked = true;
  document.getElementById('modalAdjIn').checked = true;
  updateModalFormColor();
  
  var modalEl = document.getElementById('customerEntryModal');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
  setTimeout(function() {
    var amtInput = document.getElementById('modalEntryAmount');
    if (amtInput) amtInput.focus();
  }, 400);
}

function updateModalFormColor() {
  var isIn = document.getElementById('modalTypeCashIn').checked;
  var isOut = document.getElementById('modalTypeCashOut').checked;
  var isAdj = document.getElementById('modalTypeAdjustment').checked;
  var btn = document.getElementById('modalSubmitBtn');
  var adjDir = document.getElementById('modalAdjustmentDir');

  if (isAdj) {
    adjDir.style.display = 'block';
    btn.className = 'btn btn-warning text-white';
    btn.innerHTML = '<i class="bi bi-sliders"></i> Save Adjustment';
  } else {
    adjDir.style.display = 'none';
    if (isIn) {
      btn.className = 'btn btn-success';
      btn.innerHTML = '<i class="bi bi-arrow-down-circle"></i> Save Cash In';
    } else {
      btn.className = 'btn btn-danger';
      btn.innerHTML = '<i class="bi bi-arrow-up-circle"></i> Save Cash Out';
    }
  }
}

function openPrintModal(customerId, customerName, fromDate = '', toDate = '') {
  document.getElementById('printModalCustomerName').textContent = customerName;
  var loader = document.getElementById('previewLoader');
  var iframe = document.getElementById('previewIframe');
  
  if (loader) loader.style.display = 'block';
  if (iframe) {
    iframe.style.display = 'none';
    var url = 'print-customers.php?id=' + encodeURIComponent(customerId) + '&preview=1';
    if (fromDate) url += '&from=' + encodeURIComponent(fromDate);
    if (toDate) url += '&to=' + encodeURIComponent(toDate);
    iframe.src = url;
  }

  var modalEl = document.getElementById('printLedgerModal');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
}

function onPreviewIframeLoaded() {
  var loader = document.getElementById('previewLoader');
  var iframe = document.getElementById('previewIframe');
  if (loader) loader.style.display = 'none';
  if (iframe) iframe.style.display = 'block';
}

function printFromPreviewModal() {
  var iframe = document.getElementById('previewIframe');
  if (iframe && iframe.contentWindow) {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
  }
}

function downloadPdfFromPreviewModal() {
  var iframe = document.getElementById('previewIframe');
  if (iframe && iframe.contentWindow && typeof iframe.contentWindow.downloadPDF === 'function') {
    iframe.contentWindow.downloadPDF();
  } else if (iframe && iframe.contentWindow) {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
  }
}

function confirmDeleteCustomer(id, name) {
  document.getElementById('deleteCustomerId').value = id;
  document.getElementById('deleteCustomerName').textContent = name;
  var modalEl = document.getElementById('deleteCustomerModal');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
}
</script>

<!-- Delete Customer Modal -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-labelledby="deleteCustomerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="customers.php">
        <input type="hidden" name="delete_customer" value="1">
        <input type="hidden" name="customer_id" id="deleteCustomerId" value="">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteCustomerModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Customer</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Are you sure you want to delete customer <strong id="deleteCustomerName" class="text-danger"></strong>?</p>
          <div class="alert alert-warning small mb-0">
            <i class="bi bi-info-circle-fill me-1"></i>
            This will permanently remove this customer and all their ledger transactions. This action cannot be undone.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-trash-fill me-1"></i> Delete Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Print / Download Live Preview Modal -->
<div class="modal fade" id="printLedgerModal" tabindex="-1" aria-labelledby="printLedgerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 950px;">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light py-2 px-3 align-items-center border-bottom">
        <h5 class="modal-title fs-6 mb-0 text-dark" id="printLedgerModalLabel">
          <i class="bi bi-file-earmark-text text-primary me-1"></i> 
          Ledger Preview: <strong id="printModalCustomerName" class="text-primary">-</strong>
        </h5>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="printFromPreviewModal()">
            <i class="bi bi-printer-fill me-1"></i> Print
          </button>
          <button type="button" class="btn btn-danger btn-sm px-3 shadow-sm" onclick="downloadPdfFromPreviewModal()">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
          </button>
          <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0" style="background: #e9ecef; min-height: 520px; position: relative;">
        <div id="previewLoader" class="text-center py-5 text-secondary">
          <div class="spinner-border text-primary mb-2" role="status"></div>
          <div>Loading preview...</div>
        </div>
        <iframe id="previewIframe" src="" style="width: 100%; height: 75vh; border: none; display: none; background: #fff;" onload="onPreviewIframeLoaded()"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

