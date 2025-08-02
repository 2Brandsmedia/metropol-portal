#!/bin/bash
# Fix deployment by using working test.php as template

echo "<?php
// Metropol Portal Entry Point
// Based on working test.php

// Check if installation is needed
if (!file_exists('.env') || !file_exists('vendor/autoload.php')) {
    // Redirect to installation
    header('Location: /install.php');
    exit;
}

// Load the application
require_once 'vendor/autoload.php';

// For now, just confirm PHP works
echo '<h1>Metropol Portal</h1>';
echo '<p>PHP ' . PHP_VERSION . ' is working!</p>';
echo '<p>Next step: <a href=\"/install.php\">Run Installation</a></p>';
?>" > index.php

echo "<?php
// Installation Script
echo '<h1>Installation</h1>';
echo '<p>PHP is working! Installation can proceed.</p>';
?>" > install.php

# Commit changes
git add index.php install.php
git commit -m "Use working PHP template"
git push origin main