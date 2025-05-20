<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Handle Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appliance'])) {
    $appliance_name = trim($_POST['appliance_name']);
    $rate = trim($_POST['rate']);
    $appliance_id = isset($_POST['appliance_id']) ? intval($_POST['appliance_id']) : 0;

    if ($appliance_id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE appliances SET appliance_name = ?, rate = ? WHERE appliance_id = ?");
        $stmt->bind_param("sdi", $appliance_name, $rate, $appliance_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Appliance updated!";
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO appliances (appliance_name, rate) VALUES (?, ?)");
        $stmt->bind_param("sd", $appliance_name, $rate);
        $stmt->execute();
        $_SESSION['success_message'] = "Appliance added!";
    }
    header("Location: set-appliances.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $appliance_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM appliances WHERE appliance_id = ?");
    $stmt->bind_param("i", $appliance_id);
    $stmt->execute();
    $_SESSION['success_message'] = "Appliance deleted!";
    header("Location: set-appliances.php");
    exit;
}

// Fetch appliances for display/edit
$appliances = [];
$result = $conn->query("SELECT * FROM appliances ORDER BY appliance_name ASC");
if ($result && $result->num_rows > 0) {
    $appliances = $result->fetch_all(MYSQLI_ASSOC);
}

// Edit form population
$edit_appliance = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM appliances WHERE appliance_id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_appliance = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appliances and Rates</title>
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
            <h2><i class="bi bi-plug"></i> <strong>Appliances & Rates</strong></h2>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-pencil-square"></i>
                <?= $edit_appliance ? "Edit Appliance" : "Add Appliance" ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="alert-container">
                <?php if (!empty($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
            </div>
            <form method="POST" action="set-appliances.php" class="row g-3">
                <input type="hidden" name="appliance_id" value="<?= htmlspecialchars($edit_appliance['appliance_id'] ?? '') ?>">
                <div class="col-md-6">
                    <label class="form-label">Appliance Name</label>
                    <input type="text" name="appliance_name" class="form-control" value="<?= htmlspecialchars($edit_appliance['appliance_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rate (₱)</label>
                    <input type="number" step="0.01" min="0" name="rate" class="form-control" value="<?= htmlspecialchars($edit_appliance['rate'] ?? '') ?>" required>
                </div>
                <br>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" name="save_appliance" class="btn btn-primary btn-md">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-list"></i> Current Appliances</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($appliances)): ?>
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Appliance</th>
                        <th>Rate (₱)</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appliances as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['appliance_name']) ?></td>
                        <td><?= number_format($a['rate'], 2) ?></td>
                        <td>
                            <a href="?edit=<?= $a['appliance_id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="?delete=<?= $a['appliance_id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this appliance?')"><i class="bi bi-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="text-muted">No appliances added yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
