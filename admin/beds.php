<?php
session_start();
include('../connection/db.php'); 

$categories = [];
$sql = "SELECT 
            b.bed_id,
			concat(f.floor_no, '  ', 
            r.room_no) as room_no,
            b.bed_no,
            b.deck,
            b.monthly_rent,
            b.status
        FROM beds b
        JOIN rooms r ON b.room_id = r.room_id
        join floors f on r.floor_id= f.floor_id;";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
  }
}




// for deletion



  include "../security/crypt.php";

  if (isset($_GET['del'])) {
    var_dump($_GET['del']);
  $decryptedID = decrypt($_GET['del']);

  if ($decryptedID === false || !is_numeric($decryptedID)) {
    $_SESSION['error'] = "Invalid or corrupted ID.";
    header("Location: beds.php");
    exit;
  }

  $stmt = $conn->prepare("DELETE FROM beds WHERE bed_id=?"); // corrected column name
  $stmt->bind_param("i", $decryptedID);

  if ($stmt->execute()) {
    $_SESSION['delmsg'] = "Bed record deleted successfully.";
  } else {
    $_SESSION['error'] = "Error deleting record: " . $stmt->error;
  }

  $stmt->close();
  header("Location: beds.php");
  exit;
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Beds</title>

  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

  <!-- DataTables & Export Buttons -->
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet" />

  <link rel="stylesheet" href="CSS/sidebar.css">

  
</head>
<body>

<div class="d-flex">
  <!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">
  

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="mb-0"><strong>Manage Beds</strong></h2>
  <button class="btn btn-success" onclick="window.location.href = 'add-beds.php';">
    <i class="fa fa-plus me-2"></i>Add Bed
  </button>
</div>



  <?php
  $alerts = ['error', 'msg', 'updatemsg', 'delmsg'];
  foreach ($alerts as $alert) {
    if (!empty($_SESSION[$alert])) {
      $type = ($alert == 'error') ? 'danger' : 'success';
      echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
      echo '<strong>' . ucfirst($type) . ':</strong> ' . htmlentities($_SESSION[$alert]);
      echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
      echo '</div>';
      $_SESSION[$alert] = "";
    }
  }
  ?>

  <div class="card shadow-sm w-80">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table id="bedsTable" class="table table-sm table-bordered table-hover align-middle">
          <thead class=" table-success text-white">
            <tr>
              <th>#</th>
              <th>Room No</th>
              <th>Bed No</th>
              <th>Deck</th>
              <th>Monthly Rent</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $cnt = 1;
            if (count($categories) > 0) {
              foreach ($categories as $row) {
                $encryptedid = urlencode(encrypt($row['bed_id']));
                $statusBadge = $row['status'] == 'Vacant'
                  ? '<span class="badge bg-success">Vacant</span>'
                  : '<span class="badge bg-danger">Occupied</span>';

                echo "<tr>
                        <td>{$cnt}</td>
                        <td>" . htmlentities($row['room_no']) . "</td>
                        <td>" . htmlentities($row['bed_no']) . "</td>
                        <td>" . htmlentities($row['deck']) . "</td>
                        <td>" . htmlentities(number_format($row['monthly_rent'], 2)) . "</td>
                        <td>{$statusBadge}</td>
                        <td>
                          <a href=\"edit-beds.php?id=($encryptedid}\" class=\"btn btn-sm btn-primary me-1\" name='edit-btn'>
                            <i class=\"fas fa-edit\"></i> Edit
                          </a>
                          <a href=\"beds.php?del={$encryptedid}\" class=\"btn btn-sm btn-danger\" name='delete-btn' onclick='return	confirm(\"Are you sure you want to delete this student?\")'>
                            <i class=\"fas fa-trash-alt\"></i> Delete
                          </a>
                        </td>
                      </tr>";
                $cnt++;
              }
            } else {
              echo '<tr><td colspan="7" class="text-center">No beds found.</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<?php if (count($categories) > 0): ?>
<script>
  $(document).ready(function () {
    $('#bedsTable').DataTable({
      dom:
        "<'row d-flex align-items-center mb-3'" +
        "<'col-auto'l><'col-auto'B><'col ms-auto'f>>" +
        "<'row'<'col-12'tr>>" +
        "<'row mt-3'<'col-sm-6'i><'col-sm-6 text-end'p>>",
      buttons: [
        {
          extend: 'excelHtml5',
          title: 'Beds_List',
          className: 'btn btn-sm btn-secondary'
        }
      ]
    });
  });
</script>
<?php endif; ?>

<!-- <?php include('includes/footer.php'); ?> -->
</body>
</html>
