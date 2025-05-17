<?php
session_start();

// Add cache control headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Define semesters as enum values
$semesters = ['First', 'Second', 'Summer'];

// Handle form submission to add new academic year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_academic_year'])) {
    $start_year = $_POST['start_year'];
    $end_year = $_POST['end_year'];
    $semester = $_POST['semester'];
    $is_current = isset($_POST['is_current']) ? 1 : 0;

    // Validate input
    if (!is_numeric($start_year) || !is_numeric($end_year) || $end_year <= $start_year) {
        $_SESSION['error_message'] = "Invalid years: End year must be greater than start year";
        header("Location: set-academic-year.php");
        exit;
    }
    if (empty($semester) || !in_array($semester, $semesters)) {
        $_SESSION['error_message'] = "Invalid semester selected";
        header("Location: set-academic-year.php");
        exit;
    }

    // Check if academic year with semester already exists
    $checkQuery = "SELECT COUNT(*) FROM academic_years WHERE start_year = ? AND end_year = ? AND semester = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("iis", $start_year, $end_year, $semester);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        $_SESSION['error_message'] = "Academic year with this semester already exists";
        header("Location: set-academic-year.php");
        exit;
    }

    // If setting as current, clear existing current year
    if ($is_current) {
        $clearQuery = "UPDATE academic_years SET is_current = FALSE WHERE is_current = TRUE";
        $conn->query($clearQuery);
    }

    // Insert new academic year
    $insertQuery = "INSERT INTO academic_years (start_year, end_year, semester, is_current) VALUES (?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("iisi", $start_year, $end_year, $semester, $is_current);
    if ($insertStmt->execute()) {
        $_SESSION['success_message'] = "Academic year added successfully!";
        header("Location: set-academic-year.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Error adding academic year: " . $conn->error;
        header("Location: set-academic-year.php");
        exit;
    }
    $insertStmt->close();
}

// Handle setting an academic year as current
if (isset($_GET['set_current']) && is_numeric($_GET['set_current'])) {
    $academic_year_id = $_GET['set_current'];

    // Clear existing current year
    $clearQuery = "UPDATE academic_years SET is_current = FALSE WHERE is_current = TRUE";
    $conn->query($clearQuery);

    // Set new current year
    $updateQuery = "UPDATE academic_years SET is_current = TRUE WHERE academic_year_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $academic_year_id);
    if ($updateStmt->execute()) {
        $_SESSION['success_message'] = "Academic year set as current successfully!";
        header("Location: set-academic-year.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Error setting current academic year: " . $conn->error;
        header("Location: set-academic-year.php");
        exit;
    }
    $updateStmt->close();
}

// Fetch all academic years
$academicYears = [];
$ayQuery = "SELECT academic_year_id, start_year, end_year, semester, is_current
            FROM academic_years
            ORDER BY start_year DESC, end_year DESC";
$ayResult = $conn->query($ayQuery);
if ($ayResult && $ayResult->num_rows > 0) {
    while ($row = $ayResult->fetch_assoc()) {
        $academicYears[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Academic Year</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        h2 {
            color: #095544;
        }
        .card {
            border-radius: 10px;
            background-color: #e5edf0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .d-flex {
            margin: none;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .d-flex {
            
            padding: 20px;
        }
        .alert-container {
            min-height: 60px;
        }
    </style>
</head>
<body>

<div class="d-flex">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
        <div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="bi bi-calendar"></i> <strong>Set Academic Year</strong></h2>
        </div>
    </div>
    
    <!-- Add New Academic Year Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Academic Year</h5>
        </div>
        <div class="card-body">
            <!-- Success/error messages -->
            <div class="alert-container">
                <?php if (isset($_SESSION['success_message']) && strpos($_SESSION['success_message'], 'added') !== false): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message']) && (strpos($_SESSION['error_message'], 'Invalid') !== false || strpos($_SESSION['error_message'], 'already exists') !== false)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>
            
            <form action="set-academic-year.php" method="post" class="row g-3">
                <div class="col-md-3">
                    <label for="start_year" class="form-label">Start Year</label>
                    <input type="number" id="start_year" name="start_year" class="form-control" min="2000" max="2100" required>
                </div>
                
                <div class="col-md-3">
                    <label for="end_year" class="form-label">End Year</label>
                    <input type="number" id="end_year" name="end_year" class="form-control" min="2000" max="2100" required>
                </div>
                
                <div class="col-md-3">
                    <label for="semester" class="form-label">Semester</label>
                    <select id="semester" name="semester" class="form-select" required>
                        <option value="">Select Semester</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $sem ?>"><?= htmlspecialchars($sem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="is_current" class="form-label">Set as Current</label>
                    <div class="form-check">
                        <input type="checkbox" id="is_current" name="is_current" class="form-check-input">
                        <label for="is_current" class="form-check-label">Current Academic Year</label>
                    </div>
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" name="add_academic_year" class="btn btn-success btn-sm" style="width: 80px;">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Academic Years Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-table"></i> Academic Years</h5>
        </div>
        <div class="card-body">
            <!-- Success/error messages -->
            <div class="alert-container">
                <?php if (isset($_SESSION['success_message']) && strpos($_SESSION['success_message'], 'set as current') !== false): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message']) && strpos($_SESSION['error_message'], 'setting current') !== false): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($academicYears as $year): ?>
                        <tr>
                            <td><?= $year['start_year'] ?>-<?= $year['end_year'] ?></td>
                            <td><?= htmlspecialchars($year['semester']) ?></td>
                            <td>
                                <?php if ($year['is_current']): ?>
                                    <span class="badge bg-success">Current</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Current</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$year['is_current']): ?>
                                    <form action="set-academic-year.php" method="get" onsubmit="return confirmSetCurrent('<?= $year['start_year'] ?>-<?= $year['end_year'] ?>', '<?= htmlspecialchars($year['semester']) ?>')">
                                        <input type="hidden" name="set_current" value="<?= $year['academic_year_id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" title="Set as Current">
                                            <i class="bi bi-check-circle"></i> Set Current
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($academicYears)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-calendar display-6"></i>
                                <div class="mt-2">No academic years found</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmSetCurrent(year, semester) {
    return confirm('Are you sure you want to set ' + year + ' (' + semester + ') as the current academic year?\nThis will unset any other current academic year.');
}
</script>
</body>
</html>

<?php 
$conn->close();
?>