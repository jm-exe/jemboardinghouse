<?php
session_start();
include 'connection/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Tenant') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$successMessage = '';
$errorMsg = '';

// Fetch the current username and is_first_login from the database
$stmt = $conn->prepare("SELECT username, is_first_login, password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_username = $user['username'] ?? '';
$is_first_login = $user['is_first_login'] ?? 0;
$hashed_password = $user['password'] ?? '';
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_credentials'])) {
    $current_password = trim($_POST['current_password']);
    $new_username = trim($_POST['new_username']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate input
    if (empty($current_password) || empty($new_username) || empty($new_password) || empty($confirm_password)) {
        $errorMsg = "All fields are required.";
    } elseif (!password_verify($current_password, $hashed_password)) {
        $errorMsg = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $errorMsg = "New passwords do not match.";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errorMsg = "Username already taken.";
        } else {
            // Update username, password, and is_first_login
            $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
            $update_query = "UPDATE users SET username = ?, password = ?, is_first_login = 0 WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssi", $new_username, $hashed_new_password, $user_id);

            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username;
                $successMessage = "Credentials updated successfully! You will be redirected to the dashboard.";
                header("refresh:3;url=dashboard.php");
            } else {
                $errorMsg = "Failed to update credentials. Please try again.";
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Credentials</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/styles.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9rem;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .success-message {
            color: green;
            font-size: 0.9rem;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        h4 {
            color: royalblue;
            margin: auto;
            padding: 0;
        }
        /* Disable sidebar interaction */
        .sidebar.disabled {
            pointer-events: none;
            opacity: 0.6;
        }
        /* Modal backdrop customization */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.8);
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'credentials'; 
    include 'sidebar.php'; 
    ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-10" style="margin-left: 280px;">
                    <h1><strong>Change Your Credentials</strong></h1>
                    <p>Update your username and password for security purposes.</p>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errorMsg)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="POST" action="">
                                <h4>Change username</h4><br>
                                <div class="mb-3">
                                    <label for="new_username" class="form-label">New Username:</label>
                                    <input type="text" class="form-control" id="new_username" name="new_username" required 
                                           value="<?php echo isset($_POST['new_username']) ? htmlspecialchars($_POST['new_username']) : htmlspecialchars($current_username); ?>">
                                </div>
                                
                                <h4>Change password</h4><br>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password:</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password:</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required 
                                           value="<?php echo isset($_POST['new_password']) ? htmlspecialchars($_POST['new_password']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password:</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                           value="<?php echo isset($_POST['confirm_password']) ? htmlspecialchars($_POST['confirm_password']) : ''; ?>">
                                </div>
                                <button type="submit" name="update_credentials" class="btn btn-primary">Update Credentials</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for first-time login alert -->
    <?php if ($is_first_login): ?>
    <div class="modal fade" id="firstLoginModal" tabindex="-1" aria-labelledby="firstLoginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="firstLoginModalLabel">First-Time Login</h5>
                </div>
                <div class="modal-body">
                    <p class="text-danger">For security reasons, you must change your default credentials before accessing the system.</p>
                    <p>Please update your username and password below.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($is_first_login): ?>
        // Show modal on first login
        document.addEventListener('DOMContentLoaded', function() {
            var firstLoginModal = new bootstrap.Modal(document.getElementById('firstLoginModal'), {
                backdrop: 'static',
                keyboard: false
            });
            firstLoginModal.show();

            // Disable sidebar
            document.querySelector('.sidebar').classList.add('disabled');

            // Prevent sidebar navigation
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('Please update your credentials before accessing other pages.');
                });
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>