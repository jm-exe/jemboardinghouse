<?php
session_start();

// Add cache control headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Display messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            '.htmlspecialchars($_SESSION['success_message']).'
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success_message']);
}

if (isset($_GET['deleted'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            Tenant deleted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            '.htmlspecialchars($_GET['error']).'
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

require_once '../connection/db.php';

// Initialize variables
$search = $_GET['search'] ?? '';
$selectedYear = $_GET['academic_year_id'] ?? '';
$tenantType = $_GET['tenant_type'] ?? '';
$selectedFloor = $_GET['floor_id'] ?? '';
$selectedRoom = $_GET['room_id'] ?? '';
$tenants = [];

// Fetch academic years
$academicYears = [];
$ayQuery = "SELECT academic_year_id, start_year, end_year, semester FROM academic_years ORDER BY start_year DESC, semester DESC";
$ayResult = $conn->query($ayQuery);
if ($ayResult && $ayResult->num_rows > 0) {
    while ($row = $ayResult->fetch_assoc()) {
        $academicYears[] = $row;
    }
}

// Fetch all floors for filter dropdown
$floors = $conn->query("SELECT DISTINCT floor_id, floor_no FROM floors ORDER BY floor_no ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch rooms based on selected floor (if any)
$rooms = [];
$roomQuery = "SELECT DISTINCT r.room_id, r.room_no 
              FROM rooms r
              LEFT JOIN floors f ON r.floor_id = f.floor_id
              WHERE 1=1";

if (!empty($selectedFloor)) {
    $roomQuery .= " AND r.floor_id = " . (int)$selectedFloor;
}

$rooms = $conn->query($roomQuery . " ORDER BY r.room_no ASC")->fetch_all(MYSQLI_ASSOC);

// Build query
$query = "SELECT t.*, 
          CONCAT(t.last_name, ', ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS full_name,
          c.course_description,
          g.first_name AS guardian_first_name, g.last_name AS guardian_last_name,
          g.mobile_no AS guardian_mobile,
          b.bed_no, b.deck, b.status as bed_status, 
          r.room_no, r.room_id,
          f.floor_no, f.floor_id,
          a.start_year, a.end_year, a.semester,
          t.student_id
          FROM tenants t
          LEFT JOIN course c ON t.course_id = c.course_id
          LEFT JOIN guardians g ON t.guardian_id = g.guardian_id
          LEFT JOIN academic_years a ON t.academic_year_id = a.academic_year_id
          LEFT JOIN (
              SELECT tenant_id, bed_id, start_date, due_date
              FROM boarding
              WHERE due_date >= CURDATE()
              ORDER BY boarding_id DESC
              LIMIT 1
          ) bo ON t.tenant_id = bo.tenant_id
          LEFT JOIN beds b ON bo.bed_id = b.bed_id
          LEFT JOIN rooms r ON b.room_id = r.room_id
          LEFT JOIN floors f ON r.floor_id = f.floor_id
          WHERE 1=1";

$types = '';
$params = [];

if (!empty($search)) {
    $query .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.mobile_no LIKE ? 
              OR c.course_description LIKE ? OR g.last_name LIKE ? OR g.mobile_no LIKE ?)";
    $types .= str_repeat('s', 6);
    $searchTerm = "%$search%";
    $params = array_fill(0, 6, $searchTerm);
}

if (!empty($selectedYear)) {
    $query .= " AND t.academic_year_id = ?";
    $types .= 'i';
    $params[] = $selectedYear;
}

if (!empty($tenantType)) {
    $query .= " AND t.tenant_type = ?";
    $types .= 's';
    $params[] = $tenantType;
}

if (!empty($selectedFloor)) {
    $query .= " AND f.floor_id = ?";
    $types .= 'i';
    $params[] = $selectedFloor;
}

if (!empty($selectedRoom)) {
    $query .= " AND r.room_id = ?";
    $types .= 'i';
    $params[] = $selectedRoom;
}

$query .= " ORDER BY t.last_name, t.first_name";

// Execute query
if ($stmt = $conn->prepare($query)) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $tenants = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        .tenant-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            background-color: #6c757d;
            color: white;
        }
        .tenant-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .occupied {
            background-color: #f0f00b;
            color: #000;
        }
        .vacant {
            background-color: #28a745;
            color: white;
        }
        .student-badge {
            background-color: #007bff;
            color: white;
        }
        .non-student-badge {
            background-color: #6c757d;
            color: white;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .dropdown-menu .active {
            background-color: #4361ee;
            color: white !important;
        }
        .dropdown-menu .active:hover {
            background-color: #3a56d4;
        }
        .filter-badge {
            font-size: 0.75rem;
        }
        .add-tenant-btn {
            white-space: nowrap;
        }
        .search-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
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
            <h2><i class="bi bi-people-fill"></i> <strong>Tenant Management</strong></h2>
        </div>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <!-- Search Column - Adjusted -->
                <div class="col-md-3">
                    <form method="get" class="d-flex">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search tenants..." 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary search-btn">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tenant Type Filter -->
                <div class="col-md-2">
                    <form method="get" id="typeFilterForm">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="floor_id" value="<?= htmlspecialchars($selectedFloor) ?>">
                        <input type="hidden" name="room_id" value="<?= htmlspecialchars($selectedRoom) ?>">
                        <input type="hidden" name="academic_year_id" value="<?= htmlspecialchars($selectedYear) ?>">
                        <select name="tenant_type" class="form-select" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="Student" <?= $tenantType == 'Student' ? 'selected' : '' ?>>Students</option>
                            <option value="Non-Student" <?= $tenantType == 'Non-Student' ? 'selected' : '' ?>>Non-Students</option>
                        </select>
                    </form>
                </div>

                <!-- Academic Year Filter -->
                <div class="col-md-2">
                    <form method="get" id="yearFilterForm">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="tenant_type" value="<?= htmlspecialchars($tenantType) ?>">
                        <input type="hidden" name="floor_id" value="<?= htmlspecialchars($selectedFloor) ?>">
                        <input type="hidden" name="room_id" value="<?= htmlspecialchars($selectedRoom) ?>">
                        <select name="academic_year_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Academic Year</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['academic_year_id'] ?>" <?= $selectedYear == $year['academic_year_id'] ? 'selected' : '' ?>>
                                    <?= $year['start_year'] ?>-<?= $year['end_year'] ?> (<?= $year['semester'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Floor Filter -->
                <div class="col-md-2">
                    <form method="get" id="floorFilterForm">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="tenant_type" value="<?= htmlspecialchars($tenantType) ?>">
                        <input type="hidden" name="academic_year_id" value="<?= htmlspecialchars($selectedYear) ?>">
                        <select name="floor_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Floors</option>
                            <?php foreach ($floors as $floor): ?>
                                <option value="<?= $floor['floor_id'] ?>" <?= $selectedFloor == $floor['floor_id'] ? 'selected' : '' ?>>
                                    Floor <?= $floor['floor_no'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Room Filter -->
                <div class="col-md-2">
                    <form method="get" id="roomFilterForm">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="tenant_type" value="<?= htmlspecialchars($tenantType) ?>">
                        <input type="hidden" name="academic_year_id" value="<?= htmlspecialchars($selectedYear) ?>">
                        <input type="hidden" name="floor_id" value="<?= htmlspecialchars($selectedFloor) ?>">
                        <select name="room_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Rooms</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= $room['room_id'] ?>" <?= $selectedRoom == $room['room_id'] ? 'selected' : '' ?>>
                                    Room <?= $room['room_no'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Add Tenant Button -->
                <div class="col-md-1">
                    <a href="add-tenant.php" class="btn btn-success w-100 add-tenant-btn" title="Add New Tenant">
                        <i class="bi bi-plus-lg"></i> Add
                    </a>
                </div>
                
                <!-- Print Button -->
                <div class="col-md-1">
                    <button class="btn btn-info w-100" onclick="printTenants()" title="Print Tenant List">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Summary and Quick Filters -->
    <div class="d-flex mb-3 gap-2 flex-wrap">
        <div>
            <span class="badge student-badge">Students: 
                <?= count(array_filter($tenants, fn($t) => $t['tenant_type'] === 'Student')) ?>
            </span>
        </div>
        <div>
            <span class="badge non-student-badge">Non-Students: 
                <?= count(array_filter($tenants, fn($t) => $t['tenant_type'] === 'Non-Student')) ?>
            </span>
        </div>
        <div>
            <span class="badge occupied">Occupied: 
                <?= count(array_filter($tenants, fn($t) => ($t['bed_status'] ?? '') === 'Occupied')) ?>
            </span>
        </div>
        <div>
            <span class="badge vacant">Vacant: 
                <?= count(array_filter($tenants, fn($t) => !isset($t['bed_status']) || $t['bed_status'] === 'Vacant')) ?>
            </span>
        </div>
    
        
        <!-- Clear filters button -->
        <?php if (!empty($search) || !empty($selectedYear) || !empty($tenantType) || !empty($selectedFloor) || !empty($selectedRoom)): ?>
        <div>
            <a href="tenant.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-x-circle"></i> Clear Filters
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Active filters indicator -->
    <?php if (!empty($selectedFloor) || !empty($selectedRoom)): ?>
    <div class="mb-3">
        <small class="text-muted">
            Currently showing: 
            <?php if (!empty($selectedFloor)): ?>
                <span class="badge bg-primary filter-badge">
                    Floor <?= $floors[array_search($selectedFloor, array_column($floors, 'floor_id'))]['floor_no'] ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($selectedRoom)): ?>
                <span class="badge bg-secondary filter-badge">
                    Room <?= $rooms[array_search($selectedRoom, array_column($rooms, 'room_id'))]['room_no'] ?>
                </span>
            <?php endif; ?>
        </small>
    </div>
    <?php endif; ?>

    <!-- Tenants Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Tenant</th>
                            <th>Type</th>
                            <th>Student ID</th>
                            <th>Course</th>
                            <th>Contact</th>
                            <th>Bed/Room</th>
                            <th>Academic Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                        <tr>
                            <td>
                                <div class="tenant-photo">
                                    <?php if (!empty($tenant['profile_picture'])): ?>
                                        <img src="../Uploads/profiles/<?= htmlspecialchars($tenant['profile_picture']) ?>" 
                                             alt="<?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?>">
                                    <?php else: ?>
                                        <?= strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($tenant['last_name'] . ', ' . $tenant['first_name']) ?></div>
                                <small class="text-muted"><?= $tenant['gender'] === 'M' ? 'Male' : 'Female' ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $tenant['tenant_type'] === 'Student' ? 'student-badge' : 'non-student-badge' ?>">
                                    <?= $tenant['tenant_type'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tenant['tenant_type'] === 'Student'): ?>
                                    <?= !empty($tenant['student_id']) ? htmlspecialchars($tenant['student_id']) : 'N/A' ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tenant['tenant_type'] === 'Student'): ?>
                                    <?= htmlspecialchars($tenant['course_description'] ?? 'N/A') ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($tenant['mobile_no']) ?></div>
                                <small class="text-muted"><?= date('M j, Y', strtotime($tenant['birthdate'])) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($tenant['bed_no'])): ?>
                                <span class="badge <?= $tenant['bed_status'] === 'Occupied' ? 'occupied' : 'vacant' ?> status-badge">
                                    Bed <?= $tenant['bed_no'] ?> (<?= $tenant['deck'] ?>)
                                </span>
                                <div class="small">
                                    <a href="?floor_id=<?= $tenant['floor_id'] ?>&room_id=<?= $tenant['room_id'] ?>" class="text-decoration-none">
                                        Room <?= $tenant['room_no'] ?>, Floor <?= $tenant['floor_no'] ?>
                                    </a>
                                </div>
                                <?php else: ?>
                                <span class="badge bg-secondary status-badge">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $tenant['start_year'] ?>-<?= $tenant['end_year'] ?>
                                <div class="small"><?= $tenant['semester'] ?> Semester</div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view-tenant.php?id=<?= $tenant['tenant_id'] ?>" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit-tenant.php?id=<?= $tenant['tenant_id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete-tenant.php?id=<?= $tenant['tenant_id'] ?>" class="btn btn-outline-danger" title="Delete" 
                                       onclick="return confirmDelete(<?= $tenant['tenant_id'] ?>, '<?= htmlspecialchars(addslashes($tenant['last_name'] . ', ' . $tenant['first_name'])) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tenants)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-people display-6"></i>
                                <div class="mt-2">No tenants found</div>
                                <?php if (!empty($search) || !empty($selectedYear) || !empty($tenantType) || !empty($selectedFloor) || !empty($selectedRoom)): ?>
                                <a href="tenant.php" class="btn btn-sm btn-outline-primary mt-2">
                                    Clear filters
                                </a>
                                <?php endif; ?>
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
function confirmDelete(id, name) {
    if (confirm('Are you sure you want to delete tenant: ' + name + '?\nThis will also free up any assigned beds.\nThis action cannot be undone!')) {
        window.location.href = 'delete-tenant.php?id=' + id + '&t=' + Date.now();
        return true;
    }
    return false;
}

function printTenants() {
    // Get current filter values
    const tenantType = document.querySelector('select[name="tenant_type"]').value;
    const floorId = document.querySelector('select[name="floor_id"]').value;
    const roomId = document.querySelector('select[name="room_id"]').value;
    const academicYear = document.querySelector('select[name="academic_year_id"]').value;
    const searchTerm = document.querySelector('input[name="search"]').value;
    
    // Open print window with filters
    const printUrl = `print-tenants.php?tenant_type=${tenantType}&floor_id=${floorId}&room_id=${roomId}&academic_year_id=${academicYear}&search=${encodeURIComponent(searchTerm)}`;
    window.open(printUrl, '_blank');
}

window.onpageshow = function(event) {
    if (event.persisted) {
        window.location.reload();
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // Auto-clear room filter when floor changes
    document.querySelector('select[name="floor_id"]')?.addEventListener('change', function() {
        document.querySelector('select[name="room_id"]').value = '';
    });
    
    // Highlight current filters in dropdowns
    const currentFloor = <?= json_encode($selectedFloor) ?>;
    const currentRoom = <?= json_encode($selectedRoom) ?>;
    
    if (currentFloor) {
        const floorItems = document.querySelectorAll('#floorDropdown + .dropdown-menu a');
        floorItems.forEach(item => {
            if (item.getAttribute('href')?.includes(`floor_id=${currentFloor}`)) {
                item.classList.add('active');
            }
        });
    }
    
    if (currentRoom) {
        const roomItems = document.querySelectorAll('#roomDropdown + .dropdown-menu a');
        roomItems.forEach(item => {
            if (item.getAttribute('href')?.includes(`room_id=${currentRoom}`)) {
                item.classList.add('active');
            }
        });
    }
});
</script>
</body>
</html>

<?php 
$conn->close();
?>