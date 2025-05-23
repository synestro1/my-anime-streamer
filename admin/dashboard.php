<?php
session_start();
require_once '../config/config.php'; // Adjusted path for config
// require_once '../includes/header_admin.php'; // Optional: if you create a specific admin header

// Basic authentication check (example - can be more robust)
if (!isset($_SESSION['username'])) { // Replace with a real admin check later
    echo "<p style='color:red;'><strong>Note:</strong> Admin authentication is not yet implemented. This page should be protected.</p>";
}

// Fetch basic stats
$user_count = 0;
$anime_count = 0;
$episode_count = 0;
$new_users_last_7_days = 0;
$most_watched_anime = [];
$popular_genres = [];
$pending_comments_count = 0; // New metric

try {
    $stmt_users = $conn->query("SELECT COUNT(*) FROM users");
    if ($stmt_users) $user_count = $stmt_users->fetchColumn();

    $stmt_anime = $conn->query("SELECT COUNT(*) FROM anime");
    if ($stmt_anime) $anime_count = $stmt_anime->fetchColumn();
    
    $stmt_episodes = $conn->query("SELECT COUNT(*) FROM episodes");
    if ($stmt_episodes) $episode_count = $stmt_episodes->fetchColumn();

    // New Users (Last 7 Days)
    $stmt_new_users = $conn->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    if ($stmt_new_users) $new_users_last_7_days = $stmt_new_users->fetchColumn();

    // Most Watched Anime (Top 3)
    $stmt_most_watched = $conn->query("
        SELECT a.title, COALESCE(SUM(e.views), 0) AS total_views 
        FROM anime a 
        LEFT JOIN episodes e ON a.id = e.anime_id 
        GROUP BY a.id 
        ORDER BY total_views DESC 
        LIMIT 3
    ");
    if ($stmt_most_watched) $most_watched_anime = $stmt_most_watched->fetchAll(PDO::FETCH_ASSOC);

    // Popular Genres (Top 3)
    $stmt_popular_genres = $conn->query("
        SELECT g.name, COUNT(ag.anime_id) AS anime_count 
        FROM genres g 
        LEFT JOIN anime_genres ag ON g.id = ag.genre_id 
        GROUP BY g.id 
        ORDER BY anime_count DESC 
        LIMIT 3
    ");
    if ($stmt_popular_genres) $popular_genres = $stmt_popular_genres->fetchAll(PDO::FETCH_ASSOC);

    // Pending Comments Count (using the new 'status' column)
    $stmt_pending_comments = $conn->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
    if ($stmt_pending_comments) $pending_comments_count = $stmt_pending_comments->fetchColumn();


} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    echo "<p style='color:red;'>Error fetching dashboard stats: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css"> <!-- Adjust path if admin CSS is different or reuse main -->
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f7f6; color: #333; }
        h1, h2, h3 { color: #2c3e50; }
        .dashboard-container { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; width: 220px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { margin-top: 0; font-size: 1.2em; color: #34495e; }
        .stat-card p { font-size: 2.5em; margin-bottom: 0; color: #2980b9; font-weight: bold;}
        .list-card { background-color: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; width: 300px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom:20px; }
        .list-card h3 { margin-top: 0; font-size: 1.2em; color: #34495e; }
        .list-card ul { list-style-type: none; padding: 0; }
        .list-card li { padding: 8px 0; border-bottom: 1px solid #eee; font-size: 0.95em; }
        .list-card li:last-child { border-bottom: none; }
        .list-card li strong { color: #2980b9; }
        .dashboard-section { margin-bottom: 30px; }
        .dashboard-section h2 { border-bottom: 2px solid #2980b9; padding-bottom: 10px; }
        .next-steps-container ul { list-style-type: disc; padding-left: 20px; }
        .nav-list { list-style-type: none; padding: 0; margin-top: 10px; }
        .nav-list li { margin-bottom: 8px; }
        .nav-list li a { text-decoration: none; color: #007bff; font-weight: bold; padding: 5px 10px; border: 1px solid #007bff; border-radius: 4px; display: inline-block;}
        .nav-list li a:hover { background-color: #007bff; color: #fff; }
    </style>
</head>
<body>
    <h1>Admin Dashboard</h1>
    
    <div class="dashboard-section">
        <h2>Key Metrics</h2>
        <div class="dashboard-container">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p><?php echo $user_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Anime Titles</h3>
                <p><?php echo $anime_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Episodes</h3>
                <p><?php echo $episode_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>New Users (Last 7 Days)</h3>
                <p><?php echo $new_users_last_7_days; ?></p>
            </div>
             <div class="stat-card">
                <h3>Pending Comments</h3>
                <p><?php echo $pending_comments_count; ?></p>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Content Performance</h2>
        <div class="dashboard-container">
            <div class="list-card">
                <h3>Top 3 Most Watched Anime</h3>
                <ul>
                    <?php if (!empty($most_watched_anime)): ?>
                        <?php foreach($most_watched_anime as $anime_item): ?>
                            <li><?php echo htmlspecialchars($anime_item['title']); ?> (<strong><?php echo htmlspecialchars($anime_item['total_views']); ?></strong> views)</li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No view data available.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="list-card">
                <h3>Top 3 Popular Genres</h3>
                <ul>
                    <?php if (!empty($popular_genres)): ?>
                        <?php foreach($popular_genres as $genre_item): ?>
                            <li><?php echo htmlspecialchars($genre_item['name']); ?> (<strong><?php echo htmlspecialchars($genre_item['anime_count']); ?></strong> titles)</li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No genre data available.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="dashboard-section next-steps-container">
        <h2>Management Sections</h2>
        <ul class="nav-list">
            <li><a href="manage_genres.php">Manage Genres</a></li>
            <li><a href="manage_anime.php">Manage Anime</a></li>
            <li><a href="manage_users.php">Manage Users</a></li>
            <li><a href="manage_comments.php">Manage Comments</a></li>
        </ul>
    </div>

    <div class="dashboard-section next-steps-container">
        <h2>Future Enhancements:</h2>
        <ul>
            <li>Implement proper admin user role and authentication.</li>
            <li>Display more detailed site statistics and analytics.</li>
        </ul>
    </div>
    <?php // require_once '../includes/footer_admin.php'; // Optional: if you create a specific admin footer ?>
</body>
</html>
