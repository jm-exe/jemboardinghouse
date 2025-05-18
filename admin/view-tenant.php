<?php
session_start();
require_once '../connection/db.php';

// 1. Validate Tenant ID
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    $_SESSION['error_message'] = "No tenant ID provided";
    header("Location: tenant.php");
    exit();
}

$tenantId = (int)$_GET['id'];
if ($tenantId <= 0) {
    $_SESSION['error_message'] = "Invalid tenant ID format";
    header("Location: tenant.php");
    exit();
}

// 2. Fetch Tenant Data with Error Handling
try {
    // Base query with joins that apply to all tenants
    $query = "SELECT t.*, 
              g.first_name AS guardian_first_name, g.last_name AS guardian_last_name, 
              g.middle_name AS guardian_middle_name, g.mobile_no AS guardian_mobile, g.relationship,
              a.start_year, a.end_year, a.semester,
              b.bed_id, b.bed_no, b.deck, b.monthly_rent, b.status AS bed_status,
              r.room_no, f.floor_no,
              bo.start_date, bo.due_date,
              u.username
              FROM tenants t
              LEFT JOIN guardians g ON t.guardian_id = g.guardian_id
              LEFT JOIN academic_years a ON t.academic_year_id = a.academic_year_id
              LEFT JOIN boarding bo ON t.tenant_id = bo.tenant_id
              LEFT JOIN beds b ON bo.bed_id = b.bed_id
              LEFT JOIN rooms r ON b.room_id = r.room_id
              LEFT JOIN floors f ON r.floor_id = f.floor_id
              LEFT JOIN users u ON t.user_id = u.user_id
              WHERE t.tenant_id = ?";

    // For students only, join the course table
    if (isset($_GET['type']) && $_GET['type'] === 'Student') {
        $query = str_replace('FROM tenants t', 'FROM tenants t JOIN course c ON t.course_id = c.course_id', $query);
        $query = str_replace('SELECT t.*,', 'SELECT t.*, c.course_code, c.course_description, c.major,', $query);
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param('i', $tenantId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to fetch tenant data");
    }

    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    $stmt->close();

    if (!$tenant) {
        $_SESSION['error_message'] = "Tenant not found (ID: $tenantId)";
        header("Location: tenant.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: tenant.php");
    exit();
}

// 3. Handle Session Messages
$messages = [];
if (isset($_SESSION['success_message'])) {
    $messages['success'] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $messages['error'] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tenant - <?= htmlspecialchars($tenant['last_name'] . ', ' . $tenant['first_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        .profile-header { background-color: #f8f9fa; border-radius: 0.375rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .tenant-photo {
            width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
            background-color: #6c757d; color: white; display: flex;
            align-items: center; justify-content: center; font-size: 3rem; font-weight: bold;
            border: 4px solid #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .tenant-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .detail-card { margin-bottom: 1.5rem; }
        .detail-card .card-header { font-weight: 600; }
        .badge-occupied { background-color: #dc3545; color: white; }
        .badge-vacant { background-color: #28a745; color: white; }
        .current-assignment { background-color: #f8f9fa; border-left: 4px solid #0d6efd; }
        .credentials-alert { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .photo-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            transform: translate(-15%, -15%);
        }
        .student-badge { background-color: #007bff; color: white; }
        .non-student-badge { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid mt-4">
        <!-- System Messages -->
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $type => $message): ?>
                <div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-person-lines-fill"></i> Tenant Details</h2>
            <div>
                <a href="edit-tenant.php?id=<?= $tenantId ?>&type=<?= urlencode($tenant['tenant_type']) ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="tenant.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Profile Summary -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center position-relative">
                    <div class="tenant-photo mx-auto">
                        <?php if (!empty($tenant['profile_picture'])): ?>
                            <img src="../uploads/profiles/<?= htmlspecialchars($tenant['profile_picture']) ?>" 
                                 alt="<?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?>">
                        <?php else: ?>
                            <?= strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary photo-upload-btn" 
                            data-bs-toggle="modal" data-bs-target="#photoModal">
                        <i class="bi bi-camera"></i>
                    </button>
                </div>
                <div class="col-md-10">
                    <h3><?= htmlspecialchars($tenant['last_name'] . ', ' . $tenant['first_name'] . ' ' . ($tenant['middle_name'] ?? '')) ?></h3>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Type:</strong> 
                                <span class="badge <?= $tenant['tenant_type'] === 'Student' ? 'student-badge' : 'non-student-badge' ?>">
                                    <?= $tenant['tenant_type'] ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Academic Year:</strong> 
                                <?= $tenant['start_year'] ?? 'N/A' ?>-<?= $tenant['end_year'] ?? 'N/A' ?> 
                                <?= isset($tenant['semester']) ? '(' . $tenant['semester'] . ')' : '' ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Status:</strong> 
                                <span class="badge <?= ($tenant['bed_status'] ?? '') === 'Occupied' ? 'badge-occupied' : 'badge-secondary' ?>">
                                    <?= $tenant['bed_status'] ?? 'Not Assigned' ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="card detail-card">
            <div class="card-header">
                <i class="bi bi-person-vcard"></i> Personal Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>First Name:</strong> <?= htmlspecialchars($tenant['first_name']) ?></p>
                        <p><strong>Middle Name:</strong> <?= htmlspecialchars($tenant['middle_name'] ?? 'N/A') ?></p>
                        <p><strong>Last Name:</strong> <?= htmlspecialchars($tenant['last_name']) ?></p>
                        <p><strong>Birthdate:</strong> <?= date('F j, Y', strtotime($tenant['birthdate'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Gender:</strong> <?= $tenant['gender'] === 'M' ? 'Male' : 'Female' ?></p>
                        <p><strong>Mobile Number:</strong> <?= htmlspecialchars($tenant['mobile_no']) ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($tenant['address']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Information (Students Only) -->
        <?php if ($tenant['tenant_type'] === 'Student'): ?>
        <div class="card detail-card">
            <div class="card-header">
                <i class="bi bi-book"></i> Academic Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Student ID:</strong> <?= htmlspecialchars($tenant['student_id'] ?? 'N/A') ?></p>
                        <p><strong>Course:</strong> <?= htmlspecialchars($tenant['course_description'] ?? 'N/A') ?></p>
                        <p><strong>Course Code:</strong> <?= htmlspecialchars($tenant['course_code'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Major:</strong> <?= htmlspecialchars($tenant['major'] ?? 'N/A') ?></p>
                        <p><strong>Academic Year:</strong> <?= $tenant['start_year'] ?>-<?= $tenant['end_year'] ?> (<?= $tenant['semester'] ?>)</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Guardian Information -->
        <?php if (!empty($tenant['guardian_first_name'])): ?>
        <div class="card detail-card">
            <div class="card-header">
                <i class="bi bi-shield"></i> Guardian Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?= htmlspecialchars($tenant['guardian_last_name'] . ', ' . $tenant['guardian_first_name'] . ' ' . ($tenant['guardian_middle_name'] ?? '')) ?></p>
                        <p><strong>Relationship:</strong> <?= htmlspecialchars($tenant['relationship'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Mobile Number:</strong> <?= htmlspecialchars($tenant['guardian_mobile'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Accommodation Details -->
        <div class="card detail-card <?= $tenant['bed_no'] ? 'current-assignment' : '' ?>">
            <div class="card-header">
                <i class="bi bi-house-door"></i> Accommodation Details
            </div>
            <div class="card-body">
                <?php if ($tenant['bed_no']): ?>
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Bed Number:</strong> <?= $tenant['bed_no'] ?> (<?= $tenant['deck'] ?>)</p>
                        <p><strong>Monthly Rent:</strong> ₱<?= number_format($tenant['monthly_rent'], 2) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Room Number:</strong> <?= $tenant['room_no'] ?></p>
                        <p><strong>Floor:</strong> <?= $tenant['floor_no'] ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Start Date:</strong> <?= date('M j, Y', strtotime($tenant['start_date'])) ?></p>
                        
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center mb-0">
                    <i class="bi bi-info-circle"></i> No bed currently assigned to this tenant
                </div>
                <?php endif; ?>
            </div>
        </div>

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
                        <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*">
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
</script>
</body>
</html>
<?php
$conn->close();
?>