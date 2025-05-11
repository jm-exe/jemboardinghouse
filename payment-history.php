<?php
session_start();
if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
include 'connection/db.php';

// Handle search
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$searchClause = $search ? "AND (p.payment_for_month_of LIKE ? OR DATE_FORMAT(p.payment_date, '%M %Y') LIKE ?)" : "";




// Count total entries
$countQuery = "SELECT COUNT(*) AS total FROM payments p
               JOIN boarding bo ON p.boarding_id = bo.boarding_id
               WHERE bo.tenant_id = ? $searchClause";
$stmt = $conn->prepare($search ? $countQuery : str_replace('LIKE ? OR DATE_FORMAT(p.payment_date, \'%M %Y\') LIKE ?', '', $countQuery));
if ($search) {
    $likeSearch = "%$search%";
    $stmt->bind_param("iss", $tenant_id, $likeSearch, $likeSearch);
} else {
    $stmt->bind_param("i", $tenant_id);
}
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);




// Total paid
$totalPaidQuery = "SELECT IFNULL(SUM(p.payment_amount), 0) AS total_paid
                   FROM payments p JOIN boarding bo ON p.boarding_id = bo.boarding_id WHERE bo.tenant_id = ?";
$stmt = $conn->prepare($totalPaidQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$total_paid = $stmt->get_result()->fetch_assoc()['total_paid'];

// Paginated payments
$historyQuery = "SELECT p.payment_amount AS amount, p.payment_date AS date, p.payment_for_month_of AS month
                 FROM payments p
                 JOIN boarding bo ON p.boarding_id = bo.boarding_id
                 WHERE bo.tenant_id = ? $searchClause
                 ORDER BY p.payment_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($search ? $historyQuery : str_replace($searchClause, '', $historyQuery));
if ($search) {
    $stmt->bind_param("issii", $tenant_id, $likeSearch, $likeSearch, $limit, $offset);
} else {
    $stmt->bind_param("iii", $tenant_id, $limit, $offset);
}
$stmt->execute();
$paymentHistory = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment History</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/styles.css">

</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
<div class="container-fluid">
  <div class="row">
  <div class="col-lg-10 mr-4" style="margin-left: 270px;">
      <h1><strong>Payment History</strong></h1>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex" role="search">
          <input type="text" name="search" class="form-control me-2" placeholder="Search month/date..." value="<?php echo htmlspecialchars($search); ?>">
          <button class="btn btn-outline-primary" type="submit">Search</button>
        </form>
        <a href="export-payment-history.php" class="btn btn-outline-danger">Download PDF</a>
      </div>

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
          <h5>Transaction Records</h5>
          <span class="text-success"><strong>Total Paid:</strong> PHP <?php echo number_format($total_paid, 2); ?></span>
        </div>
        <ul class="list-group list-group-flush">
          <?php while ($payment = $paymentHistory->fetch_assoc()) { ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span><?php echo htmlspecialchars($payment['month']); ?> - <?php echo date("F j, Y", strtotime($payment['date'])); ?></span>
              <span class="badge bg-success rounded-pill">PHP <?php echo number_format($payment['amount'], 2); ?></span>
            </li>
          <?php } ?>
          <?php if ($paymentHistory->num_rows === 0): ?>
            <li class="list-group-item text-muted">No payment history found.</li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Pagination -->
      <nav>
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
              <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>

    </div>
  </div>
</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
