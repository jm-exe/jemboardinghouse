<?php
session_start();

if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
include 'connection/db.php';  

// Handle suggestion form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['suggestion'])) {
  $suggestion = trim($_POST['suggestion']);
    $suggestion = mysqli_real_escape_string($conn, $suggestion);

    $suggestionQuery = "INSERT INTO suggestions (tenant_id, suggestion) VALUES (?, ?)";
    $stmt = $conn->prepare($suggestionQuery);
    $stmt->bind_param("is", $tenant_id, $suggestion);
    $stmt->execute();
}

// Fetch submitted suggestions
$suggestionsQuery = "SELECT suggestion, date_submitted FROM suggestions WHERE tenant_id = ? ORDER BY date_submitted DESC";
$stmt = $conn->prepare($suggestionsQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$suggestionsResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/styles.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-10 mr-4" style="margin-left: 270px;">
        <h1><strong>Suggestion Box</strong></h1>
        <p>What's on your mind, <?php echo htmlspecialchars($_SESSION["username"]); ?>?</p>

        <!-- Suggestion Form -->
        <div class="card mb-4">
          <div class="card-header"><h5>Suggestion Box</h5></div>
          <div class="card-body">
            <form method="POST" action="">
              <div class="form-group">
                <label for="suggestion">Your Suggestion:</label>
                <textarea name="suggestion" id="suggestion" class="form-control" rows="4" required></textarea>
              </div>
              <button type="submit" class="btn btn-primary mt-3">Send Suggestion</button>
            </form>
          </div>
        </div>

        <!-- Display Suggestions -->
        <div class="card mb-4">
          <div class="card-header"><h5>Submitted Suggestions</h5></div>
          <div class="card-body">
            <?php if ($suggestionsResult->num_rows > 0): ?>
              <ul class="list-group">
                <?php while ($row = $suggestionsResult->fetch_assoc()) { ?>
                  <li class="list-group-item">
                    <strong><?php echo date("F j, Y", strtotime($row['date_submitted'])); ?>:</strong>
                    <p><?php echo nl2br(htmlspecialchars($row['suggestion'])); ?></p>
                  </li>
                <?php } ?>
              </ul>
            <?php else: ?>
              <p>No suggestions submitted yet.</p>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('suggestion').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
    }
  });
</script>
</body>
</html>
