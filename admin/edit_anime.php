<?php
session_start();
require_once '../config/config.php'; // Database connection

// Placeholder for admin authentication
if (!isset($_SESSION['username'])) {
    echo "<p style='color:red;'><strong>Note:</strong> Admin authentication is not yet implemented. This page should be protected.</p>";
}

$action = $_GET['action'] ?? 'add'; // 'add' or 'edit'
$anime_id_get = $_GET['id'] ?? null; // Renamed to avoid conflict with $anime_data['id'] later
$message = '';
// Initialize $anime_data with all expected keys to prevent undefined index notices in the form
$anime_data = [
    'id' => null, // Ensure 'id' is part of the structure
    'title' => '', 
    'jikan_anime_id' => '', 
    'description' => '', 
    'type' => '', 
    'studios' => '', 
    'air_date' => '', 
    'status' => '', 
    'cover_image_url_local' => '', 
    'cover_image_url_external' => '', 
    'duration_per_episode_minutes' => '', 
    'quality' => '', 
    'total_episodes' => ''
];
$selected_genre_ids = [];

// Fetch all genres for selection
$all_genres = [];
try {
    $stmt_all_genres = $conn->query("SELECT id, name FROM genres ORDER BY name ASC");
    if ($stmt_all_genres) $all_genres = $stmt_all_genres->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching genres: " . $e->getMessage();
}

if ($action === 'edit' && $anime_id_get) {
    try {
        $stmt = $conn->prepare("SELECT * FROM anime WHERE id = :id");
        $stmt->bindParam(':id', $anime_id_get, PDO::PARAM_INT);
        $stmt->execute();
        $fetched_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fetched_data) {
            $message = "Anime not found for editing.";
            $action = 'add'; // Revert to add mode
            // $anime_id_get remains null or its previous value, but form will act as 'add'
        } else {
            $anime_data = array_merge($anime_data, $fetched_data); // Merge fetched data into default structure
            // Fetch selected genres for this anime
            $stmt_selected_genres = $conn->prepare("SELECT genre_id FROM anime_genres WHERE anime_id = :anime_id");
            $stmt_selected_genres->bindParam(':anime_id', $anime_id_get, PDO::PARAM_INT);
            $stmt_selected_genres->execute();
            $selected_genre_ids = $stmt_selected_genres->fetchAll(PDO::FETCH_COLUMN, 0);
        }
    } catch (PDOException $e) {
        $message = "Error fetching anime data for editing: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_anime'])) {
    // Collect data from form, ensuring keys exist from $anime_data initialization
    $current_anime_id_for_ops = $_POST['anime_id_hidden'] ?? $anime_id_get; // Use hidden field for ID in edit, or GET param

    $form_data = [
        'title' => $_POST['title'] ?? $anime_data['title'],
        'jikan_anime_id' => !empty($_POST['jikan_anime_id']) ? (int)$_POST['jikan_anime_id'] : null,
        'description' => $_POST['description'] ?? $anime_data['description'],
        'type' => $_POST['type'] ?? $anime_data['type'],
        'studios' => $_POST['studios'] ?? $anime_data['studios'],
        'air_date' => !empty($_POST['air_date']) ? $_POST['air_date'] : null,
        'status' => $_POST['status'] ?? $anime_data['status'],
        'cover_image_url_local' => $_POST['cover_image_url_local'] ?? $anime_data['cover_image_url_local'],
        'cover_image_url_external' => $_POST['cover_image_url_external'] ?? $anime_data['cover_image_url_external'],
        'duration_per_episode_minutes' => !empty($_POST['duration_per_episode_minutes']) ? (int)$_POST['duration_per_episode_minutes'] : null,
        'quality' => $_POST['quality'] ?? $anime_data['quality'],
        'total_episodes' => !empty($_POST['total_episodes']) ? (int)$_POST['total_episodes'] : null,
    ];
    
    $posted_genre_ids = $_POST['genre_ids'] ?? [];

    // Basic validation
    if (empty($form_data['title'])) {
        $message = "Title is required.";
         // Repopulate $anime_data with submitted values to show back in form
        $anime_data = array_merge($anime_data, $form_data);
        if($current_anime_id_for_ops) $anime_data['id'] = $current_anime_id_for_ops; // Keep ID if it was an edit attempt
    } else {
        try {
            $conn->beginTransaction();

            if ($action === 'add') {
                $sql = "INSERT INTO anime (title, jikan_anime_id, description, type, studios, air_date, status, cover_image_url_local, cover_image_url_external, duration_per_episode_minutes, quality, total_episodes) 
                        VALUES (:title, :jikan_anime_id, :description, :type, :studios, :air_date, :status, :cover_image_url_local, :cover_image_url_external, :duration_per_episode_minutes, :quality, :total_episodes)";
                $stmt = $conn->prepare($sql);
                $stmt->execute($form_data);
                $current_anime_id_for_ops = $conn->lastInsertId(); // Get ID for genre linking
                $message = "Anime added successfully! ID: $current_anime_id_for_ops";
            } elseif ($action === 'edit' && $current_anime_id_for_ops) {
                $form_data['id'] = $current_anime_id_for_ops; 
                $sql = "UPDATE anime SET title = :title, jikan_anime_id = :jikan_anime_id, description = :description, type = :type, studios = :studios, air_date = :air_date, status = :status, cover_image_url_local = :cover_image_url_local, cover_image_url_external = :cover_image_url_external, duration_per_episode_minutes = :duration_per_episode_minutes, quality = :quality, total_episodes = :total_episodes 
                        WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute($form_data);
                $message = "Anime updated successfully!";
            }

            if ($current_anime_id_for_ops) { // Ensure we have an anime ID for genre operations
                // Manage anime_genres
                // 1. Delete existing genres for this anime
                $stmt_delete_genres = $conn->prepare("DELETE FROM anime_genres WHERE anime_id = :anime_id");
                $stmt_delete_genres->bindParam(':anime_id', $current_anime_id_for_ops, PDO::PARAM_INT);
                $stmt_delete_genres->execute();
                
                // 2. Insert selected genres
                if (!empty($posted_genre_ids)) {
                    $stmt_insert_genre = $conn->prepare("INSERT INTO anime_genres (anime_id, genre_id) VALUES (:anime_id, :genre_id)");
                    foreach ($posted_genre_ids as $genre_id_posted) {
                        $stmt_insert_genre->execute([':anime_id' => $current_anime_id_for_ops, ':genre_id' => (int)$genre_id_posted]);
                    }
                }
            }
            $conn->commit();
            
            if ($action === 'add' && $current_anime_id_for_ops) {
                 header("Location: manage_anime.php?message=" . urlencode($message)); // Redirect after successful add
                 exit;
            }
             // After update, re-fetch data to display correctly, including new genres
            if ($action === 'edit' && $current_anime_id_for_ops) {
                $stmt = $conn->prepare("SELECT * FROM anime WHERE id = :id");
                $stmt->bindParam(':id', $current_anime_id_for_ops, PDO::PARAM_INT);
                $stmt->execute();
                $anime_data = $stmt->fetch(PDO::FETCH_ASSOC); // This ensures $anime_data has the ID for the form

                $stmt_selected_genres = $conn->prepare("SELECT genre_id FROM anime_genres WHERE anime_id = :anime_id");
                $stmt_selected_genres->bindParam(':anime_id', $current_anime_id_for_ops, PDO::PARAM_INT);
                $stmt_selected_genres->execute();
                $selected_genre_ids = $stmt_selected_genres->fetchAll(PDO::FETCH_COLUMN, 0);
            }

        } catch (PDOException $e) {
            if ($conn->inTransaction()) { // Check if transaction is active before rollback
                 $conn->rollBack();
            }
            $message = "Database error: " . $e->getMessage();
            // Repopulate $anime_data with submitted values to show back in form
            $anime_data = array_merge($anime_data, $form_data);
            if($current_anime_id_for_ops) $anime_data['id'] = $current_anime_id_for_ops; // Keep ID
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo ucfirst($action); ?> Anime</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f7f6; color: #333; }
        .form-container { max-width: 800px; margin: auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="date"], .form-group textarea, .form-group select { 
            width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;
        }
        .form-group textarea { min-height: 80px; }
        .form-group .genres-list { max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 4px; }
        .form-group .genres-list label { display: block; font-weight: normal; }
        .message { padding: 12px 15px; margin-bottom:20px; border-radius:4px; font-size:0.95em; }
        .success { background-color: #d4edda; color: #155724; border:1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border:1px solid #f5c6cb; }
        button[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        button[type="submit"]:hover { background-color: #0056b3; }
        .nav-link-back { display:inline-block; margin-bottom:20px; padding:8px 15px; background-color:#6c757d; color:white; text-decoration:none; border-radius:4px;}
        .nav-link-back:hover { background-color:#5a6268; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1><?php echo ucfirst($action); ?> Anime</h1>
        <p><a href="manage_anime.php" class="nav-link-back">Back to Anime List</a></p>

        <?php if ($message): ?>
            <div class="message <?php echo (stripos(strtolower($message), 'error') === false && stripos(strtolower($message), 'not found') === false) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="edit_anime.php?action=<?php echo $action; ?><?php echo $anime_data['id'] ? '&id='.$anime_data['id'] : ''; ?>" method="POST">
            <!-- Hidden field to pass anime_id for edit operations, especially if GET id is lost -->
            <?php if ($action === 'edit' && $anime_data['id']): ?>
                <input type="hidden" name="anime_id_hidden" value="<?php echo htmlspecialchars($anime_data['id']); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($anime_data['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="jikan_anime_id">Jikan Anime ID (Optional):</label>
                <input type="number" id="jikan_anime_id" name="jikan_anime_id" value="<?php echo htmlspecialchars($anime_data['jikan_anime_id'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($anime_data['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="type">Type (e.g., TV, Movie, OVA):</label>
                <input type="text" id="type" name="type" value="<?php echo htmlspecialchars($anime_data['type'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="studios">Studios:</label>
                <input type="text" id="studios" name="studios" value="<?php echo htmlspecialchars($anime_data['studios'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="air_date">Air Date:</label>
                <input type="date" id="air_date" name="air_date" value="<?php echo htmlspecialchars($anime_data['air_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="status">Status (e.g., Airing, Finished):</label>
                <input type="text" id="status" name="status" value="<?php echo htmlspecialchars($anime_data['status'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="cover_image_url_local">Cover Image URL (Local, e.g., img/anime/cover.jpg):</label>
                <input type="text" id="cover_image_url_local" name="cover_image_url_local" value="<?php echo htmlspecialchars($anime_data['cover_image_url_local'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="cover_image_url_external">Cover Image URL (External):</label>
                <input type="text" id="cover_image_url_external" name="cover_image_url_external" value="<?php echo htmlspecialchars($anime_data['cover_image_url_external'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="duration_per_episode_minutes">Duration per Episode (minutes):</label>
                <input type="number" id="duration_per_episode_minutes" name="duration_per_episode_minutes" value="<?php echo htmlspecialchars($anime_data['duration_per_episode_minutes'] ?? ''); ?>">
            </div>
             <div class="form-group">
                <label for="quality">Quality (e.g., HD, SD):</label>
                <input type="text" id="quality" name="quality" value="<?php echo htmlspecialchars($anime_data['quality'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="total_episodes">Total Episodes (number):</label>
                <input type="number" id="total_episodes" name="total_episodes" value="<?php echo htmlspecialchars($anime_data['total_episodes'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Genres:</label>
                <div class="genres-list">
                    <?php if (!empty($all_genres)): ?>
                        <?php foreach ($all_genres as $genre_option): ?>
                            <label>
                                <input type="checkbox" name="genre_ids[]" value="<?php echo $genre_option['id']; ?>" <?php echo in_array($genre_option['id'], $selected_genre_ids) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($genre_option['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No genres found. Please <a href="manage_genres.php">add genres</a> first.</p>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" name="submit_anime"><?php echo ucfirst($action); ?> Anime</button>
        </form>
    </div>
</body>
</html>
