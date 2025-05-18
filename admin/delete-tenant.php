<?php
session_start();
require_once 'connection/db.php';

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No tenant ID provided for deletion";
    header("Location: tenant.php");
    exit;
}

$tenantId = (int)$_GET['id'];

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Get all boarding records for this tenant
    $boardingRecords = $conn->query("SELECT boarding_id, bed_id FROM boarding WHERE tenant_id = $tenantId")->fetch_all(MYSQLI_ASSOC);
    
    // 2. Delete payments associated with each boarding record
    if (!empty($boardingRecords)) {
        $boardingIds = array_column($boardingRecords, 'boarding_id');
        $boardingIdsStr = implode(',', $boardingIds);
        $conn->query("DELETE FROM payments WHERE boarding_id IN ($boardingIdsStr)");
    }

    // 3. Delete from boarding table
    $conn->query("DELETE FROM boarding WHERE tenant_id = $tenantId");
    
    // 4. Update bed statuses to Vacant
    if (!empty($boardingRecords)) {
        $bedIds = array_column($boardingRecords, 'bed_id');
        $bedIdsStr = implode(',', $bedIds);
        $conn->query("UPDATE beds SET status = 'Vacant' WHERE bed_id IN ($bedIdsStr)");
    }
    
    // 5. Delete the tenant
    $conn->query("DELETE FROM tenants WHERE tenant_id = $tenantId");
    
    // Commit transaction
    $conn->commit();
    
    // Set session success message and redirect
    $_SESSION['success_message'] = "Tenant deleted successfully";
    header("Location: tenant.php?nocache=" . time());
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
    header("Location: tenant.php?nocache=" . time());
    exit;
}
?>
