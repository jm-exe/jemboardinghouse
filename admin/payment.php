<?php
session_start();
require '../connection/db.php';

// Generate unique receipt number
function generateReceiptNumber() {
    return 'BH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Function to get unpaid months for a tenant
function getUnpaidMonths($conn, $tenant_id) {
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
    $current_year = date('Y');
    
    $stmt = $conn->prepare("SELECT payment_for_month_of FROM payments 
                           WHERE boarding_id IN (SELECT boarding_id FROM boarding WHERE tenant_id = ?) 
                           AND YEAR(payment_date) = ?");
    $stmt->execute([$tenant_id, $current_year]);
    $paid_months = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return array_diff($months, $paid_months);
}

// Initialize variables
$success_message = "";
$error_message = "";
$show_receipt = false; 
$receipt_data = [];
$active_tab = isset($_GET['type']) && $_GET['type'] === 'reparations' ? 'reparations' : 'monthly';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get user_id from session (assuming auth system exists)
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1; // Fallback to 1 if not set
        $academic_year_id = 21; // Current academic year from dump

        // Get boarding_id for the tenant
        $tenant_id = intval($_POST['tenant_id']);
        $stmt = $conn->prepare("SELECT boarding_id FROM boarding WHERE tenant_id = ? LIMIT 1");
        $stmt->execute([$tenant_id]);
        $boarding = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$boarding) {
            throw new Exception("No active boarding record found for tenant!");
        }
        $boarding_id = $boarding['boarding_id'];

        if (isset($_POST['payment_type']) && $_POST['payment_type'] === 'reparations') {
            // Process reparation payment
            $payment_date = $_POST['payment_date'];
            $appliances = isset($_POST['appliances']) ? $_POST['appliances'] : [];
            $guest_nights = intval($_POST['guest_nights']);
            $damage_amount = floatval($_POST['damage_amount']);
            $early_termination = isset($_POST['early_termination']) ? 1 : 0;
            $amount_paid = floatval($_POST['amount_paid']);
            $description = htmlspecialchars(trim($_POST['description']));
            
            $appliance_charge = count($appliances) * 100.00;
            $guest_charge = $guest_nights * 250.00;
            $early_termination_charge = $early_termination * 3000.00;
            $total_amount = $appliance_charge + $guest_charge + $damage_amount + $early_termination_charge;
            
            if ($amount_paid < $total_amount) {
                throw new Exception("Amount paid is less than total amount due!");
            }
            
            $change_amount = $amount_paid - $total_amount;
            $receipt_number = generateReceiptNumber();
            $method = 'Cash'; // Default, adjust if form includes method
            $reason = $description ?: 'Reparation charges';
            
            $stmt = $conn->prepare("INSERT INTO payments 
                (user_id, boarding_id, total_amount, payment_amount, appliance_charge, guest_charge, 
                 damage_charge, early_termination_charge, payment_date, method, academic_year_id, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, $boarding_id, $total_amount, $amount_paid, $appliance_charge, $guest_charge,
                $damage_amount, $early_termination_charge, $payment_date, $method, $academic_year_id, $reason
            ]);
            $payment_id = $conn->lastInsertId();
            
            // Insert receipt
            $stmt = $conn->prepare("INSERT INTO receipts 
                (payment_id, receipt_number, tenant_id, amount, receipt_date, description) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$payment_id, $receipt_number, $tenant_id, $amount_paid, $payment_date, $reason]);
            
            // Log activity
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action_details) 
                                   VALUES (?, ?, ?)");
            $action_type = 'add_payment';
            $action_details = "Added reparation payment ID: $payment_id for tenant ID: $tenant_id, Amount: ₱$amount_paid";
            $stmt->execute([$user_id, $action_type, $action_details]);
            
            $stmt = $conn->prepare("SELECT first_name, last_name FROM tenants WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $receipt_data = [
                'type' => 'reparations',
                'receipt_number' => $receipt_number,
                'date' => date('F j, Y', strtotime($payment_date)),
                'tenant_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
                'tenant_id' => $tenant_id,
                'appliance_charge' => $appliance_charge,
                'guest_charge' => $guest_charge,
                'damage_charge' => $damage_amount,
                'early_termination_charge' => $early_termination_charge,
                'total_amount' => $total_amount,
                'amount_paid' => $amount_paid,
                'change_amount' => $change_amount,
                'description' => $description
            ];
            
            $show_receipt = true;
            $success_message = "Reparation payment recorded successfully!";
        } else {
            // Process monthly payment
            $payment_date = $_POST['payment_date'];
            $payment_month = $_POST['payment_month'];
            $water_bill = floatval($_POST['water_bill']);
            $electric_bill = floatval($_POST['electric_bill']);
            $amount_paid = floatval($_POST['amount_paid']);

            $rental_amount = 1100.00;
            $late_penalty = 0;
            $utility_surcharge = 0;

            $payment_day = (int)date('d', strtotime($payment_date));
            if ($payment_day > 14) {
                $weeks_late = ceil(($payment_day - 14) / 7);
                $late_penalty = $weeks_late * 50.00;
            }

            if ($water_bill > 3000) $utility_surcharge += 100.00;
            if ($electric_bill > 5000) $utility_surcharge += 100.00;

            $total_amount = $rental_amount + $late_penalty + $utility_surcharge;
            
            if ($amount_paid < $total_amount) {
                throw new Exception("Amount paid is less than total amount due!");
            }
            
            $change_amount = $amount_paid - $total_amount;
            $receipt_number = generateReceiptNumber();
            $method = 'Cash'; // Default, adjust if form includes method
            $reason = "Monthly payment for $payment_month";
            
            $stmt = $conn->prepare("INSERT INTO payments 
                (user_id, boarding_id, total_amount, payment_amount, late_penalty, utility_surcharge, 
                 payment_date, method, academic_year_id, payment_for_month_of, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, $boarding_id, $total_amount, $amount_paid, $late_penalty, $utility_surcharge,
                $payment_date, $method, $academic_year_id, $payment_month, $reason
            ]);
            $payment_id = $conn->lastInsertId();
            
            // Insert receipt
            $stmt = $conn->prepare("INSERT INTO receipts 
                (payment_id, receipt_number, tenant_id, amount, receipt_date, description) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$payment_id, $receipt_number, $tenant_id, $amount_paid, $payment_date, $reason]);
            
            // Log activity
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action_details) 
                                   VALUES (?, ?, ?)");
            $action_type = 'add_payment';
            $action_details = "Added monthly payment ID: $payment_id for tenant ID: $tenant_id, Amount: ₱$amount_paid";
            $stmt->execute([$user_id, $action_type, $action_details]);
            
            $stmt = $conn->prepare("SELECT first_name, last_name FROM tenants WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            $receipt_data = [
                'type' => 'monthly',
                'receipt_number' => $receipt_number,
                'date' => date('F j, Y', strtotime($payment_date)),
                'tenant_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
                'tenant_id' => $tenant_id,
                'month' => $payment_month,
                'rental_amount' => $rental_amount,
                'late_penalty' => $late_penalty,
                'utility_surcharge' => $utility_surcharge,
                'total_amount' => $total_amount,
                'amount_paid' => $amount_paid,
                'change_amount' => $change_amount
            ];
            
            $show_receipt = true;
            $success_message = "Monthly payment recorded successfully!";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boarding House Payment System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="CSS/sidebar.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #10b981;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #6b7280;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            margin: 0;
            overflow-x: hidden;
        }

        
        .sidebar-open {
            transform: translateX(0);
        }

        .sidebar ul {
            padding: 0;
            margin: 0;
            list-style: none;
            }

            .sidebar ul li {
            margin-bottom: 10px; /* Add space between items */
            }

            .sidebar ul li:last-child {
            margin-bottom: 0; /* Remove margin for last item */
            }


    

        .payment-nav {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .payment-tab {
            flex: 1;
            padding: 1rem;
            font-weight: 600;
            color: var(--gray);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .payment-tab:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .payment-tab.active {
            color: var(--primary-dark);
            background: #eff6ff;
        }

        .payment-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }

        .form-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            min-height: 400px;
        }

        .form-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
            box-sizing: border-box;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group label {
            position: absolute;
            top: 0.75rem;
            left: 1rem;
            font-size: 0.875rem;
            color: var(--gray);
            transition: all 0.2s ease;
            background: white;
            padding: 0 0.25rem;
            pointer-events: none;
        }

        .input-group input:not(:placeholder-shown) + label,
        .input-group select:not(:placeholder-shown) + label,
        .input-group textarea:not(:placeholder-shown) + label,
        .input-group input:focus + label,
        .input-group select:focus + label,
        .input-group textarea:focus + label {
            top: -0.75rem;
            left: 0.75rem;
            font-size: 0.75rem;
            color: var(--primary);
        }

        .input-currency {
            position: relative;
        }

        .input-currency input {
            padding-left: 2.5rem;
        }

        .input-currency span {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 0.875rem;
            z-index: 10;
        }
.checkbox-group {
    display: flex;
    align-items: center;
    min-height: 3.5rem; /* Matches other input groups for consistent alignment */
}

.checkbox-card {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    width: 100%; /* Ensures consistent width */
    background: white; /* Matches input field background */
    box-sizing: border-box;
    position: relative; /* Ensures proper stacking */
    z-index: 1; /* Prevents overlap by other elements */
}

.checkbox-card:hover {
    border-color: var(--primary);
    background: #f8fafc;
}

.checkbox-card input {
    margin-right: 0.5rem;
    cursor: pointer;
    z-index: 2; /* Ensures checkbox is clickable */
}

.checkbox-card .checkbox-content {
    font-size: 0.875rem;
    color: var(--gray);
}

.checkbox-card input:checked + .checkbox-content {
    color: var(--primary);
    font-weight: 500;
}

/* Add to your existing styles */
.input-group .flex.items-center {
    transition: all 0.2s ease;
    cursor: pointer;
}

.input-group .flex.items-center:hover {
    border-color: var(--primary);
}

.input-group input[type="checkbox"]:checked + label span:first-child {
    color: var(--primary);
    font-weight: 500;
}

.input-group input[type="checkbox"]:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.input-group .bg-gray-100 {
    transition: all 0.2s ease;
}

.input-group input[type="checkbox"]:checked ~ label .bg-gray-100 {
    background-color: #e0f2fe;
    color: var(--primary-dark);
}

.grid > .input-group {
    min-height: 3.5rem; /* Prevents collapse of input groups */
}

.input-group textarea {
    resize: vertical; /* Allows vertical resizing only */
    min-height: 80px;
    max-height: 200px;
}

        .submit-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .submit-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .receipt-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        .receipt-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem 0.75rem 0 0;
            text-align: center;
        }

        .receipt-divider {
            border-top: 1px dashed #d1d5db;
            margin: 1rem 0;
        }

        .receipt-total {
            font-weight: 600;
            color: var(--primary);
            font-size: 1.125rem;
        }

        .popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        .popup-content {
            background: white;
            border-radius: 0.75rem;
            max-width: 500px;
            width: 90%;
            padding: 2rem;
            position: relative;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin: 2rem 0;
        }

        .popup-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .search-bar {
            position: relative;
            margin-bottom: 2rem;
            max-width: 300px;
        }

        .search-bar input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .search-bar i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-card, .receipt-card * {
                visibility: visible;
            }
            .receipt-card {
                position: static;
                width: 100%;
                box-shadow: none;
                margin: 0;
            }
            .no-print, .sidebar, .payment-nav, .search-bar, .popup, .form-card {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Sidebar Toggle -->
    <button class="md:hidden fixed top-4 left-4 z-[1001] p-2 bg-primary text-white rounded-lg" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Search Bar -->
        <div class="search-bar">
            <input type="number" id="tenant_search" placeholder="Search by Tenant ID" min="1">
            <i class="fas fa-search"></i>
        </div>

        <!-- Payment Type Tabs -->
        <div class="payment-nav">
            <a href="?type=monthly" class="payment-tab flex items-center justify-center <?php echo $active_tab === 'monthly' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt mr-2"></i> Monthly Payments
            </a>
            <a href="?type=reparations" class="payment-tab flex items-center justify-center <?php echo $active_tab === 'reparations' ? 'active' : ''; ?>">
                <i class="fas fa-tools mr-2"></i> Reparations
            </a>
        </div>

        <!-- Tenant Info Popup -->
        <div class="popup" id="tenant_popup">
            <div class="popup-content">
                <span class="popup-close" onclick="closePopup()">×</span>
                <h2 class="text-xl font-semibold mb-4">Tenant Information</h2>
                <div id="tenant_info"></div>
            </div>
        </div>

        <?php if ($show_receipt): ?>
            <!-- Receipt Display -->
            <div class="receipt-card">
                <div class="receipt-header">
                    <h2 class="text-2xl font-bold">Boarding House Payment Receipt</h2>
                    <p class="text-sm opacity-80">Official Receipt #<?php echo htmlspecialchars($receipt_data['receipt_number']); ?></p>
                </div>
                <div class="p-6">
                    <div class="flex justify-between mb-3">
                        <span class="text-gray-600">Date:</span>
                        <span><?php echo htmlspecialchars($receipt_data['date']); ?></span>
                    </div>
                    <div class="flex justify-between mb-3">
                        <span class="text-gray-600">Tenant Name:</span>
                        <span><?php echo htmlspecialchars($receipt_data['tenant_name']); ?></span>
                    </div>
                    <div class="flex justify-between mb-3">
                        <span class="text-gray-600">Tenant ID:</span>
                        <span><?php echo htmlspecialchars($receipt_data['tenant_id']); ?></span>
                    </div>
                    
                    <?php if ($receipt_data['type'] === 'monthly'): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Payment Month:</span>
                            <span><?php echo htmlspecialchars($receipt_data['month']); ?></span>
                        </div>
                        <div class="receipt-divider"></div>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Rental Amount:</span>
                            <span>₱<?php echo number_format($receipt_data['rental_amount'], 2); ?></span>
                        </div>
                        <?php if ($receipt_data['late_penalty'] > 0): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Late Penalty:</span>
                            <span>₱<?php echo number_format($receipt_data['late_penalty'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($receipt_data['utility_surcharge'] > 0): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Utility Surcharge:</span>
                            <span>₱<?php echo number_format($receipt_data['utility_surcharge'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($receipt_data['appliance_charge'] > 0): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Appliance Charges:</span>
                            <span>₱<?php echo number_format($receipt_data['appliance_charge'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($receipt_data['guest_charge'] > 0): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Guest Charges:</span>
                            <span>₱<?php echo number_format($receipt_data['guest_charge'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($receipt_data['damage_charge'] > 0): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Damage Charges:</span>
                            <span>₱<?php echo number_format($receipt_data['damage_charge'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($receipt_data['early_termination_charge'] > 0): ?>
                        <div class="flex justify-between mb-3">
                            <span class="text-gray-600">Early Termination Charge:</span>
                            <span>₱<?php echo number_format($receipt_data['early_termination_charge'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($receipt_data['description'])): ?>
                        <div class="receipt-divider"></div>
                        <div class="mb-3">
                            <span class="text-gray-600 block mb-1">Description:</span>
                            <span><?php echo htmlspecialchars($receipt_data['description']); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="receipt-divider"></div>
                    <div class="receipt-total flex justify-between mb-3">
                        <span>Total Amount:</span>
                        <span>₱<?php echo number_format($receipt_data['total_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between mb-3">
                        <span class="text-gray-600">Amount Paid:</span>
                        <span>₱<?php echo number_format($receipt_data['amount_paid'], 2); ?></span>
                    </div>
                    <div class="flex justify-between mb-3">
                        <span class="text-gray-600">Change:</span>
                        <span>₱<?php echo number_format($receipt_data['change_amount'], 2); ?></span>
                    </div>
                </div>
                <div class="p-6 pt-0 flex justify-center gap-4 no-print">
                    <button onclick="window.print()" class="submit-btn">
                        <i class="fas fa-print mr-2"></i> Print Receipt
                    </button>
                    <a href="payment.php" class="submit-btn bg-gray-600 hover:bg-gray-700">
                        <i class="fas fa-undo mr-2"></i> New Payment
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Payment Forms -->
            <div class="form-card">
                <?php if ($error_message): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php elseif ($success_message): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($active_tab === 'monthly'): ?>
                    <!-- Monthly Payment Form -->
                    <div class="form-header">
                        <h2 class="text-xl font-semibold">Monthly Payment</h2>
                    </div>
                    <form method="POST" class="p-6" id="monthly-payment-form">
                        <input type="hidden" name="payment_type" value="monthly">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <input type="number" id="tenant_id" name="tenant_id" required placeholder=" " min="1">
                                <label for="tenant_id">Tenant ID</label>
                            </div>
                            <div class="input-group">
                                <input type="date" id="payment_date" name="payment_date" required placeholder=" ">
                                <label for="payment_date">Payment Date</label>
                            </div>
                            <div class="input-group">
                                <select id="payment_month" name="payment_month" required placeholder=" ">
                                    <option value="">Select Month</option>
                                    <?php
                                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                               'July', 'August', 'September', 'October', 'November', 'December'];
                                    $currentMonth = date('F');
                                    foreach ($months as $month) {
                                        $selected = ($month === $currentMonth) ? 'selected' : '';
                                        echo "<option value='$month' $selected>$month</option>";
                                    }
                                    ?>
                                </select>
                                <label for="payment_month">Payment Month</label>
                            </div>
                            <div class="input-group input-currency">
                                <span>₱</span>
                                <input type="number" id="water_bill" name="water_bill" value="0" min="0" step="0.01" placeholder=" ">
                                <label for="water_bill">Water Bill</label>
                            </div>
                            <div class="input-group input-currency">
                                <span>₱</span>
                                <input type="number" id="electric_bill" name="electric_bill" value="0" min="0" step="0.01" placeholder=" ">
                                <label for="electric_bill">Electric Bill</label>
                            </div>
                            <div class="input-group input-currency">
                                <span>₱</span>
                                <input type="number" id="amount_paid" name="amount_paid" required min="0" step="0.01" placeholder=" ">
                                <label for="amount_paid">Amount Paid</label>
                            </div>
                            <div class="input-group input-currency">
                                <span>₱</span>
                                <input type="text" id="calculated_total" readonly placeholder=" ">
                                <label for="calculated_total">Calculated Total</label>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="submit-btn" id="submit-monthly">
                                <i class="fas fa-save mr-2"></i> Record Payment
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                                    <!-- Reparations Payment Form -->
                <div class="form-header">
                    <h2 class="text-xl font-semibold">Reparation Payment</h2>
                </div>
                <form method="POST" class="p-6" id="reparation-payment-form">
                    <input type="hidden" name="payment_type" value="reparations">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="input-group">
                            <input type="number" id="rep_tenant_id" name="tenant_id" required placeholder=" " min="1">
                            <label for="rep_tenant_id">Tenant ID</label>
                        </div>
                        <div class="input-group">
                             <input type="date" id="rep_payment_date" name="payment_date" required placeholder=" ">
                            <label for="rep_payment_date">Payment Date</label>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block mb-2 font-medium text-gray-700">Appliances Used/Damaged <span class="text-sm text-gray-500">(₱100 each)</span></label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <label class="checkbox-card flex items-center">
                                    <input type="checkbox" name="appliances[]" value="rice_cooker">
                                    <span class="checkbox-content ml-2">Rice Cooker</span>
                                </label>
                                <label class="checkbox-card flex items-center">
                                    <input type="checkbox" name="appliances[]" value="electric_fan">
                                    <span class="checkbox-content ml-2">Electric Fan</span>
                                </label>
                                <label class="checkbox-card flex items-center">
                                    <input type="checkbox" name="appliances[]" value="water_heater">
                                    <span class="checkbox-content ml-2">Water Heater</span>
                                </label>
                                <label class="checkbox-card flex items-center">
                                    <input type="checkbox" name="appliances[]" value="laptop">
                                    <span class="checkbox-content ml-2">Laptop</span>
                                </label>
                                <label class="checkbox-card flex items-center">
                                    <input type="checkbox" name="appliances[]" value="flat_iron">
                                    <span class="checkbox-content ml-2">Flat Iron</span>
                                </label>
                            </div>
                        </div>
                        <div class="input-group">
                            <input type="number" id="guest_nights" name="guest_nights" value="0" min="0" placeholder=" ">
                            <label for="guest_nights">Guest Sleepover Nights <span class="text-sm text-gray-500">(₱250 per night)</span></label>
                        </div>
                        <div class="input-group input-currency">
                            <span>₱</span>
                            <input type="number" id="damage_amount" name="damage_amount" value="0" min="0" step="0.01" placeholder=" ">
                            <label for="damage_amount">Damage Charges</label>
                        </div>
                       <!-- Replace the existing early termination checkbox with this -->
<div class="input-group">
    <div class="flex items-center p-3 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition-colors">
        <input type="checkbox" id="early_termination" name="early_termination" value="1" 
               class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
        <label for="early_termination" class="ml-3 text-sm font-medium text-gray-700 flex items-center">
            <span>Early Termination Charge</span>
            <span class="ml-2 px-2 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded">₱3,000</span>
        </label>
    </div>
</div>
                        <div class="input-group md:col-span-2 mt-4">
                            <textarea id="description" name="description" rows="3" placeholder=" "></textarea>
                            <label for="description">Description</label>
                        </div>
                        <div class="input-group input-currency">
                            <span>₱</span>
                            <input type="number" id="rep_amount_paid" name="amount_paid" required min="0" step="0.01" placeholder=" ">
                            <label for="rep_amount_paid">Amount Paid</label>
                        </div>
                        <div class="input-group input-currency">
                            <span>₱</span>
                            <input type="text" id="rep_calculated_total" readonly placeholder=" ">
                            <label for="rep_calculated_total">Calculated Total</label>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end">
                        <button type="submit" class="submit-btn" id="submit-reparation">
                            <i class="fas fa-save mr-2"></i> Record Payment
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar for mobile
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('sidebar-open');
            }

            window.toggleSidebar = toggleSidebar;

            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const paymentDateInput = document.getElementById('payment_date');
            const repPaymentDateInput = document.getElementById('rep_payment_date');
            if (paymentDateInput) paymentDateInput.value = today;
            if (repPaymentDateInput) repPaymentDateInput.value = today;

            // Monthly payment calculation
            function calculateMonthlyTotal() {
                const rentalAmount = 1100.00;
                let latePenalty = 0;
                let utilitySurcharge = 0;

                const paymentDate = document.getElementById('payment_date')?.value;
                if (paymentDate) {
                    const paymentDay = new Date(paymentDate).getDate();
                    if (paymentDay > 14) {
                        const weeksLate = Math.ceil((paymentDay - 14) / 7);
                        latePenalty = weeksLate * 50.00;
                    }
                }

                const waterBill = parseFloat(document.getElementById('water_bill')?.value) || 0;
                const electricBill = parseFloat(document.getElementById('electric_bill')?.value) || 0;

                if (waterBill < 0 || electricBill < 0) {
                    alert('Bill amounts cannot be negative.');
                    return;
                }

                if (waterBill > 3000) utilitySurcharge += 100;
                if (electricBill > 5000) utilitySurcharge += 100;

                const totalAmount = rentalAmount + latePenalty + utilitySurcharge;
                const calculatedTotalInput = document.getElementById('calculated_total');
                if (calculatedTotalInput) {
                    calculatedTotalInput.value = totalAmount.toFixed(2);
                }

                const amountPaidInput = document.getElementById('amount_paid');
                if (amountPaidInput) {
                    amountPaidInput.min = totalAmount.toFixed(2);
                }
            }

            // Reparations payment calculation
            function calculateReparationsTotal() {
                const applianceCharge = document.querySelectorAll('input[name="appliances[]"]:checked').length * 100;
                const guestNights = parseInt(document.getElementById('guest_nights')?.value) || 0;
                const damageAmount = parseFloat(document.getElementById('damage_amount')?.value) || 0;
                const earlyTerminationCharge = document.getElementById('early_termination')?.checked ? 3000 : 0;

                if (guestNights < 0 || damageAmount < 0) {
                    alert('Input values cannot be negative.');
                    return;
                }

                const totalAmount = applianceCharge + (guestNights * 250) + damageAmount + earlyTerminationCharge;
                const repCalculatedTotalInput = document.getElementById('rep_calculated_total');
                if (repCalculatedTotalInput) {
                    repCalculatedTotalInput.value = totalAmount.toFixed(2);
                }

                const repAmountPaidInput = document.getElementById('rep_amount_paid');
                if (repAmountPaidInput) {
                    repAmountPaidInput.min = totalAmount.toFixed(2);
                }
            }

            // Add event listeners for monthly payment form
            if (document.getElementById('payment_date')) {
                ['payment_date', 'water_bill', 'electric_bill'].forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.addEventListener('change', calculateMonthlyTotal);
                        element.addEventListener('input', calculateMonthlyTotal);
                    }
                });
            }

            // Add event listeners for reparation payment form
            if (document.getElementById('rep_payment_date')) {
                ['guest_nights', 'damage_amount', 'early_termination'].forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.addEventListener('change', calculateReparationsTotal);
                        element.addEventListener('input', calculateReparationsTotal);
                    }
                });

                document.querySelectorAll('input[name="appliances[]"]').forEach(checkbox => {
                    checkbox.addEventListener('change', calculateReparationsTotal);
                });
            }

            // Initial calculations
            calculateMonthlyTotal();
            calculateReparationsTotal();

            // Form submission loading state
            const forms = [document.getElementById('monthly-payment-form'), document.getElementById('reparation-payment-form')];
            forms.forEach(form => {
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const submitBtn = form.querySelector('.submit-btn');
                        const amountPaid = parseFloat(form.querySelector('[name="amount_paid"]')?.value) || 0;
                        const totalAmount = parseFloat(form.querySelector('[id$="calculated_total"]')?.value) || 0;
                        if (amountPaid < totalAmount) {
                            e.preventDefault();
                            alert('Amount paid cannot be less than the total amount due.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Record Payment';
                            return;
                        }
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                    });
                }
            });

            // Search functionality
            const searchInput = document.getElementById('tenant_search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const tenantId = parseInt(this.value.trim());
                        if (tenantId > 0) {
                            fetchTenantInfo(tenantId);
                        } else {
                            showPopup('<p class="text-red-600">Please enter a valid Tenant ID.</p>');
                        }
                    }
                });
            }

            function fetchTenantInfo(tenantId) {
                fetch('payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'tenant_id=' + encodeURIComponent(tenantId)
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showPopup(`<p class="text-red-600">${data.error}</p>`);
                    } else {
                        const unpaidMonths = data.unpaid_months.length > 0 
                            ? data.unpaid_months.join(', ')
                            : 'All months paid';
                        const html = `
                            <p><strong>Tenant ID:</strong> ${data.tenant_id}</p>
                            <p><strong>Name:</strong> ${data.first_name} ${data.last_name}</p>
                            <p><strong>Contact:</strong> ${data.mobile_no || 'N/A'}</p>
                            <p><strong>Unpaid Months:</strong> ${unpaidMonths}</p>
                        `;
                        showPopup(html);
                    }
                })
                .catch(error => {
                    showPopup(`<p class="text-red-600">Error fetching tenant information: ${error.message}</p>`);
                });
            }

            function showPopup(content) {
                const popup = document.getElementById('tenant_popup');
                const popupContent = document.getElementById('tenant_info');
                popupContent.innerHTML = content;
                popup.style.display = 'flex';
            }

            function closePopup() {
                const popup = document.getElementById('tenant_popup');
                popup.style.display = 'none';
            }

            window.closePopup = closePopup;

            // Handle AJAX request for tenant info
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'])) {
                $tenant_id = intval($_POST['tenant_id']);
                $stmt = $conn->prepare("SELECT tenant_id, first_name, last_name, mobile_no 
                                       FROM tenants WHERE tenant_id = ?");
                $stmt->execute([$tenant_id]);
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tenant) {
                    $unpaid_months = getUnpaidMonths($conn, $tenant_id);
                    echo "echo json_encode([
                        'tenant_id' => {$tenant['tenant_id']},
                        'first_name' => '" . addslashes($tenant['first_name']) . "',
                        'last_name' => '" . addslashes($tenant['last_name']) . "',
                        'mobile_no' => '" . addslashes($tenant['mobile_no']) . "',
                        'unpaid_months' => " . json_encode($unpaid_months) . "
                    ]);";
                } else {
                    echo "echo json_encode(['error' => 'Tenant not found']);";
                }
                exit;
            }
            ?>
        });
    </script>
</body>
</html>