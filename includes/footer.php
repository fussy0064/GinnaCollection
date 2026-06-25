        </main>

        <!-- Site Footer -->
        <footer class="site-footer <?php echo (isset($isPrintPage) && $isPrintPage) ? 'no-print' : ''; ?>">
            <div class="footer-content">
                <div class="footer-brand">
                    <strong>Ginna Beauty Collection</strong>
                    <span class="footer-separator">•</span>
                    <span>Inventory Management System</span>
                </div>
                <div class="footer-links">
                    <a href="dashboard.php">Dashboard</a>
                    <span class="footer-separator">•</span>
                    <a href="change_password.php">Account</a>
                    <span class="footer-separator">•</span>
                    <a href="logout.php">Logout</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> Ginna Beauty Collection. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
