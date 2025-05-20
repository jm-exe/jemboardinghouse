<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Add at the top of your PHP file
$setting_labels = [
    'late_payment_rate' => 'Late Payment Rate',
    'overnight_guest_rate' => 'Overnight Rate (Guest)',
    'overuse_water_fee' => 'Water Overuse Charge Fee',
    'overuse_electric_fee' => 'Water Overuse Charge Fee'
    // add more as needed
];


// Handle Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setting'])) {
    $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;
    $setting_name = trim($_POST['setting_name']);
    $setting_value = trim($_POST['setting_value']);

    if ($setting_id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_id = ?");
        $stmt->bind_param("di", $setting_value, $setting_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Setting updated!";
    } else {
        // Insert (rarely used here)
        $stmt = $conn->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");
        $stmt->bind_param("sd", $setting_name, $setting_value);
        $stmt->execute();
        $_SESSION['success_message'] = "Setting added!";
    }
    header("Location: set-rates.php");
    exit;
}

// Fetch all settings for display/edit
$settings = [];
$result = $conn->query("SELECT * FROM settings ORDER BY setting_name ASC");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_all(MYSQLI_ASSOC);
}

// Edit form population
$edit_setting = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM settings WHERE setting_id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_setting = $stmt->get_result()->fetch_assoc();
}

$setting_code = $edit_setting['setting_name'] ?? '';
$setting_label = $setting_labels[$setting_code] ?? $setting_code;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Rates and Fees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/sidebar.css">
</head>
<body>
<div class="d-flex">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><strong>Rates & Fees</strong></h2>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><?= $edit_setting ? "Edit Setting" : "Add Setting" ?></h5>
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
            <form method="POST" action="set-rates.php" class="row g-3">
                <input type="hidden" name="setting_id" value="<?= htmlspecialchars($edit_setting['setting_id'] ?? '') ?>">
                <input type="hidden" name="setting_name" value="<?= htmlspecialchars($setting_code) ?>">
                <div class="col-md-6">
                    <label class="form-label">Setting Name</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($setting_label) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rate / Fee (₱)</label>
                    <input type="number" step="0.01" min="0" name="setting_value" class="form-control"
                        value="<?= htmlspecialchars($edit_setting['setting_value'] ?? '') ?>" required>
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" name="save_setting" class="btn btn-primary btn-md">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </form>`

        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Current Settings</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($settings)): ?>
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Value (₱)</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($settings as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($setting_labels[$s['setting_name']] ?? $s['setting_name']) ?></td>
                        <td><?= number_format($s['setting_value'], 2) ?></td>
                        <td>
                            <a href="?edit=<?= $s['setting_id'] ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="text-muted">No rates/fees added yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
