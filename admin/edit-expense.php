<?php
session_start();

// Add cache control headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Check if expense ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid expense ID.";
    header("Location: expenses.php?action=edit");
    exit;
}

$monthly_expense_id = $_GET['id'];

// Fetch the expense record
$query = "SELECT me.monthly_expense_id, me.academic_year_id, me.month, me.expense_id, me.amount,
                 a.start_year, a.end_year, a.semester
          FROM monthly_expenses me
          JOIN academic_years a ON me.academic_year_id = a.academic_year_id
          WHERE me.monthly_expense_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $monthly_expense_id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
$stmt->close();

if (!$expense) {
    $_SESSION['error_message'] = "Expense not found.";
    header("Location: expenses.php?action=edit");
    exit;
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
} else {
    error_log("No academic years found in database: " . $conn->error);
    $_SESSION['error_message'] = "No academic years available.";
    header("Location: expenses.php?action=edit");
    exit;
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

// Get month options based on semester
function getMonthOptions($year, $semester) {
    $months = [];
    $monthRanges = [
        'First' => [8, 9, 10, 11, 12], // August to December
        'Second' => [1, 2, 3, 4, 5],   // January to May
        'Summer' => [6, 7]             // June to July
    ];
    
    $allowedMonths = $monthRanges[$semester] ?? range(1, 12);
    
    foreach ($allowedMonths as $month) {
        $date = sprintf('%04d-%02d-01', $year, $month);
        $months[$date] = date('F Y', strtotime($date));
    }
    return $months;
}

// Get initial months for the expense's academic year
$monthOptions = getMonthOptions(
    $expense['start_year'],
    $expense['semester']
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expense'])) {
    $academic_year_id = $_POST['academic_year_id'] ?? '';
    $month = $_POST['month'] ?? '';
    $expense_id = $_POST['expense_id'] ?? '';
    $amount = $_POST['amount'] ?? '';

    // Validate inputs
    $errors = [];
    if (empty($academic_year_id) || !is_numeric($academic_year_id)) {
        $errors[] = "Valid academic year is required.";
    }
    if (empty($month)) {
        $errors[] = "Valid month is required.";
    }
    if (empty($expense_id) || !is_numeric($expense_id)) {
        $errors[] = "Valid expense type is required.";
    }
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Amount must be a positive number.";
    }

    // Validate month against selected academic year's semester
    if (empty($errors)) {
        $ayQuery = "SELECT start_year, semester FROM academic_years WHERE academic_year_id = ?";
        $ayStmt = $conn->prepare($ayQuery);
        $ayStmt->bind_param("i", $academic_year_id);
        $ayStmt->execute();
        $ayResult = $ayStmt->get_result();
        $selectedYear = $ayResult->fetch_assoc();
        $ayStmt->close();

        if ($selectedYear) {
            $validMonths = getMonthOptions($selectedYear['start_year'], $selectedYear['semester']);
            if (!array_key_exists($month, $validMonths)) {
                $errors[] = "Selected month is not valid for the chosen academic year's semester.";
            }
        } else {
            $errors[] = "Invalid academic year selected.";
        }
    }

    if (empty($errors)) {
        // Update expense
        $updateQuery = "UPDATE monthly_expenses 
                        SET academic_year_id = ?, month = ?, expense_id = ?, amount = ?
                        WHERE monthly_expense_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("issdi", $academic_year_id, $month, $expense_id, $amount, $monthly_expense_id);
        
        if ($updateStmt->execute()) {
            $_SESSION['success_message'] = "Expense updated successfully!";
            header("Location: expenses.php?action=edit");
            exit;
        } else {
            $_SESSION['error_message'] = "Error updating expense: " . $conn->error;
            header("Location: expenses.php?action=edit");
            exit;
        }
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
        // Regenerate monthOptions for the selected academic year to display in the form
        if ($selectedYear) {
            $monthOptions = getMonthOptions($selectedYear['start_year'], $selectedYear['semester']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        h2 {
            color: #095544;
        }
        .card-header {
            background-color: #095544;
        }
        .card {
            border-radius: 10px;
            background-color: #e5edf0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
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
             <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-pencil"></i> <strong>Edit Expense</strong></h2>
            </div>
        </div>

        <!-- Success/error messages -->
        <div class="alert-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        </div>

        <!-- Edit Expense Form -->
        <div class="card mb-4">
            <div class="card-header text-white">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Update Expense Details</h5>
            </div>
            <div class="card-body">
                <form action="edit-expense.php?id=<?= $monthly_expense_id ?>" method="post" class="row g-3">
                    <div class="col-md-4">
                        <label for="academic_year_id" class="form-label">Academic Year</label>
                        <select id="academic_year_id" name="academic_year_id" class="form-select" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($academicYears as $year): ?>
                                <?php 
                                $yearLabel = $year['start_year'] . '-' . $year['end_year'] . ' (' . $year['semester'] . ')' . ($year['is_current'] ? ' (Current)' : '');
                                ?>
                                <option value="<?= $year['academic_year_id'] ?>" 
                                        <?= $expense['academic_year_id'] == $year['academic_year_id'] ? 'selected' : '' ?>>
                                    <?= $yearLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="month" class="form-label">Month</label>
                        <select id="month" name="month" class="form-select" required>
                            <option value="">Select Month</option>
                            <?php foreach ($monthOptions as $date => $display): ?>
                                <option value="<?= $date ?>" 
                                        <?= $expense['month'] == $date ? 'selected' : '' ?>>
                                    <?= $display ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="expense_id" class="form-label">Expense Type</label>
                        <select id="expense_id" name="expense_id" class="form-select" required>
                            <option value="">Select Expense Type</option>
                            <?php foreach ($expenseTypes as $expType): ?>
                                <option value="<?= $expType['expense_id'] ?>" 
                                        <?= $expense['expense_id'] == $expType['expense_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($expType['description']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" id="amount" name="amount" 
                                   class="form-control" value="<?= number_format($expense['amount'], 2) ?>" required>
                        </div>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" name="update_expense" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Update Expense
                        </button>
                        <a href="expenses.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
       
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('academic_year_id').addEventListener('change', function() {
    const academicYearId = this.value;
    const monthSelect = document.getElementById('month');
    
    // Clear existing options
    monthSelect.innerHTML = '<option value="">Select Month</option>';

    if (academicYearId) {
        // Fetch months for the selected academic year
        fetch('get-months.php?academic_year_id=' + academicYearId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }

                // Add new month options
                data.months.forEach(month => {
                    const option = document.createElement('option');
                    option.value = month.date;
                    option.textContent = month.display;
                    // Pre-select if matches the current expense month
                    if (month.date === '<?= $expense['month'] ?>') {
                        option.selected = true;
                    }
                    monthSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Fetch error:', error));
    }
});
</script>
</body>
</html>

<?php 
$conn->close();
?>