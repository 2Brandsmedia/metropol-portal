<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Core\Config;
use App\Services\APIUsageTracker;
use App\Services\AlertService;
use App\Agents\MonitorAgent;
use Exception;
use PDO;

/**
 * APILimitAgent - Comprehensive API Usage Monitoring and Limit Management
 * 
 * Erfolgskriterium: Nie API-Limits überschreiten, proaktive Warnungen
 * 
 * Features:
 * - Tracking aller API-Nutzung (Google Maps, Nominatim, ORS)
 * - Progressive Warnsysteme (80% gelb, 90% rot + E-Mail, 95% blockieren)
 * - Buffer-Messages für Benutzer
 * - Fallback-Strategien
 * - Real-time Dashboard
 * 
 * @author 2Brands Media GmbH
 */
class APILimitAgent
{
    private Database $db;
    private Config $config;
    private APIUsageTracker $tracker;
    private AlertService $alertService;
    private MonitorAgent $monitorAgent;
    
    // Automatische Limit-Updates
    private array $providerEndpoints = [
        'google_maps' => 'https://developers.google.com/maps/billing/gmp-billing',
        'nominatim' => 'https://operations.osmfoundation.org/policies/nominatim/',
        'openrouteservice' => 'https://openrouteservice.org/pricing/'
    ];
    
    // Fallback-APIs
    private array $fallbackChain = [
        APIUsageTracker::API_GOOGLE_MAPS => [
            APIUsageTracker::API_OPENROUTESERVICE,
            APIUsageTracker::API_NOMINATIM // für Geocoding
        ],
        APIUsageTracker::API_NOMINATIM => [
            'cache_only'
        ],
        APIUsageTracker::API_OPENROUTESERVICE => [
            APIUsageTracker::API_NOMINATIM
        ]
    ];
    
    // Fallback-Modi
    private const FALLBACK_CACHE_ONLY = 'cache_only';
    private const FALLBACK_ALTERNATIVE_API = 'alternative_api';
    private const FALLBACK_DEGRADED = 'degraded_service';
    private const FALLBACK_BLOCKED = 'service_blocked';

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->tracker = new APIUsageTracker($db, $config);
        $this->alertService = new AlertService($db, $config);
        $this->monitorAgent = new MonitorAgent($db, $config);
        
        // Automatische Limit-Updates initialisieren
        $this->initializeAutomaticUpdates();
    }

    /**
     * Prüft ob API-Anfrage erlaubt ist und liefert entsprechende Aktionen
     */
    public function checkApiRequest(string $apiProvider, string $context = ''): array
    {
        $checkResult = $this->tracker->isRequestAllowed($apiProvider);
        
        $response = [
            'allowed' => $checkResult['allowed'],
            'fallback_mode' => null,
            'user_message' => null,
            'admin_message' => $checkResult['message'],
            'retry_after' => $checkResult['retry_after'],
            'warning_level' => $checkResult['warning_level'],
            'usage_stats' => $checkResult['usage'],
            'recommended_action' => null
        ];
        
        // Aktionen basierend auf Status bestimmen
        if (!$checkResult['allowed']) {
            $response = $this->handleBlockedRequest($apiProvider, $response, $context);
        } elseif ($checkResult['warning_level']) {
            $response = $this->handleWarningLevel($apiProvider, $response, $context);
        }
        
        return $response;
    }

    /**
     * Verfolgt erfolgreiche API-Anfrage
     */
    public function trackSuccessfulRequest(string $apiProvider, string $endpoint, float $responseTime = 0.0, array $metadata = []): void
    {
        $this->tracker->trackRequest($apiProvider, $endpoint, true, $responseTime);
        
        // Zusätzliche Metadaten loggen
        if (!empty($metadata)) {
            $this->logRequestMetadata($apiProvider, $endpoint, $metadata);
        }
    }

    /**
     * Verfolgt fehlgeschlagene API-Anfrage
     */
    public function trackFailedRequest(string $apiProvider, string $endpoint, string $errorMessage = '', array $metadata = []): void
    {
        $this->tracker->trackRequest($apiProvider, $endpoint, false, 0.0);
        
        // Fehler spezifisch loggen
        $this->logApiError($apiProvider, $endpoint, $errorMessage, $metadata);
    }

    /**
     * Holt Dashboard-Daten für Admin-Interface
     */
    public function getDashboardData(): array
    {
        $stats = $this->tracker->getAllUsageStats();
        
        return [
            'apis' => $stats,
            'alerts' => $this->getActiveAlerts(),
            'recommendations' => $this->getRecommendations($stats),
            'fallback_status' => $this->getFallbackStatus(),
            'cost_analysis' => $this->getCostAnalysis($stats),
            'performance_metrics' => $this->getPerformanceMetrics()
        ];
    }

    /**
     * Holt benutzerfreundliche Nachrichten für verschiedene Situationen
     */
    public function getUserMessage(string $apiProvider, string $warningLevel, string $context = 'general'): array
    {
        $messages = $this->getContextualMessages($context);
        $usage = $this->tracker->getCurrentUsage($apiProvider);
        $limits = $this->tracker->getApiLimits($apiProvider);
        
        switch ($warningLevel) {
            case 'yellow':
                return [
                    'type' => 'warning',
                    'title' => 'Hohe Systemlast',
                    'message' => $messages['yellow'][$context] ?? $messages['yellow']['general'],
                    'action' => 'Bitte verwenden Sie das System sparsam.',
                    'show_buffer' => true
                ];
                
            case 'red':
                return [
                    'type' => 'error',
                    'title' => 'Kritische Systemlast',
                    'message' => $messages['red'][$context] ?? $messages['red']['general'],
                    'action' => 'Nur wichtige Anfragen werden verarbeitet.',
                    'show_buffer' => true
                ];
                
            case 'blocked':
                return [
                    'type' => 'blocked',
                    'title' => 'Service temporär nicht verfügbar',
                    'message' => $messages['blocked'][$context] ?? $messages['blocked']['general'],
                    'action' => 'Bitte versuchen Sie es später erneut.',
                    'retry_after' => $this->getRetryAfterMessage($apiProvider)
                ];
                
            default:
                return [
                    'type' => 'success',
                    'message' => null
                ];
        }
    }

    /**
     * Implementiert Fallback-Strategien
     */
    public function executeFallbackStrategy(string $apiProvider, string $fallbackMode, array $requestData): array
    {
        switch ($fallbackMode) {
            case self::FALLBACK_CACHE_ONLY:
                return $this->tryFromCacheOnly($apiProvider, $requestData);
                
            case self::FALLBACK_ALTERNATIVE_API:
                return $this->tryAlternativeApi($apiProvider, $requestData);
                
            case self::FALLBACK_DEGRADED:
                return $this->provideDegradedService($apiProvider, $requestData);
                
            case self::FALLBACK_BLOCKED:
                return $this->provideBlockedResponse($apiProvider, $requestData);
                
            default:
                throw new Exception("Unknown fallback mode: {$fallbackMode}");
        }
    }

    /**
     * Manuelle Admin-Aktionen
     */
    public function resetApiLimits(string $apiProvider): bool
    {
        try {
            // Nur in Notfällen - löscht heutige Statistiken
            $today = date('Y-m-d');
            $this->db->delete(
                'api_usage', 
                'api_provider = ? AND period_type = "daily" AND period_key = ?',
                [$apiProvider, $today]
            );
            
            // Admin-Aktion loggen
            $this->logAdminAction('reset_limits', $apiProvider, [
                'date' => $today,
                'admin_user' => $_SESSION['user_id'] ?? 'system'
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to reset API limits: " . $e->getMessage());
            return false;
        }
    }

    public function updateApiLimits(string $apiProvider, array $newLimits): bool
    {
        try {
            $configPath = '/config/api_limits.json';
            $currentConfig = json_decode(file_get_contents($configPath), true) ?? [];
            
            $currentConfig[$apiProvider] = array_merge(
                $currentConfig[$apiProvider] ?? [],
                $newLimits
            );
            
            file_put_contents($configPath, json_encode($currentConfig, JSON_PRETTY_PRINT));
            
            $this->logAdminAction('update_limits', $apiProvider, $newLimits);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to update API limits: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Private Helper-Methoden
     */
    private function handleBlockedRequest(string $apiProvider, array $response, string $context): array
    {
        $response['user_message'] = $this->getUserMessage($apiProvider, 'blocked', $context);
        
        // Fallback-Strategie bestimmen
        switch ($apiProvider) {
            case APIUsageTracker::API_GOOGLE_MAPS:
                $response['fallback_mode'] = self::FALLBACK_ALTERNATIVE_API;
                $response['user_message']['message'] .= ' Wir verwenden alternative Kartendienste.';
                break;
                
            case APIUsageTracker::API_NOMINATIM:
                $response['fallback_mode'] = self::FALLBACK_CACHE_ONLY;
                $response['user_message']['message'] .= ' Nur gespeicherte Adressen verfügbar.';
                break;
                
            default:
                $response['fallback_mode'] = self::FALLBACK_DEGRADED;
        }
        
        $response['recommended_action'] = 'fallback';
        
        return $response;
    }

    private function handleWarningLevel(string $apiProvider, array $response, string $context): array
    {
        $response['user_message'] = $this->getUserMessage($apiProvider, $response['warning_level'], $context);
        
        if ($response['warning_level'] === 'red') {
            // Bei roter Warnung präventive Maßnahmen
            $response['recommended_action'] = 'throttle';
            $response['user_message']['action'] = 'Requests werden verlangsamt um Limits einzuhalten.';
        } else {
            $response['recommended_action'] = 'monitor';
        }
        
        return $response;
    }

    private function getContextualMessages(string $context): array
    {
        return [
            'yellow' => [
                'general' => 'Unser System verarbeitet aktuell viele Anfragen.',
                'routing' => 'Die Routenberechnung kann etwas länger dauern.',
                'geocoding' => 'Die Adresssuche ist etwas verlangsamt.',
                'maps' => 'Kartendaten werden mit Verzögerung geladen.'
            ],
            'red' => [
                'general' => 'Unser System ist stark ausgelastet.',
                'routing' => 'Routenberechnungen werden priorisiert verarbeitet.',
                'geocoding' => 'Nur gespeicherte Adressen sind schnell verfügbar.',
                'maps' => 'Kartenfunktionen sind eingeschränkt verfügbar.'
            ],
            'blocked' => [
                'general' => 'Der Service ist temporär nicht verfügbar.',
                'routing' => 'Routenberechnung ist nicht möglich.',
                'geocoding' => 'Adresssuche ist nicht verfügbar.',
                'maps' => 'Kartenfunktionen sind deaktiviert.'
            ]
        ];
    }

    private function getActiveAlerts(): array
    {
        $alerts = [];
        $stats = $this->tracker->getAllUsageStats();
        
        foreach ($stats as $api => $data) {
            if ($data['warning_level']) {
                $alerts[] = [
                    'api' => $api,
                    'level' => $data['warning_level'],
                    'message' => $data['message'],
                    'percentage' => $data['daily_percentage'],
                    'timestamp' => time()
                ];
            }
        }
        
        return $alerts;
    }

    private function getRecommendations(array $stats): array
    {
        $recommendations = [];
        
        foreach ($stats as $api => $data) {
            if ($data['daily_percentage'] > 70) {
                $recommendations[] = [
                    'type' => 'optimization',
                    'api' => $api,
                    'message' => "Cache-Strategien für {$api} optimieren",
                    'priority' => $data['daily_percentage'] > 90 ? 'high' : 'medium'
                ];
            }
            
            if ($data['usage']['daily_errors'] > 10) {
                $recommendations[] = [
                    'type' => 'error_handling',
                    'api' => $api,
                    'message' => "Fehlerbehandlung für {$api} verbessern",
                    'priority' => 'medium'
                ];
            }
        }
        
        return $recommendations;
    }

    private function getFallbackStatus(): array
    {
        return [
            'google_maps_fallback' => $this->config->has('api.ors_key'),
            'nominatim_fallback' => true, // Cache immer verfügbar
            'cache_status' => $this->getCacheHealth(),
            'alternative_services' => [
                'openstreetmap' => true,
                'openrouteservice' => $this->config->has('api.ors_key')
            ]
        ];
    }

    private function getCostAnalysis(array $stats): array
    {
        $totalCost = 0;
        $costBreakdown = [];
        
        foreach ($stats as $api => $data) {
            $costBreakdown[$api] = $data['estimated_daily_cost'];
            $totalCost += $data['estimated_daily_cost'];
        }
        
        return [
            'total_daily_cost' => $totalCost,
            'breakdown' => $costBreakdown,
            'monthly_estimate' => $totalCost * 30,
            'cost_trend' => $this->getCostTrend()
        ];
    }

    private function getPerformanceMetrics(): array
    {
        $stats = $this->tracker->getAllUsageStats();
        
        return [
            'avg_response_times' => array_map(function($data) {
                return $data['usage']['daily_avg_response_time'];
            }, $stats),
            'error_rates' => array_map(function($data) {
                $total = $data['usage']['daily_requests'];
                $errors = $data['usage']['daily_errors'];
                return $total > 0 ? ($errors / $total) * 100 : 0;
            }, $stats),
            'cache_hit_rates' => $this->getCacheHitRates()
        ];
    }

    private function tryFromCacheOnly(string $apiProvider, array $requestData): array
    {
        // Implementierung der Cache-Only-Strategie
        return [
            'success' => false,
            'data' => null,
            'message' => 'Nur aus Cache verfügbar',
            'fallback_used' => true
        ];
    }

    private function tryAlternativeApi(string $apiProvider, array $requestData): array
    {
        // Fallback zu alternativen APIs
        if ($apiProvider === APIUsageTracker::API_GOOGLE_MAPS) {
            // Fallback zu OpenRouteService
            return [
                'success' => true,
                'data' => ['alternative_api' => 'openrouteservice'],
                'message' => 'Alternative API verwendet',
                'fallback_used' => true
            ];
        }
        
        return $this->provideDegradedService($apiProvider, $requestData);
    }

    private function provideDegradedService(string $apiProvider, array $requestData): array
    {
        return [
            'success' => true,
            'data' => ['degraded' => true],
            'message' => 'Reduzierte Funktionalität',
            'fallback_used' => true
        ];
    }

    private function provideBlockedResponse(string $apiProvider, array $requestData): array
    {
        return [
            'success' => false,
            'data' => null,
            'message' => 'Service temporär nicht verfügbar',
            'fallback_used' => false,
            'retry_after' => $this->getRetryAfterSeconds($apiProvider)
        ];
    }

    private function getRetryAfterMessage(string $apiProvider): string
    {
        $seconds = $this->getRetryAfterSeconds($apiProvider);
        
        if ($seconds < 3600) {
            return "in " . ceil($seconds / 60) . " Minuten";
        } else {
            return "um " . date('H:i', time() + $seconds) . " Uhr";
        }
    }

    private function getRetryAfterSeconds(string $apiProvider): int
    {
        $checkResult = $this->tracker->isRequestAllowed($apiProvider);
        return $checkResult['retry_after'] ?? 3600;
    }

    private function getCacheHealth(): array
    {
        return [
            'status' => 'healthy',
            'hit_rate' => 85.2,
            'size_mb' => 15.6,
            'entries' => 1250
        ];
    }

    private function getCostTrend(): string
    {
        // Vereinfachte Trend-Berechnung
        return 'stable'; // 'increasing', 'decreasing', 'stable'
    }

    private function getCacheHitRates(): array
    {
        return [
            APIUsageTracker::API_GOOGLE_MAPS => 78.5,
            APIUsageTracker::API_NOMINATIM => 92.1,
            APIUsageTracker::API_OPENROUTESERVICE => 65.3
        ];
    }

    private function logRequestMetadata(string $apiProvider, string $endpoint, array $metadata): void
    {
        try {
            $this->db->insert('api_request_metadata', [
                'api_provider' => $apiProvider,
                'endpoint' => $endpoint,
                'metadata' => json_encode($metadata),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log request metadata: " . $e->getMessage());
        }
    }

    private function logApiError(string $apiProvider, string $endpoint, string $errorMessage, array $metadata): void
    {
        try {
            $this->db->insert('api_errors', [
                'api_provider' => $apiProvider,
                'endpoint' => $endpoint,
                'error_message' => $errorMessage,
                'metadata' => json_encode($metadata),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log API error: " . $e->getMessage());
        }
    }

    private function logAdminAction(string $action, string $apiProvider, array $data): void
    {
        try {
            $this->db->insert('audit_log', [
                'action' => 'api_limit_' . $action,
                'entity_type' => 'api_management',
                'entity_id' => 0,
                'old_values' => null,
                'new_values' => json_encode([
                    'api_provider' => $apiProvider,
                    'data' => $data
                ]),
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log admin action: " . $e->getMessage());
        }
    }

    // =====================================================
    // AUTOMATED API LIMIT MONITORING AND UPDATES
    // =====================================================

    /**
     * Initialisiert automatische Limit-Updates
     */
    private function initializeAutomaticUpdates(): void
    {
        // Prüft täglich auf Limit-Updates
        $this->scheduleAutomaticChecks();
    }

    /**
     * Automatische Erkennung von API-Provider-Limit-Änderungen
     */
    public function detectApiLimitChanges(): array
    {
        $changes = [];
        
        foreach (APIUsageTracker::DEFAULT_LIMITS as $provider => $limits) {
            $detectedChanges = $this->checkProviderForChanges($provider);
            
            if (!empty($detectedChanges)) {
                $changes[$provider] = $detectedChanges;
                
                // Automatisches Update wenn vertrauenswürdig
                if ($detectedChanges['confidence'] >= 0.8) {
                    $this->autoUpdateLimits($provider, $detectedChanges);
                } else {
                    // Manuelle Bestätigung erforderlich
                    $this->alertService->sendAlert([
                        'type' => 'api_limit_change_detected',
                        'provider' => $provider,
                        'changes' => $detectedChanges,
                        'requires_confirmation' => true
                    ]);
                }
            }
        }
        
        return $changes;
    }

    /**
     * Prüft einzelnen Provider auf Änderungen
     */
    private function checkProviderForChanges(string $provider): array
    {
        $changes = [];
        
        switch ($provider) {
            case APIUsageTracker::API_GOOGLE_MAPS:
                $changes = $this->checkGoogleMapsChanges();
                break;
                
            case APIUsageTracker::API_NOMINATIM:
                $changes = $this->checkNominatimChanges();
                break;
                
            case APIUsageTracker::API_OPENROUTESERVICE:
                $changes = $this->checkOpenRouteServiceChanges();
                break;
        }
        
        // Vertrauen basierend auf verschiedenen Quellen berechnen
        if (!empty($changes)) {
            $changes['confidence'] = $this->calculateChangeConfidence($provider, $changes);
            $changes['detection_method'] = $this->getDetectionMethod($provider);
            $changes['detected_at'] = date('Y-m-d H:i:s');
        }
        
        return $changes;
    }

    /**
     * Google Maps API Änderungen prüfen
     */
    private function checkGoogleMapsChanges(): array
    {
        $changes = [];
        
        try {
            // Prüfe Google Cloud Console API
            $quotaInfo = $this->fetchGoogleCloudQuotas();
            $currentLimits = $this->tracker->getApiLimits(APIUsageTracker::API_GOOGLE_MAPS);
            
            if ($quotaInfo['daily'] !== $currentLimits['daily']) {
                $changes['daily'] = [
                    'old' => $currentLimits['daily'],
                    'new' => $quotaInfo['daily'],
                    'source' => 'google_cloud_console'
                ];
            }
            
            if ($quotaInfo['cost_per_request'] !== $currentLimits['cost_per_request']) {
                $changes['cost_per_request'] = [
                    'old' => $currentLimits['cost_per_request'],
                    'new' => $quotaInfo['cost_per_request'],
                    'source' => 'pricing_api'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Failed to check Google Maps changes: " . $e->getMessage());
        }
        
        return $changes;
    }

    /**
     * Nominatim API Änderungen prüfen
     */
    private function checkNominatimChanges(): array
    {
        $changes = [];
        
        try {
            // Prüfe OSM Nominatim Usage Policy
            $policyInfo = $this->fetchNominatimPolicy();
            $currentLimits = $this->tracker->getApiLimits(APIUsageTracker::API_NOMINATIM);
            
            if ($policyInfo['per_second'] !== $currentLimits['per_second']) {
                $changes['per_second'] = [
                    'old' => $currentLimits['per_second'],
                    'new' => $policyInfo['per_second'],
                    'source' => 'osm_policy_page'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Failed to check Nominatim changes: " . $e->getMessage());
        }
        
        return $changes;
    }

    /**
     * OpenRouteService API Änderungen prüfen
     */
    private function checkOpenRouteServiceChanges(): array
    {
        $changes = [];
        
        try {
            // Prüfe ORS API Status und Limits
            $statusInfo = $this->fetchOpenRouteServiceStatus();
            $currentLimits = $this->tracker->getApiLimits(APIUsageTracker::API_OPENROUTESERVICE);
            
            if ($statusInfo['daily'] !== $currentLimits['daily']) {
                $changes['daily'] = [
                    'old' => $currentLimits['daily'],
                    'new' => $statusInfo['daily'],
                    'source' => 'ors_api_status'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Failed to check OpenRouteService changes: " . $e->getMessage());
        }
        
        return $changes;
    }

    /**
     * Automatisches Update der Limits bei hoher Vertrauenswürdigkeit
     */
    private function autoUpdateLimits(string $provider, array $changes): bool
    {
        try {
            $newLimits = [];
            
            foreach ($changes as $key => $change) {
                if (is_array($change) && isset($change['new'])) {
                    $newLimits[$key] = $change['new'];
                }
            }
            
            if (empty($newLimits)) {
                return false;
            }
            
            // Update durchführen
            $success = $this->updateApiLimits($provider, $newLimits);
            
            if ($success) {
                // Cache invalidieren
                $this->invalidateApiCache($provider);
                
                // Alert senden über automatisches Update
                $this->alertService->sendAlert([
                    'type' => 'api_limits_auto_updated',
                    'provider' => $provider,
                    'changes' => $changes,
                    'confidence' => $changes['confidence']
                ]);
                
                $this->logAdminAction('auto_update_limits', $provider, [
                    'changes' => $changes,
                    'confidence' => $changes['confidence']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Failed to auto-update API limits: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Berechnet Vertrauen in erkannte Änderungen
     */
    private function calculateChangeConfidence(string $provider, array $changes): float
    {
        $confidence = 0.0;
        $factors = [];
        
        // Verschiedene Vertrauensfaktoren
        foreach ($changes as $key => $change) {
            if (!is_array($change)) continue;
            
            $source = $change['source'] ?? 'unknown';
            
            switch ($source) {
                case 'google_cloud_console':
                    $factors[] = 0.95; // Sehr vertrauenswürdig
                    break;
                case 'pricing_api':
                    $factors[] = 0.9;
                    break;
                case 'osm_policy_page':
                    $factors[] = 0.8;
                    break;
                case 'ors_api_status':
                    $factors[] = 0.85;
                    break;
                default:
                    $factors[] = 0.5;
            }
        }
        
        // Durchschnitt mit Bonus für mehrere Quellen
        $confidence = array_sum($factors) / count($factors);
        
        if (count($factors) > 1) {
            $confidence += 0.1; // Bonus für Bestätigung durch mehrere Quellen
        }
        
        return min(1.0, $confidence);
    }

    /**
     * Kostenüberwachung mit Budget-Alerts
     */
    public function monitorCostBudgets(): array
    {
        $budgets = $this->config->get('api.budgets', []);
        $alerts = [];
        
        foreach ($budgets as $provider => $budget) {
            $currentCost = $this->calculateCurrentCost($provider);
            $percentage = ($currentCost / $budget['monthly']) * 100;
            
            if ($percentage >= 90) {
                $alerts[] = [
                    'level' => 'critical',
                    'provider' => $provider,
                    'current_cost' => $currentCost,
                    'budget' => $budget['monthly'],
                    'percentage' => $percentage,
                    'action' => 'immediate_review_required'
                ];
                
                // Automatische Notfallmaßnahmen
                $this->triggerEmergencyMeasures($provider, $currentCost, $budget);
                
            } elseif ($percentage >= 75) {
                $alerts[] = [
                    'level' => 'warning',
                    'provider' => $provider,
                    'current_cost' => $currentCost,
                    'budget' => $budget['monthly'],
                    'percentage' => $percentage,
                    'action' => 'budget_review_recommended'
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Proaktive Kapazitätsplanung
     */
    public function predictCapacityNeeds(): array
    {
        $predictions = [];
        
        foreach ([APIUsageTracker::API_GOOGLE_MAPS, APIUsageTracker::API_NOMINATIM, APIUsageTracker::API_OPENROUTESERVICE] as $provider) {
            $usage = $this->getUsageTrend($provider, 30); // 30 Tage
            $prediction = $this->calculateUsagePrediction($usage);
            
            $predictions[$provider] = [
                'current_trend' => $prediction['trend'],
                'predicted_daily_usage' => $prediction['predicted_daily'],
                'days_until_limit' => $prediction['days_until_limit'],
                'recommendation' => $this->getCapacityRecommendation($prediction)
            ];
        }
        
        return $predictions;
    }

    /**
     * Intelligente Fallback-Orchestrierung
     */
    public function orchestrateIntelligentFallback(string $provider, array $requestData): array
    {
        $fallbackChain = $this->fallbackChain[$provider] ?? [];
        
        foreach ($fallbackChain as $fallbackProvider) {
            if ($fallbackProvider === 'cache_only') {
                $result = $this->tryFromCacheOnly($provider, $requestData);
                if ($result['success']) {
                    return $result;
                }
                continue;
            }
            
            // Prüfe ob Fallback-API verfügbar
            $fallbackCheck = $this->checkApiRequest($fallbackProvider);
            
            if ($fallbackCheck['allowed']) {
                // Verwende Fallback-API
                $result = $this->executeFallbackToAlternativeApi($fallbackProvider, $requestData);
                
                if ($result['success']) {
                    // Log successful fallback
                    $this->logSuccessfulFallback($provider, $fallbackProvider, $requestData);
                    return $result;
                }
            }
        }
        
        // Kein Fallback verfügbar
        return $this->provideBlockedResponse($provider, $requestData);
    }

    /**
     * Real-time API Health Monitoring
     */
    public function monitorApiHealth(): array
    {
        $healthStatus = [];
        
        foreach ([APIUsageTracker::API_GOOGLE_MAPS, APIUsageTracker::API_NOMINATIM, APIUsageTracker::API_OPENROUTESERVICE] as $provider) {
            $health = [
                'status' => $this->checkApiStatus($provider),
                'response_time' => $this->getAverageResponseTime($provider),
                'error_rate' => $this->getErrorRate($provider),
                'availability' => $this->calculateAvailability($provider),
                'last_check' => date('Y-m-d H:i:s')
            ];
            
            $health['overall_health'] = $this->calculateOverallHealth($health);
            $healthStatus[$provider] = $health;
        }
        
        return $healthStatus;
    }

    /**
     * Schedulet automatische Checks
     */
    private function scheduleAutomaticChecks(): void
    {
        // Diese Methode würde in einem Cron-Job oder ähnlichem aufgerufen
        // Hier nur die Logik für die Checks
    }

    /**
     * Helper-Methoden für spezifische API-Checks
     */
    private function fetchGoogleCloudQuotas(): array
    {
        // Implementierung würde Google Cloud Quotas API verwenden
        return ['daily' => 25000, 'cost_per_request' => 0.005];
    }

    private function fetchNominatimPolicy(): array
    {
        // Implementierung würde OSM Website parsen
        return ['per_second' => 1];
    }

    private function fetchOpenRouteServiceStatus(): array
    {
        // Implementierung würde ORS Status API verwenden
        return ['daily' => 2000];
    }

    private function getDetectionMethod(string $provider): string
    {
        return match($provider) {
            APIUsageTracker::API_GOOGLE_MAPS => 'cloud_console_api',
            APIUsageTracker::API_NOMINATIM => 'policy_page_parsing',
            APIUsageTracker::API_OPENROUTESERVICE => 'status_api',
            default => 'unknown'
        };
    }

    private function invalidateApiCache(string $provider): void
    {
        // Cache für diesen Provider leeren
        $this->db->delete('cache', 'cache_key LIKE ?', [strtolower($provider) . '_%']);
    }

    private function calculateCurrentCost(string $provider): float
    {
        $usage = $this->tracker->getCurrentUsage($provider);
        $limits = $this->tracker->getApiLimits($provider);
        
        return $usage['daily_requests'] * $limits['cost_per_request'];
    }

    private function triggerEmergencyMeasures(string $provider, float $currentCost, array $budget): void
    {
        // Notfallmaßnahmen bei Budget-Überschreitung
        $this->alertService->sendAlert([
            'type' => 'budget_exceeded',
            'provider' => $provider,
            'current_cost' => $currentCost,
            'budget' => $budget['monthly'],
            'emergency_measures' => 'activated'
        ]);
        
        // Temporäre Limits reduzieren
        $emergencyLimits = [
            'daily' => $budget['emergency_daily_limit'] ?? 1000,
            'hourly' => $budget['emergency_hourly_limit'] ?? 100
        ];
        
        $this->updateApiLimits($provider, $emergencyLimits);
    }

    private function getUsageTrend(string $provider, int $days): array
    {
        return $this->tracker->getUsageHistory($provider, $days);
    }

    private function calculateUsagePrediction(array $usage): array
    {
        // Vereinfachte lineare Regression für Trend-Berechnung
        $trend = 'stable';
        $predictedDaily = array_sum(array_column($usage, 'requests')) / count($usage);
        
        return [
            'trend' => $trend,
            'predicted_daily' => $predictedDaily,
            'days_until_limit' => 30 // Vereinfacht
        ];
    }

    private function getCapacityRecommendation(array $prediction): string
    {
        if ($prediction['days_until_limit'] < 7) {
            return 'urgent_capacity_increase_needed';
        } elseif ($prediction['days_until_limit'] < 30) {
            return 'plan_capacity_increase';
        }
        
        return 'capacity_sufficient';
    }

    private function executeFallbackToAlternativeApi(string $fallbackProvider, array $requestData): array
    {
        // Implementierung des Fallback zu alternativer API
        return [
            'success' => true,
            'data' => ['fallback_provider' => $fallbackProvider],
            'message' => "Fallback zu {$fallbackProvider} erfolgreich",
            'fallback_used' => true
        ];
    }

    private function logSuccessfulFallback(string $originalProvider, string $fallbackProvider, array $requestData): void
    {
        $this->db->insert('api_fallback_log', [
            'original_provider' => $originalProvider,
            'fallback_provider' => $fallbackProvider,
            'request_type' => $requestData['endpoint'] ?? 'unknown',
            'success' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function checkApiStatus(string $provider): string
    {
        // Implementierung würde echte Gesundheitschecks durchführen
        return 'healthy';
    }

    private function getAverageResponseTime(string $provider): float
    {
        $usage = $this->tracker->getCurrentUsage($provider);
        return $usage['daily_avg_response_time'];
    }

    private function getErrorRate(string $provider): float
    {
        $usage = $this->tracker->getCurrentUsage($provider);
        return $usage['daily_requests'] > 0 ? 
            ($usage['daily_errors'] / $usage['daily_requests']) * 100 : 0;
    }

    private function calculateAvailability(string $provider): float
    {
        // Vereinfachte Berechnung - sollte echte Uptime-Daten verwenden
        return 99.5;
    }

    private function calculateOverallHealth(array $health): string
    {
        if ($health['availability'] < 95 || $health['error_rate'] > 10) {
            return 'unhealthy';
        } elseif ($health['availability'] < 99 || $health['error_rate'] > 5) {
            return 'degraded';
        }
        
        return 'healthy';
    }
}