<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../index.php');
  exit();
}

include('../connection/db.php');

// Fetch business name
$business_name_query = "SELECT setting_value FROM settings WHERE setting_name = 'business_name'";
$business_name_result = $conn->query($business_name_query);
$business_name = $business_name_result->fetch_assoc()['setting_value'] ?? 'Radyx BH';

// Fetch current academic year and semester
$academic_year_query = "SELECT start_year, end_year, semester, academic_year_id FROM academic_years WHERE is_current = 1";
$academic_year_result = $conn->query($academic_year_query);
$academic_year = $academic_year_result->fetch_assoc();
$academic_year_display = $academic_year ? "{$academic_year['start_year']}-{$academic_year['end_year']}, {$academic_year['semester']} Semester" : "2024-2025, Second Semester";
$current_academic_year_id = $academic_year['academic_year_id'] ?? 26;

// Update beds status based on active boardings
$update_beds_status = "UPDATE beds b
                       LEFT JOIN boarding bo ON b.bed_id = bo.bed_id
                       LEFT JOIN tenants t ON bo.tenant_id = t.tenant_id
                       AND t.academic_year_id = ?
                       AND bo.start_date <= CURDATE()
                       AND (bo.due_date IS NULL OR bo.due_date >= CURDATE())
                       SET b.status = CASE 
                         WHEN bo.boarding_id IS NOT NULL AND t.tenant_id IS NOT NULL THEN 'Occupied'
                         ELSE 'Vacant'
                       END";
$stmt = $conn->prepare($update_beds_status);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$stmt->close();

// Fetch total beds
$total_beds_query = "SELECT COUNT(*) as total_beds FROM beds";
$total_beds_result = $conn->query($total_beds_query);
$total_beds = $total_beds_result->fetch_assoc()['total_beds'] ?? 54;

// Fetch occupied beds
$occupied_beds_query = "SELECT COUNT(DISTINCT b.bed_id) as occupied_beds 
                        FROM boarding b 
                        JOIN tenants t ON b.tenant_id = t.tenant_id 
                        WHERE t.academic_year_id = ?
                        AND b.start_date <= CURDATE()
                        AND (b.due_date IS NULL OR b.due_date >= CURDATE())";
$stmt = $conn->prepare($occupied_beds_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$occupied_beds_result = $stmt->get_result();
$occupied_beds = $occupied_beds_result->fetch_assoc()['occupied_beds'] ?? 0;
$stmt->close();

// Fetch total tenants with student status
$total_tenants_query = "SELECT 
                        COUNT(DISTINCT b.tenant_id) as total_tenants,
                        SUM(CASE WHEN t.is_student = 1 THEN 1 ELSE 0 END) as student_tenants,
                        SUM(CASE WHEN t.is_student = 0 OR t.is_student IS NULL THEN 1 ELSE 0 END) as non_student_tenants
                        FROM boarding b 
                        JOIN tenants t ON b.tenant_id = t.tenant_id 
                        WHERE t.academic_year_id = ?
                        AND b.start_date <= CURDATE()
                        AND (b.due_date IS NULL OR b.due_date >= CURDATE())";
$stmt = $conn->prepare($total_tenants_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$total_tenants_result = $stmt->get_result();
$tenants_data = $total_tenants_result->fetch_assoc();
$total_tenants = $tenants_data['total_tenants'] ?? 0;
$student_tenants = $tenants_data['student_tenants'] ?? 0;
$non_student_tenants = $tenants_data['non_student_tenants'] ?? 0;
$stmt->close();

// Fetch semesterly income
$income_query = "SELECT ay.semester, COALESCE(SUM(p.payment_amount), 0) as total_income
                 FROM academic_years ay
                 LEFT JOIN payments p ON (p.academic_year_id = ay.academic_year_id OR p.academic_year_id IS NULL)
                 WHERE ay.academic_year_id = ?
                 GROUP BY ay.semester
                 UNION
                 SELECT 'First' as semester, 0 as total_income";
$stmt = $conn->prepare($income_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$income_result = $stmt->get_result();
$income_data = ['First' => 0, 'Second' => 0];
while ($row = $income_result->fetch_assoc()) {
    $income_data[$row['semester']] = $row['total_income'];
}
$stmt->close();

// Fetch semesterly expenses
$expenses_query = "SELECT ay.semester, COALESCE(SUM(me.amount), 0) as total_expenses
                   FROM academic_years ay
                   LEFT JOIN monthly_expenses me ON me.academic_year_id = ay.academic_year_id
                   WHERE ay.academic_year_id = ?
                   GROUP BY ay.semester
                   UNION
                   SELECT 'First' as semester, 0 as total_expenses";
$stmt = $conn->prepare($expenses_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$expenses_result = $stmt->get_result();
$expenses_data = ['First' => 0, 'Second' => 0];
while ($row = $expenses_result->fetch_assoc()) {
    $expenses_data[$row['semester']] = $row['total_expenses'];
}
$stmt->close();

// Fetch net income data
$net_income_first = $income_data['First'] - $expenses_data['First'];
$net_income_second = $income_data['Second'] - $expenses_data['Second'];
$total_net_income = $net_income_first + $net_income_second;

// Calculate profit/loss percentage
$profit_loss_first = ($income_data['First'] > 0) ? (($net_income_first / $income_data['First']) * 100) : 0;
$profit_loss_second = ($income_data['Second'] > 0) ? (($net_income_second / $income_data['Second']) * 100) : 0;

// Calculate expense ratios
$expense_ratio_first = ($income_data['First'] > 0) ? (($expenses_data['First'] / $income_data['First']) * 100) : 0;
$expense_ratio_second = ($income_data['Second'] > 0) ? (($expenses_data['Second'] / $income_data['Second']) * 100) : 0;

// Fetch upper and lower bunk beds and their occupancy
$upper_beds_query = "SELECT COUNT(*) as total_upper, SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied_upper 
                     FROM beds WHERE deck = 'Upper'";
$upper_beds_result = $conn->query($upper_beds_query);
$upper_beds = $upper_beds_result->fetch_assoc();
$total_upper = $upper_beds['total_upper'] ?? 0;
$occupied_upper = $upper_beds['occupied_upper'] ?? 0;

$lower_beds_query = "SELECT COUNT(*) as total_lower, SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied_lower 
                     FROM beds WHERE deck = 'Lower'";
$lower_beds_result = $conn->query($lower_beds_query);
$lower_beds = $lower_beds_result->fetch_assoc();
$total_lower = $lower_beds['total_lower'] ?? 0;
$occupied_lower = $lower_beds['occupied_lower'] ?? 0;

// Fetch tenants and occupancy per floor
$floor_tenants_query = "SELECT f.floor_no, 
                        COUNT(DISTINCT b.tenant_id) as tenant_count, 
                        COUNT(be.bed_id) as total_beds_floor, 
                        SUM(CASE WHEN be.status = 'Occupied' THEN 1 ELSE 0 END) as occupied_beds_floor
                        FROM floors f
                        LEFT JOIN rooms r ON r.floor_id = f.floor_id
                        LEFT JOIN beds be ON be.room_id = r.room_id
                        LEFT JOIN boarding b ON b.bed_id = be.bed_id
                        LEFT JOIN tenants t ON b.tenant_id = t.tenant_id
                        WHERE (t.academic_year_id = ? OR t.academic_year_id IS NULL)
                        AND (b.start_date <= CURDATE() AND (b.due_date IS NULL OR b.due_date >= CURDATE()) OR b.start_date IS NULL)
                        GROUP BY f.floor_id, f.floor_no";
$stmt = $conn->prepare($floor_tenants_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$floor_tenants_result = $stmt->get_result();
$floor_data = [];
while ($row = $floor_tenants_result->fetch_assoc()) {
    $floor_data[] = [
        'floor_no' => $row['floor_no'],
        'tenant_count' => $row['tenant_count'],
        'total_beds' => $row['total_beds_floor'],
        'occupied_beds' => $row['occupied_beds_floor']
    ];
}
$stmt->close();

// Fetch annual income data
$annual_income_query = "SELECT 
                        CONCAT(ay.start_year, '-', ay.end_year) as year_label,
                        SUM(CASE WHEN p.payment_type = 'Monthly Rent' OR p.reason LIKE '%room%' THEN p.payment_amount ELSE 0 END) as room_income,
                        SUM(CASE WHEN p.payment_type != 'Monthly Rent' AND (p.reason NOT LIKE '%room%' OR p.reason IS NULL) THEN p.payment_amount ELSE 0 END) as other_income
                        FROM academic_years ay
                        LEFT JOIN payments p ON (p.academic_year_id = ay.academic_year_id OR p.academic_year_id IS NULL)
                        GROUP BY ay.academic_year_id, ay.start_year, ay.end_year
                        ORDER BY ay.start_year DESC
                        LIMIT 5";
$annual_income_result = $conn->query($annual_income_query);
$annual_income_data = [];
while ($row = $annual_income_result->fetch_assoc()) {
    $annual_income_data[] = [
        'year_label' => $row['year_label'],
        'room_income' => $row['room_income'] ?? 0,
        'other_income' => $row['other_income'] ?? 0
    ];
}

// Fetch monthly income data
$monthly_income_query = "SELECT 
                         MONTHNAME(p.payment_date) as month_name, 
                         MONTH(p.payment_date) as month_num,
                         SUM(CASE WHEN p.payment_type = 'Monthly Rent' OR p.reason LIKE '%room%' THEN p.payment_amount ELSE 0 END) as room_income,
                         SUM(CASE WHEN p.payment_type != 'Monthly Rent' AND (p.reason NOT LIKE '%room%' OR p.reason IS NULL) THEN p.payment_amount ELSE 0 END) as other_income
                         FROM payments p
                         WHERE (p.academic_year_id = ? OR p.academic_year_id IS NULL)
                         AND YEAR(p.payment_date) = YEAR(CURDATE())
                         GROUP BY MONTH(p.payment_date), MONTHNAME(p.payment_date)
                         ORDER BY month_num";
$stmt = $conn->prepare($monthly_income_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$monthly_income_result = $stmt->get_result();
$monthly_income_data = [];
while ($row = $monthly_income_result->fetch_assoc()) {
    $monthly_income_data[] = [
        'month_name' => $row['month_name'],
        'month_num' => $row['month_num'],
        'room_income' => $row['room_income'] ?? 0,
        'other_income' => $row['other_income'] ?? 0
    ];
}
$stmt->close();

// Fetch income by category
$income_category_query = "SELECT 
                          SUM(CASE WHEN p.payment_type = 'Monthly Rent' OR p.reason LIKE '%room%' THEN p.payment_amount ELSE 0 END) as room_income,
                          SUM(CASE WHEN p.payment_type != 'Monthly Rent' AND (p.reason NOT LIKE '%room%' OR p.reason IS NULL) THEN p.payment_amount ELSE 0 END) as other_income
                          FROM payments p
                          WHERE (p.academic_year_id = ? OR p.academic_year_id IS NULL)";
$stmt = $conn->prepare($income_category_query);
$stmt->bind_param("i", $current_academic_year_id);
$stmt->execute();
$income_category_result = $stmt->get_result();
$income_category = $income_category_result->fetch_assoc();
$room_income = $income_category['room_income'] ?? 0;
$other_income = $income_category['other_income'] ?? 0;
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>
  <link href="CSS/dashboard.css" rel="stylesheet" />
  <link rel="stylesheet" href="CSS/sidebar.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <style>
    .parent {
      display: grid;
      grid-template-columns: repeat(8, 1fr);
      grid-template-rows: auto auto 1fr 1fr auto auto auto;
      gap: 16px;
      padding: 20px;
      min-height: calc(100vh - 60px);
      overflow-y: auto;
      font-family: 'Rubik', Tahoma, Geneva, Verdana, sans-serif;
    }
    .div1 {
      grid-column: 1 / -1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      min-height: 100px;
    }
    .div1 img {
      max-height: 80px;
      border-radius: 8px;
    }
    .div1 .boardinghouse-info h2 {
      margin: 0;
      font-size: 24px;
      color: #333;
    }
    .div1 .academic-year {
      font-size: 18px;
      font-weight: bold;
      color: #2f8656;
    }
    .div2 {
      grid-column: 1 / 5;
      grid-row: 2 / 5;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    .div4 {
      grid-column: 5 / 7;
      grid-row: 2 / 3;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
    }
    .div5 {
      grid-column: 7 / 9;
      grid-row: 2 / 3;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
    }
    .total-display {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }
    .total-display h4 {
      margin: 0;
      color: #333;
      font-size: 16px;
      font-weight: 600;
    }
    .total-amount {
      font-size: 28px;
      font-weight: bold;
      margin: 0;
    }
    .income-amount { color: #2f8656; }
    .expense-amount { color: #4287f5; }
    .tenant-amount { color: #ff6b35; }
    .currency-symbol {
      font-size: 20px;
      margin-right: 5px;
    }
    .div6 {
      grid-column: 5 / 7;
      grid-row: 3 / 5;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
    }
    .capacity-header {
      margin-bottom: 15px;
      text-align: center;
    }
    .capacity-header h4 {
      margin: 0;
      color: #333;
      font-size: 18px;
      font-weight: 600;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    .capacity-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      flex: 1;
      gap: 20px;
    }
    .chart-wrapper {
      position: relative;
      width: 200px;
      height: 200px;
      margin: 0 auto;
    }
    .capacity-stats {
      display: flex;
      justify-content: center;
      gap: 15px;
      flex-wrap: wrap;
      width: 100%;
    }
    .stat-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 12px 15px;
      border-radius: 8px;
      min-width: 80px;
    }
    .stat-item.occupied {
      background: rgba(47, 134, 86, 0.1);
      border: 1px solid rgba(47, 134, 86, 0.2);
    }
    .stat-item.vacant {
      background: rgba(224, 224, 224, 0.3);
      border: 1px solid rgba(224, 224, 224, 0.5);
    }
    .stat-item.total {
      background: rgba(66, 135, 245, 0.1);
      border: 1px solid rgba(66, 135, 245, 0.2);
    }
    .stat-number {
      font-size: 20px;
      font-weight: bold;
      color: #333;
      line-height: 1;
    }
    .stat-item.occupied .stat-number { color: #2f8656; }
    .stat-item.vacant .stat-number { color: #666; }
    .stat-item.total .stat-number { color: #4287f5; }
    .stat-label {
      font-size: 12px;
      color: #666;
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .div7 {
      grid-column: 7 / 9;
      grid-row: 3 / 5;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      overflow: auto;
    }
    .bunk-floor-info { width: 100%; }
    .section-title {
      margin: 0 0 15px 0;
      color: #333;
      font-size: 18px;
      font-weight: 600;
      text-align: center;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    .bunk-stats {
      display: flex;
      justify-content: space-around;
      margin-bottom: 20px;
      gap: 10px;
    }
    .bunk-stat {
      flex: 1;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      min-width: 120px;
    }
    .bunk-stat.upper {
      background: rgba(47, 134, 86, 0.1);
      border: 1px solid rgba(47, 134, 86, 0.2);
    }
    .bunk-stat.lower {
      background: rgba(66, 135, 245, 0.1);
      border: 1px solid rgba(66, 135, 245, 0.2);
    }
    .bunk-stat-value {
      font-size: 20px;
      font-weight: bold;
      margin: 5px 0;
    }
    .bunk-stat-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .floor-stats { margin-top: 15px; }
    .floor-stats-title {
      margin: 0 0 15px 0;
      color: #333;
      font-size: 16px;
      font-weight: 600;
      text-align: center;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    .floor-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .floor-item {
      background: #f9f9f9;
      padding: 12px 15px;
      border-radius: 8px;
      border-left: 4px solid #2f8656;
    }
    .floor-item p {
      margin: 0;
      color: #666;
      font-size: 14px;
      display: flex;
      justify-content: space-between;
    }
    .floor-item strong {
      color: #2f8656;
      margin-right: 5px;
    }
    .floor-occupancy {
      font-weight: 600;
      color: #4287f5;
    }
    .div8 {
      grid-column: 1 / 5;
      grid-row: 5 / 6;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      min-height: 400px;
    }
    .div9, .div10, .div11, .div12 {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      min-height: 400px;
    }
    .div9 { grid-column: 5 / 9; grid-row: 5 / 6; }
    .div10 { grid-column: 1 / 5; grid-row: 6 / 7; }
    .div11 { grid-column: 5 / 9; grid-row: 6 / 7; }
    .div12 {
    grid-column: 1 / -1;
    grid-row: 7 / 8;
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    min-height: 400px;
  }

.div12 .chart-container {
  flex: 1;
  position: relative;
  min-height: 300px;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;

}

.financial-summary-item {
  margin-bottom: 10px;
  padding-bottom: 10px;
  
  padding-right:50px;
  border-bottom: 1px solid #eee;
}

.financial-summary-item:last-child {
  border-bottom: none;
}
    .chart-container {
      flex: 1;
      position: relative;
      min-height: 300px;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .chart-container canvas {
      max-width: 100%;
      max-height: 100%;
      width: 100% !important;
      height: 100% !important;
    }
    .chart-title {
      margin: 0 0 15px 0;
      color: #333;
      font-size: 18px;
      font-weight: 600;
      text-align: center;
      padding: 10px;
      border-bottom: 2px solid #2f8656;
      background: #f9f9f9;
      border-radius: 8px;
    }
  </style>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="parent">
      <div class="div1">
        <div class="boardinghouse-info d-flex align-items-center">
          <img src="../Pictures/logo.png" alt="<?php echo htmlspecialchars($business_name); ?>">
          <h2 class="ms-3"><?php echo htmlspecialchars($business_name); ?></h2>
        </div>
        <div class="academic-year">
          Academic Year: <?php echo htmlspecialchars($academic_year_display); ?>
        </div>
      </div>
      <div class="div2">
        <?php include 'suggestions.php'; ?>
      </div>
      <div class="div4">
        <div class="total-display">
          <h4>Total Semesterly Income</h4>
          <h2 class="total-amount income-amount">
            <span class="currency-symbol">₱</span><?php echo number_format($income_data['First'] + $income_data['Second'], 2); ?>
          </h2>
        </div>
      </div>
      <div class="div5">
        <div class="total-display">
          <h4>Total Semesterly Expenses</h4>
          <h2 class="total-amount expense-amount">
            <span class="currency-symbol">₱</span><?php echo number_format($expenses_data['First'] + $expenses_data['Second'], 2); ?>
          </h2>
        </div>
      </div>
      <div class="div6">
        <div class="capacity-header">
          <h4>Overall Bed Capacity</h4>
        </div>
        <div class="capacity-content">
          <div class="chart-wrapper">
            <canvas id="overallCapacityChart"></canvas>
          </div>
          <div class="capacity-stats">
            <div class="stat-item occupied">
              <span class="stat-number"><?php echo $occupied_beds; ?></span>
              <span class="stat-label">Occupied</span>
            </div>
            <div class="stat-item vacant">
              <span class="stat-number"><?php echo $total_beds - $occupied_beds; ?></span>
              <span class="stat-label">Vacant</span>
            </div>
            <div class="stat-item total">
              <span class="stat-number"><?php echo $total_beds; ?></span>
              <span class="stat-label">Total Beds</span>
            </div>
          </div>
        </div>
      </div>
      <div class="div7">
        <div class="bunk-floor-info">
          <h4 class="section-title">Beds Occupancy</h4>
          <div class="bunk-stats">
            <div class="bunk-stat upper">
              <div class="bunk-stat-value"><?php echo $occupied_upper; ?>/<?php echo $total_upper; ?></div>
              <div class="bunk-stat-label">Upper Bunks</div>
            </div>
            <div class="bunk-stat lower">
              <div class="bunk-stat-value"><?php echo $occupied_lower; ?>/<?php echo $total_lower; ?></div>
              <div class="bunk-stat-label">Lower Bunks</div>
            </div>
          </div>
          <div class="floor-stats">
            <h5 class="floor-stats-title">Floor Occupancy</h5>
            <div class="floor-list">
              <?php foreach ($floor_data as $floor): ?>
                <div class="floor-item">
                  <p>
                    <strong>Floor <?php echo htmlspecialchars($floor['floor_no']); ?>:</strong>
                    <span class="floor-occupancy">
                      <?php echo $floor['occupied_beds']; ?>/<?php echo $floor['total_beds']; ?> beds
                    </span>
                  </p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="div8">
      <div class="total-display">
        <h4>Total Active Tenants</h4>
        <h2 class="total-amount tenant-amount"><?php echo $total_tenants; ?></h2>
        <div style="display: flex; gap: 15px; margin-top: 10px;">
          <div style="text-align: center;">
            <div style="font-size: 14px; color: #666;">Students</div>
            <div style="font-size: 18px; font-weight: bold; color: #2f8656;"><?php echo $student_tenants; ?></div>
          </div>
          <div style="text-align: center;">
            <div style="font-size: 14px; color: #666;">Non-Students</div>
            <div style="font-size: 18px; font-weight: bold; color: #4287f5;"><?php echo $non_student_tenants; ?></div>
          </div>
        </div>
      </div>
    </div>
      <div class="div9">
        <h4 class="chart-title">Annual Income Trend</h4>
        <div class="chart-container">
          <canvas id="annualIncomeChart"></canvas>
        </div>
      </div>
      <div class="div10">
        <h4 class="chart-title">Monthly Income (Current Year)</h4>
        <div class="chart-container">
          <canvas id="monthlyIncomeChart"></canvas>
        </div>
      </div>
      <div class="div11">
        <h4 class="chart-title">Income by Category</h4>
        <div class="chart-container">
          <canvas id="incomeCategoryChart"></canvas>
        </div>
      </div>
      <div class="div12">
  <h4 class="chart-title">Income vs Expenses Comparison</h4>
  <div style="display: flex; gap: 20px;">
    <div style="flex: 2; min-width: 0;">
      <div class="chart-container">
        <canvas id="incomeVsExpensesChart"></canvas>
      </div>
    </div>
    <div style="flex: 1; background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #2f8656;">
      <h5 style="margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">Financial Summary</h5>
      
      <div style="margin-bottom: 15px;">
        <div style="font-weight: 600; color: #333; margin-bottom: 5px;">Net Income:</div>
        <div style="display: flex; justify-content: space-between;">
          <span>First Semester:</span>
          <span style="font-weight: bold; color: <?php echo $net_income_first >= 0 ? '#2f8656' : '#d9534f'; ?>">
            ₱<?php echo number_format(abs($net_income_first), 2); ?>
            (<?php echo number_format($profit_loss_first, 1); ?>%)
          </span>
        </div>
        <div style="display: flex; justify-content: space-between;">
          <span>Second Semester:</span>
          <span style="font-weight: bold; color: <?php echo $net_income_second >= 0 ? '#2f8656' : '#d9534f'; ?>">
            ₱<?php echo number_format(abs($net_income_second), 2); ?>
            (<?php echo number_format($profit_loss_second, 1); ?>%)
          </span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 5px; border-top: 1px solid #eee; padding-top: 5px;">
          <span>Total:</span>
          <span style="font-weight: bold; color: <?php echo $total_net_income >= 0 ? '#2f8656' : '#d9534f'; ?>">
            ₱<?php echo number_format(abs($total_net_income), 2); ?>
          </span>
        </div>
      </div>
      
      <div style="margin-bottom: 15px;">
            <div style="font-weight: 600; color: #333; margin-bottom: 5px;">Expense Ratio:</div>
            <div style="display: flex; justify-content: space-between;">
              <span>First Semester:</span>
              <span style="font-weight: bold;"><?php echo number_format($expense_ratio_first, 1); ?>%</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span>Second Semester:</span>
              <span style="font-weight: bold;"><?php echo number_format($expense_ratio_second, 1); ?>%</span>
            </div>
          </div>
          
          <div>
            <div style="font-weight: 600; color: #333; margin-bottom: 5px;">Key Observations:</div>
            <ul style="padding-left: 20px; margin: 0; font-size: 14px;">
              <?php if ($net_income_first < 0): ?>
                <li>First semester had a loss of ₱<?php echo number_format(abs($net_income_first), 2); ?></li>
              <?php endif; ?>
              <?php if ($net_income_second < 0): ?>
                <li>Second semester had a loss of ₱<?php echo number_format(abs($net_income_second), 2); ?></li>
              <?php endif; ?>
              <?php if ($expense_ratio_first > 100): ?>
                <li>First semester expenses exceeded income</li>
              <?php endif; ?>
              <?php if ($expense_ratio_second > 100): ?>
                <li>Second semester expenses exceeded income</li>
              <?php endif; ?>
              <?php if ($net_income_first >= 0 && $net_income_second >= 0): ?>
                <li>Both semesters were profitable</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        function getCanvasContext(id) {
          const canvas = document.getElementById(id);
          if (!canvas) {
            console.error(`Canvas with ID ${id} not found`);
            return null;
          }
          return canvas.getContext('2d');
        }
        // Overall Capacity Chart
        const ctxCapacity = getCanvasContext('overallCapacityChart');
        if (ctxCapacity) {
          new Chart(ctxCapacity, {
            type: 'doughnut',
            data: {
              labels: ['Occupied', 'Vacant'],
              datasets: [{
                data: [<?php echo $occupied_beds; ?>, <?php echo $total_beds - $occupied_beds; ?>],
                backgroundColor: ['#2f8656', '#e0e0e0'],
                borderColor: ['#2f8656', '#e0e0e0'],
                borderWidth: 2,
                cutout: '65%'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { display: false },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      let label = context.label || '';
                      let value = context.raw || 0;
                      let total = <?php echo $total_beds; ?>;
                      let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                      return `${label}: ${value} beds (${percentage}%)`;
                    }
                  }
                }
              },
              layout: { padding: 0 },
              elements: { arc: { borderWidth: 2 } }
            }
          });
        }
        // Annual Income Chart
        const ctxAnnual = getCanvasContext('annualIncomeChart');
        if (ctxAnnual) {
          new Chart(ctxAnnual, {
            type: 'line',
            data: {
              labels: <?php echo json_encode(array_column($annual_income_data, 'year_label')); ?>.length ? <?php echo json_encode(array_column($annual_income_data, 'year_label')); ?> : ['No Data'],
              datasets: [{
                label: 'Room Payments',
                data: <?php echo json_encode(array_column($annual_income_data, 'room_income')); ?>.length ? <?php echo json_encode(array_column($annual_income_data, 'room_income')); ?> : [0],
                backgroundColor: 'rgba(47, 134, 86, 0.1)',
                borderColor: '#2f8656',
                borderWidth: 3,
                fill: true,
                tension: 0.4
              }, {
                label: 'Other Income',
                data: <?php echo json_encode(array_column($annual_income_data, 'other_income')); ?>.length ? <?php echo json_encode(array_column($annual_income_data, 'other_income')); ?> : [0],
                backgroundColor: 'rgba(66, 135, 245, 0.1)',
                borderColor: '#4287f5',
                borderWidth: 3,
                fill: true,
                tension: 0.4
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: { padding: 15, usePointStyle: true }
                },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      let label = context.dataset.label || '';
                      let value = context.raw || 0;
                      return `${label}: ₱${value.toLocaleString()}`;
                    }
                  }
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    callback: function(value) {
                      return '₱' + value.toLocaleString();
                    }
                  }
                }
              }
            }
          });
        }
        // Monthly Income Chart
        const ctxMonthly = getCanvasContext('monthlyIncomeChart');
        if (ctxMonthly) {
          new Chart(ctxMonthly, {
            type: 'bar',
            data: {
              labels: <?php echo json_encode(array_column($monthly_income_data, 'month_name')); ?>.length ? <?php echo json_encode(array_column($monthly_income_data, 'month_name')); ?> : ['No Data'],
              datasets: [{
                label: 'Room Payments',
                data: <?php echo json_encode(array_column($monthly_income_data, 'room_income')); ?>.length ? <?php echo json_encode(array_column($monthly_income_data, 'room_income')); ?> : [0],
                backgroundColor: '#2f8656',
                borderColor: '#2f8656',
                borderWidth: 1
              }, {
                label: 'Other Income',
                data: <?php echo json_encode(array_column($monthly_income_data, 'other_income')); ?>.length ? <?php echo json_encode(array_column($monthly_income_data, 'other_income')); ?> : [0],
                backgroundColor: '#4287f5',
                borderColor: '#4287f5',
                borderWidth: 1
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: { padding: 15, usePointStyle: true }
                },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      let label = context.dataset.label || '';
                      let value = context.raw || 0;
                      return `${label}: ₱${value.toLocaleString()}`;
                    }
                  }
                }
              },
              scales: {
                x: { stacked: true },
                y: {
                  stacked: true,
                  beginAtZero: true,
                  ticks: {
                    callback: function(value) {
                      return '₱' + value.toLocaleString();
                    }
                  }
                }
              }
            }
          });
        }
        // Income Category Chart
        const ctxCategory = getCanvasContext('incomeCategoryChart');
        if (ctxCategory) {
          const categoryData = [<?php echo $room_income; ?>, <?php echo $other_income; ?>];
          new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
              labels: ['Room Payments', 'Other Income'],
              datasets: [{
                data: categoryData.every(val => val === 0) ? [1, 1] : categoryData,
                backgroundColor: ['#2f8656', '#4287f5'],
                borderColor: ['#2f8656', '#4287f5'],
                borderWidth: 2,
                cutout: '50%'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: { padding: 15, usePointStyle: true }
                },
                tooltip: {
                  enabled: !categoryData.every(val => val === 0),
                  callbacks: {
                    label: function(context) {
                      let label = context.label || '';
                      let value = context.raw || 0;
                      let total = <?php echo $room_income + $other_income; ?>;
                      let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                      return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                    }
                  }
                }
              }
            }
          });
        }
        // Income vs Expenses Chart
        const ctxComparison = getCanvasContext('incomeVsExpensesChart');
        if (ctxComparison) {
          new Chart(ctxComparison, {
            type: 'bar',
            data: {
              labels: ['First Semester', 'Second Semester'],
              datasets: [{
                label: 'Income',
                data: [<?php echo $income_data['First']; ?>, <?php echo $income_data['Second']; ?>],
                backgroundColor: '#2f8656',
                borderColor: '#2f8656',
                borderWidth: 1
              }, {
                label: 'Expenses',
                data: [<?php echo $expenses_data['First']; ?>, <?php echo $expenses_data['Second']; ?>],
                backgroundColor: '#4287f5',
                borderColor: '#4287f5',
                borderWidth: 1
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'top',
                  labels: { padding: 20, usePointStyle: true }
                },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      let label = context.dataset.label || '';
                      let value = context.raw || 0;
                      return `${label}: ₱${value.toLocaleString()}`;
                    }
                  }
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    callback: function(value) {
                      return '₱' + value.toLocaleString();
                    }
                  }
                }
              }
            }
          });
        }
      });
    </script>
  </div>
</body>
</html>