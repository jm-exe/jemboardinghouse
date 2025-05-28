
<?php
session_start();
require_once '../connection/db.php';

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get current academic year
$currentAcademicYearStmt = $conn->query("
    SELECT academic_year_id, start_year, end_year, semester
    FROM academic_years
    WHERE is_current = 1
    LIMIT 1
");
$current_academic_year = $currentAcademicYearStmt->fetch_assoc();
if (!$current_academic_year) {
    $_SESSION['error'] = "No current academic year set. Please configure in academic years.";
    header("Location: manage_guest_stays.php");
    exit();
}
$current_academic_year_id = $current_academic_year['academic_year_id'];

// Handle add, edit, delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $stay_date = $_POST['stay_date'] ?? '';
                $guest_count = (int)($_POST['guest_count'] ?? 0);
                $charge = (float)($_POST['charge'] ?? 250.00);

                if (!$tenant_id || !$stay_date || $guest_count < 1 || $charge < 0.01) {
                    throw new Exception("All fields are required, and guest count/charge must be positive.");
                }

                $stmt = $conn->prepare("
                    INSERT INTO guest_stays (tenant_id, stay_date, guest_count, charge, academic_year_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isidi", $tenant_id, $stay_date, $guest_count, $charge, $current_academic_year_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Guest stay added successfully.";
            } elseif ($_POST['action'] === 'edit') {
                $guest_stay_id = (int)($_POST['guest_stay_id'] ?? 0);
                $stay_date = $_POST['stay_date'] ?? '';
                $guest_count = (int)($_POST['guest_count'] ?? 0);
                $charge = (float)($_POST['charge'] ?? 0.00);

                if (!$guest_stay_id || !$stay_date || $guest_count < 1 || $charge < 0.01) {
                    throw new Exception("All fields are required, and guest count/charge must be positive.");
                }

                $stmt = $conn->prepare("
                    UPDATE guest_stays
                    SET stay_date = ?, guest_count = ?, charge = ?, academic_year_id = ?
                    WHERE guest_stay_id = ?
                ");
                $stmt->bind_param("sidii", $stay_date, $guest_count, $charge, $current_academic_year_id, $guest_stay_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Guest stay updated successfully.";
            } elseif ($_POST['action'] === 'delete') {
                $guest_stay_id = (int)($_POST['guest_stay_id'] ?? 0);

                if (!$guest_stay_id) {
                    throw new Exception("Guest stay ID is required.");
                }

                $stmt = $conn->prepare("DELETE FROM guest_stays WHERE guest_stay_id = ?");
                $stmt->bind_param("i", $guest_stay_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Guest stay deleted successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header("Location: manage_guest_stays.php");
        exit();
    }
}

// Initialize filter variables
$tenant_filter = isset($_POST['tenant_filter']) ? trim($_POST['tenant_filter']) : '';
$month_filter = isset($_POST['month_filter']) ? $_POST['month_filter'] : '';

// Build the SQL query with filters
$sql = "
    SELECT gs.guest_stay_id, gs.tenant_id, gs.stay_date, gs.guest_count, gs.charge, gs.academic_year_id,
           t.first_name, t.last_name,
           ay.start_year, ay.end_year, ay.semester
    FROM guest_stays gs
    JOIN tenants t ON gs.tenant_id = t.tenant_id
    JOIN academic_years ay ON gs.academic_year_id = ay.academic_year_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($tenant_filter) {
    $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ?)";
    $params[] = "%$tenant_filter%";
    $params[] = "%$tenant_filter%";
    $types .= 'ss';
}

if ($month_filter) {
    $sql .= " AND DATE_FORMAT(gs.stay_date, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= 's';
}

$sql .= " ORDER BY gs.stay_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$guest_stays = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch tenants for the add/edit forms
$tenantsStmt = $conn->query("
    SELECT t.tenant_id, t.first_name, t.last_name, b.boarding_id
    FROM tenants t
    JOIN boarding b ON t.tenant_id = b.tenant_id
    WHERE b.due_date >= CURDATE()
");
$tenants = $tenantsStmt->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Guest Stays</title>
    <link rel="stylesheet" href="CSS/sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <h2><strong>Manage Guest Stays</strong></h2>
            
            <!-- Filter Form -->
            <form method="POST" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="tenant_filter" class="form-label">Search by Tenant</label>
                        <input type="text" class="form-control" id="tenant_filter" name="tenant_filter" 
                               value="<?php echo htmlspecialchars($tenant_filter); ?>" placeholder="Enter tenant name">
                    </div>
                    <div class="col-md-3">
                        <label for="month_filter" class="form-label">Stay Month</label>
                        <input type="month" class="form-control" id="month_filter" name="month_filter" 
                               value="<?php echo htmlspecialchars($month_filter); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ms-2">Clear</a>
                    </div>
                </div>
            </form>

            <!-- Add New Guest Stay Form -->
            <div class="mb-4">
                <h4>Add New Guest Stay</h4>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="academic_year_id" value="<?php echo $current_academic_year_id; ?>">
                    <div class="col-md-3">
                        <label for="tenant_id" class="form-label">Tenant</label>
                        <select name="tenant_id" id="tenant_id" class="form-select" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo htmlspecialchars($tenant['tenant_id']); ?>">
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="stay_date" class="form-label">Stay Date</label>
                        <input type="date" name="stay_date" id="stay_date" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label for="guest_count" class="form-label">Guest Count</label>
                        <input type="number" name="guest_count" id="guest_count" class="form-control" min="1" step="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label for="charge" class="form-label">Charge (₱)</label>
                        <input type="number" name="charge" id="charge" class="form-control" min="0.01" step="0.01" value="250.00" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success">Add</button>
                    </div>
                </form>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Stay Date</th>
                        <th>Guest Count</th>
                        <th>Charge</th>
                        <th>Academic Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guest_stays)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No guest stays found matching the criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($guest_stays as $guest): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($guest['stay_date'])); ?></td>
                                <td><?php echo (int)$guest['guest_count']; ?></td>
                                <td>₱<?php echo number_format($guest['charge'], 2); ?></td>
                                <td><?php echo htmlspecialchars($guest['start_year'] . '-' . $guest['end_year'] . ' ' . $guest['semester']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $guest['guest_stay_id']; ?>">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this guest stay?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="guest_stay_id" value="<?php echo $guest['guest_stay_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $guest['guest_stay_id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $guest['guest_stay_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel<?php echo $guest['guest_stay_id']; ?>">Edit Guest Stay</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="guest_stay_id" value="<?php echo $guest['guest_stay_id']; ?>">
                                                <input type="hidden" name="academic_year_id" value="<?php echo $current_academic_year_id; ?>">
                                                <div class="mb-3">
                                                    <label for="edit_tenant_id<?php echo $guest['guest_stay_id']; ?>" class="form-label">Tenant</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?>" disabled>
                                                    <input type="hidden" name="tenant_id" value="<?php echo $guest['tenant_id']; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="edit_stay_date<?php echo $guest['guest_stay_id']; ?>" class="form-label">Stay Date</label>
                                                    <input type="date" name="stay_date" id="edit_stay_date<?php echo $guest['guest_stay_id']; ?>" class="form-control" value="<?php echo $guest['stay_date']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="edit_guest_count<?php echo $guest['guest_stay_id']; ?>" class="form-label">Guest Count</label>
                                                    <input type="number" name="guest_count" id="edit_guest_count<?php echo $guest['guest_stay_id']; ?>" class="form-control" value="<?php echo (int)$guest['guest_count']; ?>" min="1" step="1" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="edit_charge<?php echo $guest['guest_stay_id']; ?>" class="form-label">Charge (₱)</label>
                                                    <input type="number" name="charge" id="edit_charge<?php echo $guest['guest_stay_id']; ?>" class="form-control" value="<?php echo $guest['charge']; ?>" min="0.01" step="0.01" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
