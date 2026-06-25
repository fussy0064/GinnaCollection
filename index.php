<?php
require_once 'config/config.php';

// Redirect to dashboard (which handles login redirect if unauthenticated)
header("Location: dashboard.php");
exit;
