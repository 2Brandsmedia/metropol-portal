<?php

return [
    // Steps
    'steps' => [
        'language' => 'Select Language',
        'requirements' => 'System Requirements',
        'database' => 'Database',
        'admin' => 'Administrator',
        'config' => 'Configuration',
        'install' => 'Installation'
    ],
    
    // Buttons
    'next' => 'Next',
    'back' => 'Back',
    'test_connection' => 'Test Connection',
    'install_now' => 'Install Now',
    'go_to_app' => 'Go to Application',
    
    // Step 1 - Language
    'welcome' => 'Welcome to Metropol Portal Installer',
    'select_language' => 'Please select your preferred language:',
    
    // Step 2 - Requirements
    'checking_requirements' => 'Checking system requirements...',
    'all_requirements_met' => 'All system requirements are met!',
    'requirements_not_met' => 'Some system requirements are not met.',
    'please_fix_errors' => 'Please fix the errors before continuing.',
    
    // Step 3 - Database
    'database_configuration' => 'Database Configuration',
    'database_host' => 'Database Host',
    'database_port' => 'Port',
    'database_name' => 'Database Name',
    'database_user' => 'Username',
    'database_pass' => 'Password',
    'database_help' => 'You can get this information from your hosting provider.',
    
    // Step 4 - Admin
    'create_admin' => 'Create Administrator Account',
    'admin_username' => 'Username',
    'admin_email' => 'Email Address',
    'admin_password' => 'Password',
    'admin_password_confirm' => 'Confirm Password',
    'password_requirements' => 'At least 8 characters, uppercase/lowercase and numbers',
    
    // Step 5 - Configuration
    'basic_configuration' => 'Basic Configuration',
    'site_name' => 'Site Name',
    'timezone' => 'Timezone',
    'api_configuration' => 'API Configuration (optional)',
    'google_maps_key' => 'Google Maps API Key',
    'ors_api_key' => 'OpenRouteService API Key',
    'api_keys_optional' => 'API keys can be configured later.',
    
    // Step 6 - Installation
    'ready_to_install' => 'Ready to Install',
    'installation_summary' => 'Installation Summary',
    'installing' => 'Installing...',
    'installation_complete' => 'Installation Complete!',
    'installation_success_message' => 'Metropol Portal has been successfully installed.',
    'important_security' => 'Important: The installer has been disabled for security reasons.',
    
    // Errors
    'error_database_connection' => 'Database connection failed',
    'error_database_exists' => 'Database does not exist',
    'error_file_permissions' => 'No write permissions',
    'error_php_version' => 'PHP version too old',
    'error_missing_extension' => 'PHP extension missing',
    
    // Success
    'success_connection' => 'Connection successful!',
    'success_requirements' => 'All requirements met',
    'success_installation' => 'Installation complete'
];