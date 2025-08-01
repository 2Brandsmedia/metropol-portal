<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Config;
use App\Agents\APILimitAgent;
use App\Services\APILimitReportingService;
use App\Services\AlertService;
use Exception;

/**
 * ApiLimitController - API für das API-Limit-Management-Dashboard
 * 
 * Stellt REST-Endpunkte für das Dashboard und die Verwaltung bereit
 * 
 * @author 2Brands Media GmbH
 */
class ApiLimitController
{
    private Database $db;
    private Config $config;
    private APILimitAgent $apiLimitAgent;
    private APILimitReportingService $reportingService;
    private AlertService $alertService;

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->apiLimitAgent = new APILimitAgent($db, $config);
        $this->reportingService = new APILimitReportingService($db, $config);
        $this->alertService = new AlertService($db, $config);
    }

    /**
     * Dashboard-Hauptseite anzeigen
     */
    public function dashboard(Request $request): Response
    {
        try {
            // Dashboard-Template laden
            ob_start();
            include __DIR__ . '/../../templates/api-limits/dashboard.php';
            $content = ob_get_clean();
            
            return new Response($content);
            
        } catch (Exception $e) {
            error_log("Dashboard error: " . $e->getMessage());
            return new Response('Dashboard nicht verfügbar', 500);
        }
    }

    /**
     * Real-time Dashboard-Daten (AJAX)
     */
    public function getDashboardData(Request $request): Response
    {
        try {
            $data = [
                'dashboard' => $this->apiLimitAgent->getDashboardData(),
                'realtime' => $this->reportingService->getRealTimeDashboard(),
                'health' => $this->apiLimitAgent->monitorApiHealth()
            ];
            
            return new Response(json_encode($data), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Dashboard data error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Daten nicht verfügbar']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * API-Limits manuell aktualisieren
     */
    public function updateLimits(Request $request): Response
    {
        try {
            $data = json_decode($request->getBody(), true);
            
            if (!isset($data['provider']) || !isset($data['limits'])) {
                return new Response(json_encode(['error' => 'Provider und Limits erforderlich']), 400, ['Content-Type' => 'application/json']);
            }
            
            $success = $this->apiLimitAgent->updateApiLimits($data['provider'], $data['limits']);
            
            if ($success) {
                // Alert senden
                $this->alertService->sendAlert([
                    'type' => 'manual_limit_update',
                    'provider' => $data['provider'],
                    'limits' => $data['limits'],
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                
                return new Response(json_encode(['success' => true, 'message' => 'Limits erfolgreich aktualisiert']), 200, ['Content-Type' => 'application/json']);
            } else {
                return new Response(json_encode(['error' => 'Update fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
            }
            
        } catch (Exception $e) {
            error_log("Update limits error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Unbekannter Fehler']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * API-Limits zurücksetzen (Notfall)
     */
    public function resetLimits(Request $request): Response
    {
        try {
            $data = json_decode($request->getBody(), true);
            
            if (!isset($data['provider'])) {
                return new Response(json_encode(['error' => 'Provider erforderlich']), 400, ['Content-Type' => 'application/json']);
            }
            
            // Nur Admins dürfen Limits zurücksetzen
            if (!$this->isAdmin()) {
                return new Response(json_encode(['error' => 'Nicht berechtigt']), 403, ['Content-Type' => 'application/json']);
            }
            
            $success = $this->apiLimitAgent->resetApiLimits($data['provider']);
            
            if ($success) {
                // Critical Alert senden
                $this->alertService->sendAlert([
                    'type' => 'emergency_limit_reset',
                    'provider' => $data['provider'],
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'priority' => 'critical'
                ]);
                
                return new Response(json_encode(['success' => true, 'message' => 'Limits zurückgesetzt']), 200, ['Content-Type' => 'application/json']);
            } else {
                return new Response(json_encode(['error' => 'Reset fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
            }
            
        } catch (Exception $e) {
            error_log("Reset limits error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Unbekannter Fehler']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Automatische Limit-Erkennung manuell triggern
     */
    public function detectLimitChanges(Request $request): Response
    {
        try {
            $changes = $this->apiLimitAgent->detectApiLimitChanges();
            
            return new Response(json_encode([
                'success' => true,
                'changes' => $changes,
                'count' => count($changes)
            ]), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Detect limit changes error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Erkennung fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Kostenprojektion abrufen
     */
    public function getCostProjection(Request $request): Response
    {
        try {
            $days = (int) ($request->getQuery('days') ?? 30);
            $projection = $this->reportingService->generateCostProjection($days);
            
            return new Response(json_encode($projection), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Cost projection error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Projektion nicht verfügbar']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Berichte generieren
     */
    public function generateReport(Request $request): Response
    {
        try {
            $type = $request->getQuery('type') ?? 'daily';
            $date = $request->getQuery('date') ?? date('Y-m-d');
            
            $report = match($type) {
                'daily' => $this->reportingService->generateDailyReport($date),
                'weekly' => $this->reportingService->generateWeeklyReport($date),
                'monthly' => $this->reportingService->generateMonthlyReport($date),
                default => throw new Exception('Unbekannter Report-Typ')
            };
            
            return new Response(json_encode($report), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Generate report error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Report-Generierung fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Compliance-Bericht abrufen
     */
    public function getComplianceReport(Request $request): Response
    {
        try {
            $startDate = $request->getQuery('start') ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $request->getQuery('end') ?? date('Y-m-d');
            
            $report = $this->reportingService->generateComplianceReport($startDate, $endDate);
            
            return new Response(json_encode($report), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Compliance report error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Compliance-Bericht nicht verfügbar']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Budget-Monitoring aktivieren/deaktivieren
     */
    public function toggleBudgetMonitoring(Request $request): Response
    {
        try {
            $data = json_decode($request->getBody(), true);
            
            if (!isset($data['provider']) || !isset($data['enabled'])) {
                return new Response(json_encode(['error' => 'Provider und Status erforderlich']), 400, ['Content-Type' => 'application/json']);
            }
            
            // Konfiguration aktualisieren
            $budgets = $this->config->get('api.budgets', []);
            $budgets[$data['provider']]['monitoring_enabled'] = (bool) $data['enabled'];
            
            // In Config-Datei speichern (vereinfacht)
            $configPath = '/config/api_budgets.json';
            file_put_contents($configPath, json_encode($budgets, JSON_PRETTY_PRINT));
            
            return new Response(json_encode(['success' => true]), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Toggle budget monitoring error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Konfiguration fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Fallback-Status testen
     */
    public function testFallback(Request $request): Response
    {
        try {
            $data = json_decode($request->getBody(), true);
            
            if (!isset($data['provider']) || !isset($data['fallback_mode'])) {
                return new Response(json_encode(['error' => 'Provider und Fallback-Modus erforderlich']), 400, ['Content-Type' => 'application/json']);
            }
            
            $result = $this->apiLimitAgent->executeFallbackStrategy(
                $data['provider'],
                $data['fallback_mode'],
                $data['request_data'] ?? []
            );
            
            return new Response(json_encode([
                'success' => true,
                'result' => $result
            ]), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Test fallback error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Fallback-Test fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * API-Gesundheit prüfen
     */
    public function checkApiHealth(Request $request): Response
    {
        try {
            $provider = $request->getQuery('provider');
            
            if ($provider) {
                // Einzelner Provider
                $health = $this->apiLimitAgent->monitorApiHealth()[$provider] ?? null;
                
                if (!$health) {
                    return new Response(json_encode(['error' => 'Provider nicht gefunden']), 404, ['Content-Type' => 'application/json']);
                }
                
                return new Response(json_encode($health), 200, ['Content-Type' => 'application/json']);
            } else {
                // Alle Provider
                $health = $this->apiLimitAgent->monitorApiHealth();
                return new Response(json_encode($health), 200, ['Content-Type' => 'application/json']);
            }
            
        } catch (Exception $e) {
            error_log("Check API health error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Gesundheitsprüfung fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Aktive Alerts abrufen
     */
    public function getActiveAlerts(Request $request): Response
    {
        try {
            $alerts = $this->apiLimitAgent->getDashboardData()['alerts'];
            
            return new Response(json_encode([
                'alerts' => $alerts,
                'count' => count($alerts)
            ]), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Get active alerts error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Alerts nicht verfügbar']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Alert als gelesen markieren
     */
    public function markAlertAsRead(Request $request): Response
    {
        try {
            $data = json_decode($request->getBody(), true);
            
            if (!isset($data['alert_id'])) {
                return new Response(json_encode(['error' => 'Alert-ID erforderlich']), 400, ['Content-Type' => 'application/json']);
            }
            
            // Alert in Datenbank als gelesen markieren
            $this->db->update(
                'api_warnings',
                ['read_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$data['alert_id']]
            );
            
            return new Response(json_encode(['success' => true]), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Mark alert as read error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Alert-Update fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * System-Status für Monitoring
     */
    public function getSystemStatus(Request $request): Response
    {
        try {
            $status = [
                'timestamp' => date('Y-m-d H:i:s'),
                'system_health' => 'healthy',
                'monitoring_active' => true,
                'last_check' => $this->getLastMonitoringCheck(),
                'apis' => $this->apiLimitAgent->monitorApiHealth(),
                'alerts_count' => count($this->apiLimitAgent->getDashboardData()['alerts']),
                'fallback_systems' => $this->apiLimitAgent->getDashboardData()['fallback_status']
            ];
            
            return new Response(json_encode($status), 200, ['Content-Type' => 'application/json']);
            
        } catch (Exception $e) {
            error_log("Get system status error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'System-Status nicht verfügbar']), 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Hilfsmethoden
     */
    private function isAdmin(): bool
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    private function getLastMonitoringCheck(): ?string
    {
        $result = $this->db->selectOne(
            'SELECT MAX(created_at) as last_check FROM api_monitor_stats'
        );
        
        return $result['last_check'] ?? null;
    }

    /**
     * Export-Funktionen
     */
    public function exportReport(Request $request): Response
    {
        try {
            $type = $request->getQuery('type') ?? 'daily';
            $date = $request->getQuery('date') ?? date('Y-m-d');
            $format = $request->getQuery('format') ?? 'json';
            
            $report = match($type) {
                'daily' => $this->reportingService->generateDailyReport($date),
                'weekly' => $this->reportingService->generateWeeklyReport($date),
                'monthly' => $this->reportingService->generateMonthlyReport($date),
                default => throw new Exception('Unbekannter Report-Typ')
            };
            
            if ($format === 'csv') {
                return $this->exportAsCSV($report, $type, $date);
            } else {
                $filename = "api_report_{$type}_{$date}.json";
                
                return new Response(
                    json_encode($report, JSON_PRETTY_PRINT),
                    200,
                    [
                        'Content-Type' => 'application/json',
                        'Content-Disposition' => "attachment; filename=\"{$filename}\""
                    ]
                );
            }
            
        } catch (Exception $e) {
            error_log("Export report error: " . $e->getMessage());
            return new Response(json_encode(['error' => 'Export fehlgeschlagen']), 500, ['Content-Type' => 'application/json']);
        }
    }

    private function exportAsCSV(array $report, string $type, string $date): Response
    {
        $filename = "api_report_{$type}_{$date}.csv";
        
        ob_start();
        $output = fopen('php://output', 'w');
        
        // CSV-Header
        fputcsv($output, ['Provider', 'Requests', 'Errors', 'Error Rate %', 'Avg Response Time', 'Cost EUR']);
        
        // Daten schreiben
        if (isset($report['api_usage'])) {
            foreach ($report['api_usage'] as $provider => $data) {
                fputcsv($output, [
                    $provider,
                    $data['requests'],
                    $data['errors'],
                    $data['error_rate'],
                    $data['avg_response_time'],
                    $report['cost_analysis']['breakdown'][$provider]['total_cost'] ?? 0
                ]);
            }
        }
        
        fclose($output);
        $content = ob_get_clean();
        
        return new Response(
            $content,
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\""
            ]
        );
    }
}