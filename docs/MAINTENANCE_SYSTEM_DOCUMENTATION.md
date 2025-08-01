# Metropol Portal - Wartungssystem Dokumentation

## Übersicht

Das automatisierte Wartungssystem des Metropol Portals sorgt für optimale Systemleistung mit minimalem manuellen Eingriff. Das System wurde von **2Brands Media GmbH** entwickelt und bietet umfassende Wartung, Überwachung und proaktive Problembehandlung.

## 🎯 Erfolgskriterien

- **Minimaler manueller Eingriff** bei optimaler Systemleistung
- **Performance-Ziele** werden kontinuierlich eingehalten:
  - Login: < 100ms
  - Routenberechnung: < 300ms  
  - API-Response: < 200ms
  - Datenbank-Queries: < 50ms
- **99.9% Uptime** durch proaktives Monitoring
- **Automatische Wiederherstellung** bei kritischen Zuständen

## 🏗️ Systemarchitektur

### MaintenanceAgent (Kern-Agent)
- **Zweck**: Koordiniert alle Wartungsaktivitäten
- **Verantwortlichkeiten**:
  - Automatische Datenbankwartung und -optimierung
  - System-Gesundheitsüberwachung mit proaktiven Maßnahmen  
  - Performance-Optimierung und Cache-Management
  - Sicherheitswartung und Log-Analyse
  - Automatisches Reporting und Kapazitätsplanung

### Unterstützende Komponenten
- **MonitorAgent**: Sammelt System- und Performance-Metriken
- **Health Monitor**: Kontinuierliche Systemüberwachung (alle 5 Min)
- **Diagnostics System**: Detaillierte Systemanalyse und Reporting
- **Scheduler Scripts**: Cron-basierte Wartungsausführung

## 📅 Wartungszeitpläne

### Stündlich
- Cache-Bereinigung (abgelaufene Einträge)
- Session-Cleanup (inaktive Sessions)  
- Temporäre Dateien löschen
- System-Metriken sammeln

### Täglich (3:00 Uhr)
- Log-Rotation und Archivierung
- Backup-Validierung
- Performance-Analyse
- Sicherheitsscan
- Vollständiger Gesundheitscheck

### Wöchentlich (Sonntag 3:30 Uhr)
- Datenbank-Optimierung
- Index-Wartung und Analyse
- Kapazitätsanalyse
- Performance-Trends-Analyse

### Monatlich (1. des Monats, 4:00 Uhr)
- Vollständiger Systemreport
- Alte Daten archivieren
- Sicherheitsaudit
- Kapazitätsplanung-Update

## 🗄️ Datenaufbewahrung

| Datentyp | Aufbewahrungszeit |
|----------|-------------------|
| Performance-Metriken | 90 Tage |
| Error-Logs | 180 Tage |
| System-Metriken | 30 Tage |
| Audit-Logs | 365 Tage |
| Sessions | 30 Tage |
| Geocache | 90 Tage |
| Cache-Einträge | 7 Tage |
| Alert-Logs | 30 Tage |

## 🚨 Notfall-Wartung

Das System löst automatisch Notfall-Wartung aus bei:
- **CPU/Memory > 95%**
- **Disk-Usage > 95%**
- **Health-Score < 50**
- **Kritische Sicherheitsfehler**

### Notfall-Maßnahmen
1. **Speicher freigeben**: Cache komplett leeren
2. **Sessions bereinigen**: Inaktive Sessions sofort beenden
3. **Temp-Dateien löschen**: Alle temporären Dateien entfernen
4. **Datenbank optimieren**: Kritische Tabellen sofort optimieren

## 📊 Monitoring und Metriken

### System-Gesundheit
- **Health-Score**: 0-100 basierend auf gewichteten Checks
  - Datenbank-Gesundheit (25%)
  - Speicherverbrauch (20%)
  - Festplattenspeicher (15%)
  - Performance-Metriken (15%)
  - Fehlerrate (15%)
  - Cache-Gesundheit (10%)

### Performance-Überwachung
- **Response-Zeiten**: Alle API-Endpunkte überwacht
- **Datenbank-Performance**: Query-Zeiten und Optimierungsempfehlungen
- **Ressourcenverbrauch**: CPU, Memory, Disk kontinuierlich überwacht
- **Cache-Effizienz**: Hit/Miss-Raten und Optimierungsvorschläge

## 🔧 Installation und Konfiguration

### 1. Cron-Jobs einrichten
```bash
# Als root ausführen
chmod +x scripts/setup-maintenance-cron.sh
./scripts/setup-maintenance-cron.sh
```

### 2. Manuelle Testausführung
```bash
# Health-Check
php scripts/health-monitor.php

# Tägliche Wartung testen
php scripts/maintenance-scheduler.php daily

# Vollständige Diagnose
php scripts/system-diagnostics.php --format=html --output=report.html
```

### 3. Konfiguration anpassen
```php
// In MaintenanceAgent-Konstruktor
$config = [
    'maintenance_window_hours' => [2, 3, 4], // 2-4 Uhr nachts
    'emergency_maintenance_threshold' => 95,  // CPU/Memory %
    'max_maintenance_duration_minutes' => 30
];
```

## 🌐 API-Endpunkte

### Systemstatus
```http
GET /api/maintenance/health
GET /api/maintenance/status  
GET /api/maintenance/metrics?timeframe=24h
GET /api/maintenance/diagnostics
```

### Wartungsausführung (nur Admins)
```http
POST /api/maintenance/run
{
  "schedule": "daily|weekly|monthly|emergency"
}

POST /api/maintenance/emergency
{
  "reason": "manual_trigger"
}

POST /api/maintenance/task/cache_cleanup
```

### Spezielle Aktionen
```http
POST /api/maintenance/cache/clear
POST /api/maintenance/database/optimize
```

## 📈 Dashboard und Reporting

### Maintenance Dashboard
- **URL**: `/maintenance-dashboard.php?token=maintenance_token_123`
- **Features**:
  - Live Health-Score
  - Aktuelle System-Checks
  - Wartungshistorie
  - Performance-Trends

### Diagnostics Reports
- **Täglich**: Automatischer HTML-Report um 6:00 Uhr
- **Auf Abruf**: `system-diagnostics.php` mit verschiedenen Formaten
- **Inhalte**: Performance, Sicherheit, Kapazität, Empfehlungen

## 🔐 Sicherheit

### Zugriffskontrolle
- **API-Endpunkte**: Nur für authentifizierte Admins
- **Dashboard**: Token-basierte Authentifizierung
- **Scripts**: Ausführung nur durch System-User

### Audit-Trail
- **Alle Wartungsaktivitäten** werden in `audit_log` protokolliert
- **Detaillierte Logs** mit Zeitstempel, Benutzer, Aktionen
- **Error-Tracking** mit Stack-Traces und Context

## 🚀 Performance-Optimierung

### Automatische Optimierungen
- **Query-Optimierung**: Langsame Queries werden identifiziert
- **Index-Wartung**: Ungenutzte Indizes werden erkannt
- **Cache-Optimierung**: Hit-Raten werden maximiert
- **Speicher-Management**: Automatische Bereinigung

### Empfehlungssystem
- **Performance-Bottlenecks** werden automatisch erkannt
- **Optimierungsvorschläge** basierend auf Metriken
- **Kapazitätsplanung** mit Wachstumstrends
- **Proaktive Skalierungsempfehlungen**

## 📋 Wartungsaufgaben im Detail

### Cache-Cleanup
- Abgelaufene Cache-Einträge löschen
- Alte Geocaching-Daten bereinigen
- Cache-Invalidations aufräumen
- Cache-Statistiken aktualisieren

### Datenbank-Optimierung
- Tabellen optimieren (OPTIMIZE TABLE)
- Index-Analyse und Empfehlungen
- Query-Performance überwachen
- Speicherplatz freigeben

### Log-Rotation
- Error-Logs archivieren
- Performance-Metriken bereinigen
- System-Metriken rotieren
- Alert-Logs aufräumen

### Sicherheitswartung
- Fehlgeschlagene Logins analysieren
- Verdächtige IP-Aktivitäten erkennen
- Kritische Sicherheitsfehler überwachen
- Security-Score berechnen

## 🔧 Troubleshooting

### Häufige Probleme

#### Wartung schlägt fehl
```bash
# Logs prüfen
tail -f storage/logs/maintenance-daily.log

# Manual ausführen für Details
php scripts/maintenance-scheduler.php daily
```

#### Health-Score niedrig
```bash
# Detaillierte Diagnose
php scripts/system-diagnostics.php

# Einzelne Checks prüfen
php scripts/health-monitor.php
```

#### Performance-Probleme
```bash
# Langsame Queries identifizieren
php scripts/maintenance-scheduler.php performance_analysis

# Datenbank optimieren
php scripts/maintenance-scheduler.php database_optimization
```

### Debug-Modus aktivieren
```php
// In MaintenanceAgent-Konfiguration
$config['log_level'] = 'debug';
$config['enable_verbose_logging'] = true;
```

## 📞 Support und Wartung

- **Entwickelt von**: 2Brands Media GmbH
- **Support-Level**: Business Hours
- **Kritische Probleme**: Automatische Alerts und Wiederherstellung
- **Reguläre Updates**: Monatlich
- **Security Patches**: Sofort

## 📖 Weitere Dokumentation

- [API-Dokumentation](API_DOCUMENTATION.md)
- [Deployment-Guide](DEPLOYMENT_GUIDE.md)
- [Security-Dokumentation](SECURITY_DOCUMENTATION.md)
- [Performance-Optimierung](PERFORMANCE_OPTIMIZATION_GUIDE.md)

---

**© 2024 2Brands Media GmbH** - Alle Rechte vorbehalten