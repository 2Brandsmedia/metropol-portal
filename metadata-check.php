<?php
// Check file metadata and server environment
header('Content-Type: text/plain');

echo "=== FILE METADATA ANALYSIS ===\n\n";

// Get process info
echo "Process Info:\n";
echo "User: " . get_current_user() . "\n";
echo "UID: " . getmyuid() . "\n";
echo "GID: " . getmygid() . "\n";
echo "PID: " . getmypid() . "\n";
echo "\n";

// Check working file
$working = 'test.php';
if (file_exists($working)) {
    echo "WORKING FILE: $working\n";
    
    // Get all possible metadata
    $stat = stat($working);
    echo "Inode: " . $stat['ino'] . "\n";
    echo "Device: " . $stat['dev'] . "\n";
    echo "Links: " . $stat['nlink'] . "\n";
    echo "UID: " . $stat['uid'] . "\n";
    echo "GID: " . $stat['gid'] . "\n";
    echo "Size: " . $stat['size'] . "\n";
    echo "Blocks: " . $stat['blocks'] . "\n";
    echo "Block size: " . $stat['blksize'] . "\n";
    echo "Access time: " . date('Y-m-d H:i:s', $stat['atime']) . "\n";
    echo "Modify time: " . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
    echo "Change time: " . date('Y-m-d H:i:s', $stat['ctime']) . "\n";
    
    // Try to get more info
    if (function_exists('posix_getpwuid')) {
        $owner = posix_getpwuid($stat['uid']);
        echo "Owner name: " . $owner['name'] . "\n";
    }
    
    // Check if we can read the file
    echo "Readable: " . (is_readable($working) ? 'Yes' : 'No') . "\n";
    echo "Writable: " . (is_writable($working) ? 'Yes' : 'No') . "\n";
    echo "Executable: " . (is_executable($working) ? 'Yes' : 'No') . "\n";
}

echo "\n" . str_repeat('-', 50) . "\n\n";

// Check broken file
$broken = 'test-minimal.php';
if (file_exists($broken)) {
    echo "BROKEN FILE: $broken\n";
    
    $stat = stat($broken);
    echo "Inode: " . $stat['ino'] . "\n";
    echo "Device: " . $stat['dev'] . "\n";
    echo "UID: " . $stat['uid'] . "\n";
    echo "GID: " . $stat['gid'] . "\n";
    echo "Access time: " . date('Y-m-d H:i:s', $stat['atime']) . "\n";
    echo "Modify time: " . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
    echo "Change time: " . date('Y-m-d H:i:s', $stat['ctime']) . "\n";
}

echo "\n=== DIRECTORY LISTING ===\n";
$files = scandir('.');
foreach ($files as $file) {
    if (strpos($file, '.php') !== false) {
        $stat = stat($file);
        printf("%-30s %s %4d %4d %s\n", 
            $file, 
            decoct($stat['mode'] & 0777),
            $stat['uid'],
            $stat['gid'],
            date('Y-m-d H:i:s', $stat['mtime'])
        );
    }
}

echo "\n=== SERVER HANDLER CHECK ===\n";
// Check specific server variables
$vars = ['REDIRECT_HANDLER', 'REDIRECT_STATUS', 'HTTP_HANDLER', 'SCRIPT_NAME', 'PATH_TRANSLATED'];
foreach ($vars as $var) {
    if (isset($_SERVER[$var])) {
        echo "$var: " . $_SERVER[$var] . "\n";
    }
}
?>