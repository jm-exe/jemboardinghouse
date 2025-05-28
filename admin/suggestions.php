<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Prevent browser caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    exit('Unauthorized access');
}

// Database connection
include '../connection/db.php';

// Get current academic year
$currentAYStmt = $conn->prepare("SELECT academic_year_id FROM academic_years WHERE is_current = 1 LIMIT 1");
$currentAYStmt->execute();
$currentAYResult = $currentAYStmt->get_result();
$currentAY = $currentAYResult->fetch_assoc();
$currentAYID = $currentAY ? $currentAY['academic_year_id'] : null;

// Handle suggestion status update
if (isset($_POST['action'], $_POST['suggestion_id'])) {
    $suggestionID = (int)$_POST['suggestion_id'];
    $action = in_array($_POST['action'], ['Unread', 'Pending', 'Noted']) ? $_POST['action'] : 'Pending';

    $stmt = $conn->prepare("UPDATE suggestions SET status = ? WHERE suggestion_id = ?");
    $stmt->bind_param("si", $action, $suggestionID);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Suggestion marked as $action.";
    } else {
        $_SESSION['error_message'] = "Error updating suggestion: " . $conn->error;
    }
    $stmt->close();

    // Redirect to prevent form resubmission
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $redirect_url .= '?status=' . urlencode($_GET['status']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle suggestion deletion
if (isset($_POST['delete_suggestion'], $_POST['suggestion_id'])) {
    $suggestionID = (int)$_POST['suggestion_id'];
    
    // Verify the suggestion is marked as Noted before deletion
    $checkStmt = $conn->prepare("SELECT status FROM suggestions WHERE suggestion_id = ?");
    $checkStmt->bind_param("i", $suggestionID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        
        // Check if status is 'Noted'
        if ($row['status'] === 'Noted') {
            $deleteStmt = $conn->prepare("DELETE FROM suggestions WHERE suggestion_id = ?");
            $deleteStmt->bind_param("i", $suggestionID);
            
            if ($deleteStmt->execute()) {
                $_SESSION['success_message'] = "Suggestion deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Error deleting suggestion: " . $conn->error;
            }
            $deleteStmt->close();
        } else {
            $_SESSION['error_message'] = "Only suggestions marked as 'Noted' can be deleted. Current status: " . htmlspecialchars($row['status']);
        }
    } else {
        $_SESSION['error_message'] = "Suggestion not found.";
    }
    $checkStmt->close();
    
    // Redirect to prevent form resubmission
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $redirect_url .= '?status=' . urlencode($_GET['status']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Get selected status filter (from GET parameter)
$selectedStatus = isset($_GET['status']) && in_array($_GET['status'], ['Pending', 'Unread', 'Noted']) ? $_GET['status'] : 'All';

// Fetch suggestions
$query = "
    SELECT s.suggestion_id, s.suggestion, s.date_submitted, s.status,
           t.first_name, t.last_name
    FROM suggestions s
    LEFT JOIN tenants t ON s.tenant_id = t.tenant_id
    WHERE t.academic_year_id = ?
";
$params = [$currentAYID];
$types = "i";

if ($selectedStatus !== 'All') {
    $query .= " AND s.status = ?";
    $params[] = $selectedStatus;
    $types .= "s";
}

$query .= " ORDER BY s.date_submitted DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$suggestions = $stmt->get_result();
?>

<div class="suggestions-container">
    <div class="suggestions-header mb-3">
        <h3><i class="fa fa-lightbulb-o"></i> Tenant Suggestions</h3>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-sm" id="success-message">
                <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-sm" id="error-message">
                <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        <!-- Status Filter Dropdown -->
        <div class="status-filter">
            <label for="status-filter" class="me-2">Filter by Status:</label>
            <select id="status-filter" onchange="filterSuggestions(this.value)" class="form-select form-select-sm d-inline-block" style="width: 150px;">
                <option value="All" <?= $selectedStatus === 'All' ? 'selected' : '' ?>>All</option>
                <option value="Pending" <?= $selectedStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Unread" <?= $selectedStatus === 'Unread' ? 'selected' : '' ?>>Unread</option>
                <option value="Noted" <?= $selectedStatus === 'Noted' ? 'selected' : '' ?>>Noted</option>
            </select>
        </div>
    </div>

    <div class="suggestions-list-container">
        <div class="suggestions-list">
            <?php if ($suggestions->num_rows > 0): ?>
                <?php while ($row = $suggestions->fetch_assoc()): ?>
                    <div class="suggestion-card">
                        <div class="card-header">
                            <div class="tenant-info">
                                <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars(date("M j, Y", strtotime($row['date_submitted']))) ?></small>
                            </div>
                            <span class="status-badge <?= $row['status'] === 'Noted' ? 'status-noted' : ($row['status'] === 'Pending' ? 'status-pending' : 'status-unread') ?>">
                                <?= htmlspecialchars($row['status'] ?? 'Unread') ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="suggestion-text"><?= htmlspecialchars($row['suggestion']) ?></p>
                        </div>
                        <div class="card-actions">
                            <form method="POST" class="action-form">
                                <input type="hidden" name="suggestion_id" value="<?= $row['suggestion_id'] ?>">
                                <div class="btn-group">
                                    <button type="submit" name="action" value="Unread" class="btn btn-action btn-unread" title="Mark as Unread">
                                        <i class="fa fa-envelope"></i>
                                    </button>
                                    <button type="submit" name="action" value="Pending" class="btn btn-action btn-pending" title="Set as Pending">
                                        <i class="fa fa-clock-o"></i>
                                    </button>
                                    <button type="submit" name="action" value="Noted" class="btn btn-action btn-noted" title="Mark as Noted">
                                        <i class="fa fa-check"></i>
                                    </button>
                                    <?php if ($row['status'] === 'Noted'): ?>
                                        <button type="submit" name="delete_suggestion" value="Delete" class="btn btn-action btn-delete" title="Delete Suggestion" onclick="return confirm('Are you sure you want to delete this suggestion?');">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-suggestions">
                    <i class="fa fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No suggestions available for the selected status in the current academic year.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-dismiss messages after 5 seconds
setTimeout(function() {
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    if (successMessage) {
        successMessage.style.transition = 'opacity 0.5s ease';
        successMessage.style.opacity = '0';
        setTimeout(() => successMessage.remove(), 500);
    }
    if (errorMessage) {
        errorMessage.style.transition = 'opacity 0.5s ease';
        errorMessage.style.opacity = '0';
        setTimeout(() => errorMessage.remove(), 500);
    }
}, 5000);

function filterSuggestions(status) {
    window.location.href = 'dashboard.php?status=' + encodeURIComponent(status);
}
</script>

<style>
    .suggestions-container {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .suggestions-header {
        flex-shrink: 0;
        padding: 0 10px;
    }

    .suggestions-list-container {
        flex: 1;
        overflow: hidden;
        position: relative;
    }

    .suggestions-list {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        padding: 0 10px 10px 10px;
    }

    .suggestions-list::-webkit-scrollbar {
        width: 6px;
    }

    .suggestions-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .suggestions-list::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .suggestions-list::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    .suggestion-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: box-shadow 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }

    .suggestion-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
        background: #fff;
        border-radius: 8px 8px 0 0;
    }

    .tenant-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .tenant-info strong {
        color: #333;
        font-size: 14px;
    }

    .tenant-info small {
        font-size: 12px;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-noted {
        background: #d4edda;
        color: #155724;
    }

    .status-pending {
        background: #f8f9fa;
        color: #6c757d;
        border: 1px solid #dee2e6;
    }

    .status-unread {
        background: #fff3cd;
        color: #856404;
    }

    .card-body {
        padding: 15px;
    }

    .suggestion-text {
        margin: 0;
        color: #495057;
        font-size: 14px;
        line-height: 1.4;
    }

    .card-actions {
        padding: 10px 15px;
        background: #fff;
        border-radius: 0 0 8px 8px;
    }

    .action-form {
        margin: 0;
    }

    .btn-group {
        display: flex;
        gap: 6px;
    }

    .btn-action {
        padding: 6px 10px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-unread {
        background: #ffc107;
        color: #212529;
    }

    .btn-unread:hover {
        background: #e0a800;
        transform: translateY(-1px);
    }

    .btn-pending {
        background: #6c757d;
        color: #fff;
    }

    .btn-pending:hover {
        background: #5a6268;
        transform: translateY(-1px);
    }

    .btn-noted {
        background: #28a745;
        color: #fff;
    }

    .btn-noted:hover {
        background: #218838;
        transform: translateY(-1px);
    }

    .btn-delete {
        background: #dc3545;
        color: #fff;
    }

    .btn-delete:hover {
        background: #c82333;
        transform: translateY(-1px);
    }

    .no-suggestions {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .no-suggestions p {
        margin: 0;
        font-size: 14px;
    }

    .status-filter {
        margin-top: 10px;
        display: flex;
        align-items: center;
    }

    .alert-sm {
        padding: 8px 12px;
        font-size: 14px;
        margin-bottom: 10px;
    }
</style>