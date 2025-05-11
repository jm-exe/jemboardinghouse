<?php
session_start();
require_once 'connection/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. First try to get payment_id from URL
$paymentId = $_GET['payment_id'] ?? null;

// 2. If not in URL, try session
if (!$paymentId && isset($_SESSION['new_tenant_ids']['payment_id'])) {
    $paymentId = $_SESSION['new_tenant_ids']['payment_id'];
}

// 3. Validate payment ID
if (!$paymentId || !is_numeric($paymentId)) {
    die('<div style="padding:20px; border:1px solid #f00; color:#f00;">
            <h3>Error Generating Receipt</h3>
            <p>Payment reference missing. Please:</p>
            <ol>
                <li>Return to <a href="tenant.php">dashboard</a></li>
                <li>Find the tenant in the list</li>
                <li>Use the "Print Receipt" option there</li>
            </ol>
            <p>If the problem persists, contact support with the tenant name and payment date.</p>
        </div>');
}

$paymentId = (int)$paymentId;

try {
    // Check database connection
    if (!$conn) {
        throw new Exception("Could not connect to database");
    }

    // Get payment details
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ?");
    $stmt->bind_param("i", $paymentId);
    if (!$stmt->execute()) {
        throw new Exception("Database query failed");
    }
    
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$payment) {
        throw new Exception("Payment record not found");
    }

    // Get related records
    $queries = [
        'boarding' => "SELECT * FROM boarding WHERE boarding_id = ?",
        'tenant' => "SELECT * FROM tenants WHERE tenant_id = ?",
        'bed' => "SELECT * FROM beds WHERE bed_id = ?",
        'room' => "SELECT * FROM rooms WHERE room_id = ?",
        'floor' => "SELECT * FROM floors WHERE floor_id = ?"
    ];
    
    $data = ['payment' => $payment];
    
    foreach ($queries as $key => $query) {
        $id = match($key) {
            'boarding' => $payment['boarding_id'],
            'tenant' => $data['boarding']['tenant_id'],
            'bed' => $data['boarding']['bed_id'],
            'room' => $data['bed']['room_id'],
            'floor' => $data['room']['floor_id']
        };
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data[$key] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Format dates
    $dates = [
        'payment' => date('F j, Y', strtotime($data['payment']['payment_date'])),
        'start' => date('F j, Y', strtotime($data['boarding']['start_date'])),
        'due' => !empty($data['boarding']['due_date']) ? date('F j, Y', strtotime($data['boarding']['due_date'])) : null
    ];

} catch (Exception $e) {
    die('<div style="padding:20px; border:1px solid #f00; color:#f00;">
            <h3>Error Generating Receipt</h3>
            <p>'.htmlspecialchars($e->getMessage()).'</p>
            <p>Reference: PAY-'.htmlspecialchars($paymentId).'</p>
            <p>Please contact support with this reference.</p>
        </div>');
}

function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt for Payment #<?= safe($paymentId) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .receipt-body { max-width: 600px; margin: 0 auto; }
        .receipt-row { display: flex; margin-bottom: 10px; }
        .receipt-label { font-weight: bold; width: 150px; }
        .footer { margin-top: 40px; text-align: center; font-size: 0.9em; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="receipt-body">
        <div class="header">
            <h2>BOARDING HOUSE</h2>
            <h3>PAYMENT RECEIPT</h3>
            <p>Receipt #: <?= safe($paymentId) ?></p>
            <p>Date: <?= safe($dates['payment']) ?></p>
        </div>
        
        <div class="details">
            <div class="receipt-row">
                <div class="receipt-label">Tenant:</div>
                <div><?= safe($data['tenant']['first_name'] . ' ' . safe($data['tenant']['last_name'])) ?></div>
            </div>
            <div class="receipt-row">
                <div class="receipt-label">Room:</div>
                <div>Floor <?= safe($data['floor']['floor_no']) ?>, Room <?= safe($data['room']['room_no']) ?>, Bed <?= safe($data['bed']['bed_no']) ?></div>
            </div>
            <div class="receipt-row">
                <div class="receipt-label">Amount:</div>
                <div>₱<?= number_format($data['payment']['payment_amount'], 2) ?></div>
            </div>
            <div class="receipt-row">
                <div class="receipt-label">Method:</div>
                <div><?= safe($data['payment']['method']) ?></div>
            </div>
            <div class="receipt-row">
                <div class="receipt-label">Period:</div>
                <div>
                    <?= safe($dates['start']) ?>
                    <?php if ($dates['due']): ?>
                    - <?= safe($dates['due']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an official receipt</p>
            <p>Thank you for your payment!</p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer;">
            Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; cursor: pointer; margin-left: 10px;">
            Close Window
        </button>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }