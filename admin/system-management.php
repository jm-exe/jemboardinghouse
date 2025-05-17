<?php
session_start();
include '../connection/db.php';

// Alert Handling
$success = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
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
    .settings-card a {
      color: white;
      text-decoration: none;
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
      <div class="card bg-success text-white text-center p-4 settings-card">
        <a href="set-academic-year.php">
          <h5 class="card-title mb-0">Add Academic Year</h5>
        </a>
      </div>
    </div>

    <!-- Course & Major -->
    <div class="col">
      <div class="card bg-success text-white text-center p-4 settings-card">
        <a href="set-course.php">
          <h5 class="card-title mb-0">Add Course & Major</h5>
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
