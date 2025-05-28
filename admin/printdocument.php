<?php
session_start(); // Start session for user data
include('../connection/db.php'); // Database connection

// --- Helper Functions ---
function fetch_academic_years($conn) {
    $years = [];
    $sql = "SELECT academic_year_id, start_year, end_year, semester FROM academic_years ORDER BY start_year DESC, semester ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $years[] = $row;
        }
    }
    return $years;
}

function fetch_floors($conn) {
    $floors = [];
    $sql = "SELECT floor_id, floor_no FROM floors ORDER BY floor_no ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $floors[] = $row;
        }
    }
    return $floors;
}

function fetch_tenants_for_dropdown($conn) {
    $tenants = [];
    $sql = "SELECT tenant_id, first_name, last_name FROM tenants ORDER BY last_name, first_name";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tenants[] = $row;
        }
    }
    return $tenants;
}

function fetch_tenants_by_floor($conn, $floor_id = null) {
    $tenants_by_floor = [];
    
    $base_sql = "SELECT f.floor_id, f.floor_no, r.room_id, r.room_no, 
                   b.bed_id, b.bed_no, b.bed_type, b.deck, b.monthly_rent,
                   t.tenant_id, t.first_name, t.last_name, t.mobile_no,
                   t.profile_picture, t.academic_year_id,
                   ay.start_year, ay.end_year, ay.semester
            FROM floors f
            LEFT JOIN rooms r ON f.floor_id = r.floor_id
            LEFT JOIN beds b ON r.room_id = b.room_id
            LEFT JOIN boarding bo ON b.bed_id = bo.bed_id AND (bo.due_date IS NULL OR bo.due_date >= CURDATE())
            LEFT JOIN tenants t ON bo.tenant_id = t.tenant_id
            LEFT JOIN academic_years ay ON t.academic_year_id = ay.academic_year_id";
    
    if ($floor_id && $floor_id !== 'all') {
        $sql = $base_sql . " WHERE f.floor_id = ? ORDER BY f.floor_no, r.room_no, b.bed_no";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $floor_id);
    } else {
        $sql = $base_sql . " ORDER BY f.floor_no, r.room_no, b.bed_no";
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $current_floor_id = $row['floor_id'];
            $room_id = $row['room_id'];
            
            if (!isset($tenants_by_floor[$current_floor_id])) {
                $tenants_by_floor[$current_floor_id] = [
                    'floor_no' => $row['floor_no'],
                    'rooms' => []
                ];
            }
            
            if ($room_id && !isset($tenants_by_floor[$current_floor_id]['rooms'][$room_id])) {
                $tenants_by_floor[$current_floor_id]['rooms'][$room_id] = [
                    'room_no' => $row['room_no'],
                    'beds' => []
                ];
            }
            
            if ($row['tenant_id']) {
                $tenants_by_floor[$current_floor_id]['rooms'][$room_id]['beds'][] = [
                    'bed_no' => $row['bed_no'],
                    'bed_type' => $row['bed_type'],
                    'deck' => $row['deck'],
                    'monthly_rent' => $row['monthly_rent'],
                    'tenant_id' => $row['tenant_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'mobile_no' => $row['mobile_no'],
                    'profile_picture' => $row['profile_picture'],
                    'academic_year' => ($row['start_year'] && $row['end_year'] && $row['semester']) 
                        ? $row['start_year'] . '-' . $row['end_year'] . ' ' . $row['semester'] 
                        : 'N/A'
                ];
            }
        }
    }
    return $tenants_by_floor;
}

function fetch_payment_status_tenants($conn, $academic_year_id = null) {
    $tenants_payment_status = [];
    
    $base_sql = "SELECT t.tenant_id, t.first_name, t.last_name, t.mobile_no, 
                   b.monthly_rent, ay.start_year, ay.end_year, ay.semester,
                   p.payment_id, p.payment_amount, p.payment_date, p.payment_for_month_of, p.method
            FROM tenants t
            LEFT JOIN boarding bo ON t.tenant_id = bo.tenant_id
            LEFT JOIN beds b ON bo.bed_id = b.bed_id
            LEFT JOIN academic_years ay ON t.academic_year_id = ay.academic_year_id
            LEFT JOIN payments p ON bo.boarding_id = p.boarding_id";
    
    if ($academic_year_id) {
        $sql = $base_sql . " WHERE t.academic_year_id = ? ORDER BY t.last_name, t.first_name, p.payment_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $academic_year_id);
    } else {
        $sql = $base_sql . " ORDER BY t.last_name, t.first_name, p.payment_date DESC";
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tenant_id = $row['tenant_id'];
            if (!isset($tenants_payment_status[$tenant_id])) {
                $tenants_payment_status[$tenant_id] = [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'mobile_no' => $row['mobile_no'],
                    'monthly_rent' => $row['monthly_rent'],
                    'academic_year' => ($row['start_year'] && $row['end_year'] && $row['semester']) 
                        ? $row['start_year'] . '-' . $row['end_year'] . ' ' . $row['semester'] 
                        : 'N/A',
                    'payments' => []
                ];
            }
            if ($row['payment_id']) {
                $tenants_payment_status[$tenant_id]['payments'][] = [
                    'payment_id' => $row['payment_id'],
                    'payment_amount' => $row['payment_amount'],
                    'payment_date' => $row['payment_date'],
                    'payment_for_month_of' => $row['payment_for_month_of'],
                    'payment_method' => $row['method'] // Add payment_method
                ];
            }
        }
    }
    return $tenants_payment_status;
}

function fetch_income_report($conn, $period_type, $year, $month = null) {
    $income_data = [];
    
    $base_sql = "SELECT p.payment_id, p.payment_amount, p.payment_date, p.payment_for_month_of,
                   t.first_name, t.last_name, ay.start_year, ay.end_year, ay.semester
            FROM payments p
            JOIN boarding bo ON p.boarding_id = bo.boarding_id
            JOIN tenants t ON bo.tenant_id = t.tenant_id
            JOIN academic_years ay ON p.academic_year_id = ay.academic_year_id
            WHERE YEAR(p.payment_date) = ?";
    
    $params = ['i', $year];
    
    if ($period_type === 'monthly' && $month) {
        $base_sql .= " AND MONTH(p.payment_date) = ?";
        $params[0] .= 'i';
        $params[] = $month;
    }
    $base_sql .= " ORDER BY p.payment_date";
    
    $stmt = $conn->prepare($base_sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $income_data[] = [
                'payment_id' => $row['payment_id'],
                'payment_amount' => $row['payment_amount'],
                'payment_date' => $row['payment_date'],
                'payment_for_month_of' => $row['payment_for_month_of'],
                'tenant_name' => ($row['first_name'] && $row['last_name']) 
                    ? $row['first_name'] . ' ' . $row['last_name'] 
                    : 'N/A',
                'academic_year' => ($row['start_year'] && $row['end_year'] && $row['semester']) 
                    ? $row['start_year'] . '-' . $row['end_year'] . ' ' . $row['semester'] 
                    : 'N/A'
            ];
        }
    }
    return $income_data;
}

function fetch_expenses_report($conn, $period_type, $year, $month = null) {
    $expenses_data = [];
    
    $base_sql = "SELECT me.monthly_expense_id, me.amount, me.month, e.description
            FROM monthly_expenses me
            JOIN expenses e ON me.expense_id = e.expense_id
            WHERE YEAR(me.month) = ?";

    $params = ['i', $year];

    if ($period_type === 'monthly' && $month) {
        $base_sql .= " AND MONTH(me.month) = ?";
        $params[0] .= 'i';
        $params[] = $month;
    }
    $base_sql .= " ORDER BY me.month";

    $stmt = $conn->prepare($base_sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expenses_data[] = [
                'monthly_expense_id' => $row['monthly_expense_id'],
                'amount' => $row['amount'],
                'month' => $row['month'],
                'description' => $row['description']
            ];
        }
    }
    return $expenses_data;
}

function fetch_tenant_payment_history($conn, $tenant_id) {
    $payment_history = [];
    
    $sql = "SELECT p.payment_id, p.payment_amount, p.payment_date, p.payment_for_month_of, p.method,
                   p.appliance_charges, p.appliances, r.receipt_id, r.receipt_number,
                   t.first_name, t.last_name, ay.start_year, ay.end_year, ay.semester
            FROM payments p
            JOIN boarding bo ON p.boarding_id = bo.boarding_id
            JOIN tenants t ON bo.tenant_id = t.tenant_id
            JOIN academic_years ay ON p.academic_year_id = ay.academic_year_id
            LEFT JOIN receipts r ON p.payment_id = r.payment_id
            WHERE t.tenant_id = ?
            ORDER BY p.payment_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payment_history[] = [
                'payment_id' => $row['payment_id'],
                'payment_amount' => $row['payment_amount'],
                'payment_date' => $row['payment_date'],
                'payment_for_month_of' => $row['payment_for_month_of'],
                'method' => $row['method'],
                'appliance_charges' => $row['appliance_charges'] ?? 0,
                'appliances' => $row['appliances'] ?? 'None',
                'receipt_id' => $row['receipt_id'],
                'receipt_number' => $row['receipt_number'],
                'tenant_name' => ($row['first_name'] && $row['last_name']) 
                    ? $row['first_name'] . ' ' . $row['last_name'] 
                    : 'N/A',
                'academic_year' => ($row['start_year'] && $row['end_year'] && $row['semester']) 
                    ? $row['start_year'] . '-' . $row['end_year'] . ' ' . $row['semester'] 
                    : 'N/A'
            ];
        }
    }
    return $payment_history;
}

// --- HELPER FUNCTION FOR ANNUAL FINANCIAL DATA ---
function fetch_annual_financial_data($conn, $year) {
    $report_data = [];
    $annual_totals = [
        'tenants_monthly_avg' => 0,
        'total_tenant_months' => 0,
        'monthly_payment' => 0,
        'charges' => 0,
        'electric_bill' => 0,
        'water_bill' => 0,
        'total_expenses' => 0,
    ];
    $month_count_with_tenants = 0;

    for ($month_num = 1; $month_num <= 12; $month_num++) {
        $month_name = date("F", mktime(0, 0, 0, $month_num, 10));
        $first_day_of_month_str = date("Y-m-d", mktime(0, 0, 0, $month_num, 1, $year));
        $last_day_of_month_str = date("Y-m-t", strtotime($first_day_of_month_str));

        // 1. Tenants for the month (active boarding during any part of the month)
        $stmt_tenants = $conn->prepare("
            SELECT COUNT(DISTINCT b.tenant_id) as tenant_count
            FROM boarding b
            WHERE b.start_date <= ? AND (b.due_date IS NULL OR b.due_date >= ?)
        ");
        $stmt_tenants->bind_param("ss", $last_day_of_month_str, $first_day_of_month_str);
        $stmt_tenants->execute();
        $result_tenants = $stmt_tenants->get_result();
        $tenants_count = ($row_tenants = $result_tenants->fetch_assoc()) ? (int)$row_tenants['tenant_count'] : 0;
        $stmt_tenants->close();

        // 2. Income - Monthly Payment (Base Rent) and Surcharges
        $monthly_rent_income = 0.00;
        $surcharges = 0.00;
        $stmt_rent = $conn->prepare("
            SELECT p.payment_amount, b.monthly_rent
            FROM payments p
            JOIN boarding bo ON p.boarding_id = bo.boarding_id
            JOIN beds b ON bo.bed_id = b.bed_id
            WHERE YEAR(p.payment_date) = ? AND MONTH(p.payment_date) = ?
        ");
        $stmt_rent->bind_param("ii", $year, $month_num);
        $stmt_rent->execute();
        $result_rent = $stmt_rent->get_result();
        while ($row_rent = $result_rent->fetch_assoc()) {
            $payment_amount = (float)($row_rent['payment_amount'] ?? 0.00);
            $monthly_rent = (float)($row_rent['monthly_rent'] ?? 1100.00); // Default to 1100 if null
            // Calculate base rent and surcharges
            if ($payment_amount > $monthly_rent) {
                $monthly_rent_income += $monthly_rent; // Base rent only
                $surcharges += $payment_amount - $monthly_rent; // Excess is surcharge
            } else {
                $monthly_rent_income += $payment_amount; // Use full payment if less than rent
            }
        }
        $stmt_rent->close();

        // 3. Income - Charges (Appliance + Guest Stays + Surcharges)
        $appliance_income = 0.00;
        $stmt_appliance_charges = $conn->prepare("
            SELECT SUM(p.appliance_charges) as total_appliance
            FROM payments p
            WHERE YEAR(p.payment_date) = ? AND MONTH(p.payment_date) = ?
        ");
        $stmt_appliance_charges->bind_param("ii", $year, $month_num);
        $stmt_appliance_charges->execute();
        $result_appliance_charges = $stmt_appliance_charges->get_result();
        if ($row_appliance = $result_appliance_charges->fetch_assoc()) {
            $appliance_income = (float)($row_appliance['total_appliance'] ?? 0.00);
        }
        $stmt_appliance_charges->close();

        $guest_income = 0.00;
        $stmt_guest_charges = $conn->prepare("
            SELECT SUM(gs.charge) as total_guest
            FROM guest_stays gs
            WHERE YEAR(gs.stay_date) = ? AND MONTH(gs.stay_date) = ?
        ");
        $stmt_guest_charges->bind_param("ii", $year, $month_num);
        $stmt_guest_charges->execute();
        $result_guest_charges = $stmt_guest_charges->get_result();
        if ($row_guest = $result_guest_charges->fetch_assoc()) {
            $guest_income = (float)($row_guest['total_guest'] ?? 0.00);
        }
        $stmt_guest_charges->close();
        $total_charges_income = $appliance_income + $guest_income + $surcharges;

        // 4. Expenses - Electric Bill
        $electric_bill = 0.00;
        $electric_desc_pattern = '%Electric%';
        $stmt_electric = $conn->prepare("
            SELECT SUM(me.amount) as total_electric
            FROM monthly_expenses me
            JOIN expenses e ON me.expense_id = e.expense_id
            WHERE YEAR(me.month) = ? AND MONTH(me.month) = ? AND e.description LIKE ?
        ");
        $stmt_electric->bind_param("iis", $year, $month_num, $electric_desc_pattern);
        $stmt_electric->execute();
        $result_electric = $stmt_electric->get_result();
        if ($row_electric = $result_electric->fetch_assoc()) {
            $electric_bill = (float)($row_electric['total_electric'] ?? 0.00);
        }
        $stmt_electric->close();

        // 5. Expenses - Water Bill
        $water_bill = 0.00;
        $water_desc_pattern = '%Water%';
        $stmt_water = $conn->prepare("
            SELECT SUM(me.amount) as total_water
            FROM monthly_expenses me
            JOIN expenses e ON me.expense_id = e.expense_id
            WHERE YEAR(me.month) = ? AND MONTH(me.month) = ? AND e.description LIKE ?
        ");
        $stmt_water->bind_param("iis", $year, $month_num, $water_desc_pattern);
        $stmt_water->execute();
        $result_water = $stmt_water->get_result();
        if ($row_water = $result_water->fetch_assoc()) {
            $water_bill = (float)($row_water['total_water'] ?? 0.00);
        }
        $stmt_water->close();

        $total_monthly_expenses = $electric_bill + $water_bill;

        $report_data[] = [
            'Month' => $month_name,
            'Tenants' => $tenants_count,
            'MonthlyPayment' => $monthly_rent_income,
            'Charges' => $total_charges_income,
            'ElectricBill' => $electric_bill,
            'WaterBill' => $water_bill,
            'TotalExpenses' => $total_monthly_expenses,
        ];

        $annual_totals['total_tenant_months'] += $tenants_count;
        if ($tenants_count > 0) {
            $month_count_with_tenants++;
        }
        $annual_totals['monthly_payment'] += $monthly_rent_income;
        $annual_totals['charges'] += $total_charges_income;
        $annual_totals['electric_bill'] += $electric_bill;
        $annual_totals['water_bill'] += $water_bill;
        $annual_totals['total_expenses'] += $total_monthly_expenses;
    }

    if ($month_count_with_tenants > 0) {
        $annual_totals['tenants_monthly_avg'] = round($annual_totals['total_tenant_months'] / $month_count_with_tenants, 2);
    } else {
        $annual_totals['tenants_monthly_avg'] = 0;
    }

    $annual_net_income = ($annual_totals['monthly_payment'] + $annual_totals['charges']) - $annual_totals['total_expenses'];

    return [
        'monthly_data' => $report_data,
        'annual_totals' => $annual_totals,
        'annual_net_income' => $annual_net_income,
        'year' => $year
    ];
}

$available_academic_years = fetch_academic_years($conn);
$available_floors = fetch_floors($conn);
$available_tenants_dropdown = fetch_tenants_for_dropdown($conn);

$document_types = [
    "tenant_list_by_floor" => "List of Tenants per Floor",
    "payment_status_tenants" => "Paid/Unpaid Tenants by Academic Year",
    "income_report" => "Income Report (Annual/Monthly)",
    "expenses_report" => "Expenses Report (Annual/Monthly)",
    "tenant_payment_history" => "Receipts from Previous Payments",
    "annual_financial_report" => "Boardinghouse Annual Report"
];

$selected_document_type = isset($_GET['document_type']) ? $_GET['document_type'] : null;
$document_content_html = "";
$document_title = "Print Documents"; 
$report_subtitle = "";

$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : date("Y");

if ($selected_document_type && isset($document_types[$selected_document_type])) {
    if ($selected_document_type === 'annual_financial_report') {
        $document_title = $document_types[$selected_document_type] . " " . $filter_year;
    } else {
        $document_title = $document_types[$selected_document_type];
    }
}

$filter_floor_id = isset($_GET['floor_id']) ? $_GET['floor_id'] : null;
$filter_academic_year_id = isset($_GET['academic_year_id']) ? $_GET['academic_year_id'] : null;
$filter_report_period_type = isset($_GET['report_period_type']) ? $_GET['report_period_type'] : 'monthly';
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : date("m");
$filter_tenant_id = isset($_GET['filter_tenant_id']) ? (int)$_GET['filter_tenant_id'] : null;

if ($selected_document_type) {
    switch ($selected_document_type) {
        case "tenant_list_by_floor":
            $report_subtitle = "Generated on: " . date("Y-m-d H:i:s");
            if ($filter_floor_id && $filter_floor_id !== 'all') {
                $selected_floor_info = array_filter($available_floors, fn($floor) => $floor['floor_id'] == $filter_floor_id);
                if ($selected_floor_info) {
                    $report_subtitle .= "<br>Floor: " . htmlspecialchars(reset($selected_floor_info)['floor_no'] ?? 'Unknown');
                }
            } else {
                $report_subtitle .= "<br>All Floors";
            }
            $tenants_by_floor = fetch_tenants_by_floor($conn, $filter_floor_id);
            if (!empty($tenants_by_floor)) {
                foreach ($tenants_by_floor as $floor_id_key => $floor_data) { 
                    $document_content_html .= "<div class='floor-section mb-4'>";
                    $document_content_html .= "<h3 class='floor-title bg-light p-2'>Floor: " . htmlspecialchars($floor_data['floor_no'] ?? 'Unknown') . "</h3>";
                    if (!empty($floor_data['rooms'])) {
                        foreach ($floor_data['rooms'] as $room_id => $room_data) {
                            $document_content_html .= "<div class='room-section mb-3'>";
                            $document_content_html .= "<h4 class='room-title bg-secondary text-white p-2'>Room: " . htmlspecialchars($room_data['room_no'] ?? 'Unknown') . "</h4>";
                            if (!empty($room_data['beds'])) {
                                $document_content_html .= "<table class='table table-bordered table-striped'>";
                                $document_content_html .= "<thead><tr><th>Bed #</th><th>Type</th><th>Deck</th><th>Tenant Name</th><th>Contact</th><th>Academic Year</th><th>Rent</th></tr></thead><tbody>";
                                foreach ($room_data['beds'] as $bed) {
                                    $tenant_name = ($bed['first_name'] && $bed['last_name']) ? $bed['first_name'] . ' ' . $bed['last_name'] : 'N/A';
                                    $contact = htmlspecialchars($bed['mobile_no'] ?? 'N/A');
                                    $document_content_html .= "<tr><td>" . htmlspecialchars($bed['bed_no'] ?? 'N/A') . "</td><td>" . htmlspecialchars($bed['bed_type'] ?? 'N/A') . "</td><td>" . htmlspecialchars($bed['deck'] ?? '-') . "</td><td>" . htmlspecialchars($tenant_name) . "</td><td>" . $contact . "</td><td>" . htmlspecialchars($bed['academic_year']) . "</td><td class='text-right'>₱ " . number_format($bed['monthly_rent'] ?? 0, 2) . "</td></tr>";
                                }
                                $document_content_html .= "</tbody></table>";
                            } else { $document_content_html .= "<p class='alert alert-info'>No tenants in this room.</p>"; }
                            $document_content_html .= "</div>"; 
                        }
                    } else { $document_content_html .= "<p class='alert alert-info'>No rooms with tenants on this floor.</p>"; }
                    $document_content_html .= "</div>"; 
                }
            } else { $document_content_html .= "<p class='alert alert-info'>No tenants found for the selected criteria.</p>"; }
            break;

        case "payment_status_tenants":
    $report_subtitle = "Generated on: " . date("Y-m-d H:i:s");
    if ($filter_academic_year_id) {
        $selected_ay = array_filter($available_academic_years, fn($ay) => $ay['academic_year_id'] == $filter_academic_year_id);
        if ($selected_ay) {
            $ay = reset($selected_ay);
            $report_subtitle .= "<br>Academic Year: " . htmlspecialchars(($ay['start_year'] && $ay['end_year'] && $ay['semester']) ? $ay['start_year'] . '-' . $ay['end_year'] . ' ' . $ay['semester'] : 'Unknown');
        }
    } else {
        $report_subtitle .= "<br>All Academic Years";
    }
    $tenants_payment_status = fetch_payment_status_tenants($conn, $filter_academic_year_id);
    if (!empty($tenants_payment_status)) {
        $document_content_html .= "<table class='table table-bordered table-striped'>";
        $document_content_html .= "<thead><tr><th>Tenant Name</th><th>Contact</th><th>Academic Year</th><th>Monthly Rent</th><th>Payment Status</th><th>Payments</th></tr></thead><tbody>";
        foreach ($tenants_payment_status as $tenant) {
            // Check if all payments are cash
            $payment_status = "Paid";
            if (!empty($tenant['payments'])) {
                foreach ($tenant['payments'] as $payment) {
                    if (strtolower($payment['payment_method']) !== 'cash') {
                        $payment_status = "Unpaid";
                        break; // If any payment is not cash, mark as Unpaid
                    }
                }
            } else {
                $payment_status = "Unpaid"; // No payments means Unpaid
            }
            $payment_details = "";
            foreach ($tenant['payments'] as $payment) {
                // Format payment_for_month_of as "Month YYYY"
                $formatted_month = 'N/A';
                if (!empty($payment['payment_for_month_of'])) {
                    try {
                        $date = new DateTime($payment['payment_for_month_of'] . '-01');
                        $formatted_month = $date->format('F Y'); // e.g., "May 2025"
                    } catch (Exception $e) {
                        $formatted_month = htmlspecialchars($payment['payment_for_month_of']); // Fallback to original if invalid
                    }
                }
                $payment_details .= "ID: " . htmlspecialchars($payment['payment_id'] ?? 'N/A') . " - ₱" . number_format($payment['payment_amount'] ?? 0, 2) . " (" . htmlspecialchars($payment['payment_date'] ?? 'N/A') . ", " . $formatted_month . ", " . htmlspecialchars($payment['payment_method'] ?? 'N/A') . ")<br>";
            }
            $payment_details = $payment_details ?: "No payments recorded";
            $tenant_name = ($tenant['first_name'] && $tenant['last_name']) ? $tenant['first_name'] . ' ' . $tenant['last_name'] : 'N/A';
            $document_content_html .= "<tr><td>" . htmlspecialchars($tenant_name) . "</td><td>" . htmlspecialchars($tenant['mobile_no'] ?? 'N/A') . "</td><td>" . htmlspecialchars($tenant['academic_year']) . "</td><td class='text-right'>₱ " . number_format($tenant['monthly_rent'] ?? 0, 2) . "</td><td>" . htmlspecialchars($payment_status) . "</td><td>" . $payment_details . "</td></tr>";
        }
        $document_content_html .= "</tbody></table>";
    } else {
        $document_content_html .= "<p class='alert alert-info'>No tenants found for the selected criteria.</p>";
    }
    break;

        case "income_report":
            $report_subtitle = "Generated on: " . date("Y-m-d H:i:s");
            $report_subtitle .= "<br>Period: " . ($filter_report_period_type === 'monthly' ? date("F Y", mktime(0, 0, 0, $filter_month, 1, $filter_year)) : $filter_year);
            $income_data = fetch_income_report($conn, $filter_report_period_type, $filter_year, $filter_month);
            if (!empty($income_data)) {
                $total_income = array_sum(array_column($income_data, 'payment_amount'));
                $document_content_html .= "<table class='table table-bordered table-striped'>";
                $document_content_html .= "<thead><tr><th>Payment ID</th><th>Tenant</th><th>Academic Year</th><th>Payment Date</th><th>Month Of</th><th>Amount</th></tr></thead><tbody>";
                foreach ($income_data as $income) {
                    $document_content_html .= "<tr><td>" . htmlspecialchars($income['payment_id'] ?? 'N/A') . "</td><td>" . htmlspecialchars($income['tenant_name']) . "</td><td>" . htmlspecialchars($income['academic_year']) . "</td><td>" . htmlspecialchars($income['payment_date'] ?? 'N/A') . "</td><td>" . htmlspecialchars($income['payment_for_month_of'] ?? 'N/A') . "</td><td class='text-right'>₱ " . number_format($income['payment_amount'] ?? 0, 2) . "</td></tr>";
                }
                $document_content_html .= "</tbody><tfoot><tr><th colspan='5' class='text-right'>Total Income:</th><th class='text-right'>₱ " . number_format($total_income, 2) . "</th></tr></tfoot></table>";
            } else { $document_content_html .= "<p class='alert alert-info'>No income recorded for the selected period.</p>"; }
            break;

        case "expenses_report":
            $report_subtitle = "Generated on: " . date("Y-m-d H:i:s");
            $report_subtitle .= "<br>Period: " . ($filter_report_period_type === 'monthly' ? date("F Y", mktime(0, 0, 0, $filter_month, 1, $filter_year)) : $filter_year);
            $expenses_data = fetch_expenses_report($conn, $filter_report_period_type, $filter_year, $filter_month);
            if (!empty($expenses_data)) {
                $total_expenses = array_sum(array_column($expenses_data, 'amount'));
                $document_content_html .= "<table class='table table-bordered table-striped'>";
                $document_content_html .= "<thead><tr><th>Expense ID</th><th>Description</th><th>Date</th><th>Amount</th></tr></thead><tbody>";
                foreach ($expenses_data as $expense) {
                    $document_content_html .= "<tr><td>" . htmlspecialchars($expense['monthly_expense_id'] ?? 'N/A') . "</td><td>" . htmlspecialchars($expense['description'] ?? 'N/A') . "</td><td>" . htmlspecialchars($expense['month'] ?? 'N/A') . "</td><td class='text-right'>₱ " . number_format($expense['amount'] ?? 0, 2) . "</td></tr>";
                }
                $document_content_html .= "</tbody><tfoot><tr><th colspan='3' class='text-right'>Total Expenses:</th><th class='text-right'>₱ " . number_format($total_expenses, 2) . "</th></tr></tfoot></table>";
            } else { $document_content_html .= "<p class='alert alert-info'>No expenses recorded for the selected period.</p>"; }
            break;

        case "tenant_payment_history":
            $report_subtitle = "Generated on: " . date("Y-m-d H:i:s");
            if ($filter_tenant_id) {
                $selected_tenant = array_filter($available_tenants_dropdown, fn($tenant) => $tenant['tenant_id'] == $filter_tenant_id);
                if ($selected_tenant) {
                    $tenant = reset($selected_tenant);
                    $report_subtitle .= "<br>Tenant: " . htmlspecialchars(($tenant['last_name'] && $tenant['first_name']) ? $tenant['last_name'] . ', ' . $tenant['first_name'] : 'Unknown');
                }
            } else { $report_subtitle .= "<br>All Tenants (Select a tenant to see history)"; }
            $payment_history = [];
            if ($filter_tenant_id) { $payment_history = fetch_tenant_payment_history($conn, $filter_tenant_id); }
            if (!empty($payment_history)) {
                $document_content_html .= "<table class='table table-bordered table-striped'>";
                $document_content_html .= "<thead><tr><th>Receipt #</th><th>Tenant</th><th>Academic Year</th><th>Payment Date</th><th>Month Of</th><th>Amount</th><th>Appliances</th><th>Method</th><th>Action</th></tr></thead><tbody>";
                foreach ($payment_history as $payment) {
                    $document_content_html .= "<tr><td>" . htmlspecialchars($payment['receipt_number'] ?? 'N/A') . "</td><td>" . htmlspecialchars($payment['tenant_name']) . "</td><td>" . htmlspecialchars($payment['academic_year']) . "</td><td>" . htmlspecialchars($payment['payment_date'] ?? 'N/A') . "</td><td>" . htmlspecialchars($payment['payment_for_month_of'] ?? 'N/A') . "</td><td class='text-right'>₱ " . number_format(($payment['payment_amount'] ?? 0) + ($payment['appliance_charges'] ?? 0), 2) . "</td><td>" . htmlspecialchars($payment['appliances']) . "</td><td>" . htmlspecialchars($payment['method'] ?? 'N/A') . "</td><td><button class='btn btn-sm btn-primary no-print' onclick='printReceipt(" . htmlspecialchars($payment['payment_id'] ?? 0) . ")'>View Receipt</button></td></tr>";
                }
                $document_content_html .= "</tbody></table>";
            } else {
                 if ($filter_tenant_id) { $document_content_html .= "<p class='alert alert-info'>No payment history found for the selected tenant.</p>"; } 
                 else { $document_content_html .= "<p class='alert alert-warning'>Please select a tenant to view payment history.</p>"; }
            }
            break;

        case "annual_financial_report":
            $report_subtitle = "Non-Airconditioned Room (3-Story Building)<br>Monthly Rate: Based on actual payments<br>Location: Brgy. Rizal, Southern Leyte";
            
            $financial_data = fetch_annual_financial_data($conn, $filter_year);
            
            $document_content_html .= "<h2 class='mt-3 mb-3'>Financial Overview for " . htmlspecialchars($filter_year) . "</h2>";
            
            if (!empty($financial_data['monthly_data'])) {
                $document_content_html .= "<table class='table table-bordered table-sm'>";
                $document_content_html .= "<thead class='table-light'>
                                            <tr>
                                                <th rowspan='2' class='align-middle text-center'>Month</th>
                                                <th rowspan='2' class='align-middle text-center'>Tenants</th>
                                                <th colspan='2' class='text-center'>Income</th>
                                                <th colspan='2' class='text-center'>Expenses</th>
                                                <th rowspan='2' class='align-middle text-center'>Total Expenses</th>
                                            </tr>
                                            <tr>
                                                <th class='text-center'>Monthly Payment</th>
                                                <th class='text-center'>Charges/ Overnight Stay/ Appliances</th>
                                                <th class='text-center'>Electric Bill</th>
                                                <th class='text-center'>Water Bill</th>
                                            </tr>
                                          </thead><tbody>";

                foreach ($financial_data['monthly_data'] as $row) {
                    $document_content_html .= "<tr>
                                                <td>" . htmlspecialchars($row['Month']) . "</td>
                                                <td class='text-center'>" . htmlspecialchars($row['Tenants']) . "</td>
                                                <td class='text-end'>₱ " . number_format($row['MonthlyPayment'], 2) . "</td>
                                                <td class='text-end'>₱ " . number_format($row['Charges'], 2) . "</td>
                                                <td class='text-end'>₱ " . number_format($row['ElectricBill'], 2) . "</td>
                                                <td class='text-end'>₱ " . number_format($row['WaterBill'], 2) . "</td>
                                                <td class='text-end fw-bold'>₱ " . number_format($row['TotalExpenses'], 2) . "</td>
                                              </tr>";
                }
                
                $totals = $financial_data['annual_totals'];
                $document_content_html .= "</tbody><tfoot class='table-light'>
                                            <tr>
                                                <th class='text-center'>TOTAL</th>
                                                <th class='text-center'>" . htmlspecialchars($totals['total_tenant_months']) . " (Avg: " . htmlspecialchars($totals['tenants_monthly_avg']) . ")</th>
                                                <th class='text-end'>₱ " . number_format($totals['monthly_payment'], 2) . "</th>
                                                <th class='text-end'>₱ " . number_format($totals['charges'], 2) . "</th>
                                                <th class='text-end'>₱ " . number_format($totals['electric_bill'], 2) . "</th>
                                                <th class='text-end'>₱ " . number_format($totals['water_bill'], 2) . "</th>
                                                <th class='text-end fw-bold'>₱ " . number_format($totals['total_expenses'], 2) . "</th>
                                            </tr>
                                          </tfoot></table>";

                $document_content_html .= "<div class='net-income-section mt-4'>";
                $document_content_html .= "<h4>Net Income Summary for " . htmlspecialchars($filter_year) . "</h4>";
                $total_annual_income = $totals['monthly_payment'] + $totals['charges'];
                $document_content_html .= "<p style='margin-bottom: 0.2rem;'><strong>Total Annual Income:</strong> ₱ " . number_format($total_annual_income, 2) . "</p>";
                $document_content_html .= "<p style='margin-bottom: 0.2rem;'><strong>Total Annual Expenses:</strong> ₱ " . number_format($totals['total_expenses'], 2) . "</p>";
                $document_content_html .= "<p><strong>Net Profit/Loss:</strong> <span class='fw-bold'>₱ " . number_format($financial_data['annual_net_income'], 2) . "</span></p>";
                $document_content_html .= "</div>";
            } else {
                $document_content_html .= "<p class='alert alert-info'>No financial data found for the year " . htmlspecialchars($filter_year) . ".</p>";
            }
            break;
        
        default:
            if ($selected_document_type) {
                 $report_subtitle = "Generated on: " . date("Y-m-d H:i:s");
                $document_content_html = "<p class='alert alert-warning'>Selected document type ('".htmlspecialchars($selected_document_type)."') is not yet implemented or is invalid.</p>";
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($document_title); ?> - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">

    <style>
        body { display: flex; }
        .main-content { flex-grow: 1; padding: 20px; overflow-x: auto; }
        .sidebar { height: 100vh; min-width: 280px; }
        .document-selector-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        .printable-content { padding: 20px; border: 1px solid #ccc; background-color: #fff; margin-top: 20px; }
        .filter-group { margin-bottom: 1rem; }
        .report-header h1 { margin-bottom: 0.5rem; font-size: 1.8rem; }
        .report-header .subtitle { font-size: 0.9rem; color: #555; margin-bottom: 1.5rem; line-height: 1.4; }
        
        .floor-section { page-break-inside: avoid; margin-bottom: 30px; }
        .floor-title { font-size: 1.3rem; border-left: 5px solid #0d6efd; }
        .room-section { page-break-inside: avoid; margin-bottom: 20px; }
        .room-title { font-size: 1.1rem; border-left: 5px solid #6c757d; }

        .net-income-section p { font-size: 1rem; }
        .net-income-section h4 { font-size: 1.2rem; margin-bottom: 0.8rem; }
        
        @media print {
            body { display: block !important; }
            .sidebar, .document-selector-section, .btn-print-action, .no-print, .modal-backdrop, .modal-header button.btn-close, .modal-footer button:not(:last-child) { display: none !important; }
            .main-content { padding: 0; margin: 0; width: 100%; overflow: visible; }
            .printable-content, .printable-modal-content { border: none; padding: 0; margin: 0; box-shadow: none; width: 100%; }
            table { width: 100% !important; border-collapse: collapse !important; font-size: 9pt; }
            th, td { border: 1px solid #333 !important; padding: 4px 6px !important; vertical-align: middle; }
            thead.table-light th, tfoot.table-light th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            h1, h2, h3, h4, h5, h6 { page-break-after: avoid; margin-top: 0.5em; margin-bottom: 0.3em;}
            .report-header h1 { font-size: 16pt; }
            .report-header .subtitle { font-size: 10pt; }
            h2 {font-size: 14pt;}
            h3 {font-size: 12pt;}
            h4 {font-size: 11pt;}
            .floor-section, .room-section, .net-income-section { page-break-inside: avoid; }
            a[href]:after { content: none !important; }
            .modal { position: relative !important; overflow: visible !important; }
            .modal-dialog { margin: 0 !important; max-width: 100% !important; width:100% !important; }
            .modal-content { border: 0 !important; box-shadow: none !important; }
            .modal-body { padding: 0 !important; }
            p {font-size: 10pt;}
            .table-sm th, .table-sm td { padding: 0.2rem 0.4rem !important; }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="report-header">
            <h1><?php echo htmlspecialchars($document_title); ?></h1>
            <?php if (!empty($report_subtitle)): ?>
                <div class="subtitle"><?php echo $report_subtitle; ?></div>
            <?php endif; ?>
        </div>
        <hr class="no-print">

        <div class="document-selector-section no-print">
            <form method="GET" action="printdocument.php" id="reportForm">
                <div class="row">
                    <div class="col-md-6 filter-group">
                        <label for="document_type" class="form-label">Select Document Type:</label>
                        <select name="document_type" id="document_type" class="form-select" onchange="toggleFilters()">
                            <option value="">-- Select a Report --</option>
                            <?php foreach ($document_types as $key => $value): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($selected_document_type == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($value); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 filter-group" id="floorFilterGroup" style="display:none;">
                        <label for="floor_id" class="form-label">Floor:</label>
                        <select name="floor_id" id="floor_id" class="form-select">
                            <option value="all">All Floors</option>
                            <?php foreach ($available_floors as $floor): ?>
                                <option value="<?php echo htmlspecialchars($floor['floor_id'] ?? ''); ?>" <?php echo ($filter_floor_id == $floor['floor_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($floor['floor_no'] ?? 'Unknown'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 filter-group" id="academicYearFilterGroup" style="display:none;">
                        <label for="academic_year_id" class="form-label">Academic Year & Semester:</label>
                        <select name="academic_year_id" id="academic_year_id" class="form-select">
                            <option value="">-- Select Academic Year --</option>
                            <?php foreach ($available_academic_years as $ay): ?>
                                <option value="<?php echo htmlspecialchars($ay['academic_year_id'] ?? ''); ?>" <?php echo ($filter_academic_year_id == $ay['academic_year_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($ay['start_year'] && $ay['end_year'] && $ay['semester']) 
                                        ? $ay['start_year'] . '-' . $ay['end_year'] . ' ' . $ay['semester'] 
                                        : 'Unknown'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 filter-group" id="tenantFilterGroup" style="display:none;">
                        <label for="filter_tenant_id" class="form-label">Tenant:</label>
                        <select name="filter_tenant_id" id="filter_tenant_id" class="form-select">
                            <option value="">-- Select Tenant --</option>
                            <?php foreach ($available_tenants_dropdown as $tenant): ?>
                                <option value="<?php echo htmlspecialchars($tenant['tenant_id'] ?? ''); ?>" <?php echo ($filter_tenant_id == $tenant['tenant_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($tenant['last_name'] && $tenant['first_name']) 
                                        ? $tenant['last_name'] . ', ' . $tenant['first_name'] 
                                        : 'Unknown'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 filter-group" id="periodTypeFilterGroup" style="display:none;">
                        <label for="report_period_type" class="form-label">Report Period:</label>
                        <select name="report_period_type" id="report_period_type" class="form-select" onchange="toggleYearMonthFilters()">
                            <option value="monthly" <?php echo ($filter_report_period_type == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="annually" <?php echo ($filter_report_period_type == 'annually') ? 'selected' : ''; ?>>Annually</option>
                        </select>
                    </div>
                    <div class="col-md-3 filter-group" id="yearFilterGroup" style="display:none;">
                        <label for="filter_year" class="form-label">Year:</label>
                        <select name="filter_year" id="filter_year" class="form-select">
                            <?php for ($y = date("Y"); $y >= date("Y") - 10; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($filter_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 filter-group" id="monthFilterGroup" style="display:none;">
                        <label for="filter_month" class="form-label">Month:</label>
                        <select name="filter_month" id="filter_month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($filter_month == $m) ? 'selected' : ''; ?>><?php echo date("F", mktime(0, 0, 0, $m, 10)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-2"><i class="fa fa-filter me-2"></i>Generate Report</button>
            </form>
        </div>

        <?php if ($selected_document_type && !empty($document_content_html)): ?>
            <div class="d-flex justify-content-end align-items-center my-3 no-print">
                <button class="btn btn-success btn-print-action" onclick="window.print();">
                    <i class="fa fa-print me-2"></i>Print This Report
                </button>
            </div>
            <div id="printableArea" class="printable-content">
                <?php echo $document_content_html; ?>
            </div>
        <?php elseif($selected_document_type && (strpos($document_content_html, 'alert-warning') !== false || strpos($document_content_html, 'alert-info') !== false)): ?>
             <div id="printableArea" class="printable-content">
                <?php echo $document_content_html; ?>
            </div>
        <?php elseif($selected_document_type): ?>
             <p class="alert alert-info mt-3">No data available for '<?php echo htmlspecialchars($document_types[$selected_document_type] ?? $selected_document_type); ?>' with the current filters or an issue occurred.</p>
        <?php elseif(!$selected_document_type): ?>
            <p class="alert alert-info mt-3">Please select a document type and apply filters to generate a report.</p>
        <?php endif; ?>

        <div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-labelledby="receiptPreviewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="receiptPreviewModalLabel">Receipt Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="receiptPreviewContent"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="printModalContent('receiptPreviewContent')">Print Receipt</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFilters() {
            const docType = document.getElementById('document_type').value;
            const floorFilter = document.getElementById('floorFilterGroup');
            const academicYearFilter = document.getElementById('academicYearFilterGroup');
            const periodTypeFilter = document.getElementById('periodTypeFilterGroup');
            const yearFilter = document.getElementById('yearFilterGroup');
            const monthFilter = document.getElementById('monthFilterGroup');
            const tenantFilter = document.getElementById('tenantFilterGroup');

            floorFilter.style.display = 'none';
            academicYearFilter.style.display = 'none';
            periodTypeFilter.style.display = 'none';
            yearFilter.style.display = 'none';
            monthFilter.style.display = 'none';
            tenantFilter.style.display = 'none';

            if (docType === 'tenant_list_by_floor') {
                floorFilter.style.display = 'block';
            } else if (docType === 'payment_status_tenants') {
                academicYearFilter.style.display = 'block';
            } else if (docType === 'income_report' || docType === 'expenses_report') {
                periodTypeFilter.style.display = 'block';
                yearFilter.style.display = 'block';
                toggleYearMonthFilters(); 
            } else if (docType === 'tenant_payment_history') {
                tenantFilter.style.display = 'block';
            } else if (docType === 'annual_financial_report') {
                yearFilter.style.display = 'block'; 
            }
        }

        function toggleYearMonthFilters() {
            const periodType = document.getElementById('report_period_type').value;
            const monthFilter = document.getElementById('monthFilterGroup');
            const currentDocType = document.getElementById('document_type').value;
            if (currentDocType === 'income_report' || currentDocType === 'expenses_report') {
                 monthFilter.style.display = (periodType === 'monthly') ? 'block' : 'none';
            } else {
                monthFilter.style.display = 'none';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilters(); 
        });

        async function printReceipt(paymentId) {
            const modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
            const modalBody = document.getElementById('receiptPreviewContent');
            modalBody.innerHTML = '<p class="text-center">Loading receipt details for Payment ID: ' + paymentId + '...</p>';
            modal.show();

            try {
                const response = await fetch(`fetch_receipt.php?payment_id=${paymentId}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                
                if (data.error) {
                    modalBody.innerHTML = `<p class="alert alert-danger">${data.error}</p>`;
                    return;
                }

                let totalAmount = (parseFloat(data.payment_amount) || 0) + (parseFloat(data.appliance_charges) || 0);

                modalBody.innerHTML = `
                       <div class="container printable-modal-content">
                        <style> 
                            .printable-modal-content { font-family: Arial, sans-serif; font-size: 10pt; }
                            .printable-modal-content h4 {font-size: 14pt; margin-bottom: 10px;}
                            .printable-modal-content p { margin-bottom: 5px; }
                            .printable-modal-content table { width: 100%; border-collapse: collapse; margin-top:10px; margin-bottom:10px; }
                            .printable-modal-content th, .printable-modal-content td { border: 1px solid #ccc; padding: 5px; text-align: left; }
                            .printable-modal-content .text-end { text-align: right; }
                            .printable-modal-content .signature-line { border-top: 1px solid #000; display: inline-block; width: 200px; margin-top: 30px;}
                        </style>
                        <div class="row mb-3">
                            <div class="col">
                                <h4>OFFICIAL RECEIPT</h4>
                                <p><strong>JEM Boardinghouse</strong><br>
                                Brgy. Rizal, Southern Leyte<br> 
                                Contact: [Your Contact No]</p>
                            </div>
                            <div class="col text-end">
                                <p><strong>Receipt No:</strong> ${data.receipt_number || 'N/A'}</p>
                                <p><strong>Date:</strong> ${data.payment_date ? new Date(data.payment_date).toLocaleDateString() : 'N/A'}</p>
                            </div>
                        </div>
                        <hr>
                        <p><strong>Received from:</strong> ${data.tenant_name || 'N/A'}</p>
                        <p><strong>Payment For:</strong> ${data.payment_for_month_of || 'N/A'}</p>
                        <table class="table table-sm">
                            <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <tr><td>Base Rent</td><td class="text-end">₱ ${parseFloat(data.payment_amount || 0).toFixed(2)}</td></tr>
                                ${(parseFloat(data.appliance_charges) || 0) > 0 ? `<tr><td>Appliance Fee (${data.appliances || 'N/A'})</td><td class="text-end">₱ ${parseFloat(data.appliance_charges).toFixed(2)}</td></tr>` : ''}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="text-end">TOTAL AMOUNT PAID:</th>
                                    <th class="text-end">₱ ${totalAmount.toFixed(2)}</th>
                                </tr>
                            </tfoot>
                        </table>
                        <p class='mt-3'><strong>Amount in words:</strong> ${numberToWords(totalAmount)}</p>
                        <hr>
                        <div class='row mt-4'>
                            <div class='col'>
                                <span class="signature-line"></span><br>
                                Received By (Admin Signature)
                            </div>
                            <div class='col text-end align-self-end'>
                                <em>Thank you! This serves as your official receipt.</em>
                            </div>
                        </div>
                    </div>
                `;
        } catch (error) {
            modalBody.innerHTML = '<p class="alert alert-danger">Error loading receipt details: ' + error.message + '</p>';
            console.error('Error fetching receipt:', error);
        }
    }

    function printModalContent(elementId) {
        const printContentEl = document.getElementById(elementId);
        if (!printContentEl) return;

        const printWindow = window.open('', '_blank', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print Receipt</title>');
        printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">');
        printWindow.document.write(printContentEl.querySelector('style') ? printContentEl.querySelector('style').outerHTML : ''); 
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContentEl.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus(); 
        
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    function numberToWords(amount) {
        const mainAmount = Math.floor(amount);
        const cents = Math.round((amount - mainAmount) * 100);
        let words = `[${mainAmount} Pesos`;
        if (cents > 0) {
            words += ` and ${cents} Centavos`;
        }
        words += " Only]";
        return words; 
    }
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>