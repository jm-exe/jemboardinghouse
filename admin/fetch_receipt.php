<?php
 
// Set JSON header immediately
header('Content-Type: application/json');

// Error logging (not display)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/jemboardinghouse/PHP/error_log.txt');
error_reporting(E_ALL);

// Include DB connection (correct path)
include('../connection/db.php');

// Function to convert number to words (English)
function numberToWords($number) {
    $ones = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    ];
    $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    ];
    $thousands = [
        0 => '', 1 => 'Thousand', 2 => 'Million', 3 => 'Billion'
    ];

    if ($number == 0) return 'Zero';

    $number = round($number, 2);
    $integerPart = floor($number);
    $decimalPart = round(($number - $integerPart) * 100);

    $words = [];
    $chunkCount = 0;

    while ($integerPart > 0) {
        $chunk = $integerPart % 1000;
        $integerPart = floor($integerPart / 1000);

        if ($chunk == 0) {
            $chunkCount++;
            continue;
        }

        $chunkWords = [];
        $hundreds = floor($chunk / 100);
        $chunk = $chunk % 100;

        if ($hundreds > 0) {
            $chunkWords[] = $ones[$hundreds] . ' Hundred';
        }

        if ($chunk > 0) {
            if ($chunk < 20) {
                $chunkWords[] = $ones[$chunk];
            } else {
                $tensDigit = floor($chunk / 10);
                $onesDigit = $chunk % 10;
                $chunkWords[] = $tens[$tensDigit];
                if ($onesDigit > 0) {
                    $chunkWords[] = $ones[$onesDigit];
                }
            }
        }

        if (!empty($chunkWords)) {
            $chunkWords[] = $thousands[$chunkCount];
            $words[] = implode(' ', $chunkWords);
        }

        $chunkCount++;
    }

    $integerWords = implode(' ', array_reverse($words));
    $decimalWords = $decimalPart > 0 ? ' and ' . ($decimalPart < 20 ? $ones[$decimalPart] : $tens[floor($decimalPart / 10)] . ($decimalPart % 10 > 0 ? ' ' . $ones[$decimalPart % 10] : '')) . ' Centavos' : '';

    return trim($integerWords . ' Pesos' . $decimalWords);
}

$response = [];

try {
    if (!isset($_GET['payment_id']) || !is_numeric($_GET['payment_id'])) {
        throw new Exception('Payment ID is required or invalid');
    }

    $payment_id = (int)$_GET['payment_id'];

    // Fetch payment details
    $sql = "SELECT p.payment_id, p.payment_amount, p.payment_date, p.payment_for_month_of, 
                   p.appliance_charges, p.appliances, r.receipt_number, 
                   t.first_name, t.last_name
            FROM payments p
            JOIN boarding bo ON p.boarding_id = bo.boarding_id
            JOIN tenants t ON bo.tenant_id = t.tenant_id
            LEFT JOIN receipts r ON p.payment_id = r.payment_id
            WHERE p.payment_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Fetch business owner details
        $owner_sql = "SELECT owner_name, address, contact_no, email FROM business_owner WHERE owner_id = 1";
        $owner_result = $conn->query($owner_sql);
        $business_info = $owner_result && $owner_result->num_rows > 0 ? $owner_result->fetch_assoc() : [
            'owner_name' => 'JEM Boardinghouse',
            'address' => '123 Boarding Lane, City, Country',
            'contact_no' => '123-456-7890',
            'email' => 'jem@example.com'
        ];

        // Calculate total amount and convert to words
        $total_amount = ($row['payment_amount'] ?? 0) + ($row['appliance_charges'] ?? 0);
        $amount_in_words = numberToWords($total_amount);

        $response = [
            'payment_id' => $row['payment_id'],
            'payment_amount' => $row['payment_amount'],
            'payment_date' => $row['payment_date'],
            'payment_for_month_of' => $row['payment_for_month_of'] ?? 'N/A',
            'appliance_charges' => $row['appliance_charges'] ?? 0,
            'appliances' => $row['appliances'] ?? 'None',
            'receipt_number' => $row['receipt_number'] ?? 'RPT-' . str_pad($row['payment_id'], 5, '0', STR_PAD_LEFT),
            'tenant_name' => ($row['first_name'] && $row['last_name']) 
                ? $row['first_name'] . ' ' . $row['last_name'] 
                : 'N/A',
            'business_name' => $business_info['owner_name'],
            'business_address' => $business_info['address'],
            'business_contact' => $business_info['contact_no'] . ($business_info['email'] ? ' | ' . $business_info['email'] : ''),
            'amount_in_words' => $amount_in_words
        ];
    } else {
        $response = ['error' => 'No receipt found for the given payment ID'];
    }

    $stmt->close();
} catch (Exception $e) {
    $response = ['error' => 'Server error: ' . $e->getMessage()];
}

$conn->close();

// Clean output buffer and send JSON
if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;