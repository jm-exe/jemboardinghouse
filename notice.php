<?php
session_start();

if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
include 'connection/db.php';

// Fetch latest announcements (assuming visible to all tenants)
$announcementQuery = "SELECT title, content, posted_on FROM announcements ORDER BY posted_on DESC LIMIT 10";
$announcements = $conn->query($announcementQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notice Board</title>
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
        <h1><strong>Notice Board</strong></h1>
        <p>Stay updated with announcements from your landlord</p>

        <!-- Announcement Cards -->
        <?php if ($announcements && $announcements->num_rows > 0): ?>
          <?php while ($notice = $announcements->fetch_assoc()): ?>
            <div class="card mb-3 shadow-sm">
              <div class="card-header bg-primary text-white">
                <strong><?php echo htmlspecialchars($notice['title']); ?></strong>
              </div>
              <div class="card-body">
                <p class="card-text"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></p>
              </div>
              <div class="card-footer text-muted">
                <small>Posted on <?php echo date("F j, Y", strtotime($notice['posted_on'])); ?></small>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="alert alert-info">No announcements found at this time.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  </div>
  

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
</body>
</html>
