<?php
session_start();
require_once '../connection/db.php';

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Initialize filter variables
$tenant_filter = isset($_POST['tenant_filter']) ? trim($_POST['tenant_filter']) : '';
$status_filter = isset($_POST['status_filter']) ? $_POST['status_filter'] : '';
$month_filter = isset($_POST['month_filter']) ? $_POST['month_filter'] : '';
$payment_type_filter = isset($_POST['payment_type_filter']) ? $_POST['payment_type_filter'] : '';

// Build the SQL query with filters
$sql = "
    SELECT p.payment_id, p.boarding_id, p.payment_amount, p.payment_date, p.method, 
           p.appliance_charges, p.appliances, p.payment_for_month_of, p.reason, 
           p.payment_type, p.balance,
           t.first_name, t.last_name, t.tenant_id
    FROM payments p
    JOIN boarding b ON p.boarding_id = b.boarding_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($tenant_filter) {
    $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ?)";
    $params[] = "%$tenant_filter%";
    $params[] = "%$tenant_filter%";
    $types .= 'ss';
}

if ($status_filter) {
    $sql .= " AND p.method = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($month_filter) {
    $sql .= " AND p.payment_for_month_of = ?";
    $params[] = $month_filter;
    $types .= 's';
}

if ($payment_type_filter) {
    $sql .= " AND p.payment_type = ?";
    $params[] = $payment_type_filter;
    $types .= 's';
}

$sql .= " ORDER BY p.payment_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch tenants for the new payment form
$tenantsStmt = $conn->query("
    SELECT t.tenant_id, t.first_name, t.last_name, b.boarding_id
    FROM tenants t
    JOIN boarding b ON t.tenant_id = b.tenant_id
    WHERE b.due_date >= CURDATE()
");
$tenants = $tenantsStmt->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments</title>
    <link rel="stylesheet" href="CSS/sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container mt-4">
        <h2><strong>Payments</strong></h2>
        
        <!-- Filter Form -->
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="tenant_filter" class="form-label">Search by Tenant</label>
                    <input type="text" class="form-control" id="tenant_filter" name="tenant_filter" 
                           value="<?php echo htmlspecialchars($tenant_filter); ?>" placeholder="Enter tenant name">
                </div>
                <div class="col-md-2">
                    <label for="status_filter" class="form-label">Payment Status</label>
                    <select class="form-select" id="status_filter" name="status_filter">
                        <option value="">All Statuses</option>
                        <option value="Credit" <?php if ($status_filter == 'Credit') echo 'selected'; ?>>Unpaid</option>
                        <option value="Cash" <?php if ($status_filter == 'Cash') echo 'selected'; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="month_filter" class="form-label">Payment Month</label>
                    <input type="month" class="form-control" id="month_filter" name="month_filter" 
                           value="<?php echo htmlspecialchars($month_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label for="payment_type_filter" class="form-label">Payment Type</label>
                    <select class="form-select" id="payment_type_filter" name="payment_type_filter">
                        <option value="">All Types</option>
                        <option value="Monthly Rent" <?php if ($payment_type_filter == 'Monthly Rent') echo 'selected'; ?>>Monthly Rent</option>
                        <option value="Advance" <?php if ($payment_type_filter == 'Advance') echo 'selected'; ?>>Advance</option>
                        <option value="Other" <?php if ($payment_type_filter == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary ms-2">Clear</a>
                </div>
            </div>
        </form>

        <!-- New Payment Form -->
        <div class="mb-4">
            <h4>Add New Payment</h4>
            <form method="POST" action="edit_payment.php" class="row g-3">
                <div class="col-md-4">
                    <label for="new_payment_tenant" class="form-label">Tenant</label>
                    <select name="tenant_id" id="new_payment_tenant" class="form-control" required>
                        <option value="">Select Tenant</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['tenant_id']; ?>" data-boarding-id="<?php echo $tenant['boarding_id']; ?>">
                                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="boarding_id" id="new_payment_boarding_id">
                </div>
                <div class="col-md-3">
                    <label for="new_payment_type" class="form-label">Payment Type</label>
                    <select name="new_payment" id="new_payment_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="Advance">Advance</option>
                        <option value="Other">Other (e.g., Guest Stay)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success">Add Payment</button>
                </div>
            </form>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <a href="payment_automation.php" class="btn btn-primary mb-3">Run Payment Automation</a> <br>
        <small style="color:red">Before you run payment automation, make sure you have inputted the <strong>EXPENSES</strong> this month. This will be included in the computation if there are any surcharges.</small>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Payment Type</th>
                    <th>Month</th>
                    <th>Amount</th>
                    <th>Balance</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th>Appliances</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="11" class="text-center">No payments found matching the criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                        <td>
                            <?php
                            if (!empty($payment['payment_for_month_of'])) {
                                // Convert string to timestamp, then format as full month name
                                echo date('F', strtotime($payment['payment_for_month_of']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>

                        <td>₱<?php echo number_format($payment['payment_amount'], 2); ?></td>
                        <td>₱<?php echo number_format($payment['balance'], 2); ?></td>
                        <td><?php echo $payment['payment_date']; ?></td>
                        <td>
                            <?php if ($payment['method'] == 'Credit'): ?>
                                <span class="badge bg-warning">Unpaid</span>
                            <?php else: ?>
                                <span class="badge bg-success">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($payment['appliances'] ?: 'None'); ?></td>
                        
                        <td><?php echo htmlspecialchars($payment['reason'] ?: 'None'); ?></td>
                        <td>
                            <?php if ($payment['method'] == 'Credit' && $payment['payment_type'] == 'Monthly Rent'): ?>
                                <a href="edit_payment.php?payment_id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-warning">Pay</a>
                            <?php endif; ?>
                            <a href="view_receipt.php?payment_id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-info">View Receipt</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tenantSelect = document.getElementById('new_payment_tenant');
    const boardingIdInput = document.getElementById('new_payment_boarding_id');

    if (tenantSelect && boardingIdInput) {
        tenantSelect.addEventListener('change', function() {
            const selectedOption = tenantSelect.options[tenantSelect.selectedIndex];
            boardingIdInput.value = selectedOption.dataset.boardingId || '';
        });
    }
});
</script>
</body>
</html>