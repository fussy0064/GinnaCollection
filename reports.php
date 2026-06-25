<?php
require_once 'config/config.php';

// Access Control: CEO only
$currentUser = BaseUser::requireRole(['CEO']);
$navigation = $currentUser->getNavigationMenu();

$db = Database::getInstance();
$error = '';
$range = $_GET['range'] ?? 'all';

// Determine SQL condition for date range
$dateCondition = '';
$params = [];

switch ($range) {
    case 'day':
        $dateCondition = "WHERE DATE(s.sale_date) = CURRENT_DATE()";
        break;
    case 'week':
        $dateCondition = "WHERE s.sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateCondition = "WHERE s.sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)";
        break;
    case 'all':
    default:
        $range = 'all';
        $dateCondition = "";
        break;
}

// Fetch sales records based on date range
try {
    $salesQuery = "
        SELECT s.*, u.username as seller_username, u.name as seller_name_encrypted 
        FROM sales s 
        LEFT JOIN users u ON s.seller_id = u.id 
        $dateCondition 
        ORDER BY s.sale_date DESC
    ";
    $stmt = $db->prepare($salesQuery);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
} catch (Exception $e) {
    $sales = [];
    $error = 'Failed to fetch sales report: ' . $e->getMessage();
}

// Handle CSV Download Request
if (isset($_GET['download']) && $_GET['download'] === '1' && empty($error)) {
    // Clear any output buffer to ensure clean CSV delivery
    if (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=GinnaBeauty_Sales_' . $range . '_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Receipt ID', 'Date', 'Client Name', 'Client Phone', 'Seller Name', 'Total Cost (Tshs)']);
    
    foreach ($sales as $row) {
        $clientName = Encryption::decrypt($row['client_name']) ?? 'Decryption Failed';
        $clientPhone = Encryption::decrypt($row['client_phone']) ?? 'Decryption Failed';
        $sellerName = !empty($row['seller_name_encrypted']) ? (Encryption::decrypt($row['seller_name_encrypted']) ?? $row['seller_username'] ?? 'Unknown') : ($row['seller_username'] ?? 'Unknown');
        fputcsv($output, [
            $row['id'],
            $row['sale_date'],
            $clientName,
            $clientPhone,
            $sellerName,
            number_format($row['total_cost'], 2)
        ]);
    }
    
    fclose($output);
    exit;
}

// Calculate Financial Metrics (Revenue, Cost of Goods Sold, Net Profit/Loss)
$revenue = 0.0;
$cogs = 0.0;

try {
    // Get all sale items for the selected range to calculate COGS based on the products' current cost price
    $cogsQuery = "
        SELECT si.quantity, p.cost_price, si.unit_price
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        $dateCondition
    ";
    $cogsStmt = $db->prepare($cogsQuery);
    $cogsStmt->execute($params);
    $items = $cogsStmt->fetchAll();

    foreach ($items as $item) {
        $revenue += $item['quantity'] * $item['unit_price'];
        $cogs += $item['quantity'] * $item['cost_price'];
    }
    
    $netProfit = $revenue - $cogs;
} catch (Exception $e) {
    $netProfit = 0;
    $error = 'Failed to calculate financial metrics: ' . $e->getMessage();
}

// Fetch Inventory Stock Status Aggregations
$lowStockCount = 0;
$heavyStockCount = 0;
$normalStockCount = 0;

try {
    $stockStmt = $db->query("SELECT stock_quantity, min_stock_level, max_stock_level FROM products");
    while ($row = $stockStmt->fetch()) {
        $qty = (int)$row['stock_quantity'];
        $min = (int)$row['min_stock_level'];
        $max = (int)$row['max_stock_level'];

        if ($qty < $min) {
            $lowStockCount++;
        } elseif ($qty > $max) {
            $heavyStockCount++;
        } else {
            $normalStockCount++;
        }
    }
} catch (Exception $e) {
    // Suppress or handle stock status errors gracefully
}
$pageTitle = 'Reports - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <h2>Sales & Analytics Reporting</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Date Range Filters -->
            <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; gap: 15px;">
                <form action="reports.php" method="GET" style="display: flex; align-items: center; gap: 15px;">
                    <label for="range" style="font-weight: bold;">Select Filter Period:</label>
                    <select id="range" name="range" style="padding: 8px 14px; border: 1px solid #d1d5db; border-radius: 8px; width: 180px;">
                        <option value="all" <?php echo $range === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="day" <?php echo $range === 'day' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $range === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $range === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                    <button type="submit" class="btn">Filter</button>
                </form>
                
                <div>
                    <a href="reports.php?range=<?php echo urlencode($range); ?>&download=1" class="btn" style="background-color: #28a745; border-color: #28a745;">Download CSV Report</a>
                </div>
            </div>

            <!-- Dashboard Analytics Counters -->
            <div class="row" style="margin-bottom: 25px;">
                <!-- Financial Cards -->
                <div class="col" style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; text-align: center;">
                    <h3>Revenue</h3>
                    <p style="font-size: 1.6rem; font-weight: bold; margin-top: 10px;">Tshs <?php echo number_format($revenue, 2); ?></p>
                </div>
                <div class="col" style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; text-align: center;">
                    <h3>Cost of Goods (COGS)</h3>
                    <p style="font-size: 1.6rem; font-weight: bold; margin-top: 10px;">Tshs <?php echo number_format($cogs, 2); ?></p>
                </div>
                <div class="col" style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; text-align: center;">
                    <h3>Net Profit / Loss</h3>
                    <?php if ($netProfit >= 0): ?>
                        <p class="text-success" style="font-size: 1.6rem; margin-top: 10px;">Tshs <?php echo number_format($netProfit, 2); ?></p>
                    <?php else: ?>
                        <p class="text-danger" style="font-size: 1.6rem; margin-top: 10px;">-Tshs <?php echo number_format(abs($netProfit), 2); ?> (Loss)</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <!-- Stock Status Summary -->
                <div class="col" style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; max-width: 320px; display: flex; flex-direction: column; justify-content: center;">
                    <h3 style="text-align: center; margin-bottom: 15px;">Stock Status Summary</h3>
                    <div style="font-size: 1.1rem; line-height: 2;">
                        <p>🔴 Low Stock Items: <strong class="text-danger"><?php echo $lowStockCount; ?></strong></p>
                        <p>🟢 Heavy Stock Items: <strong class="text-success"><?php echo $heavyStockCount; ?></strong></p>
                        <p>⚫ Normal Stock Items: <strong><?php echo $normalStockCount; ?></strong></p>
                    </div>
                </div>

                <!-- Sales Transactions Table -->
                <div class="col">
                    <h3>Sales Transactions (Filtered: <?php echo ucfirst($range); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Receipt ID</th>
                                <th>Date</th>
                                <th>Client Name</th>
                                <th>Client Phone</th>
                                <th>Seller Name</th>
                                <th>Total Cost</th>
                                <?php if ($currentUser->getRole() !== 'CEO'): ?>
                                    <th>Receipt</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No transactions found for the selected range.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($sales as $row): ?>
                                <?php
                                    $clientName = Encryption::decrypt($row['client_name']) ?? 'Decryption Failed';
                                    $clientPhone = Encryption::decrypt($row['client_phone']) ?? 'Decryption Failed';
                                    $sellerName = !empty($row['seller_name_encrypted']) ? (Encryption::decrypt($row['seller_name_encrypted']) ?? $row['seller_username'] ?? 'Unknown') : ($row['seller_username'] ?? 'Unknown');
                                ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><small><?php echo htmlspecialchars($row['sale_date']); ?></small></td>
                                    <td><?php echo htmlspecialchars($clientName); ?></td>
                                    <td><?php echo htmlspecialchars($clientPhone); ?></td>
                                    <td><?php echo htmlspecialchars($sellerName); ?></td>
                                    <td>Tshs <?php echo number_format($row['total_cost'], 2); ?></td>
                                    <?php if ($currentUser->getRole() !== 'CEO'): ?>
                                        <td>
                                            <a href="receipt.php?id=<?php echo $row['id']; ?>" class="btn" style="padding: 2px 6px; font-size: 0.8rem;">View</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once 'includes/footer.php'; ?>
