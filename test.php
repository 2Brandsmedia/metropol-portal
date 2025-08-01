<?php
/**
 * Metropol Portal - System Check
 * Diese Datei nach erfolgreicher Installation löschen!
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metropol Portal - System Check</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            font-size: 28px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
            font-size: 20px;
        }
        .success {
            color: #27ae60;
            font-weight: bold;
            font-size: 18px;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
            font-size: 18px;
        }
        .warning {
            color: #f39c12;
            font-weight: bold;
            font-size: 18px;
        }
        code {
            background: #f8f8f8;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        ul {
            list-style: none;
            padding-left: 0;
        }
        li {
            padding: 8px 0;
            font-size: 16px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .button:hover {
            background: #2980b9;
        }
        .button.success {
            background: #27ae60;
        }
        .button.success:hover {
            background: #229954;
        }
        .info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Metropol Portal - System Check</h1>
        
        <?php
        // PHP Version
        echo "<h2>1. PHP Version:</h2>";
        echo "<p style='font-size: 16px;'>Ihre Version: <strong>" . PHP_VERSION . "</strong></p>";
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        echo $phpOk 
            ? "<p class='success'>✅ PHP 8.1+ gefunden</p>" 
            : "<p class='error'>❌ PHP zu alt! Mindestens 8.1 erforderlich</p>";

        // Wichtige Extensions
        echo "<h2>2. PHP Extensions:</h2>";
        $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'session'];
        echo "<ul>";
        foreach ($required as $ext) {
            $loaded = extension_loaded($ext);
            echo "<li>" . $ext . ": " . 
                ($loaded ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ FEHLT!</span>") . 
                "</li>";
        }
        echo "</ul>";

        // Verzeichnisstruktur
        echo "<h2>3. Verzeichnisstruktur:</h2>";
        echo "<p style='font-size: 16px;'>Aktuelles Verzeichnis: <code>" . __DIR__ . "</code></p>";
        echo "<p style='font-size: 16px;'>Gefundene Verzeichnisse:</p>";
        echo "<ul>";
        $dirs = ['public', 'lib', 'src', 'installer', 'templates', 'database', 'lang'];
        foreach ($dirs as $dir) {
            $exists = is_dir(__DIR__ . '/' . $dir);
            echo "<li>/{$dir}/ " . 
                ($exists ? "<span class='success'>✅ Vorhanden</span>" : "<span class='error'>❌ Fehlt</span>") . 
                "</li>";
        }
        echo "</ul>";

        // Wichtige Dateien
        echo "<h2>4. Wichtige Dateien:</h2>";
        echo "<ul>";
        $files = [
            'install.php' => 'Installer',
            'public/index.php' => 'Hauptdatei',
            'lib/Autoloader.php' => 'Autoloader',
            '.env' => 'Konfiguration (nach Installation)'
        ];
        foreach ($files as $file => $desc) {
            $exists = file_exists(__DIR__ . '/' . $file);
            echo "<li>{$file} ({$desc}): " . 
                ($exists ? "<span class='success'>✅ OK</span>" : "<span class='warning'>⚠️ Noch nicht vorhanden</span>") . 
                "</li>";
        }
        echo "</ul>";

        // Installation Status
        echo "<h2>5. Installation:</h2>";
        if (file_exists(__DIR__ . '/.env')) {
            echo "<p class='success'>✅ Portal ist installiert</p>";
            echo '<a href="public/" class="button success">→ Zum Portal</a>';
        } else {
            echo "<p class='warning'>⚠️ Portal ist noch nicht installiert</p>";
            echo '<a href="install.php" class="button">→ Jetzt installieren</a>';
        }
        ?>

        <div class="info">
            <strong>Hinweis:</strong> Diese Datei können Sie nach erfolgreicher Installation löschen.
        </div>
    </div>
</body>
</html>
