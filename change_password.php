<?php
require_once 'config/config.php';

// Access Control: Any authenticated role can change password
$currentUser = BaseUser::requireRole(['SysAdmin', 'CEO', 'Manager', 'Seller']);
$navigation = $currentUser->getNavigationMenu();

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        try {
            // Fetch password hash from DB
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute([':id' => $currentUser->getId()]);
            $row = $stmt->fetch();

            if ($row && password_verify($currentPassword, $row['password_hash'])) {
                // Update password hash
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $updateStmt->execute([
                    ':hash' => $newHash,
                    ':id' => $currentUser->getId()
                ]);
                $success = 'Your password has been successfully updated.';
            } else {
                $error = 'Current password is incorrect.';
            }
        } catch (Exception $e) {
            $error = 'Failed to update password: ' . $e->getMessage();
        }
    }
    }
}
$pageTitle = 'Change Password - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <div style="max-width: 500px; margin: 0 auto; border: 1px solid #e5e7eb; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); background: #ffffff;">
                <h2 style="text-align: center; margin-bottom: 20px;">Change Password</h2>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="change_password.php" method="POST">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn">Update Password</button>
                    </div>
                </form>
            </div>

<?php require_once 'includes/footer.php'; ?>
