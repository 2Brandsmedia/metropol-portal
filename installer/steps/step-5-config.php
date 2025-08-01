<?php
// Step 5: Configuration
$config = $_SESSION['installer_config_form'] ?? [
    'site_name' => 'Metropol Portal',
    'timezone' => 'Europe/Berlin',
    'ors_api_key' => '',
    'google_maps_key' => ''
];

// Timezone list
$timezones = [
    'Europe/Berlin' => 'Berlin (UTC+1)',
    'Europe/Istanbul' => 'Istanbul (UTC+3)',
    'Europe/London' => 'London (UTC+0)',
    'Europe/Paris' => 'Paris (UTC+1)',
    'Europe/Moscow' => 'Moscow (UTC+3)',
    'America/New_York' => 'New York (UTC-5)',
    'America/Chicago' => 'Chicago (UTC-6)',
    'America/Los_Angeles' => 'Los Angeles (UTC-8)',
    'Asia/Tokyo' => 'Tokyo (UTC+9)',
    'Asia/Shanghai' => 'Shanghai (UTC+8)',
];
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $t['basic_configuration'] ?? 'Basic Configuration'; ?></h2>
</div>

<form method="post" action="install.php">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="space-y-6">
        <!-- Basic Settings -->
        <div class="space-y-4">
            <!-- Site Name -->
            <div>
                <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo $t['site_name'] ?? 'Site Name'; ?>
                </label>
                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($config['site_name']); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       required>
            </div>
            
            <!-- Timezone -->
            <div>
                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo $t['timezone'] ?? 'Timezone'; ?>
                </label>
                <select id="timezone" name="timezone" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($timezones as $tz => $label): ?>
                        <option value="<?php echo $tz; ?>" <?php echo $config['timezone'] === $tz ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- API Configuration -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-3">
                <?php echo $t['api_configuration'] ?? 'API Configuration (optional)'; ?>
            </h3>
            <p class="text-sm text-gray-600 mb-4">
                <?php echo $t['api_keys_optional'] ?? 'API keys can be configured later.'; ?>
            </p>
            
            <div class="space-y-4">
                <!-- OpenRouteService API Key -->
                <div>
                    <label for="ors_api_key" class="block text-sm font-medium text-gray-700 mb-1">
                        <?php echo $t['ors_api_key'] ?? 'OpenRouteService API Key'; ?>
                        <span class="text-gray-500 font-normal">(Optional)</span>
                    </label>
                    <input type="text" id="ors_api_key" name="ors_api_key" value="<?php echo htmlspecialchars($config['ors_api_key']); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="5b3ce3597851110001cf6248...">
                    <p class="mt-1 text-xs text-gray-500">
                        <a href="https://openrouteservice.org/dev/#/signup" target="_blank" class="text-indigo-600 hover:text-indigo-700">
                            Get free API key →
                        </a>
                    </p>
                </div>
                
                <!-- Google Maps API Key -->
                <div>
                    <label for="google_maps_key" class="block text-sm font-medium text-gray-700 mb-1">
                        <?php echo $t['google_maps_key'] ?? 'Google Maps API Key'; ?>
                        <span class="text-gray-500 font-normal">(Optional)</span>
                    </label>
                    <input type="text" id="google_maps_key" name="google_maps_key" value="<?php echo htmlspecialchars($config['google_maps_key']); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="AIzaSy...">
                    <p class="mt-1 text-xs text-gray-500">
                        <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank" class="text-indigo-600 hover:text-indigo-700">
                            Get API key →
                        </a>
                    </p>
                </div>
            </div>
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