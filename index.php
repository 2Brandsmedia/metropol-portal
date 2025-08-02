<?php
/**
 * Metropol Portal - Entry Point
 * 
 * @author 2Brands Media GmbH
 */

// Test ob PHP funktioniert
echo "PHP " . PHP_VERSION . " funktioniert!<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Zeit: " . date('Y-m-d H:i:s') . "<br><br>";

// Prüfen ob Installation nötig
if (!file_exists(__DIR__ . '/.env')) {
    echo '<h2>Installation erforderlich</h2>';
    echo '<p>Die Anwendung ist noch nicht installiert.</p>';
    echo '<p><a href="/install.php">Installation starten</a></p>';
} else {
    echo '<h2>Anwendung bereit</h2>';
    echo '<p><a href="/public/">Zur Anwendung</a></p>';
}
?>