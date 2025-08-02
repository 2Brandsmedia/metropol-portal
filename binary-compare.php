<?php
// Binary comparison tool
header('Content-Type: text/plain; charset=utf-8');

echo "=== BINARY FILE COMPARISON ===\n\n";

// Files to compare
$working = 'test.php';
$broken = ['test-minimal.php', 'diagnose.php', 'server-check.php'];

if (!file_exists($working)) {
    die("ERROR: $working not found!\n");
}

// Get working file info
echo "WORKING FILE: $working\n";
$workingContent = file_get_contents($working);
$workingStat = stat($working);

echo "Size: " . strlen($workingContent) . " bytes\n";
echo "MD5: " . md5($workingContent) . "\n";
echo "SHA1: " . sha1($workingContent) . "\n";
echo "Permissions: " . decoct($workingStat['mode'] & 0777) . "\n";
echo "Modified: " . date('Y-m-d H:i:s', $workingStat['mtime']) . "\n";
echo "First 50 bytes (hex): " . bin2hex(substr($workingContent, 0, 50)) . "\n";
echo "BOM check: " . (substr($workingContent, 0, 3) === "\xEF\xBB\xBF" ? "UTF-8 BOM found!" : "No BOM") . "\n";

// Check line endings
$crlf = substr_count($workingContent, "\r\n");
$lf = substr_count($workingContent, "\n") - $crlf;
echo "Line endings: CRLF=$crlf, LF=$lf\n";

// Extended attributes (if available)
if (function_exists('xattr_list')) {
    $xattrs = @xattr_list($working);
    if ($xattrs) {
        echo "Extended attributes: " . implode(', ', $xattrs) . "\n";
    }
}

echo "\n" . str_repeat('-', 50) . "\n\n";

// Compare with broken files
foreach ($broken as $file) {
    if (!file_exists($file)) {
        echo "BROKEN FILE: $file - NOT FOUND\n\n";
        continue;
    }
    
    echo "BROKEN FILE: $file\n";
    $brokenContent = file_get_contents($file);
    $brokenStat = stat($file);
    
    echo "Size: " . strlen($brokenContent) . " bytes\n";
    echo "MD5: " . md5($brokenContent) . "\n";
    echo "Permissions: " . decoct($brokenStat['mode'] & 0777) . "\n";
    echo "Modified: " . date('Y-m-d H:i:s', $brokenStat['mtime']) . "\n";
    echo "First 50 bytes (hex): " . bin2hex(substr($brokenContent, 0, 50)) . "\n";
    echo "BOM check: " . (substr($brokenContent, 0, 3) === "\xEF\xBB\xBF" ? "UTF-8 BOM found!" : "No BOM") . "\n";
    
    // Line endings
    $crlf = substr_count($brokenContent, "\r\n");
    $lf = substr_count($brokenContent, "\n") - $crlf;
    echo "Line endings: CRLF=$crlf, LF=$lf\n";
    
    // Differences
    echo "\nDIFFERENCES:\n";
    if ($workingStat['mode'] !== $brokenStat['mode']) {
        echo "- Different permissions\n";
    }
    if (substr($workingContent, 0, 10) !== substr($brokenContent, 0, 10)) {
        echo "- Different file start\n";
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

// Check HTTP headers
echo "=== HTTP HEADER CHECK ===\n";
echo "Run these commands to compare:\n";
echo "curl -I https://firmenpro.de/test.php\n";
echo "curl -I https://firmenpro.de/test-minimal.php\n";
?>