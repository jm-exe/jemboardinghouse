<?php
session_start();
include('connection/db.php'); 

$categories = [];
$sql = "SELECT 
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

  
</head>
<body>

<!-- SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="flex-grow-1 p-4">
  

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

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table id="bedsTable" class="table table-bordered table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Tenant Name</th>
              <th>Floor No</th>
              <th>Room No</th>
              <th>Bed No</th>
              <th>Date Start</th>
              <th>Due Date</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $cnt = 1;
            if (count($categories) > 0) {
              foreach ($categories as $row) {
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
                          <a href=\"edit-beds.php\" class=\"btn btn-sm btn-primary me-1\">
                            <i class=\"fas fa-edit\"></i> Edit
                          </a>
                          <a href=\"#\" class=\"btn btn-sm btn-danger\">
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
