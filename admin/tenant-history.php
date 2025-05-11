<?php
session_start();
include '../connection/db.php';

$query = "
SELECT th.*, ay.start_year, ay.end_year, b.bed_no, r.room_no,
       t.first_name, t.last_name
FROM tenant_history th
JOIN academic_years ay ON th.academic_year_id = ay.academic_year_id
LEFT JOIN beds b ON th.bed_id = b.bed_id
LEFT JOIN rooms r ON b.room_id = r.room_id
JOIN tenants t ON th.tenant_id = t.tenant_id
ORDER BY th.created_at DESC
";
$results = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tenant History Logs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/sidebar.css">

  <style>
    .main-content { padding: 2rem; }
    .table td, .table th { vertical-align: middle; }
    h3 { font-weight: bold; margin-bottom: 10px; }
  </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
  <br>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-clock-history"></i> Tenant History Logs</h3>
  </div>

  <div class="container bg-white p-4 shadow rounded">
    <table id="historyTable" class="table table-bordered table-hover">
      <thead class="table-light">
        <tr>
          <th>Tenant Name</th>
          <th>Academic Year</th>
          <th>Room</th>
          <th>Bed</th>
          <th>Start</th>
          <th>End</th>
          <th>Status</th>
          <th>Logged</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $results->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
          <td><?= $row['start_year'] ?>-<?= $row['end_year'] ?></td>
          <td><?= $row['room_no'] ?? 'N/A' ?></td>
          <td><?= $row['bed_no'] ?? 'N/A' ?></td>
          <td><?= htmlspecialchars($row['start_date']) ?></td>
          <td><?= htmlspecialchars($row['end_date']) ?></td>
          <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['status']) ?></span></td>
          <td><?= date('Y-m-d H:i A', strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function () {
    $('#historyTable').DataTable({
      pageLength: 10,
      order: [[7, 'desc']]
    });
  });
</script>

</body>
</html>
