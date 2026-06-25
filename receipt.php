<?php
require_once 'config/config.php';

// Access Control: Seller only
$currentUser = BaseUser::requireRole(['Seller']);
$navigation = $currentUser->getNavigationMenu();

$db = Database::getInstance();
$error = '';
$sale = null;
$sellerName = '';
$itemCount = 0;

if (!isset($_GET['id'])) {
    $error = 'No Sale ID provided.';
} else {
    $saleId = (int)$_GET['id'];
    try {
        $sale = Sale::fetchById($db, $saleId);
        if ($sale) {
            // Fetch seller's decrypted name
            $sellerStmt = $db->prepare("SELECT name FROM users WHERE id = :id");
            $sellerStmt->execute([':id' => $sale->getSellerId()]);
            $sellerRow = $sellerStmt->fetch();
            $sellerName = $sellerRow ? (Encryption::decrypt($sellerRow['name']) ?? 'Unknown') : 'Unknown';

            // Calculate total items count
            foreach ($sale->getItems() as $item) {
                $itemCount += $item['quantity'];
            }
        } else {
            $error = 'Sale not found.';
        }
    } catch (Exception $e) {
        $error = 'Error retrieving receipt: ' . $e->getMessage();
    }
}
$pageTitle = 'Sales Receipt - Ginna Beauty Collection';
$isPrintPage = true;
?>
<?php require_once 'includes/header.php'; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger no-print"><?php echo htmlspecialchars($error); ?></div>
                <p class="no-print"><a href="sales.php" class="btn">Back to POS</a></p>
            <?php elseif ($sale): ?>
                
                <div class="receipt-box">
                    <div class="receipt-header">
                        <h2>GINNA BEAUTY COLLECTION</h2>
                        <p>Inventory System Receipt</p>
                        <p><strong>Receipt ID:</strong> #<?php echo $sale->getId(); ?></p>
                        <p><strong>Date:</strong> <?php echo htmlspecialchars($sale->getSaleDate()); ?></p>
                    </div>

                    <hr style="border: none; border-top: 1px dashed #000; margin: 15px 0;">

                    <div style="margin-bottom: 15px;">
                        <p><strong>Client Name:</strong> <?php echo htmlspecialchars($sale->getClientName()); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($sale->getClientPhone()); ?></p>
                        <p><strong>Seller Name:</strong> <?php echo htmlspecialchars($sellerName); ?></p>
                        <?php if ($sale->getDescription() !== ''): ?>
                            <p><strong>Cart Description:</strong> <?php echo htmlspecialchars($sale->getDescription()); ?></p>
                        <?php endif; ?>
                    </div>

                    <hr style="border: none; border-top: 1px dashed #000; margin: 15px 0;">

                    <table style="margin: 10px 0; border: none;">
                        <thead>
                            <tr style="border-bottom: 1px solid #000;">
                                <th style="border: none; background: none; padding: 5px 0;">Item Name</th>
                                <th style="border: none; background: none; padding: 5px 0; text-align: center;">Qty</th>
                                <th style="border: none; background: none; padding: 5px 0; text-align: right;">Price</th>
                                <th style="border: none; background: none; padding: 5px 0; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale->getItems() as $item): ?>
                                <tr>
                                    <td style="border: none; padding: 5px 0;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td style="border: none; padding: 5px 0; text-align: center;"><?php echo $item['quantity']; ?></td>
                                    <td style="border: none; padding: 5px 0; text-align: right;">Tshs <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td style="border: none; padding: 5px 0; text-align: right;">Tshs <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <hr style="border: none; border-top: 1px dashed #000; margin: 15px 0;">

                    <div style="text-align: right; margin-bottom: 20px;">
                        <p><strong>Total Items Count:</strong> <?php echo $itemCount; ?></p>
                        <p style="font-size: 1.25rem;"><strong>Total Cost:</strong> Tshs <?php echo number_format($sale->getTotalCost(), 2); ?></p>
                    </div>
                </div>

                <div class="no-print" style="text-align: center; margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
                    <button onclick="window.print();" class="btn">Print Receipt</button>
                    <a href="sales.php" class="btn btn-secondary">New Sale (POS)</a>
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                </div>
            <?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
