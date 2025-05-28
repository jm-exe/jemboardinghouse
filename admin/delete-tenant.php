delete tenant

<?php
session_start();
require_once '../connection/db.php';

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
    
    // 2. Delete guest stays associated with this tenant
    $conn->query("DELETE FROM guest_stays WHERE tenant_id = $tenantId");
    
    // 3. Delete receipts and payments (in correct order)
    if (!empty($boardingRecords)) {
        $boardingIds = array_column($boardingRecords, 'boarding_id');
        $boardingIdsStr = implode(',', $boardingIds);
        
        // First get all payment IDs for these boarding records
        $paymentIds = $conn->query("SELECT payment_id FROM payments WHERE boarding_id IN ($boardingIdsStr)")->fetch_all(MYSQLI_ASSOC);
        
        if (!empty($paymentIds)) {
            $paymentIdsArray = array_column($paymentIds, 'payment_id');
            $paymentIdsStr = implode(',', $paymentIdsArray);
            
            // Delete receipts for these payments
            $conn->query("DELETE FROM receipts WHERE payment_id IN ($paymentIdsStr)");
        }
        
        // Now delete the payments
        $conn->query("DELETE FROM payments WHERE boarding_id IN ($boardingIdsStr)");
    }

    // 4. Delete tenant appliances
    $conn->query("DELETE FROM tenant_appliances WHERE tenant_id = $tenantId");
    
    // 5. Delete from boarding table
    $conn->query("DELETE FROM boarding WHERE tenant_id = $tenantId");
    
    // 6. Update bed statuses to Vacant
    if (!empty($boardingRecords)) {
        $bedIds = array_column($boardingRecords, 'bed_id');
        $bedIdsStr = implode(',', $bedIds);
        $conn->query("UPDATE beds SET status = 'Vacant' WHERE bed_id IN ($bedIdsStr)");
    }
    
    // 7. Delete tenant history
    $conn->query("DELETE FROM tenant_history WHERE tenant_id = $tenantId");
    
    // 8. Delete suggestions
    $conn->query("DELETE FROM suggestions WHERE tenant_id = $tenantId");
    
    // 9. Delete the tenant
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