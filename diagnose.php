<?php
header('Content-Type: text/plain');

echo "=== PHP Diagnose ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Server API: " . php_sapi_name() . "\n\n";

echo "=== Handler Info ===\n";
echo "Script: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Handler: " . (isset($_SERVER['REDIRECT_HANDLER']) ? $_SERVER['REDIRECT_HANDLER'] : 'nicht gesetzt') . "\n\n";

echo "=== Server Variables ===\n";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'PHP') !== false || strpos($key, 'HANDLER') !== false) {
        echo "$key = $value\n";
    }
}

echo "\n=== Loaded Extensions ===\n";
$extensions = get_loaded_extensions();
sort($extensions);
echo implode(", ", $extensions) . "\n";

echo "\n=== File Info ===\n";
echo "This file: " . __FILE__ . "\n";
echo "Permissions: " . substr(sprintf('%o', fileperms(__FILE__)), -4) . "\n";
echo "Owner: " . fileowner(__FILE__) . "\n";
echo "Group: " . filegroup(__FILE__) . "\n";

// Test ob andere PHP-Dateien existieren
echo "\n=== Andere PHP-Dateien ===\n";
$files = ['test.php', 'phpinfo.php', 'index.php', 'test-minimal.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "$file: existiert (Perms: " . substr(sprintf('%o', fileperms($file)), -4) . ")\n";
    } else {
        echo "$file: nicht gefunden\n";
    }
}

// .htaccess prüfen
echo "\n=== .htaccess Info ===\n";
if (file_exists('.htaccess')) {
    echo ".htaccess existiert\n";
    echo "Größe: " . filesize('.htaccess') . " bytes\n";
    echo "Letzte Änderung: " . date('Y-m-d H:i:s', filemtime('.htaccess')) . "\n";
    echo "\nInhalt (erste 500 Zeichen):\n";
    echo substr(file_get_contents('.htaccess'), 0, 500) . "\n";
} else {
    echo ".htaccess nicht gefunden!\n";
}
?>