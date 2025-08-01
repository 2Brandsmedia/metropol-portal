<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Exception;

/**
 * Alert-Service für automatisierte Benachrichtigungen
 * 
 * Verwaltet die Erstellung, Evaluierung und den Versand von System-Alerts:
 * - E-Mail-Benachrichtigungen für kritische Ereignisse
 * - Webhook-Integration für externe Monitoring-Tools
 * - Escalation-Rules für ungelöste Alerts
 * - Alert-Suppression für wiederholte Benachrichtigungen
 * 
 * @author 2Brands Media GmbH
 */
class AlertService
{
    private Database $db;
    private array $config;
    private array $notificationChannels = [];

    public function __construct(Database $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'smtp_host' => 'smtp.all-inkl.com',
            'smtp_port' => 587,
            'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
            'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'smtp_from_email' => 'monitoring@metropol-portal.de',
            'smtp_from_name' => 'Metropol Portal Monitoring',
            'default_alert_email' => 'admin@2brands-media.de',
            'webhook_urls' => [
                'slack' => $_ENV['SLACK_WEBHOOK_URL'] ?? '',
                'teams' => $_ENV['TEAMS_WEBHOOK_URL'] ?? ''
            ],
            'alert_cooldown_minutes' => 15,
            'escalation_enabled' => true,
            'escalation_delay_minutes' => 60,
            'max_alerts_per_hour' => 20
        ], $config);

        $this->initializeNotificationChannels();
    }

    /**
     * Initialisiert Benachrichtigungskanäle
     */
    private function initializeNotificationChannels(): void
    {
        $this->notificationChannels = [
            'email' => new EmailNotificationChannel($this->config),
            'webhook' => new WebhookNotificationChannel($this->config),
            'log' => new LogNotificationChannel($this->config)
        ];
    }

    /**
     * Evaluiert alle aktiven Alert-Regeln
     */
    public function evaluateAlerts(): array
    {
        try {
            $alerts = $this->db->select(
                'SELECT * FROM alerts WHERE enabled = TRUE ORDER BY severity DESC'
            );

            $triggeredAlerts = [];
            $now = time();

            foreach ($alerts as $alertConfig) {
                // Prüfen ob Alert kürzlich ausgelöst wurde (Cooldown)
                if ($this->isInCooldown($alertConfig)) {
                    continue;
                }

                // Alert-Regel evaluieren
                $triggerResult = $this->evaluateAlertRule($alertConfig);
                
                if ($triggerResult['triggered']) {
                    $alertLog = $this->createAlertLog($alertConfig, $triggerResult);
                    $this->sendNotifications($alertConfig, $alertLog);
                    $triggeredAlerts[] = $alertLog;
                }
            }

            // Escalation für ungelöste Alerts prüfen
            if ($this->config['escalation_enabled']) {
                $escalatedAlerts = $this->processEscalations();
                $triggeredAlerts = array_merge($triggeredAlerts, $escalatedAlerts);
            }

            return $triggeredAlerts;

        } catch (Exception $e) {
            error_log("AlertService: Failed to evaluate alerts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Evaluiert eine einzelne Alert-Regel
     */
    private function evaluateAlertRule(array $alertConfig): array
    {
        try {
            $metricValue = $this->getMetricValue($alertConfig);
            
            if ($metricValue === null) {
                return ['triggered' => false, 'reason' => 'metric_not_available'];
            }

            $threshold = (float) $alertConfig['threshold_value'];
            $triggered = $this->checkCondition(
                $metricValue, 
                $alertConfig['condition_operator'], 
                $threshold
            );

            return [
                'triggered' => $triggered,
                'metric_value' => $metricValue,
                'threshold_value' => $threshold,
                'condition' => $alertConfig['condition_operator'],
                'evaluation_time' => date('c')
            ];

        } catch (Exception $e) {
            error_log("AlertService: Failed to evaluate alert rule {$alertConfig['name']}: " . $e->getMessage());
            return ['triggered' => false, 'reason' => 'evaluation_error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Holt aktuellen Metrik-Wert basierend auf Alert-Konfiguration
     */
    private function getMetricValue(array $alertConfig): ?float
    {
        $metricType = $alertConfig['metric_type'];
        $timeWindow = (int) $alertConfig['time_window_minutes'];

        switch ($alertConfig['alert_type']) {
            case 'performance':
                return $this->getPerformanceMetric($metricType, $timeWindow);
                
            case 'error':
                return $this->getErrorMetric($metricType, $timeWindow);
                
            case 'system':
                return $this->getSystemMetric($metricType, $timeWindow);
                
            case 'business':
                return $this->getBusinessMetric($metricType, $timeWindow);
                
            default:
                return null;
        }
    }

    /**
     * Performance-Metriken abrufen
     */
    private function getPerformanceMetric(string $metricType, int $timeWindow): ?float
    {
        switch ($metricType) {
            case 'avg_response_time':
                $result = $this->db->selectOne(
                    'SELECT AVG(response_time_ms) as value FROM performance_metrics 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            case 'p95_response_time':
                $result = $this->db->selectOne(
                    'SELECT response_time_ms as value FROM performance_metrics 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                     ORDER BY response_time_ms DESC 
                     LIMIT 1 OFFSET FLOOR((COUNT(*) * 0.05))',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            case 'error_rate':
                $result = $this->db->selectOne(
                    'SELECT 
                        (COUNT(CASE WHEN status_code >= 400 THEN 1 END) / COUNT(*)) * 100 as value
                     FROM performance_metrics 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            case 'request_rate':
                $result = $this->db->selectOne(
                    'SELECT COUNT(*) / ? as value FROM performance_metrics 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow, $timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            default:
                return null;
        }
    }

    /**
     * Error-Metriken abrufen
     */
    private function getErrorMetric(string $metricType, int $timeWindow): ?float
    {
        switch ($metricType) {
            case 'error_count':
                $result = $this->db->selectOne(
                    'SELECT COUNT(*) as value FROM error_logs 
                     WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            case 'critical_error_count':
                $result = $this->db->selectOne(
                    'SELECT COUNT(*) as value FROM error_logs 
                     WHERE severity IN ("critical", "emergency") 
                     AND first_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            case 'error_rate':
                $result = $this->db->selectOne(
                    'SELECT SUM(error_count) as value FROM error_logs 
                     WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            default:
                return null;
        }
    }

    /**
     * System-Metriken abrufen
     */
    private function getSystemMetric(string $metricType, int $timeWindow): ?float
    {
        $result = $this->db->selectOne(
            'SELECT value, percentage FROM system_metrics 
             WHERE metric_type = ? 
             ORDER BY measured_at DESC 
             LIMIT 1',
            [$metricType]
        );

        if (!$result) {
            return null;
        }

        // Für Prozent-basierte Metriken Percentage verwenden, sonst Value
        return in_array($metricType, ['memory', 'disk', 'cpu']) ? 
            (float) $result['percentage'] : (float) $result['value'];
    }

    /**
     * Business-Metriken abrufen (customizable)
     */
    private function getBusinessMetric(string $metricType, int $timeWindow): ?float
    {
        switch ($metricType) {
            case 'active_users':
                $result = $this->db->selectOne(
                    'SELECT COUNT(DISTINCT user_id) as value FROM performance_metrics 
                     WHERE user_id IS NOT NULL 
                     AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            case 'api_calls_per_minute':
                $result = $this->db->selectOne(
                    'SELECT COUNT(*) / ? as value FROM performance_metrics 
                     WHERE endpoint LIKE "/api/%" 
                     AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                    [$timeWindow, $timeWindow]
                );
                return $result ? (float) $result['value'] : null;

            default:
                return null;
        }
    }

    /**
     * Prüft Bedingung gegen Schwellenwert
     */
    private function checkCondition(float $value, string $operator, float $threshold): bool
    {
        switch ($operator) {
            case 'gt': return $value > $threshold;
            case 'gte': return $value >= $threshold;
            case 'lt': return $value < $threshold;
            case 'lte': return $value <= $threshold;
            case 'eq': return abs($value - $threshold) < 0.001; // Float-Vergleich
            case 'neq': return abs($value - $threshold) >= 0.001;
            default: return false;
        }
    }

    /**
     * Prüft ob Alert in Cooldown-Phase ist
     */
    private function isInCooldown(array $alertConfig): bool
    {
        $cooldownMinutes = $this->config['alert_cooldown_minutes'];
        
        $recentAlert = $this->db->selectOne(
            'SELECT id FROM alert_logs 
             WHERE alert_id = ? 
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY created_at DESC LIMIT 1',
            [$alertConfig['id'], $cooldownMinutes]
        );

        return $recentAlert !== null;
    }

    /**
     * Erstellt Alert-Log-Eintrag
     */
    private function createAlertLog(array $alertConfig, array $triggerResult): array
    {
        try {
            $message = $this->generateAlertMessage($alertConfig, $triggerResult);
            
            $alertLogId = $this->db->insert(
                'INSERT INTO alert_logs (
                    alert_id, alert_name, severity, trigger_value, threshold_value,
                    message, context, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $alertConfig['id'],
                    $alertConfig['name'],
                    $alertConfig['severity'],
                    $triggerResult['metric_value'],
                    $triggerResult['threshold_value'],
                    $message,
                    json_encode($triggerResult)
                ]
            );

            return array_merge($alertConfig, [
                'alert_log_id' => $alertLogId,
                'message' => $message,
                'trigger_result' => $triggerResult
            ]);

        } catch (Exception $e) {
            error_log("AlertService: Failed to create alert log: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generiert Alert-Nachricht
     */
    private function generateAlertMessage(array $alertConfig, array $triggerResult): string
    {
        $metricValue = $triggerResult['metric_value'];
        $threshold = $triggerResult['threshold_value'];
        $metricType = $alertConfig['metric_type'];

        $templates = [
            'performance' => [
                'avg_response_time' => "Durchschnittliche Response-Zeit zu hoch: {$metricValue}ms (Schwellenwert: {$threshold}ms)",
                'error_rate' => "Fehlerrate zu hoch: {$metricValue}% (Schwellenwert: {$threshold}%)",
                'request_rate' => "Request-Rate: {$metricValue} req/min (Schwellenwert: {$threshold} req/min)"
            ],
            'system' => [
                'memory' => "Speicherverbrauch kritisch: {$metricValue}% (Schwellenwert: {$threshold}%)",
                'disk' => "Festplattenspeicher kritisch: {$metricValue}% (Schwellenwert: {$threshold}%)",
                'cpu' => "CPU-Auslastung kritisch: {$metricValue}% (Schwellenwert: {$threshold}%)"
            ],
            'error' => [
                'error_count' => "Zu viele Fehler: {$metricValue} (Schwellenwert: {$threshold})",
                'critical_error_count' => "Kritische Fehler aufgetreten: {$metricValue} (Schwellenwert: {$threshold})"
            ]
        ];

        $alertType = $alertConfig['alert_type'];
        
        if (isset($templates[$alertType][$metricType])) {
            return $templates[$alertType][$metricType];
        }

        // Fallback-Template
        return "{$alertConfig['name']}: {$metricType} = {$metricValue} (Schwellenwert: {$threshold})";
    }

    /**
     * Sendet Benachrichtigungen für Alert
     */
    private function sendNotifications(array $alertConfig, array $alertLog): void
    {
        try {
            $channels = json_decode($alertConfig['notification_channels'] ?? '[]', true);
            
            // Fallback auf Standard-Kanäle wenn keine konfiguriert
            if (empty($channels)) {
                $channels = $this->getDefaultChannelsForSeverity($alertConfig['severity']);
            }

            $notificationsSent = [];
            $errors = [];

            foreach ($channels as $channelConfig) {
                $channelType = $channelConfig['type'] ?? $channelConfig;
                
                if (!isset($this->notificationChannels[$channelType])) {
                    $errors[] = "Unknown notification channel: {$channelType}";
                    continue;
                }

                try {
                    $channel = $this->notificationChannels[$channelType];
                    $result = $channel->send($alertLog, $channelConfig);
                    
                    $notificationsSent[] = [
                        'channel' => $channelType,
                        'success' => $result['success'],
                        'response' => $result['response'] ?? null
                    ];

                } catch (Exception $e) {
                    $errors[] = "Failed to send via {$channelType}: " . $e->getMessage();
                }
            }

            // Notification-Status in Alert-Log aktualisieren
            $this->updateNotificationStatus($alertLog['alert_log_id'], $notificationsSent, $errors);

        } catch (Exception $e) {
            error_log("AlertService: Failed to send notifications: " . $e->getMessage());
        }
    }

    /**
     * Standard-Kanäle basierend auf Severity
     */
    private function getDefaultChannelsForSeverity(string $severity): array
    {
        switch ($severity) {
            case 'critical':
                return ['email', 'webhook', 'log'];
            case 'high':
                return ['email', 'log'];
            case 'medium':
                return ['log'];
            case 'low':
                return ['log'];
            default:
                return ['log'];
        }
    }

    /**
     * Aktualisiert Notification-Status
     */
    private function updateNotificationStatus(int $alertLogId, array $sent, array $errors): void
    {
        try {
            $this->db->update(
                'UPDATE alert_logs SET 
                 notification_sent = ?,
                 notification_channels = ?,
                 notification_errors = ?
                 WHERE id = ?',
                [
                    !empty($sent),
                    json_encode($sent),
                    empty($errors) ? null : json_encode($errors),
                    $alertLogId
                ]
            );
        } catch (Exception $e) {
            error_log("AlertService: Failed to update notification status: " . $e->getMessage());
        }
    }

    /**
     * Verarbeitet Escalations für ungelöste Alerts
     */
    private function processEscalations(): array
    {
        try {
            $escalationDelay = $this->config['escalation_delay_minutes'];
            
            $unescalatedAlerts = $this->db->select(
                'SELECT al.*, a.escalation_rules 
                 FROM alert_logs al
                 JOIN alerts a ON al.alert_id = a.id
                 WHERE al.resolved_at IS NULL
                 AND al.created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 AND al.context NOT LIKE "%escalated%"',
                [$escalationDelay]
            );

            $escalatedAlerts = [];

            foreach ($unescalatedAlerts as $alert) {
                $escalationRules = json_decode($alert['escalation_rules'] ?? '{}', true);
                
                if (empty($escalationRules)) {
                    continue;
                }

                // Escalation durchführen
                $escalationResult = $this->executeEscalation($alert, $escalationRules);
                
                if ($escalationResult) {
                    $escalatedAlerts[] = $escalationResult;
                }
            }

            return $escalatedAlerts;

        } catch (Exception $e) {
            error_log("AlertService: Failed to process escalations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Führt Escalation für Alert aus
     */
    private function executeEscalation(array $alert, array $escalationRules): ?array
    {
        try {
            // Escalation-Nachricht erstellen
            $escalationMessage = "ESCALATION: " . $alert['message'] . " (Ungelöst seit " . 
                $this->getTimeDifference($alert['created_at']) . ")";

            // Context aktualisieren
            $context = json_decode($alert['context'], true);
            $context['escalated'] = true;
            $context['escalation_time'] = date('c');

            // Alert-Log aktualisieren
            $this->db->update(
                'UPDATE alert_logs SET 
                 message = ?,
                 context = ?
                 WHERE id = ?',
                [$escalationMessage, json_encode($context), $alert['id']]
            );

            // Escalation-Benachrichtigungen senden
            $escalationChannels = $escalationRules['channels'] ?? ['email'];
            
            $escalatedAlert = array_merge($alert, [
                'message' => $escalationMessage,
                'escalated' => true
            ]);

            foreach ($escalationChannels as $channel) {
                if (isset($this->notificationChannels[$channel])) {
                    $this->notificationChannels[$channel]->send($escalatedAlert, ['urgent' => true]);
                }
            }

            return $escalatedAlert;

        } catch (Exception $e) {
            error_log("AlertService: Failed to execute escalation: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Berechnet Zeit-Differenz für Escalation
     */
    private function getTimeDifference(string $timestamp): string
    {
        $now = time();
        $created = strtotime($timestamp);
        $diff = $now - $created;

        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }

    /**
     * Löst Alert auf
     */
    public function resolveAlert(int $alertLogId, ?int $userId = null, ?string $notes = null): bool
    {
        try {
            $this->db->update(
                'UPDATE alert_logs SET 
                 resolved_at = NOW(),
                 resolved_by = ?,
                 resolution_notes = ?
                 WHERE id = ?',
                [$userId, $notes, $alertLogId]
            );

            return true;

        } catch (Exception $e) {
            error_log("AlertService: Failed to resolve alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Erstellt neue Alert-Regel
     */
    public function createAlert(array $alertData): ?int
    {
        try {
            $required = ['name', 'alert_type', 'metric_type', 'condition_operator', 'threshold_value', 'severity'];
            foreach ($required as $field) {
                if (!isset($alertData[$field])) {
                    throw new Exception("Required field missing: {$field}");
                }
            }

            return $this->db->insert(
                'INSERT INTO alerts (
                    name, description, alert_type, metric_type, condition_operator,
                    threshold_value, time_window_minutes, evaluation_frequency_minutes,
                    severity, enabled, notification_channels, escalation_rules,
                    suppression_rules, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $alertData['name'],
                    $alertData['description'] ?? null,
                    $alertData['alert_type'],
                    $alertData['metric_type'],
                    $alertData['condition_operator'],
                    $alertData['threshold_value'],
                    $alertData['time_window_minutes'] ?? 5,
                    $alertData['evaluation_frequency_minutes'] ?? 1,
                    $alertData['severity'],
                    $alertData['enabled'] ?? true,
                    json_encode($alertData['notification_channels'] ?? []),
                    json_encode($alertData['escalation_rules'] ?? []),
                    json_encode($alertData['suppression_rules'] ?? []),
                    $alertData['created_by'] ?? null
                ]
            );

        } catch (Exception $e) {
            error_log("AlertService: Failed to create alert: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test-Alert senden
     */
    public function sendTestAlert(): array
    {
        $testAlert = [
            'alert_log_id' => 0,
            'alert_name' => 'Test Alert',
            'severity' => 'medium',
            'message' => 'Dies ist ein Test-Alert vom Metropol Portal Monitoring-System.',
            'created_at' => date('c'),
            'trigger_result' => [
                'metric_value' => 42,
                'threshold_value' => 40,
                'evaluation_time' => date('c')
            ]
        ];

        $results = [];
        foreach ($this->notificationChannels as $type => $channel) {
            try {
                $result = $channel->send($testAlert, ['test' => true]);
                $results[$type] = $result;
            } catch (Exception $e) {
                $results[$type] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}

/**
 * E-Mail Notification Channel
 */
class EmailNotificationChannel
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $alert, array $options = []): array
    {
        $to = $options['email'] ?? $this->config['default_alert_email'];
        $subject = "[{$alert['severity']}] {$alert['alert_name']} - Metropol Portal";
        $message = $this->buildEmailMessage($alert);

        if (function_exists('mail')) {
            $headers = [
                'From: ' . $this->config['smtp_from_name'] . ' <' . $this->config['smtp_from_email'] . '>',
                'Content-Type: text/html; charset=UTF-8',
                'X-Priority: ' . ($alert['severity'] === 'critical' ? '1' : '3')
            ];

            $success = mail($to, $subject, $message, implode("\r\n", $headers));
            
            return [
                'success' => $success,
                'response' => $success ? 'Email sent' : 'Email failed'
            ];
        }

        return ['success' => false, 'response' => 'Mail function not available'];
    }

    private function buildEmailMessage(array $alert): string
    {
        $severityColors = [
            'critical' => '#dc2626',
            'high' => '#ea580c',
            'medium' => '#d97706',
            'low' => '#2563eb'
        ];

        $color = $severityColors[$alert['severity']] ?? '#6b7280';

        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: {$color}; color: white; padding: 15px; border-radius: 5px 5px 0 0;'>
                    <h2 style='margin: 0;'>" . strtoupper($alert['severity']) . " ALERT</h2>
                </div>
                <div style='background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px;'>
                    <h3 style='color: {$color}; margin-top: 0;'>{$alert['alert_name']}</h3>
                    <p><strong>Nachricht:</strong> {$alert['message']}</p>
                    <p><strong>Zeitpunkt:</strong> {$alert['created_at']}</p>
                    
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                    
                    <p style='font-size: 12px; color: #666;'>
                        Dieses Alert wurde automatisch vom Metropol Portal Monitoring-System generiert.<br>
                        Entwickelt von 2Brands Media GmbH
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }
}

/**
 * Webhook Notification Channel
 */
class WebhookNotificationChannel
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $alert, array $options = []): array
    {
        $webhookUrl = $options['webhook_url'] ?? $this->config['webhook_urls']['slack'] ?? '';
        
        if (empty($webhookUrl)) {
            return ['success' => false, 'response' => 'No webhook URL configured'];
        }

        $payload = $this->buildWebhookPayload($alert, $options);
        
        return $this->sendWebhook($webhookUrl, $payload);
    }

    private function buildWebhookPayload(array $alert, array $options): array
    {
        // Slack-Format
        return [
            'text' => "[{$alert['severity']}] {$alert['alert_name']}",
            'attachments' => [
                [
                    'color' => $this->getSeverityColor($alert['severity']),
                    'fields' => [
                        [
                            'title' => 'Message',
                            'value' => $alert['message'],
                            'short' => false
                        ],
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($alert['severity']),
                            'short' => true
                        ],
                        [
                            'title' => 'Time',
                            'value' => $alert['created_at'],
                            'short' => true
                        ]
                    ]
                ]
            ]
        ];
    }

    private function sendWebhook(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error
        ];
    }

    private function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'warning',
            'low' => 'good',
            default => 'warning'
        };
    }
}

/**
 * Log Notification Channel
 */
class LogNotificationChannel
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $alert, array $options = []): array
    {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ALERT [{$alert['severity']}] {$alert['alert_name']}: {$alert['message']}";
        
        error_log($logMessage);
        
        // Zusätzlich in separates Alert-Log
        $logFile = '/tmp/metropol_alerts.log';
        file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);

        return [
            'success' => true,
            'response' => 'Logged to system and alert log'
        ];
    }
}