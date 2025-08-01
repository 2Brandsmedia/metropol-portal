<?php
// Temporäres Setup-Skript - NACH AUSFÜHRUNG SOFORT LÖSCHEN!
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Metropol Portal - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { background: #ffeb3b; padding: 15px; border-radius: 4px; margin: 20px 0; }
        pre { background: #f5f5f5; padding: 15px; overflow-x: auto; }
        .button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        .button:hover { background: #45a049; }
        .delete-button { background: #f44336; }
        .delete-button:hover { background: #da190b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Metropol Portal - Database Setup</h1>
        
        <?php
        $config = [
            'host' => 'localhost',
            'name' => 'd0446399',
            'user' => 'd0446399',
            'password' => '2Brands2025!'
        ];

        try {
            echo "<p>Connecting to database...</p>";
            
            $pdo = new PDO(
                "mysql:host={$config['host']};charset=utf8mb4",
                $config['user'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            echo "<p class='success'>✓ Database connection successful!</p>";
            
            // Select database
            $pdo->exec("USE `{$config['name']}`");
            echo "<p class='success'>✓ Database selected: {$config['name']}</p>";
            
            // Check if SQL file exists
            $sqlFile = dirname(__DIR__) . '/database/migrations/create_all_tables.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("SQL file not found at: $sqlFile");
            }
            
            echo "<p>Reading SQL file...</p>";
            $sql = file_get_contents($sqlFile);
            
            // Remove delimiter statements for PHP execution
            $sql = preg_replace('/DELIMITER\s+\$\$/', '', $sql);
            $sql = preg_replace('/DELIMITER\s+;/', '', $sql);
            $sql = str_replace('$$', ';', $sql);
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            echo "<p>Executing " . count($statements) . " SQL statements...</p>";
            
            $successCount = 0;
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                    try {
                        $pdo->exec($statement);
                        $successCount++;
                    } catch (PDOException $e) {
                        // Ignore "table already exists" errors
                        if ($e->getCode() != '42S01') {
                            echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
                        }
                    }
                }
            }
            
            echo "<p class='success'>✓ Executed $successCount SQL statements successfully!</p>";
            
            // Set admin password
            echo "<p>Setting admin password...</p>";
            $adminPassword = password_hash('Admin2025!', PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@firmenpro.de'");
            $stmt->execute([$adminPassword]);
            
            if ($stmt->rowCount() > 0) {
                echo "<p class='success'>✓ Admin password set successfully!</p>";
            } else {
                // Try to insert admin user if not exists
                $pdo->exec("
                    INSERT IGNORE INTO users (name, email, password, role, is_active, created_at, updated_at)
                    VALUES ('Administrator', 'admin@firmenpro.de', '$adminPassword', 'admin', 1, NOW(), NOW())
                ");
                echo "<p class='success'>✓ Admin user created successfully!</p>";
            }
            
            // Test query
            $result = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch();
            echo "<p class='success'>✓ Database setup completed! Admin users: {$result['count']}</p>";
            
            ?>
            
            <div class="warning">
                <h3>⚠️ WICHTIG - SICHERHEITSHINWEIS</h3>
                <p><strong>Löschen Sie diese Datei SOFORT nach der Ausführung!</strong></p>
                <p>Diese Datei enthält Datenbankzugangsdaten und stellt ein Sicherheitsrisiko dar.</p>
            </div>
            
            <h2>✅ Setup erfolgreich abgeschlossen!</h2>
            
            <h3>Login-Daten:</h3>
            <ul>
                <li><strong>URL:</strong> <a href="https://firmenpro.de">https://firmenpro.de</a></li>
                <li><strong>E-Mail:</strong> admin@firmenpro.de</li>
                <li><strong>Passwort:</strong> Admin2025!</li>
            </ul>
            
            <h3>Nächste Schritte:</h3>
            <ol>
                <li>Klicken Sie auf den Button unten um diese Datei zu löschen</li>
                <li>Melden Sie sich im Portal an</li>
                <li>Ändern Sie sofort das Admin-Passwort</li>
                <li>Konfigurieren Sie die E-Mail-Einstellungen</li>
                <li>Richten Sie die Cron-Jobs ein</li>
            </ol>
            
            <form method="post" action="?delete=1" onsubmit="return confirm('Diese Datei wirklich löschen?');">
                <button type="submit" class="button delete-button">🗑️ Diese Setup-Datei jetzt löschen</button>
            </form>
            
            <?php
        } catch (Exception $e) {
            echo "<p class='error'>✗ Fehler: " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        
        // Delete file if requested
        if (isset($_GET['delete'])) {
            if (unlink(__FILE__)) {
                echo "<script>alert('Datei wurde gelöscht!'); window.location.href = '/';</script>";
            } else {
                echo "<p class='error'>Konnte Datei nicht löschen. Bitte manuell via FTP löschen!</p>";
            }
        }
        ?>
    </div>
</body>
</html>