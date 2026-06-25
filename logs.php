<?php
require_once 'config/config.php';

// Access Control: SysAdmin only
$currentUser = BaseUser::requireRole(['SysAdmin']);
$navigation = $currentUser->getNavigationMenu();

$db = Database::getInstance();
$error = '';

// Filter parameters
$filterAction = $_GET['action_filter'] ?? 'all';
$filterDate = $_GET['date_filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query conditions
$conditions = [];
$params = [];

if ($filterAction !== 'all') {
    $conditions[] = "action = :action";
    $params[':action'] = $filterAction;
}

if ($filterDate !== 'all') {
    switch ($filterDate) {
        case 'today':
            $conditions[] = "DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if ($search !== '') {
    $conditions[] = "(username LIKE :search OR details LIKE :search2 OR ip_address LIKE :search3)";
    $params[':search'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Fetch logs
try {
    $stmt = $db->prepare("SELECT * FROM system_logs {$whereClause} ORDER BY created_at DESC LIMIT 200");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $logs = [];
    $error = 'Failed to fetch logs: ' . $e->getMessage();
}

// Fetch distinct action types for the filter dropdown
try {
    $actionsStmt = $db->query("SELECT DISTINCT action FROM system_logs ORDER BY action ASC");
    $availableActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $availableActions = [];
}

// Fetch active sessions: users who logged in but haven't logged out
// We approximate this by looking at users whose last activity was LOGIN_SUCCESS without a subsequent LOGOUT
try {
    $activeStmt = $db->query("
        SELECT u.id, u.username, u.role, u.name, 
               sl.created_at as last_login, sl.ip_address
        FROM users u
        INNER JOIN system_logs sl ON sl.user_id = u.id AND sl.action = 'LOGIN_SUCCESS'
        WHERE u.is_active = 1
        AND sl.created_at = (
            SELECT MAX(sl2.created_at) FROM system_logs sl2 
            WHERE sl2.user_id = u.id AND sl2.action = 'LOGIN_SUCCESS'
        )
        AND NOT EXISTS (
            SELECT 1 FROM system_logs sl3 
            WHERE sl3.user_id = u.id 
            AND sl3.action = 'LOGOUT' 
            AND sl3.created_at > sl.created_at
        )
        ORDER BY sl.created_at DESC
    ");
    $activeSessions = $activeStmt->fetchAll();
} catch (Exception $e) {
    $activeSessions = [];
}

$pageTitle = 'System Logs - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <h2>System Logs & Sessions</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Active Sessions Panel -->
            <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; margin-bottom: 25px;">
                <h3 style="margin-bottom: 15px;">🟢 Active Sessions</h3>
                <?php if (empty($activeSessions)): ?>
                    <p style="color: #6b7280; font-style: italic;">No active sessions detected.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Name</th>
                                <th>Last Login</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeSessions as $session): ?>
                                <?php $sessionName = Encryption::decrypt($session['name']) ?? 'Unknown'; ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($session['username']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($session['role']); ?></code></td>
                                    <td><?php echo htmlspecialchars($sessionName); ?></td>
                                    <td><small><?php echo htmlspecialchars($session['last_login']); ?></small></td>
                                    <td><code><?php echo htmlspecialchars($session['ip_address']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 12px; background: #ffffff; margin-bottom: 25px;">
                <form action="logs.php" method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <label style="font-weight: bold;">Filters:</label>
                    
                    <select name="action_filter" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
                        <option value="all" <?php echo $filterAction === 'all' ? 'selected' : ''; ?>>All Actions</option>
                        <?php foreach ($availableActions as $act): ?>
                            <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $filterAction === $act ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($act); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="date_filter" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
                        <option value="all" <?php echo $filterDate === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $filterDate === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $filterDate === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $filterDate === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                    
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by username, details, or IP..." 
                           style="flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
                    
                    <button type="submit" class="btn">Filter</button>
                    <?php if ($filterAction !== 'all' || $filterDate !== 'all' || $search !== ''): ?>
                        <a href="logs.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Logs Table -->
            <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff;">
                <h3 style="margin-bottom: 15px;">
                    📋 Activity Log
                    <small style="font-weight: normal; color: #6b7280;">(showing <?php echo count($logs); ?> entries, max 200)</small>
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #6b7280; padding: 20px;">No log entries found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><small><?php echo htmlspecialchars($log['created_at']); ?></small></td>
                                <td><strong><?php echo htmlspecialchars($log['username'] ?? 'system'); ?></strong></td>
                                <td>
                                    <?php
                                        $actionClass = '';
                                        $actionIcon = '📝';
                                        switch ($log['action']) {
                                            case 'LOGIN_SUCCESS':
                                                $actionClass = 'status-active';
                                                $actionIcon = '✅';
                                                break;
                                            case 'LOGIN_FAILED':
                                                $actionClass = 'status-inactive';
                                                $actionIcon = '❌';
                                                break;
                                            case 'LOGIN_BLOCKED':
                                                $actionClass = 'status-inactive';
                                                $actionIcon = '🚫';
                                                break;
                                            case 'LOGOUT':
                                                $actionIcon = '🚪';
                                                break;
                                            case 'USER_CREATED':
                                                $actionClass = 'status-active';
                                                $actionIcon = '👤';
                                                break;
                                            case 'USER_DELETED':
                                                $actionClass = 'status-inactive';
                                                $actionIcon = '🗑️';
                                                break;
                                            case 'USER_ACTIVATED':
                                                $actionClass = 'status-active';
                                                $actionIcon = '▶️';
                                                break;
                                            case 'USER_DEACTIVATED':
                                                $actionClass = 'status-inactive';
                                                $actionIcon = '⏸️';
                                                break;
                                            case 'PASSWORD_RESET':
                                                $actionIcon = '🔑';
                                                break;
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $actionClass; ?>" style="font-size: 0.8rem;">
                                        <?php echo $actionIcon; ?> <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($log['details'] ?? ''); ?></small></td>
                                <td><code style="font-size: 0.8rem;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

<?php require_once 'includes/footer.php'; ?>
