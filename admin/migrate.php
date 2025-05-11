<?php
session_start();
include '../connection/db.php';

// Auto-detect current academic year & semester
$month = (int)date('n');
$year = (int)date('Y');
if ($month >= 8 && $month <= 12) {
    $semester = 'First';
    $startYear = $year;
} elseif ($month >= 1 && $month <= 5) {
    $semester = 'Second';
    $startYear = $year - 1;
} else {
    $semester = 'Summer';
    $startYear = $year - 1;
}

// Get current academic year
$currentAYStmt = $conn->prepare("SELECT * FROM academic_years WHERE start_year = ? AND semester = ? LIMIT 1");
$currentAYStmt->bind_param("is", $startYear, $semester);
$currentAYStmt->execute();
$currentAYResult = $currentAYStmt->get_result();
$currentAY = $currentAYResult->fetch_assoc();
if (!$currentAY) {
    die("No current academic year found. Please add one first.");
}
$currentAYID = $currentAY['academic_year_id'];

// Get next academic year
$nextAYQuery = "SELECT * FROM academic_years WHERE academic_year_id > $currentAYID ORDER BY academic_year_id ASC LIMIT 1";
$nextAYResult = $conn->query($nextAYQuery);
$nextAY = $nextAYResult->fetch_assoc();
if (!$nextAY) {
    $_SESSION['success_message'] = "No next academic year found. Please add one first.";
    header("Location: system-management.php");
    exit();
}
$nextAYID = $nextAY['academic_year_id'];

// Handle migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate']) && isset($_POST['selected_tenants'])) {
    foreach ($_POST['selected_tenants'] as $tenantID) {
        $tenantID = (int)$tenantID;

        // Insert into tenant_history
        $historyStmt = $conn->prepare("
            INSERT INTO tenant_history (tenant_id, academic_year_id, bed_id, start_date, end_date, status)
            SELECT t.tenant_id, t.academic_year_id, bo.bed_id, bo.start_date, bo.due_date, 'Migrated'
            FROM tenants t
            JOIN boarding bo ON bo.tenant_id = t.tenant_id
            WHERE t.tenant_id = ?
            ORDER BY bo.boarding_id DESC LIMIT 1
        ");
        $historyStmt->bind_param("i", $tenantID);
        $historyStmt->execute();

        // Update current tenant
        $updateStmt = $conn->prepare("UPDATE tenants SET academic_year_id = ?, is_willing_to_continue = 0 WHERE tenant_id = ?");
        $updateStmt->bind_param("ii", $nextAYID, $tenantID);
        $updateStmt->execute();
    }

    $_SESSION['success_message'] = count($_POST['selected_tenants']) . " tenant(s) migrated to AY {$nextAY['start_year']}-{$nextAY['end_year']}.";
    header("Location: migrate.php");
    exit();
}

// Fetch tenants
$tenantsQuery = "
    SELECT tenant_id, first_name, last_name, is_willing_to_continue
    FROM tenants
    WHERE academic_year_id = $currentAYID
    ORDER BY is_willing_to_continue DESC, last_name ASC
";
$tenants = $conn->query($tenantsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <style>
        .badge-confirmed { background-color: #28a745; }
        .badge-pending { background-color: #6c757d; }
        .table td, .table th { vertical-align: middle; }

        h3{
          margin-bottom: 10px;
          font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
  <br>
    <div class="d-flex justify-content-between align-items-center mb-3">
        
          <h3 class="mb-0"><i class="bi bi-arrow-left-right"></i> Tenant Migration - AY <?= "{$currentAY['start_year']}-{$currentAY['end_year']} ({$semester} Semester)" ?></h3>
       
        <a href="system-management.php" class="btn btn-outline-primary">
            <i class="bi bi-gear"></i> System Settings
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <div class="container bg-white p-4 shadow rounded">
      <form method="POST">
        <?php if ($tenants->num_rows > 0): ?>
        <table id="tenantTable" class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th class="text-center">Select</th>
                    <th>Tenant Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $tenants->fetch_assoc()): ?>
                <tr>
                    <td class="text-center">
                        <input type="checkbox" name="selected_tenants[]" value="<?= $row['tenant_id'] ?>" <?= $row['is_willing_to_continue'] ? 'checked' : '' ?>>
                    </td>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td>
                        <?php if ($row['is_willing_to_continue']): ?>
                            <span class="badge badge-confirmed text-white">Confirmed</span>
                        <?php else: ?>
                            <span class="badge badge-pending text-white">Not Yet Confirmed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <br>
          <div class="d-flex justify-content-end mt-3">
              <button type="submit" name="migrate" class="btn btn-success"
                  onclick="return confirm('Are you sure you want to migrate these tenants?');">
                  <i class="bi bi-box-arrow-up-right"></i> Migrate Selected Tenants
              </button>
          </div>

        <?php else: ?>
            <div class="alert alert-info">No tenants found for this academic year.</div>
        <?php endif; ?>
    </form>
    </div>
    
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        $('#tenantTable').DataTable({
            pageLength: 5,
            columnDefs: [{ orderable: false, targets: 0 }]
        });
    });
</script>

</body>
</html>
