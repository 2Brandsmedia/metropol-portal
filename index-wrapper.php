<?php
// Index Wrapper - nutzt die funktionierende test.php
if (file_exists('test.php')) {
    // test.php existiert und funktioniert - nutzen wir das
    $_SERVER['SCRIPT_NAME'] = '/test.php';
    $_SERVER['PHP_SELF'] = '/test.php';
    include 'test.php';
    exit;
}

// Fallback
echo "Installation erforderlich";
?>