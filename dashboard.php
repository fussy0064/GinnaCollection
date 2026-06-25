<?php
require_once 'config/config.php';

// Require login
$currentUser = BaseUser::requireLogin();
$navigation = $currentUser->getNavigationMenu();
$pageTitle = 'Dashboard - Ginna Beauty Collection';
?>
<?php require_once 'includes/header.php'; ?>

            <h2>Welcome to the Inventory System</h2>
            <p style="margin-top: 10px;">
                You are logged in as <strong><?php echo htmlspecialchars($currentUser->getName()); ?></strong>. 
                Your role is <strong><?php echo htmlspecialchars($currentUser->getRole()); ?></strong>, which allows you to perform the following actions:
            </p>
            
            <ul style="margin: 20px 0; padding-left: 20px;">
                <?php foreach ($navigation as $label => $link): ?>
                    <li style="margin-bottom: 15px;">
                        <a href="<?php echo htmlspecialchars($link); ?>" class="btn" style="margin-right: 15px;">
                            <?php echo htmlspecialchars($label); ?>
                        </a>
                        - Access your specialized module.
                    </li>
                <?php endforeach; ?>
            </ul>

<?php require_once 'includes/footer.php'; ?>
