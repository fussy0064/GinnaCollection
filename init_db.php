<?php
/**
 * CLI Database Initialization & Seeding Script
 * Suitable for running as an AWS container command or manually.
 */

// If accessed via web, require a secret token or block direct web execution
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    $expectedToken = getenv('DEPLOYMENT_SECRET') ?: '';
    if (empty($expectedToken) || $token !== $expectedToken) {
        http_response_code(403);
        die("Forbidden: CLI execution only or invalid DEPLOYMENT_SECRET token.");
    }
}

require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    // Check if the users table already exists
    $checkStmt = $db->query("SHOW TABLES LIKE 'users'");
    $tablesExist = $checkStmt->rowCount() > 0;
    
    if (!$tablesExist) {
        echo "No tables found. Executing schema.sql...\n";
        
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found at: " . $schemaFile);
        }
        
        $sql = file_get_contents($schemaFile);
        
        // Execute schema initialization
        $db->exec($sql);
        echo "Schema imported successfully.\n";
        
        // Seeding initial users
        echo "Seeding users...\n";
        $seeds = [
            [
                'username' => 'admin',
                'password' => 'adminpassword',
                'role' => 'SysAdmin',
                'name' => 'System Administrator',
                'phone' => '+2551111111'
            ],
            [
                'username' => 'ceo',
                'password' => 'ceopassword',
                'role' => 'CEO',
                'name' => 'Chief Executive',
                'phone' => '+2552222222'
            ],
            [
                'username' => 'manager',
                'password' => 'managerpassword',
                'role' => 'Manager',
                'name' => 'Store Manager',
                'phone' => '+2553333333'
            ],
            [
                'username' => 'seller',
                'password' => 'sellerpassword',
                'role' => 'Seller',
                'name' => 'Shop Seller',
                'phone' => '+2554444444'
            ]
        ];
        
        foreach ($seeds as $seed) {
            $encryptedName = Encryption::encrypt($seed['name']);
            $encryptedPhone = Encryption::encrypt($seed['phone']);
            $passwordHash = password_hash($seed['password'], PASSWORD_BCRYPT);
            
            $insert = $db->prepare("INSERT INTO users (username, password_hash, role, name, phone) VALUES (:username, :password_hash, :role, :name, :phone)");
            $insert->execute([
                ':username' => $seed['username'],
                ':password_hash' => $passwordHash,
                ':role' => $seed['role'],
                ':name' => $encryptedName,
                ':phone' => $encryptedPhone
            ]);
            echo "Seeded user '{$seed['username']}' ({$seed['role']})\n";
        }
        
        // Seeding initial products
        echo "Seeding products...\n";
        $productSeeds = [
            [
                'name' => 'Floral Summer Top',
                'category' => 'Tops',
                'cost_price' => 10.00,
                'sell_price' => 25.00,
                'stock_quantity' => 20,
                'min_stock_level' => 5,
                'max_stock_level' => 50,
                'description' => 'Lightweight floral summer top'
            ],
            [
                'name' => 'Skinny High-Waist Jeans',
                'category' => 'Jeans',
                'cost_price' => 15.00,
                'sell_price' => 35.00,
                'stock_quantity' => 15,
                'min_stock_level' => 5,
                'max_stock_level' => 50,
                'description' => 'Stretch fit skinny denim jeans'
            ],
            [
                'name' => 'Hydrating Skin Serum',
                'category' => 'Skincare',
                'cost_price' => 12.00,
                'sell_price' => 30.00,
                'stock_quantity' => 40,
                'min_stock_level' => 10,
                'max_stock_level' => 80,
                'description' => 'Serum with hyaluronic acid'
            ],
            [
                'name' => 'Matte Lipstick (Ruby Red)',
                'category' => 'Makeup',
                'cost_price' => 5.00,
                'sell_price' => 15.00,
                'stock_quantity' => 2,
                'min_stock_level' => 5,
                'max_stock_level' => 40,
                'description' => 'Long lasting matte finish lipstick'
            ],
            [
                'name' => 'Leather Handbag',
                'category' => 'Bags',
                'cost_price' => 45.00,
                'sell_price' => 95.00,
                'stock_quantity' => 60,
                'min_stock_level' => 5,
                'max_stock_level' => 50,
                'description' => 'Premium genuine leather handbag'
            ]
        ];
        
        foreach ($productSeeds as $p) {
            $insert = $db->prepare("INSERT INTO products (name, category, cost_price, sell_price, stock_quantity, min_stock_level, max_stock_level, description) VALUES (:name, :category, :cost_price, :sell_price, :stock_quantity, :min_stock_level, :max_stock_level, :description)");
            $insert->execute([
                ':name' => $p['name'],
                ':category' => $p['category'],
                ':cost_price' => $p['cost_price'],
                ':sell_price' => $p['sell_price'],
                ':stock_quantity' => $p['stock_quantity'],
                ':min_stock_level' => $p['min_stock_level'],
                ':max_stock_level' => $p['max_stock_level'],
                ':description' => $p['description']
            ]);
            echo "Seeded product '{$p['name']}'\n";
        }
        
        echo "Database schema creation and seeding finished successfully.\n";
    } else {
        echo "Database tables already exist. Skipping schema import.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR during database initialization: " . $e->getMessage() . "\n";
    exit(1);
}
