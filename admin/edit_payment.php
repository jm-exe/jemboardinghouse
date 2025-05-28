<?php
session_start();
require_once '../connection/db.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

if (!isset($_GET['payment_id']) && !isset($_POST['new_payment'])) {
    header("Location: payments.php");
    exit();
}

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle new advance or other payment
$newPayment = isset($_POST['new_payment']) ? $_POST['new_payment'] : null;
$paymentId = $newPayment ? null : $_GET['payment_id'];

if ($newPayment) {
    $tenantId = $_POST['tenant_id'];
    $boardingId = $_POST['boarding_id'];
    $paymentType = $_POST['payment_type'] ?? $newPayment;
    $month = $_POST['payment_for_month_of'] ?? date('Y-m');
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $tenderAmount = floatval($_POST['tender_amount'] ?? 0);
    $reason = $_POST['reason'] ?? '';
    $appliancesStr = '';
    $applianceCharges = 0.00;
    $monthlyRent = 0.00;
} else {
    $paymentStmt = $conn->prepare("
        SELECT p.*, t.tenant_id, t.first_name, t.last_name, b.boarding_id, b.bed_id
        FROM payments p
        JOIN boarding b ON p.boarding_id = b.boarding_id
        JOIN tenants t ON b.tenant_id = t.tenant_id
        WHERE p.payment_id = ?
    ");
    $paymentStmt->bind_param('i', $paymentId);
    $paymentStmt->execute();
    $payment = $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();

    if (!$payment) {
        $_SESSION['error'] = "Payment not found.";
        header("Location: payments.php");
        exit();
    }
    $tenantId = $payment['tenant_id'];
    $boardingId = $payment['boarding_id'];
    $month = substr($payment['payment_for_month_of'], 0, 7);
    $paymentType = $payment['payment_type'];
}

// Fetch business details
$businessStmt = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'business_name'");
$businessName = $businessStmt->fetch_assoc()['setting_value'] ?? 'Radyx BH';

// Fetch appliances for table
$appliancesStmt = $conn->query("SELECT appliance_id, appliance_name, rate FROM appliances");
$appliances = $appliancesStmt->fetch_all(MYSQLI_ASSOC);

// Fetch monthly rent if Monthly Rent payment
$monthlyRent = 0.00;
if ($paymentType === 'Monthly Rent' && !$newPayment) {
    $bedStmt = $conn->prepare("SELECT monthly_rent FROM beds WHERE bed_id = ?");
    $bedStmt->bind_param('i', $payment['bed_id']);
    $bedStmt->execute();
    $monthlyRent = $bedStmt->get_result()->fetch_assoc()['monthly_rent'] ?? 1100.00;
    $bedStmt->close();
}

// Fetch advance payments for Monthly Rent
$advanceAmount = 0.00;
if ($paymentType === 'Monthly Rent' && !$newPayment) {
    $advanceStmt = $conn->prepare("
        SELECT SUM(payment_amount) as total_advance
        FROM payments
        WHERE boarding_id = ? AND payment_for_month_of = ? AND payment_type = 'Advance' AND method = 'Cash'
    ");
    $advanceStmt->bind_param('is', $boardingId, $month);
    $advanceStmt->execute();
    $advanceAmount = $advanceStmt->get_result()->fetch_assoc()['total_advance'] ?? 0.00;
    $advanceStmt->close();
}

// Fetch tenants for dropdown in new payment form
$tenantsStmt = $conn->query("
    SELECT t.tenant_id, t.first_name, t.last_name, b.boarding_id
    FROM tenants t
    JOIN boarding b ON t.tenant_id = b.tenant_id
    WHERE b.due_date >= CURDATE()
");
$tenants = $tenantsStmt->fetch_all(MYSQLI_ASSOC);

// Initialize error variable
$error = '';

// Function to calculate utility surcharges, late penalties, and guest charges
function calculateSurchargesAndPenalties($conn, $academicYearId, $month, $paymentDate, $boardingId, $tenantId) {
    $surcharge = 0.00;
    $latePenalty = 0.00;
    $guestCharges = 0.00;
    $advanceAmount = 0.00;
    $reason = [];

    if (!$academicYearId) {
        $academicYearStmt = $conn->query("SELECT academic_year_id FROM academic_years WHERE is_current = 1");
        $academicYearId = $academicYearStmt->fetch_assoc()['academic_year_id'] ?? 0;
    }

    $advanceStmt = $conn->prepare("
        SELECT SUM(payment_amount) as total_advance
        FROM payments
        WHERE boarding_id = ? AND payment_for_month_of = ? AND payment_type = 'Advance' AND method = 'Cash'
    ");
    $advanceStmt->bind_param('is', $boardingId, $month);
    $advanceStmt->execute();
    $advanceAmount = $advanceStmt->get_result()->fetch_assoc()['total_advance'] ?? 0.00;
    $advanceStmt->close();
    if ($advanceAmount > 0) {
        $reason[] = "Advance Payment Applied: ₱" . number_format($advanceAmount, 2);
    }

    $expenseStmt = $conn->prepare("
        SELECT e.description, me.amount
        FROM monthly_expenses me
        JOIN expenses e ON me.expense_id = e.expense_id
        WHERE me.academic_year_id = ? AND me.month = ?
        AND e.description IN ('Water Bill', 'Electric Bill')
    ");
    $monthStart = $month . '-01';
    $expenseStmt->bind_param('is', $academicYearId, $monthStart);
    $expenseStmt->execute();
    $expenses = $expenseStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $expenseStmt->close();

    foreach ($expenses as $expense) {
        if ($expense['description'] == 'Water Bill' && $expense['amount'] > 3000.00) {
            $surcharge += 100.00;
            $reason[] = "Water Bill Surcharge: ₱100.00";
        }
        if ($expense['description'] == 'Electric Bill' && $expense['amount'] > 5000.00) {
            $surcharge += 100.00;
            $reason[] = "Electric Bill Surcharge: ₱100.00";
        }
    }

    $guestStmt = $conn->prepare("
        SELECT SUM(charge) as total_guest_charges
        FROM guest_stays
        WHERE tenant_id = ? AND YEAR(stay_date) = ? AND MONTH(stay_date) = ? AND academic_year_id = ?
    ");
    $year = substr($month, 0, 4);
    $monthNum = substr($month, 5, 2);
    $guestStmt->bind_param('iiii', $tenantId, $year, $monthNum, $academicYearId);
    $guestStmt->execute();
    $guestResult = $guestStmt->get_result()->fetch_assoc();
    $guestCharges = (float)($guestResult['total_guest_charges'] ?? 0.00);
    $guestStmt->close();
    if ($guestCharges > 0) {
        $reason[] = "Guest Stay Charges: ₱" . number_format($guestCharges, 2);
    }

    $dueDate = new DateTime($month . '-07');
    $gracePeriodEnd = new DateTime($month . '-21');
    $paymentDateObj = new DateTime($paymentDate);
    if ($paymentDateObj > $gracePeriodEnd) {
        $weeksLate = ceil(($paymentDateObj->getTimestamp() - $gracePeriodEnd->getTimestamp()) / (7 * 24 * 3600));
        $latePenalty = $weeksLate * 50.00;
        $reason[] = "Late Penalty: ₱" . number_format($latePenalty, 2);
    }

    return [
        'surcharge' => $surcharge,
        'latePenalty' => $latePenalty,
        'guestCharges' => $guestCharges,
        'advanceAmount' => $advanceAmount,
        'reason' => implode('; ', $reason)
    ];
}

// Function to generate or update receipt
function handleReceipt($conn, $paymentId, $tenantId, $amount, $paymentDate, $appliancesStr, $boardingId, $reason, $paymentType, $monthlyRent) {
    $description = $paymentType === 'Monthly Rent' ? "Monthly Rent: ₱" . number_format($monthlyRent, 2) : $paymentType;
    if ($appliancesStr) {
        $description .= "\nAppliances: $appliancesStr";
    }
    if ($reason) {
        $description .= "\n" . $reason;
    }

    $receiptStmt = $conn->prepare("SELECT receipt_id, receipt_number FROM receipts WHERE payment_id = ?");
    $receiptStmt->bind_param('i', $paymentId);
    $receiptStmt->execute();
    $receipt = $receiptStmt->get_result()->fetch_assoc();
    $receiptStmt->close();

    if ($receipt) {
        $updateReceiptStmt = $conn->prepare("
            UPDATE receipts 
            SET amount = ?, receipt_date = ?, description = ?
            WHERE payment_id = ?
        ");
        $updateReceiptStmt->bind_param('dssi', $amount, $paymentDate, $description, $paymentId);
        $updateReceiptStmt->execute();
        $updateReceiptStmt->close();
    } else {
        $today = new DateTime();
        $dateStr = $today->format('Ymd');
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM receipts WHERE receipt_number LIKE ?");
        $likePattern = "REC-$dateStr%";
        $countStmt->bind_param('s', $likePattern);
        $countStmt->execute();
        $count = $countStmt->get_result()->fetch_assoc()['count'] + 1;
        $countStmt->close();
        $receiptNumber = sprintf("REC-%s-%03d", $dateStr, $count);

        $insertReceiptStmt = $conn->prepare("
            INSERT INTO receipts (payment_id, receipt_number, tenant_id, amount, receipt_date, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertReceiptStmt->bind_param('isidss', $paymentId, $receiptNumber, $tenantId, $amount, $paymentDate, $description);
        $insertReceiptStmt->execute();
        $insertReceiptStmt->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$newPayment) {
    $paymentAmount = floatval($_POST['payment_amount']);
    $paymentDate = $_POST['payment_date'];
    $tenderAmount = floatval($_POST['tender_amount']);
    $selectedAppliances = isset($_POST['appliances']) ? $_POST['appliances'] : [];
    $applianceQuantities = isset($_POST['appliance_quantities']) ? $_POST['appliance_quantities'] : [];
    $partialReason = $_POST['partial_reason'] ?? '';

    // Calculate appliance charges
    $applianceCharges = 0.00;
    $applianceList = [];
    foreach ($selectedAppliances as $applianceId) {
        $quantity = isset($applianceQuantities[$applianceId]) ? intval($applianceQuantities[$applianceId]) : 1;
        foreach ($appliances as $appliance) {
            if ($appliance['appliance_id'] == $applianceId) {
                $applianceCharges += $appliance['rate'] * $quantity;
                $applianceList[] = $appliance['appliance_name'] . ' (' . $quantity . ')';
                break;
            }
        }
    }
    $appliancesStr = implode(', ', $applianceList);

    // Calculate previous balance (excluding current payment)
    $balanceStmt = $conn->prepare("
        SELECT SUM(balance) as total_balance
        FROM payments
        WHERE boarding_id = ? AND payment_for_month_of < ? AND payment_type = 'Monthly Rent' AND payment_id != ?
    ");
    $balanceStmt->bind_param('isi', $payment['boarding_id'], $month, $paymentId);
    $balanceStmt->execute();
    $previousBalance = $balanceStmt->get_result()->fetch_assoc()['total_balance'] ?? 0.00;
    $balanceStmt->close();

    // Calculate surcharges, penalties, and guest charges
    $charges = calculateSurchargesAndPenalties($conn, $payment['academic_year_id'], $month, $paymentDate, $payment['boarding_id'], $tenantId);
    $totalAmount = $monthlyRent + $applianceCharges + $charges['surcharge'] + $charges['latePenalty'] + $charges['guestCharges'] + $previousBalance - $charges['advanceAmount'];
    $totalAmount = max(0, $totalAmount);
    $reason = $charges['reason'];
    if ($previousBalance > 0) {
        $reason .= ($reason ? '; ' : '') . "Previous Balance: ₱" . number_format($previousBalance, 2);
    }

    // Handle partial payment
    $balance = $tenderAmount < $totalAmount ? $totalAmount - $tenderAmount : 0.00;
    if ($balance > 0 && $partialReason) {
        $reason .= ($reason ? '; ' : '') . "Partial Payment: ₱" . number_format($tenderAmount, 2) . " (Reason: $partialReason)";
    } elseif ($balance > 0 && !$partialReason) {
        $error = "Partial payment detected. Please provide a reason for the partial payment.";
    } else {
        // Validate tender amount
        if ($tenderAmount < 0) {
            $error = "Tender amount cannot be negative.";
        } else {
            // Update payment
            $updateStmt = $conn->prepare("
                UPDATE payments 
                SET payment_amount = ?, payment_date = ?, method = 'Cash', appliance_charges = ?, appliances = ?, reason = ?, balance = ?
                WHERE payment_id = ?
            ");
            $updateStmt->bind_param('dsdssid', 
                $totalAmount, 
                $paymentDate, 
                $applianceCharges, 
                $appliancesStr, 
                $reason, 
                $balance, 
                $paymentId
            );
            $updateStmt->execute();
            $updateStmt->close();

            // Generate or update receipt
            handleReceipt($conn, $paymentId, $payment['tenant_id'], $tenderAmount, $paymentDate, $appliancesStr, $payment['boarding_id'], $reason, $paymentType, $monthlyRent);

            $_SESSION['success'] = "Payment processed successfully.";
            header("Location: payments.php");
            exit();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $newPayment) {
    if ($tenderAmount < 0) {
        $error = "Tender amount cannot be negative.";
    } elseif ($tenderAmount == 0) {
        $error = "Tender amount must be greater than zero.";
    } else {
        $userStmt = $conn->prepare("SELECT user_id FROM tenants WHERE tenant_id = ?");
        $userStmt->bind_param('i', $tenantId);
        $userStmt->execute();
        $userId = $userStmt->get_result()->fetch_assoc()['user_id'];
        $userStmt->close();

        $academicYearStmt = $conn->query("SELECT academic_year_id FROM academic_years WHERE is_current = 1");
        $academicYearId = $academicYearStmt->fetch_assoc()['academic_year_id'] ?? 0;

        $insertStmt = $conn->prepare("
            INSERT INTO payments 
            (user_id, boarding_id, payment_amount, payment_date, method, appliance_charges, appliances, academic_year_id, payment_for_month_of, reason, payment_type, balance)
            VALUES (?, ?, ?, ?, 'Cash', 0.00, '', ?, ?, ?, ?, 0.00)
        ");
        $insertStmt->bind_param('iidsisss', 
            $userId, 
            $boardingId, 
            $tenderAmount, 
            $paymentDate, 
            $academicYearId, 
            $month, 
            $reason, 
            $paymentType
        );
        $insertStmt->execute();
        $newPaymentId = $conn->insert_id;
        $insertStmt->close();

        handleReceipt($conn, $newPaymentId, $tenantId, $tenderAmount, $paymentDate, '', $boardingId, $reason, $paymentType, 0.00);

        $_SESSION['success'] = "Payment recorded successfully.";
        header("Location: payments.php");
        exit();
    }
}

// Calculate initial surcharges and penalties for display
$initialCharges = $newPayment ? ['surcharge' => 0.00, 'latePenalty' => 0.00, 'guestCharges' => 0.00, 'advanceAmount' => 0.00, 'reason' => ''] : calculateSurchargesAndPenalties($conn, $payment['academic_year_id'], $month, $payment['payment_date'], $boardingId, $tenantId);
// Calculate previous balance for display
$displayPreviousBalance = 0.00;
if (!$newPayment && $paymentType === 'Monthly Rent') {
    $balanceStmt = $conn->prepare("
        SELECT SUM(balance) as total_balance
        FROM payments
        WHERE boarding_id = ? AND payment_for_month_of < ? AND payment_type = 'Monthly Rent' AND payment_id != ?
    ");
    $balanceStmt->bind_param('isi', $boardingId, $month, $paymentId);
    $balanceStmt->execute();
    $displayPreviousBalance = $balanceStmt->get_result()->fetch_assoc()['total_balance'] ?? 0.00;
    $balanceStmt->close();
}
$initialReason = $newPayment ? '' : $initialCharges['reason'];
$displayReason = $initialReason ? str_replace(';', "\n", $initialReason) : 'None';
if ($displayPreviousBalance > 0) {
    $displayReason .= ($displayReason ? "\n" : '') . "Previous Balance: ₱" . number_format($displayPreviousBalance, 2);
}
// Modified: Set initial tender amount to grand total for Monthly Rent
$initialTenderAmount = isset($_POST['tender_amount']) ? floatval($_POST['tender_amount']) : ($newPayment ? $tenderAmount : $initialTotalAmount);
$initialTotalAmount = $paymentType === 'Monthly Rent' && !$newPayment 
    ? $monthlyRent + $payment['appliance_charges'] + $initialCharges['surcharge'] + $initialCharges['latePenalty'] + $initialCharges['guestCharges'] + $displayPreviousBalance - $initialCharges['advanceAmount']
    : ($newPayment ? $tenderAmount : $payment['payment_amount']);
$initialTotalAmount = max(0, $initialTotalAmount);
$isPartialPayment = ($paymentType === 'Monthly Rent' && !$newPayment && $initialTenderAmount < $initialTotalAmount && $initialTenderAmount > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $newPayment ? 'New Payment' : 'Pay #' . $paymentId; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .receipt-container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
        .receipt-header { text-align: center; margin-bottom: 20px; }
        .receipt-table, .appliance-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .receipt-table th, .receipt-table td, .appliance-table th, .appliance-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .receipt-table th, .appliance-table th { background-color: #f8f9fa; }
        .total-row { font-weight: bold; background-color: #e9ecef; }
        .reason-section { margin-top: 20px; }
        .appliance-table .form-control { width: 100px; display: inline-block; }
    </style>
</head>
<body>
<div class="container receipt-container">
    <div class="receipt-header">
        <h2><?php echo htmlspecialchars($businessName); ?></h2>
        <h4><?php echo $newPayment ? 'New Payment' : 'Pay #' . $paymentId; ?></h4>
        <?php if (!$newPayment): ?>
            <p>Tenant: <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
            <p>Boarding ID: <?php echo $payment['boarding_id']; ?></p>
        <?php endif; ?>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" id="paymentForm">
        <?php if ($newPayment): ?>
            <div class="mb-3">
                <label class="form-label">Tenant *</label>
                <select name="tenant_id" class="form-control" required>
                    <option value="">Select Tenant</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?php echo $tenant['tenant_id']; ?>" data-boarding-id="<?php echo $tenant['boarding_id']; ?>" 
                                <?php echo $tenantId == $tenant['tenant_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="boarding_id" id="boardingId" value="<?php echo htmlspecialchars($boardingId); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Payment Type *</label>
                <select name="payment_type" id="paymentType" class="form-control" required>
                    <option value="Advance" <?php echo $paymentType === 'Advance' ? 'selected' : ''; ?>>Advance</option>
                    <option value="Other" <?php echo $paymentType === 'Other' ? 'selected' : ''; ?>>Other (e.g., Guest Stay)</option>
                </select>
            </div>
            <div class="mb-3" id="monthField">
                <label class="form-label">Payment For Month (Advance Only)</label>
                <input type="month" name="payment_for_month_of" class="form-control" 
                       value="<?php echo htmlspecialchars($month); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Reason/Description *</label>
                <textarea name="reason" class="form-control" rows="3" required><?php echo htmlspecialchars($reason); ?></textarea>
            </div>
        <?php else: ?>
            <?php if ($paymentType === 'Monthly Rent'): ?>
                <h3>Appliance Additional</h3>
                <table class="appliance-table">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Appliance</th>
                            <th>Rate</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appliances as $appliance): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="appliances[]" value="<?php echo $appliance['appliance_id']; ?>" 
                                       class="form-check-input appliance-checkbox" 
                                       id="appliance_<?php echo $appliance['appliance_id']; ?>"
                                       data-rate="<?php echo $appliance['rate']; ?>"
                                       <?php echo (isset($_POST['appliances']) && in_array($appliance['appliance_id'], $_POST['appliances'])) || 
                                                 (!isset($_POST['appliances']) && strpos($payment['appliances'], $appliance['appliance_name']) !== false) ? 'checked' : ''; ?>>
                            </td>
                            <td><?php echo htmlspecialchars($appliance['appliance_name']); ?></td>
                            <td>₱<?php echo number_format($appliance['rate'], 2); ?></td>
                            <td>
                                <input type="number" name="appliance_quantities[<?php echo $appliance['appliance_id']; ?>]" 
                                       class="form-control appliance-quantity" 
                                       value="<?php echo isset($_POST['appliance_quantities'][$appliance['appliance_id']]) ? 
                                                     htmlspecialchars($_POST['appliance_quantities'][$appliance['appliance_id']]) : 
                                                     (strpos($payment['appliances'], $appliance['appliance_name']) !== false ? '1' : '1'); ?>" 
                                       min="1">
                            </td>
                            <td>₱<span class="appliance-total" data-appliance-id="<?php echo $appliance['appliance_id']; ?>">
                                <?php 
                                $quantity = isset($_POST['appliance_quantities'][$appliance['appliance_id']]) ? 
                                            intval($_POST['appliance_quantities'][$appliance['appliance_id']]) : 
                                            (strpos($payment['appliances'], $appliance['appliance_name']) !== false ? 1 : 0);
                                echo number_format($quantity * $appliance['rate'], 2); 
                                ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($paymentType === 'Monthly Rent'): ?>
                        <tr>
                            <td>Monthly Rent</td>
                            <td>₱<span id="rentAmount"><?php echo number_format($monthlyRent, 2); ?></span></td>
                        </tr>
                        <tr>
                            <td>Appliances</td>
                            <td>₱<span id="applianceAmount"><?php echo number_format($payment['appliance_charges'], 2); ?></span></td>
                        </tr>
                        <tr>
                            <td>Guest Stay Charges</td>
                            <td>₱<span id="guestChargeAmount"><?php echo number_format($initialCharges['guestCharges'], 2); ?></span></td>
                        </tr>
                        <tr class="total-row">
                            <td>Subtotal</td>
                            <td>₱<span id="subtotalAmount"><?php echo number_format($monthlyRent + $payment['appliance_charges'] + $initialCharges['guestCharges'], 2); ?></span></td>
                        </tr>
                        <tr>
                            <td>Utility Surcharge</td>
                            <td>₱<span id="surchargeAmount"><?php echo number_format($initialCharges['surcharge'], 2); ?></span></td>
                        </tr>
                        <tr>
                            <td>Late Penalty</td>
                            <td>₱<span id="penaltyAmount"><?php echo number_format($initialCharges['latePenalty'], 2); ?></span></td>
                        </tr>
                        <tr>
                            <td>Previous Balance</td>
                            <td>₱<span id="previousBalance"><?php echo number_format($displayPreviousBalance, 2); ?></span></td>
                        </tr>
                        <tr>
                            <td>Advance Payment Applied</td>
                            <td>-₱<span id="advanceAmount"><?php echo number_format($initialCharges['advanceAmount'], 2); ?></span></td>
                        </tr>
                        <tr class="total-row">
                            <td>Grand Total</td>
                            <td><h1><strong>₱<span id="totalAmount"><?php echo number_format($initialTotalAmount, 2); ?></strong></h1></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td><?php echo htmlspecialchars($paymentType); ?></td>
                            <td>₱<span id="totalAmount"><?php echo number_format($payment['payment_amount'], 2); ?></span></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <!-- Modified: Dynamic partial payment alert -->
            <div id="partialPaymentAlert" class="alert alert-warning alert-dismissible fade show <?php echo $isPartialPayment ? '' : 'd-none'; ?>" role="alert">
                <strong>Partial Payment:</strong> The tender amount (₱<span id="tenderAmountDisplay"><?php echo number_format($initialTenderAmount, 2); ?></span>) is less than the grand total (₱<span id="totalAmountDisplay"><?php echo number_format($initialTotalAmount, 2); ?></span>). Please provide a reason for the partial payment below.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <div class="reason-section">
                <label class="form-label">Reason for Additional Charges</label>
                <textarea class="form-control" id="reason" rows="4" disabled><?php echo htmlspecialchars($displayReason); ?></textarea>
            </div>
            <div class="mt-3">
                <label class="form-label">Partial Payment Reason (if applicable)</label>
                <textarea id="partial_reason" name="partial_reason" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['partial_reason'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>
        <div class="mt-3">
            <label class="form-label">Payment Date *</label>
            <input id="paymentDate" type="date" name="payment_date" 
                   value="<?php echo isset($_POST['payment_date']) ? htmlspecialchars($_POST['payment_date']) : ($newPayment ? date('Y-m-d') : $payment['payment_date']); ?>" 
                   class="form-control" required>
        </div>
        <div class="mt-3">
            <label class="form-label">Tender Amount *</label>
            <input id="tenderAmount" type="number" name="tender_amount" 
                   value="<?php echo isset($_POST['tender_amount']) ? htmlspecialchars($_POST['tender_amount']) : number_format($initialTotalAmount, 2); ?>" 
                   step="0.01" min="0" class="form-control" required>
            <div id="errorAmount" class="invalid-feedback"></div>
        </div>
        <div class="mt-3">
            <label class="form-label">Change</label>
            <input id="changeAmount" type="text" value="0.00" class="form-control" readonly>
        </div>
        <input type="hidden" name="payment_amount" id="paymentAmount" value="<?php echo $newPayment ? $tenderAmount : $initialTotalAmount; ?>">
        <input type="hidden" id="monthlyRent" value="<?php echo $monthlyRent; ?>">
        <input type="hidden" id="initialAdvanceAmount" value="<?php echo $initialCharges['advanceAmount']; ?>">
        <?php if ($newPayment): ?>
            <input type="hidden" name="new_payment" value="<?php echo htmlspecialchars($newPayment); ?>">
        <?php endif; ?>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Proceed Payment</button>
            <a href="payments.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthlyRent = parseFloat(document.getElementById('monthlyRent').value);
    const paymentAmountInput = document.getElementById('paymentAmount');
    const paymentDateInput = document.getElementById('paymentDate');
    const tenderAmountInput = document.getElementById('tenderAmount');
    const changeAmountInput = document.getElementById('changeAmount');
    const tenderError = document.getElementById('errorAmount');
    const reasonInput = document.getElementById('reason');
    const rentAmount = document.getElementById('rentAmount');
    const applianceAmount = document.getElementById('applianceAmount');
    const guestAmount = document.getElementById('guestChargeAmount');
    const subtotalAmount = document.getElementById('subtotalAmount');
    const surchargeAmount = document.getElementById('surchargeAmount');
    const penaltyAmount = document.getElementById('penaltyAmount');
    const previousBalance = document.getElementById('previousBalance');
    const advanceAmount = document.getElementById('advanceAmount');
    const totalAmount = document.getElementById('totalAmount');
    const checkboxes = document.querySelectorAll('.appliance-checkbox');
    const quantities = document.querySelectorAll('.appliance-quantity');
    const applianceTotals = document.querySelectorAll('.appliance-total');
    const form = document.getElementById('paymentForm');
    const paymentType = '<?php echo $newPayment ? $newPayment : $paymentType; ?>';
    const tenantSelect = document.querySelector('select[name="tenant_id"]');
    const boardingIdInput = document.getElementById('boardingId');
    const paymentTypeSelect = document.getElementById('paymentType');
    const monthField = document.getElementById('monthField');
    const initialAdvanceAmount = parseFloat(document.getElementById('initialAdvanceAmount').value);
    // Added: Variables for partial payment alert
    const partialPaymentAlert = document.getElementById('partialPaymentAlert');
    const tenderAmountDisplay = document.getElementById('tenderAmountDisplay');
    const totalAmountDisplay = document.getElementById('totalAmountDisplay');
    const partialReasonInput = document.getElementById('partial_reason');

    function calculateTotal() {
        if (paymentType !== 'Monthly Rent') {
            const tender = parseFloat(tenderAmountInput.value) || 0;
            totalAmount.textContent = tender.toFixed(2);
            paymentAmountInput.value = tender.toFixed(2);
            tenderAmountInput.value = tender.toFixed(2); // Ensure tender amount matches for non-Monthly Rent
            partialPaymentAlert.classList.add('d-none'); // Hide alert for non-Monthly Rent
            updateChange();
            return;
        }

        let applianceCharges = 0;
        checkboxes.forEach((checkbox, index) => {
            if (checkbox.checked) {
                const rate = parseFloat(checkbox.dataset.rate);
                const quantity = parseInt(quantities[index].value) || 1;
                const total = rate * quantity;
                applianceCharges += total;
                applianceTotals[index].textContent = total.toFixed(2);
            } else {
                applianceTotals[index].textContent = '0.00';
            }
        });

        applianceAmount.textContent = applianceCharges.toFixed(2);
        const subtotal = monthlyRent + applianceCharges + parseFloat(guestAmount.textContent || 0);
        subtotalAmount.textContent = subtotal.toFixed(2);

        const paymentDate = paymentDateInput.value;
        const month = '<?php echo $newPayment ? date('Y-m') : $month; ?>';
        const academicYearId = '<?php echo $newPayment ? 0 : $payment['academic_year_id']; ?>';
        const boardingId = '<?php echo $newPayment ? $boardingId : $payment['boarding_id']; ?>';
        const tenantId = '<?php echo $newPayment ? $tenantId : $tenantId; ?>';

        fetch('calculate_charges.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `tenant_id=${tenantId}&month=${month}&academic_year_id=${academicYearId}&payment_date=${paymentDate}&boarding_id=${boardingId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error from calculate_charges:', data.error);
                surchargeAmount.textContent = '0.00';
                penaltyAmount.textContent = '0.00';
                guestAmount.textContent = '0.00';
                advanceAmount.textContent = '0.00';
                reasonInput.value = 'Error calculating charges';
                totalAmount.textContent = subtotal.toFixed(2);
                paymentAmountInput.value = subtotal.toFixed(2);
                tenderAmountInput.value = subtotal.toFixed(2); // Set tender amount to subtotal on error
                partialPaymentAlert.classList.add('d-none');
                updateChange();
                return;
            }
            const surcharge = parseFloat(data.surcharge || 0);
            const latePenalty = parseFloat(data.latePenalty || 0);
            const guestCharges = parseFloat(data.guestCharges || 0);
            const advance = parseFloat(data.advanceAmount || 0);
            const prevBalance = parseFloat(previousBalance.textContent.replace(/,/g, '') || 0);
            const total = Math.max(0, subtotal + surcharge + latePenalty + prevBalance - advance);

            surchargeAmount.textContent = surcharge.toFixed(2);
            penaltyAmount.textContent = latePenalty.toFixed(2);
            guestAmount.textContent = guestCharges.toFixed(2);
            advanceAmount.textContent = advance.toFixed(2);
            totalAmount.textContent = total.toFixed(2);
            paymentAmountInput.value = total.toFixed(2);
            tenderAmountInput.value = total.toFixed(2); // Modified: Set tender amount to grand total
            reasonInput.value = data.reason || 'None';
            if (prevBalance > 0) {
                reasonInput.value += (reasonInput.value ? '\n' : '') + `Previous Balance: ₱${prevBalance.toFixed(2)}`;
            }
            updateChange();
        })
        .catch(error => {
            console.error('Fetch error:', error);
            surchargeAmount.textContent = '0.00';
            penaltyAmount.textContent = '0.00';
            guestAmount.textContent = '0.00';
            advanceAmount.textContent = '0.00';
            reasonInput.value = 'Error fetching charges';
            totalAmount.textContent = subtotal.toFixed(2);
            paymentAmountInput.value = subtotal.toFixed(2);
            tenderAmountInput.value = subtotal.toFixed(2); // Set tender amount to subtotal on error
            partialPaymentAlert.classList.add('d-none');
            updateChange();
        });
    }

    function updateChange() {
        const total = parseFloat(totalAmount.textContent.replace(/,/g, '')) || 0;
        const tender = parseFloat(tenderAmountInput.value) || 0;
        const change = tender - total;
        changeAmountInput.value = change >= 0 ? change.toFixed(2) : '0.00';
        // Added: Update partial payment alert
        if (paymentType === 'Monthly Rent' && tender < total && tender > 0) {
            partialPaymentAlert.classList.remove('d-none');
            tenderAmountDisplay.textContent = tender.toFixed(2);
            totalAmountDisplay.textContent = total.toFixed(2);
        } else {
            partialPaymentAlert.classList.add('d-none');
        }
    }

    form.addEventListener('submit', function(event) {
        const total = parseFloat(totalAmount.textContent.replace(/,/g, '')) || 0;
        const tender = parseFloat(tenderAmountInput.value) || 0;
        if (tender < 0) {
            event.preventDefault();
            tenderAmountInput.classList.add('is-invalid');
            tenderError.textContent = 'Tender amount cannot be negative.';
        } else if (tender == 0 && paymentType !== 'Monthly Rent') {
            event.preventDefault();
            tenderAmountInput.classList.add('is-invalid');
            tenderError.textContent = 'Tender amount must be greater than zero.';
        } else if (paymentType === 'Monthly Rent' && tender < total && tender > 0 && !partialReasonInput.value.trim()) {
            event.preventDefault();
            partialReasonInput.classList.add('is-invalid');
            tenderError.textContent = 'Please provide a reason for the partial payment.';
            partialPaymentAlert.classList.remove('d-none');
            tenderAmountDisplay.textContent = tender.toFixed(2);
            totalAmountDisplay.textContent = total.toFixed(2);
            partialReasonInput.focus();
        } else {
            tenderAmountInput.classList.remove('is-invalid');
            tenderError.textContent = '';
            partialReasonInput.classList.remove('is-invalid');
        }
    });

    if (tenantSelect) {
        tenantSelect.addEventListener('change', function() {
            const selectedOption = tenantSelect.options[tenantSelect.selectedIndex];
            boardingIdInput.value = selectedOption.dataset.boardingId || '';
        });
    }

    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', function() {
            monthField.style.display = paymentTypeSelect.value === 'Advance' ? 'block' : 'none';
        });
        monthField.style.display = paymentTypeSelect.value === 'Advance' ? 'block' : 'none';
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', calculateTotal);
    });
    quantities.forEach(quantity => {
        quantity.addEventListener('input', calculateTotal);
    });
    paymentDateInput.addEventListener('change', calculateTotal);
    tenderAmountInput.addEventListener('input', function() {
        updateChange();
        tenderAmountInput.classList.remove('is-invalid');
        tenderError.textContent = '';
        partialReasonInput.classList.remove('is-invalid');
    });

    calculateTotal();
});
</script>
</body>
</html>