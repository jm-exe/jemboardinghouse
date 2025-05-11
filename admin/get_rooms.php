<?php
include('../connection/db.php');

if (isset($_GET['floor_id'])) {
    $floor_id = intval($_GET['floor_id']);
    
    $result = mysqli_query($conn, "
        SELECT room_id, room_no, room_image 
        FROM rooms 
        WHERE floor_id = $floor_id 
        ORDER BY CAST(room_no AS UNSIGNED)
    ");
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Room No</th><th>Image</th></tr></thead>';
        echo '<tbody>';
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['room_no']) . '</td>';
            echo '<td>';
            if (!empty($row['room_image'])) {
                echo '<img src="' . htmlspecialchars($row['room_image']) . '" style="width:40px;height:30px;object-fit:cover;">';
            } else {
                echo '<span class="text-muted">None</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-muted">No rooms on this floor yet</p>';
    }
} else {
    echo '<p class="text-danger">Error: Floor ID not provided</p>';
}
?>