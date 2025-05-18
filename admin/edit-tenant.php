<?php
session_start();
require_once '../connection/db.php';

// Validate tenant ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid tenant ID provided";
    header("Location: tenant.php");
    exit;
}

$tenantId = (int)$_GET['id'];
if ($tenantId <= 0) {
    $_SESSION['error_message'] = "Invalid tenant ID format";
    header("Location: tenant.php");
    exit;
}

// Fetch tenant details with LEFT JOINs for optional relationships
$query = "SELECT t.*, 
          c.course_id, c.course_code, c.course_description,
          g.guardian_id, g.first_name AS guardian_first_name, g.last_name AS guardian_last_name, 
          g.middle_name AS guardian_middle_name, g.mobile_no AS guardian_mobile, g.relationship,
          a.academic_year_id, a.start_year, a.end_year, a.semester,
          b.bed_id, b.bed_no, b.deck, b.monthly_rent, b.status,
          r.room_id, r.room_no, r.capacity,
          fl.floor_id, fl.floor_no
          FROM tenants t
          LEFT JOIN course c ON t.course_id = c.course_id
          LEFT JOIN guardians g ON t.guardian_id = g.guardian_id
          LEFT JOIN academic_years a ON t.academic_year_id = a.academic_year_id
          LEFT JOIN boarding bd ON t.tenant_id = bd.tenant_id
          LEFT JOIN beds b ON bd.bed_id = b.bed_id
          LEFT JOIN rooms r ON b.room_id = r.room_id
          LEFT JOIN floors fl ON r.floor_id = fl.floor_id
          WHERE t.tenant_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$result = $stmt->get_result();
$tenant = $result->fetch_assoc();
$stmt->close();

if (!$tenant) {
    $_SESSION['error_message'] = "Tenant not found with ID: $tenantId";
    header("Location: tenant.php");
    exit;
}

// Fetch dropdown data
$courses = $conn->query("SELECT * FROM course ORDER BY course_description")->fetch_all(MYSQLI_ASSOC);
$academicYears = $conn->query("SELECT * FROM academic_years ORDER BY start_year DESC, semester DESC")->fetch_all(MYSQLI_ASSOC);
$floors = $conn->query("SELECT * FROM floors ORDER BY floor_no")->fetch_all(MYSQLI_ASSOC);

// Initialize room and bed arrays
$rooms = [];
$beds = [];

// If tenant has a bed assigned, get rooms for that floor and beds for that room
if ($tenant['bed_id']) {
    $rooms = $conn->query("
        SELECT r.*, 
               COUNT(b.bed_id) AS capacity,
               SUM(CASE WHEN b.status = 'Occupied' AND (bd.tenant_id IS NULL OR bd.tenant_id != $tenantId) THEN 1 ELSE 0 END) AS occupied_beds
        FROM rooms r
        LEFT JOIN beds b ON r.room_id = b.room_id
        LEFT JOIN boarding bd ON b.bed_id = bd.bed_id
        WHERE r.floor_id = {$tenant['floor_id']}
        GROUP BY r.room_id
        HAVING capacity > occupied_beds OR r.room_id = {$tenant['room_id']}
        ORDER BY r.room_no
    ")->fetch_all(MYSQLI_ASSOC);

    $beds = $conn->query("
        SELECT b.* 
        FROM beds b
        LEFT JOIN boarding bd ON b.bed_id = bd.bed_id
        WHERE b.room_id = {$tenant['room_id']}
        AND (b.status = 'Vacant' OR (bd.tenant_id = $tenantId AND bd.bed_id = b.bed_id))
        ORDER BY b.bed_no
    ")->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update guardian
        $guardianStmt = $conn->prepare("UPDATE guardians SET 
                                      last_name = ?, first_name = ?, middle_name = ?, 
                                      mobile_no = ?, relationship = ?
                                      WHERE guardian_id = ?");
        $guardianStmt->bind_param('sssssi',
            $_POST['guardian_last_name'],
            $_POST['guardian_first_name'],
            $_POST['guardian_middle_name'],
            $_POST['guardian_mobile'],
            $_POST['relationship'],
            $tenant['guardian_id']
        );
        $guardianStmt->execute();
        $guardianStmt->close();
        
        // Update tenant
        $isStudent = isset($_POST['is_student']) && $_POST['is_student'] == 1;
        
        if ($isStudent) {
            $tenantStmt = $conn->prepare("UPDATE tenants SET 
                                        first_name = ?, last_name = ?, middle_name = ?,
                                        birthdate = ?, address = ?, gender = ?,
                                        mobile_no = ?, course_id = ?, academic_year_id = ?,
                                        is_student = 1, student_id = ?, tenant_type = 'Student'
                                        WHERE tenant_id = ?");
            $tenantStmt->bind_param('sssssssiiii',
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['middle_name'],
                $_POST['birthdate'],
                $_POST['address'],
                $_POST['gender'],
                $_POST['mobile_no'],
                $_POST['course_id'],
                $_POST['academic_year_id'],
                $_POST['student_id'],
                $tenantId
            );
        } else {
            $tenantStmt = $conn->prepare("UPDATE tenants SET 
                                        first_name = ?, last_name = ?, middle_name = ?,
                                        birthdate = ?, address = ?, gender = ?,
                                        mobile_no = ?, course_id = NULL, academic_year_id = NULL,
                                        is_student = 0, student_id = NULL, tenant_type = 'Non-Student'
                                        WHERE tenant_id = ?");
            $tenantStmt->bind_param('sssssssi',
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['middle_name'],
                $_POST['birthdate'],
                $_POST['address'],
                $_POST['gender'],
                $_POST['mobile_no'],
                $tenantId
            );
        }
        $tenantStmt->execute();
        $tenantStmt->close();
        
        // Handle bed assignment
        $newBedId = !empty($_POST['bed_id']) ? (int)$_POST['bed_id'] : null;
        
        if ($newBedId) {
            // Check if tenant already has a boarding record
            $boardingCheck = $conn->query("SELECT * FROM boarding WHERE tenant_id = $tenantId");
            
            if ($boardingCheck->num_rows > 0) {
                // Update existing boarding record
                $boardingStmt = $conn->prepare("UPDATE boarding SET bed_id = ? WHERE tenant_id = ?");
                $boardingStmt->bind_param('ii', $newBedId, $tenantId);
            } else {
                // Create new boarding record
                $boardingStmt = $conn->prepare("INSERT INTO boarding (tenant_id, bed_id, start_date, due_date) 
                                              VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))");
                $boardingStmt->bind_param('ii', $tenantId, $newBedId);
            }
            $boardingStmt->execute();
            $boardingStmt->close();
            
            // Update bed status
            $conn->query("UPDATE beds SET status = 'Occupied' WHERE bed_id = $newBedId");
            
            // If changing beds, set old bed to vacant
            if ($tenant['bed_id'] && $tenant['bed_id'] != $newBedId) {
                $conn->query("UPDATE beds SET status = 'Vacant' WHERE bed_id = {$tenant['bed_id']}");
            }
        } else {
            // Remove bed assignment if bed_id is empty
            if ($tenant['bed_id']) {
                $conn->query("DELETE FROM boarding WHERE tenant_id = $tenantId");
                $conn->query("UPDATE beds SET status = 'Vacant' WHERE bed_id = {$tenant['bed_id']}");
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Tenant updated successfully";
        header("Location: view-tenant.php?id=$tenantId");
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = "Error updating tenant: " . $e->getMessage();
    }
}

// Handle AJAX requests for room and bed dropdowns
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] == 'rooms' && isset($_GET['floor_id'])) {
        $floorId = (int)$_GET['floor_id'];
        $tenantId = (int)$_GET['id'];
        
        $rooms = $conn->query("
            SELECT r.*, 
                   COUNT(b.bed_id) AS capacity,
                   SUM(CASE WHEN b.status = 'Occupied' AND (bd.tenant_id IS NULL OR bd.tenant_id != $tenantId) THEN 1 ELSE 0 END) AS occupied_beds
            FROM rooms r
            LEFT JOIN beds b ON r.room_id = b.room_id
            LEFT JOIN boarding bd ON b.bed_id = bd.bed_id
            WHERE r.floor_id = $floorId
            GROUP BY r.room_id
            HAVING capacity > occupied_beds OR r.room_id = " . ($tenant['room_id'] ?? 0) . "
            ORDER BY r.room_no
        ")->fetch_all(MYSQLI_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($rooms);
        exit;
    }
    
    if ($_GET['ajax'] == 'beds' && isset($_GET['room_id'])) {
        $roomId = (int)$_GET['room_id'];
        $tenantId = (int)$_GET['id'];
        
        $beds = $conn->query("
            SELECT b.* 
            FROM beds b
            LEFT JOIN boarding bd ON b.bed_id = bd.bed_id
            WHERE b.room_id = $roomId
            AND (b.status = 'Vacant' OR (bd.tenant_id = $tenantId AND bd.bed_id = b.bed_id))
            ORDER BY b.bed_no
        ")->fetch_all(MYSQLI_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($beds);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tenant - <?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        .student-fields { transition: all 0.3s ease; overflow: hidden; }
        .profile-header { background-color: #f8f9fa; border-radius: 0.375rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .tenant-photo { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; background-color: #6c757d; 
                      color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; 
                      font-weight: bold; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 0 auto; }
        .tenant-photo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        #photoModal .modal-body { padding: 1.5rem; }
        .photo-upload-btn { position: absolute; bottom: 0; right: 0; transform: translate(25%, 25%); }
        .form-label.required:after { content: " *"; color: red; }
        .loading { opacity: 0.6; pointer-events: none; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid mt-4">
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square"></i> Edit Tenant</h2>
            <a href="tenant.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="position-relative">
                        <div class="tenant-photo mx-auto mb-2">
                            <?php if (!empty($tenant['profile_picture'])): ?>
                                <img src="../uploads/profiles/<?= htmlspecialchars($tenant['profile_picture']) ?>" 
                                     alt="<?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?>">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <?= strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary photo-upload-btn" 
                                data-bs-toggle="modal" data-bs-target="#photoModal">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-10">
                    <h3><?= htmlspecialchars($tenant['last_name'] . ', ' . $tenant['first_name']) ?></h3>
                    <p class="mb-0">Tenant ID: <?= $tenantId ?></p>
                    <p class="mb-0">Status: <?= $tenant['is_student'] ? 'Student' : 'Non-Student' ?></p>
                    <?php if ($tenant['bed_id']): ?>
                        <p class="mb-0">Current Bed: Floor <?= htmlspecialchars($tenant['floor_no']) ?> - Room <?= htmlspecialchars($tenant['room_no']) ?> - Bed <?= htmlspecialchars($tenant['bed_no']) ?> (<?= $tenant['deck'] ?>)</p>
                        <p class="mb-0">Monthly Rent: ₱<?= number_format($tenant['monthly_rent'], 2) ?></p>
                    <?php else: ?>
                        <p class="mb-0 text-danger">No bed assigned</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="POST" class="needs-validation" novalidate>
            <!-- Personal Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-person-vcard"></i> Personal Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">First Name</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['first_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['middle_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Last Name</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Birthdate</label>
                            <input type="date" name="birthdate" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['birthdate']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="M" <?= $tenant['gender'] === 'M' ? 'selected' : '' ?>>Male</option>
                                <option value="F" <?= $tenant['gender'] === 'F' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Mobile Number</label>
                            <input type="text" name="mobile_no" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['mobile_no']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Address</label>
                        <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($tenant['address']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Status</label>
                        <select name="is_student" id="isStudentSelect" class="form-select" required>
                            <option value="1" <?= $tenant['is_student'] ? 'selected' : '' ?>>Student</option>
                            <option value="0" <?= !$tenant['is_student'] ? 'selected' : '' ?>>Non-Student</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Academic Information Card (shown only for students) -->
            <div class="card mb-4 student-fields" id="studentFields" style="<?= $tenant['is_student'] ? '' : 'display: none;' ?>">
                <div class="card-header">
                    <i class="bi bi-book"></i> Academic Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Course</label>
                            <select name="course_id" class="form-select" id="courseSelect" <?= $tenant['is_student'] ? 'required' : '' ?>>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['course_id'] ?>" 
                                    <?= $course['course_id'] == $tenant['course_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_description']) ?> (<?= $course['course_code'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Academic Year</label>
                            <select name="academic_year_id" class="form-select" id="academicYearSelect" <?= $tenant['is_student'] ? 'required' : '' ?>>
                                <option value="">Select Academic Year</option>
                                <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['academic_year_id'] ?>" 
                                    <?= $year['academic_year_id'] == $tenant['academic_year_id'] ? 'selected' : '' ?>>
                                    <?= $year['start_year'] ?>-<?= $year['end_year'] ?> (<?= $year['semester'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Student ID</label>
                            <input type="text" name="student_id" class="form-control" id="studentIdField"
                                   value="<?= htmlspecialchars($tenant['student_id'] ?? '') ?>" <?= $tenant['is_student'] ? 'required' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guardian Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-shield"></i> Guardian Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">First Name</label>
                            <input type="text" name="guardian_first_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_first_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="guardian_middle_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_middle_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Last Name</label>
                            <input type="text" name="guardian_last_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Mobile Number</label>
                            <input type="text" name="guardian_mobile" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_mobile']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Relationship</label>
                            <input type="text" name="relationship" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['relationship']) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bed Assignment Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-house-door"></i> Bed Assignment
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Floor</label>
                            <select id="floor_id" class="form-select">
                                <option value="">Select Floor</option>
                                <?php foreach ($floors as $floor): ?>
                                <option value="<?= $floor['floor_id'] ?>" 
                                    <?= isset($tenant['floor_id']) && $floor['floor_id'] == $tenant['floor_id'] ? 'selected' : '' ?>>
                                    Floor <?= htmlspecialchars($floor['floor_no']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Room</label>
                            <select id="room_id" class="form-select" <?= empty($rooms) ? 'disabled' : '' ?>>
                                <option value="">Select Room</option>
                                <?php if (!empty($rooms)): ?>
                                    <?php foreach ($rooms as $room): ?>
                                    <option value="<?= $room['room_id'] ?>" 
                                        <?= isset($tenant['room_id']) && $room['room_id'] == $tenant['room_id'] ? 'selected' : '' ?>
                                        data-available="<?= $room['capacity'] - $room['occupied_beds'] ?>">
                                        Room <?= htmlspecialchars($room['room_no']) ?> 
                                        (Capacity: <?= $room['capacity'] ?>, 
                                        Available: <?= $room['capacity'] - $room['occupied_beds'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bed</label>
                            <select name="bed_id" id="bed_id" class="form-select" <?= empty($beds) ? 'disabled' : '' ?>>
                                <option value="">Select Bed</option>
                                <?php if (!empty($beds)): ?>
                                    <?php foreach ($beds as $bed): ?>
                                    <option value="<?= $bed['bed_id'] ?>" 
                                        <?= isset($tenant['bed_id']) && $bed['bed_id'] == $tenant['bed_id'] ? 'selected' : '' ?>
                                        data-monthly-rent="<?= $bed['monthly_rent'] ?>">
                                        Bed <?= $bed['bed_no'] ?> (<?= $bed['deck'] ?> - ₱<?= number_format($bed['monthly_rent'], 2) ?>)
                                        <?= $bed['status'] === 'Occupied' && $bed['bed_id'] != $tenant['bed_id'] ? ' - Occupied' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Changes
                </button>
                <a href="view-tenant.php?id=<?= $tenantId ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Photo Upload Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="photoForm" action="upload-photo.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="tenant_id" value="<?= $tenantId ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Update Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Choose new photo</label>
                        <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                        <div class="form-text">Max size: 2MB. Allowed formats: JPG, PNG, GIF</div>
                    </div>
                    <?php if (!empty($tenant['profile_picture'])): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remove_photo" id="remove_photo">
                        <label class="form-check-label" for="remove_photo">
                            Remove current photo
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})()

// Toggle student fields based on status
document.getElementById('isStudentSelect').addEventListener('change', function() {
    const studentFields = document.getElementById('studentFields');
    const isStudent = this.value === '1';
    
    if (isStudent) {
        studentFields.style.display = 'block';
        document.getElementById('courseSelect').required = true;
        document.getElementById('academicYearSelect').required = true;
        document.getElementById('studentIdField').required = true;
    } else {
        studentFields.style.display = 'none';
        document.getElementById('courseSelect').required = false;
        document.getElementById('academicYearSelect').required = false;
        document.getElementById('studentIdField').required = false;
    }
});

// AJAX for bed assignment
document.addEventListener('DOMContentLoaded', function() {
    const floorSelect = document.getElementById('floor_id');
    const roomSelect = document.getElementById('room_id');
    const bedSelect = document.getElementById('bed_id');

    // Function to show loading state
    function showLoading(element) {
        element.classList.add('loading');
        element.disabled = true;
    }

    // Function to hide loading state
    function hideLoading(element) {
        element.classList.remove('loading');
        element.disabled = false;
    }

    // Function to fetch available rooms for a floor
    function fetchAvailableRooms(floorId) {
        if (!floorId) {
            roomSelect.innerHTML = '<option value="">Select Room</option>';
            roomSelect.disabled = true;
            bedSelect.innerHTML = '<option value="">Select Bed</option>';
            bedSelect.disabled = true;
            return;
        }

        showLoading(roomSelect);
        roomSelect.innerHTML = '<option value="">Loading rooms...</option>';
        
        fetch(`edit-tenant.php?ajax=rooms&floor_id=${floorId}&id=<?= $tenantId ?>`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(rooms => {
                roomSelect.innerHTML = '<option value="">Select Room</option>';
                
                if (rooms && rooms.length > 0) {
                    rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.room_id;
                        option.textContent = `Room ${room.room_no} (Avail: ${room.capacity - room.occupied_beds}/${room.capacity})`;
                        if (room.room_id == <?= $tenant['room_id'] ?? 0 ?>) {
                            option.selected = true;
                        }
                        roomSelect.appendChild(option);
                    });
                } else {
                    roomSelect.innerHTML += '<option value="" disabled>No rooms available on this floor</option>';
                }
                
                roomSelect.disabled = false;
                hideLoading(roomSelect);
                
                // If tenant already has a room on this floor, trigger room change
                if (<?= isset($tenant['room_id']) ? 'true' : 'false' ?> && 
                    <?= isset($tenant['floor_id']) ? $tenant['floor_id'] : 0 ?> == floorId) {
                    roomSelect.dispatchEvent(new Event('change'));
                }
            })
            .catch(error => {
                console.error('Error fetching rooms:', error);
                roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
                hideLoading(roomSelect);
            });
    }

    // Function to fetch available beds for a room
    function fetchAvailableBeds(roomId) {
        if (!roomId) {
            bedSelect.innerHTML = '<option value="">Select Bed</option>';
            bedSelect.disabled = true;
            return;
        }

        showLoading(bedSelect);
        bedSelect.innerHTML = '<option value="">Loading beds...</option>';
        
        fetch(`edit-tenant.php?ajax=beds&room_id=${roomId}&id=<?= $tenantId ?>`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(beds => {
                bedSelect.innerHTML = '<option value="">Select Bed</option>';
                
                if (beds && beds.length > 0) {
                    beds.forEach(bed => {
                        const option = document.createElement('option');
                        option.value = bed.bed_id;
                        let bedText = `Bed ${bed.bed_no} (${bed.deck} - ₱${parseFloat(bed.monthly_rent).toFixed(2)}`;
                        if (bed.status === 'Occupied' && bed.bed_id != <?= $tenant['bed_id'] ?? 0 ?>) {
                            bedText += ' - Occupied';
                        }
                        option.textContent = bedText;
                        if (bed.bed_id == <?= $tenant['bed_id'] ?? 0 ?>) {
                            option.selected = true;
                        }
                        bedSelect.appendChild(option);
                    });
                } else {
                    bedSelect.innerHTML += '<option value="" disabled>No beds available in this room</option>';
                }
                
                bedSelect.disabled = false;
                hideLoading(bedSelect);
            })
            .catch(error => {
                console.error('Error fetching beds:', error);
                bedSelect.innerHTML = '<option value="">Error loading beds</option>';
                hideLoading(bedSelect);
            });
    }

    // Event listeners
    floorSelect.addEventListener('change', function() {
        fetchAvailableRooms(this.value);
    });

    roomSelect.addEventListener('change', function() {
        fetchAvailableBeds(this.value);
    });

    // Initialize if floor is already selected
    if (floorSelect.value) {
        roomSelect.disabled = false;
        fetchAvailableRooms(floorSelect.value);
        
        // If tenant has a room, enable bed dropdown immediately
        if (<?= isset($tenant['room_id']) ? 'true' : 'false' ?>) {
            bedSelect.disabled = false;
        }
    }

    // Handle photo upload form submission
    document.getElementById('photoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('upload-photo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
});
</script>
</body>
</html>

<?php
$conn->close();
?>