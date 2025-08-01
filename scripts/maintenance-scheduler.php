<?php

declare(strict_types=1);

/**
 * Maintenance Scheduler - Automatisierte Wartungsaufgaben
 * 
 * Dieses Skript sollte über Cron ausgeführt werden:
 * 
 * # Stündliche Wartung
 * 0 * * * * php /path/to/scripts/maintenance-scheduler.php hourly
 * 
 * # Tägliche Wartung (3 Uhr morgens)
 * 0 3 * * * php /path/to/scripts/maintenance-scheduler.php daily
 * 
 * # Wöchentliche Wartung (Sonntag 3 Uhr)
 * 0 3 * * 0 php /path/to/scripts/maintenance-scheduler.php weekly
 * 
 * # Monatliche Wartung (1. des Monats, 3 Uhr)
 * 0 3 1 * * php /path/to/scripts/maintenance-scheduler.php monthly
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MaintenanceAgent;

// Konfiguration laden
$config = new Config();
$db = new Database($config->get('database'));

// Wartungs-Agent initialisieren
$maintenanceAgent = new MaintenanceAgent($db, [
    'enable_automated_maintenance' => true,
    'enable_performance_optimization' => true,
    'enable_security_maintenance' => true,
    'log_level' => 'info'
]);

// Schedule aus Kommandozeilen-Argument
$schedule = $argv[1] ?? 'daily';
$validSchedules = ['hourly', 'daily', 'weekly', 'monthly', 'emergency'];

if (!in_array($schedule, $validSchedules)) {
    echo "Error: Invalid schedule '{$schedule}'. Valid options: " . implode(', ', $validSchedules) . "\n";
    exit(1);
}

echo "Starting {$schedule} maintenance at " . date('Y-m-d H:i:s') . "\n";

try {
    // System-Gesundheitscheck vor Wartung
    $healthCheck = $maintenanceAgent->performSystemHealthCheck();
    
    if (!$healthCheck['healthy'] && $schedule !== 'emergency') {
        echo "WARNING: System health check failed (Score: {$healthCheck['health_score']}/100)\n";
        
        // Bei kritischen Problemen automatisch Notfall-Wartung
        if ($healthCheck['health_score'] < 50) {
            echo "Triggering emergency maintenance due to low health score\n";
            $schedule = 'emergency';
        }
    }

    // Wartung ausführen
    $result = $maintenanceAgent->runScheduledMaintenance($schedule);

    // Ergebnisse ausgeben
    echo "Maintenance completed in {$result['duration_seconds']} seconds\n";
    
    if (isset($result['tasks'])) {
        echo "\nTask Results:\n";
        foreach ($result['tasks'] as $task => $taskResult) {
            $status = $taskResult['success'] ? 'SUCCESS' : 'FAILED';
            $duration = $taskResult['duration_seconds'] ?? 0;
            echo "  {$task}: {$status} ({$duration}s)\n";
            
            if (!$taskResult['success']) {
                echo "    Error: {$taskResult['error']}\n";
            }
        }
    }

    // Post-Wartung Gesundheitscheck
    if (isset($result['post_health_check'])) {
        $postHealth = $result['post_health_check'];
        echo "\nPost-maintenance health score: {$postHealth['health_score']}/100\n";
        
        if ($postHealth['health_score'] > $healthCheck['health_score']) {
            echo "✓ System health improved\n";
        } elseif ($postHealth['health_score'] < $healthCheck['health_score']) {
            echo "⚠ System health degraded - investigation needed\n";
        }
    }

    // Exit-Code basierend auf Erfolg
    $exitCode = 0;
    if (isset($result['tasks'])) {
        foreach ($result['tasks'] as $taskResult) {
            if (!$taskResult['success']) {
                $exitCode = 1;
                break;
            }
        }
    }

    exit($exitCode);

} catch (Exception $e) {
    echo "CRITICAL ERROR: Maintenance failed with exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    // Bei kritischen Fehlern Notfall-Wartung versuchen
    if ($schedule !== 'emergency') {
        echo "\nAttempting emergency maintenance...\n";
        try {
            $emergencyResult = $maintenanceAgent->runEmergencyMaintenance('scheduler_exception');
            echo "Emergency maintenance completed\n";
        } catch (Exception $emergencyException) {
            echo "Emergency maintenance also failed: " . $emergencyException->getMessage() . "\n";
        }
    }
    
    exit(2);
}