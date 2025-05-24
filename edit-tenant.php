<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Tenant') {
  header('Location: index.php');
  exit();
}

include 'connection/db.php';

$tenant_id = $_SESSION['tenant_id'];
$success = '';
$error = '';

// Fetch tenant data
$stmt = $conn->prepare("SELECT first_name, last_name, mobile_no, profile_picture FROM tenants WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $mobile_no = trim($_POST['mobile_no']);

    $imageFileName = $tenant['profile_picture'];

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/profiles/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $tempName = $_FILES['profile_picture']['tmp_name'];
        $imageFileName = basename($_FILES['profile_picture']['name']);
        move_uploaded_file($tempName, $uploadDir . $imageFileName);
    }

    $updateQuery = "UPDATE tenants SET first_name = ?, last_name = ?, mobile_no = ?, profile_picture = ? WHERE tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ssssi", $first_name, $last_name, $mobile_no, $imageFileName, $tenant_id);

    if ($stmt->execute()) {
        $success = "Information updated successfully!";
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Update failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Tenant Info</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/styles.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="main-content">
    <div class="container" style="margin-left: 280px;">
      <h2 class="mt-4">Edit Your Information</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" class="mt-4">
        <div class="row">
          <div class="col-md-4 text-center">
            <div class="profile-pic-container mb-3 mx-auto">
              <img src="<?= $tenant['profile_picture'] ? 'uploads/profiles/' . htmlspecialchars($tenant['profile_picture']) : 'Pictures/logo.png'; ?>" 
                  alt="Profile Image">
            </div>

            <div class="mb-3">
              <label for="profile_picture" class="form-label">Change Profile Picture</label>
              <input type="file" name="profile_picture" class="form-control">
            </div>
          </div>

          <div class="col-md-8">
            <div class="mb-3">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($tenant['first_name']) ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($tenant['last_name']) ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Mobile No.</label>
              <input type="text" name="mobile_no" class="form-control" value="<?= htmlspecialchars($tenant['mobile_no']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            <a href="dashboard.php" class="btn btn-secondary ms-2">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
