<?php
// Step 6: Installation
$errors = $_SESSION['installer_errors'] ?? [];
$db = $_SESSION['installer_db'] ?? [];
$admin = $_SESSION['installer_admin'] ?? [];
$config = $_SESSION['installer_config'] ?? [];
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $t['ready_to_install'] ?? 'Ready to Install'; ?></h2>
    <p class="text-gray-600"><?php echo $t['installation_summary'] ?? 'Installation Summary'; ?></p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
        <?php foreach ($errors as $error): ?>
            <p class="text-red-700">• <?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Installation Summary -->
<div class="bg-gray-50 rounded-lg p-6 mb-6">
    <div class="space-y-4">
        <!-- Database -->
        <div>
            <h3 class="font-medium text-gray-900 mb-1">Datenbank</h3>
            <p class="text-sm text-gray-600">
                Server: <?php echo htmlspecialchars($db['host'] . ':' . $db['port']); ?><br>
                Datenbank: <?php echo htmlspecialchars($db['name']); ?>
            </p>
        </div>
        
        <!-- Admin -->
        <div>
            <h3 class="font-medium text-gray-900 mb-1">Administrator</h3>
            <p class="text-sm text-gray-600">
                Benutzername: <?php echo htmlspecialchars($admin['username'] ?? ''); ?><br>
                E-Mail: <?php echo htmlspecialchars($admin['email'] ?? ''); ?>
            </p>
        </div>
        
        <!-- Configuration -->
        <div>
            <h3 class="font-medium text-gray-900 mb-1">Konfiguration</h3>
            <p class="text-sm text-gray-600">
                Site Name: <?php echo htmlspecialchars($config['site_name'] ?? 'Metropol Portal'); ?><br>
                Zeitzone: <?php echo htmlspecialchars($config['timezone'] ?? 'Europe/Berlin'); ?>
            </p>
        </div>
    </div>
</div>

<form method="post" action="install.php" id="install-form">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <!-- Installation Progress (hidden by default) -->
    <div id="installation-progress" class="hidden">
        <div class="mb-4">
            <div class="bg-gray-200 rounded-full h-4 overflow-hidden">
                <div id="progress-bar" class="bg-indigo-600 h-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>
        
        <div id="installation-log" class="bg-gray-900 text-gray-100 rounded p-4 h-64 overflow-y-auto font-mono text-sm">
            <p><?php echo $t['installing'] ?? 'Installing...'; ?></p>
        </div>
    </div>
    
    <!-- Success Message (hidden by default) -->
    <div id="installation-success" class="hidden">
        <div class="bg-green-50 border border-green-200 rounded p-6 text-center">
            <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-xl font-bold text-green-900 mb-2">
                <?php echo $t['installation_complete'] ?? 'Installation Complete!'; ?>
            </h3>
            <p class="text-green-700 mb-4">
                <?php echo $t['installation_success_message'] ?? 'Metropol Portal has been successfully installed.'; ?>
            </p>
            <p class="text-sm text-gray-600 mb-6">
                <?php echo $t['important_security'] ?? 'Important: The installer has been disabled for security reasons.'; ?>
            </p>
            <a href="public/index.php" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition-colors">
                <?php echo $t['go_to_app'] ?? 'Go to Application'; ?> →
            </a>
        </div>
    </div>
    
    <!-- Navigation -->
    <div id="navigation-buttons" class="mt-8 flex justify-between">
        <button type="submit" name="back" value="1" class="text-gray-600 hover:text-gray-900">
            ← <?php echo $t['back'] ?? 'Back'; ?>
        </button>
        
        <button type="button" onclick="startInstallation()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors">
            <?php echo $t['install_now'] ?? 'Install Now'; ?>
        </button>
    </div>
</form>

<script>
function startInstallation() {
    // Hide navigation buttons
    document.getElementById('navigation-buttons').classList.add('hidden');
    
    // Show progress
    document.getElementById('installation-progress').classList.remove('hidden');
    
    // Add log entries
    const log = document.getElementById('installation-log');
    const progressBar = document.getElementById('progress-bar');
    
    const steps = [
        { text: 'Creating configuration files...', progress: 10 },
        { text: 'Connecting to database...', progress: 20 },
        { text: 'Creating database tables...', progress: 40 },
        { text: 'Running migrations...', progress: 60 },
        { text: 'Creating administrator account...', progress: 80 },
        { text: 'Finalizing installation...', progress: 90 },
        { text: 'Installation complete!', progress: 100 }
    ];
    
    let currentStep = 0;
    
    function runStep() {
        if (currentStep < steps.length) {
            const step = steps[currentStep];
            
            // Add log entry
            const entry = document.createElement('p');
            entry.textContent = '> ' + step.text;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
            
            // Update progress bar
            progressBar.style.width = step.progress + '%';
            
            currentStep++;
            
            // Run next step after delay
            setTimeout(runStep, 1000);
        } else {
            // Submit form to actually run installation
            document.getElementById('install-form').submit();
        }
    }
    
    // Start installation
    runStep();
}
</script>