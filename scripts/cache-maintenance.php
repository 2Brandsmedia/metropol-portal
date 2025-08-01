#!/usr/bin/env php
<?php

/**
 * Cache-Wartungs-Skript für automatisches Warming und Invalidierung
 * 
 * Sollte als Cron-Job alle 5-15 Minuten ausgeführt werden:
 * */5 * * * * /usr/bin/php /path/to/cache-maintenance.php
 * 
 * @author 2Brands Media GmbH
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\CacheAgent;
use App\Agents\GeoAgent;
use App\Agents\GoogleMapsAgent;
use App\Agents\RouteAgent;
use App\Services\CacheWarmingService;
use App\Services\CacheInvalidationService;

// CLI-only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line');
}

class CacheMaintenanceRunner 
{
    private Database $db;
    private Config $config;
    private array $stats = [];
    private bool $verbose = false;

    public function __construct()
    {
        $this->config = new Config();
        $this->db = new Database($this->config);
        $this->verbose = in_array('-v', $GLOBALS['argv']) || in_array('--verbose', $GLOBALS['argv']);
        
        $this->log("Cache-Wartung gestartet um " . date('Y-m-d H:i:s'));
    }

    public function run(): void
    {
        try {
            // 1. Cache-Invalidierung
            $this->runInvalidation();
            
            // 2. Cache-Warming (nur zu bestimmten Zeiten)
            if ($this->shouldRunWarming()) {
                $this->runWarming();
            }
            
            // 3. Cache-Bereinigung
            $this->runCleanup();
            
            // 4. Statistiken aktualisieren
            $this->updateStatistics();
            
            // 5. Performance-Report generieren (täglich)
            if ($this->shouldGenerateReport()) {
                $this->generatePerformanceReport();
            }
            
            $this->log("Cache-Wartung erfolgreich abgeschlossen");
            $this->printSummary();
            
        } catch (Exception $e) {
            $this->log("FEHLER: " . $e->getMessage(), true);
            exit(1);
        }
    }

    private function runInvalidation(): void
    {
        $this->log("Starte intelligente Cache-Invalidierung...");
        
        $cacheAgent = new CacheAgent($this->db, $this->config);
        $invalidationService = new CacheInvalidationService($this->db, $this->config, $cacheAgent);
        
        $results = $invalidationService->runIntelligentInvalidation();
        
        $this->stats['invalidation'] = $results;
        $this->log("Invalidierung: {$results['invalidated']} von {$results['total_checked']} Einträgen invalidiert");
        
        if ($this->verbose && !empty($results['strategies_applied'])) {
            foreach ($results['strategies_applied'] as $strategy) {
                $this->log("  - {$strategy['strategy']}: {$strategy['invalidated']} invalidiert");
            }
        }
    }

    private function runWarming(): void
    {
        $this->log("Starte intelligentes Cache-Warming...");
        
        $cacheAgent = new CacheAgent($this->db, $this->config);
        $geoAgent = new GeoAgent($this->db, $this->config, $cacheAgent);
        $mapsAgent = new GoogleMapsAgent($this->db, $this->config);
        $routeAgent = new RouteAgent($this->db, $this->config);
        
        $warmingService = new CacheWarmingService(
            $this->db, $this->config, $cacheAgent, $geoAgent, $mapsAgent, $routeAgent
        );
        
        $results = $warmingService->executeWarmingStrategy();
        
        $this->stats['warming'] = $results;
        $this->log("Warming: {$results['successful_warmings']} erfolgreich, {$results['failed_warmings']} fehlgeschlagen");
        $this->log("API-Aufrufe: {$results['api_calls_made']}, Geschätzte Kosten: {$results['estimated_cost']}€");
        
        if ($this->verbose && !empty($results['strategies_used'])) {
            $this->log("Verwendete Strategien: " . implode(', ', $results['strategies_used']));
        }
    }

    private function runCleanup(): void
    {
        $this->log("Starte Cache-Bereinigung...");
        
        // Abgelaufene Cache-Einträge löschen
        $expiredDeleted = $this->db->delete('enhanced_cache', 'expires_at < NOW()');
        
        // Alte Warming-Queue-Einträge löschen
        $oldWarmingDeleted = $this->db->delete(
            'cache_warming_queue',
            'status IN ("completed", "failed") 
             AND processed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        
        // Alte Invalidation-Logs löschen (> 30 Tage)
        $oldInvalidationsDeleted = $this->db->delete(
            'cache_invalidations',
            'invalidated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        
        // Alte Cache-Statistiken bereinigen (> 90 Tage)
        $oldStatsDeleted = $this->db->delete(
            'cache_stats',
            'date_key < DATE_SUB(CURDATE(), INTERVAL 90 DAY)'
        );
        
        $this->stats['cleanup'] = [
            'expired_cache_entries' => $expiredDeleted,
            'old_warming_jobs' => $oldWarmingDeleted,
            'old_invalidations' => $oldInvalidationsDeleted,
            'old_statistics' => $oldStatsDeleted
        ];
        
        $totalDeleted = $expiredDeleted + $oldWarmingDeleted + $oldInvalidationsDeleted + $oldStatsDeleted;
        $this->log("Bereinigung: {$totalDeleted} alte Einträge entfernt");
    }

    private function updateStatistics(): void
    {
        $this->log("Aktualisiere Cache-Statistiken...");
        
        // Tägliche Statistiken für heute aktualisieren
        $today = date('Y-m-d');
        
        $stats = $this->db->select(
            "SELECT 
                cache_type,
                cache_layer,
                COUNT(*) as entries,
                SUM(hit_count) as total_hits,
                SUM(miss_count) as total_misses,
                AVG(hit_rate) as avg_hit_rate,
                SUM(data_size_bytes) / 1024 / 1024 as size_mb,
                SUM(api_cost) as cost_saved
             FROM enhanced_cache 
             WHERE DATE(created_at) = ?
             GROUP BY cache_type, cache_layer",
            [$today]
        );
        
        foreach ($stats as $stat) {
            $this->db->query(
                "INSERT INTO cache_stats (
                    date_key, cache_type, cache_layer, total_requests,
                    cache_hits, cache_misses, total_data_size_mb, estimated_cost_saved
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_requests = VALUES(total_requests),
                    cache_hits = VALUES(cache_hits),
                    cache_misses = VALUES(cache_misses),
                    total_data_size_mb = VALUES(total_data_size_mb),
                    estimated_cost_saved = VALUES(estimated_cost_saved)",
                [
                    $today,
                    $stat['cache_type'],
                    $stat['cache_layer'],
                    $stat['total_hits'] + $stat['total_misses'],
                    $stat['total_hits'],
                    $stat['total_misses'],
                    $stat['size_mb'],
                    $stat['cost_saved']
                ]
            );
        }
        
        $this->log("Statistiken für " . count($stats) . " Cache-Type/Layer-Kombinationen aktualisiert");
    }

    private function generatePerformanceReport(): void
    {
        $this->log("Generiere täglichen Performance-Report...");
        
        // Performance-Metriken der letzten 24h sammeln
        $metrics = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_cache_entries,
                SUM(hit_count) as total_hits,
                SUM(miss_count) as total_misses,
                AVG(hit_rate) as avg_hit_rate,
                SUM(api_cost) as total_cost_saved,
                SUM(data_size_bytes) / 1024 / 1024 as total_size_mb
             FROM enhanced_cache 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Warming-Statistiken
        $warmingStats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_jobs,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                AVG(estimated_cost) as avg_cost
             FROM cache_warming_queue 
             WHERE scheduled_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Invalidierung-Statistiken
        $invalidationStats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_invalidations,
                COUNT(DISTINCT strategy) as strategies_used
             FROM cache_invalidations 
             WHERE invalidated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        $totalRequests = ($metrics['total_hits'] ?? 0) + ($metrics['total_misses'] ?? 0);
        $hitRate = $totalRequests > 0 ? (($metrics['total_hits'] ?? 0) / $totalRequests) * 100 : 0;
        $warmingSuccessRate = ($warmingStats['total_jobs'] ?? 0) > 0 
            ? (($warmingStats['completed'] ?? 0) / $warmingStats['total_jobs']) * 100 
            : 0;
        
        $report = "
=== METROPOL PORTAL - TÄGLICHER CACHE-PERFORMANCE-REPORT ===
Datum: " . date('Y-m-d H:i:s') . "

CACHE-STATISTIKEN (24h):
- Cache-Einträge: " . number_format($metrics['total_cache_entries'] ?? 0) . "
- Cache-Hits: " . number_format($metrics['total_hits'] ?? 0) . "
- Cache-Misses: " . number_format($metrics['total_misses'] ?? 0) . "
- Hit-Rate: " . round($hitRate, 2) . "%
- Gespeicherte API-Kosten: " . number_format($metrics['total_cost_saved'] ?? 0, 4) . "€
- Cache-Größe: " . round($metrics['total_size_mb'] ?? 0, 2) . " MB

CACHE-WARMING:
- Warming-Jobs: " . ($warmingStats['total_jobs'] ?? 0) . "
- Erfolgreich: " . ($warmingStats['completed'] ?? 0) . "
- Fehlgeschlagen: " . ($warmingStats['failed'] ?? 0) . "
- Erfolgsrate: " . round($warmingSuccessRate, 2) . "%
- Ø Kosten pro Job: " . number_format($warmingStats['avg_cost'] ?? 0, 4) . "€

CACHE-INVALIDIERUNG:
- Invalidierungen: " . ($invalidationStats['total_invalidations'] ?? 0) . "
- Verwendete Strategien: " . ($invalidationStats['strategies_used'] ?? 0) . "

PERFORMANCE-BEWERTUNG:
";
        
        // Performance-Bewertung
        if ($hitRate >= 80) {
            $report .= "✅ AUSGEZEICHNET: Hit-Rate über 80%\n";
        } elseif ($hitRate >= 60) {
            $report .= "⚠️  GUT: Hit-Rate über 60%, aber Optimierungspotential vorhanden\n";
        } else {
            $report .= "❌ KRITISCH: Hit-Rate unter 60%, dringende Optimierung erforderlich!\n";
        }
        
        if ($warmingSuccessRate >= 90) {
            $report .= "✅ Cache-Warming läuft stabil\n";
        } elseif ($warmingSuccessRate >= 70) {
            $report .= "⚠️  Cache-Warming teilweise problematisch\n";
        } else {
            $report .= "❌ Cache-Warming stark beeinträchtigt!\n";
        }
        
        $costSavings = $metrics['total_cost_saved'] ?? 0;
        if ($costSavings > 10) {
            $report .= "✅ Hohe Kosteneinsparungen: {$costSavings}€\n";
        } elseif ($costSavings > 1) {
            $report .= "⚠️  Moderate Kosteneinsparungen: {$costSavings}€\n";
        } else {
            $report .= "❌ Niedrige Kosteneinsparungen: {$costSavings}€\n";
        }
        
        $report .= "\n=== ENDE REPORT ===\n";
        
        // Report in Datei speichern
        $reportFile = __DIR__ . '/../logs/cache-performance-' . date('Y-m-d') . '.txt';
        file_put_contents($reportFile, $report);
        
        $this->log("Performance-Report gespeichert: {$reportFile}");
        
        // Bei kritischen Werten E-Mail senden (vereinfacht)
        if ($hitRate < 50 || $warmingSuccessRate < 50) {
            $this->sendAlertEmail($report);
        }
    }

    private function shouldRunWarming(): bool
    {
        $hour = (int)date('H');
        $minute = (int)date('i');
        
        // Warming nur zu bestimmten Zeiten:
        // - Alle 15 Minuten während Geschäftszeiten (7-19 Uhr)
        // - Alle 30 Minuten außerhalb der Geschäftszeiten
        // - Intensives Warming alle 5 Minuten während Rush-Hour (7-9, 17-19 Uhr)
        
        $isBusinessHours = $hour >= 7 && $hour <= 19;
        $isRushHour = ($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19);
        
        if ($isRushHour && $minute % 5 === 0) {
            return true; // Rush-Hour: alle 5 Minuten
        } elseif ($isBusinessHours && $minute % 15 === 0) {
            return true; // Geschäftszeiten: alle 15 Minuten
        } elseif (!$isBusinessHours && $minute % 30 === 0) {
            return true; // Außerhalb: alle 30 Minuten
        }
        
        return false;
    }

    private function shouldGenerateReport(): bool
    {
        // Report täglich um Mitternacht generieren
        $hour = (int)date('H');
        $minute = (int)date('i');
        
        return $hour === 0 && $minute <= 5; // Zwischen 00:00 und 00:05
    }

    private function sendAlertEmail(string $report): void
    {
        // Vereinfachte E-Mail-Funktion
        $adminEmails = $this->config->get('admin.emails', []);
        
        foreach ($adminEmails as $email) {
            $subject = "ALERT: Cache-Performance kritisch - Metropol Portal";
            $headers = "From: noreply@metropol-portal.de\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            @mail($email, $subject, $report, $headers);
        }
        
        $this->log("ALERT-E-Mail an Administratoren gesendet");
    }

    private function log(string $message, bool $isError = false): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $level = $isError ? 'ERROR' : 'INFO';
        $logMessage = "[{$timestamp}] {$level}: {$message}";
        
        if ($this->verbose || $isError) {
            echo $logMessage . "\n";
        }
        
        // In Log-Datei schreiben
        $logFile = __DIR__ . '/../logs/cache-maintenance.log';
        file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
    }

    private function printSummary(): void
    {
        echo "\n=== CACHE-WARTUNG ZUSAMMENFASSUNG ===\n";
        
        if (isset($this->stats['invalidation'])) {
            $inv = $this->stats['invalidation'];
            echo "Invalidierung: {$inv['invalidated']} von {$inv['total_checked']} Einträgen\n";
        }
        
        if (isset($this->stats['warming'])) {
            $warm = $this->stats['warming'];
            echo "Warming: {$warm['successful_warmings']} erfolgreich, {$warm['failed_warmings']} fehlgeschlagen\n";
        }
        
        if (isset($this->stats['cleanup'])) {
            $clean = $this->stats['cleanup'];
            $total = array_sum($clean);
            echo "Bereinigung: {$total} alte Einträge entfernt\n";
        }
        
        echo "=== WARTUNG ABGESCHLOSSEN ===\n\n";
    }
}

// Skript ausführen
$runner = new CacheMaintenanceRunner();
$runner->run();