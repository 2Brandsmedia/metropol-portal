<?php
/**
 * Installation Check für firmenpro.de
 * Zeigt an, ob die Installation erforderlich ist
 * 
 * @author 2Brands Media GmbH
 */

// Fehler anzeigen für Debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Check - Metropol Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-8 px-4">
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Metropol Portal - Installation Check</h1>
            
            <?php
            $checks = [];
            
            // PHP Version
            $php_version = PHP_VERSION;
            $php_ok = version_compare($php_version, '8.0.0', '>=');
            $checks[] = [
                'name' => 'PHP Version',
                'status' => $php_ok,
                'info' => "PHP $php_version" . ($php_ok ? '' : ' (Minimum: 8.0)')
            ];
            
            // Required Extensions
            $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'curl'];
            foreach ($extensions as $ext) {
                $checks[] = [
                    'name' => "PHP Extension: $ext",
                    'status' => extension_loaded($ext),
                    'info' => extension_loaded($ext) ? 'Installiert' : 'Fehlt'
                ];
            }
            
            // File Checks
            $files = [
                '.env' => 'Umgebungskonfiguration',
                'vendor/autoload.php' => 'Composer Dependencies',
                'public/index.php' => 'Hauptanwendung'
            ];
            
            foreach ($files as $file => $desc) {
                $exists = file_exists(__DIR__ . '/' . $file);
                $checks[] = [
                    'name' => $desc,
                    'status' => $exists,
                    'info' => $exists ? 'Vorhanden' : 'Fehlt'
                ];
            }
            
            // Directory Permissions
            $dirs = ['logs', 'storage', 'public/uploads'];
            foreach ($dirs as $dir) {
                $path = __DIR__ . '/' . $dir;
                $exists = file_exists($path);
                $writable = $exists && is_writable($path);
                $checks[] = [
                    'name' => "Verzeichnis: $dir",
                    'status' => $writable,
                    'info' => !$exists ? 'Fehlt' : ($writable ? 'Beschreibbar' : 'Nicht beschreibbar')
                ];
            }
            
            // Installation Status
            $installed = file_exists(__DIR__ . '/.env') && file_exists(__DIR__ . '/vendor/autoload.php');
            ?>
            
            <div class="space-y-4">
                <?php foreach ($checks as $check): ?>
                <div class="flex items-center justify-between p-4 border rounded-lg <?php echo $check['status'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                    <div>
                        <span class="font-semibold"><?php echo htmlspecialchars($check['name']); ?></span>
                        <span class="text-sm text-gray-600 ml-2"><?php echo htmlspecialchars($check['info']); ?></span>
                    </div>
                    <div>
                        <?php if ($check['status']): ?>
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-8 p-4 rounded-lg <?php echo $installed ? 'bg-green-100' : 'bg-yellow-100'; ?>">
                <h2 class="text-xl font-semibold mb-2">
                    <?php echo $installed ? '✅ Portal ist installiert' : '⚠️ Installation erforderlich'; ?>
                </h2>
                <?php if (!$installed): ?>
                    <p class="text-gray-700 mb-4">Das Metropol Portal muss noch installiert werden.</p>
                    <a href="/install.php" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700">
                        Installation starten
                    </a>
                <?php else: ?>
                    <p class="text-gray-700 mb-4">Das Portal ist installiert und sollte funktionieren.</p>
                    <div class="space-x-4">
                        <a href="/public/" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700">
                            Zur Anwendung
                        </a>
                        <a href="/debug.php" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700">
                            Debug-Info
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-8 text-sm text-gray-500 text-center">
                <p>Entwickelt von 2Brands Media GmbH</p>
                <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
            </div>
        </div>
    </div>
</body>
</html>