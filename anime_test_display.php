<?php
// Test display for anime data
session_start(); // Good practice, though header might also do it.
require_once 'config/config.php'; // Database connection
// Not including full site header/footer for this minimal test, to reduce complexity for the tool.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Anime Test Display</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .anime-item { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; width: 300px; }
        .anime-item img { max-width: 100px; max-height: 150px; float: left; margin-right: 10px;}
        .anime-item h5 { margin: 0 0 5px 0; }
        .anime-item p { font-size: 0.9em; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <h1>Anime Test Display</h1>
    <?php
    $anime_list = [];
    try {
        // Ensure APPURL is defined or default it for image paths if header.php isn't included
        if (!defined('APPURL')) {
            // Attempt to include header.php to get APPURL, but do it safely
            // This path is relative to anime_test_display.php in the root
            if (file_exists(__DIR__ . '/includes/header.php')) {
                // Temporarily capture output to prevent premature HTML from header
                ob_start();
                require_once __DIR__ . '/includes/header.php';
                ob_end_clean(); // Discard output from header.php
            }
            // If APPURL is still not defined after trying to include header.php, set a default
            if (!defined('APPURL')) {
                 define('APPURL', '.'); // Default if header.php didn't define it or wasn't found
            }
        }


        $stmt = $conn->query("SELECT id, title, description, cover_image_url_local FROM anime ORDER BY RAND() LIMIT 5");
        if ($stmt) {
            $anime_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            echo "<p>Error preparing statement or no statement returned: " . print_r($conn->errorInfo(), true) . "</p>";
        }
    } catch (PDOException $e) {
        error_log("Error fetching anime for test display: " . $e->getMessage());
        echo "<p>Error fetching anime: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    if (!empty($anime_list)) {
        foreach ($anime_list as $anime_item) {
            echo '<div class="anime-item clearfix">';
            // Construct image path.
            $image_path = 'https://via.placeholder.com/100x150.png?text=No+Image'; // Default placeholder
            if (!empty($anime_item['cover_image_url_local'])) {
                 // Prepend APPURL only if cover_image_url_local is not an absolute URL
                if (strpos($anime_item['cover_image_url_local'], 'http') === 0) {
                    $image_path = htmlspecialchars($anime_item['cover_image_url_local']);
                } else {
                    $image_path = htmlspecialchars(APPURL . '/' . ltrim($anime_item['cover_image_url_local'], '/'));
                }
            }
            echo '<img src="' . $image_path . '" alt="' . htmlspecialchars($anime_item['title']) . '">';
            // Link to anime-details.php, ensuring APPURL is used if it's a root-relative link
            $details_url = APPURL . '/anime-details.php?id=' . $anime_item['id'];
            echo '<h5><a href="' . htmlspecialchars($details_url) . '">' . htmlspecialchars($anime_item['title']) . '</a></h5>';
            echo '<p>' . htmlspecialchars(substr($anime_item['description'] ?? 'No description available.', 0, 100)) . '...</p>';
            echo '</div>';
        }
    } elseif (isset($e)) {
        // Error message already displayed if $e is set (PDOException)
    } 
    else if (!$stmt && isset($conn) && $conn->errorCode() !== '00000') {
        // Error message for $stmt being false, if not a PDOException
        // Error info already printed if $stmt was false
    }
    else {
        echo "<p>No anime found in the database.</p>";
    }
    ?>
</body>
</html>
