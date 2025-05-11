<?php
session_start();
include 'connection/db.php'; 

$errorMsg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {
    $username = trim($_POST["Username"]);
    $password = $_POST["Password"];

    // Prepare statement
    $stmt = $conn->prepare("SELECT tenant_id, username, password FROM tenants WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        

        if (password_verify($password, $row["password"])) {
            $_SESSION["tenant_id"] = $row["tenant_id"];
            $_SESSION["username"] = $row["username"];
            header("Location: user/dashboard.php"); 
            exit();
        } else {
            $errorMsg = "Invalid password.";
        }
    } else {
        $errorMsg = "Username not found.";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" >
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="../CSS/style.css" >
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" >
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

<?php $password = password_hash('a', PASSWORD_BCRYPT);
        var_dump($password);
        ?>
  <div class="container">
    <!-- Left Panel -->
    <div class="left-panel">
      <div class="branding">
        <div class="icon"></div>
        <h1></h1>
        <h3></h3>
        <p></p>
        <div class="features">
          <p></p>
          <p></p>
        </div>
      </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
      <div class="login-container">
        <img src="../Pictures/whitelogo.png" alt="Logo" class="image-class">
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
        </form>
      </div>
    </div>
  </div>
</body>
</html>
