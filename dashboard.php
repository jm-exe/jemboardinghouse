<?php
session_start();
$successMessage = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Tenant') {
  header('Location: index.php');
  exit();
}

$tenant_id=$_SESSION['tenant_id'];


include 'connection/db.php';  

// Calculate balance by summing all payments and comparing to expected rent
$balanceQuery = "SELECT 
                    SUM(b.monthly_rent) AS total_rent_due,
                    IFNULL(SUM(p.payment_amount), 0) AS total_paid,
                    (SUM(b.monthly_rent) - IFNULL(SUM(p.payment_amount), 0)) AS balance
                FROM boarding bo
                JOIN beds b ON bo.bed_id = b.bed_id
                LEFT JOIN payments p ON bo.boarding_id = p.boarding_id
                WHERE bo.tenant_id = ?";
$stmt = $conn->prepare($balanceQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$balance = 0;
if ($result && $row = $result->fetch_assoc()) {
    $balance = $row['balance'];  
}

$paymentHistoryQuery = "SELECT 
                            p.payment_amount AS amount, 
                            p.payment_date AS date,
                            p.payment_for_month_of AS month
                        FROM payments p
                        JOIN boarding bo ON p.boarding_id = bo.boarding_id
                        WHERE bo.tenant_id = ?
                        ORDER BY p.payment_date DESC 
                        LIMIT 5";
$stmt = $conn->prepare($paymentHistoryQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$paymentHistory = $stmt->get_result();

// Fetch tenant details for display
$tenantDetailsQuery = "SELECT 
                          t.first_name, t.last_name, t.mobile_no,
                          c.course_code, c.course_description,
                          b.bed_no, b.deck, r.room_no,
                          g.first_name AS guardian_first, g.last_name AS guardian_last,
                          g.mobile_no AS guardian_mobile, g.relationship,
                          ay.start_year, ay.end_year, ay.semester,
                          t.profile_picture, 
                          t.mobile_no, 
                          t.is_willing_to_continue
                       FROM tenants t
                       JOIN course c ON t.course_id = c.course_id
                       JOIN guardians g ON t.guardian_id = g.guardian_id
                       JOIN academic_years ay ON t.academic_year_id = ay.academic_year_id
                       JOIN boarding bo ON t.tenant_id = bo.tenant_id
                       JOIN beds b ON bo.bed_id = b.bed_id
                       JOIN rooms r ON b.room_id = r.room_id
                       WHERE t.tenant_id = ?";
$stmt = $conn->prepare($tenantDetailsQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$tenantDetails = $stmt->get_result()->fetch_assoc();


// === Check if today is within 14 days before semester ends ===
$today = date('Y-m-d');

// Estimate semester end
if ($tenantDetails['semester'] === 'First') {
    $semesterEnd = $tenantDetails['start_year'] . '-12-15';
} elseif ($tenantDetails['semester'] === 'Second') {
    $semesterEnd = ($tenantDetails['start_year'] + 1) . '-05-15';
} else {
    $semesterEnd = ($tenantDetails['start_year'] + 1) . '-07-31';
}

// Show confirmation if within 14 days before end
$confirmationStart = date('Y-m-d', strtotime("$semesterEnd -30 days"));
$debugMode = false;

$showConfirmation = (
    $debugMode ||
    ($today >= $confirmationStart &&
     $today <= $semesterEnd &&
     $tenantDetails['is_willing_to_continue'] == 0)
);




?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/styles.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="main-content">
 

  <div class="container-fluid">
    <div class="row">


      <!-- Main content area with margin-left to accommodate sidebar -->
      <div class="col-lg-10 mr-4" style="margin-left: 280px;">

       <?php if ($successMessage): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

        <h1><strong>Dashboard</strong></h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</p>
        

             <?php if ($debugMode): ?>
              <div class="alert alert-info mt-2">
                <strong>Debug Info:</strong><br>
                Today: <?= $today ?><br>
                Semester End: <?= $semesterEnd ?><br>
                Confirmation Start: <?= $confirmationStart ?><br>
                AY: <?= $tenantDetails['start_year'] ?> - <?= $tenantDetails['end_year'] ?> (<?= $tenantDetails['semester'] ?>)<br>
                is_willing_to_continue: <?= $tenantDetails['is_willing_to_continue'] ?>
              </div>
            <?php endif; ?>

            


            <?php if ($showConfirmation): ?>
              <div class="alert alert-warning border-warning mt-3 p-4 rounded">
                <h5><i class="fas fa-question-circle"></i> Confirm Your Tenancy for Next Semester</h5>
                <p>Would you like to continue staying in the boarding house for the next semester?</p>
                <form method="POST" action="confirm-continuation.php" class="mt-3">
                  <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                  <button type="submit" name="confirm" value="1" class="btn btn-success">
                    Yes, I want to continue
                  </button>
                </form>
              </div>
            <?php endif; ?>



        <!-- Tenant Information Card -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Your Information</h5>
            <a href="edit-tenant.php" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-edit"></i> Edit Info
            </a>
          </div>
          <div class="card-body">
            <div class="row align-items-center">
              <!-- Profile Image -->
             <div class="col-md-3 text-center">
              <?php
                $imagePath = $tenantDetails['profile_picture'] 
                  ? 'uploads/profiles/' . htmlspecialchars($tenantDetails['profile_picture']) 
                  : 'Pictures/logo.png';
              ?>
              <div class="profile-pic-container mb-3 mx-auto">
                <img src="<?= $imagePath ?>" alt="Tenant Image">
              </div>
            </div>

              
              <!-- Details -->
              <div class="col-md-9">
                <div class="row">
                  <div class="col-md-6">
                    <p><strong>Name:</strong> <?= htmlspecialchars($tenantDetails['first_name'] . ' ' . $tenantDetails['last_name']); ?></p>
                    <p><strong>Course:</strong> <?= htmlspecialchars($tenantDetails['course_code'] . ' - ' . $tenantDetails['course_description']); ?></p>
                    <p><strong>Academic Year:</strong> <?= htmlspecialchars($tenantDetails['start_year'] . '-' . $tenantDetails['end_year'] . ' (' . $tenantDetails['semester'] . ')'); ?></p>
                    <p><strong>Mobile Number: </strong> <?=htmlspecialchars($tenantDetails['mobile_no']); ?> </p>
                  </div>
                  <div class="col-md-6">
                    <p><strong>Room:</strong> <?= htmlspecialchars($tenantDetails['room_no']); ?></p>
                    <p><strong>Bed:</strong> <?= htmlspecialchars($tenantDetails['bed_no'] . ' (' . $tenantDetails['deck'] . ' deck)'); ?></p>
                    <p><strong>Guardian:</strong> <?= htmlspecialchars($tenantDetails['guardian_first'] . ' ' . $tenantDetails['guardian_last'] . ' (' . $tenantDetails['relationship'] . ')'); ?></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>


        <!-- Balance and Payment History Cards -->
        <div class="row">
          <div class="col-md-6">
            <div class="card mb-3">
              <div class="row g-0">
                <div class="col-md-4 d-flex align-items-center justify-content-center">
                  <i class="fas fa-wallet fa-3x text-success"></i>
                </div>
                <div class="col-md-8">
                  <div class="card-body">
                    <h5 class="card-title">Current Balance</h5>
                    <p class="card-text">PHP <?php echo number_format($balance, 2); ?></p>
                    <!-- <a href="make-payment.php" class="btn btn-primary">Make Payment</a> -->
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card mb-3">
              <div class="card-header"><h5>Recent Payments</h5></div>
              <ul class="list-group list-group-flush">
                <?php while ($payment = $paymentHistory->fetch_assoc()) { ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?php echo htmlspecialchars($payment['month']); ?> - <?php echo date("F j, Y", strtotime($payment['date'])); ?></span>
                    <span class="badge bg-success rounded-pill">PHP <?php echo number_format($payment['amount'], 2); ?></span>
                  </li>
                <?php } ?>
                <?php if ($paymentHistory->num_rows === 0): ?>
                  <li class="list-group-item">No payment history found</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

      </div> <!-- end main col -->
    </div> <!-- end row -->
  </div> <!-- end container-fluid -->
  </div>
  

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
</body>

</html>