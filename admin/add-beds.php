<?php 
include 'connection/db.php'; 

// Initialize message variables
$successMsg = $errorMsg = "";

// Handle form submission
if (isset($_POST['create'])) {
  $roomno = $_POST['roomno'];
  $bedno = $_POST['bedno'];
  $deck = $_POST['deck'];
  $monthly_rent = $_POST['monthly_rent'];
  $status = $_POST['status'];

  // Prepare and bind
  $stmt = $conn->prepare("INSERT INTO beds (room_id, bed_no, deck, monthly_rent, status) VALUES (?, ?, ?, ?, ?)");
  if ($stmt) {
    $stmt->bind_param("iisss", $roomno, $bedno, $deck, $monthly_rent, $status);

    if ($stmt->execute()) {
      $successMsg = "Bed added successfully.";
      header('location: beds.php');
      exit;
    } else {
      $errorMsg = "Error inserting bed: " . $stmt->error;
    }

    $stmt->close();
  } else {
    $errorMsg = "Error preparing statement: " . $conn->error;
  }
}

// Fetch room numbers
$roomQuery = "SELECT r.room_id, CONCAT(f.floor_no, ' ', r.room_no) AS room_no 
              FROM rooms r 
              JOIN floors f ON r.floor_id = f.floor_id;";
$roomResult = $conn->query($roomQuery);


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Beds</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
</head>
<body>

<div class="d-flex">
<?php include 'sidebar.php'; ?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow">
        <div class="card-header bg-success text-white text-start">
          <h3><i class="fas fa-plus-circle"></i> &nbsp; <strong>Add Bed</strong></h3>
        </div>
        <div class="card-body">
          
          <!-- Alert Messages -->
          <?php if (!empty($successMsg)) : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= $successMsg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php elseif (!empty($errorMsg)) : ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?= $errorMsg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="POST" action=""> 
            <div class="mb-3">
              <label for="roomno" class="form-label">Room No.</label>
              <select class="form-select" name="roomno" required>
                <option value="">Select Room</option>
                <?php
                  while ($row = $roomResult->fetch_assoc()) {
                    echo '<option value="' . $row['room_id'] . '">' . htmlspecialchars($row['room_no']) . '</option>';
                  }
                ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="bedno" class="form-label">Bed No</label>
              <input type="number" class="form-control" name="bedno" required />
            </div>

            <div class="mb-3">
              <label for="deck" class="form-label">Deck</label>
              <select class="form-select" name="deck" required>
                <option value="">Select Deck</option>
                <option value="Upper">Upper</option>
                <option value="Lower">Lower</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="monthly_rent" class="form-label">Monthly Rent (₱)</label>
              <input type="number" step="0.01" class="form-control" name="monthly_rent" required />
            </div>

            <div class="mb-3">
              <label for="status" class="form-label">Status</label>
              <select class="form-select" name="status" required>
                <option value="">Select Status</option>
                <option value="Vacant">Vacant</option>
                <option value="Occupied">Occupied</option>
              </select>
            </div>

            <div class="d-grid mt-3">
              <button type="submit" name="create" class="btn btn-success">
                <i class="fa fa-check me-2"></i>Create
              </button>
            </div>
          </form>

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
