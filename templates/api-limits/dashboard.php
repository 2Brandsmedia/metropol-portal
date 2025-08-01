<?php
/**
 * API Limits Dashboard - Real-time Überwachung
 * 
 * @author 2Brands Media GmbH
 */

use App\Agents\APILimitAgent;
use App\Services\APILimitReportingService;

$title = 'API Limits Dashboard';
$pageClass = 'api-limits-dashboard';

// Dashboard-Daten laden
$apiLimitAgent = new APILimitAgent($db, $config);
$reportingService = new APILimitReportingService($db, $config);

$dashboardData = $apiLimitAgent->getDashboardData();
$realTimeData = $reportingService->getRealTimeDashboard();
$costProjection = $reportingService->generateCostProjection(30);

include __DIR__ . '/../layouts/header.php';
?>

<div class="api-limits-dashboard min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">API Limits Dashboard</h1>
                    <p class="text-sm text-gray-500">Real-time Überwachung und Limit-Management</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        Letzte Aktualisierung: <span id="last-update"><?= date('H:i:s') ?></span>
                    </div>
                    <button onclick="refreshDashboard()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        Aktualisieren
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Status-Übersicht -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Gesamt-Requests heute -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Requests heute</dt>
                            <dd class="text-lg font-medium text-gray-900"><?= number_format($realTimeData['daily_progress']['total_requests'] ?? 0) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Gesamtkosten heute -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Kosten heute</dt>
                            <dd class="text-lg font-medium text-gray-900">€<?= number_format($realTimeData['cost_tracker']['daily_cost'] ?? 0, 2) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Aktive Alerts -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.864-.833-2.634 0L4.184 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Aktive Alerts</dt>
                            <dd class="text-lg font-medium text-gray-900"><?= count($dashboardData['alerts']) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- API Gesundheit -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">API Gesundheit</dt>
                            <dd class="text-lg font-medium text-green-600">Gesund</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- API-Status-Übersicht -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Live API Nutzung -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Live API Nutzung</h3>
                    <p class="text-sm text-gray-500">Aktuelle Limit-Auslastung der APIs</p>
                </div>
                <div class="p-6">
                    <?php foreach ($dashboardData['apis'] as $provider => $data): ?>
                        <div class="mb-6 last:mb-0">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $provider)) ?></span>
                                    <?php if ($data['warning_level']): ?>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $data['warning_level'] === 'red' ? 'red' : 'yellow' ?>-100 text-<?= $data['warning_level'] === 'red' ? 'red' : 'yellow' ?>-800">
                                            <?= $data['warning_level'] === 'red' ? 'Kritisch' : 'Warnung' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-sm text-gray-500"><?= $data['daily_percentage'] ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-<?= $data['warning_level'] === 'red' ? 'red' : ($data['warning_level'] === 'yellow' ? 'yellow' : 'blue') ?>-600 h-2 rounded-full" style="width: <?= $data['daily_percentage'] ?>%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span><?= number_format($data['usage']['daily_requests']) ?> / <?= number_format($data['limits']['daily']) ?> Requests</span>
                                <span>€<?= number_format($data['estimated_daily_cost'], 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kostenprojektion -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Kostenprojektion (30 Tage)</h3>
                    <p class="text-sm text-gray-500">Geschätzte Kosten basierend auf aktueller Nutzung</p>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <div class="text-3xl font-bold text-gray-900">€<?= number_format($costProjection['total_projection']['total_cost_30_days'], 2) ?></div>
                        <div class="text-sm text-gray-500">Geschätzte Kosten für 30 Tage</div>
                    </div>
                    
                    <?php foreach ($costProjection['provider_projections'] as $provider => $projection): ?>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                            <span class="text-sm text-gray-600"><?= ucfirst(str_replace('_', ' ', $provider)) ?></span>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900">€<?= number_format($projection['projected_cost_30_days'], 2) ?></div>
                                <div class="text-xs text-gray-500"><?= ucfirst($projection['trend']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Alerts und Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Aktive Alerts -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Aktive Alerts</h3>
                    <p class="text-sm text-gray-500">Warnungen und kritische Benachrichtigungen</p>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php if (empty($dashboardData['alerts'])): ?>
                        <div class="p-6 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Keine aktiven Alerts</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dashboardData['alerts'] as $alert): ?>
                            <div class="p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-2 h-2 bg-<?= $alert['level'] === 'red' ? 'red' : 'yellow' ?>-400 rounded-full mt-2"></div>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($alert['message']) ?></p>
                                        <p class="text-sm text-gray-500"><?= ucfirst(str_replace('_', ' ', $alert['api'])) ?> - <?= $alert['percentage'] ?>%</p>
                                        <p class="text-xs text-gray-400"><?= date('H:i:s', $alert['timestamp']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Performance-Metriken -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Performance-Metriken</h3>
                    <p class="text-sm text-gray-500">Response-Zeiten und Fehlerrate</p>
                </div>
                <div class="p-6">
                    <?php foreach ($dashboardData['performance_metrics']['avg_response_times'] as $provider => $responseTime): ?>
                        <div class="mb-4 last:mb-0">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $provider)) ?></span>
                                <div class="text-right">
                                    <span class="text-sm text-gray-900"><?= number_format($responseTime, 0) ?>ms</span>
                                    <span class="text-xs text-gray-500 ml-2"><?= number_format($dashboardData['performance_metrics']['error_rates'][$provider], 1) ?>% Fehler</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <?php 
                                $performanceClass = $responseTime < 200 ? 'green' : ($responseTime < 500 ? 'yellow' : 'red');
                                $performanceWidth = min(($responseTime / 1000) * 100, 100);
                                ?>
                                <div class="bg-<?= $performanceClass ?>-500 h-1.5 rounded-full" style="width: <?= $performanceWidth ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Fallback-Status und Empfehlungen -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Fallback-System Status -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Fallback-System</h3>
                    <p class="text-sm text-gray-500">Status der alternativen Services</p>
                </div>
                <div class="p-6">
                    <?php foreach ($dashboardData['fallback_status']['alternative_services'] as $service => $available): ?>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-gray-600"><?= ucfirst(str_replace('_', ' ', $service)) ?></span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $available ? 'green' : 'red' ?>-100 text-<?= $available ? 'green' : 'red' ?>-800">
                                <?= $available ? 'Verfügbar' : 'Nicht verfügbar' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="text-sm text-gray-600">Cache Status</div>
                        <div class="mt-1">
                            <span class="text-sm font-medium text-gray-900">Hit Rate: <?= $dashboardData['fallback_status']['cache_status']['hit_rate'] ?>%</span>
                            <span class="text-xs text-gray-500 ml-2"><?= $dashboardData['fallback_status']['cache_status']['entries'] ?> Einträge</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empfehlungen -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Empfehlungen</h3>
                    <p class="text-sm text-gray-500">Optimierungsvorschläge</p>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php if (empty($dashboardData['recommendations'])): ?>
                        <div class="p-6 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Keine Empfehlungen</p>
                            <p class="text-xs">Alle Systeme laufen optimal</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dashboardData['recommendations'] as $recommendation): ?>
                            <div class="p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-2 h-2 bg-<?= $recommendation['priority'] === 'high' ? 'red' : 'blue' ?>-400 rounded-full mt-2"></div>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($recommendation['message']) ?></p>
                                        <p class="text-xs text-gray-500 mt-1">Priorität: <?= ucfirst($recommendation['priority']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-Refresh Dashboard
let refreshInterval;

function refreshDashboard() {
    document.getElementById('last-update').textContent = new Date().toLocaleTimeString();
    // Hier würde eine AJAX-Anfrage die Daten aktualisieren
    location.reload();
}

function startAutoRefresh() {
    refreshInterval = setInterval(refreshDashboard, 30000); // 30 Sekunden
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
}

// Auto-Refresh beim Laden starten
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Auto-Refresh pausieren wenn Tab nicht aktiv
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});
</script>

<style>
.api-limits-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Custom progress bar animations */
.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Status indicator animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .7;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>