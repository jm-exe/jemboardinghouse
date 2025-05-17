<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Handle course submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_description = trim($_POST['course_description']);
    $major = trim($_POST['major']) ?: null;

    // Check for duplicates
    $checkQuery = "SELECT COUNT(*) FROM course WHERE course_code = ? AND course_description = ? AND (major = ? OR (major IS NULL AND ? IS NULL))";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ssss", $course_code, $course_description, $major, $major);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        $_SESSION['error_message'] = "Course with the same code, description, and major already exists.";
        header("Location: set-course.php");
        exit;
    }

    // Insert new course
    $insertQuery = "INSERT INTO course (course_code, course_description, major) VALUES (?, ?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("sss", $course_code, $course_description, $major);
    if ($insertStmt->execute()) {
        $_SESSION['success_message'] = "Course added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding course: " . $conn->error;
    }
    $insertStmt->close();
    header("Location: set-course.php");
    exit;
}

// Fetch courses
$courses = [];
$courseQuery = "SELECT * FROM course ORDER BY course_code ASC";
$result = $conn->query($courseQuery);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Course</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        h2 { color: #095544; }
        .card { border-radius: 10px; background-color: #e5edf0; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .d-flex { padding: 20px; }
        .alert-container { min-height: 60px; }
    </style>
</head>
<body>
<div class="d-flex">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="bi bi-book"></i> <strong>Set Course</strong></h2>
        </div>
    </div>

    <!-- Add Course Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Course</h5>
        </div>
        <div class="card-body">
            <div class="alert-container">
                <?php if (!empty($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>

            <form method="POST" action="set-course.php" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Course Code</label>
                    <input type="text" name="course_code" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Course Description</label>
                    <input type="text" name="course_description" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Major (optional)</label>
                    <input type="text" name="major" class="form-control">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" name="add_course" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Course Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-table"></i> Course List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Course Code</th>
                            <th>Description</th>
                            <th>Major</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['course_code']) ?></td>
                            <td><?= htmlspecialchars($c['course_description']) ?></td>
                            <td><?= htmlspecialchars($c['major'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                <i class="bi bi-book display-6"></i>
                                <div class="mt-2">No courses found</div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
