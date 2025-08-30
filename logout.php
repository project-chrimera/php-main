<?php
require_once 'include.php';
require(__DIR__ . '/wordpress/wp-load.php');

// --- Destroy PHP session ---
session_start();
$_SESSION = [];
session_destroy();

// --- Log out from WordPress ---
wp_logout();

// Optional: redirect to homepage or login page
echo "<script>
    alert('You have been logged out.');
    window.location.href = '/';
</script>";
exit;
