<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Ginna Beauty Collection'); ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <!-- Site Header -->
        <header class="site-header <?php echo isset($isLoginPage) && $isLoginPage ? 'login-header' : ''; ?>">
            <div class="header-brand">
                <div class="brand-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="header-title"><a href="dashboard.php">Ginna Beauty Collection</a></h1>
                    <p class="header-tagline">Inventory Management System</p>
                </div>
            </div>
        </header>

        <?php if (isset($navigation) && !empty($navigation)): ?>
        <nav class="<?php echo (isset($isPrintPage) && $isPrintPage) ? 'no-print' : ''; ?>">
            <div class="nav-links">
                <?php foreach ($navigation as $label => $link): ?>
                    <a href="<?php echo htmlspecialchars($link); ?>" class="<?php echo (basename($_SERVER['PHP_SELF']) === basename($link)) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Top-Right dropdown profile list -->
            <div class="user-dropdown">
                <button class="dropdown-trigger">
                    <span class="user-avatar"><?php echo strtoupper(substr($currentUser->getName(), 0, 1)); ?></span>
                    <span class="user-name"><?php echo htmlspecialchars($currentUser->getName()); ?></span>
                    <code class="user-role-badge"><?php echo htmlspecialchars($currentUser->getRole()); ?></code>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($currentUser->getName()); ?></strong>
                        <small><?php echo htmlspecialchars($currentUser->getRole()); ?></small>
                    </div>
                    <div class="dropdown-divider"></div>
                        <a href="change_password.php">🔒 Change Password</a>
                    <a href="logout.php" class="dropdown-logout">🚪 Logout</a>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <main>
