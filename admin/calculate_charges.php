
<?php
header('Content-Type: application/json');
require_once '../connection/db.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

try {
    $month = $_POST['month'] ?? '';
    $academicYearId = $_POST['academic_year_id'] ?? 0;
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $boardingId = $_POST['boarding_id'] ?? 0;
    $tenantId = $_POST['tenant_id'] ?? 0;

    if (!$month || !$boardingId || !$tenantId) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit();
    }

    $surcharge = 0.00;
    $latePenalty = 0.00;
    $guestCharges = 0.00;
    $advanceAmount = 0.00;
    $reason = [];

    // Fetch current academic year if not provided
    if (!$academicYearId) {
        $academicYearStmt = $conn->query("SELECT academic_year_id FROM academic_years WHERE is_current = 1");
        $academicYearId = $academicYearStmt->fetch_assoc()['academic_year_id'] ?? 0;
    }

    // Fetch advance payments
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

    // Fetch utility expenses
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

    // Fetch guest stay charges
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

    // Calculate late penalty
    $dueDate = new DateTime($month . '-07');
    $gracePeriodEnd = new DateTime($month . '-21');
    $paymentDateObj = new DateTime($paymentDate);
    if ($paymentDateObj > $gracePeriodEnd) {
        $weeksLate = ceil(($paymentDateObj->getTimestamp() - $gracePeriodEnd->getTimestamp()) / (7 * 24 * 3600));
        $latePenalty = $weeksLate * 50.00;
        $reason[] = "Late Penalty: ₱" . number_format($latePenalty, 2);
    }

    echo json_encode([
        'surcharge' => $surcharge,
        'latePenalty' => $latePenalty,
        'guestCharges' => $guestCharges,
        'advanceAmount' => $advanceAmount,
        'reason' => implode('; ', $reason)
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
