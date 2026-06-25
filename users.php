<?php
require_once 'config/config.php';

// Access Control: Only SysAdmin can manage users
$currentUser = BaseUser::requireRole(['SysAdmin']);
$navigation = $currentUser->getNavigationMenu();

$error = '';
$success = '';

$db = Database::getInstance();

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($username === '' || $password === '' || $role === '' || $name === '' || $phone === '') {
        $error = 'All fields are required.';
    } elseif (!in_array($role, ['SysAdmin', 'CEO', 'Manager', 'Seller'])) {
        $error = 'Invalid role selected.';
    } else {
        try {
            // Check if username already exists
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
            $checkStmt->execute([':username' => $username]);
            if ($checkStmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                // Row-level Encryption
                $encryptedName = Encryption::encrypt($name);
                $encryptedPhone = Encryption::encrypt($phone);
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $insertStmt = $db->prepare("INSERT INTO users (username, password_hash, role, name, phone, is_active) VALUES (:username, :password_hash, :role, :name, :phone, 1)");
                $insertStmt->execute([
                    ':username' => $username,
                    ':password_hash' => $passwordHash,
                    ':role' => $role,
                    ':name' => $encryptedName,
                    ':phone' => $encryptedPhone
                ]);

                $success = 'User created successfully.';
                log_activity('USER_CREATED', "Created user: {$username} (Role: {$role})");
            }
        } catch (Exception $e) {
            $error = 'Error creating user: ' . $e->getMessage();
        }
    }
    }
}



// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $userId = (int)($_POST['id'] ?? 0);
    if ($userId === $currentUser->getId()) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            // Check if user has recorded sales to avoid breaking 3NF data integrity
            $salesCheck = $db->prepare("SELECT COUNT(*) FROM sales WHERE seller_id = :id");
            $salesCheck->execute([':id' => $userId]);
            if ($salesCheck->fetchColumn() > 0) {
                $error = 'Cannot delete user because they have recorded sales transactions. Deactivate their account instead.';
            } else {
                $deleteStmt = $db->prepare("DELETE FROM users WHERE id = :id");
                // Fetch username before deletion for logging
                $nameStmt = $db->prepare("SELECT username FROM users WHERE id = :id");
                $nameStmt->execute([':id' => $userId]);
                $deletedUser = $nameStmt->fetch();
                
                $deleteStmt = $db->prepare("DELETE FROM users WHERE id = :id");
                $deleteStmt->execute([':id' => $userId]);
                $success = 'User deleted successfully.';
                log_activity('USER_DELETED', "Deleted user: " . ($deletedUser['username'] ?? 'ID:' . $userId));
            }
        } catch (Exception $e) {
            $error = 'Error deleting user: ' . $e->getMessage();
        }
    }
    }
}

// Handle user activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $userId = (int)($_POST['id'] ?? 0);
    if ($userId === $currentUser->getId()) {
        $error = 'You cannot deactivate your own account.';
    } else {
        try {
            // Fetch current status
            $statusStmt = $db->prepare("SELECT username, is_active FROM users WHERE id = :id");
            $statusStmt->execute([':id' => $userId]);
            $targetUser = $statusStmt->fetch();
            if ($targetUser) {
                $newStatus = (int)$targetUser['is_active'] === 1 ? 0 : 1;
                $updateStmt = $db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
                $updateStmt->execute([':status' => $newStatus, ':id' => $userId]);
                $statusLabel = $newStatus === 1 ? 'activated' : 'deactivated';
                $success = "User '{$targetUser['username']}' has been {$statusLabel}.";
                log_activity('USER_' . strtoupper($statusLabel), "User '{$targetUser['username']}' {$statusLabel}");
            } else {
                $error = 'User not found.';
            }
        } catch (Exception $e) {
            $error = 'Error toggling user status: ' . $e->getMessage();
        }
    }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $userId = (int)($_POST['id'] ?? 0);
        if ($userId === $currentUser->getId()) {
            $error = 'You cannot reset your own password here. Use Change Password instead.';
        } else {
            try {
                // Fetch target user info
                $userStmt = $db->prepare("SELECT username FROM users WHERE id = :id");
                $userStmt->execute([':id' => $userId]);
                $targetUser = $userStmt->fetch();
                
                if ($targetUser) {
                    // Generate a secure temporary password
                    $tempPassword = substr(bin2hex(random_bytes(4)), 0, 8); // 8-char random password
                    $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);
                    
                    $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                    $updateStmt->execute([':hash' => $hashedPassword, ':id' => $userId]);
                    
                    $success = "Password for '{$targetUser['username']}' has been reset. Temporary password: <strong>{$tempPassword}</strong> — Please share this securely with the user.";
                    log_activity('PASSWORD_RESET', "Reset password for user: {$targetUser['username']}");
                } else {
                    $error = 'User not found.';
                }
            } catch (Exception $e) {
                $error = 'Error resetting password: ' . $e->getMessage();
            }
        }
    }
}

// Fetch search query
$search = trim($_GET['search'] ?? '');

// Fetch and filter users in memory due to row-level encryption
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
    $rawUsers = $stmt->fetchAll();
    
    $usersList = [];
    foreach ($rawUsers as $row) {
        $decryptedName = Encryption::decrypt($row['name']) ?? 'Decryption Failed';
        $decryptedPhone = Encryption::decrypt($row['phone']) ?? 'Decryption Failed';
        
        if ($search !== '') {
            $matchUsername = stripos($row['username'], $search) !== false;
            $matchName = stripos($decryptedName, $search) !== false;
            $matchPhone = stripos($decryptedPhone, $search) !== false;
            $matchRole = stripos($row['role'], $search) !== false;
            
            if (!$matchUsername && !$matchName && !$matchPhone && !$matchRole) {
                continue;
            }
        }
        
        $usersList[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'role' => $row['role'],
            'name' => $decryptedName,
            'phone' => $decryptedPhone,
            'is_active' => (int)$row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
} catch (Exception $e) {
    $usersList = [];
    $error = 'Failed to fetch users: ' . $e->getMessage();
}
$pageTitle = 'Manage Users - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <h2>User Management</h2>
            
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Search Users bar -->
            <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 12px; background: #ffffff; margin-bottom: 25px;">
                <form action="users.php" method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <label for="search" style="font-weight: bold; min-width: 100px;">Search Users:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, phone, username, or role..." style="flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <button type="submit" class="btn">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="users.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="row">
                <!-- Create User Form -->
                <div class="col" style="max-width: 380px; border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; align-self: flex-start;">
                    <h3>Add New User</h3>
                    <form action="users.php" method="POST" style="margin-top: 15px;">
                        <?php echo csrf_token_field(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="SysAdmin">SysAdmin</option>
                                <option value="CEO">CEO</option>
                                <option value="Manager">Manager</option>
                                <option value="Seller">Seller</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" required placeholder="+255...">
                        </div>
                        
                        <div style="text-align: center; margin-top: 15px;">
                            <button type="submit" class="btn">Create User</button>
                        </div>
                    </form>
                </div>

                <!-- Users List -->
                <div class="col">
                    <h3>Existing Users</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usersList)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 20px;">No users found matching query.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($usersList as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($user['role']); ?></code></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Deactivated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown" id="action-dropdown-<?php echo $user['id']; ?>">
                                            <button type="button" class="action-trigger" onclick="toggleActionMenu(<?php echo $user['id']; ?>)">
                                                ⋯
                                            </button>
                                            <div class="action-menu" id="action-menu-<?php echo $user['id']; ?>">
                                                <?php if ($user['id'] !== $currentUser->getId()): ?>
                                                    <!-- Activate/Deactivate Toggle -->
                                                    <form action="users.php" method="POST">
                                                        <?php echo csrf_token_field(); ?>
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <?php if ($user['is_active']): ?>
                                                            <button type="submit" class="action-item action-warn">⏸️ Deactivate</button>
                                                        <?php else: ?>
                                                            <button type="submit" class="action-item action-success">▶️ Activate</button>
                                                        <?php endif; ?>
                                                    </form>
                                                    <div class="action-divider"></div>
                                                    <!-- Reset Password -->
                                                    <form action="users.php" method="POST" onsubmit="return confirm('Reset password for this user? A new temporary password will be generated.');">
                                                        <?php echo csrf_token_field(); ?>
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-item action-warn">🔑 Reset Password</button>
                                                    </form>
                                                    <div class="action-divider"></div>
                                                    <!-- Delete -->
                                                    <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                                        <?php echo csrf_token_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-item action-danger">🗑️ Delete User</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="action-item action-disabled">Self — No Delete</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<script>
function toggleActionMenu(userId) {
    // Close all other open menus first
    document.querySelectorAll('.action-menu.open').forEach(function(menu) {
        if (menu.id !== 'action-menu-' + userId) {
            menu.classList.remove('open');
        }
    });
    // Toggle the clicked menu
    var menu = document.getElementById('action-menu-' + userId);
    menu.classList.toggle('open');
}

// Close all action menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-menu.open').forEach(function(menu) {
            menu.classList.remove('open');
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
