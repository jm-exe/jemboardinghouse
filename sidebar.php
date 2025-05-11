
<aside class="sidebar p-4">
  <div>
    <div class="text-center mb-4">
      <div class="user-avatar">
        <i class="fas fa-user-circle"></i>
      </div>
      <div class="welcome-text">Welcome Back, <?php echo $_SESSION["username"]; ?></div>
    </div>

    <div class="divider-line"></div>

    <ul class="nav nav-pills flex-column">
      <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-pie"></i>Dashboard</a></li>
      <li><a href="notice.php" class="nav-link"><i class="fas fa-bell"></i>Notice</a></li>
      <li><a href="payment-history.php" class="nav-link"><i class="fas fa-file-invoice-dollar"></i>Payment History</a></li>
      <li><a href="suggestions.php" class="nav-link"><i class="fas fa-lightbulb"></i>Suggestions</a></li>
      <!-- <li><a href="notification.php" class="nav-link"><i class="fas fa-bell"></i>Notification</a></li> -->
      <!-- <li><a href="request-sleepover.php" class="nav-link"><i class="fas fa-lightbulb"></i>Request Sleepover</a></li> -->
      
    </ul>

    <div class="text-center mt-4">
      <a href="index.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
    </div>
  </div>

</aside>