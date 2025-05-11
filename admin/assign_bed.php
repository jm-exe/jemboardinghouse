<?php
require_once 'connection/db.php';

if (!isset($_GET['tenant_id'])) {
    header("Location: tenant.php");
    exit;
}

$tenantId = (int)$_GET['tenant_id'];

// Fetch tenant info
$tenant = $conn->query("SELECT * FROM tenants WHERE tenant_id = $tenantId")->fetch_assoc();
if (!$tenant) {
    header("Location: tenant.php");
    exit;
}

// Fetch available beds
$beds = $conn->query("SELECT b.*, r.room_no, f.floor_no 
                     FROM beds b
                     JOIN rooms r ON b.room_id = r.room_id
                     JOIN floors f ON r.floor_id = f.floor_id
                     WHERE b.status = 'Vacant'
                     ORDER BY f.floor_no, r.room_no, b.bed_no")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bedId = (int)$_POST['bed_id'];
    $startDate = $_POST['start_date'];
    $dueDate = $_POST['due_date'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert boarding record
        $boardingStmt = $conn->prepare("INSERT INTO boarding 
                                      (tenant_id, bed_id, start_date, due_date) 
                                      VALUES (?, ?, ?, ?)");
        $boardingStmt->bind_param('iiss', $tenantId, $bedId, $startDate, $dueDate);
        $boardingStmt->execute();
        $boardingStmt->close();
        
        // Update bed status
        $bedStmt = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE bed_id = ?");
        $bedStmt->bind_param('i', $bedId);
        $bedStmt->execute();
        $bedStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        header("Location: tenant.php?success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error assigning bed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Bed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bed"></i> Assign Bed</h2>
            <a href="tenant.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Tenant Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?= htmlspecialchars($tenant['last_name'] . ', ' . $tenant['first_name']) ?></p>
                        <p><strong>Gender:</strong> <?= $tenant['gender'] === 'M' ? 'Male' : 'Female' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Mobile:</strong> <?= htmlspecialchars($tenant['mobile_no']) ?></p>
                        <p><strong>Birthdate:</strong> <?= date('M j, Y', strtotime($tenant['birthdate'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Available Beds</h5>
                
                <?php if (empty($beds)): ?>
                <div class="alert alert-warning">No available beds found. Please add beds first.</div>
                <?php else: ?>
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required 
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date *</label>
                            <input type="date" name="due_date" class="form-control" required 
                                   value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Bed</th>
                                    <th>Room</th>
                                    <th>Floor</th>
                                    <th>Deck</th>
                                    <th>Monthly Rent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($beds as $bed): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="bed_id" 
                                                   id="bed_<?= $bed['bed_id'] ?>" value="<?= $bed['bed_id'] ?>" required>
                                        </div>
                                    </td>
                                    <td><?= $bed['bed_no'] ?></td>
                                    <td><?= $bed['room_no'] ?></td>
                                    <td><?= $bed['floor_no'] ?></td>
                                    <td><?= $bed['deck'] ?></td>
                                    <td>₱<?= number_format($bed['monthly_rent'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Assign Bed
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
$conn->close();
?>