<?php
header('Content-Type: application/json');

include '../connection/db.php';

// Check if academic_year_id is provided
if (!isset($_GET['academic_year_id']) || !is_numeric($_GET['academic_year_id'])) {
    echo json_encode(['error' => 'Invalid academic year ID']);
    exit;
}

$academic_year_id = $_GET['academic_year_id'];

// Fetch academic year details
$query = "SELECT start_year, semester FROM academic_years WHERE academic_year_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $academic_year_id);
$stmt->execute();
$result = $stmt->get_result();
$academicYear = $result->fetch_assoc();
$stmt->close();

if (!$academicYear) {
    echo json_encode(['error' => 'Academic year not found']);
    exit;
}

// Get month options based on semester
function getMonthOptions($year, $semester) {
    $months = [];
    $monthRanges = [
        'First' => [8, 9, 10, 11, 12], // August to December
        'Second' => [1, 2, 3, 4, 5],   // January to May
        'Summer' => [6, 7]             // June to July
    ];
    
    $allowedMonths = $monthRanges[$semester] ?? range(1, 12);
    
    foreach ($allowedMonths as $month) {
        $date = sprintf('%04d-%02d-01', $year, $month);
        $months[] = [
            'date' => $date,
            'display' => date('F Y', strtotime($date))
        ];
    }
    return $months;
}

$monthOptions = getMonthOptions($academicYear['start_year'], $academicYear['semester']);

echo json_encode(['months' => $monthOptions]);

$conn->close();
?>