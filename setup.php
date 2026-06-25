<?php
require_once 'config/config.php';

// Security: Only SysAdmin can run the seeder
$currentUser = BaseUser::requireRole(['SysAdmin']);

try {
    $db = Database::getInstance();
    
    // Define seed users
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
    
    echo "Seeding users...\n";
    
    foreach ($seeds as $seed) {
        // Check if user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $seed['username']]);
        if ($stmt->fetch()) {
            echo "User '{$seed['username']}' already exists. Skipping.\n";
            continue;
        }
        
        // Encrypt fields
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
        
    }

    // Define seed products
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
            'stock_quantity' => 2, // Starts at low stock for visual testing
            'min_stock_level' => 5,
            'max_stock_level' => 40,
            'description' => 'Long lasting matte finish lipstick'
        ],
        [
            'name' => 'Leather Handbag',
            'category' => 'Bags',
            'cost_price' => 45.00,
            'sell_price' => 95.00,
            'stock_quantity' => 60, // Starts at heavy stock for visual testing
            'min_stock_level' => 5,
            'max_stock_level' => 50,
            'description' => 'Premium genuine leather handbag'
        ]
    ];

    echo "Seeding products...\n";

    foreach ($productSeeds as $p) {
        // Check if product exists
        $stmt = $db->prepare("SELECT id FROM products WHERE name = :name");
        $stmt->execute([':name' => $p['name']]);
        if ($stmt->fetch()) {
            echo "Product '{$p['name']}' already exists. Skipping.\n";
            continue;
        }

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
        echo "Created product '{$p['name']}'.\n";
    }
    
    echo "Database seeding completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error during setup: " . $e->getMessage() . "\n";
}

