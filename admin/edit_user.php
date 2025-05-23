<?php
session_start();
require_once '../config/config.php'; // Database connection

// Placeholder for admin authentication
if (!isset($_SESSION['username'])) {
    echo "<p style='color:red;'><strong>Note:</strong> Admin authentication is not yet implemented. This page should be protected.</p>";
}

$user_id = $_GET['id'] ?? null;
$message = '';
$user_data = ['id' => null, 'username' => '', 'email' => '']; // Initialize with empty values, including id

if (!$user_id) {
    header("Location: manage_users.php?message=" . urlencode("No user ID specified for editing."));
    exit;
}

// Fetch user data for editing
try {
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $fetched_user_data = $stmt->fetch(PDO::FETCH_ASSOC); // Use a different variable name
    if (!$fetched_user_data) {
        header("Location: manage_users.php?message=" . urlencode("User not found."));
        exit;
    }
    $user_data = $fetched_user_data; // Assign to user_data if found
} catch (PDOException $e) {
    $message = "Error fetching user data: " . $e->getMessage();
    // Display error and prevent form display if severe, or allow form display with error
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_edit_user'])) {
    $new_username = $_POST['username'] ?? '';
    $new_email = $_POST['email'] ?? '';
    $posted_user_id = $_POST['user_id'] ?? null;

    // Basic validation
    if (empty($new_username) || empty($new_email)) {
        $message = "Username and Email are required.";
        // Preserve submitted values in $user_data for form repopulation
        $user_data['username'] = $new_username;
        $user_data['email'] = $new_email;
    } elseif ($posted_user_id != $user_id) {
        $message = "User ID mismatch. Operation aborted.";
    } else {
        try {
            // Check if new username or email already exists for another user
            $check_sql = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':username', $new_username);
            $check_stmt->bindParam(':email', $new_email);
            $check_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                $message = "Error: Username or Email already taken by another user.";
                // Preserve submitted values for form repopulation
                $user_data['username'] = $new_username;
                $user_data['email'] = $new_email;
            } else {
                $sql = "UPDATE users SET username = :username, email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                $stmt_update = $conn->prepare($sql);
                $stmt_update->bindParam(':username', $new_username);
                $stmt_update->bindParam(':email', $new_email);
                $stmt_update->bindParam(':id', $user_id, PDO::PARAM_INT);
                
                if ($stmt_update->execute()) {
                    $message = "User details updated successfully!";
                    // Re-fetch data to display updated values in form
                    $stmt_refetch = $conn->prepare("SELECT id, username, email FROM users WHERE id = :id");
                    $stmt_refetch->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $stmt_refetch->execute();
                    $user_data = $stmt_refetch->fetch(PDO::FETCH_ASSOC); // Update $user_data with fresh data
                } else {
                    $message = "Error updating user details.";
                     // Preserve submitted values for form repopulation
                    $user_data['username'] = $new_username;
                    $user_data['email'] = $new_email;
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            // Preserve submitted values for form repopulation
            $user_data['username'] = $new_username;
            $user_data['email'] = $new_email;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f7f6; color: #333; }
        .form-container { max-width: 600px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #495057; }
        .form-group input[type="text"], .form-group input[type="email"] { 
            width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ced4da; border-radius: 4px; 
        }
        .message { padding: 12px 15px; margin-bottom:20px; border-radius:4px; font-size:0.95em; text-align:center; }
        .success { background-color: #d4edda; color: #155724; border:1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border:1px solid #f5c6cb; }
        button[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; display: block; width: 100%; }
        button[type="submit"]:hover { background-color: #0056b3; }
        .nav-link-back { display:inline-block; margin-bottom:20px; padding:8px 15px; background-color:#6c757d; color:white; text-decoration:none; border-radius:4px;}
        .nav-link-back:hover { background-color:#5a6268; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Edit User: <?php echo htmlspecialchars($user_data['username'] ?? 'N/A'); ?></h1>
        <p style="text-align:center;"><a href="manage_users.php" class="nav-link-back">Back to Users List</a></p>

        <?php if ($message): ?>
            <div class="message <?php echo (stripos(strtolower($message), 'error') === false && stripos(strtolower($message), 'taken') === false && stripos(strtolower($message), 'mismatch') === false) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php 
        // Condition to show form: user data must be loaded and ( (no message) OR (message is success) OR (message indicates a validation error like 'taken' or 'required') )
        // This avoids showing form if there was a critical error fetching user data initially.
        $show_form = false;
        if ($user_data && $user_data['id']) { // Ensure user_data is for a valid fetched user
            if (empty($message) || 
                stripos(strtolower($message), 'successfully') !== false || 
                stripos(strtolower($message), 'taken') !== false ||
                stripos(strtolower($message), 'required') !== false) {
                $show_form = true;
            }
        }
        ?>

        <?php if ($show_form): ?>
            <form action="edit_user.php?id=<?php echo htmlspecialchars($user_id); ?>" method="POST">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>
                <p><em>Password management is handled separately (e.g., user-initiated password reset). This form does not change passwords.</em></p>
                <button type="submit" name="submit_edit_user">Update User</button>
            </form>
        <?php elseif (!$user_data['id'] && empty($message)): // Only show 'Could not load' if no other message is set and user_id in user_data is null (meaning fetch failed) ?>
            <p class="error">Could not load user data for editing.</p>
        <?php endif; ?>
    </div>
</body>
</html>
