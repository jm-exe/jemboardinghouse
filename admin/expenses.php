<?php
session_start();

// Add cache control headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Handle new expense type submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense_type'])) {
    $description = trim($_POST['description'] ?? '');
    
    if (empty($description)) {
        $_SESSION['error_message'] = "Expense type description is required.";
        header("Location: expenses.php");
        exit;
    }

    include '../connection/db.php';
    
    // Check for duplicate description
    $checkQuery = "SELECT COUNT(*) FROM expenses WHERE description = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $description);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();
    
    if ($count > 0) {
        $_SESSION['error_message'] = "Expense type '$description' already exists.";
        header("Location: expenses.php");
        exit;
    }
    
    // Insert new expense type
    $insertQuery = "INSERT INTO expenses (description) VALUES (?)";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("s", $description);
    if ($insertStmt->execute()) {
        $_SESSION['success_message'] = "Expense type '$description' added successfully!";
        header("Location: expenses.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Error adding expense type: " . $conn->error;
        header("Location: expenses.php");
        exit;
    }
}

include '../connection/db.php';

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
}

// Determine current academic year based on is_current flag
$currentYearId = null;
$currentAcademicYear = null;
foreach ($academicYears as $year) {
    if ($year['is_current']) {
        $currentYearId = $year['academic_year_id'];
        $currentAcademicYear = $year;
        break;
    }
}
if ($currentYearId === null && !empty($academicYears)) {
    $currentYearId = $academicYears[0]['academic_year_id'];
    $currentAcademicYear = $academicYears[0];
}

// Initialize variables
$selectedYear = $_GET['academic_year_id'] ?? '';
$selectedExpense = $_GET['expense_id'] ?? '';

// Default to current academic year if not set
if (empty($selectedYear) && $currentYearId !== null) {
    $selectedYear = $currentYearId;
}

// Get month options based on semester
function getMonthOptions($year, $semester) {
    $months = [];
    $monthRanges = [
        'First' => [8, 9, 10, 11, 12], // August to December
        'Second' => [1, 2, 3, 4, 5],   // January to May (NEXT YEAR)
        'Summer' => [6, 7]             // June to July (NEXT YEAR)
    ];

    $allowedMonths = $monthRanges[$semester] ?? range(1, 12); // Fallback to all months if semester invalid

    foreach ($allowedMonths as $month) {
        if ($semester === 'Second' && $month >= 1 && $month <= 5) {
            $date = sprintf('%04d-%02d-01', $year + 1, $month); // Next year for 2nd Sem Jan–May
        } elseif ($semester === 'Summer' && $month >= 6 && $month <= 7) {
            $date = sprintf('%04d-%02d-01', $year + 1, $month); // Next year for Summer
        } else {
            $date = sprintf('%04d-%02d-01', $year, $month);
        }
        $months[$date] = date('F Y', strtotime($date));
    }
    return $months;
}


// Get months for the current year based on semester
$monthOptions = getMonthOptions(
    $currentAcademicYear ? $currentAcademicYear['start_year'] : date('Y'),
    $currentAcademicYear ? $currentAcademicYear['semester'] : 'First'
);

// Fetch expense types
$expenseTypes = [];
$expTypeQuery = "SELECT expense_id, description FROM expenses ORDER BY description";
$expTypeResult = $conn->query($expTypeQuery);
if ($expTypeResult && $expTypeResult->num_rows > 0) {
    while ($row = $expTypeResult->fetch_assoc()) {
        $expenseTypes[] = $row;
    }
}

// Build query
$query = "SELECT me.monthly_expense_id, me.month, me.amount, 
          e.expense_id, e.description AS expense_description,
          a.academic_year_id, a.start_year, a.end_year, a.semester
          FROM monthly_expenses me
          JOIN expenses e ON me.expense_id = e.expense_id
          JOIN academic_years a ON me.academic_year_id = a.academic_year_id
          WHERE 1=1";

$types = '';
$params = [];

if (!empty($selectedYear)) {
    $query .= " AND me.academic_year_id = ?";
    $types .= 'i';
    $params[] = $selectedYear;
}

if (!empty($selectedExpense)) {
    $query .= " AND me.expense_id = ?";
    $types .= 'i';
    $params[] = $selectedExpense;
}

$query .= " ORDER BY me.month DESC, e.description";

// Execute query
if ($stmt = $conn->prepare($query)) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $expenses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Calculate totals by expense type
$expenseTotals = [];
foreach ($expenses as $expense) {
    $expenseId = $expense['expense_id'];
    if (!isset($expenseTotals[$expenseId])) {
        $expenseTotals[$expenseId] = [
            'description' => $expense['expense_description'],
            'total' => 0
        ];
    }
    $expenseTotals[$expenseId]['total'] += $expense['amount'];
}

// Calculate grand total
$grandTotal = array_sum(array_column($expenseTotals, 'total'));

// Prepare data for Line Chart
$expenseByMonth = [];
$monthKeys = array_keys($monthOptions); // e.g., ['2024-01-01', '2024-02-01', ...]
$monthLabels = array_values($monthOptions); // e.g., ['January 2024', 'February 2024', ...]

foreach ($expenses as $expense) {
    $monthKey = date('Y-m-01', strtotime($expense['month'])); // Ensure format matches monthKeys
    $amount = $expense['amount'];
    $expenseId = $expense['expense_id'];
    $expenseDesc = $expense['expense_description'];

    if (!isset($expenseByMonth[$expenseId])) {
        $expenseByMonth[$expenseId] = [
            'description' => $expenseDesc,
            'data' => array_fill_keys($monthKeys, 0) // Initialize with zeros for allowed months
        ];
    }
    if (in_array($monthKey, $monthKeys)) {
        $expenseByMonth[$expenseId]['data'][$monthKey] = ($expenseByMonth[$expenseId]['data'][$monthKey] ?? 0) + $amount;
    }
}

$lineChartDatasets = [];
$colors = [
    'rgba(255, 99, 132, 0.5)',  // Red
    'rgba(54, 162, 235, 0.5)', // Blue
    'rgba(255, 206, 86, 0.5)', // Yellow
    'rgba(75, 192, 192, 0.5)', // Green
    'rgba(153, 102, 255, 0.5)' // Purple
];
$colorIndex = 0;

foreach ($expenseByMonth as $expenseId => $expenseData) {
    $data = array_values($expenseData['data']); // Align with monthKeys order
    $lineChartDatasets[] = [
        'label' => $expenseData['description'],
        'data' => $data,
        'borderColor' => $colors[$colorIndex % count($colors)],
        'backgroundColor' => str_replace('0.5', '0.2', $colors[$colorIndex % count($colors)]),
        'fill' => true
    ];
    $colorIndex++;
}

// Prepare data for Pie Chart
$pieChartLabels = array_column($expenseTotals, 'description');
$pieChartData = array_column($expenseTotals, 'total');
$pieChartColors = array_slice($colors, 0, count($pieChartLabels));

$monthLabels = json_encode($monthLabels);
$lineChartDatasets = json_encode($lineChartDatasets);
$pieChartLabels = json_encode($pieChartLabels);
$pieChartData = json_encode($pieChartData);
$pieChartColors = json_encode($pieChartColors);

// Handle Delete Expense
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expenseId = $_GET['delete'];
    $deleteQuery = "DELETE FROM monthly_expenses WHERE monthly_expense_id = ?";
    
    if ($deleteStmt = $conn->prepare($deleteQuery)) {
        $deleteStmt->bind_param("i", $expenseId);
        if ($deleteStmt->execute()) {
            header("Location: expenses.php?deleted=1");
            exit;
        } else {
            header("Location: expenses.php?error=" . urlencode("Error deleting expense: " . $conn->error));
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
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
    .total-box {
        font-weight: bold;
        background-color: #d1e7dd;
        border-radius: 6px;
        padding: 8px 12px;
    }
    .expense-badge {
        font-size: 0.9rem;
        padding: 5px 10px;
        border-radius: 4px;
        white-space: nowrap;
    }
    .electricity {
        background-color: #cfe2ff;
        color: #084298;
    }
    .water {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    .other {
        background-color: #f8d7da;
        color: #842029;
    }
    .expense-item {
        background-color: #f8f9fa;
        min-height: 50px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px;
        border-radius: 6px;
    }
    .total-box {
        background-color: #d1e7dd;
        border-radius: 6px;
        padding: 15px;
        font-weight: bold;
    }
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
    }
    .form-col {
        flex: 0 0 auto;
        padding-right: 10px;
        padding-left: 10px;
    }
    .form-col-academic-year {
        width: 30%;
    }
    .form-col-month {
        width: 25%;
    }
    .form-col-expense {
        width: 25%;
    }
    .form-col-amount {
        width: 15%;
    }
    .form-col-button {
        width: 5%;
    }
    .alert-container, .alert-container-records {
        min-height: 20px;
    }
    .alert-container-records .alert {
        margin-bottom: 0;
    }
    @media (max-width: 768px) {
        .expense-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        .expense-badge {
            margin-bottom: 5px;
        }
        .total-box {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        .form-col {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>
</head>
<body>

<div class="d-flex">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="bi bi-cash-coin"></i> <strong>Expenses Tracker</strong></h2>
        </div>
    </div>
    
    <!-- Success/error messages for non-edit/delete actions -->
    <div class="alert-container">
        <?php if (isset($_SESSION['success_message']) && (!isset($_GET['action']) || $_GET['action'] !== 'edit')): ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message']) && (!isset($_GET['action']) || $_GET['action'] !== 'edit')): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </div>
    
    <!-- Add New Expense Card -->
    <div class="card mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Expense</h5>
        </div>
        <div class="card-body">
            <form action="add-expenses.php" method="post" class="row g-3">
                <div class="col-md-4">
                    <label for="academic_year_display" class="form-label">Current Academic Year</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                        <input type="text" id="academic_year_display" class="form-control" 
                               value="<?php echo $currentAcademicYear ? $currentAcademicYear['start_year'] . '-' . $currentAcademicYear['end_year'] . ' (' . $currentAcademicYear['semester'] . ')' : 'Not set'; ?>" 
                               readonly>
                        <input type="hidden" name="academic_year_id" value="<?php echo $currentYearId; ?>">
                    </div>
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
                
                <div class="col-12 text-end">
                    <button type="submit" name="add_expense" class="btn btn-success">
                        <i class="bi bi-plus-lg"></i> Add Expense
                    </button>
                </div>
            </form>
    </div>
    
    <!-- Add New Expense Type Card -->
    <div class="card mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Expense Type</h5>
        </div>
        <div class="card-body">
            <form action="expenses.php" method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="description" class="form-label">Expense Type Description</label>
                    <input type="text" id="description" name="description" class="form-control" placeholder="e.g., Internet Bill" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="add_expense_type" class="btn btn-success w-100">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Filter Expenses Section -->
    <div class="card mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0"><i class="bi bi-filter"></i> Filter Expenses</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-12">
                    <form method="get" class="row g-2">
                        <div class="col-md-5">
                            <label for="academic_year_id" class="form-label">Academic Year</label>
                            <select name="academic_year_id" id="academic_year_id" class="form-select">
                                <option value="">All Academic Years</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <?php 
                                    $yearLabel = $year['start_year'] . '-' . $year['end_year'] . ' (' . $year['semester'] . ')' . ($year['is_current'] ? ' (Current)' : '');
                                    ?>
                                    <option value="<?= $year['academic_year_id'] ?>" 
                                        <?= ($selectedYear == $year['academic_year_id']) ? 'selected' : ($year['academic_year_id'] == $currentYearId && empty($selectedYear) ? 'selected' : '') ?>>
                                        <?= $yearLabel ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <label for="expense_id" class="form-label">Expense Type</label>
                            <select name="expense_id" id="expense_id" class="form-select">
                                <option value="">All Expense Types</option>
                                <?php foreach ($expenseTypes as $expType): ?>
                                    <option value="<?= $expType['expense_id'] ?>" <?= $selectedExpense == $expType['expense_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($expType['description']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Apply
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="expenses.php" class="btn btn-secondary w-100">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-calculator"></i> Expense Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($expenseTotals as $expenseId => $expenseData): ?>
                            <div class="col-md-3 mb-3">
                                <div class="d-flex align-items-center p-2 rounded expense-item">
                                    <span class="expense-badge me-2 <?= strtolower($expenseData['description']) == 'electricity bill' ? 'electricity' : (strtolower($expenseData['description']) == 'water bill' ? 'water' : 'other') ?>">
                                        <?= htmlspecialchars($expenseData['description']) ?>
                                    </span>
                                    <span class="fw-bold">₱<?= number_format($expenseData['total'], 2) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-12">
                            <div class="total-box d-flex justify-content-between align-items-center mt-3 p-3">
                                <span class="fs-5">Total Expenses</span>
                                <span class="fs-5 fw-bold">₱<?= number_format($grandTotal, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Expense Visualizations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6>Monthly Expenses Over Time</h6>
                            <canvas id="lineChart" style="max-height: 300px;"></canvas>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h6>Expense Distribution by Type</h6>
                            <canvas id="pieChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expenses Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-table"></i> Expense Records</h5>
        </div>
        <div class="card-body">
            <!-- Success/error messages for edit/delete actions -->
            <div class="alert-container-records mb-3">
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Expense deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
                            <th>Month</th>
                            <th>Expense Type</th>
                            <th>Amount</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td>
                                <span class="month-badge">
                                    <?= date('F Y', strtotime($expense['month'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="expense-badge <?= strtolower($expense['expense_description']) == 'electricity bill' ? 'electricity' : (strtolower($expense['expense_description']) == 'water bill' ? 'water' : 'other') ?>">
                                    <?= htmlspecialchars($expense['expense_description']) ?>
                                </span>
                            </td>
                            <td class="fw-bold">₱<?= number_format($expense['amount'], 2) ?></td>
                            <td>
                                <?= $expense['start_year'] ?>-<?= $expense['end_year'] ?>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($expense['semester']) ?></div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit-expense.php?id=<?= $expense['monthly_expense_id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?delete=<?= $expense['monthly_expense_id'] ?>" class="btn btn-outline-danger" title="Delete" 
                                       onclick="return confirmDelete('<?= date('F Y', strtotime($expense['month'])) ?>', '<?= htmlspecialchars($expense['expense_description']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-cash-coin display-6"></i>
                                <div class="mt-2">No expenses found</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(month, type) {
    return confirm('Are you sure you want to delete the ' + type + ' expense for ' + month + '?\nThis action cannot be undone!');
}

const monthLabels = <?php echo $monthLabels; ?>;
const lineChartDatasets = <?php echo $lineChartDatasets; ?>;

const lineChart = new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: monthLabels.length ? monthLabels : ['No Data'],
        datasets: lineChartDatasets.length ? lineChartDatasets : [{
            label: 'No Expenses',
            data: [0],
            borderColor: 'rgba(128, 128, 128, 0.5)',
            backgroundColor: 'rgba(128, 128, 128, 0.2)',
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Monthly Expenses by Type'
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Month'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Amount (₱)'
                },
                beginAtZero: true
            }
        }
    }
});

const pieChart = new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: {
        labels: <?php echo $pieChartLabels; ?>,
        datasets: [{
            data: <?php echo $pieChartData; ?>,
            backgroundColor: <?php echo $pieChartColors; ?>,
            borderColor: <?php echo $pieChartColors; ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Expense Distribution by Type'
            }
        }
    }
});
</script>
</body>
</html>

<?php 
$conn->close();
?>