<?php
/**
 * Test-Datei für Metropol Portal
 * 
 * Diese Datei hilft bei der Fehlersuche
 */

echo "<h1>Metropol Portal - Test</h1>";
echo "<p>Wenn Sie diese Seite sehen, funktioniert PHP!</p>";

echo "<h2>PHP-Version:</h2>";
echo "<p>" . PHP_VERSION . "</p>";

echo "<h2>Wichtige PHP-Extensions:</h2>";
$required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'];
echo "<ul>";
foreach ($required as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? "✅ Geladen" : "❌ Fehlt";
    echo "<li>$ext: $status</li>";
}
echo "</ul>";

echo "<h2>Verzeichnisstruktur:</h2>";
echo "<pre>";
$dirs = ['installer', 'lib', 'public', 'src', 'templates'];
foreach ($dirs as $dir) {
    echo $dir . ": " . (is_dir($dir) ? "✅ Vorhanden" : "❌ Fehlt") . "\n";
}
echo "</pre>";

echo "<h2>Wichtige Dateien:</h2>";
echo "<pre>";
$files = ['install.php', 'lib/Autoloader.php', 'public/index.php'];
foreach ($files as $file) {
    echo $file . ": " . (file_exists($file) ? "✅ Vorhanden" : "❌ Fehlt") . "\n";
}
echo "</pre>";

echo "<h2>Nächste Schritte:</h2>";
echo "<ol>";
echo "<li>Wenn alles grün ist, liegt es an der .htaccess</li>";
echo "<li>Löschen/Umbenennen Sie die .htaccess</li>";
echo "<li>Rufen Sie install.php direkt auf</li>";
echo "</ol>";

echo "<p><a href='install.php'>→ Zur Installation</a></p>";