<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Agents\MonitorAgent;
use App\Services\AlertService;
use Exception;

/**
 * Performance-Analysis-Service für Regression-Detection und historische Datenanalyse
 * 
 * Analysiert Performance-Trends und erkennt Regressionen:
 * - Baseline-Performance-Tracking
 * - Automatische Regression-Detection
 * - Historische Trend-Analyse
 * - Performance-Budget-Überwachung
 * - Predictive Performance-Alerts
 * 
 * @author 2Brands Media GmbH
 */
class PerformanceAnalysisService
{
    private Database $db;
    private MonitorAgent $monitor;
    private AlertService $alertService;
    private array $config;
    
    // Performance-Baselines (ms)
    private array $performanceBaselines = [
        '/api/auth/login' => 100,
        '/api/route/calculate' => 300,
        '/api/playlists' => 200,
        '/api/geo/geocode' => 200,
        'default' => 200
    ];

    public function __construct(Database $db, MonitorAgent $monitor, AlertService $alertService, array $config = [])
    {
        $this->db = $db;
        $this->monitor = $monitor;
        $this->alertService = $alertService;
        $this->config = array_merge([
            'regression_threshold_percent' => 25, // 25% Verschlechterung = Regression
            'trend_analysis_days' => 7,
            'baseline_calculation_days' => 30,
            'min_samples_for_analysis' => 100,
            'seasonal_adjustment' => true,
            'outlier_detection_enabled' => true,
            'outlier_threshold_std_dev' => 2.5,
            'performance_budget_buffer_percent' => 10,
            'enable_predictive_alerts' => true,
        ], $config);
    }

    /**
     * Führt vollständige Performance-Analyse durch
     */
    public function performComprehensiveAnalysis(): array
    {
        try {
            $analysis = [
                'timestamp' => date('c'),
                'baselines' => $this->updatePerformanceBaselines(),
                'regressions' => $this->detectPerformanceRegressions(),
                'trends' => $this->analyzeTrends(),
                'outliers' => $this->detectOutliers(),
                'budget_violations' => $this->checkPerformanceBudgets(),
                'predictions' => $this->generatePerformancePredictions(),
                'recommendations' => []
            ];

            // Recommendations basierend auf Analyse generieren
            $analysis['recommendations'] = $this->generateRecommendations($analysis);

            // Alerts für gefundene Probleme
            $this->processAnalysisAlerts($analysis);

            return $analysis;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'error', [
                'context' => 'comprehensive_performance_analysis'
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Aktualisiert Performance-Baselines basierend auf historischen Daten
     */
    public function updatePerformanceBaselines(): array
    {
        try {
            $baselines = [];
            $daysBack = $this->config['baseline_calculation_days'];

            // Für jeden Endpoint Baseline berechnen
            $endpoints = $this->db->select(
                'SELECT DISTINCT endpoint FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND response_time_ms < 10000', // Extreme Outlier ausschließen
                [$daysBack]
            );

            foreach ($endpoints as $endpointData) {
                $endpoint = $endpointData['endpoint'];
                $baseline = $this->calculateEndpointBaseline($endpoint, $daysBack);
                
                if ($baseline) {
                    $baselines[$endpoint] = $baseline;
                }
            }

            // Baselines in Konfiguration speichern (vereinfacht)
            $this->performanceBaselines = array_merge($this->performanceBaselines, $baselines);

            return $baselines;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'baseline_update'
            ]);
            return [];
        }
    }

    /**
     * Berechnet Baseline für spezifischen Endpoint
     */
    private function calculateEndpointBaseline(string $endpoint, int $daysBack): ?array
    {
        try {
            // Statistische Metriken für Endpoint sammeln
            $stats = $this->db->selectOne(
                'SELECT 
                    COUNT(*) as sample_count,
                    AVG(response_time_ms) as mean,
                    MIN(response_time_ms) as min_time,
                    MAX(response_time_ms) as max_time,
                    STDDEV(response_time_ms) as std_dev
                 FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND response_time_ms < 10000
                 AND status_code < 400',
                [$endpoint, $daysBack]
            );

            if (!$stats || $stats['sample_count'] < $this->config['min_samples_for_analysis']) {
                return null;
            }

            // Percentile berechnen
            $percentiles = $this->calculatePercentiles($endpoint, $daysBack);

            return [
                'endpoint' => $endpoint,
                'mean_ms' => round($stats['mean'], 2),
                'min_ms' => (int) $stats['min_time'],
                'max_ms' => (int) $stats['max_time'],
                'std_dev' => round($stats['std_dev'], 2),
                'p50_ms' => $percentiles['p50'] ?? null,
                'p95_ms' => $percentiles['p95'] ?? null,
                'p99_ms' => $percentiles['p99'] ?? null,
                'sample_count' => (int) $stats['sample_count'],
                'calculated_at' => date('c'),
                'confidence_interval' => $this->calculateConfidenceInterval($stats)
            ];

        } catch (Exception $e) {
            error_log("Failed to calculate baseline for {$endpoint}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Erkennt Performance-Regressionen
     */
    public function detectPerformanceRegressions(): array
    {
        try {
            $regressions = [];
            $comparisonDays = $this->config['trend_analysis_days'];

            foreach ($this->performanceBaselines as $endpoint => $baselineMs) {
                if (is_array($baselineMs)) {
                    $baselineMs = $baselineMs['mean_ms'];
                }

                $regressionData = $this->analyzeEndpointRegression($endpoint, $baselineMs, $comparisonDays);
                
                if ($regressionData && $regressionData['regression_detected']) {
                    $regressions[] = $regressionData;
                }
            }

            return $regressions;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'regression_detection'
            ]);
            return [];
        }
    }

    /**
     * Analysiert Regression für spezifischen Endpoint
     */
    private function analyzeEndpointRegression(string $endpoint, float $baselineMs, int $comparisonDays): ?array
    {
        try {
            // Aktuelle Performance vs. Baseline
            $currentStats = $this->db->selectOne(
                'SELECT 
                    AVG(response_time_ms) as current_mean,
                    COUNT(*) as sample_count,
                    MIN(created_at) as period_start,
                    MAX(created_at) as period_end
                 FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND status_code < 400',
                [$endpoint, $comparisonDays]
            );

            if (!$currentStats || $currentStats['sample_count'] < 10) {
                return null;
            }

            $currentMean = (float) $currentStats['current_mean'];
            $regressionPercent = (($currentMean - $baselineMs) / $baselineMs) * 100;
            $regressionDetected = $regressionPercent > $this->config['regression_threshold_percent'];

            // Trend-Analyse innerhalb des Zeitraums
            $trendAnalysis = $this->analyzeTrendDirection($endpoint, $comparisonDays);

            return [
                'endpoint' => $endpoint,
                'baseline_ms' => $baselineMs,
                'current_mean_ms' => round($currentMean, 2),
                'regression_percent' => round($regressionPercent, 2),
                'regression_detected' => $regressionDetected,
                'severity' => $this->calculateRegressionSeverity($regressionPercent),
                'sample_count' => (int) $currentStats['sample_count'],
                'analysis_period' => [
                    'start' => $currentStats['period_start'],
                    'end' => $currentStats['period_end'],
                    'days' => $comparisonDays
                ],
                'trend_analysis' => $trendAnalysis,
                'statistical_significance' => $this->calculateStatisticalSignificance($endpoint, $baselineMs, $currentMean)
            ];

        } catch (Exception $e) {
            error_log("Failed to analyze regression for {$endpoint}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Analysiert Performance-Trends
     */
    public function analyzeTrends(): array
    {
        try {
            $trends = [];
            $daysBack = $this->config['trend_analysis_days'];

            // Tägliche Trend-Daten sammeln
            $dailyTrends = $this->db->select(
                'SELECT 
                    endpoint,
                    DATE(created_at) as trend_date,
                    AVG(response_time_ms) as daily_avg,
                    COUNT(*) as daily_count,
                    MIN(response_time_ms) as daily_min,
                    MAX(response_time_ms) as daily_max
                 FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND status_code < 400
                 GROUP BY endpoint, DATE(created_at)
                 ORDER BY endpoint, trend_date',
                [$daysBack]
            );

            // Nach Endpoint gruppieren und Trends berechnen
            $groupedTrends = [];
            foreach ($dailyTrends as $trend) {
                $endpoint = $trend['endpoint'];
                if (!isset($groupedTrends[$endpoint])) {
                    $groupedTrends[$endpoint] = [];
                }
                $groupedTrends[$endpoint][] = $trend;
            }

            foreach ($groupedTrends as $endpoint => $endpointTrends) {
                if (count($endpointTrends) >= 3) { // Mindestens 3 Datenpunkte für Trend
                    $trendAnalysis = $this->calculateTrendMetrics($endpointTrends);
                    $trends[$endpoint] = $trendAnalysis;
                }
            }

            return $trends;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'trend_analysis'
            ]);
            return [];
        }
    }

    /**
     * Erkennt Performance-Outliers
     */
    public function detectOutliers(): array
    {
        if (!$this->config['outlier_detection_enabled']) {
            return [];
        }

        try {
            $outliers = [];
            $hoursBack = 24;

            // Outliers pro Endpoint finden
            $endpoints = $this->db->select(
                'SELECT DISTINCT endpoint FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)',
                [$hoursBack]
            );

            foreach ($endpoints as $endpointData) {
                $endpoint = $endpointData['endpoint'];
                $endpointOutliers = $this->detectEndpointOutliers($endpoint, $hoursBack);
                
                if (!empty($endpointOutliers)) {
                    $outliers[$endpoint] = $endpointOutliers;
                }
            }

            return $outliers;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'outlier_detection'
            ]);
            return [];
        }
    }

    /**
     * Prüft Performance-Budget-Violations
     */
    public function checkPerformanceBudgets(): array
    {
        try {
            $violations = [];
            $hoursBack = 1; // Letzte Stunde prüfen

            foreach ($this->performanceBaselines as $endpoint => $budgetMs) {
                if (is_array($budgetMs)) {
                    $budgetMs = $budgetMs['mean_ms'];
                }

                // Budget mit Buffer
                $budgetWithBuffer = $budgetMs * (1 + $this->config['performance_budget_buffer_percent'] / 100);

                $violations_data = $this->db->selectOne(
                    'SELECT 
                        COUNT(*) as total_requests,
                        COUNT(CASE WHEN response_time_ms > ? THEN 1 END) as violations,
                        AVG(response_time_ms) as avg_response_time,
                        MAX(response_time_ms) as max_response_time
                     FROM performance_metrics 
                     WHERE endpoint = ? 
                     AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                     AND status_code < 400',
                    [$budgetWithBuffer, $endpoint, $hoursBack]
                );

                if ($violations_data && $violations_data['violations'] > 0) {
                    $violationPercent = ($violations_data['violations'] / $violations_data['total_requests']) * 100;
                    
                    $violations[] = [
                        'endpoint' => $endpoint,
                        'budget_ms' => $budgetMs,
                        'budget_with_buffer_ms' => round($budgetWithBuffer, 2),
                        'violation_count' => (int) $violations_data['violations'],
                        'total_requests' => (int) $violations_data['total_requests'],
                        'violation_percent' => round($violationPercent, 2),
                        'avg_response_time' => round($violations_data['avg_response_time'], 2),
                        'max_response_time' => (int) $violations_data['max_response_time'],
                        'severity' => $violationPercent > 10 ? 'high' : 'medium'
                    ];
                }
            }

            return $violations;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'budget_violation_check'
            ]);
            return [];
        }
    }

    /**
     * Generiert Performance-Predictions
     */
    public function generatePerformancePredictions(): array
    {
        if (!$this->config['enable_predictive_alerts']) {
            return [];
        }

        try {
            $predictions = [];
            $daysBack = 14; // 2 Wochen Daten für Vorhersage

            foreach ($this->performanceBaselines as $endpoint => $baseline) {
                $prediction = $this->predictEndpointPerformance($endpoint, $daysBack);
                
                if ($prediction) {
                    $predictions[$endpoint] = $prediction;
                }
            }

            return $predictions;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'performance_predictions'
            ]);
            return [];
        }
    }

    /**
     * Generiert Empfehlungen basierend auf Analyse
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Regression-Empfehlungen
        if (!empty($analysis['regressions'])) {
            foreach ($analysis['regressions'] as $regression) {
                if ($regression['severity'] === 'critical') {
                    $recommendations[] = [
                        'type' => 'regression',
                        'priority' => 'high',
                        'endpoint' => $regression['endpoint'],
                        'message' => "Kritische Performance-Regression bei {$regression['endpoint']} (+{$regression['regression_percent']}%). Sofortige Optimierung erforderlich.",
                        'suggested_actions' => [
                            'Code-Review der letzten Änderungen',
                            'Database-Query-Optimierung prüfen',
                            'Cache-Strategien überprüfen',
                            'Load-Testing durchführen'
                        ]
                    ];
                }
            }
        }

        // Budget-Violation-Empfehlungen
        if (!empty($analysis['budget_violations'])) {
            $highViolations = array_filter($analysis['budget_violations'], fn($v) => $v['severity'] === 'high');
            
            if (!empty($highViolations)) {
                $recommendations[] = [
                    'type' => 'budget_violation',
                    'priority' => 'medium',
                    'message' => "Performance-Budget-Überschreitungen bei " . count($highViolations) . " Endpoints.",
                    'suggested_actions' => [
                        'Performance-Profiling durchführen',
                        'Bottlenecks identifizieren',
                        'Caching-Strategien implementieren',
                        'Budget-Limits überprüfen'
                    ]
                ];
            }
        }

        // Outlier-Empfehlungen
        if (!empty($analysis['outliers'])) {
            $totalOutliers = array_sum(array_map('count', $analysis['outliers']));
            
            if ($totalOutliers > 50) {
                $recommendations[] = [
                    'type' => 'outliers',
                    'priority' => 'low',
                    'message' => "Viele Performance-Outliers erkannt ({$totalOutliers}). Systemstabilität prüfen.",
                    'suggested_actions' => [
                        'System-Ressourcen überwachen',
                        'Externe API-Abhängigkeiten prüfen',
                        'Error-Logs analysieren'
                    ]
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Verarbeitet Alerts basierend auf Analyse
     */
    private function processAnalysisAlerts(array $analysis): void
    {
        try {
            // Regression-Alerts
            foreach ($analysis['regressions'] as $regression) {
                if ($regression['severity'] === 'critical') {
                    $this->alertService->evaluateAlerts();
                }
            }

            // Budget-Violation-Alerts
            $criticalViolations = array_filter(
                $analysis['budget_violations'], 
                fn($v) => $v['violation_percent'] > 20
            );

            if (!empty($criticalViolations)) {
                $this->alertService->evaluateAlerts();
            }

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'analysis_alerts_processing'
            ]);
        }
    }

    /**
     * Hilfsmethoden für Berechnungen
     */
    private function calculatePercentiles(string $endpoint, int $daysBack): array
    {
        try {
            $responseTimes = $this->db->select(
                'SELECT response_time_ms FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND status_code < 400
                 ORDER BY response_time_ms',
                [$endpoint, $daysBack]
            );

            $times = array_column($responseTimes, 'response_time_ms');
            $count = count($times);

            if ($count === 0) {
                return [];
            }

            return [
                'p50' => $this->percentile($times, 50),
                'p95' => $this->percentile($times, 95),
                'p99' => $this->percentile($times, 99)
            ];

        } catch (Exception $e) {
            return [];
        }
    }

    private function percentile(array $values, int $percentile): int
    {
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);
        
        if (floor($index) == $index) {
            return (int) $values[$index];
        }
        
        $lower = (int) $values[floor($index)];
        $upper = (int) $values[ceil($index)];
        
        return (int) ($lower + ($index - floor($index)) * ($upper - $lower));
    }

    private function calculateConfidenceInterval(array $stats): array
    {
        $mean = $stats['mean'];
        $stdDev = $stats['std_dev'];
        $sampleCount = $stats['sample_count'];
        
        // 95% Konfidenzintervall
        $marginOfError = 1.96 * ($stdDev / sqrt($sampleCount));
        
        return [
            'lower' => round($mean - $marginOfError, 2),
            'upper' => round($mean + $marginOfError, 2),
            'confidence_level' => 95
        ];
    }

    private function analyzeTrendDirection(string $endpoint, int $days): array
    {
        try {
            $trendData = $this->db->select(
                'SELECT 
                    DATE(created_at) as date,
                    AVG(response_time_ms) as avg_time
                 FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date',
                [$endpoint, $days]
            );

            if (count($trendData) < 3) {
                return ['direction' => 'insufficient_data'];
            }

            // Einfache lineare Regression für Trend
            $slope = $this->calculateSlope($trendData);
            
            return [
                'direction' => $slope > 5 ? 'worsening' : ($slope < -5 ? 'improving' : 'stable'),
                'slope' => round($slope, 3),
                'data_points' => count($trendData)
            ];

        } catch (Exception $e) {
            return ['direction' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function calculateSlope(array $data): float
    {
        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($data as $i => $point) {
            $x = $i; // Tag-Index
            $y = (float) $point['avg_time'];
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;
        
        if ($denominator == 0) {
            return 0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }

    private function calculateRegressionSeverity(float $regressionPercent): string
    {
        if ($regressionPercent >= 50) return 'critical';
        if ($regressionPercent >= 35) return 'high';
        if ($regressionPercent >= 25) return 'medium';
        return 'low';
    }

    private function calculateStatisticalSignificance(string $endpoint, float $baseline, float $current): array
    {
        // Vereinfachte statistische Signifikanz-Berechnung
        try {
            $difference = abs($current - $baseline);
            $relativeDifference = ($difference / $baseline) * 100;
            
            return [
                'significant' => $relativeDifference > 15, // 15% Unterschied als signifikant
                'relative_difference_percent' => round($relativeDifference, 2),
                'confidence' => $relativeDifference > 25 ? 'high' : ($relativeDifference > 15 ? 'medium' : 'low')
            ];

        } catch (Exception $e) {
            return ['significant' => false, 'error' => $e->getMessage()];
        }
    }

    private function calculateTrendMetrics(array $endpointTrends): array
    {
        $responseTimes = array_column($endpointTrends, 'daily_avg');
        
        return [
            'data_points' => count($endpointTrends),
            'trend_direction' => $this->calculateSlope($endpointTrends) > 0 ? 'worsening' : 'improving',
            'avg_response_time' => round(array_sum($responseTimes) / count($responseTimes), 2),
            'min_daily_avg' => round(min($responseTimes), 2),
            'max_daily_avg' => round(max($responseTimes), 2),
            'variance' => round($this->calculateVariance($responseTimes), 2),
            'stability' => $this->assessStability($responseTimes)
        ];
    }

    private function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $sumSquares = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values));
        return $sumSquares / count($values);
    }

    private function assessStability(array $values): string
    {
        $variance = $this->calculateVariance($values);
        $mean = array_sum($values) / count($values);
        $coefficientOfVariation = ($variance > 0) ? sqrt($variance) / $mean : 0;
        
        if ($coefficientOfVariation < 0.1) return 'very_stable';
        if ($coefficientOfVariation < 0.2) return 'stable';
        if ($coefficientOfVariation < 0.4) return 'moderate';
        return 'unstable';
    }

    private function detectEndpointOutliers(string $endpoint, int $hoursBack): array
    {
        try {
            // Statistische Daten für Endpoint sammeln
            $stats = $this->db->selectOne(
                'SELECT 
                    AVG(response_time_ms) as mean,
                    STDDEV(response_time_ms) as std_dev
                 FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 AND status_code < 400',
                [$endpoint, $hoursBack]
            );

            if (!$stats || $stats['std_dev'] == 0) {
                return [];
            }

            $mean = (float) $stats['mean'];
            $stdDev = (float) $stats['std_dev'];
            $threshold = $this->config['outlier_threshold_std_dev'];

            // Outliers finden
            $outliers = $this->db->select(
                'SELECT id, response_time_ms, created_at, user_id, status_code
                 FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 AND ABS(response_time_ms - ?) > ? * ?
                 ORDER BY response_time_ms DESC
                 LIMIT 50',
                [$endpoint, $hoursBack, $mean, $threshold, $stdDev]
            );

            return array_map(function ($outlier) use ($mean, $stdDev) {
                $zScore = ($outlier['response_time_ms'] - $mean) / $stdDev;
                return array_merge($outlier, [
                    'z_score' => round($zScore, 2),
                    'deviation_from_mean' => round($outlier['response_time_ms'] - $mean, 2)
                ]);
            }, $outliers);

        } catch (Exception $e) {
            error_log("Failed to detect outliers for {$endpoint}: " . $e->getMessage());
            return [];
        }
    }

    private function predictEndpointPerformance(string $endpoint, int $daysBack): ?array
    {
        try {
            // Vereinfachte lineare Vorhersage basierend auf Trend
            $trendData = $this->db->select(
                'SELECT 
                    DATE(created_at) as date,
                    AVG(response_time_ms) as avg_time
                 FROM performance_metrics 
                 WHERE endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date',
                [$endpoint, $daysBack]
            );

            if (count($trendData) < 7) {
                return null; // Nicht genügend Daten für Vorhersage
            }

            $slope = $this->calculateSlope($trendData);
            $currentAvg = (float) end($trendData)['avg_time'];
            
            // Vorhersage für nächste 7 Tage
            $prediction = $currentAvg + ($slope * 7);
            
            $baseline = $this->performanceBaselines[$endpoint] ?? $this->performanceBaselines['default'];
            if (is_array($baseline)) {
                $baseline = $baseline['mean_ms'];
            }

            $alert = $prediction > $baseline * 1.5; // Alert wenn 50% über Baseline vorhergesagt

            return [
                'endpoint' => $endpoint,
                'current_avg_ms' => round($currentAvg, 2),
                'predicted_avg_ms' => round($prediction, 2),
                'trend_slope' => round($slope, 3),
                'prediction_horizon_days' => 7,
                'baseline_ms' => $baseline,
                'alert_recommended' => $alert,
                'confidence' => count($trendData) >= 14 ? 'high' : 'medium'
            ];

        } catch (Exception $e) {
            error_log("Failed to predict performance for {$endpoint}: " . $e->getMessage());
            return null;
        }
    }
}