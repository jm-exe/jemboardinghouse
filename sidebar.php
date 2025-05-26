<aside class="sidebar p-4">
  <div>
    <div class="text-center mb-4">
      <div class="user-avatar profile-pic-container mb-2 mx-auto" style="width: 110px; height: 110px;">
        <?php if (!empty($imagePath)): ?>
          <img src="<?= $imagePath ?>" alt="Profile Picture" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        
        <?php endif; ?>
      </div>
      <div class="welcome-text"><?php echo htmlspecialchars($_SESSION["username"]); ?></div>
    </div>

    <div class="divider-line"></div>
    <ul class="nav nav-pills flex-column">
      <li><a href="dashboard.php" class="nav-link<?= ($currentPage == 'dashboard' ? ' active' : '') ?>"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="notice.php" class="nav-link<?= ($currentPage == 'notice' ? ' active' : '') ?>"><i class="fas fa-bell"></i>Notice</a></li>
      <li><a href="suggestions.php" class="nav-link<?= ($currentPage == 'suggestions' ? ' active' : '') ?>"><i class="fas fa-lightbulb"></i>Suggestions</a></li>
      <li><a href="change-credentials.php" class="nav-link<?= ($currentPage == 'credentials' ? ' active' : '') ?>"><i class="fas fa-key"></i>My Credentials</a></li>
    </ul>

    <div class="text-center mt-4">
      <a href="index.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
    </div>
  </div>
</aside>
