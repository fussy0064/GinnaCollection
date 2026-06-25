<?php
require_once 'config/config.php';

// Access Control: Seller only
$currentUser = BaseUser::requireRole(['Seller']);
$navigation = $currentUser->getNavigationMenu();

$db = Database::getInstance();
$error = '';
$success = '';

// Initialize cart session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $cartAction = $_POST['cart_action'];

    if ($cartAction === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($productId <= 0 || $quantity <= 0) {
            $error = 'Invalid product or quantity.';
        } else {
            $product = Product::fetchById($db, $productId);
            if ($product) {
                // Check if already in cart
                $currentQtyInCart = isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId]['quantity'] : 0;
                $newQty = $currentQtyInCart + $quantity;

                if ($product->getStockQuantity() < $newQty) {
                    $error = "Insufficient stock for '{$product->getName()}'. Only {$product->getStockQuantity()} available.";
                } else {
                    $_SESSION['cart'][$productId] = [
                        'name' => $product->getName(),
                        'quantity' => $newQty,
                        'unit_price' => $product->getSellPrice()
                    ];
                    $success = "'{$product->getName()}' added to cart.";
                }
            } else {
                $error = 'Product not found.';
            }
        }
    } elseif ($cartAction === 'remove') {
        $productId = (int)($_POST['product_id'] ?? 0);
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            $success = 'Item removed from cart.';
        }
    } elseif ($cartAction === 'clear') {
        $_SESSION['cart'] = [];
        $success = 'Cart cleared.';
    } elseif ($cartAction === 'checkout') {
        $clientName = trim($_POST['client_name'] ?? '');
        $clientPhone = trim($_POST['client_phone'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($clientName === '' || $clientPhone === '') {
            $error = 'Client Name and Client Phone are required.';
        } elseif (empty($_SESSION['cart'])) {
            $error = 'Cart is empty. Please add items before checking out.';
        } else {
            try {
                // Create Sale instance with cart description
                $sale = new Sale($clientName, $clientPhone, $currentUser->getId(), $description);
                
                foreach ($_SESSION['cart'] as $pId => $item) {
                    $sale->addItem((int)$pId, (int)$item['quantity'], (float)$item['unit_price']);
                }

                // Process transaction
                if ($sale->checkout($db)) {
                    $_SESSION['cart'] = []; // Clear cart
                    header("Location: receipt.php?id=" . $sale->getId());
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Checkout failed: ' . $e->getMessage();
            }
        }
    }
    }
}

// Fetch available products with stock > 0
try {
    $stmt = $db->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name ASC");
    $availableProducts = [];
    while ($row = $stmt->fetch()) {
        $availableProducts[] = $row;
    }
} catch (Exception $e) {
    $availableProducts = [];
    $error = 'Failed to fetch available products.';
}
$pageTitle = 'Create Sale - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <h2>Point of Sale (POS)</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Add Items to Cart Form -->
                <div class="col" style="max-width: 360px; border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff;">
                    <h3>Add Item to Cart</h3>
                    <form action="sales.php" method="POST" style="margin-top: 15px;">
                        <?php echo csrf_token_field(); ?>
                        <input type="hidden" name="cart_action" value="add">
                        
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select id="product_id" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php foreach ($availableProducts as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>">
                                        <?php echo htmlspecialchars($prod['name']); ?> 
                                        (Tshs <?php echo number_format($prod['sell_price'], 2); ?>) 
                                        - Stock: <?php echo $prod['stock_quantity']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                        </div>

                        <div style="text-align: center; margin-top: 10px;">
                            <button type="submit" class="btn">Add to Cart</button>
                        </div>
                    </form>
                </div>

                <!-- Cart List & Client Info Form -->
                <div class="col">
                    <h3>Current Shopping Cart</h3>
                    <?php if (empty($_SESSION['cart'])): ?>
                        <p style="margin: 20px 0; font-style: italic;">The cart is currently empty.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $cartTotal = 0;
                                    foreach ($_SESSION['cart'] as $pId => $item): 
                                        $subtotal = $item['quantity'] * $item['unit_price'];
                                        $cartTotal += $subtotal;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>Tshs <?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>Tshs <?php echo number_format($subtotal, 2); ?></td>
                                        <td>
                                            <form action="sales.php" method="POST" style="display:inline;">
                                                <?php echo csrf_token_field(); ?>
                                                <input type="hidden" name="cart_action" value="remove">
                                                <input type="hidden" name="product_id" value="<?php echo $pId; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 2px 6px; font-size: 0.8rem;">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="3" style="text-align: right; font-weight: bold;">Total:</td>
                                    <td colspan="2" style="font-weight: bold;">Tshs <?php echo number_format($cartTotal, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <form action="sales.php" method="POST" style="margin-top: 20px; border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff;">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="cart_action" value="checkout">
                            <h3>Checkout Details</h3>
                            
                            <div class="form-group" style="margin-top: 15px;">
                                <label for="client_name">Client Name *</label>
                                <input type="text" id="client_name" name="client_name" required placeholder="e.g. Mary Jane">
                            </div>

                            <div class="form-group">
                                <label for="client_phone">Client Phone *</label>
                                <input type="text" id="client_phone" name="client_phone" required placeholder="e.g. +255712345678">
                            </div>

                            <div class="form-group">
                                <label for="description">Cart Description / Notes</label>
                                <textarea id="description" name="description" placeholder="e.g. Custom tags, specific color request, or gift notes" rows="3"></textarea>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-start;">
                                <button type="submit" class="btn">Complete Checkout</button>
                            </div>
                        </form>
                        
                        <!-- Separate form for Clear Cart to avoid form field conflicts -->
                        <form action="sales.php" method="POST" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to clear the entire cart?');">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="cart_action" value="clear">
                            <button type="submit" class="btn btn-danger">Clear Cart</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

<?php require_once 'includes/footer.php'; ?>
