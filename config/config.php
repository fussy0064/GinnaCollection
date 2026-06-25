<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden");
}

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ginna_beauty_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');

// Row-level Encryption Key (32-byte string)
define('ENCRYPTION_KEY', 'GinnaBeautySecretKey2026Base32Chr!');

// OOP Class Autoloader
spl_autoload_register(function ($class_name) {
    $file = dirname(__DIR__) . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// CSRF Protection Helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// System Activity Logging
function log_activity(string $action, string $details = '', ?int $userId = null, ?string $username = null): void {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO system_logs (user_id, username, action, details, ip_address) VALUES (:user_id, :username, :action, :details, :ip)");
        $stmt->execute([
            ':user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
            ':username' => $username ?? ($_SESSION['log_username'] ?? 'system'),
            ':action' => $action,
            ':details' => $details,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
