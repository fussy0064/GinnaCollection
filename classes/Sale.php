<?php

class Sale {
    private ?int $id = null;
    private string $clientName;
    private string $clientPhone;
    private int $sellerId;
    private float $totalCost = 0.0;
    private string $description = '';
    private ?string $saleDate = null;
    private array $items = []; // List of array items: ['product_id' => int, 'quantity' => int, 'unit_price' => float]

    public function __construct(string $clientName, string $clientPhone, int $sellerId, string $description = '', ?int $id = null, ?string $saleDate = null) {
        $this->clientName = trim($clientName);
        $this->clientPhone = trim($clientPhone);
        $this->sellerId = $sellerId;
        $this->description = trim($description);
        $this->id = $id;
        $this->saleDate = $saleDate;
    }

    // Encapsulation: Getters
    public function getId(): ?int { return $this->id; }
    public function getClientName(): string { return $this->clientName; }
    public function getClientPhone(): string { return $this->clientPhone; }
    public function getSellerId(): int { return $this->sellerId; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getDescription(): string { return $this->description; }
    public function getSaleDate(): ?string { return $this->saleDate; }
    public function getItems(): array { return $this->items; }

    public function addItem(int $productId, int $quantity, float $unitPrice): void {
        $this->items[] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice
        ];
        $this->totalCost += $quantity * $unitPrice;
    }

    /**
     * Commit the sale to database using transactions.
     * Decrements product inventory levels.
     */
    public function checkout(PDO $db): bool {
        if (empty($this->items)) {
            throw new Exception("Cannot process checkout: The cart is empty.");
        }

        try {
            $db->beginTransaction();

            // 1. Check stock levels with row-level locks
            foreach ($this->items as $item) {
                $stmt = $db->prepare("SELECT name, stock_quantity FROM products WHERE id = :id FOR UPDATE");
                $stmt->execute([':id' => $item['product_id']]);
                $prod = $stmt->fetch();
                if (!$prod) {
                    throw new Exception("Product ID {$item['product_id']} does not exist in inventory.");
                }
                if ($prod['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for product '{$prod['name']}'. Available: {$prod['stock_quantity']}, Requested: {$item['quantity']}.");
                }
            }

            // 2. Encrypt client details & cart description
            $encryptedName = Encryption::encrypt($this->clientName);
            $encryptedPhone = Encryption::encrypt($this->clientPhone);
            $encryptedDesc = ($this->description !== '') ? Encryption::encrypt($this->description) : null;

            // 3. Insert Sale order record
            $saleStmt = $db->prepare("INSERT INTO sales (client_name, client_phone, seller_id, total_cost, description) VALUES (:client_name, :client_phone, :seller_id, :total_cost, :description)");
            $saleStmt->execute([
                ':client_name' => $encryptedName,
                ':client_phone' => $encryptedPhone,
                ':seller_id' => $this->sellerId,
                ':total_cost' => $this->totalCost,
                ':description' => $encryptedDesc
            ]);
            $this->id = (int)$db->lastInsertId();

            // 4. Insert Sale items and decrement inventory stock levels
            $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) VALUES (:sale_id, :product_id, :quantity, :unit_price)");
            $stockUpdate = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id");

            foreach ($this->items as $item) {
                $itemStmt->execute([
                    ':sale_id' => $this->id,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price']
                ]);

                $stockUpdate->execute([
                    ':qty' => $item['quantity'],
                    ':id' => $item['product_id']
                ]);
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve sale record by ID with item lists and decrypted details.
     */
    public static function fetchById(PDO $db, int $id): ?Sale {
        $stmt = $db->prepare("SELECT s.*, u.username as seller_username FROM sales s JOIN users u ON s.seller_id = u.id WHERE s.id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // Decrypt row-level sensitive data
        $clientName = Encryption::decrypt($row['client_name']) ?? 'Decryption Error';
        $clientPhone = Encryption::decrypt($row['client_phone']) ?? 'Decryption Error';
        $description = '';
        if (!empty($row['description'])) {
            $description = Encryption::decrypt($row['description']) ?? '';
        }

        $sale = new Sale($clientName, $clientPhone, (int)$row['seller_id'], $description, (int)$row['id'], $row['sale_date']);
        $sale->totalCost = (float)$row['total_cost'];
        $sale->items = []; // Initialize items

        // Fetch related sales items
        $itemStmt = $db->prepare("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = :sale_id");
        $itemStmt->execute([':sale_id' => $id]);
        
        // Custom array storage for the items list inside sale representation
        $sale->items = $itemStmt->fetchAll();

        return $sale;
    }
}
