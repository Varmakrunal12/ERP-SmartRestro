<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin']);

$pageTitle = 'Admin Settings';
$currentPage = 'settings';
require_once '../includes/header.php';

// Get database size info
$dbSize = 'Unknown';
try {
    $dbSizeQuery = $pdo->prepare("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size 
        FROM information_schema.tables 
        WHERE table_schema = ?
    ");
    $dbSizeQuery->execute(['restaurant_erp']);
    $dbSize = $dbSizeQuery->fetchColumn() . ' MB';
} catch (Exception $e) {
    // Fallback
}

// Fetch feedback logs joined with tables and orders info
$feedbackLogs = $pdo->query("
    SELECT f.*, o.grand_total, o.order_time, rt.table_number
    FROM feedback f
    JOIN orders o ON f.order_id = o.id
    JOIN restaurant_tables rt ON o.table_id = rt.id
    ORDER BY f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate feedback statistics
$stats = $pdo->query("
    SELECT COUNT(*) as total_feedback,
           COALESCE(AVG(rating), 0) as avg_rating,
           SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as stars_5,
           SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as stars_4,
           SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as stars_3,
           SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as stars_2,
           SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as stars_1
    FROM feedback
")->fetch(PDO::FETCH_ASSOC);

$totalFb = $stats['total_feedback'] ?: 0;
$avgRating = round((float)$stats['avg_rating'], 1);
?>

<div class="page-header">
    <h1 class="page-title">⚙️ Admin Settings</h1>
    <p class="text-muted">Manage system configuration, data backups, and view customer reviews.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">📊</div>
        <div class="stat-value"><?php echo $totalFb; ?></div>
        <div class="stat-label">Total Feedbacks</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">⭐</div>
        <div class="stat-value"><?php echo $avgRating; ?> / 5.0</div>
        <div class="stat-label">Average Customer Rating</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💾</div>
        <div class="stat-value"><?php echo $dbSize; ?></div>
        <div class="stat-label">ERP Database Size</div>
    </div>
</div>

<div class="two-columns mt-4">
    <!-- Database Backup Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">💾 Database Management</h2>
        </div>
        <div style="padding: 1.5rem;">
            <p style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.5rem;">
                Back up your entire database structure and row data. Clicking the export button will compile all MySQL tables, relationships, and data dumps into a single secure <code>.sql</code> file for local archiving.
            </p>
            <a href="/api/export_db.php" class="btn btn-primary btn-lg btn-block" style="justify-content: center; font-size: 1rem; padding: 12px 20px;">
                📥 Export Database Backup (.sql)
            </a>
        </div>
    </div>

    <!-- System Info Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🖥️ System & Server Information</h2>
        </div>
        <div style="padding: 1rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span style="color: var(--text-muted); font-size: 0.9rem;">ERP Version</span>
                <strong style="color: var(--text-primary); font-size: 0.9rem;">v3.0.0 (Advanced)</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span style="color: var(--text-muted); font-size: 0.9rem;">PHP Engine</span>
                <strong style="color: var(--text-primary); font-size: 0.9rem;"><?php echo phpversion(); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span style="color: var(--text-muted); font-size: 0.9rem;">PDO SQL Driver</span>
                <strong style="color: var(--text-primary); font-size: 0.9rem;"><?php echo $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span style="color: var(--text-muted); font-size: 0.9rem;">Database Engine</span>
                <strong style="color: var(--text-primary); font-size: 0.9rem;"><?php echo $pdo->getAttribute(PDO::ATTR_SERVER_INFO); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span style="color: var(--text-muted); font-size: 0.9rem;">Environment Mode</span>
                <strong style="color: var(--warning); font-size: 0.9rem;">Development / Testing</strong>
            </div>
        </div>
    </div>
</div>

<!-- Customer Reviews Section -->
<div class="card mt-4">
    <div class="card-header">
        <h2 class="card-title">💬 Customer Reviews & Feedback Logs</h2>
    </div>
    
    <?php if (empty($feedbackLogs)): ?>
        <div class="text-center text-muted" style="padding: 3rem;">
            <div style="font-size: 3rem;">💬</div>
            <p>No customer reviews submitted yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Table</th>
                        <th>Amount</th>
                        <th>Rating</th>
                        <th>Comments</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbackLogs as $fb): ?>
                        <tr>
                            <td><strong>#<?php echo $fb['order_id']; ?></strong></td>
                            <td>Table <?php echo htmlspecialchars($fb['table_number']); ?></td>
                            <td>₹<?php echo number_format($fb['grand_total'], 2); ?></td>
                            <td>
                                <span style="color: #fbbf24; font-weight: bold; letter-spacing: 2px;">
                                    <?php echo str_repeat('★', $fb['rating']) . str_repeat('☆', 5 - $fb['rating']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-style: italic; color: var(--text-primary);">
                                    <?php echo $fb['comments'] ? '"' . htmlspecialchars($fb['comments']) . '"' : '<span style="color: var(--text-muted);">No comments left</span>'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y, h:i A', strtotime($fb['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
