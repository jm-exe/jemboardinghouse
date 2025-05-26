<?php
session_start();
require_once '../connection/db.php';

// Initialize summary variables
$studentCount = 0;
$nonStudentCount = 0;
$totalTenants = 0;

// Get filter parameters
$search = $_GET['search'] ?? '';
$selectedYear = $_GET['academic_year_id'] ?? '';
$tenantType = $_GET['tenant_type'] ?? '';
$selectedFloor = $_GET['floor_id'] ?? '';
$selectedRoom = $_GET['room_id'] ?? '';

// Build the query
$query = "SELECT t.*, 
          CONCAT(t.last_name, ', ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS full_name,
          c.course_description,
          g.first_name AS guardian_first_name, g.last_name AS guardian_last_name,
          g.mobile_no AS guardian_mobile,
          b.bed_no, b.deck, b.status as bed_status, 
          r.room_no, r.room_id,
          f.floor_no, f.floor_id,
          a.start_year, a.end_year, a.semester,
          t.student_id
          FROM tenants t
          LEFT JOIN course c ON t.course_id = c.course_id
          LEFT JOIN guardians g ON t.guardian_id = g.guardian_id
          LEFT JOIN academic_years a ON t.academic_year_id = a.academic_year_id
          LEFT JOIN boarding bo ON t.tenant_id = bo.tenant_id
          LEFT JOIN beds b ON bo.bed_id = b.bed_id
          LEFT JOIN rooms r ON b.room_id = r.room_id
          LEFT JOIN floors f ON r.floor_id = f.floor_id
          WHERE 1=1";

$types = '';
$params = [];

if (!empty($search)) {
    $query .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.mobile_no LIKE ? 
              OR c.course_description LIKE ? OR g.last_name LIKE ? OR g.mobile_no LIKE ?)";
    $types .= str_repeat('s', 6);
    $searchTerm = "%$search%";
    $params = array_fill(0, 6, $searchTerm);
}

if (!empty($selectedYear)) {
    $query .= " AND t.academic_year_id = ?";
    $types .= 'i';
    $params[] = $selectedYear;
}

if (!empty($tenantType)) {
    $query .= " AND t.tenant_type = ?";
    $types .= 's';
    $params[] = $tenantType;
}

if (!empty($selectedFloor)) {
    $query .= " AND f.floor_id = ?";
    $types .= 'i';
    $params[] = $selectedFloor;
}

if (!empty($selectedRoom)) {
    $query .= " AND r.room_id = ?";
    $types .= 'i';
    $params[] = $selectedRoom;
}

$query .= " ORDER BY t.last_name, t.first_name";

// Execute query
$tenants = [];
if ($stmt = $conn->prepare($query)) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $tenants = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get current academic year
$currentYear = '';
$ayQuery = "SELECT start_year, end_year, semester FROM academic_years WHERE is_current = 1 LIMIT 1";
$ayResult = $conn->query($ayQuery);
if ($ayResult && $ayResult->num_rows > 0) {
    $currentYear = $ayResult->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant List - Radyx Boarding House</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 16px;
        }
        .print-date {
            text-align: right;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .filters {
            margin-bottom: 15px;
            font-size: 14px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .filters span {
            font-weight: bold;
            color: #007bff;
        }
        .room-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 20px;
        }
        .room-box {
            border: 1px solid #dee2e6;
            padding: 15px;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .room-header {
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #007bff;
            font-size: 18px;
        }
        .beds-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .bed-column {
            width: 48%;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .bed-header {
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
        }
        .tenant-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .tenant-list li {
            padding: 8px 5px;
            border-bottom: 1px solid #eee;
        }
        .tenant-list li:last-child {
            border-bottom: none;
        }
        .floor-header {
            width: 100%;
            background-color: #007bff;
            color: white;
            padding: 12px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 18px;
        }
        .summary {
            margin-top: 30px;
            font-size: 16px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .summary p {
            margin: 8px 0;
        }
        .summary strong {
            color: #007bff;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 10px;
            }
            .room-box {
                width: 100%;
                max-width: 100%;
                page-break-inside: avoid;
                box-shadow: none;
            }
            .floor-header {
                background-color: #007bff !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RADYX BOARDING-HOUSE</h1>
        <p>LIST OF TENANTS</p>
        <?php if ($currentYear): ?>
        <p>S.Y. <?= $currentYear['start_year'] ?> - <?= $currentYear['end_year'] ?>, <?= $currentYear['semester'] ?> Semester</p>
        <?php endif; ?>
    </div>
    
    <div class="print-date">
        Printed on: <?= date('F j, Y h:i A') ?>
    </div>
    
    <div class="filters">
        <strong>Filters Applied:</strong><br>
        <?php if (!empty($tenantType)): ?>
        Tenant Type: <span><?= $tenantType ?></span><br>
        <?php endif; ?>
        <?php if (!empty($selectedFloor)): ?>
        Floor: <span><?= $selectedFloor ?></span><br>
        <?php endif; ?>
        <?php if (!empty($selectedRoom)): ?>
        Room: <span><?= $selectedRoom ?></span><br>
        <?php endif; ?>
        <?php if (!empty($search)): ?>
        Search Term: <span><?= htmlspecialchars($search) ?></span><br>
        <?php endif; ?>
    </div>
    
    <?php 
    // Reorganize data by floor -> room -> deck
    $organizedData = [];
    foreach ($tenants as $tenant) {
        $floorNo = $tenant['floor_no'] ?? 'Unknown';
        $roomNo = $tenant['room_no'] ?? 'Unknown';
        $deck = $tenant['deck'] ?? 'Unknown';
        
        if (!isset($organizedData[$floorNo])) {
            $organizedData[$floorNo] = [];
        }
        if (!isset($organizedData[$floorNo][$roomNo])) {
            $organizedData[$floorNo][$roomNo] = [
                'Upper' => [],
                'Lower' => []
            ];
        }
        
        $organizedData[$floorNo][$roomNo][$deck][] = $tenant;
        
        // Count tenant types as we organize the data
        if ($tenant['tenant_type'] === 'Student') {
            $studentCount++;
        } else {
            $nonStudentCount++;
        }
        $totalTenants++;
    }
    
    // Sort floors and rooms numerically
    ksort($organizedData, SORT_NUMERIC);
    foreach ($organizedData as &$floor) {
        ksort($floor, SORT_NUMERIC);
    }
    
    foreach ($organizedData as $floorNo => $rooms): 
        $floorTenantCount = 0;
        foreach ($rooms as $room) {
            $floorTenantCount += count($room['Upper']) + count($room['Lower']);
        }
    ?>
    <div class="floor-header">
        Floor <?= $floorNo ?>: <?= $floorTenantCount ?> Tenants
    </div>
    
    <div class="room-container">
        <?php foreach ($rooms as $roomNo => $decks): ?>
        <div class="room-box">
            <div class="room-header">Room <?= $roomNo ?></div>
            <div class="beds-container">
                <div class="bed-column">
                    <div class="bed-header">Upper</div>
                    <ol class="tenant-list">
                        <?php foreach ($decks['Upper'] as $tenant): ?>
                        <li><?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <div class="bed-column">
                    <div class="bed-header">Lower</div>
                    <ol class="tenant-list">
                        <?php foreach ($decks['Lower'] as $tenant): ?>
                        <li><?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    
    <div class="summary">
        <p><strong>Summary:</strong></p>
        <p>Total Tenants: <?= $totalTenants ?></p>
        <p>Students: <?= $studentCount ?></p>
        <p>Non-Students: <?= $nonStudentCount ?></p>
    </div>
    
    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Print
        </button>
        <button onclick="window.close()" style="padding: 8px 15px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        // Close window after print (if not already closed)
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
</body>
</html>