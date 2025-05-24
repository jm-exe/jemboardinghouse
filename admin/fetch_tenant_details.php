<?php
require 'db.php';
header('Content-Type: application/json');

$tenant_id = intval($_GET['tenant_id'] ?? 0);
if (!$tenant_id) {
    echo json_encode(['success' => false]);
    exit;
}

// Get tenant's most recent boarding assignment, with bed & room info
$sql = "SELECT r.room_no, b.bed_no, b.monthly_rent
        FROM boarding bo
        JOIN beds b ON bo.bed_id = b.bed_id
        JOIN rooms r ON b.room_id = r.room_id
        WHERE bo.tenant_id = ?
        ORDER BY bo.start_date DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$stmt->bind_result($room_no, $bed_no, $room_rate);

if ($stmt->fetch()) {
    echo json_encode([
        'success' => true,
        'room_no' => $room_no,
        'bed_no' => $bed_no,
        'room_rate' => $room_rate
    ]);
} else {
    echo json_encode(['success' => false]);
}
$stmt->close();
