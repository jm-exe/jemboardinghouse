<?php
session_start();

// Add cache control headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Include database connection
include '../connection/db.php';

// Initialize variables
$academicYearId = '';
$month = '';
$expenseId = '';
$amount = '';
$error = '';

// Fetch academic years
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

// Fetch expense types
$expenseTypes = [];
$expTypeQuery = "SELECT expense_id, description FROM expenses ORDER BY description";
$expTypeResult = $conn->query($expTypeQuery);
if ($expTypeResult && $expTypeResult->num_rows > 0) {
    while ($row = $expTypeResult->fetch_assoc()) {
        $expenseTypes[] = $row;
    }
}

// Get current academic year for default year
$currentYear = null;
$currentSemester = null;
foreach ($academicYears as $year) {
    if ($year['is_current']) {
        $currentYear = $year['start_year'];
        $currentSemester = $year['semester'];
        break;
    }
}
if (!$currentYear && !empty($academicYears)) {
    $currentYear = $academicYears[0]['start_year'];
    $currentSemester = $academicYears[0]['semester'];
}

// Generate month options based on semester
function getMonthOptions($year, $semester) {
    $months = [];
    $monthRanges = [
        'First' => [8, 9, 10, 11, 12], // August to December
        'Second' => [1, 2, 3, 4, 5],   // January to May
        'Summer' => [6, 7]             // June to July
    ];
    
    $allowedMonths = $monthRanges[$semester] ?? range(1, 12); // Fallback to all months if semester invalid
    
    foreach ($allowedMonths as $month) {
        $date = sprintf('%04d-%02d-01', $year, $month);
        $months[$date] = date('F Y', strtotime($date));
    }
    return $months;
}

// Default month options
$monthOptions = getMonthOptions($currentYear, $currentSemester ?? 'First');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $academicYearId = $_POST['academic_year_id'] ?? '';
    $month = $_POST['month'] ?? '';
    $expenseId = $_POST['expense_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    
    // Validate inputs
    $errors = [];
    if (empty($academicYearId)) $errors[] = "Academic year is required";
    if (empty($month)) $errors[] = "Month is required";
    if (empty($expenseId)) $errors[] = "Expense type is required";
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) $errors[] = "Valid amount is required";
    
    if (empty($errors)) {
        // Fetch semester for the selected academic year
        $semesterQuery = "SELECT semester FROM academic_years WHERE academic_year_id = ?";
        $semesterStmt = $conn->prepare($semesterQuery);
        $semesterStmt->bind_param("i", $academicYearId);
        $semesterStmt->execute();
        $semesterStmt->bind_result($semester);
        $semesterStmt->fetch();
        $semesterStmt->close();

        if (empty($semester)) {
            $errors[] = "Invalid academic year selected";
        } else {
            // Validate month against semester
            $monthNumber = (int) date('m', strtotime($month));
            $monthRanges = [
                'First' => [8, 9, 10, 11, 12],
                'Second' => [1, 2, 3, 4, 5],
                'Summer' => [6, 7]
            ];
            if (!in_array($monthNumber, $monthRanges[$semester] ?? [])) {
                $errors[] = "Selected month is not valid for the $semester semester";
            }
        }

        if (empty($errors)) {
            // Check if expense already exists for this month/expense/academic year
            $checkQuery = "SELECT monthly_expense_id FROM monthly_expenses 
                          WHERE academic_year_id = ? AND MONTH(month) = MONTH(?) AND YEAR(month) = YEAR(?) AND expense_id = ?";
            
            if ($checkStmt = $conn->prepare($checkQuery)) {
                $checkStmt->bind_param("issi", $academicYearId, $month, $month, $expenseId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Update existing expense
                    $existingExpense = $checkResult->fetch_assoc();
                    $updateQuery = "UPDATE monthly_expenses SET amount = ? WHERE monthly_expense_id = ?";
                    
                    if ($updateStmt = $conn->prepare($updateQuery)) {
                        $updateStmt->bind_param("di", $amount, $existingExpense['monthly_expense_id']);
                        if ($updateStmt->execute()) {
                            $_SESSION['success_message'] = "Expense updated successfully!";
                            header("Location: expenses.php");
                            exit;
                        } else {
                            $error = "Error updating expense: " . $conn->error;
                        }
                        $updateStmt->close();
                    }
                } else {
                    // Insert new expense
                    $insertQuery = "INSERT INTO monthly_expenses (academic_year_id, month, expense_id, amount) 
                                   VALUES (?, ?, ?, ?)";
                    
                    if ($insertStmt = $conn->prepare($insertQuery)) {
                        $insertStmt->bind_param("isid", $academicYearId, $month, $expenseId, $amount);
                        if ($insertStmt->execute()) {
                            $_SESSION['success_message'] = "Expense added successfully!";
                            header("Location: expenses.php");
                            exit;
                        } else {
                            $error = "Error adding expense: " . $conn->error;
                        }
                        $insertStmt->close();
                    }
                }
                $checkStmt->close();
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            $error = implode("<br>", $errors);
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
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
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
    
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-plus-circle"></i> <strong>Add New Expense</strong></h2>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add Expense</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label for="academic_year_id" class="form-label">Academic Year</label>
                        <select id="academic_year_id" name="academic_year_id" class="form-select" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['academic_year_id'] ?>" 
                                    <?= ($year['is_current']) ? 'selected' : '' ?>>
                                    <?= $year['start_year'] ?>-<?= $year['end_year'] ?> (<?= $year['semester'] ?>) <?= $year['is_current'] ? '(Current)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="month" class="form-label">Month</label>
                        <select id="month" name="month" class="form-select" required>
                            <option value="">Select Month</option>
                            <?php foreach ($monthOptions as $date => $display): ?>
                                <option value="<?= $date ?>"><?= $display ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="expense_id" class="form-label">Expense Type</label>
                        <select id="expense_id" name="expense_id" class="form-select" required>
                            <option value="">Select Expense Type</option>
                            <?php foreach ($expenseTypes as $expType): ?>
                                <option value="<?= $expType['expense_id'] ?>"><?= htmlspecialchars($expType['description']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" id="amount" name="amount" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-start">
                        <button type="submit" name="add_expense" class="btn btn-success">
                            <i class="bi bi-plus-lg"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <a href="expenses.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Expenses Tracker</a>
    </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>