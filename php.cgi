#!/usr/bin/php-cgi
<?php
// CGI Wrapper for PHP execution
// This file must have 755 permissions

// Get the requested file
$script = $_SERVER['PATH_TRANSLATED'] ?? $_SERVER['SCRIPT_FILENAME'] ?? '';

if (empty($script) || !file_exists($script)) {
    header("Status: 404 Not Found");
    echo "File not found";
    exit;
}

// Execute the PHP file
include $script;
?>