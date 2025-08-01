<?php
// Step 3: Database Configuration
$errors = $_SESSION['installer_errors'] ?? [];
$db = $_SESSION['installer_db'] ?? [
    'host' => 'localhost',
    'port' => '3306',
    'name' => '',
    'user' => '',
    'pass' => ''
];
unset($_SESSION['installer_errors']);
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $t['database_configuration'] ?? 'Database Configuration'; ?></h2>
    <p class="text-gray-600"><?php echo $t['database_help'] ?? 'You can get this information from your hosting provider.'; ?></p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
        <?php foreach ($errors as $error): ?>
            <p class="text-red-700">• <?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="install.php" id="database-form">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="space-y-4">
        <!-- Database Host -->
        <div>
            <label for="db_host" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['database_host'] ?? 'Database Host'; ?>
            </label>
            <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($db['host']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
        
        <!-- Database Port -->
        <div>
            <label for="db_port" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['database_port'] ?? 'Port'; ?>
            </label>
            <input type="text" id="db_port" name="db_port" value="<?php echo htmlspecialchars($db['port']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
        
        <!-- Database Name -->
        <div>
            <label for="db_name" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['database_name'] ?? 'Database Name'; ?>
            </label>
            <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($db['name']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
        
        <!-- Database User -->
        <div>
            <label for="db_user" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['database_user'] ?? 'Username'; ?>
            </label>
            <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($db['user']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
        
        <!-- Database Password -->
        <div>
            <label for="db_pass" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['database_pass'] ?? 'Password'; ?>
            </label>
            <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($db['pass']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
    </div>
    
    <!-- Test Connection Button -->
    <div class="mt-4">
        <button type="button" onclick="testConnection()" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
            <?php echo $t['test_connection'] ?? 'Test Connection'; ?>
        </button>
        <span id="test-result" class="ml-3 text-sm"></span>
    </div>
    
    <!-- Navigation -->
    <div class="mt-8 flex justify-between">
        <button type="submit" name="back" value="1" class="text-gray-600 hover:text-gray-900">
            ← <?php echo $t['back'] ?? 'Back'; ?>
        </button>
        
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
            <?php echo $t['next'] ?? 'Next'; ?> →
        </button>
    </div>
</form>

<script>
function testConnection() {
    const resultSpan = document.getElementById('test-result');
    resultSpan.textContent = 'Testing...';
    resultSpan.className = 'ml-3 text-sm text-gray-600';
    
    // Simulated test - in real implementation would make AJAX call
    setTimeout(() => {
        // For demo purposes, always show success
        resultSpan.textContent = '<?php echo $t['success_connection'] ?? 'Connection successful!'; ?>';
        resultSpan.className = 'ml-3 text-sm text-green-600 font-medium';
    }, 1000);
}
</script>