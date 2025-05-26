<?php
session_start();
require_once 'connection/db.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Tenant') {
    header('Location: index.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$type = $_GET['type'] ?? '';
$where = "WHERE t.tenant_id = ?";
if ($type === "balance") {
    $where .= " AND p.method = 'Credit'";
} elseif ($type === "paid") {
    $where .= " AND p.method <> 'Credit'";
}

// Dynamic page title
$pageTitle = 'Payment Breakdown';
if ($type === 'balance') {
    $pageTitle = 'Balance Breakdown';
} elseif ($type === 'paid') {
    $pageTitle = 'Total Paid Breakdown';
}

// Fetch payments with tenant details, including reason
$sql = "
    SELECT p.payment_id, p.boarding_id, p.payment_amount, p.payment_date, p.method, 
           p.appliance_charges, p.appliances, p.payment_for_month_of, p.reason,
           t.first_name, t.last_name, t.tenant_id
    FROM payments p
    JOIN boarding b ON p.boarding_id = b.boarding_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
    $where
    ORDER BY p.payment_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compute total amount for the report
$totalAmount = 0;
foreach ($payments as $payment) {
    $totalAmount += $payment['payment_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/styles.css">
</head>
<body>
 <?php 
  $currentPage = 'dashboard'; 
  include 'sidebar.php'; ?>

  <div class="main-content">
    <div class="col-lg-10 mr-4" style="margin-left: 280px;">
      <div class="container-fluid">
        <h2><strong><?= htmlspecialchars($pageTitle) ?></strong></h2>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th>Appliances</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['payment_for_month_of']); ?></td>
                    <td>₱<?= number_format($payment['payment_amount'], 2); ?></td>
                    <td><?= htmlspecialchars($payment['payment_date']); ?></td>
                    <td>
                        <?php if ($payment['method'] == 'Credit'): ?>
                            <span class="badge bg-warning">Unpaid</span>
                        <?php else: ?>
                            <span class="badge bg-success">Paid</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($payment['appliances'] ?: 'None'); ?></td>
                    <td><?= htmlspecialchars($payment['reason'] ?: 'None'); ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if (!empty($payments)): ?>
                <tr>
                    <td class="text-end fw-bold" colspan="1">TOTAL</td>
                    <td class="fw-bold text-success">₱<?= number_format($totalAmount, 2) ?></td>
                    <td colspan="4"></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
