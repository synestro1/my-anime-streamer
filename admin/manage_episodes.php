<?php
session_start();
require_once '../config/config.php'; // Database connection

// Placeholder for admin authentication
if (!isset($_SESSION['username'])) {
    echo "<p style='color:red;'><strong>Note:</strong> Admin authentication is not yet implemented. This page should be protected.</p>";
}

$anime_id = $_GET['anime_id'] ?? null;
$action = $_GET['action'] ?? 'list'; // 'list', 'edit_episode', 'delete_episode'
$episode_id = $_GET['episode_id'] ?? null;

$message = $_GET['message'] ?? '';
$current_episode_data = ['id' => null, 'episode_number' => '', 'title' => '', 'video_url_local' => '', 'duration_minutes' => '', 'air_date' => ''];

if (!$anime_id) {
    header("Location: manage_anime.php?message=" . urlencode("No anime selected."));
    exit;
}

// Fetch Anime Title for display
$anime_title = '';
try {
    $stmt_anime_title = $conn->prepare("SELECT title FROM anime WHERE id = :anime_id");
    $stmt_anime_title->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_anime_title->execute();
    $anime_title_result = $stmt_anime_title->fetch(PDO::FETCH_ASSOC);
    if ($anime_title_result) {
        $anime_title = $anime_title_result['title'];
    } else {
        header("Location: manage_anime.php?message=" . urlencode("Selected anime not found."));
        exit;
    }
} catch (PDOException $e) {
    $message = "Error fetching anime title: " . $e->getMessage();
}


// Handle POST requests for add/edit episode
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_anime_id = $_POST['anime_id'] ?? null;
    $posted_episode_id = $_POST['episode_id'] ?? null; // For edits
    $episode_number = $_POST['episode_number'] ?? '';
    $title = $_POST['title'] ?? '';
    // Initialize $video_url_local with the current/posted text value
    $video_url_local = $_POST['video_url_local'] ?? ''; 
    $duration_minutes = !empty($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
    $air_date = !empty($_POST['air_date']) ? $_POST['air_date'] : null;
    $upload_error = false; // Flag for upload issues

    // --- Video Upload Handling ---
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../videos/'; // Relative to admin directory
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); // Create if not exists
        }

        $original_filename = basename($_FILES['video_file']['name']);
        // Generate a unique filename to prevent overwriting
        $new_filename = uniqid() . '-' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $original_filename);
        $target_file = $upload_dir . $new_filename;
        $video_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Basic validation for video file type
        $allowed_types = ['mp4', 'avi', 'mov', 'mkv', 'webm']; // Add more as needed
        if (!in_array($video_file_type, $allowed_types)) {
            $message = "Error: Only " . implode(', ', $allowed_types) . " file types are allowed. You uploaded a '{$video_file_type}' file.";
            $upload_error = true;
        } else {
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $target_file)) {
                // File uploaded successfully, set path for DB (relative to project root)
                $video_url_local = 'videos/' . $new_filename; 
                $message = "Video file uploaded successfully. Path set to: $video_url_local";
            } else {
                $message = "Error: Failed to move uploaded video file.";
                $upload_error = true;
            }
        }
    } elseif (isset($_FILES['video_file']) && $_FILES['video_file']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors (e.g., file too large, partial upload)
        $message = "Error uploading video file. Code: " . $_FILES['video_file']['error'];
        $upload_error = true;
    }
    // --- End Video Upload Handling ---


    if (!$upload_error && $posted_anime_id == $anime_id && !empty($episode_number)) { // Ensure anime_id matches and no upload error
        if (isset($_POST['add_episode'])) {
            try {
                $sql = "INSERT INTO episodes (anime_id, episode_number, title, video_url_local, duration_minutes, air_date) 
                        VALUES (:anime_id, :episode_number, :title, :video_url_local, :duration_minutes, :air_date)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':anime_id' => $anime_id, 
                    ':episode_number' => $episode_number, 
                    ':title' => $title, 
                    ':video_url_local' => $video_url_local, // This will be the new path if uploaded, or the text input value
                    ':duration_minutes' => $duration_minutes,
                    ':air_date' => $air_date
                ]);
                $message = (empty($message) ? "" : $message . " ") . "Episode added successfully!"; // Append to upload message if any
            } catch (PDOException $e) {
                $message = "Error adding episode: " . $e->getMessage();
            }
        } elseif (isset($_POST['edit_episode']) && $posted_episode_id) {
             try {
                $sql = "UPDATE episodes SET episode_number = :episode_number, title = :title, video_url_local = :video_url_local, duration_minutes = :duration_minutes, air_date = :air_date 
                        WHERE id = :episode_id AND anime_id = :anime_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':episode_number' => $episode_number, 
                    ':title' => $title, 
                    ':video_url_local' => $video_url_local, // This will be the new path if uploaded, or the text input value
                    ':duration_minutes' => $duration_minutes,
                    ':air_date' => $air_date,
                    ':episode_id' => $posted_episode_id,
                    ':anime_id' => $anime_id
                ]);
                $message = (empty($message) ? "" : $message . " ") . "Episode updated successfully!"; // Append to upload message if any
            } catch (PDOException $e) {
                $message = "Error updating episode: " . $e->getMessage();
            }
        }
    } elseif (!$upload_error) { // if no upload error, but other validation failed
        $message = (empty($message) ? "" : $message . " ") . "Anime ID mismatch or episode number missing.";
    }
    // Redirect to clear POST and show message
    header("Location: manage_episodes.php?anime_id={$anime_id}&message=" . urlencode($message));
    exit;
}

// Handle GET requests for delete_episode (existing logic)
if ($action === 'delete_episode' && $episode_id) {
    try {
        // Fetch video_url_local to delete the file
        $stmt_fetch_video = $conn->prepare("SELECT video_url_local FROM episodes WHERE id = :episode_id AND anime_id = :anime_id");
        $stmt_fetch_video->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
        $stmt_fetch_video->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
        $stmt_fetch_video->execute();
        $video_to_delete = $stmt_fetch_video->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM episodes WHERE id = :episode_id AND anime_id = :anime_id");
        $stmt->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
        $stmt->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Episode deleted successfully!";
            // Delete the associated video file if it exists and path is known
            if ($video_to_delete && !empty($video_to_delete['video_url_local'])) {
                $file_path_to_delete = '../' . $video_to_delete['video_url_local']; // Path from admin folder
                if (file_exists($file_path_to_delete)) {
                    unlink($file_path_to_delete);
                    $message .= " Associated video file deleted.";
                } else {
                     $message .= " Associated video file not found at '{$file_path_to_delete}'.";
                }
            }
        } else {
            $message = "Episode not found or already deleted.";
        }
    } catch (PDOException $e) {
        $message = "Error deleting episode: " . $e->getMessage();
    }
    header("Location: manage_episodes.php?anime_id={$anime_id}&message=" . urlencode($message));
    exit;
}

// Fetch episode for editing if action is 'edit_episode' (existing logic)
if ($action === 'edit_episode' && $episode_id) {
    $stmt_edit = $conn->prepare("SELECT id, episode_number, title, video_url_local, duration_minutes, air_date FROM episodes WHERE id = :episode_id AND anime_id = :anime_id");
    $stmt_edit->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
    $stmt_edit->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_edit->execute();
    $fetched_episode_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
    if ($fetched_episode_data) {
        $current_episode_data = $fetched_episode_data; 
    } else {
        $message = "Episode not found for editing.";
        $action = 'list'; 
    }
}

// Fetch all episodes for this anime (existing logic)
$episodes_list = [];
try {
    $stmt_episodes = $conn->prepare("SELECT id, episode_number, title, video_url_local, duration_minutes, air_date, views FROM episodes WHERE anime_id = :anime_id ORDER BY episode_number ASC");
    $stmt_episodes->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_episodes->execute();
    $episodes_list = $stmt_episodes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message .= (empty($message) ? '' : ' ') . "Error fetching episodes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Episodes for <?php echo htmlspecialchars($anime_title); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f7f6; color: #333; }
        h1, h2 { color: #2c3e50; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background-color: #e9ecef; color: #495057; }
        .action-links a { margin-right: 10px; color: #007bff; text-decoration:none; }
        .action-links a:hover { text-decoration:underline; }
        .action-links a.delete-link { color: #dc3545; }
        .form-container { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; background-color: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 12px;}
        .form-group label { display:block; margin-bottom:5px; font-weight:bold; color: #495057;}
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="date"], .form-group input[type="file"] { 
            width: calc(100% - 22px); padding: 10px; border: 1px solid #ced4da; border-radius: 4px; 
        }
        .form-container button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .form-container button:hover { background-color: #0056b3; }
        .form-container a { margin-left: 10px; color: #6c757d; text-decoration:none; }
        .form-container a:hover { text-decoration:underline; }
        .message { padding: 12px 15px; margin-bottom:20px; border-radius:4px; font-size:0.95em; }
        .success { background-color: #d4edda; color: #155724; border:1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border:1px solid #f5c6cb; }
        .nav-links { margin-bottom: 20px; }
        .nav-links a { text-decoration: none; color: #007bff; margin-right: 15px; }
        .nav-links a:hover { text-decoration: underline; }
        .form-note { font-size: 0.9em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="nav-links">
        <a href="dashboard.php">Admin Dashboard</a> |
        <a href="manage_anime.php">Manage Anime List</a>
    </div>
    <h1>Manage Episodes for: <em><?php echo htmlspecialchars($anime_title); ?></em></h1>

    <?php if ($message): ?>
        <div class="message <?php echo (stripos(strtolower($message), 'error') === false && stripos(strtolower($message), 'not found') === false && stripos(strtolower($message), 'mismatch') === false) ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars(urldecode($message)); ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <h2><?php echo ($action === 'edit_episode' && !empty($current_episode_data['id'])) ? 'Edit Episode #' . htmlspecialchars($current_episode_data['episode_number']) : 'Add New Episode'; ?></h2>
        <form action="manage_episodes.php?anime_id=<?php echo $anime_id; ?><?php echo ($action === 'edit_episode' && !empty($current_episode_data['id'])) ? '&action=edit_episode&episode_id='.$current_episode_data['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="anime_id" value="<?php echo $anime_id; ?>">
            <?php if ($action === 'edit_episode' && !empty($current_episode_data['id'])): ?>
                <input type="hidden" name="episode_id" value="<?php echo $current_episode_data['id']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="episode_number">Episode Number:</label>
                <input type="number" id="episode_number" name="episode_number" value="<?php echo htmlspecialchars($current_episode_data['episode_number'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="title">Title (Optional):</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($current_episode_data['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="video_url_local">Current Video File Path (e.g., videos/anime_x/ep1.mp4):</label>
                <input type="text" id="video_url_local" name="video_url_local" value="<?php echo htmlspecialchars($current_episode_data['video_url_local'] ?? ''); ?>" placeholder="Leave empty if uploading new file">
                <p class="form-note">Manually set path or see current path. Upload below will override this.</p>
            </div>
            <div class="form-group">
                <label for="video_file">Upload New Video File (MP4, AVI, MOV, MKV, WEBM):</label>
                <input type="file" name="video_file" id="video_file">
                <p class="form-note">If selected, this will replace the video. Max upload size depends on server config.</p>
            </div>
            <div class="form-group">
                <label for="duration_minutes">Duration (minutes, optional):</label>
                <input type="number" id="duration_minutes" name="duration_minutes" value="<?php echo htmlspecialchars($current_episode_data['duration_minutes'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="air_date">Air Date (optional):</label>
                <input type="date" id="air_date" name="air_date" value="<?php echo htmlspecialchars($current_episode_data['air_date'] ?? ''); ?>">
            </div>
            <?php if ($action === 'edit_episode' && !empty($current_episode_data['id'])): ?>
                <button type="submit" name="edit_episode">Update Episode</button>
                <a href="manage_episodes.php?anime_id=<?php echo $anime_id; ?>">Cancel Edit</a>
            <?php else: ?>
                <button type="submit" name="add_episode">Add Episode</button>
            <?php endif; ?>
        </form>
    </div>

    <h2>Existing Episodes for "<?php echo htmlspecialchars($anime_title); ?>"</h2>
    <table>
        <thead>
            <tr>
                <th>Ep#</th>
                <th>Title</th>
                <th>Video Path</th>
                <th>Duration (min)</th>
                <th>Air Date</th>
                <th>Views</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($episodes_list)): ?>
                <?php foreach ($episodes_list as $episode): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($episode['episode_number']); ?></td>
                        <td><?php echo htmlspecialchars($episode['title'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($episode['video_url_local'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($episode['duration_minutes'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($episode['air_date'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($episode['views']); ?></td>
                        <td class="action-links">
                            <a href="manage_episodes.php?anime_id=<?php echo $anime_id; ?>&action=edit_episode&episode_id=<?php echo $episode['id']; ?>">Edit</a>
                            <a href="manage_episodes.php?anime_id=<?php echo $anime_id; ?>&action=delete_episode&episode_id=<?php echo $episode['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this episode?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No episodes found for this anime. Add one above!</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
