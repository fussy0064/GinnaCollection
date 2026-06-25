<?php
require_once 'config/config.php';

// Access Control: Manager only
$currentUser = BaseUser::requireRole(['Manager']);
$navigation = $currentUser->getNavigationMenu();

$db = Database::getInstance();
$error = '';
$success = '';

// Handle Edit Fetching
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editProduct = Product::fetchById($db, $editId);
}

// Handle CRUD Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $action = $_POST['action'];

    if ($action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $cost_price = (float)($_POST['cost_price'] ?? 0);
        $sell_price = (float)($_POST['sell_price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $min_stock_level = (int)($_POST['min_stock_level'] ?? 5);
        $max_stock_level = (int)($_POST['max_stock_level'] ?? 50);

        if ($name === '' || $category === '' || $cost_price < 0 || $sell_price < 0) {
            $error = 'Please fill out all required fields with valid values.';
        } else {
            try {
                if ($id !== null) {
                    // Updating
                    $product = Product::fetchById($db, $id);
                    if ($product) {
                        $product->setName($name);
                        $product->setCategory($category);
                        $product->setDescription($description);
                        $product->setCostPrice($cost_price);
                        $product->setSellPrice($sell_price);
                        $product->setStockQuantity($stock_quantity);
                        $product->setMinStockLevel($min_stock_level);
                        $product->setMaxStockLevel($max_stock_level);
                        $product->save($db);
                        $success = 'Product updated successfully.';
                    } else {
                        $error = 'Product not found.';
                    }
                } else {
                    // Creating
                    $product = new Product($name, $category, $cost_price, $sell_price, $stock_quantity, $min_stock_level, $max_stock_level, $description);
                    $product->save($db);
                    $success = 'Product created successfully.';
                }
                
                // Redirect after save to prevent duplicate submissions on refresh (PRG pattern)
                header("Location: products.php");
                exit;
            } catch (Exception $e) {
                $error = 'Error saving product: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $product = Product::fetchById($db, $id);
            if ($product) {
                // Check if product is in any sales order first to prevent constraint violations
                $check = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = :id");
                $check->execute([':id' => $id]);
                if ($check->fetchColumn() > 0) {
                    $error = 'Cannot delete product because it has associated sales records. Update stock to 0 instead.';
                } else {
                    $product->delete($db);
                    $success = 'Product deleted successfully.';
                }
            } else {
                $error = 'Product not found.';
            }
        } catch (Exception $e) {
            $error = 'Error deleting product: ' . $e->getMessage();
        }
    }
    }
}

// Fetch all products for display
$products = Product::fetchAll($db);

$pageTitle = 'Manage Products - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <h2>Product Inventory Management</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Manage Product Form (Manual Only) -->
                <div class="col" style="max-width: 380px; display: flex; flex-direction: column; gap: 20px; align-self: flex-start;">
                    <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff;">
                        <h3><?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></h3>
                        <form action="products.php" method="POST" style="margin-top: 15px;">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="action" value="save">
                            <?php if ($editProduct): ?>
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editProduct->getId()); ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" required value="<?php echo $editProduct ? htmlspecialchars($editProduct->getName()) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Tops" <?php echo ($editProduct && $editProduct->getCategory() === 'Tops') ? 'selected' : ''; ?>>Tops</option>
                                    <option value="Jeans" <?php echo ($editProduct && $editProduct->getCategory() === 'Jeans') ? 'selected' : ''; ?>>Jeans</option>
                                    <option value="Shoes" <?php echo ($editProduct && $editProduct->getCategory() === 'Shoes') ? 'selected' : ''; ?>>Shoes</option>
                                    <option value="Skincare" <?php echo ($editProduct && $editProduct->getCategory() === 'Skincare') ? 'selected' : ''; ?>>Skincare</option>
                                    <option value="Makeup" <?php echo ($editProduct && $editProduct->getCategory() === 'Makeup') ? 'selected' : ''; ?>>Makeup</option>
                                    <option value="Bags" <?php echo ($editProduct && $editProduct->getCategory() === 'Bags') ? 'selected' : ''; ?>>Bags</option>
                                    <option value="Jewelry" <?php echo ($editProduct && $editProduct->getCategory() === 'Jewelry') ? 'selected' : ''; ?>>Jewelry</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="2"><?php echo $editProduct ? htmlspecialchars($editProduct->getDescription()) : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="cost_price">Cost Price (Tshs) *</label>
                                <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" required value="<?php echo $editProduct ? htmlspecialchars($editProduct->getCostPrice()) : '0.00'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="sell_price">Selling Price (Tshs) *</label>
                                <input type="number" id="sell_price" name="sell_price" step="0.01" min="0" required value="<?php echo $editProduct ? htmlspecialchars($editProduct->getSellPrice()) : '0.00'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="stock_quantity">Stock Quantity *</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" min="0" required value="<?php echo $editProduct ? htmlspecialchars($editProduct->getStockQuantity()) : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="min_stock_level">Min Stock (Low Stock Alert) *</label>
                                <input type="number" id="min_stock_level" name="min_stock_level" min="1" required value="<?php echo $editProduct ? htmlspecialchars($editProduct->getMinStockLevel()) : '5'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="max_stock_level">Max Stock (Heavy Stock Alert) *</label>
                                <input type="number" id="max_stock_level" name="max_stock_level" min="1" required value="<?php echo $editProduct ? htmlspecialchars($editProduct->getMaxStockLevel()) : '50'; ?>">
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: flex-start;">
                                <button type="submit" class="btn"><?php echo $editProduct ? 'Update Product' : 'Add Product'; ?></button>
                                <?php if ($editProduct): ?>
                                    <a href="products.php" class="btn btn-secondary">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="col">
                    <h3>Current Inventory</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Cost</th>
                                <th>Sell</th>
                                <th>Qty</th>
                                <th>Stock Status</th>
                                <th>Profitability</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No products in inventory.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($products as $prod): ?>
                                <?php 
                                    $status = $prod->getStockStatus(); 
                                    $isLoss = $prod->isLossMaking();
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($prod->getName()); ?></strong>
                                        <?php if ($prod->getDescription()): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($prod->getDescription()); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($prod->getCategory()); ?></code></td>
                                    <td>Tshs <?php echo number_format($prod->getCostPrice(), 2); ?></td>
                                    <td>Tshs <?php echo number_format($prod->getSellPrice(), 2); ?></td>
                                    <td><?php echo $prod->getStockQuantity(); ?></td>
                                    <td>
                                        <?php if ($status === 'Low Stock'): ?>
                                            <span class="text-danger">Low Stock (< <?php echo $prod->getMinStockLevel(); ?>)</span>
                                        <?php elseif ($status === 'Heavy Stock'): ?>
                                            <span class="text-success">Heavy Stock (> <?php echo $prod->getMaxStockLevel(); ?>)</span>
                                        <?php else: ?>
                                            <span class="text-normal">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isLoss): ?>
                                            <span class="text-danger">Loss Making</span>
                                        <?php else: ?>
                                            <span class="text-normal">Profitable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <div style="display: flex; gap: 5px;">
                                            <a href="products.php?edit=<?php echo $prod->getId(); ?>" class="btn btn-secondary" style="padding: 3px 8px; font-size: 0.85rem;">Edit</a>
                                            <form action="products.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" style="display: inline;">
                                                <?php echo csrf_token_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $prod->getId(); ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 3px 8px; font-size: 0.85rem;">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once 'includes/footer.php'; ?>
