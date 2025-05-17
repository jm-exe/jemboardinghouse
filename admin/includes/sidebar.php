
  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <!-- Sidebar -->
  <div class="sidebar p-4" style="height: 100vh">
    <div>
      <!-- Profile Section -->
      <div class="profile mb-3 text-center">
        <i class="fa fa-user-circle"></i>
        <div class="welcome-text"><?php echo "Welcome, ". "<strong> " . $_SESSION['username'] . "</strong>";?></div>
      </div>
      
      <div class="divider-line"></div>
      
      <!-- Navigation -->
      <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link"><i class="fa fa-bar-chart me-2"></i>Dashboard</a>
        </li>
        <li><a href="tenant.php" class="nav-link"><i class="fa fa-users me-2"></i>Tenants</a></li>
        <li><a href="rooms.php" class="nav-link"><i class="fa fa-door-open me-2"></i>Rooms</a></li>
        <li><a href="beds.php" class="nav-link"><i class="fa fa-bed me-2"></i>Beds</a></li>
        <li><a href="#" class="nav-link"><i class="fa fa-dollar me-2"></i>Payments</a></li>
        <li><a href="expenses.php" class="nav-link"><i class="fa fa-bank me-2"></i>Expenses</a></li>
        <li><a href="suggestions.php" class="nav-link"><i class="bi bi-lightbulb-fill me-2"></i>Suggestions</a></li>
        <li><a href="send-notice.php" class="nav-link"><i class="bi bi-megaphone-fill me-2"></i>Announcement</a></li>
       
        <li><a href="migrate.php" class="nav-link"><i class="fa fa-cog me-2"></i>Migrate Tenants</a></li>
        <li><a href="tenant-history.php" class="nav-link"><i class="fa fa-cog me-2"></i>Tenant History</a></li>
         <li><a href="system-management.php" class="nav-link"><i class="fa fa-cog me-2"></i>Settings</a></li>
        <li><a href="../index.php" class="nav-link text-danger"><i class="fa fa-sign-out me-2"></i>Log out</a></li>
      </ul>
    </div>
  </div>
  
   
