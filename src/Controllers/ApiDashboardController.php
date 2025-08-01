<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Config;
use App\Agents\APILimitAgent;
use App\Services\APIUsageTracker;
use Exception;

/**
 * ApiDashboardController - Admin Dashboard für API-Monitoring
 * 
 * Bietet Echtzeit-Übersicht über API-Nutzung und Limits
 * 
 * @author 2Brands Media GmbH
 */
class ApiDashboardController
{
    private Database $db;
    private Config $config;
    private APILimitAgent $limitAgent;

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->limitAgent = new APILimitAgent($db, $config);
    }

    /**
     * Dashboard-Hauptseite
     */
    public function dashboard(Request $request): Response
    {
        try {
            $dashboardData = $this->limitAgent->getDashboardData();
            
            return $this->renderDashboard($dashboardData);
            
        } catch (Exception $e) {
            error_log("API Dashboard error: " . $e->getMessage());
            return Response::json(['error' => 'Dashboard-Daten konnten nicht geladen werden'], 500);
        }
    }

    /**
     * API-Statistiken als JSON für AJAX-Updates
     */
    public function stats(Request $request): Response
    {
        try {
            $dashboardData = $this->limitAgent->getDashboardData();
            
            return Response::json($dashboardData);
            
        } catch (Exception $e) {
            error_log("API Stats error: " . $e->getMessage());
            return Response::json(['error' => 'Statistiken nicht verfügbar'], 500);
        }
    }

    /**
     * Historische Daten für Charts
     */
    public function history(Request $request): Response
    {
        try {
            $apiProvider = $request->get('api', 'all');
            $days = (int) $request->get('days', 7);
            
            $historyData = [];
            
            if ($apiProvider === 'all') {
                $apis = [
                    APIUsageTracker::API_GOOGLE_MAPS,
                    APIUsageTracker::API_NOMINATIM,
                    APIUsageTracker::API_OPENROUTESERVICE
                ];
                
                foreach ($apis as $api) {
                    $historyData[$api] = $this->getApiHistory($api, $days);
                }
            } else {
                $historyData[$apiProvider] = $this->getApiHistory($apiProvider, $days);
            }
            
            return Response::json($historyData);
            
        } catch (Exception $e) {
            error_log("API History error: " . $e->getMessage());
            return Response::json(['error' => 'Historische Daten nicht verfügbar'], 500);
        }
    }

    /**
     * API-Limits zurücksetzen (Admin-Aktion)
     */
    public function resetLimits(Request $request): Response
    {
        try {
            // Admin-Berechtigung prüfen
            if (!$this->isAdmin($request)) {
                return Response::json(['error' => 'Keine Berechtigung'], 403);
            }
            
            $apiProvider = $request->json('api_provider');
            
            if (!in_array($apiProvider, [
                APIUsageTracker::API_GOOGLE_MAPS,
                APIUsageTracker::API_NOMINATIM,
                APIUsageTracker::API_OPENROUTESERVICE
            ])) {
                return Response::json(['error' => 'Ungültiger API-Provider'], 400);
            }
            
            $success = $this->limitAgent->resetApiLimits($apiProvider);
            
            if ($success) {
                return Response::json([
                    'success' => true,
                    'message' => "API-Limits für {$apiProvider} wurden zurückgesetzt"
                ]);
            } else {
                return Response::json(['error' => 'Zurücksetzen fehlgeschlagen'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Reset limits error: " . $e->getMessage());
            return Response::json(['error' => 'Aktion fehlgeschlagen'], 500);
        }
    }

    /**
     * API-Limits aktualisieren
     */
    public function updateLimits(Request $request): Response
    {
        try {
            // Admin-Berechtigung prüfen
            if (!$this->isAdmin($request)) {
                return Response::json(['error' => 'Keine Berechtigung'], 403);
            }
            
            $apiProvider = $request->json('api_provider');
            $newLimits = $request->json('limits');
            
            // Validierung
            if (!$this->validateLimits($newLimits)) {
                return Response::json(['error' => 'Ungültige Limit-Werte'], 400);
            }
            
            $success = $this->limitAgent->updateApiLimits($apiProvider, $newLimits);
            
            if ($success) {
                return Response::json([
                    'success' => true,
                    'message' => "API-Limits für {$apiProvider} wurden aktualisiert"
                ]);
            } else {
                return Response::json(['error' => 'Update fehlgeschlagen'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Update limits error: " . $e->getMessage());
            return Response::json(['error' => 'Aktion fehlgeschlagen'], 500);
        }
    }

    /**
     * Live-Status für einzelne API
     */
    public function apiStatus(Request $request): Response
    {
        try {
            $apiProvider = $request->get('api');
            
            if (!$apiProvider) {
                return Response::json(['error' => 'API-Provider erforderlich'], 400);
            }
            
            $checkResult = $this->limitAgent->checkApiRequest($apiProvider);
            $usage = (new APIUsageTracker($this->db, $this->config))->getCurrentUsage($apiProvider);
            
            return Response::json([
                'api' => $apiProvider,
                'status' => $checkResult['allowed'] ? 'available' : 'blocked',
                'warning_level' => $checkResult['warning_level'],
                'message' => $checkResult['admin_message'],
                'usage' => $usage,
                'retry_after' => $checkResult['retry_after'],
                'last_updated' => time()
            ]);
            
        } catch (Exception $e) {
            error_log("API Status error: " . $e->getMessage());
            return Response::json(['error' => 'Status nicht verfügbar'], 500);
        }
    }

    /**
     * Aktuelle Warnungen und Alerts
     */
    public function alerts(Request $request): Response
    {
        try {
            $alerts = $this->getActiveAlerts();
            
            return Response::json($alerts);
            
        } catch (Exception $e) {
            error_log("Alerts error: " . $e->getMessage());
            return Response::json(['error' => 'Alerts nicht verfügbar'], 500);
        }
    }

    /**
     * Private Helper-Methoden
     */
    private function renderDashboard(array $data): Response
    {
        $html = $this->generateDashboardHTML($data);
        
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function generateDashboardHTML(array $data): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Dashboard - Metropol Portal</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                .warning-yellow { @apply bg-yellow-100 border-yellow-400 text-yellow-800; }
                .warning-red { @apply bg-red-100 border-red-400 text-red-800; }
                .status-healthy { @apply bg-green-100 border-green-400 text-green-800; }
                .status-blocked { @apply bg-red-100 border-red-400 text-red-800; }
            </style>
        </head>
        <body class="bg-gray-100">
            <div class="container mx-auto px-4 py-8">
                <h1 class="text-3xl font-bold mb-8">API Usage Dashboard</h1>
                
                <!-- Alert-Bereich -->
                <div id="alerts-container" class="mb-6">
                    <?php foreach ($data['alerts'] as $alert): ?>
                    <div class="p-4 border-l-4 mb-4 warning-<?= $alert['level'] ?>">
                        <div class="flex">
                            <div class="flex-1">
                                <h4 class="font-medium"><?= ucfirst($alert['api']) ?> API</h4>
                                <p><?= htmlspecialchars($alert['message']) ?></p>
                                <small><?= $alert['percentage'] ?>% des Tageslimits verwendet</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- API-Übersicht -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <?php foreach ($data['apis'] as $api => $stats): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4"><?= ucfirst(str_replace('_', ' ', $api)) ?></h3>
                        
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span>Täglich</span>
                                    <span><?= $stats['usage']['daily_requests'] ?> / <?= $stats['limits']['daily'] ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full bg-<?= $this->getProgressColor($stats['daily_percentage']) ?>-500" 
                                         style="width: <?= min(100, $stats['daily_percentage']) ?>%"></div>
                                </div>
                                <small class="text-gray-500"><?= $stats['daily_percentage'] ?>%</small>
                            </div>
                            
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span>Stündlich</span>
                                    <span><?= $stats['usage']['hourly_requests'] ?> / <?= $stats['limits']['hourly'] ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full bg-<?= $this->getProgressColor($stats['hourly_percentage']) ?>-500" 
                                         style="width: <?= min(100, $stats['hourly_percentage']) ?>%"></div>
                                </div>
                                <small class="text-gray-500"><?= $stats['hourly_percentage'] ?>%</small>
                            </div>
                            
                            <div class="pt-2 border-t">
                                <div class="flex justify-between text-sm">
                                    <span>Kosten heute:</span>
                                    <span class="font-medium"><?= number_format($stats['estimated_daily_cost'], 2) ?> €</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Fehlerrate:</span>
                                    <span><?= $this->calculateErrorRate($stats['usage']) ?>%</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Ø Response:</span>
                                    <span><?= round($stats['usage']['daily_avg_response_time']) ?>ms</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($stats['warning_level']): ?>
                        <div class="mt-4 p-2 rounded bg-<?= $stats['warning_level'] === 'red' ? 'red' : 'yellow' ?>-100">
                            <small class="text-<?= $stats['warning_level'] === 'red' ? 'red' : 'yellow' ?>-800">
                                <?= htmlspecialchars($stats['message']) ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Request-Verlauf (7 Tage)</h3>
                        <canvas id="requestChart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Kosten-Entwicklung</h3>
                        <canvas id="costChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Empfehlungen -->
                <?php if (!empty($data['recommendations'])): ?>
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-semibold mb-4">Empfehlungen</h3>
                    <div class="space-y-3">
                        <?php foreach ($data['recommendations'] as $rec): ?>
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-2 h-2 mt-2 rounded-full bg-<?= $rec['priority'] === 'high' ? 'red' : 'yellow' ?>-500"></div>
                            <div>
                                <p class="text-sm"><?= htmlspecialchars($rec['message']) ?></p>
                                <small class="text-gray-500">API: <?= ucfirst($rec['api']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Admin-Aktionen -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Admin-Aktionen</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach (array_keys($data['apis']) as $api): ?>
                        <div class="border rounded p-4">
                            <h4 class="font-medium mb-2"><?= ucfirst(str_replace('_', ' ', $api)) ?></h4>
                            <button onclick="resetLimits('<?= $api ?>')" 
                                    class="w-full bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                Limits zurücksetzen
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <script>
                // Auto-refresh alle 30 Sekunden
                setInterval(refreshStats, 30000);
                
                // Initial charts laden
                loadCharts();
                
                function refreshStats() {
                    fetch('/api/dashboard/stats')
                        .then(response => response.json())
                        .then(data => updateDashboard(data))
                        .catch(error => console.error('Stats refresh failed:', error));
                }
                
                function updateDashboard(data) {
                    // Dashboard-Update-Logik hier
                    console.log('Dashboard updated', data);
                }
                
                function loadCharts() {
                    // Chart.js Implementierung hier
                    const ctx1 = document.getElementById('requestChart').getContext('2d');
                    const ctx2 = document.getElementById('costChart').getContext('2d');
                    
                    // Request Chart
                    new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($this->getChartLabels(7)) ?>,
                            datasets: <?= json_encode($this->getRequestChartData($data)) ?>
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    
                    // Cost Chart
                    new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($this->getChartLabels(7)) ?>,
                            datasets: <?= json_encode($this->getCostChartData($data)) ?>
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
                
                function resetLimits(api) {
                    if (!confirm('Wirklich die Limits für ' + api + ' zurücksetzen?')) {
                        return;
                    }
                    
                    fetch('/api/dashboard/reset-limits', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            api_provider: api
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Fehler: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Reset failed:', error);
                        alert('Aktion fehlgeschlagen');
                    });
                }
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function getProgressColor(float $percentage): string
    {
        if ($percentage >= 90) return 'red';
        if ($percentage >= 80) return 'yellow';
        return 'green';
    }

    private function calculateErrorRate(array $usage): float
    {
        $total = $usage['daily_requests'];
        $errors = $usage['daily_errors'];
        
        return $total > 0 ? round(($errors / $total) * 100, 1) : 0;
    }

    private function getChartLabels(int $days): array
    {
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('d.m', strtotime("-{$i} days"));
        }
        return $labels;
    }

    private function getRequestChartData(array $data): array
    {
        $datasets = [];
        $colors = ['#FF6384', '#36A2EB', '#FFCE56'];
        $i = 0;
        
        foreach ($data['apis'] as $api => $stats) {
            $datasets[] = [
                'label' => ucfirst(str_replace('_', ' ', $api)),
                'data' => $this->getApiHistoryData($api, 7),
                'borderColor' => $colors[$i % 3],
                'backgroundColor' => $colors[$i % 3] . '20',
                'tension' => 0.1
            ];
            $i++;
        }
        
        return $datasets;
    }

    private function getCostChartData(array $data): array
    {
        $datasets = [];
        $colors = ['#FF6384', '#36A2EB', '#FFCE56'];
        $i = 0;
        
        foreach ($data['apis'] as $api => $stats) {
            $datasets[] = [
                'label' => ucfirst(str_replace('_', ' ', $api)),
                'data' => $this->getCostHistoryData($api, 7),
                'backgroundColor' => $colors[$i % 3],
            ];
            $i++;
        }
        
        return $datasets;
    }

    private function getApiHistory(string $api, int $days): array
    {
        $tracker = new APIUsageTracker($this->db, $this->config);
        return $tracker->getUsageHistory($api, $days);
    }

    private function getApiHistoryData(string $api, int $days): array
    {
        $history = $this->getApiHistory($api, $days);
        return array_column($history, 'requests');
    }

    private function getCostHistoryData(string $api, int $days): array
    {
        $history = $this->getApiHistory($api, $days);
        $limits = $this->limitAgent->getApiLimits($api);
        $costPerRequest = $limits['cost_per_request'] ?? 0;
        
        return array_map(function($day) use ($costPerRequest) {
            return $day['requests'] * $costPerRequest;
        }, $history);
    }

    private function getActiveAlerts(): array
    {
        return $this->db->select(
            'SELECT * FROM api_warnings 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY created_at DESC
             LIMIT 10'
        );
    }

    private function isAdmin(Request $request): bool
    {
        // Session-basierte Admin-Prüfung
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    private function validateLimits(array $limits): bool
    {
        // Validierung der Limit-Werte
        $requiredKeys = ['daily', 'hourly', 'per_second'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($limits[$key]) || !is_numeric($limits[$key]) || $limits[$key] < 0) {
                return false;
            }
        }
        
        return true;
    }
}