<?php
session_start();
include '../connection/db.php';

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Update status of a suggestion
if (isset($_POST['action'], $_POST['suggestion_id'])) {
    $suggestionID = (int)$_POST['suggestion_id'];
    $action = ($_POST['action'] === 'Noted') ? 'Noted' : 'Pending';

    $stmt = $conn->prepare("UPDATE suggestions SET status = ? WHERE suggestion_id = ?");
    $stmt->bind_param("si", $action, $suggestionID);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success_message'] = "Suggestion marked as $action.";
    header("Location: suggestions.php");
    exit();
}

// Fetch suggestions
$query = "
    SELECT s.suggestion_id, s.suggestion, s.date_submitted, s.status,
           t.first_name, t.last_name
    FROM suggestions s
    JOIN tenants t ON s.tenant_id = t.tenant_id
    ORDER BY s.date_submitted DESC
";
$suggestions = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Suggestions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/sidebar.css">

        <style>
      h3{
          margin-bottom: 10px;
          font-weight: bold;
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<br>
<div class="main-content p-4">
    <h3><i class="bi bi-lightbulb"></i> Tenant Suggestions</h3>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive bg-white p-3 rounded shadow">
        <table id="suggestionsTable" class="table table-striped table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Tenant Name</th>
                    <th>Suggestion</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $suggestions->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['suggestion']) ?></td>
                    <td><?= htmlspecialchars(date("F j, Y", strtotime($row['date_submitted']))) ?></td>
                    <td>
                        <span class="badge <?= $row['status'] === 'Noted' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $row['status'] ?? 'Pending' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="suggestion_id" value="<?= $row['suggestion_id'] ?>">
                            <button type="submit" name="action" value="Noted" class="btn btn-sm btn-success">
                                Mark as Noted
                            </button>
                            <button type="submit" name="action" value="Pending" class="btn btn-sm btn-outline-secondary">
                                Set as Pending
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        $('#suggestionsTable').DataTable({
            pageLength: 10
        });
    });
</script>
</body>
</html>
