<?php
session_start();
include '../connection/db.php';

// === ALERT HANDLING ===
$success = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// === FORM HANDLERS ===
function handleInsert($conn, $query, $params, $successMsg, $checkQuery, $checkParams) {
    $check = $conn->prepare($checkQuery);
    $check->bind_param(...$checkParams);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $_SESSION['success_message'] = "Duplicate entry.";
        header("Location: system-management.php");
        exit;
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $_SESSION['success_message'] = $successMsg;
    header("Location: system-management.php");
    exit;
}

// === Academic Year with Validation + Auto-End-Year ===
if (isset($_POST['add_academic_year'])) {
    $start = (int)$_POST['start_year'];
    $end = $start + 1;
    $sem = $_POST['semester'];

    if ($start < 2000 || $start > 2100) {
        $_SESSION['success_message'] = "Start year must be between 2000 and 2100.";
        header("Location: system-management.php");
        exit;
    }

    handleInsert(
        $conn,
        "INSERT INTO academic_years (start_year, end_year, semester) VALUES (?, ?, ?)",
        ["iis", $start, $end, $sem],
        "Academic year {$start}-{$end} added successfully!",
        "SELECT * FROM academic_years WHERE start_year = ? AND end_year = ? AND semester = ?",
        ["iis", $start, $end, $sem]
    );
}

// === Course ===
if (isset($_POST['add_course'])) {
    handleInsert(
        $conn,
        "INSERT INTO course (course_code, course_description, major) VALUES (?, ?, ?)",
        ["sss", $_POST['course_code'], $_POST['course_description'], $_POST['major'] ?: null],
        "Course added successfully!",
        "SELECT * FROM course WHERE course_code = ? AND course_description = ? AND major = ?",
        ["sss", $_POST['course_code'], $_POST['course_description'], $_POST['major'] ?: null]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/sidebar.css">
  <link rel="stylesheet" href="CSS/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    .main-content { padding: 2rem; }
    .settings-card {
      cursor: pointer;
      transition: transform 0.2s ease-in-out;
    }
    .settings-card:hover {
      transform: translateY(-5px);
    }
  </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content container">
  <h1 class="mb-4"><strong>System Settings</strong></h1>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="row row-cols-1 row-cols-md-3 g-4">
    <!-- Academic Year -->
    <div class="col">
      <div class="card bg-success text-white text-center p-4 settings-card" data-bs-toggle="modal" data-bs-target="#modalAcademicYear">
        <h5 class="card-title mb-0">Add Academic Year</h5>
      </div>
    </div>

    <!-- Course & Major -->
    <div class="col">
      <div class="card bg-success text-white text-center p-4 settings-card" data-bs-toggle="modal" data-bs-target="#modalCourse">
        <h5 class="card-title mb-0">Add Course & Major</h5>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Academic Year -->
<div class="modal fade" id="modalAcademicYear" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Add Academic Year</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="number" name="start_year" id="startYear" class="form-control mb-2" placeholder="Start Year (e.g. 2024)" min="2000" max="2100" required>

        <input type="number" name="end_year" id="endYear" class="form-control mb-2 bg-light" placeholder="End Year (auto)" readonly>

        <select name="semester" class="form-control mb-2" required>
          <option value="">Semester</option>
          <option>First</option>
          <option>Second</option>
          <option>Summer</option>
        </select>
        <div class="form-text">End Year = Start Year + 1 (automatically filled)</div>
      </div>

      <div class="modal-footer">
        <button name="add_academic_year" class="btn btn-success w-100">Add Academic Year</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Course -->
<div class="modal fade" id="modalCourse" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Add Course & Major</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" name="course_code" class="form-control mb-2" placeholder="Course Code" required>
        <input type="text" name="course_description" class="form-control mb-2" placeholder="Course Description" required>
        <input type="text" name="major" class="form-control mb-2" placeholder="Major (optional)">
      </div>
      <div class="modal-footer">
        <button name="add_course" class="btn btn-success w-100">Add Course</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('startYear').addEventListener('input', function () {
    const val = parseInt(this.value);
    const endInput = document.getElementById('endYear');
    if (!isNaN(val)) {
      endInput.value = val + 1;
    } else {
      endInput.value = '';
    }
  });
</script>

</body>
</html>
