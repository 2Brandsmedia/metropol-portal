<?php
// Step 2: System Requirements
require_once __DIR__ . '/../SystemChecker.php';
$checker = new \Installer\SystemChecker();
$allMet = $checker->allRequirementsMet();
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $t['checking_requirements'] ?? 'Checking system requirements...'; ?></h2>
    <p class="text-gray-600">
        <?php if ($allMet): ?>
            <span class="text-green-600 font-medium"><?php echo $t['all_requirements_met'] ?? 'All system requirements are met!'; ?></span>
        <?php else: ?>
            <span class="text-red-600 font-medium"><?php echo $t['requirements_not_met'] ?? 'Some system requirements are not met.'; ?></span>
            <br><?php echo $t['please_fix_errors'] ?? 'Please fix the errors before continuing.'; ?>
        <?php endif; ?>
    </p>
</div>

<!-- System Requirements Report -->
<?php echo $checker->getHtmlReport(); ?>

<!-- Navigation -->
<form method="post" action="install.php">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="mt-8 flex justify-between">
        <button type="submit" name="back" value="1" class="text-gray-600 hover:text-gray-900">
            ← <?php echo $t['back'] ?? 'Back'; ?>
        </button>
        
        <?php if ($allMet): ?>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                <?php echo $t['next'] ?? 'Next'; ?> →
            </button>
        <?php else: ?>
            <button type="button" onclick="location.reload()" class="bg-gray-400 text-white px-6 py-2 rounded-lg cursor-not-allowed" disabled>
                <?php echo $t['next'] ?? 'Next'; ?> →
            </button>
        <?php endif; ?>
    </div>
</form>