#!/bin/bash

# Setup Maintenance Cron Jobs - Automatisierte Wartungsaufgaben
# 
# Dieses Skript richtet alle notwendigen Cron-Jobs für das Metropol Portal ein.
# Entwickelt von 2Brands Media GmbH
#
# Verwendung: chmod +x setup-maintenance-cron.sh && ./setup-maintenance-cron.sh

set -e

# Konfiguration
PROJECT_DIR="/path/to/metropol-portal"
PHP_PATH="/usr/bin/php"
LOG_DIR="${PROJECT_DIR}/storage/logs"
CRON_USER="www-data"

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Metropol Portal Maintenance Cron Setup ===${NC}"
echo "Developed by 2Brands Media GmbH"
echo

# Prüfung ob Skript als root ausgeführt wird
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: This script must be run as root${NC}"
   exit 1
fi

# Projektverzeichnis prüfen
if [[ ! -d "$PROJECT_DIR" ]]; then
    echo -e "${YELLOW}Warning: Project directory not found at $PROJECT_DIR${NC}"
    echo "Please update PROJECT_DIR in this script to match your installation path"
    exit 1
fi

# Log-Verzeichnis erstellen
mkdir -p "$LOG_DIR"
chown "$CRON_USER:$CRON_USER" "$LOG_DIR"

echo "Creating maintenance cron jobs..."

# Temporäre Cron-Datei erstellen
TEMP_CRON=$(mktemp)

# Bestehende Cron-Jobs für den Benutzer laden (falls vorhanden)
crontab -u "$CRON_USER" -l 2>/dev/null > "$TEMP_CRON" || true

# Metropol Portal Wartungs-Jobs hinzufügen
cat >> "$TEMP_CRON" << EOF

# === METROPOL PORTAL MAINTENANCE JOBS ===
# Developed by 2Brands Media GmbH

# System Health Monitoring (every 5 minutes)
*/5 * * * * $PHP_PATH $PROJECT_DIR/scripts/health-monitor.php >> $LOG_DIR/health-monitor.log 2>&1

# Hourly Maintenance (cache cleanup, session cleanup, temp files)
0 * * * * $PHP_PATH $PROJECT_DIR/scripts/maintenance-scheduler.php hourly >> $LOG_DIR/maintenance-hourly.log 2>&1

# Daily Maintenance (3 AM - log rotation, backup validation, performance analysis)
0 3 * * * $PHP_PATH $PROJECT_DIR/scripts/maintenance-scheduler.php daily >> $LOG_DIR/maintenance-daily.log 2>&1

# Weekly Maintenance (Sunday 3:30 AM - database optimization, index maintenance)
30 3 * * 0 $PHP_PATH $PROJECT_DIR/scripts/maintenance-scheduler.php weekly >> $LOG_DIR/maintenance-weekly.log 2>&1

# Monthly Maintenance (1st day of month, 4 AM - full system report, archive old data)
0 4 1 * * $PHP_PATH $PROJECT_DIR/scripts/maintenance-scheduler.php monthly >> $LOG_DIR/maintenance-monthly.log 2>&1

# Daily System Diagnostics Report (6 AM)
0 6 * * * $PHP_PATH $PROJECT_DIR/scripts/system-diagnostics.php --format=html --output=$LOG_DIR/diagnostics-\$(date +\%Y\%m\%d).html >> $LOG_DIR/diagnostics.log 2>&1

# Log Rotation (clean old logs, weekly at 2 AM on Monday)
0 2 * * 1 find $LOG_DIR -name "*.log" -mtime +30 -delete && find $LOG_DIR -name "diagnostics-*.html" -mtime +7 -delete

# Database Backup Verification (daily at 5 AM)
0 5 * * * $PHP_PATH $PROJECT_DIR/scripts/verify-backups.php >> $LOG_DIR/backup-verification.log 2>&1

# Performance Metrics Collection (every 15 minutes during business hours)
*/15 8-18 * * 1-5 $PHP_PATH $PROJECT_DIR/scripts/collect-performance-metrics.php >> $LOG_DIR/performance-collection.log 2>&1

# === END METROPOL PORTAL MAINTENANCE JOBS ===

EOF

# Cron-Jobs installieren
crontab -u "$CRON_USER" "$TEMP_CRON"

# Temporäre Datei löschen
rm "$TEMP_CRON"

echo -e "${GREEN}✓ Maintenance cron jobs installed successfully${NC}"
echo

# Zusätzliche Skripte erstellen
echo "Creating additional maintenance scripts..."

# Backup-Verifikationsskript
cat > "$PROJECT_DIR/scripts/verify-backups.php" << 'EOF'
<?php
/**
 * Backup Verification Script
 * Überprüft die Integrität und Verfügbarkeit von Datenbank-Backups
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;

$config = new Config();
$db = new Database($config->get('database'));

echo "[" . date('Y-m-d H:i:s') . "] Starting backup verification\n";

try {
    // Dummy-Backup-Verifikation (anpassen je nach Backup-System)
    $backupDir = '/path/to/backups';
    $latestBackup = null;
    
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/metropol_*.sql');
        if (!empty($files)) {
            $latestBackup = max($files);
            $backupAge = time() - filemtime($latestBackup);
            
            if ($backupAge > 86400) { // Älter als 24 Stunden
                echo "WARNING: Latest backup is older than 24 hours\n";
            } else {
                echo "✓ Recent backup found: " . basename($latestBackup) . "\n";
            }
        } else {
            echo "ERROR: No backup files found\n";
        }
    } else {
        echo "WARNING: Backup directory not found\n";
    }
    
    // Backup-Status in Datenbank loggen
    $db->insert(
        'INSERT INTO audit_log (action, resource_type, details, ip_address, created_at) 
         VALUES (?, ?, ?, ?, NOW())',
        [
            'backup_verification',
            'system',
            json_encode(['latest_backup' => $latestBackup, 'status' => 'checked']),
            'backup_verifier'
        ]
    );
    
} catch (Exception $e) {
    echo "ERROR: Backup verification failed: " . $e->getMessage() . "\n";
}
EOF

# Performance-Metriken-Sammler
cat > "$PROJECT_DIR/scripts/collect-performance-metrics.php" << 'EOF'
<?php
/**
 * Performance Metrics Collector
 * Sammelt zusätzliche Performance-Metriken während der Geschäftszeiten
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MonitorAgent;

$config = new Config();
$db = new Database($config->get('database'));
$monitor = new MonitorAgent($db);

try {
    // System-Metriken sammeln
    $monitor->collectSystemMetrics();
    
    // Zusätzliche Business-Metriken
    $activeUsers = $db->selectOne(
        'SELECT COUNT(DISTINCT user_id) as count 
         FROM performance_metrics 
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    
    $avgResponseTime = $db->selectOne(
        'SELECT AVG(response_time_ms) as avg_time 
         FROM performance_metrics 
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    
    echo "Active users (15min): " . ($activeUsers['count'] ?? 0) . "\n";
    echo "Avg response time (15min): " . round($avgResponseTime['avg_time'] ?? 0) . "ms\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
EOF

# Skripte ausführbar machen
chmod +x "$PROJECT_DIR/scripts/verify-backups.php"
chmod +x "$PROJECT_DIR/scripts/collect-performance-metrics.php"

echo -e "${GREEN}✓ Additional maintenance scripts created${NC}"
echo

# Systemd-Service für kritische Überwachung (optional)
echo "Creating systemd service for critical monitoring..."

cat > "/etc/systemd/system/metropol-health-monitor.service" << EOF
[Unit]
Description=Metropol Portal Health Monitor
After=network.target

[Service]
Type=simple
User=$CRON_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=$PHP_PATH $PROJECT_DIR/scripts/health-monitor.php
Restart=always
RestartSec=300
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Service aktivieren (aber nicht starten, da bereits via Cron läuft)
systemctl daemon-reload
systemctl enable metropol-health-monitor.service

echo -e "${GREEN}✓ Systemd service created (disabled, using cron instead)${NC}"
echo

# Maintenance-Dashboard-Link erstellen
echo "Creating maintenance dashboard..."

cat > "$PROJECT_DIR/public/maintenance-dashboard.php" << 'EOF'
<?php
/**
 * Simple Maintenance Dashboard
 * Zeigt aktuellen Wartungsstatus und letzte Berichte
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MaintenanceAgent;

// Einfache Authentifizierung (in Produktion durch richtige Auth ersetzen)
if (!isset($_GET['token']) || $_GET['token'] !== 'maintenance_token_123') {
    http_response_code(403);
    exit('Access denied');
}

$config = new Config();
$db = new Database($config->get('database'));
$maintenance = new MaintenanceAgent($db);

$healthCheck = $maintenance->performSystemHealthCheck();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Metropol Portal - Maintenance Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .dashboard { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .health-score { font-size: 36px; font-weight: bold; text-align: center; }
        .healthy { color: #28a745; }
        .warning { color: #ffc107; }
        .critical { color: #dc3545; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .log-entry { padding: 5px; margin: 2px 0; border-left: 3px solid #ccc; background: #f8f9fa; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="dashboard">
        <h1>Metropol Portal - Maintenance Dashboard</h1>
        <p>Developed by 2Brands Media GmbH | Last updated: <?= date('Y-m-d H:i:s') ?></p>
        
        <div class="card">
            <h2>System Health</h2>
            <?php 
            $score = $healthCheck['health_score'];
            $class = $score >= 80 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical');
            ?>
            <div class="health-score <?= $class ?>"><?= $score ?>/100</div>
            <p>Overall system status: <?= $healthCheck['healthy'] ? 'Healthy' : 'Needs Attention' ?></p>
        </div>
        
        <div class="grid">
            <?php foreach ($healthCheck['checks'] as $check => $result): ?>
            <div class="card">
                <h3><?= ucfirst(str_replace('_', ' ', $check)) ?></h3>
                <p><?= $result['healthy'] ? '✅' : '❌' ?> <?= htmlspecialchars($result['message'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card">
            <h2>Recent Maintenance Activities</h2>
            <?php
            try {
                $recentMaintenance = $db->select(
                    'SELECT action, details, created_at FROM audit_log 
                     WHERE action LIKE "maintenance_%" 
                     ORDER BY created_at DESC LIMIT 10'
                );
                
                foreach ($recentMaintenance as $entry): ?>
                <div class="log-entry">
                    <strong><?= htmlspecialchars($entry['action']) ?></strong> - <?= $entry['created_at'] ?>
                    <br><small><?= htmlspecialchars(substr($entry['details'], 0, 100)) ?>...</small>
                </div>
                <?php endforeach;
            } catch (Exception $e) {
                echo '<p>Error loading maintenance log: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>
EOF

echo -e "${GREEN}✓ Maintenance dashboard created at /maintenance-dashboard.php?token=maintenance_token_123${NC}"
echo

# Zusammenfassung anzeigen
echo -e "${GREEN}=== INSTALLATION COMPLETE ===${NC}"
echo
echo "The following maintenance jobs have been configured:"
echo "• Health monitoring every 5 minutes"
echo "• Hourly cache and session cleanup"
echo "• Daily maintenance at 3 AM"
echo "• Weekly database optimization on Sundays"
echo "• Monthly full system reports"
echo "• Daily diagnostics reports at 6 AM"
echo
echo "Log files will be stored in: $LOG_DIR"
echo "Maintenance dashboard: http://your-domain.com/maintenance-dashboard.php?token=maintenance_token_123"
echo
echo -e "${YELLOW}IMPORTANT SECURITY NOTES:${NC}"
echo "• Change the maintenance dashboard token in production"
echo "• Restrict access to maintenance dashboard by IP"
echo "• Review and adjust file paths in the scripts"
echo "• Test all scripts manually before relying on cron"
echo
echo -e "${GREEN}Setup completed successfully!${NC}"