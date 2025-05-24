<?php
session_start();
require '../connection/db.php';

// Filter by status if set
$status_filter = $_GET['status'] ?? '';
$where = '';
if ($status_filter == 'Paid' || $status_filter == 'Unpaid') {
    $where = "WHERE i.status = '$status_filter'";
}

// Fetch all invoices (with tenant info)
$sql = "SELECT i.*, t.first_name, t.last_name
        FROM invoices i
        JOIN tenants t ON i.tenant_id = t.tenant_id
        $where
        ORDER BY i.created_at DESC";
$result = $conn->query($sql);

// For showing success/error message
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Invoices/Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title mb-4">Manage Payments / Invoices</h3>
            <?php if ($msg): ?>
                <div class="alert alert-info"><?php echo $msg; ?></div>
            <?php endif; ?>
            <form class="row g-3 mb-3" method="GET" action="">
                <div class="col-auto">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Paid" <?php if ($status_filter == 'Paid') echo 'selected'; ?>>Paid</option>
                        <option value="Unpaid" <?php if ($status_filter == 'Unpaid') echo 'selected'; ?>>Unpaid</option>
                    </select>
                </div>
            </form>
            <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Invoice Number</th>
                        <th>Tenant Name</th>
                        <th>Room</th>
                        <th>Bed</th>
                        <th>Room Rate</th>
                        <th>Electricity</th>
                        <th>Water</th>
                        <th>Total Due</th>
                        <th>Month/Year</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows): $i = 1; while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['room_no']) ?></td>
                        <td><?= htmlspecialchars($row['bed_no']) ?></td>
                        <td>₱<?= number_format($row['room_rate'],2) ?></td>
                        <td>₱<?= number_format($row['electricity_bill'],2) ?></td>
                        <td>₱<?= number_format($row['water_bill'],2) ?></td>
                        <td><strong>₱<?= number_format($row['total_due'],2) ?></strong></td>
                        <td><?= htmlspecialchars($row['month']) . ' / ' . $row['year'] ?></td>
                        <td>
                            <form method="POST" action="update-invoice-status.php" class="d-inline">
                                <input type="hidden" name="invoice_id" value="<?= $row['invoice_id'] ?>">
                                <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                    <option value="Paid" <?= $row['status']=='Paid'?'selected':''; ?>>Paid</option>
                                    <option value="Unpaid" <?= $row['status']=='Unpaid'?'selected':''; ?>>Unpaid</option>
                                </select>
                            </form>
                        </td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td>
                            <!-- Optional: Add delete/view buttons -->
                            <a href="view-invoice.php?invoice_id=<?= $row['invoice_id'] ?>" class="btn btn-sm btn-info">View</a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="13" class="text-center">No invoices found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
