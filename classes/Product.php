<?php

class Product {
    private ?int $id = null;
    private string $name;
    private string $category; // Tops, Jeans, Shoes, Skincare, Makeup, Bags, Jewelry
    private ?string $description = null;
    private float $cost_price;
    private float $sell_price;
    private int $stock_quantity;
    private int $min_stock_level;
    private int $max_stock_level;

    public function __construct(
        string $name,
        string $category,
        float $cost_price,
        float $sell_price,
        int $stock_quantity = 0,
        int $min_stock_level = 5,
        int $max_stock_level = 50,
        ?string $description = null,
        ?int $id = null
    ) {
        $this->name = $name;
        $this->setCategory($category);
        $this->cost_price = $cost_price;
        $this->sell_price = $sell_price;
        $this->stock_quantity = $stock_quantity;
        $this->min_stock_level = $min_stock_level;
        $this->max_stock_level = $max_stock_level;
        $this->description = $description;
        $this->id = $id;
    }

    // Encapsulation: Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getCategory(): string { return $this->category; }
    public function getDescription(): ?string { return $this->description; }
    public function getCostPrice(): float { return $this->cost_price; }
    public function getSellPrice(): float { return $this->sell_price; }
    public function getStockQuantity(): int { return $this->stock_quantity; }
    public function getMinStockLevel(): int { return $this->min_stock_level; }
    public function getMaxStockLevel(): int { return $this->max_stock_level; }

    // Encapsulation: Setters
    public function setName(string $name): void { 
        $this->name = trim($name); 
    }
    
    public function setCategory(string $category): void {
        $allowed = ['Tops', 'Jeans', 'Shoes', 'Skincare', 'Makeup', 'Bags', 'Jewelry'];
        if (!in_array($category, $allowed)) {
            throw new InvalidArgumentException("Invalid category: " . htmlspecialchars($category));
        }
        $this->category = $category;
    }
    
    public function setDescription(?string $description): void { 
        $this->description = $description; 
    }
    
    public function setCostPrice(float $cost_price): void { 
        $this->cost_price = $cost_price; 
    }
    
    public function setSellPrice(float $sell_price): void { 
        $this->sell_price = $sell_price; 
    }
    
    public function setStockQuantity(int $stock_quantity): void { 
        $this->stock_quantity = $stock_quantity; 
    }
    
    public function setMinStockLevel(int $min_stock_level): void { 
        $this->min_stock_level = $min_stock_level; 
    }
    
    public function setMaxStockLevel(int $max_stock_level): void { 
        $this->max_stock_level = $max_stock_level; 
    }

    // Business Logic
    public function getStockStatus(): string {
        if ($this->stock_quantity < $this->min_stock_level) {
            return 'Low Stock';
        } elseif ($this->stock_quantity > $this->max_stock_level) {
            return 'Heavy Stock';
        }
        return 'Normal';
    }

    public function isLossMaking(): bool {
        return $this->sell_price < $this->cost_price;
    }

    // Database Actions
    public static function fetchAll(PDO $db): array {
        $stmt = $db->query("SELECT * FROM products ORDER BY name ASC");
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = self::fromRow($row);
        }
        return $results;
    }

    public static function fetchById(PDO $db, int $id): ?Product {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? self::fromRow($row) : null;
    }

    private static function fromRow(array $row): Product {
        return new Product(
            $row['name'],
            $row['category'],
            (float)$row['cost_price'],
            (float)$row['sell_price'],
            (int)$row['stock_quantity'],
            (int)$row['min_stock_level'],
            (int)$row['max_stock_level'],
            $row['description'],
            (int)$row['id']
        );
    }

    public function save(PDO $db): bool {
        if ($this->id !== null) {
            // Update
            $stmt = $db->prepare("UPDATE products SET name = :name, category = :category, description = :description, cost_price = :cost_price, sell_price = :sell_price, stock_quantity = :stock_quantity, min_stock_level = :min_stock_level, max_stock_level = :max_stock_level WHERE id = :id");
            return $stmt->execute([
                ':name' => $this->name,
                ':category' => $this->category,
                ':description' => $this->description,
                ':cost_price' => $this->cost_price,
                ':sell_price' => $this->sell_price,
                ':stock_quantity' => $this->stock_quantity,
                ':min_stock_level' => $this->min_stock_level,
                ':max_stock_level' => $this->max_stock_level,
                ':id' => $this->id
            ]);
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO products (name, category, description, cost_price, sell_price, stock_quantity, min_stock_level, max_stock_level) VALUES (:name, :category, :description, :cost_price, :sell_price, :stock_quantity, :min_stock_level, :max_stock_level)");
            $res = $stmt->execute([
                ':name' => $this->name,
                ':category' => $this->category,
                ':description' => $this->description,
                ':cost_price' => $this->cost_price,
                ':sell_price' => $this->sell_price,
                ':stock_quantity' => $this->stock_quantity,
                ':min_stock_level' => $this->min_stock_level,
                ':max_stock_level' => $this->max_stock_level
            ]);
            if ($res) {
                $this->id = (int)$db->lastInsertId();
            }
            return $res;
        }
    }

    public function delete(PDO $db): bool {
        if ($this->id === null) return false;
        $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }
}
