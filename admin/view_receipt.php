
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
    SELECT p.*, t.first_name, t.last_name, t.mobile_no, t.tenant_id,
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

// Fetch current academic year for fallback
$academicYearStmt = $conn->query("SELECT academic_year_id FROM academic_years WHERE is_current = 1");
$currentAcademicYearId = $academicYearStmt->fetch_assoc()['academic_year_id'] ?? 0;

// Initialize reason display for Notes section
$notes = htmlspecialchars($payment['reason'] ?: '');
$guest_total = 0.00;
$other_charges = [];

// Fetch guest stay details for Monthly Rent payments
if ($payment['payment_type'] == 'Monthly Rent' && !empty($payment['payment_for_month_of'])) {
    $year = substr($payment['payment_for_month_of'], 0, 4);
    $month_num = substr($payment['payment_for_month_of'], 5, 2);
    $academic_year_id = $payment['academic_year_id'] ?: $currentAcademicYearId;

    $guest_stmt = $conn->prepare("
        SELECT stay_date, charge
        FROM guest_stays
        WHERE tenant_id = ? AND YEAR(stay_date) = ? AND MONTH(stay_date) = ? AND academic_year_id = ?
    ");
    $guest_stmt->bind_param('iiii', $payment['tenant_id'], $year, $month_num, $academic_year_id);
    $guest_stmt->execute();
    $guest_result = $guest_stmt->get_result();
    $guest_reasons = [];

    while ($guest = $guest_result->fetch_assoc()) {
        $stay_date = date('Y-m-d', strtotime($guest['stay_date']));
        $guest_reasons[] = "Guest Stay on $stay_date: ₱" . number_format($guest['charge'], 2);
        $guest_total += (float)$guest['charge'];
    }
    $guest_stmt->close();

    if (!empty($guest_reasons)) {
        // Remove generic "Guest Stay Charges" to avoid duplication
        $notes = preg_replace('/Guest Stay Charges: ₱[0-9,.]+(; )?/', '', $notes);
        $notes = trim($notes, '; ');
        // Append detailed guest stay reasons
        $notes = empty($notes)
            ? implode('; ', $guest_reasons)
            : $notes . '; ' . implode('; ', $guest_reasons);
    }
}

// Parse other charges from reason (e.g., surcharges, penalties)
if (!empty($payment['reason'])) {
    $reason_parts = explode('; ', $payment['reason']);
    foreach ($reason_parts as $part) {
        if (preg_match('/^(Water Bill Surcharge|Electric Bill Surcharge|Late Penalty|Advance Payment Applied): ₱([0-9,.]+)/', $part, $matches)) {
            $charge_name = $matches[1];
            $charge_amount = (float)str_replace(',', '', $matches[2]);
            // Negate advance payment for correct totaling
            if ($charge_name == 'Advance Payment Applied') {
                $charge_amount = -$charge_amount;
            }
            $other_charges[] = ['name' => $charge_name, 'amount' => $charge_amount];
        }
    }
}

// Set notes to 'None' if empty
$notes = empty($notes) ? 'None' : $notes;
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
                <?php if ($guest_total > 0): ?>
                <tr>
                    <td>Guest Stay Charges</td>
                    <td>₱<?php echo number_format($guest_total, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($other_charges as $charge): ?>
                <tr>
                    <td><?php echo htmlspecialchars($charge['name']); ?></td>
                    <td><?php echo $charge['amount'] < 0 ? '-₱' : '₱'; ?><?php echo number_format(abs($charge['amount']), 2); ?></td>
                </tr>
                <?php endforeach; ?>
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
        
        <div class="mb-3">
            <h5>Notes</h5>
            <p><?php echo htmlspecialchars($notes); ?></p>
        </div>
        
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
