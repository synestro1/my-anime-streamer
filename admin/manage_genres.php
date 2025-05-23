<?php
session_start();
require_once '../config/config.php'; // Adjusted path for config

// Placeholder for admin authentication (as in dashboard.php)
if (!isset($_SESSION['username'])) {
    echo "<p style='color:red;'><strong>Note:</strong> Admin authentication is not yet implemented. This page should be protected.</p>";
}

$action = $_GET['action'] ?? 'list'; // Default action
$genre_id = $_GET['id'] ?? null;
$genre_name = $_POST['genre_name'] ?? '';
$message = ''; // For success/error messages

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_genre']) && !empty($genre_name)) {
        try {
            $stmt = $conn->prepare("INSERT INTO genres (name) VALUES (:name)");
            $stmt->bindParam(':name', $genre_name);
            $stmt->execute();
            $message = "Genre added successfully!";
        } catch (PDOException $e) {
            $message = "Error adding genre: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_genre']) && !empty($genre_name) && !empty($_POST['genre_id'])) {
        try {
            $stmt = $conn->prepare("UPDATE genres SET name = :name WHERE id = :id");
            $stmt->bindParam(':name', $genre_name);
            $stmt->bindParam(':id', $_POST['genre_id'], PDO::PARAM_INT);
            $stmt->execute();
            $message = "Genre updated successfully!";
        } catch (PDOException $e) {
            $message = "Error updating genre: " . $e->getMessage();
        }
    }
}

// Handle GET requests for delete
if ($action === 'delete' && $genre_id) {
    try {
        // First, delete associations in anime_genres
        $stmt_assoc = $conn->prepare("DELETE FROM anime_genres WHERE genre_id = :genre_id");
        $stmt_assoc->bindParam(':genre_id', $genre_id, PDO::PARAM_INT);
        $stmt_assoc->execute();

        // Then, delete the genre itself
        $stmt = $conn->prepare("DELETE FROM genres WHERE id = :id");
        $stmt->bindParam(':id', $genre_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $message = "Genre (ID: $genre_id) and its associations deleted successfully!";
        } else {
            $message = "Genre (ID: $genre_id) not found or already deleted. Associations cleared if any existed.";
        }
        // Redirect to prevent re-deletion on refresh, and to show message
        header("Location: manage_genres.php?message=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        $message = "Error deleting genre (ID: $genre_id): " . $e->getMessage();
        // To show this error message after potential header redirect issue, consider session flash messages or passing it via GET if exit isn't reached.
        // For now, if header fails or if we remove exit for debugging, $message will be shown.
    }
}

// Fetch genre for editing if action is 'edit' and id is provided
$genre_to_edit = null;
if ($action === 'edit' && $genre_id) {
    $stmt = $conn->prepare("SELECT id, name FROM genres WHERE id = :id");
    $stmt->bindParam(':id', $genre_id, PDO::PARAM_INT);
    $stmt->execute();
    $genre_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$genre_to_edit) {
        $message = "Error: Genre with ID $genre_id not found for editing.";
        $action = 'list'; // Revert action if genre not found
    }
}

// Fetch all genres for listing
$genres = [];
try {
    $stmt_genres = $conn->query("SELECT id, name, created_at FROM genres ORDER BY name ASC");
    if ($stmt_genres) $genres = $stmt_genres->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Append to existing message if one is already set (e.g., from a failed delete attempt that didn't exit)
    $message .= ($message ? " " : "") . "Error fetching genres: " . $e->getMessage();
}

if(isset($_GET['message']) && empty($message)) $message = htmlspecialchars(urldecode($_GET['message']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Genres</title>
    <link rel="stylesheet" href="../css/style.css"> <!-- Adjust path -->
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f7f6; color: #333; }
        h1, h2 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background-color: #e9ecef; color: #495057; }
        .action-links a { margin-right: 10px; color: #007bff; text-decoration:none; }
        .action-links a:hover { text-decoration:underline; }
        .action-links a.delete-link { color: #dc3545; }
        .form-container { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-bottom: 8px; font-weight: bold; color: #495057;}
        .form-container input[type="text"] { width: calc(100% - 22px); padding: 10px; margin-bottom:15px; border: 1px solid #ced4da; border-radius: 4px; }
        .form-container button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .form-container button:hover { background-color: #0056b3; }
        .form-container a { margin-left: 10px; color: #6c757d; text-decoration:none; }
        .form-container a:hover { text-decoration:underline; }
        .message { padding: 12px 15px; margin-bottom:20px; border-radius:4px; font-size:0.95em; }
        .success { background-color: #d4edda; color: #155724; border:1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border:1px solid #f5c6cb; }
        .nav-link { display:inline-block; margin-bottom:20px; padding:8px 15px; background-color:#6c757d; color:white; text-decoration:none; border-radius:4px;}
        .nav-link:hover { background-color:#5a6268; }
    </style>
</head>
<body>
    <h1>Manage Genres</h1>
    <p><a href="dashboard.php" class="nav-link">Back to Dashboard</a></p>

    <?php if ($message): ?>
        <div class="message <?php echo (stripos($message, 'error') === false && stripos($message, 'not found') === false) ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h2><?php echo ($action === 'edit' && $genre_to_edit) ? 'Edit Genre (ID: ' . htmlspecialchars($genre_to_edit['id']) . ')' : 'Add New Genre'; ?></h2>
        <form action="manage_genres.php<?php echo ($action === 'edit' && $genre_to_edit) ? '?action=list' : ''; // Always POST to the base URL, then redirect or list ?>" method="POST">
            <?php if ($action === 'edit' && $genre_to_edit): ?>
                <input type="hidden" name="genre_id" value="<?php echo $genre_to_edit['id']; ?>">
            <?php endif; ?>
            <div>
                <label for="genre_name">Genre Name:</label>
                <input type="text" id="genre_name" name="genre_name" value="<?php echo htmlspecialchars($genre_to_edit['name'] ?? ''); ?>" required>
            </div>
            <br>
            <?php if ($action === 'edit' && $genre_to_edit): ?>
                <button type="submit" name="edit_genre">Update Genre</button>
                <a href="manage_genres.php">Cancel Edit</a>
            <?php else: ?>
                <button type="submit" name="add_genre">Add Genre</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- List Genres -->
    <h2>Existing Genres</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($genres)): ?>
                <?php foreach ($genres as $genre): ?>
                    <tr>
                        <td><?php echo $genre['id']; ?></td>
                        <td><?php echo htmlspecialchars($genre['name']); ?></td>
                        <td><?php echo $genre['created_at']; ?></td>
                        <td class="action-links">
                            <a href="manage_genres.php?action=edit&id=<?php echo $genre['id']; ?>">Edit</a>
                            <a href="manage_genres.php?action=delete&id=<?php echo $genre['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this genre? This will also remove its associations with any anime.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No genres found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
