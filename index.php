<?php
session_start();
include('connection/db.php');

$errorMsg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = $_POST['Username'];
    $password = $_POST['Password'];

    // JOIN tenants to get tenant_id if role is Tenant
    $stmt = $conn->prepare("SELECT users.user_id, password, role, tenant_id, is_first_login 
                            FROM users 
                            LEFT JOIN tenants t ON t.user_id = users.user_id 
                            WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashedPassword, $role, $tenant_id, $is_first_login);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_role'] = $role;

            if ($role === "Tenant") {
                if ($tenant_id) {
                    $_SESSION['tenant_id'] = $tenant_id;
                    // Check if it's the user's first login
                    if ($is_first_login) {
                        header("Location: change-credentials.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                } else {
                    $errorMsg = "Tenant ID not found for this user.";
                }
            } elseif ($role === "Admin") {
                // Check if system is configured
                $query = "SELECT COUNT(*) as count FROM settings WHERE setting_name = 'initial_setup_completed'";
                $result = $conn->query($query);
                $row = $result->fetch_assoc();

                if ($row['count'] == 0) {
                    // System not configured, redirect to system_config.php
                    header("Location: admin/system_config.php");
                } else {
                    // System already configured, go to admin dashboard
                    header("Location: admin/dashboard.php");
                }
            } else {
                $errorMsg = "Unknown user role.";
            }
            exit();
        } else {
            $errorMsg = "Incorrect password.";
        }
    } else {
        $errorMsg = "Username not found.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="admin/CSS/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .error-message {
      color: red;
      font-size: 0.9rem;
      margin-top: 10px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Left Panel -->
    <div class="left-panel">
      <div class="branding">
        <div class="icon"></div>
        <h1 class="welcome">Welcome!</h1>
        <p>Please log in to your account to manage bookings, view announcements, and access exclusive features. 
            Enter your username and password to get started. If you’re new here, don’t hesitate to register for an account. 
            </p>
        <div class="divider"></div>
        <p>We’re glad to have you!</p>

        
        
        <div class="features">
          <!-- <p><i class="fa fa-user"></i></p>
          <p></p> -->
        </div>
      </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
      <div class="login-container">
        
        <img src="Pictures/logo2.png" alt="Logo" class="image-class">
         <p class="btmlogo" style="margin-top: 0%;">Radyx BoardingHouse</p> <br><br><br><br>
       
        <form class="login-form" action="" method="post">
          <div class="input-group">
            <label for="Username">Username:</label>
            <input type="text" name="Username" id="Username" required value="<?php echo isset($_POST['Username']) ? htmlspecialchars($_POST['Username']) : ''; ?>">
          </div>

          <div class="input-group">
            <label for="Password">Password:</label>
            <input type="password" name="Password" id="Password" required>
          </div>

          <?php if (!empty($errorMsg)): ?>
            <p class="error-message"><?php echo $errorMsg; ?></p>
          <?php endif; ?>

          <button type="submit" name="login">Login</button>

          <p class="signup-link">Don't have an account? <span>Contact admin</span></p>
        </form>
      </div>
    </div>
  </div>
</body>
</html>