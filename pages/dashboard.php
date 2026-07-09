<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin']);
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require_once '../includes/header.php';

$totalTables = $pdo->query("SELECT COUNT(*) FROM restaurant_tables")->fetchColumn();
$activeOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'paid'")->fetchColumn();
$menuItems = $pdo->query("SELECT COUNT(*) FROM menu_items WHERE is_available = 1")->fetchColumn();
$todayRevenue = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE DATE(order_time) = CURDATE() AND status = 'paid'")->fetchColumn();

$recentOrders = $pdo->query("
    SELECT o.id, rt.table_number,
           (SELECT COUNT(*) FROM order_details od WHERE od.order_id = o.id) AS item_count,
           o.grand_total, o.status, o.order_time
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    ORDER BY o.order_time DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$lowStockItems = $pdo->query("
    SELECT ingredient_name, available_qty, min_req_qty, unit
    FROM inventory
    WHERE available_qty <= min_req_qty
    ORDER BY (available_qty / GREATEST(min_req_qty, 1)) ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch Weekly Revenue (Last 7 Days) ──
$weeklyRevenueData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weeklyRevenueData[$date] = 0.00;
}

$weeklyRevenueQuery = $pdo->prepare("
    SELECT DATE(order_time) as order_date, COALESCE(SUM(grand_total), 0) as daily_revenue
    FROM orders
    WHERE order_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND status = 'paid'
    GROUP BY DATE(order_time)
");
$weeklyRevenueQuery->execute();
while ($row = $weeklyRevenueQuery->fetch()) {
    $weeklyRevenueData[$row['order_date']] = (float)$row['daily_revenue'];
}

$weeklySalesLabels = [];
$weeklySalesValues = [];
foreach ($weeklyRevenueData as $date => $rev) {
    $weeklySalesLabels[] = date('d M', strtotime($date));
    $weeklySalesValues[] = $rev;
}

// ── Fetch Top 5 Best Selling Items ──
$topItemsQuery = $pdo->query("
    SELECT mi.item_name, SUM(od.quantity) as total_qty
    FROM order_details od
    JOIN menu_items mi ON od.menu_item_id = mi.id
    JOIN orders o ON od.order_id = o.id
    WHERE o.status = 'paid'
    GROUP BY mi.id, mi.item_name
    ORDER BY total_qty DESC
    LIMIT 5
");
$topItems = $topItemsQuery->fetchAll(PDO::FETCH_ASSOC);

$topItemsLabels = [];
$topItemsValues = [];
foreach ($topItems as $item) {
    $topItemsLabels[] = $item['item_name'];
    $topItemsValues[] = (int)$item['total_qty'];
}
?>

<div class="page-header">
    <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">🪑</div>
        <div class="stat-value"><?php echo $totalTables; ?></div>
        <div class="stat-label">Total Tables</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">🛒</div>
        <div class="stat-value"><?php echo $activeOrders; ?></div>
        <div class="stat-label">Active Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📋</div>
        <div class="stat-value"><?php echo $menuItems; ?></div>
        <div class="stat-label">Menu Items</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">💰</div>
        <div class="stat-value">₹<?php echo number_format($todayRevenue, 2); ?></div>
        <div class="stat-label">Today's Revenue</div>
    </div>
</div>

<div class="two-columns mt-4">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📦 Recent Orders</h2>
        </div>
        <?php if (count($recentOrders) > 0): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Table</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                    <td>Table <?php echo htmlspecialchars($order['table_number']); ?></td>
                    <td><?php echo $order['item_count']; ?> items</td>
                    <td>₹<?php echo number_format($order['grand_total'], 2); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('h:i A', strtotime($order['order_time'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="text-center text-muted" style="padding: 2rem;">
            <p>No recent orders found.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">⚠️ Low Stock Alerts</h2>
        </div>
        <?php if (count($lowStockItems) > 0): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Available</th>
                    <th>Min Req.</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockItems as $item): ?>
                <?php
                    $isOutOfStock = $item['available_qty'] <= 0;
                    $rowClass = $isOutOfStock ? 'out-of-stock' : 'low-stock';
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><strong><?php echo htmlspecialchars($item['ingredient_name']); ?></strong></td>
                    <td><?php echo $item['available_qty']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                    <td><?php echo $item['min_req_qty']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                    <td>
                        <?php if ($isOutOfStock): ?>
                            <span class="badge badge-danger">Out of Stock</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Low Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="text-center text-muted" style="padding: 2rem;">
            <p>✅ All ingredients are well stocked!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Graphical Analytics Section -->
<div class="two-columns mt-4">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📈 Weekly Sales Revenue</h2>
        </div>
        <div style="padding: 1.5rem; position: relative; height: 320px; display: flex; align-items: center; justify-content: center;">
            <canvas id="weeklySalesChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🍩 Top 5 Best-Selling Items</h2>
        </div>
        <div style="padding: 1.5rem; position: relative; height: 320px; display: flex; align-items: center; justify-content: center;">
            <canvas id="topItemsChart"></canvas>
        </div>
    </div>
</div>

<div class="quick-actions mt-4">
    <a href="orders.php" class="btn btn-primary">🛒 View Orders</a>
    <a href="tables.php" class="btn btn-success">🪑 Manage Tables</a>
    <a href="menu.php" class="btn btn-warning">📋 View Menu</a>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // ── Weekly Sales Revenue Bar Chart ──
    const salesCtx = document.getElementById('weeklySalesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($weeklySalesLabels); ?>,
            datasets: [{
                label: 'Revenue (₹)',
                data: <?php echo json_encode($weeklySalesValues); ?>,
                backgroundColor: 'rgba(217, 119, 6, 0.6)',
                borderColor: 'rgba(217, 119, 6, 1)',
                borderWidth: 1.5,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(217, 119, 6, 0.85)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(21, 17, 13, 0.95)',
                    titleColor: '#fdf6e6',
                    bodyColor: '#cdbda6',
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    borderWidth: 1,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: ₹' + context.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#cdbda6', font: { family: 'Inter' } }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#cdbda6', font: { family: 'Inter' } },
                    beginAtZero: true
                }
            }
        }
    });

    // ── Top 5 Selling Items Donut Chart ──
    const topCtx = document.getElementById('topItemsChart').getContext('2d');
    new Chart(topCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($topItemsLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($topItemsValues); ?>,
                backgroundColor: [
                    '#d97706',
                    '#3b82f6',
                    '#10b981',
                    '#8b5cf6',
                    '#ec4899'
                ],
                borderColor: 'rgba(21, 17, 13, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#cdbda6',
                        font: { family: 'Inter', size: 12 },
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(21, 17, 13, 0.95)',
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.label + ': ' + context.raw + ' units';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
