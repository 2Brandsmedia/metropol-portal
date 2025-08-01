<?php
// Server Check Script
header('Content-Type: text/plain');

echo "=== SERVER CHECK ===\n\n";

// PHP Info
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n";

// Server Software
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";

// Document Root
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";

// Handler Info
echo "\n=== HANDLER INFO ===\n";
foreach ($_SERVER as $key => $value) {
    if (stripos($key, 'handler') !== false || stripos($key, 'php') !== false) {
        echo "$key: $value\n";
    }
}

// Check .htaccess
echo "\n=== .HTACCESS CHECK ===\n";
if (file_exists('.htaccess')) {
    echo ".htaccess exists\n";
    echo "Size: " . filesize('.htaccess') . " bytes\n";
    echo "Content:\n";
    echo file_get_contents('.htaccess');
} else {
    echo ".htaccess NOT FOUND!\n";
}

// Check working files
echo "\n\n=== WORKING FILES ===\n";
$working = ['test.php'];
foreach ($working as $file) {
    if (file_exists($file)) {
        $stat = stat($file);
        echo "$file:\n";
        echo "  Size: " . $stat['size'] . " bytes\n";
        echo "  Permissions: " . decoct($stat['mode'] & 0777) . "\n";
        echo "  Modified: " . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
    }
}

// Check broken files
echo "\n=== BROKEN FILES ===\n";
$broken = ['index.php', 'test-minimal.php', 'diagnose.php'];
foreach ($broken as $file) {
    if (file_exists($file)) {
        $stat = stat($file);
        echo "$file:\n";
        echo "  Size: " . $stat['size'] . " bytes\n";
        echo "  Permissions: " . decoct($stat['mode'] & 0777) . "\n";
        echo "  Modified: " . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
    }
}

// Compare file headers
echo "\n=== FILE COMPARISON ===\n";
if (file_exists('test.php') && file_exists('test-minimal.php')) {
    echo "First 100 bytes of test.php:\n";
    $f = fopen('test.php', 'rb');
    echo bin2hex(fread($f, 100)) . "\n";
    fclose($f);
    
    echo "\nFirst 100 bytes of test-minimal.php:\n";
    $f = fopen('test-minimal.php', 'rb');
    echo bin2hex(fread($f, 100)) . "\n";
    fclose($f);
}

echo "\n=== END CHECK ===\n";
?>