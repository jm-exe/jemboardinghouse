<?php
session_start();
require_once '../connection/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method";
    header("Location: tenant.php");
    exit;
}

if (!isset($_POST['tenant_id'])) {
    $_SESSION['error_message'] = "Tenant ID not provided";
    header("Location: tenant.php");
    exit;
}

$tenantId = (int)$_POST['tenant_id'];

// Check if tenant exists
$tenantQuery = $conn->prepare("SELECT profile_picture FROM tenants WHERE tenant_id = ?");
$tenantQuery->bind_param('i', $tenantId);
$tenantQuery->execute();
$tenantResult = $tenantQuery->get_result();

if ($tenantResult->num_rows === 0) {
    $_SESSION['error_message'] = "Tenant not found";
    header("Location: tenant.php");
    exit;
}

$tenant = $tenantResult->fetch_assoc();
$tenantQuery->close();

// Create uploads directory if it doesn't exist
if (!file_exists('../uploads/profiles')) {
    mkdir('../uploads/profiles', 0755, true);
}

// Handle photo removal
if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === 'on') {
    if (!empty($tenant['profile_picture'])) {
        $filePath = '../uploads/profiles/' . $tenant['profile_picture'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $updateStmt = $conn->prepare("UPDATE tenants SET profile_picture = NULL WHERE tenant_id = ?");
        $updateStmt->bind_param('i', $tenantId);
        $updateStmt->execute();
        $updateStmt->close();
        
        $_SESSION['success_message'] = "Profile picture removed successfully";
    }
    header("Location: edit-tenant.php?id=$tenantId");
    exit;
}

// Handle file upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/profiles/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['profile_picture']['tmp_name']);
    finfo_close($fileInfo);
    
    // Validate file type and size
    if (!in_array($mimeType, $allowedTypes)) {
        $_SESSION['error_message'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        header("Location: edit-tenant.php?id=$tenantId");
        exit;
    }
    
    if ($_FILES['profile_picture']['size'] > $maxSize) {
        $_SESSION['error_message'] = "File is too large. Maximum size is 2MB.";
        header("Location: edit-tenant.php?id=$tenantId");
        exit;
    }
    
    // Generate unique filename
    $fileExt = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    $validExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($fileExt, $validExtensions)) {
        $_SESSION['error_message'] = "Invalid file extension.";
        header("Location: edit-tenant.php?id=$tenantId");
        exit;
    }
    
    $fileName = 'profile_' . $tenantId . '_' . uniqid() . '.' . $fileExt;
    $targetPath = $uploadDir . $fileName;
    
    // Delete old photo if exists
    if (!empty($tenant['profile_picture'])) {
        $oldFilePath = $uploadDir . $tenant['profile_picture'];
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
        // Update database
        $updateStmt = $conn->prepare("UPDATE tenants SET profile_picture = ? WHERE tenant_id = ?");
        $updateStmt->bind_param('si', $fileName, $tenantId);
        $updateStmt->execute();
        $updateStmt->close();
        
        $_SESSION['success_message'] = "Profile picture updated successfully";
    } else {
        $_SESSION['error_message'] = "Error uploading file. Please try again.";
    }
} else {
    $_SESSION['error_message'] = "No file uploaded or upload error occurred";
}

header("Location: edit-tenant.php?id=$tenantId");
exit;
?>