<?php
require_once 'config/config.php';

// If already logged in, redirect to dashboard
if (BaseUser::getLoggedInUser() !== null) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $userRow = $stmt->fetch();
            
            if ($userRow && password_verify($password, $userRow['password_hash'])) {
                if ((int)$userRow['is_active'] === 0) {
                    $error = 'Your account is deactivated. Please contact the administrator.';
                    log_activity('LOGIN_BLOCKED', 'Deactivated account login attempt', (int)$userRow['id'], $userRow['username']);
                } else {
                    // Secure Session Management: Regenerate session ID
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userRow['id'];
                    $_SESSION['user_role'] = $userRow['role'];
                    $_SESSION['log_username'] = $userRow['username'];
                    
                    log_activity('LOGIN_SUCCESS', 'User logged in successfully', (int)$userRow['id'], $userRow['username']);
                    
                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
                log_activity('LOGIN_FAILED', 'Invalid credentials for username: ' . $username, null, $username);
            }
        } catch (Exception $e) {
            $error = 'Authentication failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
        }
    }
}
$pageTitle = 'Login - Ginna Beauty Collection';
$isLoginPage = true;
?>
<?php require_once 'includes/header.php'; ?>

        <div class="login-container">
            <h2 style="text-align: center; margin-bottom: 20px;">Secure Login</h2>
            
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <?php echo csrf_token_field(); ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="submit" class="btn">Login</button>
                </div>
            </form>
        </div>

<?php require_once 'includes/footer.php'; ?>
