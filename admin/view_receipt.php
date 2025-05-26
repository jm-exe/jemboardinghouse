<?php
session_start();
require_once '../connection/db.php';

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Check if payment_id is provided
if (!isset($_GET['payment_id'])) {
    $_SESSION['error'] = "No payment specified";
    header("Location: payments.php");
    exit();
}

$payment_id = $_GET['payment_id'];

// Fetch payment details with correct columns from your database
$stmt = $conn->prepare("
    SELECT p.*, t.first_name, t.last_name, t.mobile_no,
           b.bed_id, bed.monthly_rent,
           r.room_no,
           bed.bed_no, bed.deck
    FROM payments p
    JOIN boarding b ON p.boarding_id = b.boarding_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
    JOIN beds bed ON b.bed_id = bed.bed_id
    JOIN rooms r ON bed.room_id = r.room_id
    WHERE p.payment_id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    $_SESSION['error'] = "Payment not found";
    header("Location: payments.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .receipt-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .receipt-details {
            margin-bottom: 30px;
        }
        .receipt-footer {
            margin-top: 30px;
            border-top: 2px solid #eee;
            padding-top: 20px;
            text-align: center;
            font-style: italic;
            color: #666;
        }
        .print-only {
            display: none;
        }
        @media print {
            body {
                background-color: white;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container receipt-container">
        <div class="no-print text-end mb-3">
            <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
            <a href="payments.php" class="btn btn-secondary">Back to Payments</a>
        </div>
        
        <div class="receipt-header">
            <div class="receipt-title">PAYMENT RECEIPT</div>
            <div class="print-only">
                <p>Boarding House Management System</p>
                <p>Generated on: <?php echo date('F j, Y h:i A'); ?></p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Tenant Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($payment['mobile_no']); ?></p>
            </div>
            <div class="col-md-6">
                <h5>Payment Details</h5>
                <p><strong>Receipt #:</strong> <?php echo htmlspecialchars($payment['payment_id']); ?></p>
                <p><strong>Date Paid:</strong> <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['method']); ?></p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Room Information</h5>
                <p><strong>Room #:</strong> <?php echo htmlspecialchars($payment['room_no']); ?></p>
                <p><strong>Bed #:</strong> <?php echo htmlspecialchars($payment['bed_no']); ?> (<?php echo htmlspecialchars($payment['deck']); ?>)</p>
                <p><strong>Monthly Rent:</strong> ₱<?php echo number_format($payment['monthly_rent'], 2); ?></p>
            </div>
            <div class="col-md-6">
                <h5>Payment Period</h5>
                <p><strong>For Month of:</strong> 
                    <?php 
                    if (!empty($payment['payment_for_month_of'])) {
                        echo date('F Y', strtotime($payment['payment_for_month_of']));
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </p>
                <p><strong>Payment Type:</strong> <?php echo htmlspecialchars($payment['payment_type']); ?></p>
            </div>
        </div>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Monthly Rent</td>
                    <td>₱<?php echo number_format($payment['monthly_rent'], 2); ?></td>
                </tr>
                <?php if (!empty($payment['appliances']) && $payment['appliance_charges'] > 0): ?>
                <tr>
                    <td>Appliance Charges (<?php echo htmlspecialchars($payment['appliances']); ?>)</td>
                    <td>₱<?php echo number_format($payment['appliance_charges'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="table-active">
                    <td><strong>Total Amount Paid</strong></td>
                    <td><strong>₱<?php echo number_format($payment['payment_amount'], 2); ?></strong></td>
                </tr>
                <?php if ($payment['balance'] > 0): ?>
                <tr class="table-warning">
                    <td><strong>Remaining Balance</strong></td>
                    <td><strong>₱<?php echo number_format($payment['balance'], 2); ?></strong></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($payment['reason'])): ?>
        <div class="mb-3">
            <h5>Notes</h5>
            <p><?php echo htmlspecialchars($payment['reason']); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="receipt-footer">
            <p>Thank you for your payment!</p>
            <p>This is an official receipt from Boarding House Management System</p>
        </div>
        
        <div class="no-print text-center mt-4">
            <p>This receipt was generated electronically and does not require a signature.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>