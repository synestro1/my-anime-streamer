<?php
session_start();
require_once '../config/config.php'; // Database connection

// Placeholder for admin authentication
if (!isset($_SESSION['username'])) {
    echo "<p style='color:red;'><strong>Note:</strong> Admin authentication is not yet implemented. This page should be protected.</p>";
}

$message = $_GET['message'] ?? '';
$action_message = ''; // For messages from actions on this page
$current_filter_status = $_GET['current_filter'] ?? $_GET['filter_status'] ?? 'pending'; // Preserve filter on redirect

// Handle Actions (Approve, Reject, Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action_to_perform = $_GET['action'];
    $comment_id = $_GET['id'];
    $redirect_needed = true;

    try {
        if ($action_to_perform === 'approve') {
            $stmt = $conn->prepare("UPDATE comments SET status = 'approved' WHERE id = :id");
            $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
            $stmt->execute();
            $action_message = "Comment (ID: $comment_id) approved successfully.";
        } elseif ($action_to_perform === 'reject') {
            $stmt = $conn->prepare("UPDATE comments SET status = 'rejected' WHERE id = :id");
            $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
            $stmt->execute();
            $action_message = "Comment (ID: $comment_id) rejected successfully.";
        } elseif ($action_to_perform === 'delete') {
            $stmt = $conn->prepare("DELETE FROM comments WHERE id = :id");
            $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $action_message = "Comment (ID: $comment_id) deleted successfully.";
            } else {
                $action_message = "Comment (ID: $comment_id) not found or already deleted.";
            }
        } else {
            $action_message = "Invalid action specified.";
            $redirect_needed = false; // No need to redirect if action is invalid
        }

        if ($stmt_error_info = $stmt->errorInfo() && $stmt_error_info[0] !== '00000' && $action_to_perform !== 'delete' && $stmt->rowCount() === 0) {
             // If an update action affected 0 rows but didn't error, it means the comment wasn't found or status was already set
            $action_message = "Comment (ID: $comment_id) not found, or status was already as requested.";
        }


    } catch (PDOException $e) {
        $action_message = "Database error performing action '$action_to_perform' on comment (ID: $comment_id): " . $e->getMessage();
    }

    if ($redirect_needed) {
        header("Location: manage_comments.php?filter_status={$current_filter_status}&message=" . urlencode($action_message));
        exit;
    } else if (!empty($action_message)) { // If no redirect, but message exists (e.g. invalid action)
        $message = $action_message; // Display it on current page load
    }
}


$filter_status = $_GET['filter_status'] ?? 'pending'; // Default to show pending comments for list query

// Fetch comments with user and anime/episode information
$comments_list = [];
try {
    $sql = "SELECT c.id, c.comment_text, c.status, c.created_at, 
                   u.username AS commenter_username, 
                   a.title AS anime_title, 
                   e.episode_number AS episode_num
            FROM comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN anime a ON c.anime_id = a.id
            LEFT JOIN episodes e ON c.episode_id = e.id
            WHERE c.status = :status
            ORDER BY c.created_at DESC";
    $stmt_comments = $conn->prepare($sql);
    $stmt_comments->bindParam(':status', $filter_status);
    $stmt_comments->execute();
    $comments_list = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Prioritize message from GET if available (after redirect), otherwise set/append error
    $fetch_error_message = " Error fetching comments: " . $e->getMessage();
    if (empty($message)) { // If no message from GET or action
        $message = $fetch_error_message;
    } else { // If message from GET exists, append cautiously or log
        error_log("Additional error while fetching comments (GET message already present): " . $fetch_error_message);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Comments</title>
    <link rel="stylesheet" href="../css/style.css"> <!-- Adjust path -->
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .action-links a.approve { color: green; }
        .action-links a.reject { color: orange; }
        .action-links a.delete { color: red; }
        .message { padding: 10px; margin-bottom:15px; border-radius:3px; }
        .success { background-color: #d4edda; color: #155724; border:1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border:1px solid #f5c6cb; }
        .filter-form { margin-bottom: 20px; }
        .filter-form label, .filter-form select, .filter-form button { padding: 5px; }
    </style>
</head>
<body>
    <h1>Manage Comments</h1>
    <p><a href="dashboard.php">Back to Dashboard</a></p>

    <?php if ($message): ?>
        <div class="message <?php echo (stripos(strtolower($message), 'error') === false && stripos(strtolower($message), 'not found') === false && stripos(strtolower($message), 'invalid action') === false) ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars(urldecode($message)); ?>
        </div>
    <?php endif; ?>

    <div class="filter-form">
        <form action="manage_comments.php" method="GET">
            <label for="filter_status">Filter by status:</label>
            <select name="filter_status" id="filter_status">
                <option value="pending" <?php echo ($filter_status === 'pending' ? 'selected' : ''); ?>>Pending</option>
                <option value="approved" <?php echo ($filter_status === 'approved' ? 'selected' : ''); ?>>Approved</option>
                <option value="rejected" <?php echo ($filter_status === 'rejected' ? 'selected' : ''); ?>>Rejected</option>
            </select>
            <button type="submit">Filter</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Commenter</th>
                <th>Comment</th>
                <th>Target</th> <!-- Anime or Episode -->
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($comments_list)): ?>
                <?php foreach ($comments_list as $comment): ?>
                    <tr>
                        <td><?php echo $comment['id']; ?></td>
                        <td><?php echo htmlspecialchars($comment['commenter_username']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars(substr($comment['comment_text'], 0, 100))); ?>...</td>
                        <td>
                            <?php 
                            if ($comment['anime_title']) echo 'Anime: ' . htmlspecialchars($comment['anime_title']);
                            if ($comment['episode_num']) echo ' (Ep: ' . htmlspecialchars($comment['episode_num']) . ')';
                            ?>
                        </td>
                        <td><?php echo $comment['created_at']; ?></td>
                        <td><?php echo htmlspecialchars($comment['status']); ?></td>
                        <td class="action-links">
                            <?php if ($comment['status'] === 'pending' || $comment['status'] === 'rejected'): ?>
                                <a href="manage_comments.php?action=approve&id=<?php echo $comment['id']; ?>&current_filter=<?php echo $filter_status; ?>" class="approve">Approve</a>
                            <?php endif; ?>
                            <?php if ($comment['status'] === 'pending' || $comment['status'] === 'approved'): ?>
                                <a href="manage_comments.php?action=reject&id=<?php echo $comment['id']; ?>&current_filter=<?php echo $filter_status; ?>" class="reject">Reject</a>
                            <?php endif; ?>
                            <a href="manage_comments.php?action=delete&id=<?php echo $comment['id']; ?>&current_filter=<?php echo $filter_status; ?>" class="delete" onclick="return confirm('Are you sure you want to delete this comment permanently?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No comments found with status '<?php echo htmlspecialchars($filter_status); ?>'.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
