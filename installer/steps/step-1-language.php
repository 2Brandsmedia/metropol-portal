<?php
// Step 1: Language Selection
?>

<div class="text-center mb-8">
    <svg class="mx-auto h-16 w-16 text-indigo-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
    </svg>
    <h2 class="text-2xl font-bold text-gray-900 mb-2"><?php echo $t['welcome'] ?? 'Welcome to Metropol Portal Installer'; ?></h2>
    <p class="text-gray-600"><?php echo $t['select_language'] ?? 'Please select your preferred language:'; ?></p>
</div>

<form method="post" action="install.php">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="space-y-3">
        <?php foreach ($installer->languages as $code => $name): ?>
            <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors <?php echo $lang === $code ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300'; ?>">
                <input type="radio" name="language" value="<?php echo $code; ?>" 
                       <?php echo $lang === $code ? 'checked' : ''; ?>
                       class="form-radio text-indigo-600 mr-3">
                <div class="flex items-center">
                    <span class="text-2xl mr-3">
                        <?php 
                        $flags = ['de' => '🇩🇪', 'en' => '🇬🇧', 'tr' => '🇹🇷'];
                        echo $flags[$code] ?? '🏳️';
                        ?>
                    </span>
                    <span class="font-medium text-gray-900"><?php echo $name; ?></span>
                </div>
            </label>
        <?php endforeach; ?>
    </div>
    
    <div class="mt-8 flex justify-end">
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
            <?php echo $t['next'] ?? 'Next'; ?> →
        </button>
    </div>
</form>