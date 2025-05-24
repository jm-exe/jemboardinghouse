<?php
session_start();
require_once '../connection/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get payment_id from URL or session
$paymentId = $_GET['payment_id'] ?? ($_SESSION['new_tenant_ids']['payment_id'] ?? null);

// Validate payment ID
if (!$paymentId || !is_numeric($paymentId)) {
    die('<div style="padding:20px; border:1px solid #f00; color:#f00;">
            <h3>Error Generating Receipt</h3>
            <p>Payment reference missing. Please:</p>
            <ol>
                <li>Return to <a href="tenant.php">dashboard</a></li>
                <li>Find the tenant in the list</li>
                <li>Use the "Print Receipt" option there</li>
            </ol>
        </div>');
}

$paymentId = (int)$paymentId;

try {
    // Check database connection
    if (!$conn) {
        throw new Exception("Could not connect to database");
    }

    // Get payment details with all related information
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            t.first_name, t.last_name, t.mobile_no,
            bg.start_date, bg.due_date,
            b.bed_no, b.monthly_rent,
            r.room_no,
            f.floor_no
        FROM payments p
        JOIN boarding bg ON p.boarding_id = bg.boarding_id
        JOIN tenants t ON bg.tenant_id = t.tenant_id
        JOIN beds b ON bg.bed_id = b.bed_id
        JOIN rooms r ON b.room_id = r.room_id
        JOIN floors f ON r.floor_id = f.floor_id
        WHERE p.payment_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $paymentId);
    if (!$stmt->execute()) {
        throw new Exception("Database query failed: " . $stmt->error);
    }
    
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$payment) {
        throw new Exception("Payment record not found for ID: $paymentId");
    }

    // Format dates safely with null checks
    $formatDate = function($date) {
        return $date && strtotime($date) ? date('F j, Y', strtotime($date)) : 'Not specified';
    };

    $dates = [
        'payment' => $formatDate($payment['payment_date'] ?? null),
        'start' => $formatDate($payment['start_date'] ?? null),
        'due' => $formatDate($payment['due_date'] ?? null)
    ];

    // Calculate amounts with null checks
    $baseRent = (float)($payment['monthly_rent'] ?? 0);
    $applianceCharges = (float)($payment['appliance_charges'] ?? 0);
    $totalAmount = $baseRent + $applianceCharges;

    // Process appliances safely
    $appliances = [];
    if (!empty($payment['appliances']) && $payment['appliances'] !== 'None' && is_string($payment['appliances'])) {
        $appliances = explode(', ', $payment['appliances']);
    }

} catch (Exception $e) {
    die('<div style="padding:20px; border:1px solid #f00; color:#f00;">
            <h3>Error Generating Receipt</h3>
            <p>'.htmlspecialchars($e->getMessage()).'</p>
            <p>Reference: PAY-'.htmlspecialchars($paymentId).'</p>
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
    <title>Receipt #<?= safe($paymentId) ?></title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f9f9f9;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .receipt-title {
            font-size: 20px;
            margin: 10px 0;
            color: #2c3e50;
        }
        .receipt-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .meta-section {
            flex: 1;
            min-width: 250px;
        }
        .info-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            border-top: 2px solid #333;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.9em;
            color: #666;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
        }
        .no-print {
            text-align: center;
            margin-top: 30px;
        }
        @media print {
            body {
                padding: 0;
                background: white;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="logo">BOARDING HOUSE MANAGEMENT SYSTEM</div>
            <div class="receipt-title">OFFICIAL PAYMENT RECEIPT</div>
            <div>123 Dormitory Lane, University Town</div>
            <div>Contact: (123) 456-7890 | billing@boardinghouse.edu</div>
        </div>
        
        <div class="receipt-meta">
            <div class="meta-section">
                <div class="info-label">Tenant Information</div>
                <div><strong>Name:</strong> <?= safe($payment['first_name'] ?? '') . ' ' . safe($payment['last_name'] ?? '') ?></div>
                <div><strong>Contact:</strong> <?= safe($payment['mobile_no'] ?? '') ?></div>
            </div>
            
            <div class="meta-section">
                <div class="info-label">Payment Details</div>
                <div><strong>Receipt #:</strong> <?= safe($paymentId) ?></div>
                <div><strong>Date:</strong> <?= safe($dates['payment']) ?></div>
                <div><strong>Method:</strong> <?= safe($payment['method'] ?? '') ?></div>
            </div>
            
            <div class="meta-section">
                <div class="info-label">Accommodation</div>
                <div><strong>Room:</strong> Floor <?= safe($payment['floor_no'] ?? '') ?>, Room <?= safe($payment['room_no'] ?? '') ?></div>
                <div><strong>Bed:</strong> #<?= safe($payment['bed_no'] ?? '') ?></div>
                <div><strong>Period:</strong> <?= safe($dates['start']) ?> 
                    <?= $dates['due'] ? ' to ' . safe($dates['due']) : '' ?></div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Monthly Rent (Bed #<?= safe($payment['bed_no'] ?? '') ?>)</td>
                    <td>₱<?= number_format($baseRent, 2) ?></td>
                </tr>
                
                <?php if (!empty($appliances)): ?>
                    <tr>
                        <td colspan="2"><strong>Additional Appliances:</strong></td>
                    </tr>
                    <?php foreach ($appliances as $appliance): ?>
                    <tr>
                        <td><?= safe($appliance) ?></td>
                        <td>₱100.00</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><strong>Total Appliance Charges</strong></td>
                        <td>₱<?= number_format($applianceCharges, 2) ?></td>
                    </tr>
                <?php endif; ?>
                
                <tr class="total-row">
                    <td><strong>TOTAL AMOUNT PAID</strong></td>
                    <td><strong>₱<?= number_format($totalAmount, 2) ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="signature">
            <div class="signature-line">Tenant's Signature</div>
            <div class="signature-line">Landlord</div>
        </div>
        
        <div class="footer">
            <p>This is an official receipt. Please keep it for your records.</p>
            <p>Thank you for your payment!</p>
        </div>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()" style="
            padding: 10px 20px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        ">
            Print Receipt
        </button>
        <button onclick="window.close()" style="
            padding: 10px 20px;
            background: #7f8c8d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 16px;
        ">
            Close Window
        </button>
    </div>
    
    <script>
        // Auto-print only when coming from registration flow
        if (new URLSearchParams(window.location.search).has('auto_print')) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>