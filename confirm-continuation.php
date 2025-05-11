<?php
session_start();
include 'connection/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'], $_POST['tenant_id'])) {
    $tenantID = (int)$_POST['tenant_id'];
    $stmt = $conn->prepare("UPDATE tenants SET is_willing_to_continue = 1 WHERE tenant_id = ?");
    $stmt->bind_param("i", $tenantID);
    $stmt->execute();
    $_SESSION['success_message'] = "Thank you for confirming your continuation!";
}

header("Location: dashboard.php");
exit();
?>
