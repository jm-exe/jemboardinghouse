<?php
include('../connection/db.php');

if (isset($_GET['floor_id'])) {
    $floor_id = intval($_GET['floor_id']);
    
    function getNextRoomNumber($conn, $floor_id) {
        $rooms = mysqli_query($conn, "SELECT room_no FROM rooms WHERE floor_id = $floor_id ORDER BY LENGTH(room_no), room_no");
        $numbers = [];
        
        while ($room = mysqli_fetch_assoc($rooms)) {
            if (preg_match('/RM-(\d+)$/i', $room['room_no'], $matches)) {
                $numbers[] = (int)$matches[1];
            }
        }
        
        if (empty($numbers)) {
            return 'RM-1';
        }
        
        // Find the lowest missing number starting from 0
        for ($i = 1; $i <= max($numbers) + 1; $i++) {
            if (!in_array($i, $numbers)) {
                return 'RM-' . $i;
            }
        }
        
        return 'RM-' . (max($numbers) + 1);
    }
    
    $next_room = getNextRoomNumber($conn, $floor_id);
    echo json_encode(['next_room' => $next_room]);
}
?>