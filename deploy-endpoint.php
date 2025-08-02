<?php
/**
 * Deploy Endpoint - Upload this file manually via KAS/FTP
 * Then use it to deploy other files
 */

// Security token (change this!)
$token = 'your-secret-deploy-token-here';

// Check token
if (!isset($_GET['token']) || $_GET['token'] !== $token) {
    die('Unauthorized');
}

// Action
$action = $_GET['action'] ?? 'info';

switch ($action) {
    case 'info':
        echo "Deploy Endpoint Ready\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Working directory: " . getcwd() . "\n";
        break;
        
    case 'test':
        // Test if we can create working PHP files
        $testFile = 'deploy-test-' . time() . '.php';
        $content = '<?php echo "Deploy test successful!"; ?>';
        
        if (file_put_contents($testFile, $content)) {
            echo "Test file created: $testFile\n";
            echo "URL: https://firmenpro.de/$testFile\n";
        } else {
            echo "Failed to create test file\n";
        }
        break;
        
    case 'fix':
        // Try to fix existing PHP files
        $files = glob('*.php');
        foreach ($files as $file) {
            if ($file === 'deploy-endpoint.php') continue;
            
            // Read content
            $content = file_get_contents($file);
            
            // Rewrite file
            if (file_put_contents($file . '.new', $content)) {
                unlink($file);
                rename($file . '.new', $file);
                chmod($file, 0644);
                echo "Fixed: $file\n";
            }
        }
        break;
        
    case 'htaccess':
        // Create minimal .htaccess
        $htaccess = "# Minimal All-Inkl Configuration\n";
        $htaccess .= "AddHandler application/x-httpd-php84 .php\n";
        $htaccess .= "DirectoryIndex index.php index.html\n";
        
        if (file_put_contents('.htaccess', $htaccess)) {
            echo ".htaccess created\n";
        }
        break;
        
    default:
        echo "Unknown action\n";
}
?>