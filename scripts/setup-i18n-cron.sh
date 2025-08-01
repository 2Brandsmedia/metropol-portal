#!/bin/bash

# I18n Monitoring Cron-Job Setup Script
# 
# Konfiguriert automatische Übersetzungswartung als Cron-Jobs
# 
# @author 2Brands Media GmbH

set -e

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script-Verzeichnis ermitteln
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}🌐 I18n Monitoring Cron-Job Setup${NC}"
echo "========================================"
echo ""

# PHP-Pfad ermitteln
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo -e "${RED}❌ PHP nicht gefunden. Bitte PHP installieren.${NC}"
    exit 1
fi

echo -e "PHP-Pfad: ${GREEN}$PHP_PATH${NC}"
echo -e "Projekt-Verzeichnis: ${GREEN}$PROJECT_ROOT${NC}"
echo ""

# Überprüfe ob Scripts vorhanden sind
if [ ! -f "$SCRIPT_DIR/i18n-monitor.php" ]; then
    echo -e "${RED}❌ i18n-monitor.php nicht gefunden!${NC}"
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/i18n-maintenance.php" ]; then
    echo -e "${RED}❌ i18n-maintenance.php nicht gefunden!${NC}"
    exit 1
fi

# Log-Verzeichnis erstellen
LOG_DIR="$PROJECT_ROOT/logs"
mkdir -p "$LOG_DIR"
echo -e "Log-Verzeichnis erstellt: ${GREEN}$LOG_DIR${NC}"

# Backup-Verzeichnis erstellen
BACKUP_DIR="$PROJECT_ROOT/lang/backups"
mkdir -p "$BACKUP_DIR"
echo -e "Backup-Verzeichnis erstellt: ${GREEN}$BACKUP_DIR${NC}"

# Cron-Jobs definieren
CRON_JOBS=(
    # Alle 15 Minuten: Monitoring
    "*/15 * * * * cd $PROJECT_ROOT && $PHP_PATH scripts/i18n-monitor.php run >> logs/i18n-monitor-cron.log 2>&1"
    
    # Täglich um 02:00: Vollständige Wartung mit Backup
    "0 2 * * * cd $PROJECT_ROOT && $PHP_PATH scripts/i18n-maintenance.php backup >> logs/i18n-daily-backup.log 2>&1"
    
    # Täglich um 02:30: Konsistenzprüfung
    "30 2 * * * cd $PROJECT_ROOT && $PHP_PATH scripts/i18n-maintenance.php check >> logs/i18n-daily-check.log 2>&1"
    
    # Wöchentlich am Sonntag um 03:00: Synchronisation
    "0 3 * * 0 cd $PROJECT_ROOT && $PHP_PATH scripts/i18n-maintenance.php sync >> logs/i18n-weekly-sync.log 2>&1"
    
    # Monatlich am 1. um 04:00: Cleanup ungenutzter Schlüssel (nur Report)
    "0 4 1 * * cd $PROJECT_ROOT && $PHP_PATH scripts/i18n-maintenance.php unused >> logs/i18n-monthly-cleanup.log 2>&1"
)

# Aktuelle Crontab sichern
echo -e "${YELLOW}💾 Sichere aktuelle Crontab...${NC}"
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S) 2>/dev/null || echo "" > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S)

# Neue Cron-Jobs vorbereiten
echo -e "${BLUE}📝 Bereite neue Cron-Jobs vor...${NC}"

# Temporäre Crontab-Datei erstellen
TEMP_CRON=$(mktemp)

# Bestehende Crontab laden (falls vorhanden)
crontab -l 2>/dev/null > "$TEMP_CRON" || true

# Header für I18n-Jobs hinzufügen
echo "" >> "$TEMP_CRON"
echo "# I18n Monitoring and Maintenance Jobs - Generated $(date)" >> "$TEMP_CRON"
echo "# Project: Metropol Portal" >> "$TEMP_CRON"
echo "# Path: $PROJECT_ROOT" >> "$TEMP_CRON"

# Cron-Jobs hinzufügen
for job in "${CRON_JOBS[@]}"; do
    echo "$job" >> "$TEMP_CRON"
done

echo "" >> "$TEMP_CRON"
echo "# End I18n Jobs" >> "$TEMP_CRON"

# Crontab installieren
echo -e "${YELLOW}⚙️  Installiere Cron-Jobs...${NC}"

if crontab "$TEMP_CRON"; then
    echo -e "${GREEN}✅ Cron-Jobs erfolgreich installiert!${NC}"
else
    echo -e "${RED}❌ Fehler beim Installieren der Cron-Jobs${NC}"
    rm "$TEMP_CRON"
    exit 1
fi

# Aufräumen
rm "$TEMP_CRON"

# Aktuelle Crontab anzeigen
echo ""
echo -e "${BLUE}📅 Installierte Cron-Jobs:${NC}"
echo "=========================="
crontab -l | grep -A 20 "I18n Monitoring"

# Berechtigungen prüfen
echo ""
echo -e "${BLUE}🔐 Überprüfe Berechtigungen...${NC}"

# Script-Berechtigungen
chmod +x "$SCRIPT_DIR/i18n-monitor.php"
chmod +x "$SCRIPT_DIR/i18n-maintenance.php"
echo -e "Scripts: ${GREEN}Ausführbar${NC}"

# Log-Verzeichnis-Berechtigungen
chmod 755 "$LOG_DIR"
echo -e "Logs: ${GREEN}Beschreibbar${NC}"

# Backup-Verzeichnis-Berechtigungen
chmod 755 "$BACKUP_DIR"
echo -e "Backups: ${GREEN}Beschreibbar${NC}"

# Übersetzungsdateien-Berechtigungen
LANG_DIR="$PROJECT_ROOT/lang"
if [ -d "$LANG_DIR" ]; then
    chmod 644 "$LANG_DIR"/*.json 2>/dev/null || true
    echo -e "Übersetzungen: ${GREEN}Lesbar/Schreibbar${NC}"
fi

# Test-Ausführung
echo ""
echo -e "${YELLOW}🧪 Führe Test-Ausführung durch...${NC}"

# Monitor-Test
if cd "$PROJECT_ROOT" && "$PHP_PATH" scripts/i18n-monitor.php test >/dev/null 2>&1; then
    echo -e "Monitor-Test: ${GREEN}✅ Erfolgreich${NC}"
else
    echo -e "Monitor-Test: ${RED}❌ Fehlgeschlagen${NC}"
fi

# Maintenance-Test
if cd "$PROJECT_ROOT" && "$PHP_PATH" scripts/i18n-maintenance.php status >/dev/null 2>&1; then
    echo -e "Maintenance-Test: ${GREEN}✅ Erfolgreich${NC}"
else
    echo -e "Maintenance-Test: ${RED}❌ Fehlgeschlagen${NC}"
fi

# Log-Rotation Setup
echo ""
echo -e "${BLUE}📜 Konfiguriere Log-Rotation...${NC}"

LOGROTATE_CONFIG="/etc/logrotate.d/metropol-i18n"

# Prüfe ob logrotate verfügbar ist
if command -v logrotate >/dev/null 2>&1; then
    # Logrotate-Konfiguration erstellen (benötigt sudo)
    cat > /tmp/metropol-i18n-logrotate << EOF
$LOG_DIR/i18n-*.log {
    daily
    rotate 30
    compress
    missingok
    notifempty
    create 644 $(whoami) $(whoami)
    copytruncate
}
EOF

    echo -e "${YELLOW}📋 Logrotate-Konfiguration erstellt: /tmp/metropol-i18n-logrotate${NC}"
    echo -e "${YELLOW}💡 Zum Installieren als root ausführen:${NC}"
    echo -e "   ${GREEN}sudo cp /tmp/metropol-i18n-logrotate $LOGROTATE_CONFIG${NC}"
else
    echo -e "${YELLOW}⚠️  logrotate nicht verfügbar - manuelle Log-Rotation erforderlich${NC}"
fi

# Monitoring-Dashboard URL
echo ""
echo -e "${BLUE}📊 Monitoring-Dashboard:${NC}"
echo -e "   Web-Interface: ${GREEN}https://your-domain.com/admin/i18n${NC}"
echo -e "   CLI-Status: ${GREEN}php scripts/i18n-monitor.php status${NC}"

# Nützliche Kommandos
echo ""
echo -e "${BLUE}🛠️  Nützliche Kommandos:${NC}"
echo -e "   Monitor-Status: ${GREEN}php scripts/i18n-monitor.php status${NC}"
echo -e "   Manuelle Prüfung: ${GREEN}php scripts/i18n-maintenance.php check${NC}"
echo -e "   Synchronisation: ${GREEN}php scripts/i18n-maintenance.php sync${NC}"
echo -e "   Backup erstellen: ${GREEN}php scripts/i18n-maintenance.php backup${NC}"
echo -e "   Cron-Jobs anzeigen: ${GREEN}crontab -l | grep i18n${NC}"
echo -e "   Logs verfolgen: ${GREEN}tail -f logs/i18n-monitor.log${NC}"

# Warnung für Produktionsumgebung
echo ""
echo -e "${YELLOW}⚠️  WICHTIG für Produktionsumgebung:${NC}"
echo -e "   1. Überprüfen Sie alle Pfade und Berechtigungen"
echo -e "   2. Konfigurieren Sie E-Mail-Benachrichtigungen"
echo -e "   3. Testen Sie alle Cron-Jobs vor dem Go-Live"
echo -e "   4. Überwachen Sie die Log-Dateien in den ersten Tagen"
echo -e "   5. Stellen Sie sicher, dass Backups funktionieren"

echo ""
echo -e "${GREEN}🎉 I18n Monitoring Setup abgeschlossen!${NC}"
echo ""

# Cron-Service-Status prüfen
if systemctl is-active --quiet cron 2>/dev/null || systemctl is-active --quiet crond 2>/dev/null; then
    echo -e "${GREEN}✅ Cron-Service läuft${NC}"
else
    echo -e "${YELLOW}⚠️  Cron-Service-Status unbekannt - bitte manuell prüfen${NC}"
fi

echo ""
echo -e "${BLUE}📖 Weitere Informationen:${NC}"
echo -e "   - Dokumentation: docs/I18N_MONITORING.md"
echo -e "   - Logs: $LOG_DIR/"
echo -e "   - Backups: $BACKUP_DIR/"