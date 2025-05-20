<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Handle single bed update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bed'])) {
    $bed_id = intval($_POST['bed_id']);
    $monthly_rent = trim($_POST['monthly_rent']);
    $stmt = $conn->prepare("UPDATE beds SET monthly_rent = ? WHERE bed_id = ?");
    $stmt->bind_param("di", $monthly_rent, $bed_id);
    $stmt->execute();
    $_SESSION['success_message'] = "Monthly rate updated!";
    header("Location: set-monthly-rate.php");
    exit;
}

// Handle update ALL beds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all'])) {
    $new_rate = trim($_POST['new_rate']);
    if ($new_rate !== '' && is_numeric($new_rate)) {
        $stmt = $conn->prepare("UPDATE beds SET monthly_rent = ?");
        $stmt->bind_param("d", $new_rate);
        $stmt->execute();
        $_SESSION['success_message'] = "All beds updated to ₱" . number_format($new_rate, 2);
    } else {
        $_SESSION['success_message'] = "Invalid rate entered.";
    }
    header("Location: set-monthly-rate.php");
    exit;
}

// Fetch beds for display/edit
$beds = [];
$result = $conn->query("SELECT b.bed_id, b.bed_no, b.deck, b.monthly_rent, b.status, r.room_no 
                        FROM beds b 
                        JOIN rooms r ON b.room_id = r.room_id 
                        ORDER BY r.room_no ASC, b.bed_no ASC");
if ($result && $result->num_rows > 0) {
    $beds = $result->fetch_all(MYSQLI_ASSOC);
}

// Edit form population
$edit_bed = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT b.*, r.room_no FROM beds b JOIN rooms r ON b.room_id = r.room_id WHERE b.bed_id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_bed = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Monthly Rate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        h2 { color: #095544; }
        .card { border-radius: 10px; background-color: #e5edf0; box-shadow: 0 4px 20px rgba(0,0,0,0.1);}
        .d-flex { padding: 20px;}
        .alert-container { min-height: 60px;}
    </style>
</head>
<body>
<div class="d-flex">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="bi bi-currency-exchange"></i> <strong>Monthly Bed Rate</strong></h2>
        </div>
    </div>

    <!-- Bulk update card -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-collection"></i>
                Update ALL Beds Monthly Rate
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="set-monthly-rate.php" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Set All Beds to Monthly Rate (₱)</label>
                    <input type="number" step="0.01" min="0" name="new_rate" class="form-control" placeholder="Enter new monthly rate for all beds" required>
                </div>
                <div class="col-md-6 d-flex align-items-end justify-content-end">
                    <button type="submit" name="update_all" class="btn btn-danger btn-md">
                        <i class="bi bi-arrow-repeat"></i> Update All Beds
                    </button>
                </div>
            </form>
            <div class="alert-container mt-3">
                <?php if (!empty($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Individual edit card -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-pencil-square"></i>
                <?= $edit_bed ? "Edit Monthly Rate" : "Select Bed to Edit" ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if ($edit_bed): ?>
            <form method="POST" action="set-monthly-rate.php" class="row g-3">
                <input type="hidden" name="bed_id" value="<?= htmlspecialchars($edit_bed['bed_id']) ?>">
                <div class="col-md-3">
                    <label class="form-label">Room No.</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($edit_bed['room_no']) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bed No.</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($edit_bed['bed_no']) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Deck</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($edit_bed['deck']) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monthly Rent (₱)</label>
                    <input type="number" step="0.01" min="0" name="monthly_rent" class="form-control" value="<?= htmlspecialchars($edit_bed['monthly_rent']) ?>" required>
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" name="save_bed" class="btn btn-primary btn-md">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-list"></i> Current Bed Rates</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($beds)): ?>
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Bed No.</th>
                        <th>Deck</th>
                        <th>Status</th>
                        <th>Monthly Rent (₱)</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($beds as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['room_no']) ?></td>
                        <td><?= htmlspecialchars($b['bed_no']) ?></td>
                        <td><?= htmlspecialchars($b['deck']) ?></td>
                        <td><?= htmlspecialchars($b['status']) ?></td>
                        <td><?= number_format($b['monthly_rent'], 2) ?></td>
                        <td>
                            <a href="?edit=<?= $b['bed_id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="text-muted">No beds found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
