<?php
session_start();
require_once '../connection/db.php'; // Database connection

// Set timezone
date_default_timezone_set('Asia/Manila');

// Function to generate unpaid payment for a boarding record
function generateMonthlyPayment($conn, $boarding, $currentMonth, $academicYearId) {
    $tenantId = $boarding['tenant_id'];
    $boardingId = $boarding['boarding_id'];
    $bedId = $boarding['bed_id'];

    // Check if payment already exists for this month
    $checkStmt = $conn->prepare("SELECT payment_id FROM payments WHERE boarding_id = ? AND payment_for_month_of = ? AND payment_type = 'Monthly Rent'");
    $monthStr = $currentMonth->format('Y-m');
    $checkStmt->bind_param('is', $boardingId, $monthStr);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        return; // Payment already exists
    }
    $checkStmt->close();

    // Get bed details for monthly rent
    $bedStmt = $conn->prepare("SELECT monthly_rent FROM beds WHERE bed_id = ?");
    $bedStmt->bind_param('i', $bedId);
    $bedStmt->execute();
    $bed = $bedStmt->get_result()->fetch_assoc();
    $monthlyRent = $bed['monthly_rent'] ?? 1100.00; // Default from settings
    $bedStmt->close();

    // Get appliance charges
    $applianceStmt = $conn->prepare("
        SELECT a.appliance_name, a.rate, ta.quantity
        FROM tenant_appliances ta
        JOIN appliances a ON ta.appliance_id = a.appliance_id
        WHERE ta.tenant_id = ? AND ta.month = ? AND ta.academic_year_id = ?
    ");
    $monthStart = $currentMonth->format('Y-m-01');
    $applianceStmt->bind_param('isi', $tenantId, $monthStart, $academicYearId);
    $applianceStmt->execute();
    $appliances = $applianceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $applianceStmt->close();

    $applianceCharges = 0.00;
    $applianceList = [];
    foreach ($appliances as $appliance) {
        $applianceCharges += $appliance['rate'] * $appliance['quantity'];
        $applianceList[] = $appliance['appliance_name'] . ' (' . $appliance['quantity'] . ')';
    }
    $appliancesStr = implode(', ', $applianceList);

    // Calculate utility surcharges
    $utilitySurcharge = 0.00;
    $expenseStmt = $conn->prepare("
        SELECT e.description, me.amount
        FROM monthly_expenses me
        JOIN expenses e ON me.expense_id = e.expense_id
        WHERE me.academic_year_id = ? AND me.month = ?
        AND e.description IN ('Water Bill', 'Electric Bill')
    ");
    $expenseStmt->bind_param('is', $academicYearId, $monthStart);
    $expenseStmt->execute();
    $expenses = $expenseStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $expenseStmt->close();

    foreach ($expenses as $expense) {
        if ($expense['description'] == 'Water Bill' && $expense['amount'] > 3000.00) {
            $utilitySurcharge += 100.00;
        }
        if ($expense['description'] == 'Electric Bill' && $expense['amount'] > 5000.00) {
            $utilitySurcharge += 100.00;
        }
    }

    // Calculate late penalty
    $latePenalty = 0.00;
    $dueDate = new DateTime($currentMonth->format('Y-m-07'));
    $today = new DateTime();
    $gracePeriodEnd = new DateTime($currentMonth->format('Y-m-21'));

    if ($today > $gracePeriodEnd) {
        $weeksLate = ceil(($today->getTimestamp() - $gracePeriodEnd->getTimestamp()) / (7 * 24 * 3600));
        $latePenaltyPerWeek = 50.00;
        $latePenalty = $weeksLate * $latePenaltyPerWeek;
    }

    // Check for previous unpaid balance
    $balanceStmt = $conn->prepare("
        SELECT SUM(balance) as total_balance
        FROM payments
        WHERE boarding_id = ? AND payment_for_month_of < ? AND payment_type = 'Monthly Rent'
    ");
    $balanceStmt->bind_param('is', $boardingId, $monthStr);
    $balanceStmt->execute();
    $previousBalance = $balanceStmt->get_result()->fetch_assoc()['total_balance'] ?? 0.00;
    $balanceStmt->close();

    // Check for advance payments for this month
    $advanceStmt = $conn->prepare("
        SELECT SUM(payment_amount) as total_advance
        FROM payments
        WHERE boarding_id = ? AND payment_for_month_of = ? AND payment_type = 'Advance' AND method = 'Cash'
    ");
    $advanceStmt->bind_param('is', $boardingId, $monthStr);
    $advanceStmt->execute();
    $advanceAmount = $advanceStmt->get_result()->fetch_assoc()['total_advance'] ?? 0.00;
    $advanceStmt->close();

    // Total payment amount including previous balance, minus advance
    $totalAmount = $monthlyRent + $applianceCharges + $utilitySurcharge + $latePenalty + $previousBalance - $advanceAmount;
    $totalAmount = max(0, $totalAmount); // Ensure total is not negative
    $balanceAmount = $totalAmount; // Initial balance for unpaid payment

    // Get tenant's user_id
    $userStmt = $conn->prepare("SELECT user_id FROM tenants WHERE tenant_id = ?");
    $userStmt->bind_param('i', $tenantId);
    $userStmt->execute();
    $userId = $userStmt->get_result()->fetch_assoc()['user_id'];
    $userStmt->close();

    // Insert payment with method 'Credit' (unpaid) and type 'Monthly Rent'
    $paymentStmt = $conn->prepare("
        INSERT INTO payments 
        (user_id, boarding_id, payment_amount, payment_date, method, appliance_charges, appliances, academic_year_id, payment_for_month_of, reason, payment_type, balance)
        VALUES (?, ?, ?, ?, 'Credit', ?, ?, ?, ?, ?, 'Monthly Rent', ?)
    ");
    $paymentDate = $today->format('Y-m-d');
    $reason = '';
    if ($utilitySurcharge > 0) {
        $reason .= "Utility Surcharge: ₱" . number_format($utilitySurcharge, 2);
    }
    if ($latePenalty > 0) {
        $reason .= ($reason ? '; ' : '') . "Late Penalty: ₱" . number_format($latePenalty, 2);
    }
    if ($previousBalance > 0) {
        $reason .= ($reason ? '; ' : '') . "Previous Balance: ₱" . number_format($previousBalance, 2);
    }
    if ($advanceAmount > 0) {
        $reason .= ($reason ? '; ' : '') . "Advance Applied: ₱" . number_format($advanceAmount, 2);
    }
    $paymentStmt->bind_param('iidsisissd', 
        $userId, 
        $boardingId, 
        $totalAmount, 
        $paymentDate, 
        $applianceCharges, 
        $appliancesStr, 
        $academicYearId, 
        $monthStr,
        $reason,
        $balanceAmount
    );
    $paymentStmt->execute();
    $paymentStmt->close();
}

try {
    // Get current academic year
    $academicYearStmt = $conn->query("SELECT academic_year_id FROM academic_years WHERE is_current = 1");
    $academicYear = $academicYearStmt->fetch_assoc();
    if (!$academicYear) {
        throw new Exception("No current academic year found.");
    }
    $academicYearId = $academicYear['academic_year_id'];

    // Get active boarding records
    $today = new DateTime();
    $boardingStmt = $conn->query("
        SELECT boarding_id, tenant_id, bed_id, start_date, due_date
        FROM boarding
        WHERE due_date >= CURDATE()
    ");
    $boardings = $boardingStmt->fetch_all(MYSQLI_ASSOC);

    // Process payments for the current month
    $currentMonth = new DateTime('first day of this month');
    foreach ($boardings as $boarding) {
        generateMonthlyPayment($conn, $boarding, $currentMonth, $academicYearId);
    }

    $_SESSION['success'] = "Payment automation completed successfully.";
} catch (Exception $e) {
    $_SESSION['error'] = "Error during payment automation: " . $e->getMessage();
}

header("Location: payments.php");
exit();
?>