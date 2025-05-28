<?php
session_start();
include '../connection/db.php';

// Function to determine semester due date based on academic year and semester
function getSemesterDueDate($semester, $year) {
    if ($semester === 'First') {
        return "$year-12-31"; // First Semester: August to December, ends on December 31
    } elseif ($semester === 'Second') {
        return "$year-05-31"; // Second Semester: January to May, ends on May 31
    } elseif ($semester === 'Summer') {
        return "$year-07-31"; // Summer: June to July, ends on July 31
    }
    return "$year-12-31"; // Fallback: End of year
}

// Function to determine semester start date based on academic year and semester
function getSemesterStartDate($semester, $year) {
    if ($semester === 'First') {
        return "$year-08-01"; // First Semester: Starts on August 1
    } elseif ($semester === 'Second') {
        return "$year-01-01"; // Second Semester: Starts on January 1
    } elseif ($semester === 'Summer') {
        return "$year-06-01"; // Summer: Starts on June 1
    }
    return "$year-08-01"; // Fallback: Start of August
}

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
$currentAYStmt->close();
if (!$currentAY) {
    die("No current academic year found. Please add one in system management.");
}
$currentAYID = $currentAY['academic_year_id'];

// Get next academic year
$nextAYStmt = $conn->prepare("SELECT * FROM academic_years WHERE academic_year_id > ? ORDER BY academic_year_id ASC LIMIT 1");
$nextAYStmt->bind_param("i", $currentAYID);
$nextAYStmt->execute();
$nextAYResult = $nextAYStmt->get_result();
$nextAY = $nextAYResult->fetch_assoc();
$nextAYStmt->close();
if (!$nextAY) {
    $_SESSION['error'] = "No next academic year found. Please add one in system management.";
    header("Location: system-management.php");
    exit();
}
$nextAYID = $nextAY['academic_year_id'];

// Fetch tenants
$tenantsQuery = "
    SELECT tenant_id, first_name, last_name, is_willing_to_continue
    FROM tenants
    WHERE academic_year_id = ?
    ORDER BY is_willing_to_continue DESC, last_name ASC
";
$tenantsStmt = $conn->prepare($tenantsQuery);
$tenantsStmt->bind_param("i", $currentAYID);
$tenantsStmt->execute();
$tenants = $tenantsStmt->get_result();
$tenantsStmt->close();
if (!$tenants) {
    die("Error fetching tenants: " . $conn->error);
}

// Handle migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate']) && isset($_POST['selected_tenants'])) {
    $conn->begin_transaction();
    try {
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
            $historyStmt->close();

            // Get current boarding details to carry over bed_id
            $boardingStmt = $conn->prepare("
                SELECT bed_id, start_date, due_date
                FROM boarding
                WHERE tenant_id = ? AND due_date >= CURDATE()
                ORDER BY boarding_id DESC LIMIT 1
            ");
            $boardingStmt->bind_param("i", $tenantID);
            $boardingStmt->execute();
            $boardingResult = $boardingStmt->get_result();
            $currentBoarding = $boardingResult->fetch_assoc();
            $boardingStmt->close();

            // Insert new boarding record for the next academic year
            if ($currentBoarding) {
                $bedId = $currentBoarding['bed_id'];
                $startDate = getSemesterStartDate($nextAY['semester'], $nextAY['start_year']); // 1st of the month
                $dueDate = getSemesterDueDate($nextAY['semester'], $nextAY['start_year']); // End of semester

                // Check if the bed is available or assigned to the same tenant
                $bedStmt = $conn->prepare("
                    SELECT b.status, bo.tenant_id
                    FROM beds b
                    LEFT JOIN boarding bo ON b.bed_id = bo.bed_id AND bo.due_date >= CURDATE()
                    WHERE b.bed_id = ?
                ");
                $bedStmt->bind_param("i", $bedId);
                $bedStmt->execute();
                $bedResult = $bedStmt->get_result()->fetch_assoc();
                $bedStmt->close();

                if ($bedResult['status'] === 'Vacant' || ($bedResult['tenant_id'] == $tenantID)) {
                    // Bed is vacant or assigned to the same tenant, proceed with assignment
                    $newBoardingStmt = $conn->prepare("
                        INSERT INTO boarding (tenant_id, bed_id, start_date, due_date)
                        VALUES (?, ?, ?, ?)
                    ");
                    $newBoardingStmt->bind_param("iiss", $tenantID, $bedId, $startDate, $dueDate);
                    $newBoardingStmt->execute();
                    $newBoardingStmt->close();

                    // Update bed status to Occupied
                    $updateBedStmt = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE bed_id = ?");
                    $updateBedStmt->bind_param("i", $bedId);
                    $updateBedStmt->execute();
                    $updateBedStmt->close();
                } else {
                    // Current bed is occupied by another tenant, find a vacant bed
                    $vacantBedStmt = $conn->prepare("
                        SELECT bed_id
                        FROM beds
                        WHERE status = 'Vacant'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM boarding
                            WHERE bed_id = beds.bed_id
                            AND due_date >= ?
                        )
                        LIMIT 1
                    ");
                    $vacantBedStmt->bind_param("s", $startDate);
                    $vacantBedStmt->execute();
                    $vacantBedResult = $vacantBedStmt->get_result()->fetch_assoc();
                    $vacantBedStmt->close();

                    if ($vacantBedResult) {
                        $bedId = $vacantBedResult['bed_id'];
                        $newBoardingStmt = $conn->prepare("
                            INSERT INTO boarding (tenant_id, bed_id, start_date, due_date)
                            VALUES (?, ?, ?, ?)
                        ");
                        $newBoardingStmt->bind_param("iiss", $tenantID, $bedId, $startDate, $dueDate);
                        $newBoardingStmt->execute();
                        $newBoardingStmt->close();

                        // Update bed status to Occupied
                        $updateBedStmt = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE bed_id = ?");
                        $updateBedStmt->bind_param("i", $bedId);
                        $updateBedStmt->execute();
                        $updateBedStmt->close();
                    } else {
                        throw new Exception("No vacant beds available for tenant ID $tenantID.");
                    }
                }

                // Update current tenant
                $updateStmt = $conn->prepare("UPDATE tenants SET academic_year_id = ?, is_willing_to_continue = 0 WHERE tenant_id = ?");
                $updateStmt->bind_param("ii", $nextAYID, $tenantID);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // Log tenant for manual bed assignment
                $historyStmt = $conn->prepare("
                    INSERT INTO tenant_history (tenant_id, academic_year_id, status, remarks)
                    VALUES (?, ?, 'Pending', 'No active boarding record or bed available')
                ");
                $historyStmt->bind_param("ii", $tenantID, $nextAYID);
                $historyStmt->execute();
                $historyStmt->close();

                // Update tenant without creating a boarding record
                $updateStmt = $conn->prepare("UPDATE tenants SET academic_year_id = ?, is_willing_to_continue = 0 WHERE tenant_id = ?");
                $updateStmt->bind_param("ii", $nextAYID, $tenantID);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        $conn->commit();
        $_SESSION['success_message'] = count($_POST['selected_tenants']) . " tenant(s) migrated to AY {$nextAY['start_year']}-{$nextAY['end_year']}.";
        header("Location: migrate.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Migration failed: " . $e->getMessage();
        header("Location: migrate.php");
        exit();
    }
}
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
        h3 {
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
        <h3 class="mb-0"><i class="bi bi-arrow-left-right"></i> Tenant Migration - AY <?= htmlspecialchars("{$currentAY['start_year']}-{$currentAY['end_year']} ({$semester} Semester)") ?></h3>
        <a href="system-management.php" class="btn btn-outline-primary">
            <i class="bi bi-gear"></i> System Settings
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
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
                                    <input type="checkbox" name="selected_tenants[]" value="<?= htmlspecialchars($row['tenant_id']) ?>" <?= $row['is_willing_to_continue'] ? 'checked' : '' ?>>
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