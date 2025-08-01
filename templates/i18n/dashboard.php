<?php
/**
 * I18n Management Dashboard Template
 * 
 * @author 2Brands Media GmbH
 */

$pageTitle = $data['page_title'] ?? 'Übersetzungsverwaltung';
$status = $data['status'] ?? [];
$coverage = $data['coverage'] ?? [];
$report = $data['report'] ?? [];
$languages = $data['languages'] ?? [];
?>

<!DOCTYPE html>
<html lang="<?= $i18n->getLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= $i18n->t('app.name') ?></title>
    <link href="/assets/css/app.css" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .status-excellent { @apply bg-green-100 text-green-800 border-green-200; }
        .status-good { @apply bg-yellow-100 text-yellow-800 border-yellow-200; }
        .status-needs_work { @apply bg-orange-100 text-orange-800 border-orange-200; }
        .status-critical { @apply bg-red-100 text-red-800 border-red-200; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen" x-data="i18nDashboard()">
        <?php include dirname(__DIR__) . '/layouts/header.php'; ?>
        
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    🌐 <?= htmlspecialchars($pageTitle) ?>
                </h1>
                <p class="mt-2 text-gray-600">
                    Verwalten und überwachen Sie die Übersetzungen des Systems
                </p>
            </div>

            <!-- System Status -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-sm font-medium">
                                    <?= count($languages) ?>
                                </span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Sprachen</p>
                            <div class="flex space-x-1">
                                <?php foreach ($languages as $lang): ?>
                                    <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                        <?= strtoupper($lang) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                📊
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Gesamtschlüssel</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $report['total_keys'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                ⚠️
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Inkonsistenzen</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $status['inconsistencies_count'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 <?= ($report['overall_health'] ?? '') === 'excellent' ? 'bg-green-100' : 'bg-red-100' ?> rounded-full flex items-center justify-center">
                                <?= ($report['overall_health'] ?? '') === 'excellent' ? '✅' : '🚨' ?>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Systemzustand</p>
                            <p class="text-sm font-semibold capitalize <?= ($report['overall_health'] ?? '') === 'excellent' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $report['overall_health'] ?? 'Unbekannt' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coverage Overview -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Übersetzungsabdeckung</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($languages as $lang): ?>
                            <?php 
                            $langCoverage = $coverage[$lang] ?? 0;
                            $langReport = $report['languages'][$lang] ?? [];
                            $statusClass = 'status-' . ($langReport['status'] ?? 'critical');
                            ?>
                            <div class="border rounded-lg p-4 <?= $statusClass ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-semibold"><?= strtoupper($lang) ?></h3>
                                    <span class="text-2xl font-bold"><?= $langCoverage ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                    <div class="bg-current rounded-full h-2" style="width: <?= $langCoverage ?>%"></div>
                                </div>
                                <div class="text-sm">
                                    <p>Schlüssel: <?= $langReport['total_keys'] ?? 0 ?> / <?= $report['total_keys'] ?? 0 ?></p>
                                    <?php if (($langReport['missing_keys'] ?? 0) > 0): ?>
                                        <p>Fehlend: <?= $langReport['missing_keys'] ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Wartungsaktionen</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <button 
                            @click="performAction('check')"
                            :disabled="loading"
                            class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            <span x-show="!loading || currentAction !== 'check'">🔍 Prüfen</span>
                            <span x-show="loading && currentAction === 'check'" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Prüfung läuft...
                            </span>
                        </button>

                        <button 
                            @click="performAction('sync')"
                            :disabled="loading"
                            class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                        >
                            <span x-show="!loading || currentAction !== 'sync'">🔄 Synchronisieren</span>
                            <span x-show="loading && currentAction === 'sync'" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Sync läuft...
                            </span>
                        </button>

                        <button 
                            @click="performAction('backup')"
                            :disabled="loading"
                            class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50"
                        >
                            <span x-show="!loading || currentAction !== 'backup'">💾 Backup</span>
                            <span x-show="loading && currentAction === 'backup'" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Backup läuft...
                            </span>
                        </button>

                        <button 
                            @click="performAction('unused-keys')"
                            :disabled="loading"
                            class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                        >
                            <span x-show="!loading || currentAction !== 'unused-keys'">🗑️ Ungenutzte</span>
                            <span x-show="loading && currentAction === 'unused-keys'" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Suche läuft...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div x-show="results" class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Ergebnisse</h2>
                </div>
                <div class="p-6">
                    <div x-show="results.success" class="mb-4 p-4 bg-green-100 border border-green-200 rounded-md">
                        <p class="text-green-800" x-text="results.message"></p>
                    </div>
                    
                    <div x-show="!results.success" class="mb-4 p-4 bg-red-100 border border-red-200 rounded-md">
                        <p class="text-red-800" x-text="results.error"></p>
                    </div>

                    <div x-show="results.data" class="mt-4">
                        <pre x-text="JSON.stringify(results.data, null, 2)" class="bg-gray-100 p-4 rounded text-sm overflow-auto max-h-96"></pre>
                    </div>
                </div>
            </div>

            <!-- Last Check Info -->
            <?php if ($status['last_check'] ?? 0): ?>
            <div class="mt-8 text-sm text-gray-500 text-center">
                Letzte Wartungsprüfung: <?= date('d.m.Y H:i:s', $status['last_check']) ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function i18nDashboard() {
            return {
                loading: false,
                currentAction: null,
                results: null,

                async performAction(action) {
                    this.loading = true;
                    this.currentAction = action;
                    this.results = null;

                    try {
                        const response = await fetch(`/api/i18n/${action}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await response.json();
                        this.results = data;

                        if (data.success) {
                            // Nach erfolgreicher Aktion Seite neu laden für aktuelle Daten
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    } catch (error) {
                        this.results = {
                            success: false,
                            error: 'Netzwerkfehler: ' + error.message
                        };
                    } finally {
                        this.loading = false;
                        this.currentAction = null;
                    }
                }
            };
        }
    </script>
</body>
</html>