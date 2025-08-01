<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Services\APIUsageTracker;
use Exception;
use PDO;

/**
 * APILimitReportingService - Umfassendes Reporting für API-Limits und Kosten
 * 
 * Generiert detaillierte Berichte über:
 * - API-Nutzung und Trends
 * - Kosten-Analysen und Projektionen
 * - Limit-Compliance und Warnungen
 * - Performance-Metriken
 * 
 * @author 2Brands Media GmbH
 */
class APILimitReportingService
{
    private Database $db;
    private Config $config;
    private APIUsageTracker $tracker;
    
    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->tracker = new APIUsageTracker($db, $config);
    }

    /**
     * Generiert täglichen API-Nutzungsbericht
     */
    public function generateDailyReport(string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        
        $report = [
            'report_date' => $date,
            'report_generated' => date('Y-m-d H:i:s'),
            'summary' => $this->getDailySummary($date),
            'api_usage' => $this->getDailyApiUsage($date),
            'cost_analysis' => $this->getDailyCostAnalysis($date),
            'alerts' => $this->getDailyAlerts($date),
            'performance' => $this->getDailyPerformance($date),
            'recommendations' => $this->getDailyRecommendations($date)
        ];
        
        // Report in Datenbank speichern
        $this->saveReport('daily', $date, $report);
        
        return $report;
    }

    /**
     * Generiert wöchentlichen Trendbericht
     */
    public function generateWeeklyReport(string $weekStart = null): array
    {
        $weekStart = $weekStart ?? date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        
        $report = [
            'report_type' => 'weekly',
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'report_generated' => date('Y-m-d H:i:s'),
            'summary' => $this->getWeeklySummary($weekStart, $weekEnd),
            'trends' => $this->getWeeklyTrends($weekStart, $weekEnd),
            'cost_analysis' => $this->getWeeklyCostAnalysis($weekStart, $weekEnd),
            'capacity_analysis' => $this->getWeeklyCapacityAnalysis($weekStart, $weekEnd),
            'recommendations' => $this->getWeeklyRecommendations($weekStart, $weekEnd)
        ];
        
        $this->saveReport('weekly', $weekStart, $report);
        
        return $report;
    }

    /**
     * Generiert monatlichen Executive Summary
     */
    public function generateMonthlyReport(string $month = null): array
    {
        $month = $month ?? date('Y-m');
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        $report = [
            'report_type' => 'monthly',
            'month' => $month,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'report_generated' => date('Y-m-d H:i:s'),
            'executive_summary' => $this->getExecutiveSummary($monthStart, $monthEnd),
            'cost_analysis' => $this->getMonthlyCostAnalysis($monthStart, $monthEnd),
            'usage_trends' => $this->getMonthlyUsageTrends($monthStart, $monthEnd),
            'limit_compliance' => $this->getMonthlyLimitCompliance($monthStart, $monthEnd),
            'performance_metrics' => $this->getMonthlyPerformanceMetrics($monthStart, $monthEnd),
            'strategic_recommendations' => $this->getStrategicRecommendations($monthStart, $monthEnd)
        ];
        
        $this->saveReport('monthly', $month, $report);
        
        return $report;
    }

    /**
     * Generiert Kostenprojektion
     */
    public function generateCostProjection(int $daysAhead = 30): array
    {
        $currentUsage = $this->getCurrentUsagePattern();
        $projections = [];
        
        foreach ($currentUsage as $provider => $usage) {
            $dailyAverage = $usage['requests_7day_avg'];
            $costPerRequest = $usage['cost_per_request'];
            
            $projections[$provider] = [
                'current_daily_avg' => $dailyAverage,
                'projected_daily_cost' => $dailyAverage * $costPerRequest,
                'projected_monthly_cost' => $dailyAverage * $costPerRequest * 30,
                'projected_cost_' . $daysAhead . '_days' => $dailyAverage * $costPerRequest * $daysAhead,
                'confidence_level' => $this->calculateProjectionConfidence($usage),
                'trend' => $this->calculateTrend($provider, 14)
            ];
        }
        
        $totalProjection = [
            'total_daily_cost' => array_sum(array_column($projections, 'projected_daily_cost')),
            'total_monthly_cost' => array_sum(array_column($projections, 'projected_monthly_cost')),
            'total_cost_' . $daysAhead . '_days' => array_sum(array_column($projections, 'projected_cost_' . $daysAhead . '_days')),
            'budget_utilization' => $this->calculateBudgetUtilization($projections),
            'cost_optimization_potential' => $this->calculateOptimizationPotential($projections)
        ];
        
        return [
            'projection_date' => date('Y-m-d'),
            'projection_period' => $daysAhead,
            'provider_projections' => $projections,
            'total_projection' => $totalProjection,
            'recommendations' => $this->getCostOptimizationRecommendations($projections)
        ];
    }

    /**
     * Generiert Limit-Compliance-Bericht
     */
    public function generateComplianceReport(string $startDate, string $endDate): array
    {
        $violations = $this->getLimitViolations($startDate, $endDate);
        $warnings = $this->getLimitWarnings($startDate, $endDate);
        $fallbacks = $this->getFallbackUsage($startDate, $endDate);
        
        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'report_generated' => date('Y-m-d H:i:s'),
            'compliance_score' => $this->calculateComplianceScore($violations, $warnings),
            'violations' => $violations,
            'warnings' => $warnings,
            'fallback_usage' => $fallbacks,
            'improvement_areas' => $this->identifyImprovementAreas($violations, $warnings),
            'risk_assessment' => $this->assessComplianceRisk($violations, $warnings)
        ];
    }

    /**
     * Real-time Dashboard-Daten
     */
    public function getRealTimeDashboard(): array
    {
        $currentHour = date('Y-m-d H:00:00');
        
        return [
            'last_updated' => date('Y-m-d H:i:s'),
            'current_usage' => $this->getCurrentHourUsage($currentHour),
            'daily_progress' => $this->getDailyProgress(),
            'active_alerts' => $this->getActiveAlerts(),
            'api_health' => $this->getCurrentApiHealth(),
            'cost_tracker' => $this->getCurrentCostTracking(),
            'fallback_status' => $this->getFallbackSystemStatus(),
            'performance_indicators' => $this->getCurrentPerformanceIndicators()
        ];
    }

    /**
     * Private Helper-Methoden für tägliche Berichte
     */
    private function getDailySummary(string $date): array
    {
        $summary = $this->db->selectOne(
            'SELECT 
                SUM(request_count) as total_requests,
                SUM(error_count) as total_errors,
                AVG(avg_response_time) as avg_response_time,
                COUNT(DISTINCT api_provider) as active_apis
             FROM api_usage 
             WHERE period_type = "daily" AND period_key = ?',
            [$date]
        );
        
        $costData = $this->calculateDailyCost($date);
        
        return [
            'total_requests' => (int) ($summary['total_requests'] ?? 0),
            'total_errors' => (int) ($summary['total_errors'] ?? 0),
            'error_rate' => $this->calculateErrorRate($summary),
            'avg_response_time' => round((float) ($summary['avg_response_time'] ?? 0), 2),
            'active_apis' => (int) ($summary['active_apis'] ?? 0),
            'total_cost' => $costData['total_cost'],
            'cost_breakdown' => $costData['breakdown']
        ];
    }

    private function getDailyApiUsage(string $date): array
    {
        $usage = $this->db->select(
            'SELECT 
                api_provider,
                SUM(request_count) as requests,
                SUM(error_count) as errors,
                AVG(avg_response_time) as avg_response_time
             FROM api_usage 
             WHERE period_type = "daily" AND period_key = ?
             GROUP BY api_provider',
            [$date]
        );
        
        $result = [];
        foreach ($usage as $row) {
            $provider = $row['api_provider'];
            $limits = $this->tracker->getApiLimits($provider);
            
            $result[$provider] = [
                'requests' => (int) $row['requests'],
                'errors' => (int) $row['errors'],
                'error_rate' => $this->calculateProviderErrorRate($row),
                'avg_response_time' => round((float) $row['avg_response_time'], 2),
                'limit_utilization' => round(($row['requests'] / $limits['daily']) * 100, 2),
                'status' => $this->getProviderStatus($provider, $row['requests'], $limits['daily'])
            ];
        }
        
        return $result;
    }

    private function getDailyCostAnalysis(string $date): array
    {
        return $this->calculateDailyCost($date);
    }

    private function getDailyAlerts(string $date): array
    {
        return $this->db->select(
            'SELECT 
                api_provider,
                warning_level,
                created_at,
                daily_requests,
                hourly_requests
             FROM api_warnings 
             WHERE DATE(created_at) = ?
             ORDER BY created_at DESC',
            [$date]
        );
    }

    private function getDailyPerformance(string $date): array
    {
        $performance = $this->db->select(
            'SELECT 
                api_provider,
                AVG(avg_response_time) as avg_response_time,
                MIN(avg_response_time) as min_response_time,
                MAX(avg_response_time) as max_response_time
             FROM api_usage 
             WHERE period_type = "hourly" 
             AND DATE(STR_TO_DATE(period_key, "%Y-%m-%d %H:%i:%s")) = ?
             GROUP BY api_provider',
            [$date]
        );
        
        $result = [];
        foreach ($performance as $row) {
            $result[$row['api_provider']] = [
                'avg_response_time' => round((float) $row['avg_response_time'], 2),
                'min_response_time' => round((float) $row['min_response_time'], 2),
                'max_response_time' => round((float) $row['max_response_time'], 2),
                'performance_grade' => $this->calculatePerformanceGrade($row['avg_response_time'])
            ];
        }
        
        return $result;
    }

    private function getDailyRecommendations(string $date): array
    {
        $recommendations = [];
        $usage = $this->getDailyApiUsage($date);
        
        foreach ($usage as $provider => $data) {
            if ($data['limit_utilization'] > 80) {
                $recommendations[] = [
                    'type' => 'high_usage',
                    'provider' => $provider,
                    'message' => "Hohe Nutzung bei {$provider} ({$data['limit_utilization']}%)",
                    'action' => 'Cache-Strategien optimieren oder Limits erhöhen',
                    'priority' => $data['limit_utilization'] > 90 ? 'high' : 'medium'
                ];
            }
            
            if ($data['error_rate'] > 5) {
                $recommendations[] = [
                    'type' => 'high_error_rate',
                    'provider' => $provider,
                    'message' => "Hohe Fehlerrate bei {$provider} ({$data['error_rate']}%)",
                    'action' => 'Fehlerbehandlung und Retry-Logik überprüfen',
                    'priority' => 'medium'
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Private Helper-Methoden für wöchentliche Berichte
     */
    private function getWeeklySummary(string $startDate, string $endDate): array
    {
        $summary = $this->db->selectOne(
            'SELECT 
                SUM(request_count) as total_requests,
                SUM(error_count) as total_errors,
                AVG(avg_response_time) as avg_response_time
             FROM api_usage 
             WHERE period_type = "daily" 
             AND period_key BETWEEN ? AND ?',
            [$startDate, $endDate]
        );
        
        $previousWeekStart = date('Y-m-d', strtotime($startDate . ' -7 days'));
        $previousWeekEnd = date('Y-m-d', strtotime($endDate . ' -7 days'));
        
        $previousWeekSummary = $this->db->selectOne(
            'SELECT 
                SUM(request_count) as total_requests,
                SUM(error_count) as total_errors
             FROM api_usage 
             WHERE period_type = "daily" 
             AND period_key BETWEEN ? AND ?',
            [$previousWeekStart, $previousWeekEnd]
        );
        
        return [
            'current_week' => [
                'total_requests' => (int) ($summary['total_requests'] ?? 0),
                'total_errors' => (int) ($summary['total_errors'] ?? 0),
                'avg_response_time' => round((float) ($summary['avg_response_time'] ?? 0), 2)
            ],
            'previous_week' => [
                'total_requests' => (int) ($previousWeekSummary['total_requests'] ?? 0),
                'total_errors' => (int) ($previousWeekSummary['total_errors'] ?? 0)
            ],
            'growth' => [
                'requests' => $this->calculateGrowthRate(
                    $previousWeekSummary['total_requests'] ?? 0,
                    $summary['total_requests'] ?? 0
                ),
                'errors' => $this->calculateGrowthRate(
                    $previousWeekSummary['total_errors'] ?? 0,
                    $summary['total_errors'] ?? 0
                )
            ]
        ];
    }

    private function getWeeklyTrends(string $startDate, string $endDate): array
    {
        $trends = [];
        
        $providers = [APIUsageTracker::API_GOOGLE_MAPS, APIUsageTracker::API_NOMINATIM, APIUsageTracker::API_OPENROUTESERVICE];
        
        foreach ($providers as $provider) {
            $dailyUsage = $this->db->select(
                'SELECT 
                    period_key as date,
                    request_count,
                    error_count,
                    avg_response_time
                 FROM api_usage 
                 WHERE api_provider = ?
                 AND period_type = "daily" 
                 AND period_key BETWEEN ? AND ?
                 ORDER BY period_key',
                [$provider, $startDate, $endDate]
            );
            
            $trends[$provider] = [
                'daily_data' => $dailyUsage,
                'trend_direction' => $this->calculateTrendDirection($dailyUsage),
                'average_daily_requests' => $this->calculateAverage(array_column($dailyUsage, 'request_count')),
                'peak_day' => $this->findPeakDay($dailyUsage)
            ];
        }
        
        return $trends;
    }

    /**
     * Utility-Methoden
     */
    private function calculateDailyCost(string $date): array
    {
        $usage = $this->db->select(
            'SELECT api_provider, SUM(request_count) as requests
             FROM api_usage 
             WHERE period_type = "daily" AND period_key = ?
             GROUP BY api_provider',
            [$date]
        );
        
        $totalCost = 0;
        $costBreakdown = [];
        
        foreach ($usage as $row) {
            $provider = $row['api_provider'];
            $limits = $this->tracker->getApiLimits($provider);
            $cost = (int)$row['requests'] * $limits['cost_per_request'];
            
            $costBreakdown[$provider] = [
                'requests' => (int)$row['requests'],
                'cost_per_request' => $limits['cost_per_request'],
                'total_cost' => round($cost, 4)
            ];
            
            $totalCost += $cost;
        }
        
        return [
            'total_cost' => round($totalCost, 4),
            'breakdown' => $costBreakdown
        ];
    }

    private function calculateErrorRate(array $summary): float
    {
        $totalRequests = (int) ($summary['total_requests'] ?? 0);
        $totalErrors = (int) ($summary['total_errors'] ?? 0);
        
        return $totalRequests > 0 ? round(($totalErrors / $totalRequests) * 100, 2) : 0.0;
    }

    private function calculateProviderErrorRate(array $data): float
    {
        $requests = (int) $data['requests'];
        $errors = (int) $data['errors'];
        
        return $requests > 0 ? round(($errors / $requests) * 100, 2) : 0.0;
    }

    private function getProviderStatus(string $provider, int $requests, int $dailyLimit): string
    {
        $utilization = ($requests / $dailyLimit) * 100;
        
        if ($utilization >= 95) return 'critical';
        if ($utilization >= 80) return 'warning';
        if ($utilization >= 60) return 'moderate';
        
        return 'normal';
    }

    private function calculatePerformanceGrade(float $responseTime): string
    {
        if ($responseTime <= 100) return 'A';
        if ($responseTime <= 300) return 'B';
        if ($responseTime <= 500) return 'C';
        if ($responseTime <= 1000) return 'D';
        
        return 'F';
    }

    private function calculateGrowthRate(float $previous, float $current): float
    {
        if ($previous == 0) return $current > 0 ? 100.0 : 0.0;
        
        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function calculateTrendDirection(array $dailyUsage): string
    {
        if (count($dailyUsage) < 2) return 'stable';
        
        $requests = array_column($dailyUsage, 'request_count');
        $firstHalf = array_slice($requests, 0, ceil(count($requests) / 2));
        $secondHalf = array_slice($requests, floor(count($requests) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;
        
        if ($change > 10) return 'increasing';
        if ($change < -10) return 'decreasing';
        
        return 'stable';
    }

    private function calculateAverage(array $values): float
    {
        return count($values) > 0 ? round(array_sum($values) / count($values), 2) : 0.0;
    }

    private function findPeakDay(array $dailyUsage): ?array
    {
        if (empty($dailyUsage)) return null;
        
        $maxRequests = max(array_column($dailyUsage, 'request_count'));
        
        foreach ($dailyUsage as $day) {
            if ($day['request_count'] == $maxRequests) {
                return $day;
            }
        }
        
        return null;
    }

    private function saveReport(string $type, string $period, array $reportData): void
    {
        try {
            $this->db->insert('api_reports', [
                'report_type' => $type,
                'report_period' => $period,
                'report_data' => json_encode($reportData),
                'generated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to save API report: " . $e->getMessage());
        }
    }

    // Placeholder-Methoden für vollständige Implementierung
    private function getWeeklyCostAnalysis(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getWeeklyCapacityAnalysis(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getWeeklyRecommendations(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getExecutiveSummary(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getMonthlyCostAnalysis(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getMonthlyUsageTrends(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getMonthlyLimitCompliance(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getMonthlyPerformanceMetrics(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getStrategicRecommendations(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getCurrentUsagePattern(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function calculateProjectionConfidence(array $usage): float
    {
        return 0.85; // Placeholder
    }

    private function calculateTrend(string $provider, int $days): string
    {
        return 'stable'; // Placeholder
    }

    private function calculateBudgetUtilization(array $projections): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function calculateOptimizationPotential(array $projections): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getCostOptimizationRecommendations(array $projections): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getLimitViolations(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getLimitWarnings(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getFallbackUsage(string $startDate, string $endDate): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function calculateComplianceScore(array $violations, array $warnings): float
    {
        return 95.0; // Placeholder
    }

    private function identifyImprovementAreas(array $violations, array $warnings): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function assessComplianceRisk(array $violations, array $warnings): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getCurrentHourUsage(string $currentHour): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getDailyProgress(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getActiveAlerts(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getCurrentApiHealth(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getCurrentCostTracking(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getFallbackSystemStatus(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }

    private function getCurrentPerformanceIndicators(): array
    {
        return ['placeholder' => 'implementation_needed'];
    }
}