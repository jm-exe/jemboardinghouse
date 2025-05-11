<?php

session_start();
include 'connection/db.php';
include 'security/crypt.php';

$successMsg = $errorMsg = "";
$bedData = null;

// Check if a bed ID is passed
if (isset($_GET['id'])) {
  $bed_id = decrypt(urldecode($_GET['id']));

  // Fetch bed data
  $stmt = $conn->prepare("SELECT * FROM beds WHERE bed_id = ?");
  $stmt->bind_param("i", $bed_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $bedData = $result->fetch_assoc();
  $stmt->close();

  if (!$bedData) {
    $errorMsg = "Bed not found.";
  }


} else {
  $errorMsg = "No bed selected.";
}

// Handle form submission
if (isset($_POST['update'])) {
  $roomno = $_POST['roomno'];
  $bedno = $_POST['bedno'];
  $deck = $_POST['deck'];
  $monthly_rent = $_POST['monthly_rent'];
  $status = $_POST['status'];

  $stmt = $conn->prepare("UPDATE beds SET room_id = ?, bed_no = ?, deck = ?, monthly_rent = ?, status = ? WHERE bed_id = ?");
  if ($stmt) {
    $stmt->bind_param("iisssi", $roomno, $bedno, $deck, $monthly_rent, $status, $bed_id);

    if ($stmt->execute()) {
      $successMsg = "Bed updated successfully.";
      echo "<script>
  alert('Bed updated successfully.');
  window.location.href = 'beds.php';
</script>";
      exit;

    } else {
      $errorMsg = "Error updating bed: " . $stmt->error;
    }

    $stmt->close();
  } else {
    $errorMsg = "Error preparing update statement: " . $conn->error;
  }
}

// Fetch room options
$roomQuery = "SELECT r.room_id, CONCAT(f.floor_no, ' ', r.room_no) AS room_no 
              FROM rooms r 
              JOIN floors f ON r.floor_id = f.floor_id";
$roomResult = $conn->query($roomQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Bed</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
  <div class="d-flex">
  <?php include 'sidebar.php'; ?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow">
        <div class="card-header bg-success text-white">
          <h3><i class="fas fa-edit"></i> &nbsp; <strong>Edit Bed</strong></h3>
        </div>
        <div class="card-body">
          <!-- Alert messages -->
          <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= $successMsg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php elseif (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?= $errorMsg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <?php if ($bedData): ?>
            <form method="POST" action="">
              <div class="mb-3">
                <label for="roomno" class="form-label">Room No.</label>
                <select class="form-select" name="roomno" required>
                  <option value="">Select Room</option>
                  <?php while ($row = $roomResult->fetch_assoc()): ?>
                    <option value="<?= $row['room_id'] ?>" <?= $bedData['room_id'] == $row['room_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($row['room_no']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="bedno" class="form-label">Bed No</label>
                <input type="number" class="form-control" name="bedno" value="<?= $bedData['bed_no'] ?>" required />
              </div>

              <div class="mb-3">
                <label for="deck" class="form-label">Deck</label>
                <select class="form-select" name="deck" required>
                  <option value="">Select Deck</option>
                  <option value="Upper" <?= $bedData['deck'] === 'Upper' ? 'selected' : '' ?>>Upper</option>
                  <option value="Lower" <?= $bedData['deck'] === 'Lower' ? 'selected' : '' ?>>Lower</option>
                </select>
              </div>

              <div class="mb-3">
                <label for="monthly_rent" class="form-label">Monthly Rent (₱)</label>
                <input type="number" step="0.01" class="form-control" name="monthly_rent"
                  value="<?= $bedData['monthly_rent'] ?>" required />
              </div>

              <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" name="status" required>
                  <option value="">Select Status</option>
                  <option value="Vacant" <?= $bedData['status'] === 'Vacant' ? 'selected' : '' ?>>Vacant</option>
                  <option value="Occupied" <?= $bedData['status'] === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                </select>
              </div>

              <div class="d-grid mt-3">
                <button type="submit" name="update" class="btn btn-success">
                  <i class="fa fa-save me-2"></i>Update
                </button>
              </div>
            </form>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>