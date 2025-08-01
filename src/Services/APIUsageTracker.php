<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use Exception;
use PDO;

/**
 * APIUsageTracker - Zentrale Überwachung aller API-Nutzung
 * 
 * Verfolgt die Nutzung von:
 * - Google Maps API (25k/Tag)
 * - Nominatim OSM (1 req/sec)
 * - OpenRouteService API
 * 
 * @author 2Brands Media GmbH
 */
class APIUsageTracker
{
    private Database $db;
    private Config $config;
    
    // API-Konstanten
    public const API_GOOGLE_MAPS = 'google_maps';
    public const API_NOMINATIM = 'nominatim';
    public const API_OPENROUTESERVICE = 'openrouteservice';
    
    // Limit-Konstanten
    private const DEFAULT_LIMITS = [
        self::API_GOOGLE_MAPS => [
            'daily' => 25000,
            'hourly' => 2500,
            'per_second' => 50,
            'cost_per_request' => 0.005 // Euro
        ],
        self::API_NOMINATIM => [
            'daily' => 86400, // 1 req/sec für 24h
            'hourly' => 3600,
            'per_second' => 1,
            'cost_per_request' => 0.0 // Kostenlos
        ],
        self::API_OPENROUTESERVICE => [
            'daily' => 2000,
            'hourly' => 500,
            'per_second' => 5,
            'cost_per_request' => 0.0 // Kostenlos Plan
        ]
    ];
    
    // Warning-Level
    public const WARNING_YELLOW = 0.8; // 80%
    public const WARNING_RED = 0.9;    // 90%
    public const BLOCK_LEVEL = 0.95;   // 95%

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        
        // Sicherstellen dass Tabelle existiert
        $this->ensureTableExists();
    }

    /**
     * Verfolgt eine API-Anfrage
     */
    public function trackRequest(string $apiProvider, string $endpoint, bool $success = true, float $responseTime = 0.0): void
    {
        $now = new \DateTime();
        $dateKey = $now->format('Y-m-d');
        $hourKey = $now->format('Y-m-d H:00:00');
        
        try {
            // Tägliche Statistik
            $this->incrementUsage($apiProvider, $endpoint, 'daily', $dateKey, $success, $responseTime);
            
            // Stündliche Statistik
            $this->incrementUsage($apiProvider, $endpoint, 'hourly', $hourKey, $success, $responseTime);
            
            // Rate-Limiting prüfen nach dem Tracking
            $this->checkRateLimits($apiProvider);
            
        } catch (Exception $e) {
            error_log("API Usage Tracking failed: " . $e->getMessage());
        }
    }

    /**
     * Prüft ob API-Anfrage erlaubt ist
     */
    public function isRequestAllowed(string $apiProvider): array
    {
        $limits = $this->getApiLimits($apiProvider);
        $usage = $this->getCurrentUsage($apiProvider);
        
        $result = [
            'allowed' => true,
            'warning_level' => null,
            'message' => null,
            'retry_after' => null,
            'usage' => $usage,
            'limits' => $limits
        ];
        
        // Daily Limit prüfen
        $dailyRatio = $usage['daily_requests'] / $limits['daily'];
        if ($dailyRatio >= self::BLOCK_LEVEL) {
            $result['allowed'] = false;
            $result['message'] = "Tägliches API-Limit erreicht ({$usage['daily_requests']}/{$limits['daily']})";
            $result['retry_after'] = $this->getSecondsUntilMidnight();
        } elseif ($dailyRatio >= self::WARNING_RED) {
            $result['warning_level'] = 'red';
            $result['message'] = "Tägliches API-Limit fast erreicht ({$usage['daily_requests']}/{$limits['daily']})";
        } elseif ($dailyRatio >= self::WARNING_YELLOW) {
            $result['warning_level'] = 'yellow';
            $result['message'] = "Tägliches API-Limit zu 80% erreicht ({$usage['daily_requests']}/{$limits['daily']})";
        }
        
        // Hourly Limit prüfen
        $hourlyRatio = $usage['hourly_requests'] / $limits['hourly'];
        if ($hourlyRatio >= self::BLOCK_LEVEL && $result['allowed']) {
            $result['allowed'] = false;
            $result['message'] = "Stündliches API-Limit erreicht ({$usage['hourly_requests']}/{$limits['hourly']})";
            $result['retry_after'] = $this->getSecondsUntilNextHour();
        }
        
        // Per-Second Limit prüfen (für Nominatim)
        if ($apiProvider === self::API_NOMINATIM) {
            $lastRequest = $this->getLastRequestTime($apiProvider);
            if ($lastRequest && (time() - $lastRequest) < 1) {
                $result['allowed'] = false;
                $result['message'] = "Rate-Limit: Max. 1 Anfrage pro Sekunde";
                $result['retry_after'] = 1;
            }
        }
        
        return $result;
    }

    /**
     * Holt aktuelle API-Nutzung
     */
    public function getCurrentUsage(string $apiProvider): array
    {
        $now = new \DateTime();
        $dateKey = $now->format('Y-m-d');
        $hourKey = $now->format('Y-m-d H:00:00');
        
        // Tägliche Nutzung
        $dailyUsage = $this->db->selectOne(
            'SELECT * FROM api_usage 
             WHERE api_provider = ? AND period_type = "daily" AND period_key = ?',
            [$apiProvider, $dateKey]
        );
        
        // Stündliche Nutzung
        $hourlyUsage = $this->db->selectOne(
            'SELECT * FROM api_usage 
             WHERE api_provider = ? AND period_type = "hourly" AND period_key = ?',
            [$apiProvider, $hourKey]
        );
        
        return [
            'daily_requests' => (int) ($dailyUsage['request_count'] ?? 0),
            'daily_errors' => (int) ($dailyUsage['error_count'] ?? 0),
            'daily_avg_response_time' => (float) ($dailyUsage['avg_response_time'] ?? 0),
            'hourly_requests' => (int) ($hourlyUsage['request_count'] ?? 0),
            'hourly_errors' => (int) ($hourlyUsage['error_count'] ?? 0),
            'hourly_avg_response_time' => (float) ($hourlyUsage['avg_response_time'] ?? 0)
        ];
    }

    /**
     * Holt alle API-Statistiken für Dashboard
     */
    public function getAllUsageStats(): array
    {
        $apis = [self::API_GOOGLE_MAPS, self::API_NOMINATIM, self::API_OPENROUTESERVICE];
        $stats = [];
        
        foreach ($apis as $api) {
            $usage = $this->getCurrentUsage($api);
            $limits = $this->getApiLimits($api);
            $checkResult = $this->isRequestAllowed($api);
            
            $stats[$api] = [
                'usage' => $usage,
                'limits' => $limits,
                'warning_level' => $checkResult['warning_level'],
                'message' => $checkResult['message'],
                'daily_percentage' => round(($usage['daily_requests'] / $limits['daily']) * 100, 1),
                'hourly_percentage' => round(($usage['hourly_requests'] / $limits['hourly']) * 100, 1),
                'estimated_daily_cost' => $usage['daily_requests'] * $limits['cost_per_request']
            ];
        }
        
        return $stats;
    }

    /**
     * Holt API-Nutzungshistorie
     */
    public function getUsageHistory(string $apiProvider, int $days = 7): array
    {
        $history = $this->db->select(
            'SELECT period_key as date, request_count, error_count, avg_response_time
             FROM api_usage 
             WHERE api_provider = ? AND period_type = "daily" 
             AND period_key >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY period_key DESC',
            [$apiProvider, $days]
        );
        
        return array_map(function($row) {
            return [
                'date' => $row['date'],
                'requests' => (int) $row['request_count'],
                'errors' => (int) $row['error_count'],
                'avg_response_time' => (float) $row['avg_response_time']
            ];
        }, $history);
    }

    /**
     * Sendet Warnung bei Limit-Überschreitung
     */
    public function sendLimitWarning(string $apiProvider, string $level, array $usage): bool
    {
        $limits = $this->getApiLimits($apiProvider);
        $percentage = round(($usage['daily_requests'] / $limits['daily']) * 100, 1);
        
        $subject = "API Limit Warnung - {$apiProvider} ({$percentage}%)";
        $message = $this->buildWarningMessage($apiProvider, $level, $usage, $limits);
        
        // E-Mail an Admins senden (implementiert in NotificationService)
        try {
            $adminEmails = $this->config->get('admin.emails', []);
            foreach ($adminEmails as $email) {
                $this->sendEmail($email, $subject, $message);
            }
            
            // In Audit-Log eintragen
            $this->logWarning($apiProvider, $level, $usage);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send API limit warning: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bereinigt alte API-Statistiken
     */
    public function cleanOldStats(int $daysToKeep = 30): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        return $this->db->delete(
            'api_usage',
            'period_type = "daily" AND period_key < ?',
            [$cutoffDate]
        );
    }

    /**
     * Holt API-Limits (auch öffentlich verfügbar für andere Services)
     */
    public function getApiLimits(string $apiProvider): array
    {
        $configLimits = $this->config->get("api_limits.{$apiProvider}", []);
        $defaultLimits = self::DEFAULT_LIMITS[$apiProvider] ?? [];
        
        return array_merge($defaultLimits, $configLimits);
    }

    /**
     * Private Hilfsmethoden
     */
    private function incrementUsage(string $apiProvider, string $endpoint, string $periodType, string $periodKey, bool $success, float $responseTime): void
    {
        $this->db->query(
            'INSERT INTO api_usage (
                api_provider, endpoint, period_type, period_key, 
                request_count, error_count, total_response_time, updated_at
             ) VALUES (?, ?, ?, ?, 1, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                request_count = request_count + 1,
                error_count = error_count + ?,
                total_response_time = total_response_time + ?,
                avg_response_time = total_response_time / request_count,
                updated_at = NOW()',
            [
                $apiProvider, $endpoint, $periodType, $periodKey,
                $success ? 0 : 1, $responseTime,
                $success ? 0 : 1, $responseTime
            ]
        );
        
        // Letzte Request-Zeit für Rate-Limiting updaten
        $this->updateLastRequestTime($apiProvider);
    }


    private function checkRateLimits(string $apiProvider): void
    {
        $checkResult = $this->isRequestAllowed($apiProvider);
        
        if ($checkResult['warning_level'] === 'red' && !$this->wasWarningRecentlySent($apiProvider, 'red')) {
            $this->sendLimitWarning($apiProvider, 'red', $checkResult['usage']);
        } elseif ($checkResult['warning_level'] === 'yellow' && !$this->wasWarningRecentlySent($apiProvider, 'yellow')) {
            $this->sendLimitWarning($apiProvider, 'yellow', $checkResult['usage']);
        }
    }

    private function wasWarningRecentlySent(string $apiProvider, string $level): bool
    {
        $recentWarning = $this->db->selectOne(
            'SELECT id FROM api_warnings 
             WHERE api_provider = ? AND warning_level = ? 
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$apiProvider, $level]
        );
        
        return $recentWarning !== null;
    }

    private function getLastRequestTime(string $apiProvider): ?int
    {
        $result = $this->db->selectOne(
            'SELECT UNIX_TIMESTAMP(last_request_at) as last_request 
             FROM api_rate_limits WHERE api_provider = ?',
            [$apiProvider]
        );
        
        return $result ? (int) $result['last_request'] : null;
    }

    private function updateLastRequestTime(string $apiProvider): void
    {
        $this->db->query(
            'INSERT INTO api_rate_limits (api_provider, last_request_at) 
             VALUES (?, NOW()) 
             ON DUPLICATE KEY UPDATE last_request_at = NOW()',
            [$apiProvider]
        );
    }

    private function getSecondsUntilMidnight(): int
    {
        $now = new \DateTime();
        $midnight = new \DateTime('tomorrow');
        return $midnight->getTimestamp() - $now->getTimestamp();
    }

    private function getSecondsUntilNextHour(): int
    {
        $now = new \DateTime();
        $nextHour = new \DateTime($now->format('Y-m-d H:00:00'));
        $nextHour->add(new \DateInterval('PT1H'));
        return $nextHour->getTimestamp() - $now->getTimestamp();
    }

    private function buildWarningMessage(string $apiProvider, string $level, array $usage, array $limits): string
    {
        $percentage = round(($usage['daily_requests'] / $limits['daily']) * 100, 1);
        
        $message = "API Limit Warnung für {$apiProvider}\n\n";
        $message .= "Warnstufe: " . strtoupper($level) . "\n";
        $message .= "Tägliche Nutzung: {$usage['daily_requests']} / {$limits['daily']} ({$percentage}%)\n";
        $message .= "Stündliche Nutzung: {$usage['hourly_requests']} / {$limits['hourly']}\n";
        $message .= "Geschätzte Kosten heute: " . number_format($usage['daily_requests'] * $limits['cost_per_request'], 2) . " EUR\n\n";
        
        if ($level === 'red') {
            $message .= "ACHTUNG: Bei 95% wird die API automatisch blockiert!\n";
        }
        
        $message .= "Zeitpunkt: " . date('Y-m-d H:i:s') . "\n";
        $message .= "System: Metropol Portal\n";
        
        return $message;
    }

    private function sendEmail(string $to, string $subject, string $message): void
    {
        // Implementierung mit PHPMailer oder ähnlichem
        // Hier vereinfacht mit mail()
        $headers = "From: " . $this->config->get('mail.from', 'noreply@metropol-portal.de') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        @mail($to, $subject, $message, $headers);
    }

    private function logWarning(string $apiProvider, string $level, array $usage): void
    {
        $this->db->insert('api_warnings', [
            'api_provider' => $apiProvider,
            'warning_level' => $level,
            'daily_requests' => $usage['daily_requests'],
            'hourly_requests' => $usage['hourly_requests'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function ensureTableExists(): void
    {
        // Tabellen werden über Migration erstellt
        // Diese Methode prüft nur ob sie existieren
        try {
            $this->db->selectOne('SELECT 1 FROM api_usage LIMIT 1');
        } catch (Exception $e) {
            error_log("API Usage table not found. Please run migrations.");
        }
    }
}