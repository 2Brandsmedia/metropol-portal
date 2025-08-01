<?php
// Step 4: Administrator Account
$errors = $_SESSION['installer_errors'] ?? [];
$admin = $_SESSION['installer_admin_form'] ?? [
    'username' => '',
    'email' => '',
];
unset($_SESSION['installer_errors']);
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $t['create_admin'] ?? 'Create Administrator Account'; ?></h2>
    <p class="text-gray-600"><?php echo $t['password_requirements'] ?? 'At least 8 characters, uppercase/lowercase and numbers'; ?></p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
        <?php foreach ($errors as $error): ?>
            <p class="text-red-700">• <?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="install.php" id="admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="space-y-4">
        <!-- Username -->
        <div>
            <label for="admin_user" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['admin_username'] ?? 'Username'; ?>
            </label>
            <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($admin['username']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
        
        <!-- Email -->
        <div>
            <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['admin_email'] ?? 'Email Address'; ?>
            </label>
            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($admin['email']); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
        
        <!-- Password -->
        <div>
            <label for="admin_pass" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['admin_password'] ?? 'Password'; ?>
            </label>
            <input type="password" id="admin_pass" name="admin_pass" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required minlength="8">
            <div class="mt-1 text-xs text-gray-500">
                <div id="password-strength" class="flex space-x-1 mt-1">
                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>
        
        <!-- Confirm Password -->
        <div>
            <label for="admin_pass_confirm" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo $t['admin_password_confirm'] ?? 'Confirm Password'; ?>
            </label>
            <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>
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
// Password strength indicator
document.getElementById('admin_pass').addEventListener('input', function(e) {
    const password = e.target.value;
    const strength = calculatePasswordStrength(password);
    const bars = document.querySelectorAll('#password-strength > div');
    
    bars.forEach((bar, index) => {
        if (index < strength.score) {
            bar.className = `h-1 w-full rounded ${strength.color}`;
        } else {
            bar.className = 'h-1 w-full bg-gray-200 rounded';
        }
    });
});

function calculatePasswordStrength(password) {
    let score = 0;
    
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password) && /[^a-zA-Z\d]/.test(password)) score++;
    
    const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
    
    return {
        score: score,
        color: colors[Math.min(score - 1, 3)] || 'bg-gray-200'
    };
}

// Password confirmation validation
document.getElementById('admin-form').addEventListener('submit', function(e) {
    const pass = document.getElementById('admin_pass').value;
    const confirm = document.getElementById('admin_pass_confirm').value;
    
    if (pass !== confirm) {
        e.preventDefault();
        alert('<?php echo $t['error_password_mismatch'] ?? 'Passwords do not match!'; ?>');
    }
});
</script>