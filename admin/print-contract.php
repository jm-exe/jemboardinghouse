<?php
session_start();
require_once 'connection/db.php';

// Check for tenant_id in POST, GET, or SESSION
$tenantId = null;

if (isset($_POST['tenant_id'])) {
    $tenantId = (int)$_POST['tenant_id'];
} elseif (isset($_GET['tenant_id'])) {
    $tenantId = (int)$_GET['tenant_id'];
} elseif (isset($_SESSION['new_tenant_ids']) && isset($_SESSION['new_tenant_ids']['tenant_id'])) {
    $tenantId = (int)$_SESSION['new_tenant_ids']['tenant_id'];
}

if (!$tenantId) {
    die('
        <div style="padding:20px; font-family:Arial; max-width:600px; margin:0 auto;">
            <h2>Error: No Tenant Specified</h2>
            <p>Please provide a tenant ID through one of these methods:</p>
            <ol>
                <li>Add ?tenant_id=123 to the URL</li>
                <li>Submit a form with tenant_id</li>
                <li>Access this page after tenant registration</li>
            </ol>
            <p><a href="tenant.php">Return to Tenant Management</a></p>
        </div>
    ');
}

try {
    $tenantQuery = $conn->prepare("
        SELECT t.*, g.first_name as guardian_first, g.last_name as guardian_last, 
               g.mobile_no as guardian_mobile, b.bed_no, r.room_no, f.floor_no,
               bo.start_date
        FROM tenants t
        LEFT JOIN guardians g ON t.guardian_id = g.guardian_id
        LEFT JOIN boarding bo ON t.tenant_id = bo.tenant_id
        LEFT JOIN beds b ON bo.bed_id = b.bed_id
        LEFT JOIN rooms r ON b.room_id = r.room_id
        LEFT JOIN floors f ON r.floor_id = f.floor_id
        WHERE t.tenant_id = ?
    ");
    $tenantQuery->bind_param('i', $tenantId);
    $tenantQuery->execute();
    $tenant = $tenantQuery->get_result()->fetch_assoc();
    
    if (!$tenant) {
        throw new Exception("Tenant not found in database");
    }

    // Format dates
    $startDate = date('F j, Y', strtotime($tenant['start_date']));

} catch (Exception $e) {
    die("Error loading tenant data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Contract of Tenancy - <?= htmlspecialchars($tenant['first_name'].' '.$tenant['last_name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        .contract { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 30px; }
        h1 { text-align: center; color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { font-size: 1.2em; margin-top: 20px; }
        .section { margin: 20px 0; }
        .signature { margin-top: 50px; }
        .signature-line { width: 300px; border-top: 1px solid #000; margin: 40px 0 10px; }
        .terms-list { margin-left: 20px; }
        .terms-list li { margin-bottom: 10px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .contract { border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="contract">
        <h1>CONTRACT OF TENANCY</h1>
        
        <p><strong>KNOW ALL MEN BY THESE PRESENTS:</strong></p>
        
        <p>This <strong>Contract of Tenancy</strong> is entered into and executed this <?= date('jS') ?> day of <?= date('F, Y') ?>, by and between:</p>
        
        <p><strong>[LANDLORD'S FULL NAME]</strong>, of legal age, Filipino, and residing at [LANDLORD'S ADDRESS], hereinafter referred to as the "<strong>Landlord</strong>";</p>
        
        <p>-and-</p>
        
        <p><strong><?= htmlspecialchars($tenant['first_name'].' '.$tenant['last_name']) ?></strong>, of legal age, Filipino, and residing at <?= htmlspecialchars($tenant['address']) ?>, hereinafter referred to as the "<strong>Tenant</strong>".</p>
        
        <h2>WITNESSETH:</h2>
        
        <p>That both parties, having the legal capacity to contract, hereby agree to abide by the following terms and conditions:</p>
        
        <h2>Rent and Payment Schedule</h2>
        <ul class="terms-list">
            <li>The monthly rental rate is <strong>₱1,100.00</strong>.</li>
            <li>The Tenant shall remit <strong>₱1,100.00</strong> upon occupancy.</li>
            <li>Succeeding monthly payments must be made <strong>on or before the first week of each month.</strong></li>
        </ul>
        
        <h2>Late Payment Penalties</h2>
        <ul class="terms-list">
            <li>A <strong>grace period of two (2) weeks</strong> is allowed for late rental payments.</li>
            <li>Beyond the grace period, the Tenant shall incur a <strong>penalty of ₱50.00 per week</strong> of delay.</li>
        </ul>
        
        <h2>Minimum Stay Commitment</h2>
        <p>The Tenant agrees to occupy the premises for the following minimum periods:</p>
        <ul class="terms-list">
            <li><strong>Five (5) months</strong> during the <strong>First Semester</strong>;</li>
            <li><strong>Five (5) months</strong> during the <strong>Second Semester</strong>;</li>
            <li><strong>Two (2) months</strong> during the <strong>Summer Term</strong>.</li>
        </ul>
        <p>Failure to complete the stipulated stay shall result in:</p>
        <ul class="terms-list">
            <li><strong>Forfeiture of any prior payments</strong>, and</li>
            <li>An <strong>early termination charge of ₱3,000.00</strong>.</li>
        </ul>
        
        <h2>Utilities Usage</h2>
        <ul class="terms-list">
            <li>The Tenant is allowed to use water and electricity without additional charge.</li>
            <li>If the <strong>water bill exceeds ₱3,000.00</strong> or the <strong>electric bill exceeds ₱5,000.00</strong>, a <strong>₱100.00 surcharge</strong> will be added to the Tenant's account.</li>
        </ul>
        
        <h2>Appliance Usage Charges</h2>
        <p>An <strong>additional charge of ₱100.00 per unit</strong> shall apply for the following appliances:</p>
        <ul class="terms-list">
            <li>Rice Cooker</li>
            <li>Coiled Electric Fan</li>
            <li>Water Heater</li>
            <li>Laptop</li>
            <li>Flat Iron</li>
        </ul>
        
        <h2>Guest Policy</h2>
        <ul class="terms-list">
            <li>Guests are permitted only within designated areas and only until 10:00 PM.</li>
            <li>Sleepovers shall be charged at ₱250.00 per night.</li>
            <li>Unauthorized overnight stays will automatically be charged to the Tenant's account.</li>
        </ul>
        
        <h2>Cleanliness and Maintenance</h2>
        <ul class="terms-list">
            <li>The Tenant shall maintain cleanliness in their assigned bed space and common areas.</li>
            <li>Every <strong>Monday</strong> is designated as <strong>Boardinghouse Cleaning Day</strong>, and participation is <strong>mandatory</strong> for all tenants.</li>
        </ul>
        
        <h2>Liability for Damages</h2>
        <ul class="terms-list">
            <li>The Tenant shall be <strong>solely responsible for the cost of any damage</strong> caused by their own negligence, misuse, or intentional actions.</li>
            <li>Assessment and repair/replacement costs shall be determined by the Landlord, and the Tenant shall <strong>settle the full amount due within fifteen (15) days</strong> of notification.</li>
        </ul>
        
        <h2>Reporting Duties</h2>
        <ul class="terms-list">
            <li>The <strong>Tenant must promptly report any damages, concerns, or maintenance issues</strong> to the Landlord.</li>
        </ul>
        
        <h2>Acknowledgment and Full Disclosure</h2>
        <p>The Tenant acknowledges that:</p>
        <ul class="terms-list">
            <li>All provisions of this Contract have been <strong>fully explained</strong> and are <strong>clearly understood</strong>.</li>
            <li>All questions have been satisfactorily addressed prior to signing.</li>
            <li>The Tenant fully accepts all <strong>rights, responsibilities, and obligations</strong> set forth herein.</li>
            <li><strong>Violation</strong> of the terms may result in <strong>legal and/or financial consequences</strong>.</li>
        </ul>
        
        <p>The Tenant also affirms that they have received a <strong>signed copy</strong> of this Contract of Tenancy and that this document serves as a <strong>formal declaration of understanding and agreement</strong> to all stipulated conditions.</p>
        
        <p><strong>IN WITNESS WHEREOF</strong>, the parties hereunto affix their signatures on the date and at the place first above written.</p>
        
        <div class="signature">
            <div class="signature-line"></div>
            <p>[LANDLORD'S FULL NAME]</p>
            <p>Landlord</p>
            
            <div class="signature-line" style="margin-top: 60px;"></div>
            <p><?= htmlspecialchars($tenant['first_name'].' '.$tenant['last_name']) ?></p>
            <p>Tenant</p>
        </div>
        
        <h3>SIGNED IN THE PRESENCE OF:</h3>
        
        <div style="display: flex; justify-content: space-between; margin-top: 40px;">
            <div>
                <div class="signature-line" style="width: 250px;"></div>
                <p>Name & Signature</p>
            </div>
            <div>
                <div class="signature-line" style="width: 250px;"></div>
                <p>Name & Signature</p>
            </div>
        </div>
        
        <h3>ACKNOWLEDGMENT</h3>
        <p><strong>REPUBLIC OF THE PHILIPPINES</strong></p>
        <p>(_____________________________ ) S.S.</p>
        
        <p>BEFORE ME, a Notary Public, for and in the above jurisdiction, this <?= date('jS') ?> day of <?= date('F, Y') ?>, personally appeared the following:</p>
        
        <ul class="terms-list">
            <li>[Landlord's Full Name], with valid ID No. ____________________, issued on ____________________ at ____________________;</li>
            <li><?= htmlspecialchars($tenant['first_name'].' '.$tenant['last_name']) ?>, with valid ID No. ____________________, issued on ____________________ at ____________________;</li>
        </ul>
        
        <p>known to me and to me known to be the same persons who executed the foregoing Contract of Tenancy, and they acknowledged to me that the same is their free and voluntary act and deed.</p>
        
        <p>IN WITNESS WHEREOF, I have hereunto set my hand and affixed my notarial seal on the date and place first above written.</p>
        
        <div style="margin-top: 40px;">
            <p><strong>NOTARY PUBLIC</strong></p>
            <p>Doc. No. ______;</p>
            <p>Page No. ______;</p>
            <p>Book No. ______;</p>
            <p>Series of 20____.</p>
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 8px 20px; background: #4CAF50; color: white; border: none; cursor: pointer;">
            Print Contract
        </button>
        <a href="tenant.php" style="padding: 8px 20px; background: #f44336; color: white; text-decoration: none; display: inline-block; margin-left: 10px;">
            Back to Tenants
        </a>
    </div>

    <script>
        // Auto-print after short delay
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        };
    </script>
</body>
</html>