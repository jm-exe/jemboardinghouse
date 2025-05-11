<?php
session_start();
require_once '../connection/db.php';

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No tenant ID provided";
    header("Location: tenant.php");
    exit;
}

$tenantId = (int)$_GET['id'];

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
    $_SESSION['error_message'] = "Tenant not found";
    header("Location: tenant.php");
    exit;
}

// Fetch dropdown data
$courses = $conn->query("SELECT * FROM course ORDER BY course_description")->fetch_all(MYSQLI_ASSOC);
$academicYears = $conn->query("SELECT * FROM academic_years ORDER BY start_year DESC, semester DESC")->fetch_all(MYSQLI_ASSOC);
$floors = $conn->query("SELECT * FROM floors ORDER BY floor_no")->fetch_all(MYSQLI_ASSOC);
$rooms = [];
$beds = [];

if ($tenant['bed_id']) {
    // Get all rooms for the current floor
    $rooms = $conn->query("SELECT * FROM rooms WHERE floor_id = {$tenant['floor_id']} ORDER BY room_no")->fetch_all(MYSQLI_ASSOC);
    // Get all beds for the current room (excluding occupied ones, except the current one)
    $beds = $conn->query("SELECT b.* FROM beds b 
                         LEFT JOIN boarding bd ON b.bed_id = bd.bed_id 
                         LEFT JOIN tenants t ON bd.tenant_id = t.tenant_id
                         WHERE b.room_id = {$tenant['room_id']} 
                         AND (bd.bed_id IS NULL OR bd.tenant_id = {$tenantId})
                         ORDER BY b.bed_no")->fetch_all(MYSQLI_ASSOC);
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
        
        // Update tenant - handle NULL values for non-students
        $isStudent = isset($_POST['is_student']) && $_POST['is_student'] == 1;
        $courseId = $isStudent ? $_POST['course_id'] : null;
        $academicYearId = $isStudent ? $_POST['academic_year_id'] : null;
        $studentId = $isStudent ? ($_POST['student_id'] ?? null) : null;
        
        $tenantStmt = $conn->prepare("UPDATE tenants SET 
                                    first_name = ?, last_name = ?, middle_name = ?,
                                    birthdate = ?, address = ?, gender = ?,
                                    mobile_no = ?, course_id = ?, academic_year_id = ?,
                                    is_student = ?, student_id = ?, tenant_type = ?
                                    WHERE tenant_id = ?");

        $studentFlag = $isStudent ? 1 : 0;
        $studentType = $isStudent ? 'Student' : 'Non-Student';
        
        $tenantStmt->bind_param('sssssssiiiisi',
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['middle_name'],
            $_POST['birthdate'],
            $_POST['address'],
            $_POST['gender'],
            $_POST['mobile_no'],
            $courseId,
            $academicYearId,
            $studentFlag,
            $studentId,
            $studentType,
            $tenantId
        );
        
        $tenantStmt->execute();
        $tenantStmt->close();
        
        // Handle bed assignment (same for both student and non-student)
        if (!empty($_POST['bed_id'])) {
            // Check if tenant already has a boarding record
            $boardingCheck = $conn->query("SELECT * FROM boarding WHERE tenant_id = $tenantId");
            
            if ($boardingCheck->num_rows > 0) {
                // Update existing boarding record
                $boardingStmt = $conn->prepare("UPDATE boarding SET bed_id = ? WHERE tenant_id = ?");
                $boardingStmt->bind_param('ii', $_POST['bed_id'], $tenantId);
            } else {
                // Create new boarding record
                $boardingStmt = $conn->prepare("INSERT INTO boarding (tenant_id, bed_id, start_date, due_date) 
                                              VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))");
                $boardingStmt->bind_param('ii', $tenantId, $_POST['bed_id']);
            }
            $boardingStmt->execute();
            $boardingStmt->close();
            
            // Update bed status
            $conn->query("UPDATE beds SET status = 'Occupied' WHERE bed_id = {$_POST['bed_id']}");
            
            // If changing beds, set old bed to vacant
            if ($tenant['bed_id'] && $tenant['bed_id'] != $_POST['bed_id']) {
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
        $rooms = $conn->query("SELECT * FROM rooms WHERE floor_id = $floorId ORDER BY room_no")->fetch_all(MYSQLI_ASSOC);
        echo json_encode($rooms);
        exit;
    }
    
    if ($_GET['ajax'] == 'beds' && isset($_GET['room_id'])) {
        $roomId = (int)$_GET['room_id'];
        $currentBedId = $tenant['bed_id'] ?? 0;
        $beds = $conn->query("SELECT b.* FROM beds b 
                             LEFT JOIN boarding bd ON b.bed_id = bd.bed_id 
                             LEFT JOIN tenants t ON bd.tenant_id = t.tenant_id
                             WHERE b.room_id = $roomId 
                             AND (bd.bed_id IS NULL OR t.tenant_id = $tenantId)
                             ORDER BY b.bed_no")->fetch_all(MYSQLI_ASSOC);
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
    <title>Edit Tenant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        .student-fields {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .profile-header {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .tenant-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            border: 3px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        .tenant-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        #photoModal .modal-body {
            padding: 1.5rem;
        }
        .photo-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            transform: translate(25%, 25%);
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square"></i> Edit Tenant</h2>
            <a href="tenant.php?id=<?= $tenantId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

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
                    <h3>Editing: <?= htmlspecialchars($tenant['last_name'] . ', ' . $tenant['first_name']) ?></h3>
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
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['first_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['middle_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate *</label>
                            <input type="date" name="birthdate" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['birthdate']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender *</label>
                            <select name="gender" class="form-select" required>
                                <option value="M" <?= $tenant['gender'] === 'M' ? 'selected' : '' ?>>Male</option>
                                <option value="F" <?= $tenant['gender'] === 'F' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mobile Number *</label>
                            <input type="text" name="mobile_no" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['mobile_no']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($tenant['address']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
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
                            <label class="form-label">Course *</label>
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
                            <label class="form-label">Academic Year *</label>
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
                            <label class="form-label">Student ID *</label>
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
                            <label class="form-label">First Name *</label>
                            <input type="text" name="guardian_first_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_first_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="guardian_middle_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_middle_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="guardian_last_name" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mobile Number *</label>
                            <input type="text" name="guardian_mobile" class="form-control" 
                                   value="<?= htmlspecialchars($tenant['guardian_mobile']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Relationship *</label>
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
                                <?php foreach ($rooms as $room): ?>
                                <option value="<?= $room['room_id'] ?>" 
                                    <?= isset($tenant['room_id']) && $room['room_id'] == $tenant['room_id'] ? 'selected' : '' ?>>
                                    Room <?= htmlspecialchars($room['room_no']) ?> (Capacity: <?= $room['capacity'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bed</label>
                            <select name="bed_id" id="bed_id" class="form-select" <?= empty($beds) ? 'disabled' : '' ?>>
                                <option value="">No Bed</option>
                                <?php foreach ($beds as $bed): ?>
                                <option value="<?= $bed['bed_id'] ?>" 
                                    <?= isset($tenant['bed_id']) && $bed['bed_id'] == $tenant['bed_id'] ? 'selected' : '' ?>
                                    data-monthly-rent="<?= $bed['monthly_rent'] ?>">
                                    Bed <?= $bed['bed_no'] ?> (<?= $bed['deck'] ?> - ₱<?= number_format($bed['monthly_rent'], 2) ?>)
                                </option>
                                <?php endforeach; ?>
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
        // Make fields required
        document.getElementById('courseSelect').required = true;
        document.getElementById('academicYearSelect').required = true;
        document.getElementById('studentIdField').required = true;
    } else {
        studentFields.style.display = 'none';
        // Remove required attribute
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
    
    // Handle floor change
    floorSelect.addEventListener('change', function() {
        const floorId = this.value;
        
        if (!floorId) {
            roomSelect.innerHTML = '<option value="">Select Room</option>';
            roomSelect.disabled = true;
            bedSelect.innerHTML = '<option value="">No Bed</option>';
            bedSelect.disabled = true;
            return;
        }
        
        // Fetch rooms for selected floor
        fetch(`?ajax=rooms&floor_id=${floorId}`)
            .then(response => response.json())
            .then(rooms => {
                roomSelect.innerHTML = '<option value="">Select Room</option>';
                rooms.forEach(room => {
                    roomSelect.innerHTML += `<option value="${room.room_id}">Room ${room.room_no} (Capacity: ${room.capacity})</option>`;
                });
                roomSelect.disabled = false;
                bedSelect.innerHTML = '<option value="">No Bed</option>';
                bedSelect.disabled = true;
            });
    });
    
    // Handle room change
    roomSelect.addEventListener('change', function() {
        const roomId = this.value;
        
        if (!roomId) {
            bedSelect.innerHTML = '<option value="">No Bed</option>';
            bedSelect.disabled = true;
            return;
        }
        
        // Fetch beds for selected room
        fetch(`?ajax=beds&room_id=${roomId}`)
            .then(response => response.json())
            .then(beds => {
                bedSelect.innerHTML = '<option value="">No Bed</option>';
                beds.forEach(bed => {
                    bedSelect.innerHTML += `<option value="${bed.bed_id}" data-monthly-rent="${bed.monthly_rent}">Bed ${bed.bed_no} (${bed.deck} - ₱${bed.monthly_rent.toFixed(2)})</option>`;
                });
                bedSelect.disabled = false;
            });
    });

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