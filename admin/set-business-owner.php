<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include '../connection/db.php';

// Handle business owner update/add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_owner'])) {
    $owner_name = trim($_POST['owner_name']);
    $address = trim($_POST['address']);
    $contact_no = trim($_POST['contact_no']);
    $email = trim($_POST['email']);

    // Check if owner already exists (single record scenario)
    $checkQuery = "SELECT owner_id FROM business_owner LIMIT 1";
    $result = $conn->query($checkQuery);

    if ($result && $row = $result->fetch_assoc()) {
        // Update existing owner
        $updateQuery = "UPDATE business_owner SET owner_name = ?, address = ?, contact_no = ?, email = ? WHERE owner_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssi", $owner_name, $address, $contact_no, $email, $row['owner_id']);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Business owner information updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating owner: " . $conn->error;
        }
        $stmt->close();
    } else {
        // Insert new owner
        $insertQuery = "INSERT INTO business_owner (owner_name, address, contact_no, email) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ssss", $owner_name, $address, $contact_no, $email);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Business owner information added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding owner: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: set-business-owner.php");
    exit;
}

// Fetch owner info for display
$owner = [];
$result = $conn->query("SELECT * FROM business_owner LIMIT 1");
if ($result && $result->num_rows > 0) {
    $owner = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Business Owner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="CSS/sidebar.css">
    <style>
        h2 { color: #095544; }
        .card { border-radius: 10px; background-color: #e5edf0; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .d-flex { padding: 20px; }
        .alert-container { min-height: 60px; }
    </style>
</head>
<body>
<div class="d-flex">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="bi bi-person-badge"></i> <strong>Business Owner Information</strong></h2>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Update Business Owner</h5>
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
                <?php if (!empty($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>

            <form method="POST" action="set-business-owner.php" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Owner Name</label>
                    <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($owner['owner_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($owner['address'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact No.</label>
                    <input type="text" name="contact_no" class="form-control" value="<?= htmlspecialchars($owner['contact_no'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($owner['email'] ?? '') ?>">
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" name="save_owner" class="btn btn-success btn-sm">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-person"></i> Current Owner Info</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($owner)): ?>
            <table class="table table-bordered">
                <tr>
                    <th>Name</th>
                    <td><?= htmlspecialchars($owner['owner_name']) ?></td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td><?= htmlspecialchars($owner['address']) ?></td>
                </tr>
                <tr>
                    <th>Contact No.</th>
                    <td><?= htmlspecialchars($owner['contact_no']) ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($owner['email']) ?></td>
                </tr>
            </table>
            <?php else: ?>
                <div class="text-muted">No business owner information set yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
