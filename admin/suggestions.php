<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

// Database connection
include '../connection/db.php';

// Update status of a suggestion
if (isset($_POST['action'], $_POST['suggestion_id'])) {
    $suggestionID = (int)$_POST['suggestion_id'];
    $action = in_array($_POST['action'], ['Unread', 'Pending', 'Noted']) ? $_POST['action'] : 'Pending';

    $stmt = $conn->prepare("UPDATE suggestions SET status = ? WHERE suggestion_id = ?");
    $stmt->bind_param("si", $action, $suggestionID);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success_message'] = "Suggestion marked as $action.";
    header("Location: dashboard.php");
    exit();
}

// Fetch suggestions
$query = "
    SELECT s.suggestion_id, s.suggestion, s.date_submitted, s.status,
           t.first_name, t.last_name
    FROM suggestions s
    LEFT JOIN tenants t ON s.tenant_id = t.tenant_id
    ORDER BY s.date_submitted DESC
";
$suggestions = $conn->query($query);
?>

<div class="suggestions-container">
    <div class="suggestions-header mb-3">
        <h3><i class="fa fa-lightbulb-o"></i> Tenant Suggestions</h3>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-sm">
                <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
    </div>

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
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-suggestions">
                <i class="fa fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No suggestions available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .suggestions-container {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .suggestions-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: bold;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .suggestions-header h3 i {
        color: #2f8656;
    }

    .alert-sm {
        padding: 8px 12px;
        font-size: 13px;
        margin-bottom: 10px;
    }

    .suggestions-list {
        flex: 1;
        overflow-y: auto;
        padding-right: 8px;
    }

    .suggestion-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: box-shadow 0.2s ease;
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

    .no-suggestions {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .no-suggestions p {
        margin: 0;
        font-size: 14px;
    }

    /* Scrollbar styling */
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
</style>