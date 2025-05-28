<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
session_regenerate_id(true);
date_default_timezone_set('Asia/Manila');

require_once '../connection/db.php';

// Fetch necessary dropdown data
$courses = $conn->query("SELECT * FROM course ORDER BY course_description")->fetch_all(MYSQLI_ASSOC);
$academicYears = $conn->query("SELECT * FROM academic_years ORDER BY start_year DESC")->fetch_all(MYSQLI_ASSOC);
$yearLevels = ['1', '2', '3', '4'];

// Initialize current step
if (!isset($_SESSION['current_step'])) {
    $_SESSION['current_step'] = 1;
}
$current_step = $_SESSION['current_step'];

// Function to generate default username
function generateUsername($firstName, $lastName) {
    // Remove non-letters from both names
    $firstName = preg_replace('/[^a-zA-Z]/', '', $firstName);
    $lastName = preg_replace('/[^a-zA-Z]/', '', $lastName);

    // Create base username: whole first name + last name, all lowercase
    $baseUsername = strtolower($firstName . $lastName);

    // Generate a 10-digit random number (as a string, with leading zeros if needed)
    $randomNumber = str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);

    // Return the username with random 10-digit number
    return $baseUsername . $randomNumber;
}

function getAcademicYearId($conn, $yearRange, $semester) {
    [$start_year, $end_year] = explode('-', $yearRange);
    $stmt = $conn->prepare("SELECT academic_year_id FROM academic_years WHERE start_year = ? AND end_year = ? AND semester = ?");
    $stmt->bind_param("iis", $start_year, $end_year, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['academic_year_id'] ?? null;
}

function getSemesterDueDate($start_date) {
    // Use today's date to determine the semester
    $today = new DateTime(); // Current date and time
    $month = $today->format('n'); // Numeric month (1-12)
    $year = $today->format('Y'); // Current year

    // Determine the semester based on today's date
    if ($month >= 8 && $month <= 12) {
        // First Semester: August to December, ends on December 31
        return "$year-12-31";
    } elseif ($month >= 1 && $month <= 5) {
        // Second Semester: January to May, ends on May 31
        return "$year-05-31";
    } elseif ($month >= 6 && $month <= 7) {
        // Summer: June to July, ends on July 31
        return "$year-07-31";
    }

    // Fallback: If today's date is somehow invalid, use the start date's year and default to end of year
    $start = new DateTime($start_date);
    $year = $start->format('Y');
    return "$year-12-31";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step1'])) {
        // Store personal info in session
        $isStudent = isset($_POST['is_student']) && $_POST['is_student'] == '1';
        
        $_SESSION['tenant_data'] = [
            'first_name' => $_POST['first_name'],
            'middle_name' => $_POST['middle_name'] ?? null,
            'last_name' => $_POST['last_name'],
            'birthdate' => $_POST['birthdate'],
            'address' => $_POST['address'],
            'gender' => $_POST['gender'],
            'mobile_no' => $_POST['mobile_no'],
            'is_student' => $isStudent ? 1 : 0,
            'tenant_type' => $isStudent ? 'Student' : 'Non-Student',
            'student_id' => $isStudent ? ($_POST['student_id'] ?? null) : null,
            'course_id' => $isStudent ? ($_POST['course_id'] ?? null) : null,
            'year_level' => $isStudent ? ($_POST['year_level'] ?? '1') : '1',
            'academic_year_id' => $isStudent && isset($_POST['academic_year'], $_POST['semester']) 
                ? getAcademicYearId($conn, $_POST['academic_year'], $_POST['semester']) 
                : null
        ];
        
        $_SESSION['current_step'] = 2;
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['step2'])) {
        // Store guardian info in session
        $_SESSION['guardian_data'] = [
            'first_name' => $_POST['guardian_first_name'],
            'middle_name' => $_POST['guardian_middle_name'] ?? null,
            'last_name' => $_POST['guardian_last_name'],
            'mobile_no' => $_POST['guardian_mobile'],
            'relationship' => $_POST['guardian_relationship']
        ];
        $_SESSION['current_step'] = 3;
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['step3'])) {
        // Store bed assignment in session
        $_SESSION['bed_data'] = [
            'bed_id' => $_POST['bed_id'],
            'start_date' => $_POST['start_date']
        ];
        $_SESSION['current_step'] = 4;
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['step4'])) {
        // Store terms acceptance
        $_SESSION['terms_accepted'] = isset($_POST['accept_terms']);
        $_SESSION['current_step'] = 5;
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['prev_step'])) {
        // Go back to previous step
        $_SESSION['current_step'] = max($current_step - 1, 1);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['complete'])) {
        // Process all data and save to database
        $conn->begin_transaction();
        
        try {
            // 1. Save guardian
            $guardianStmt = $conn->prepare("INSERT INTO guardians 
                                         (last_name, first_name, middle_name, mobile_no, relationship) 
                                         VALUES (?, ?, ?, ?, ?)");
            
            $g_last_name = $_SESSION['guardian_data']['last_name'];
            $g_first_name = $_SESSION['guardian_data']['first_name'];
            $g_middle_name = $_SESSION['guardian_data']['middle_name'];
            $g_mobile_no = $_SESSION['guardian_data']['mobile_no'];
            $g_relationship = $_SESSION['guardian_data']['relationship'];
            
            $guardianStmt->bind_param('sssss', 
                $g_last_name,
                $g_first_name,
                $g_middle_name,
                $g_mobile_no,
                $g_relationship
            );
            $guardianStmt->execute();
            $guardianId = $conn->insert_id;
            $guardianStmt->close();

            // 2. Save tenant
            $isStudent = $_SESSION['tenant_data']['is_student'] == 1;
            $academicYearId = $isStudent ? $_SESSION['tenant_data']['academic_year_id'] : null;
            $courseId = $isStudent ? $_SESSION['tenant_data']['course_id'] : null;
            $studentId = $isStudent ? $_SESSION['tenant_data']['student_id'] : null;
            $yearLevel = $_SESSION['tenant_data']['year_level'];
            
            $t_first_name = $_SESSION['tenant_data']['first_name'];
            $t_last_name = $_SESSION['tenant_data']['last_name'];
            $t_middle_name = $_SESSION['tenant_data']['middle_name'];
            $t_birthdate = $_SESSION['tenant_data']['birthdate'];
            $t_address = $_SESSION['tenant_data']['address'];
            $t_gender = $_SESSION['tenant_data']['gender'];
            $t_mobile_no = $_SESSION['tenant_data']['mobile_no'];
            $t_is_student = $_SESSION['tenant_data']['is_student'];
            $t_tenant_type = $_SESSION['tenant_data']['tenant_type'];

            // 3. Create user account
            $defaultUsername = generateUsername($t_first_name, $t_last_name);
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            
            $userStmt = $conn->prepare("INSERT INTO users 
                                      (username, password, role) 
                                      VALUES (?, ?, 'Tenant')");
            $userStmt->bind_param('ss', $defaultUsername, $hashedPassword);
            $userStmt->execute();
            $userId = $conn->insert_id;
            $userStmt->close();

            $tenantStmt = $conn->prepare("INSERT INTO tenants 
                                       (academic_year_id, first_name, last_name, middle_name, 
                                        birthdate, address, gender, mobile_no, guardian_id, 
                                        course_id, year_level, is_student, student_id, tenant_type, user_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $tenantStmt->bind_param('isssssssiisissi', 
                $academicYearId,
                $t_first_name,
                $t_last_name,
                $t_middle_name,
                $t_birthdate,
                $t_address,
                $t_gender,
                $t_mobile_no,
                $guardianId,
                $courseId,
                $yearLevel,
                $t_is_student,
                $studentId,
                $t_tenant_type,
                $userId
            );
            $tenantStmt->execute();
            $tenantId = $conn->insert_id;
            $tenantStmt->close();
            
            // 4. Assign bed
            $bed_id = $_SESSION['bed_data']['bed_id'];
            $start_date = $_SESSION['bed_data']['start_date'];

            // Calculate due date based on today's date
            $due_date = getSemesterDueDate($start_date);

            // First verify bed is still available
            $checkBed = $conn->query("SELECT status, monthly_rent FROM beds WHERE bed_id = $bed_id")->fetch_assoc();
            if (!$checkBed || $checkBed['status'] !== 'Vacant') {
                throw new Exception("The selected bed is no longer available");
            }

            // Create boarding record with due date
            $boardingStmt = $conn->prepare("INSERT INTO boarding 
                                        (tenant_id, bed_id, start_date, due_date) 
                                        VALUES (?, ?, ?, ?)");
            $boardingStmt->bind_param('iiss', 
                $tenantId,
                $bed_id,
                $start_date,
                $due_date
            );
            $boardingStmt->execute();
            $boardingId = $conn->insert_id;
            $boardingStmt->close();
                        
            // 5. Update bed status
            $bedStmt = $conn->prepare("UPDATE beds 
                                      SET status = 'Occupied'
                                      WHERE bed_id = ? 
                                      AND status = 'Vacant'");
            $bedStmt->bind_param('i', $bed_id);
            $bedStmt->execute();
            
            if ($conn->affected_rows === 0) {
                throw new Exception("Failed to update bed status - it may have been already occupied");
            }
            $bedStmt->close();
            
            // 6. Process payment
            $base_rent = $checkBed['monthly_rent'] ?? 1100;
            $appliance_total = $_POST['appliance_total'] ?? 0;
            $total_amount_due = $base_rent + $appliance_total;
            $amount_tendered = !empty($_POST['amount_tendered']) ? floatval($_POST['amount_tendered']) : $total_amount_due;
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $appliances = isset($_POST['appliances']) ? implode(', ', $_POST['appliances']) : 'None';

            // Validate amount tendered
            if ($amount_tendered < $total_amount_due) {
                throw new Exception("Amount tendered (₱" . number_format($amount_tendered, 2) . ") is less than the total amount due (₱" . number_format($total_amount_due, 2) . ")");
            }

            $paymentStmt = $conn->prepare("INSERT INTO payments 
                                        (user_id, boarding_id, payment_amount, appliance_charges, appliances, payment_date, method) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
            $payment_date = date('Y-m-d');
            $paymentStmt->bind_param('iidssss', 
                $userId,
                $boardingId,
                $amount_tendered,
                $appliance_total,
                $appliances,
                $payment_date,
                $payment_method
            );
            $paymentStmt->execute();
            $paymentId = $conn->insert_id;
            $paymentStmt->close();
            
            $conn->commit();
            
            // Store credentials for display
            $_SESSION['new_tenant_credentials'] = [
                'name' => "$t_first_name $t_last_name",
                'username' => $defaultUsername,
                'password' => $defaultPassword
            ];

            $_SESSION['new_tenant_ids'] = [
                'tenant_id' => $tenantId,
                'payment_id' => $paymentId,
            ];

            // Clear temporary files
            if (!empty($_SESSION['tenant_data']['profile_picture']) && 
                strpos($_SESSION['tenant_data']['profile_picture'], 'temp_') === 0) {
                $tempFile = '../Uploads/profiles/' . $_SESSION['tenant_data']['profile_picture'];
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            // Clear session data
            unset($_SESSION['tenant_data']);
            unset($_SESSION['guardian_data']);
            unset($_SESSION['bed_data']);
            unset($_SESSION['terms_accepted']);
            unset($_SESSION['current_step']);

            header("Location: add-tenant.php?success=1");
            exit();

        } catch (Exception $e) {
            if (!empty($_SESSION['tenant_data']['profile_picture']) && 
                strpos($_SESSION['tenant_data']['profile_picture'], 'temp_') === 0) {
                $tempFile = '../Uploads/profiles/' . $_SESSION['tenant_data']['profile_picture'];
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            $conn->rollback();
            $error = "Error saving tenant: " . $e->getMessage();
        }
    }
}

// Fetch all floors, rooms, and beds (including occupied ones)
if ($current_step >= 3) {
    // Get all floors
    $floors = $conn->query("SELECT * FROM floors ORDER BY floor_no")->fetch_all(MYSQLI_ASSOC);
    $bedOptions = [];

    foreach ($floors as $floor) {
        $floorNo = $floor['floor_no'];
        $bedOptions[$floorNo] = [];
        
        // Get all rooms for this floor
        $rooms = $conn->query("
            SELECT r.* 
            FROM rooms r
            WHERE r.floor_id = {$floor['floor_id']}
            ORDER BY r.room_no
        ")->fetch_all(MYSQLI_ASSOC);
        
        foreach ($rooms as $room) {
            $roomNo = $room['room_no'];
            $bedOptions[$floorNo][$roomNo] = [];
            
            // Get ALL beds for this room
            $beds = $conn->query("
                SELECT b.* 
                FROM beds b
                WHERE b.room_id = {$room['room_id']}
                ORDER BY b.bed_no
            ")->fetch_all(MYSQLI_ASSOC);
            
            foreach ($beds as $bed) {
                // Add floor and room info to each bed
                $bed['floor_no'] = $floorNo;
                $bed['room_no'] = $roomNo;
                $bedOptions[$floorNo][$roomNo][] = $bed;
            }
        }
    }
}

// Fetch selected bed details for step 5
$selected_bed = null;
if ($current_step == 5 && isset($_SESSION['bed_data']['bed_id'])) {
    $bed_id = $_SESSION['bed_data']['bed_id'];
    $result = $conn->query("SELECT * FROM beds WHERE bed_id = $bed_id");
    $selected_bed = $result->fetch_assoc();
}

// Check for success parameter
$showSuccessModal = isset($_GET['success']) && $_GET['success'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Tenant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --danger-color: #ef233c;
            --border-radius: 0.375rem;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            background-color: white;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 1.25rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            padding: 0.5rem 0.75rem;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.25rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-outline-secondary {
            border-color: #e0e0e0;
            color: var(--dark-color);
        }
        
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .step-progress::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            color: white;
        }
        
        .step.active .step-number {
            background-color: var(--primary-color);
        }
        
        .step.completed .step-number {
            background-color: var(--success-color);
        }
        
        .step-title {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .step.active .step-title {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .step.completed .step-title {
            color: var(--success-color);
        }
        
        .terms-container {
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .bed-card {
            transition: all 0.2s;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .bed-card.vacant {
            border: 1px solid var(--success-color);
            cursor: pointer;
        }
        
        .bed-card.vacant:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        
        .bed-card.occupied {
            border: 1px solid var(--danger-color);
            opacity: 0.7;
        }
        
        .bed-card.selected {
            border: 2px solid var(--primary-color);
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .modal-content {
            border: none;
            border-radius: var(--border-radius);
        }
        
        .alert {
            border-radius: var(--border-radius);
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .student-fields {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .credentials-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .credentials-box h5 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .credentials-box p {
            margin-bottom: 0.5rem;
        }
        
        .credentials-box .alert {
            margin-top: 1rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-semibold">Add New Tenant</h2>
            <a href="tenant.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Step Progress -->
        <div class="step-progress">
            <div class="step <?= $current_step >= 1 ? 'completed' : '' ?> <?= $current_step == 1 ? 'active' : '' ?>">
                <div class="step-number">1</div>
                <div class="step-title">Personal</div>
            </div>
            <div class="step <?= $current_step >= 2 ? 'completed' : '' ?> <?= $current_step == 2 ? 'active' : '' ?>">
                <div class="step-number">2</div>
                <div class="step-title">Guardian</div>
            </div>
            <div class="step <?= $current_step >= 3 ? 'completed' : '' ?> <?= $current_step == 3 ? 'active' : '' ?>">
                <div class="step-number">3</div>
                <div class="step-title">Bed</div>
            </div>
            <div class="step <?= $current_step >= 4 ? 'completed' : '' ?> <?= $current_step == 4 ? 'active' : '' ?>">
                <div class="step-number">4</div>
                <div class="step-title">Contract</div>
            </div>
            <div class="step <?= $current_step >= 5 ? 'completed' : '' ?> <?= $current_step == 5 ? 'active' : '' ?>">
                <div class="step-number">5</div>
                <div class="step-title">Payment</div>
            </div>
        </div>

        <div class="card">
            <form method="POST" id="tenantForm" class="needs-validation" novalidate enctype="multipart/form-data">
                <input type="hidden" name="current_step" value="<?= $current_step ?>">

                <!-- Step 1: Personal Information -->
                <?php if ($current_step == 1): ?>
                <div class="card-header">Personal Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" required
                                    value="<?= htmlspecialchars($_SESSION['tenant_data']['first_name'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control"
                                    value="<?= htmlspecialchars($_SESSION['tenant_data']['middle_name'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required
                                    value="<?= htmlspecialchars($_SESSION['tenant_data']['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Birthdate *</label>
                                <input type="date" name="birthdate" class="form-control" required
                                    value="<?= htmlspecialchars($_SESSION['tenant_data']['birthdate'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select" required>
                                    <option value="M" <?= ($_SESSION['tenant_data']['gender'] ?? '') == 'M' ? 'selected' : '' ?>>Male</option>
                                    <option value="F" <?= ($_SESSION['tenant_data']['gender'] ?? '') == 'F' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mobile Number *</label>
                                <input type="text" name="mobile_no" class="form-control" required
                                    value="<?= htmlspecialchars($_SESSION['tenant_data']['mobile_no'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($_SESSION['tenant_data']['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select name="is_student" id="isStudentSelect" class="form-select" required>
                            <option value="1" <?= ($_SESSION['tenant_data']['is_student'] ?? 1) == 1 ? 'selected' : '' ?>>Student</option>
                            <option value="0" <?= ($_SESSION['tenant_data']['is_student'] ?? 0) == 0 ? 'selected' : '' ?>>Non-Student</option>
                        </select>
                    </div>
                    
                    <div class="student-fields" id="studentFields">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Student ID *</label>
                                    <input type="text" name="student_id" class="form-control" id="studentIdField"
                                        value="<?= htmlspecialchars($_SESSION['tenant_data']['student_id'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Course *</label>
                                    <select name="course_id" class="form-select" id="courseSelect" required>
                                        <option value="">Select Course</option>
                                        <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['course_id'] ?>" 
                                            <?= ($_SESSION['tenant_data']['course_id'] ?? '') == $course['course_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_description']) . " - " . htmlspecialchars($course['major']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Year Level *</label>
                                    <select name="year_level" class="form-select" id="yearLevelSelect" required>
                                        <option value="1" <?= ($_SESSION['tenant_data']['year_level'] ?? '1') == '1' ? 'selected' : '' ?>>1st Year</option>
                                        <option value="2" <?= ($_SESSION['tenant_data']['year_level'] ?? '') == '2' ? 'selected' : '' ?>>2nd Year</option>
                                        <option value="3" <?= ($_SESSION['tenant_data']['year_level'] ?? '') == '3' ? 'selected' : '' ?>>3rd Year</option>
                                        <option value="4" <?= ($_SESSION['tenant_data']['year_level'] ?? '') == '4' ? 'selected' : '' ?>>4th Year</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Academic Year *</label>
                                    <select name="academic_year" class="form-select" id="academicYearSelect" required>
                                        <option value="">Select Academic Year</option>
                                        <?php 
                                        $uniqueYears = [];
                                        foreach ($academicYears as $year) {
                                            $yearRange = $year['start_year'] . '-' . $year['end_year'];
                                            if (!in_array($yearRange, $uniqueYears)) {
                                                $uniqueYears[] = $yearRange;
                                                echo '<option value="' . $yearRange . '"';
                                                if (isset($_SESSION['tenant_data']['academic_year'])) {
                                                    echo ($_SESSION['tenant_data']['academic_year'] == $yearRange) ? ' selected' : '';
                                                }
                                                echo '>' . $yearRange . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Semester *</label>
                                    <select name="semester" class="form-select" id="semesterSelect" required>
                                        <option value="">Select Semester</option>
                                        <?php 
                                        $uniqueSemesters = [];
                                        foreach ($academicYears as $year) {
                                            if (!in_array($year['semester'], $uniqueSemesters)) {
                                                $uniqueSemesters[] = $year['semester'];
                                                echo '<option value="' . htmlspecialchars($year['semester']) . '"';
                                                if (isset($_SESSION['tenant_data']['semester'])) {
                                                    echo ($_SESSION['tenant_data']['semester'] == $year['semester']) ? ' selected' : '';
                                                }
                                                echo '>' . htmlspecialchars($year['semester']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="submit" name="step1" class="btn btn-primary">
                            Next <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Step 2: Guardian Information -->
                <?php if ($current_step == 2): ?>
                <div class="card-header">Guardian Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="guardian_first_name" class="form-control" required
                                    value="<?= $_SESSION['guardian_data']['first_name'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="guardian_middle_name" class="form-control"
                                    value="<?= $_SESSION['guardian_data']['middle_name'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="guardian_last_name" class="form-control" required
                                    value="<?= $_SESSION['guardian_data']['last_name'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mobile Number *</label>
                                <input type="text" name="guardian_mobile" class="form-control" required
                                    value="<?= $_SESSION['guardian_data']['mobile_no'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Relationship *</label>
                                <input type="text" name="guardian_relationship" class="form-control" required
                                    value="<?= $_SESSION['guardian_data']['relationship'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="submit" name="prev_step" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                        <button type="submit" name="step2" class="btn btn-primary">
                            Next <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Step 3: Bed Assignment -->
                <?php if ($current_step == 3): ?>
                <div class="card-header">Bed Assignment</div>
                <div class="card-body">
                    <?php if (empty($floors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle"></i> No Floors Available</h5>
                        <p>There are currently no floors in the system.</p>
                        <a href="manage-floors.php" class="btn btn-warning">
                            <i class="bi bi-plus-circle"></i> Manage Floors
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control w-auto" required 
                               value="<?= $_SESSION['bed_data']['start_date'] ?? date('Y-m-d') ?>">
                    </div>
                    
                    <div class="bed-selection-container">
                        <div class="mb-3">
                            <label class="form-label">Select Floor:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($bedOptions as $floorNo => $rooms): ?>
                                <button type="button" class="btn btn-outline-secondary floor-btn" data-floor="<?= $floorNo ?>">
                                    Floor <?= $floorNo ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="roomSelection" style="display: none;">
                            <label class="form-label">Select Room:</label>
                            <div class="d-flex flex-wrap gap-2" id="roomButtons">
                                <!-- Room buttons will be inserted here by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="mb-3" id="bedSelection" style="display: none;">
                            <label class="form-label">Select Bed:</label>
                            <div class="row g-3" id="bedCards">
                                <!-- Bed cards will be inserted here by JavaScript -->
                            </div>
                        </div>
                        
                        <div id="selectedBedInfo" class="alert alert-info mt-3" style="display: none;">
                            <strong>Selected Bed:</strong> 
                            <span id="selectedBedText"></span>
                            <input type="hidden" name="bed_id" id="bedIdInput" required>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons mt-4">
                        <button type="submit" name="prev_step" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                        <button type="submit" name="step3" class="btn btn-primary">
                            Next <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Step 4: Terms and Conditions -->
                <?php if ($current_step == 4): ?>
                <div class="card-header">Terms and Conditions</div>
                <div class="card-body">
                    <div class="terms-container">
                        <h5 class="mb-3">CONTRACT OF TENANCY</h5>
                        <h6>Rent and Payment Schedule</h6>
                        <ul>
                            <li>The monthly rental rate is <strong>₱1,100.00</strong></li>
                            <li>The Tenant shall remit <strong>₱1,100.00</strong> upon occupancy</li>
                            <li>Monthly payments must be made <strong>on or before the first week of each month</strong></li>
                        </ul>
                        
                        <h6>Late Payment Penalties</h6>
                        <ul>
                            <li>A <strong>grace period of two (2) weeks</strong> is allowed</li>
                            <li>Beyond grace period: <strong>₱50.00 per week</strong> penalty</li>
                        </ul>
                        
                        <h6>Minimum Stay Commitment</h6>
                        <ul>
                            <li><strong>Five (5) months</strong> during regular semesters</li>
                            <li><strong>Two (2) months</strong> during summer</li>
                            <li>Early termination: <strong>₱3,000.00 charge</strong></li>
                        </ul>
                        
                        <h6>Utilities and Appliances</h6>
                        <ul>
                            <li>Basic utilities included</li>
                            <li>Excess usage: <strong>₱100.00 surcharge</strong></li>
                            <li>Appliances: <strong>₱100.00 per unit</strong> additional charge</li>
                        </ul>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="accept_terms" id="accept_terms" required>
                        <label class="form-check-label" for="accept_terms">
                            I agree to the terms and conditions *
                        </label>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="submit" name="prev_step" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                        <button type="submit" name="step4" class="btn btn-primary">
                            Next <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Step 5: Payment -->
                <?php if ($current_step == 5): ?>
                <div class="card-header">Payment Information</div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">Payment Summary</div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Monthly Rent:</span>
                                        <span>₱<?= number_format($selected_bed['monthly_rent'] ?? 1100, 2) ?></span>
                                    </div>
                                    
                                    <!-- Appliance Charges Section -->
                                    <div class="mb-3">
                                        <label class="form-label">Additional Appliances (₱100.00 each):</label>
                                        <div class="appliance-list">
                                            <div class="form-check">
                                                <input class="form-check-input appliance-check" type="checkbox" 
                                                    name="appliances[]" value="Rice Cooker" id="riceCooker">
                                                <label class="form-check-label" for="riceCooker">
                                                    Rice Cooker (+₱100.00)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input appliance-check" type="checkbox" 
                                                    name="appliances[]" value="Electric Fan" id="electricFan">
                                                <label class="form-check-label" for="electricFan">
                                                    Electric Fan (+₱100.00)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input appliance-check" type="checkbox" 
                                                    name="appliances[]" value="Laptop" id="laptop">
                                                <label class="form-check-label" for="laptop">
                                                    Laptop (+₱100.00)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input appliance-check" type="checkbox" 
                                                    name="appliances[]" value="Water Heater" id="waterHeater">
                                                <label class="form-check-label" for="waterHeater">
                                                    Water Heater (+₱100.00)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input appliance-check" type="checkbox" 
                                                    name="appliances[]" value="Flat Iron" id="flatIron">
                                                <label class="form-check-label" for="flatIron">
                                                    Flat Iron (+₱100.00)
                                                </label>
                                            </div>
                                        </div>
                                        <input type="hidden" name="appliance_total" id="applianceTotal" value="0">
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Appliance Charges:</span>
                                        <span id="applianceCharges">₱0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold">
                                        <span>Total Amount Due:</span>
                                        <span id="totalAmount">₱<?= number_format($selected_bed['monthly_rent'] ?? 1100, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">Payment Details</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Payment Method *</label>
                                        <div class="d-flex flex-column gap-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" 
                                                    id="cash" value="Cash" checked>
                                                <label class="form-check-label" for="cash">
                                                    Cash
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" 
                                                    id="gcash" value="GCash">
                                                <label class="form-check-label" for="gcash">
                                                    GCash
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" 
                                                    id="bank_transfer" value="Bank Transfer">
                                                <label class="form-check-label" for="bank_transfer">
                                                    Bank Transfer
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Amount Tendered *</label>
                                        <input type="number" step="0.01" name="amount_tendered" 
                                            class="form-control" required
                                            id="amountTendered"
                                            placeholder="Enter amount...">
                                        <small class="text-primary">Leave this field empty to automatically use the total amount due.</small>

                                        <div class="invalid-feedback">
                                            Amount tendered must be at greater or equal to <strong>total amount due</strong>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons mt-4">
                        <button type="submit" name="prev_step" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                        <button type="submit" name="complete" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Complete Registration
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Appliance charges calculation
    document.querySelectorAll('.appliance-check').forEach(checkbox => {
        checkbox.addEventListener('change', updateCharges);
    });

    function updateCharges() {
        const baseRent = <?= $selected_bed['monthly_rent'] ?? 1100 ?>;
        const appliancePrice = 100; // Each appliance costs ₱100
        const checkedAppliances = document.querySelectorAll('.appliance-check:checked');
        const applianceCount = checkedAppliances.length;
        const applianceTotal = applianceCount * appliancePrice;
        const totalAmount = baseRent + applianceTotal;
        
        // Update display
        document.getElementById('applianceCharges').textContent = `₱${applianceTotal.toFixed(2)}`;
        document.getElementById('totalAmount').textContent = `₱${totalAmount.toFixed(2)}`;
        
        // Update hidden field for form submission
        document.getElementById('applianceTotal').value = applianceTotal;
        
        // Update minimum payment amount and placeholder
        const amountTenderedInput = document.getElementById('amountTendered');
        amountTenderedInput.min = totalAmount;
        document.getElementById('placeholderTotal').textContent = totalAmount.toFixed(2);
        document.getElementById('minAmount').textContent = totalAmount.toFixed(2);
    }

    // Handle student/non-student toggle
    const isStudentSelect = document.getElementById('isStudentSelect');
    const studentFields = document.getElementById('studentFields');
    
    if (isStudentSelect && studentFields) {
        function toggleStudentFields() {
            const isStudent = isStudentSelect.value === '1';
            studentFields.style.display = isStudent ? 'block' : 'none';
            
            // Toggle required attribute for student fields
            document.querySelectorAll('#studentFields select, #studentFields input').forEach(function(field) {
                field.required = isStudent;
            });
        }
        
        // Initialize and add event listener
        toggleStudentFields();
        isStudentSelect.addEventListener('change', toggleStudentFields);
    }

    <?php if ($current_step == 3 && !empty($floors)): ?>
    // Bed selection functionality
    const bedOptions = <?= json_encode($bedOptions) ?>;
    let selectedFloor = null;
    let selectedRoom = null;
    let selectedBed = null;

    // Floor selection
    document.querySelectorAll('.floor-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            selectedFloor = this.dataset.floor;
            selectedRoom = null;
            selectedBed = null;
            
            // Update UI
            document.querySelectorAll('.floor-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Show rooms for this floor
            const roomButtonsContainer = document.getElementById('roomButtons');
            roomButtonsContainer.innerHTML = '';
            
            const rooms = bedOptions[selectedFloor];
            
            for (const roomNo in rooms) {
                const roomBtn = document.createElement('button');
                roomBtn.type = 'button';
                roomBtn.className = 'btn btn-outline-secondary room-btn';
                roomBtn.dataset.room = roomNo;
                roomBtn.textContent = `Room ${roomNo}`;
                roomBtn.addEventListener('click', () => selectRoom(roomNo));
                roomButtonsContainer.appendChild(roomBtn);
            }
            
            // Show room selection section
            document.getElementById('roomSelection').style.display = 'block';
            document.getElementById('bedSelection').style.display = 'none';
            document.getElementById('selectedBedInfo').style.display = 'none';
        });
    });

    // Room selection
    function selectRoom(roomNo) {
        selectedRoom = roomNo;
        selectedBed = null;
        
        // Update UI
        document.querySelectorAll('.room-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`.room-btn[data-room="${roomNo}"]`).classList.add('active');
        
        // Show beds for this room
        const bedCardsContainer = document.getElementById('bedCards');
        bedCardsContainer.innerHTML = '';
        
        const beds = bedOptions[selectedFloor][selectedRoom];
        
        if (beds?.length > 0) {
            beds.forEach(bed => {
                const isVacant = bed.status === 'Vacant';
                const statusClass = isVacant ? 'vacant' : 'occupied';
                const statusBadge = isVacant ? 'bg-success' : 'bg-danger';
                
                const bedCard = document.createElement('div');
                bedCard.className = 'col-md-4 mb-3';
                bedCard.innerHTML = `
                    <div class="card bed-card ${statusClass}" 
                        data-bed-id="${bed.bed_id}"
                        data-status="${bed.status}">
                        <div class="card-body">
                            <h5 class="card-title">Bed ${bed.bed_no}</h5>
                            <p class="card-text">
                                Floor ${bed.floor_no}, Room ${bed.room_no}<br>
                                Deck: ${bed.deck}<br>
                                Status: <span class="badge ${statusBadge}">${bed.status}</span><br>
                                Rent: ₱${parseFloat(bed.monthly_rent).toFixed(2)}
                            </p>
                        </div>
                    </div>
                `;
                
                // Only make vacant beds clickable
                if (isVacant) {
                    bedCard.querySelector('.bed-card').addEventListener('click', () => selectBed(bed.bed_id));
                }
                
                bedCardsContainer.appendChild(bedCard);
            });
        } else {
            bedCardsContainer.innerHTML = '<div class="col-12"><div class="alert alert-warning">No beds in this room</div></div>';
        }
        
        document.getElementById('bedSelection').style.display = 'block';
        document.getElementById('selectedBedInfo').style.display = 'none';
    }

    // Bed selection
    function selectBed(bedId) {
        selectedBed = bedId;
        
        // Find the bed data
        const beds = bedOptions[selectedFloor][selectedRoom];
        const bedData = beds.find(b => b.bed_id == bedId);
        
        if (!bedData) return;
        
        // Update UI
        document.querySelectorAll('.bed-card').forEach(card => {
            card.classList.remove('selected');
            card.style.border = '';
        });
        
        const selectedCard = document.querySelector(`.bed-card[data-bed-id="${bedId}"]`);
        selectedCard.classList.add('selected');
        selectedCard.style.border = '2px solid var(--primary-color)';
        
        // Update hidden input and display
        document.getElementById('bedIdInput').value = bedId;
        document.getElementById('selectedBedText').textContent = 
            `Bed ${bedData.bed_no} (Floor ${bedData.floor_no}, Room ${bedData.room_no}) - ₱${parseFloat(bedData.monthly_rent).toFixed(2)}`;
        document.getElementById('selectedBedInfo').style.display = 'block';
    }
    <?php endif; ?>

    // Form submission handling
    const form = document.getElementById('tenantForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitter = e.submitter;
            
            if (!submitter) {
                e.preventDefault();
                return false;
            }
            
            // Handle step5 (payment) validation
            if (submitter.name === 'complete') {
                const amountTenderedInput = document.getElementById('amountTendered');
                const baseRent = <?= $selected_bed['monthly_rent'] ?? 1100 ?>;
                const applianceTotal = parseFloat(document.getElementById('applianceTotal').value) || 0;
                const totalAmountDue = baseRent + applianceTotal;
                const amountTendered = amountTenderedInput.value ? parseFloat(amountTenderedInput.value) : totalAmountDue;

                if (amountTendered < totalAmountDue) {
                    e.preventDefault();
                    amountTenderedInput.classList.add('is-invalid');
                    alert(`Amount tendered (₱${amountTendered.toFixed(2)}) must be at least ₱${totalAmountDue.toFixed(2)}`);
                    return false;
                } else {
                    amountTenderedInput.classList.remove('is-invalid');
                    // If amount is empty, set it to total amount due
                    if (!amountTenderedInput.value) {
                        amountTenderedInput.value = totalAmountDue;
                    }
                }
            }
            
            // Handle step3 (bed selection) validation
            if (submitter.name === 'step3') {
                <?php if ($current_step == 3): ?>
                if (!selectedBed) {
                    e.preventDefault();
                    alert('Please select a bed before proceeding');
                    return false;
                }
                
                const selectedBedElement = document.querySelector(`.bed-card[data-bed-id="${selectedBed}"]`);
                if (selectedBedElement?.dataset.status === 'Occupied') {
                    e.preventDefault();
                    alert('Cannot select an occupied bed. Please choose a vacant bed.');
                    return false;
                }
                <?php endif; ?>
            }
            
            // Only allow navigation buttons to submit
            if (submitter.name !== 'prev_step' && 
                !submitter.name.startsWith('step') && 
                submitter.name !== 'complete') {
                e.preventDefault();
                return false;
            }
        });
    }

    <?php if (isset($showSuccessModal) && $showSuccessModal): ?>
    // Success modal handling
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
    
    // Prevent closing by clicking backdrop or pressing escape
    successModal._element.addEventListener('hide.bs.modal', function(event) {
        event.preventDefault();
        return false;
    });
    <?php endif; ?>
});
</script>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel">Registration Successful!</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($_SESSION['new_tenant_credentials'])): ?>
                <div class="credentials-box">
                    <h5>Tenant Credentials</h5>
                    <p><strong>Name:</strong> <?= htmlspecialchars($_SESSION['new_tenant_credentials']['name']) ?></p>
                    <p><strong>Username:</strong> <?= htmlspecialchars($_SESSION['new_tenant_credentials']['username']) ?></p>
                    <p><strong>Password:</strong> <?= htmlspecialchars($_SESSION['new_tenant_credentials']['password']) ?></p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Please note these credentials as they won't be shown again.
                    </div>
                </div>
                <?php 
                unset($_SESSION['new_tenant_credentials']);
                endif; ?>
                
                <p>What would you like to do next?</p>
                <div class="d-flex flex-column gap-2">
                    <a href="print-contract.php?tenant_id=<?= $_SESSION['new_tenant_ids']['tenant_id'] ?? '' ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-file-earmark-text"></i> Print Contract
                    </a>
                    <a href="print-receipt.php?payment_id=<?= $_SESSION['new_tenant_ids']['payment_id'] ?? '' ?>" class="btn btn-outline-success" target="_blank">
                        <i class="bi bi-receipt"></i> Print Receipt
                    </a>
                    <a href="tenant.php" class="btn btn-primary">
                        <i class="bi bi-house-door"></i> Back to Tenant Management
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php 
$conn->close();
?>