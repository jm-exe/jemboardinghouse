<?php
session_start();
include('../connection/db.php');

$error = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to get next floor number with gap detection
function getNextFloorNumber($conn) {
    $floors = mysqli_query($conn, "SELECT floor_no FROM floors ORDER BY LENGTH(floor_no), floor_no");
    $numbers = [];
    
    while ($floor = mysqli_fetch_assoc($floors)) {
        if (preg_match('/FLR-(\d+)$/i', $floor['floor_no'], $matches)) {
            $numbers[] = (int)$matches[1];
        }
    }
    
    if (empty($numbers)) {
        return 'FLR-1';
    }
    
    // Find the lowest missing number starting from 1
    for ($i = 1; $i <= max($numbers) + 1; $i++) {
        if (!in_array($i, $numbers)) {
            return 'FLR-' . $i;
        }
    }
    
    return 'FLR-' . (max($numbers) + 1);
}

// Function to get next room number for a floor with gap detection
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
    
    // Find the lowest missing number starting from 1
    for ($i = 1; $i <= max($numbers) + 1; $i++) {
        if (!in_array($i, $numbers)) {
            return 'RM-' . $i;
        }
    }
    
    return 'RM-' . (max($numbers) + 1);
}

$next_floor_number = getNextFloorNumber($conn);

// Handle room delete
if (isset($_GET['delete'])) {
    $room_id = $_GET['delete'];
    
    // First delete all beds in this room
    $beds_result = mysqli_query($conn, "SELECT bed_id FROM beds WHERE room_id = $room_id");
    while ($bed = mysqli_fetch_assoc($beds_result)) {
        $bed_id = $bed['bed_id'];
        mysqli_query($conn, "DELETE FROM boarding WHERE bed_id = $bed_id");
    }
    mysqli_query($conn, "DELETE FROM beds WHERE room_id = $room_id");
    
    // Then delete the room
    mysqli_query($conn, "DELETE FROM rooms WHERE room_id = $room_id");
    header("Location: rooms.php");
    exit();
}

// Handle room add with image and beds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'], $_POST['floor_id'], $_POST['room_no'], $_POST['csrf_token'])) {
    // Verify CSRF token
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $floor_id = $_POST['floor_id'];
    $room_no = mysqli_real_escape_string($conn, $_POST['room_no']);
    $single_deck_count = isset($_POST['single_deck_count']) ? (int)$_POST['single_deck_count'] : 0;
    $double_deck_count = isset($_POST['double_deck_count']) ? (int)$_POST['double_deck_count'] : 0;
    $image_path = null;
    
    // Get the expected next room number
    $expected_room = getNextRoomNumber($conn, $floor_id);
    
    // Check if room matches the expected sequence
    if ($room_no != $expected_room) {
        $error = "Please add $expected_room first before adding other rooms on this floor";
    } else {
        // Check if room already exists on this floor
        $check = mysqli_query($conn, "SELECT room_id FROM rooms WHERE floor_id = '$floor_id' AND room_no = '$room_no'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Room '$room_no' already exists on this floor!";
        } else {
            // Process image upload
            if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] === 0) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir);
                $filename = basename($_FILES['room_image']['name']);
                $target_file = $upload_dir . time() . '_' . $filename;
                if (move_uploaded_file($_FILES['room_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }

            // Insert the room
            $insert_room = mysqli_query($conn, "INSERT INTO rooms (floor_id, room_no, room_image) 
                               VALUES ('$floor_id', '$room_no', " . ($image_path ? "'$image_path'" : "NULL") . ")");
            
            if ($insert_room) {
                $room_id = mysqli_insert_id($conn);
                $bed_counter = 1;
                
                // Create single deck beds (as Lower deck)
                for ($i = 0; $i < $single_deck_count; $i++) {
                    mysqli_query($conn, "INSERT INTO beds (bed_no, room_id, bed_type, deck, monthly_rent, status) 
                                       VALUES ($bed_counter, $room_id, 'Single', 'Lower', " . floatval($_POST['monthly_rent']) . ", 'Vacant')");
                    $bed_counter++;
                }
                
                // Create double deck beds (Upper and Lower)
                for ($i = 0; $i < $double_deck_count; $i++) {
                    // Upper bunk
                    mysqli_query($conn, "INSERT INTO beds (bed_no, room_id, bed_type, deck, monthly_rent, status) 
                                       VALUES ($bed_counter, $room_id, 'Double', 'Upper', " . floatval($_POST['monthly_rent']) . ", 'Vacant')");
                    $bed_counter++;
                    
                    // Lower bunk
                    mysqli_query($conn, "INSERT INTO beds (bed_no, room_id, bed_type, deck, monthly_rent, status) 
                                       VALUES ($bed_counter, $room_id, 'Double', 'Lower', " . floatval($_POST['monthly_rent']) . ", 'Vacant')");
                    $bed_counter++;
                }
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                header("Location: rooms.php");
                exit();
            } else {
                $error = "Failed to add room: " . mysqli_error($conn);
            }
        }
    }
}

// Handle room edit with optional image update or removal  
if (isset($_POST['edit_room_id'], $_POST['csrf_token'])) {
    // Verify CSRF token
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $room_id = $_POST['edit_room_id'];
    $new_floor_id = $_POST['edit_floor_id'];
    $new_room_no = mysqli_real_escape_string($conn, $_POST['edit_room_no']);
    $new_single_deck_count = isset($_POST['edit_single_deck_count']) ? (int)$_POST['edit_single_deck_count'] : 0;
    $new_double_deck_count = isset($_POST['edit_double_deck_count']) ? (int)$_POST['edit_double_deck_count'] : 0;
    $remove_image = isset($_POST['remove_image']);
    $new_image_path = null;
    $monthly_rent = floatval($_POST['edit_monthly_rent']);

    // Get current image
    $res = mysqli_query($conn, "SELECT room_image FROM rooms WHERE room_id = $room_id");
    $current = mysqli_fetch_assoc($res);
    $current_image = $current['room_image'];
    
    // Get current bed configuration
    $beds_res = mysqli_query($conn, "
        SELECT 
            SUM(bed_type = 'Single') as single_count,
            SUM(bed_type = 'Double' AND deck = 'Upper') as double_count
        FROM beds 
        WHERE room_id = $room_id
    ");
    $bed_counts = mysqli_fetch_assoc($beds_res);
    $current_single = $bed_counts['single_count'];
    $current_double = $bed_counts['double_count'];

    if (!$remove_image && isset($_FILES['edit_room_image']) && $_FILES['edit_room_image']['error'] === 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir);
        $filename = basename($_FILES['edit_room_image']['name']);
        $target_file = $upload_dir . time() . '_' . $filename;
        if (move_uploaded_file($_FILES['edit_room_image']['tmp_name'], $target_file)) {
            $new_image_path = $target_file;
            if ($current_image && file_exists($current_image)) unlink($current_image);
        }
    } elseif ($remove_image) {
        if ($current_image && file_exists($current_image)) unlink($current_image);
        $new_image_path = null;
    } else {
        $new_image_path = $current_image;
    }

    // Update room information
    mysqli_query($conn, "UPDATE rooms SET 
                        floor_id = '$new_floor_id', 
                        room_no = '$new_room_no', 
                        room_image = " . ($new_image_path ? "'$new_image_path'" : "NULL") . "
                        WHERE room_id = $room_id");

    // Update bed configuration if changed
    if ($current_single != $new_single_deck_count || $current_double != $new_double_deck_count) {
        // First delete existing beds
        mysqli_query($conn, "DELETE FROM beds WHERE room_id = $room_id");
        
        $bed_counter = 1;
        
        // Create new single deck beds (as Lower deck)
        for ($i = 0; $i < $new_single_deck_count; $i++) {
            mysqli_query($conn, "INSERT INTO beds (bed_no, room_id, bed_type, deck, monthly_rent, status) 
                               VALUES ($bed_counter, $room_id, 'Single', 'Lower', $monthly_rent, 'Vacant')");
            $bed_counter++;
        }
        
        // Create new double deck beds (Upper and Lower)
        for ($i = 0; $i < $new_double_deck_count; $i++) {
            // Upper bunk
            mysqli_query($conn, "INSERT INTO beds (bed_no, room_id, bed_type, deck, monthly_rent, status) 
                               VALUES ($bed_counter, $room_id, 'Double', 'Upper', $monthly_rent, 'Vacant')");
            $bed_counter++;
            
            // Lower bunk
            mysqli_query($conn, "INSERT INTO beds (bed_no, room_id, bed_type, deck, monthly_rent, status) 
                               VALUES ($bed_counter, $room_id, 'Double', 'Lower', $monthly_rent, 'Vacant')");
            $bed_counter++;
        }
    } else {
        // Just update the monthly rent for existing beds
        mysqli_query($conn, "UPDATE beds SET monthly_rent = $monthly_rent WHERE room_id = $room_id");
    }

    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    header("Location: rooms.php");
    exit();
}

// Handle floor CRUD operations
if (isset($_GET['delete_floor'])) {
    $floor_id = $_GET['delete_floor'];
    // First delete all rooms and their images in this floor
    $rooms_result = mysqli_query($conn, "SELECT room_id, room_image FROM rooms WHERE floor_id = $floor_id");
    while ($room = mysqli_fetch_assoc($rooms_result)) {
        $room_id = $room['room_id'];
        if ($room['room_image'] && file_exists($room['room_image'])) {
            unlink($room['room_image']);
        }
        
        // Delete all beds in this room
        mysqli_query($conn, "DELETE FROM beds WHERE room_id = $room_id");
    }
    mysqli_query($conn, "DELETE FROM rooms WHERE floor_id = $floor_id");
    mysqli_query($conn, "DELETE FROM floors WHERE floor_id = $floor_id");
    header("Location: rooms.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_floor'], $_POST['csrf_token'])) {
    // Verify CSRF token
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $floor_no = mysqli_real_escape_string($conn, $_POST['floor_no']);
    $next_expected = getNextFloorNumber($conn);
    
    // Check if floor already exists
    $check = mysqli_query($conn, "SELECT floor_id FROM floors WHERE floor_no = '$floor_no'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Floor '$floor_no' already exists!";
    } 
    // Validate sequential order
    elseif ($floor_no != $next_expected) {
        $error = "Please add $next_expected first before adding $floor_no";
    } else {
        mysqli_query($conn, "INSERT INTO floors (floor_no) VALUES ('$floor_no')");
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        header("Location: rooms.php");
        exit();
    }
}

if (isset($_POST['edit_floor_id'], $_POST['csrf_token'])) {
    // Verify CSRF token
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $floor_id = $_POST['edit_floor_id'];
    $new_floor_no = mysqli_real_escape_string($conn, $_POST['edit_floor_no']);
    
    // Check if new floor number already exists (excluding current floor)
    $check = mysqli_query($conn, "SELECT floor_id FROM floors WHERE floor_no = '$new_floor_no' AND floor_id != $floor_id");
    if (mysqli_num_rows($check) > 0) {
        $error = "Floor '$new_floor_no' already exists!";
    } else {
        mysqli_query($conn, "UPDATE floors SET floor_no = '$new_floor_no' WHERE floor_id = $floor_id");
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        header("Location: rooms.php");
        exit();
    }
}

// Get all floors with their rooms and bed configurations
$floors_with_rooms = [];
$floors_result = mysqli_query($conn, "SELECT * FROM floors ORDER BY LENGTH(floor_no), floor_no");
while ($floor = mysqli_fetch_assoc($floors_result)) {
    $floor_id = $floor['floor_id'];
    $rooms_result = mysqli_query($conn, "
        SELECT 
            rooms.room_id, 
            rooms.room_no,
            rooms.room_image, 
            rooms.floor_id,
            floors.floor_no,
            COUNT(beds.bed_id) AS total_beds,
            SUM(CASE WHEN beds.status = 'Vacant' THEN 1 ELSE 0 END) AS available_beds,
            MIN(beds.monthly_rent) AS min_rent,
            MAX(beds.monthly_rent) AS max_rent,
            SUM(CASE WHEN beds.bed_type = 'Single' THEN 1 ELSE 0 END) AS single_beds,
            SUM(CASE WHEN beds.bed_type = 'Double' AND beds.deck = 'Upper' THEN 1 ELSE 0 END) AS double_beds
        FROM rooms
        JOIN floors ON rooms.floor_id = floors.floor_id
        LEFT JOIN beds ON rooms.room_id = beds.room_id
        WHERE rooms.floor_id = $floor_id
        GROUP BY rooms.room_id
        ORDER BY rooms.room_no
    ");
    
    $rooms = [];
    while ($room = mysqli_fetch_assoc($rooms_result)) {
        $rooms[] = $room;
    }
    
    $floors_with_rooms[] = [
        'floor_id' => $floor['floor_id'],
        'floor_no' => $floor['floor_no'],
        'rooms' => $rooms
    ];
}

// Get floors for dropdown - sorted numerically (for add room form)
$floors = mysqli_query($conn, "SELECT * FROM floors ORDER BY LENGTH(floor_no), floor_no");
$all_floors = mysqli_query($conn, "SELECT * FROM floors ORDER BY LENGTH(floor_no), floor_no");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Room Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.2;
        }
        .action-btn .fa {
            font-size: 0.7rem;
        }
        .thumbnail {
            border-radius: 4px;
            transition: transform 0.3s ease-in-out;
            cursor: pointer;
        }
        .thumbnail:hover {
            transform: scale(1.1);
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        .nav-tabs .nav-link {
            font-weight: 500;
            padding: 0.75rem 1.5rem;
        }
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
        }
        .tab-content {
            border-left: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .existing-rooms-container {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            width: 300px;
        }
        .required-floor, .required-room {
            font-weight: bold;
            color: #dc3545;
        }
        .bed-configuration {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #dee2e6;
        }
        .rent-input {
            max-width: 150px;
        }
        .combined-offcanvas {
            width: 800px;
        }
        .floor-room-tabs .nav-link {
            font-weight: 500;
        }
        .floor-room-tab-content {
            padding: 15px;
        }
        .bed-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            margin-right: 0.25rem;
        }
        .single-bed {
            background-color: #d4edda;
            color: #155724;
        }
        .double-bed {
            background-color: #cce5ff;
            color: #004085;
        }
        .deck-info {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .bed-config-help {
            background-color: #e2f0fd;
            border-left: 4px solid #0d6efd;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
        .bed-config-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
        .progress-thin {
            height: 20px;
        }
    </style>
</head>
<body>

<!-- Error Alert Container -->
<div class="alert-container">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="flex-grow-1 p-4">
        <div class="container bg-white p-4 shadow rounded">
            <div class="header-container">
                <h2><i class="fa fa-door-open me-2"></i>Room Management</h2>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFloorRoomManagement">
                        <i class="fa fa-plus me-2"></i> Manage Floors & Rooms
                    </button>
                </div>
            </div>

            <!-- Combined Floor and Room Management Offcanvas -->
            <div class="offcanvas offcanvas-end w-50" id="offcanvasFloorRoomManagement">
                <div class="offcanvas-header">
                    <h5>Manage Floors & Rooms</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="nav nav-tabs floor-room-tabs" id="floorRoomTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="floors-tab" data-bs-toggle="tab" data-bs-target="#floors" type="button" role="tab" aria-controls="floors" aria-selected="true">
                                <i class="fa fa-layer-group me-1"></i> Floors
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="rooms-tab" data-bs-toggle="tab" data-bs-target="#rooms" type="button" role="tab" aria-controls="rooms" aria-selected="false">
                                <i class="fa fa-door-open me-1"></i> Rooms
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content floor-room-tab-content border border-top-0 rounded-bottom">
                        <!-- Floors Tab -->
                        <div class="tab-pane fade show active" id="floors" role="tabpanel" aria-labelledby="floors-tab">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="col-md-12">
                                    <label class="form-label">Floor Number</label>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text bg-light">Next required:</span>
                                        <input type="text" name="floor_no" class="form-control" 
                                               value="<?= htmlspecialchars($next_floor_number) ?>" 
                                               required readonly>
                                    </div>
                                    <div class="alert alert-info py-2">
                                        <i class="fas fa-info-circle me-2"></i>
                                        You must add <span class="required-floor"><?= htmlspecialchars($next_floor_number) ?></span> before adding higher floors
                                    </div>
                                </div>
                                <div class="col-md-12 text-end">
                                    <button type="submit" name="add_floor" class="btn btn-success">
                                        <i class="fa fa-plus me-1"></i> Add Floor
                                    </button>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <h5 class="mt-3">Existing Floors</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Floor Number</th>
                                            <th>Rooms</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        mysqli_data_seek($all_floors, 0);
                                        while ($floor = mysqli_fetch_assoc($all_floors)): 
                                            $room_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms WHERE floor_id = {$floor['floor_id']}"));
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($floor['floor_no']) ?></td>
                                            <td><?= $room_count['count'] ?> rooms</td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" 
                                                        data-bs-toggle="offcanvas" 
                                                        data-bs-target="#offcanvasEditFloor<?= $floor['floor_id'] ?>">
                                                    <i class="fa fa-edit"></i> Edit
                                                </button>
                                                <a href="?delete_floor=<?= $floor['floor_id'] ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('WARNING: This will delete ALL rooms and beds on this floor. Are you sure?')">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Rooms Tab -->
                        <div class="tab-pane fade" id="rooms" role="tabpanel" aria-labelledby="rooms-tab">
                            <form method="POST" enctype="multipart/form-data" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="col-md-6">
                                    <label class="form-label">Select Floor</label>
                                    <select name="floor_id" class="form-select" required id="floorSelect">
                                        <option value="" selected disabled>Choose Floor</option>
                                        <?php 
                                        mysqli_data_seek($floors, 0);
                                        while ($floor = mysqli_fetch_assoc($floors)): ?>
                                            <option value="<?= $floor['floor_id'] ?>"><?= htmlspecialchars($floor['floor_no']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Room Number</label>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text bg-light">Suggested:</span>
                                        <input type="text" name="room_no" class="form-control" id="suggestedRoomNo" required readonly>
                                    </div>
                                    <div class="alert alert-info py-2">
                                        <i class="fas fa-info-circle me-2"></i>
                                        You must add <span class="required-room" id="requiredRoomNo"></span> first before adding other rooms
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <label class="form-label">Room Image</label>
                                    <input type="file" name="room_image" class="form-control" accept="image/*">
                                </div>
                                
                                <div class="col-md-12 bed-configuration">
                                    <h6>Bed Configuration</h6>
                                    
                                    <div class="bed-config-help">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Single deck beds are assigned as Lower deck. Each double deck creates 2 beds (upper and lower bunks).
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Single Deck Beds</label>
                                            <input type="number" name="single_deck_count" class="form-control" value="0" min="0" max="10">
                                            <div class="deck-info">Assigned as Lower deck</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Double Deck Beds</label>
                                            <input type="number" name="double_deck_count" class="form-control" value="0" min="0" max="10">
                                            <div class="deck-info">Creates 2 beds (Upper + Lower)</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Monthly Rent per Bed</label>
                                        <div class="input-group rent-input">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" name="monthly_rent" class="form-control" value="2500" min="0" step="100" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 text-end">
                                    <button type="submit" name="add_room" class="btn btn-success">
                                        <i class="fa fa-plus me-1"></i> Add Room
                                    </button>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <h5 class="mt-3">Existing Rooms</h5>
                            <div class="existing-rooms-container">
                                <div id="roomsList">
                                    <p class="text-muted">Select a floor to view existing rooms</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floor Tabs Navigation -->
            <ul class="nav nav-tabs" id="floorTabs" role="tablist">
                <?php foreach ($floors_with_rooms as $index => $floor): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                                id="floor-<?= $floor['floor_id'] ?>-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#floor-<?= $floor['floor_id'] ?>" 
                                type="button" 
                                role="tab"
                                aria-controls="floor-<?= $floor['floor_id'] ?>"
                                aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                            <?= htmlspecialchars($floor['floor_no']) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Floor Tabs Content -->
            <div class="tab-content" id="floorTabsContent">
                <?php foreach ($floors_with_rooms as $index => $floor): ?>
                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
                         id="floor-<?= $floor['floor_id'] ?>" 
                         role="tabpanel"
                         aria-labelledby="floor-<?= $floor['floor_id'] ?>-tab">
                         
                        <!-- Rooms Table for this Floor -->
                        <table class="table table-bordered table-striped floor-table" id="floorTable-<?= $floor['floor_id'] ?>">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th>Room No</th>
                                    <th>Image</th>
                                    <th>Bed Configuration</th>
                                    <th>Monthly Rent</th>
                                    <th>Total Beds</th>
                                    <th>Available</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-center">
                                <?php foreach ($floor['rooms'] as $row): 
                                    // Calculate bed configuration display
                                    $single_count = $row['single_beds'];
                                    $double_count = $row['double_beds'];
                                    $total_beds = $row['total_beds'];
                                    $available_beds = $row['available_beds'];
                                    
                                    $bed_config = [];
                                    if ($single_count > 0) {
                                        $bed_config[] = '<span class="badge bed-badge single-bed">' . $single_count . ' Single (Lower)</span>';
                                    }
                                    if ($double_count > 0) {
                                        $bed_config[] = '<span class="badge bed-badge double-bed">' . $double_count . ' Double (Upper/Lower)</span>';
                                    }
                                    $bed_config_display = !empty($bed_config) ? implode(' ', $bed_config) : '<span class="text-muted">No beds</span>';
                                    
                                    // Calculate available beds percentage for progress bar
                                    $available_percentage = $total_beds > 0 ? ($available_beds / $total_beds) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['room_no']) ?></td>
                                    <td>
                                        <?php if (!empty($row['room_image'])): ?>
                                            <img src="<?= htmlspecialchars($row['room_image']) ?>" 
                                                 class="thumbnail" 
                                                 style="width: 60px; height: 40px; object-fit: cover;" 
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#imageModal" 
                                                 data-image="<?= htmlspecialchars($row['room_image']) ?>">
                                        <?php else: ?>
                                            <span class="text-muted">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $bed_config_display ?>
                                        <?php if ($double_count > 0): ?>
                                            <div class="deck-info">Total bunks: <?= $double_count * 2 ?> (<?= $double_count ?> upper + <?= $double_count ?> lower)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['min_rent'] == $row['max_rent']): ?>
                                            ₱<?= number_format($row['min_rent'], 2) ?>
                                        <?php else: ?>
                                            ₱<?= number_format($row['min_rent'], 2) ?> - ₱<?= number_format($row['max_rent'], 2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $total_beds ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress progress-thin flex-grow-1">
                                                <div class="progress-bar <?= $available_percentage == 100 ? 'bg-success' : ($available_percentage == 0 ? 'bg-danger' : 'bg-warning') ?>" 
                                                     role="progressbar" 
                                                     style="width: <?= $available_percentage ?>%" 
                                                     aria-valuenow="<?= $available_percentage ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <span class="ms-2"><?= $available_beds ?>/<?= $total_beds ?></span>
                                        </div>
                                    </td>
                                   <td>
                                        <div class="d-flex gap-1"> <!-- Flex container with small gap -->
                                            <button class="btn btn-sm btn-warning px-2 py-1" 
                                                    data-bs-toggle="offcanvas" 
                                                    data-bs-target="#offcanvasEditRoom<?= $row['room_id'] ?>">
                                                <i class="fas fa-edit fa-xs"></i> Edit
                                            </button>
                                            <a href="?delete=<?= $row['room_id'] ?>" 
                                            class="btn btn-sm btn-danger px-2 py-1" 
                                            onclick="return confirm('Delete this room and all its beds?')">
                                                <i class="fas fa-trash fa-xs"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Image Modal -->
            <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imageModalLabel">Room Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img id="modalImage" class="img-fluid" style="max-height: 500px; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
    

<!-- Edit Floor Offcanvases -->
<?php 
mysqli_data_seek($all_floors, 0);
while ($floor = mysqli_fetch_assoc($all_floors)): ?>
<div class="offcanvas offcanvas-end" id="offcanvasEditFloor<?= $floor['floor_id'] ?>">
    <div class="offcanvas-header">
        <h5>Edit Floor <?= htmlspecialchars($floor['floor_no']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="edit_floor_id" value="<?= $floor['floor_id'] ?>">
            <div class="mb-3">
                <label class="form-label">Floor Number/Name</label>
                <input type="text" name="edit_floor_no" class="form-control" 
                       value="<?= htmlspecialchars($floor['floor_no']) ?>" required>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-warning">
                    <i class="fa fa-edit me-1"></i>Update Floor
                </button>
            </div>
        </form>
    </div>
</div>
<?php endwhile; ?>

<!-- Edit Room Offcanvases - One for each room -->
<?php foreach ($floors_with_rooms as $floor): ?>
    <?php foreach ($floor['rooms'] as $row): 
        // Get current bed configuration for editing
        $beds_query = mysqli_query($conn, "
            SELECT 
                SUM(bed_type = 'Single') as single_count,
                SUM(bed_type = 'Double' AND deck = 'Upper') as double_count
            FROM beds 
            WHERE room_id = {$row['room_id']}
        ");
        $bed_counts = mysqli_fetch_assoc($beds_query);
        $current_single = $bed_counts['single_count'];
        $current_double = $bed_counts['double_count'];
        
        // Get current rent values
        $rent_query = mysqli_query($conn, "SELECT monthly_rent FROM beds WHERE room_id = {$row['room_id']} LIMIT 1");
        $current_rent = mysqli_fetch_assoc($rent_query)['monthly_rent'] ?? 0;
    ?>
        <div class="offcanvas offcanvas-end" id="offcanvasEditRoom<?= $row['room_id'] ?>">
            <div class="offcanvas-header">
                <h5>Edit Room <?= $row['room_no'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="edit_room_id" value="<?= $row['room_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Floor</label>
                        <select name="edit_floor_id" class="form-select" required>
                            <?php 
                            mysqli_data_seek($all_floors, 0);
                            while ($floor = mysqli_fetch_assoc($all_floors)): ?>
                                <option value="<?= $floor['floor_id'] ?>" <?= $floor['floor_id'] == $row['floor_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($floor['floor_no']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="edit_room_no" class="form-control" value="<?= htmlspecialchars($row['room_no']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Image</label>
                        <?php if (!empty($row['room_image'])): ?>
                            <div class="mb-2">
                                <img src="<?= htmlspecialchars($row['room_image']) ?>" class="img-thumbnail" style="max-height: 100px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage<?= $row['room_id'] ?>">
                                    <label class="form-check-label" for="removeImage<?= $row['room_id'] ?>">
                                        Remove current image
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="edit_room_image" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="mb-3 bed-configuration">
                        <h6>Bed Configuration</h6>
                        
                        <div class="bed-config-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Changing bed configuration will delete and recreate all beds in this room.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Single Deck Beds</label>
                                <input type="number" name="edit_single_deck_count" class="form-control" 
                                       value="<?= $current_single ?>" min="0" max="10">
                                <div class="deck-info">Assigned as Lower deck</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Double Deck Beds</label>
                                <input type="number" name="edit_double_deck_count" class="form-control" 
                                       value="<?= $current_double ?>" min="0" max="10">
                                <div class="deck-info">Creates 2 beds (Upper + Lower)</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monthly Rent per Bed</label>
                            <div class="input-group rent-input">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="edit_monthly_rent" class="form-control" 
                                    value="<?= number_format($current_rent, 2) ?>" min="0" step="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="fa fa-save me-1"></i>Update Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<!-- Move all scripts to the bottom -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        // Initialize DataTables for each floor table
        $('.floor-table').each(function() {
            $(this).DataTable({
                pageLength: 5,
                lengthMenu: [5, 10, 25, 50],
                language: {
                    searchPlaceholder: "Search Rooms",
                    search: ""
                }
            });
        });

        // Image modal functionality
        $('#imageModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var imageSrc = button.data('image');
            var modal = $(this);
            modal.find('#modalImage').attr('src', imageSrc);
        });

        // Load existing rooms when floor is selected
        $('#floorSelect').change(function() {
            var floorId = $(this).val();
            if (floorId) {
                // First get the next suggested room number
                $.ajax({
                    url: 'get_next_room.php',
                    type: 'GET',
                    data: { floor_id: floorId },
                    success: function(response) {
                        try {
                            var data = JSON.parse(response);
                            $('#suggestedRoomNo').val(data.next_room);
                            $('#requiredRoomNo').text(data.next_room);
                            
                            // Then load existing rooms for this floor
                            $.ajax({
                                url: 'get_rooms.php',
                                type: 'GET',
                                data: { floor_id: floorId },
                                success: function(roomsResponse) {
                                    $('#roomsList').html(roomsResponse);
                                },
                                    error: function() {
                                    $('#roomsList').html('<p class="text-danger">Error loading rooms</p>');
                                }
                            });
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        $('#suggestedRoomNo').val('');
                        $('#requiredRoomNo').text('');
                    }
                });
            } else {
                $('#suggestedRoomNo').val('');
                $('#requiredRoomNo').text('');
                $('#roomsList').html('<p class="text-muted">Select a floor to view existing rooms</p>');
            }
        });

        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });

    // Function to switch to rooms tab and select a floor
    function switchToRoomTab(floorId) {
        // Switch to Rooms tab
        var roomTab = new bootstrap.Tab(document.getElementById('rooms-tab'));
        roomTab.show();
        
        // Select the floor in the dropdown
        $('#floorSelect').val(floorId).trigger('change');
    }
</script>
</body>
</html>