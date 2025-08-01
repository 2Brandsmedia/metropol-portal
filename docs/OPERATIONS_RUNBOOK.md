# Operations Runbook - Metropol Portal
# Performance-Systeme Wartung und Betrieb

**Entwickelt von: 2Brands Media GmbH**  
**Version: 1.0.0**  
**Letzte Aktualisierung: 30. Juli 2025**

## Überblick

Dieses Runbook beschreibt den operativen Betrieb aller Performance-Optimierungssysteme des Metropol Portals. Es richtet sich an System-Administratoren und DevOps-Teams.

## System-Architektur

### Performance-Komponenten

```
┌─────────────────────────────────────────────────────────────┐
│                    METROPOL PORTAL                          │
├─────────────────────────────────────────────────────────────┤
│  PerformanceTestAgent  │  APILimitAgent  │  LoadTestAgent   │
│  - Lighthouse Tests    │  - API Tracking │  - K6 Load Tests │
│  - Core Web Vitals     │  - Limit Alerts │  - Browser Tests │
│  - Mobile Performance  │  - Fallbacks    │  - SLA Reports   │
├─────────────────────────────────────────────────────────────┤
│                    CacheAgent                               │
│  - Multi-Layer Cache   │  - TTL Optimization              │
│  - Predictive Warming  │  - Fuzzy Matching               │
├─────────────────────────────────────────────────────────────┤
│           Monitoring & Alerting Infrastructure              │
│  - Real-time Dashboard │  - E-Mail Alerts │  - SLA Tracking │
└─────────────────────────────────────────────────────────────┘
```

## Tägliche Wartungsaufgaben

### Automatische Tasks

Diese Tasks laufen automatisch und erfordern normalerweise keine manuelle Intervention:

#### Cache-Wartung (alle 15 Minuten)
```bash
# Cron: */15 * * * *
/usr/bin/php /path/to/scripts/cache-maintenance.php
```

**Was passiert:**
- Abgelaufene Cache-Einträge werden bereinigt
- Predictive Cache-Warming wird ausgeführt
- Performance-Statistiken werden aktualisiert
- Cache-Hit-Rates werden berechnet

**Monitoring:**
```bash
# Status prüfen
tail -f /var/log/cache-maintenance.log

# Aktuelle Cache-Performance
curl -s http://localhost/api/cache/stats | jq '.hit_rate'
```

#### API-Usage-Tracking (stündlich)
```bash
# Cron: 0 * * * *
/usr/bin/php /path/to/scripts/api-usage-tracker.php
```

**Was passiert:**
- API-Nutzungsstatistiken werden aggregiert
- Limit-Überschreitungen werden geprüft
- Alerts werden versendet falls nötig
- Kostenschätzungen werden aktualisiert

### Manuelle Tägliche Checks

#### 1. Performance-Dashboard Review (09:00 Uhr)

**Dashboard-URL:** `https://metropol-portal.de/admin/performance-dashboard`

**Zu prüfende Metriken:**
- [ ] **Overall Performance Score** > 95
- [ ] **Cache-Hit-Rate** > 80%
- [ ] **API-Kosten** im Budget (< €50/Tag)
- [ ] **Fehlerrate** < 5%
- [ ] **Response-Zeiten** alle unter SLA-Limits

```bash
# Quick Health Check via CLI
curl -s http://localhost/api/health/performance | jq '.'
```

#### 2. Alert-Review (09:15 Uhr)

**Zu prüfen:**
- [ ] Keine kritischen Alerts in den letzten 24h
- [ ] API-Limit-Warnungen Review
- [ ] Performance-Budget-Verletzungen
- [ ] Cache-System-Fehler

```bash
# Aktuelle Alerts abrufen
curl -s http://localhost/api/admin/alerts/active | jq '.alerts[]'
```

#### 3. Load-Test-Ergebnisse (09:30 Uhr)

Die nächtlichen automatischen Load-Tests prüfen:

```bash
# Letzte Load-Test-Ergebnisse
ls -la /path/to/tests/Performance/reports/ | head -5

# Quick Summary des letzten Tests
cat /path/to/tests/Performance/reports/latest-summary.json | jq '.summary'
```

**Acceptance Criteria:**
- [ ] Alle kritischen Tests bestanden
- [ ] Response-Zeiten unter SLA
- [ ] Fehlerrate < 5%
- [ ] Throughput > 40 RPS

## Wöchentliche Wartungsaufgaben

### Montag: Performance-Trend-Analyse

#### Cache-Performance Review
```bash
# Cache-Statistiken der letzten Woche
/usr/bin/php /path/to/scripts/weekly-cache-report.php

# Manuelle Analyse der Top-Missed-Caches
mysql -u user -p -e "
SELECT cache_key, cache_type, miss_count, hit_rate 
FROM enhanced_cache 
WHERE hit_rate < 50 
ORDER BY miss_count DESC 
LIMIT 20;"
```

**Aktionen bei niedrigen Hit-Rates:**
1. TTL-Strategien überprüfen
2. Fuzzy-Matching-Algorithmus anpassen
3. Predictive-Score-Kalibrierung

#### API-Kosten-Optimierung
```bash
# Kostenaanalyse der letzten Woche
curl -s http://localhost/api/admin/cost-analysis/weekly | jq '.'
```

**Zu prüfen:**
- [ ] Kosten-Trend (steigend/fallend)
- [ ] Top-kostenverursachende APIs
- [ ] Ineffiziente API-Nutzung identifizieren

### Mittwoch: Load-Test-Suite Vollprogramm

#### Umfassende Performance-Tests
```bash
cd /path/to/tests/Performance
./run-all-tests.ts --test-type full --environment production --parallel
```

**Dauer:** ~45 Minuten
**Erwartung:** 
- Alle Tests bestehen
- Performance-Scores > 90
- Keine Regressionen

#### Capacity-Planning-Update
```bash
# Aktuelle vs. maximale Kapazität
./run-load-tests.sh --scenario stressTest --max-users 150

# Scaling-Empfehlungen generieren
/usr/bin/php /path/to/scripts/capacity-planning.php
```

### Freitag: Sicherheits- und Compliance-Check

#### DSGVO-Compliance
```bash
# Alte API-Logs bereinigen (> 14 Tage)
mysql -u user -p -e "
DELETE FROM api_usage 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 14 DAY);"

# PII-Check in Cache-Systemen
/usr/bin/php /path/to/scripts/privacy-compliance-check.php
```

#### Security-Audit der Performance-Systeme
```bash
# API-Key-Rotation prüfen
/usr/bin/php /path/to/scripts/security-audit.php

# Rate-Limiting-Effectiveness
curl -s http://localhost/api/admin/security/rate-limiting-stats | jq '.'
```

## Monatliche Wartungsaufgaben

### Performance-Budget Review

#### SLA-Anpassung prüfen
1. **Business-Anforderungen** mit aktueller Performance abgleichen
2. **Performance-Budgets** bei Bedarf anpassen
3. **Alert-Schwellenwerte** kalibrieren

```bash
# Performance-Trend der letzten 30 Tage
./run-all-tests.ts --test-type baseline --environment production
```

#### Kosten-Optimierung
```bash
# Monatliche Kostenanalyse
curl -s http://localhost/api/admin/cost-analysis/monthly | jq '.'

# ROI-Berechnung der Cache-Optimierung
/usr/bin/php /path/to/scripts/cache-roi-analysis.php
```

### Infrastructure-Health-Check

#### Database-Optimierung
```sql
-- Performance-kritische Indizes optimieren
ANALYZE TABLE enhanced_cache;
OPTIMIZE TABLE api_usage;

-- Slow-Query-Log analysieren
SELECT * FROM mysql.slow_log WHERE start_time > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

#### Cache-System Wartung
```bash
# Cache-Fragmentierung prüfen
/usr/bin/php /path/to/scripts/cache-defragmentation.php

# Memory-Usage der Cache-Layers
free -h && ps aux | grep -E "(cache|php-fpm)" | awk '{sum+=$6} END {print "Cache Memory: " sum/1024 " MB"}'
```

## Troubleshooting

### Häufige Probleme und Lösungen

#### Problem: Cache-Hit-Rate plötzlich niedrig (<50%)

**Diagnose:**
```bash
# Cache-System-Status prüfen
curl -s http://localhost/api/cache/health | jq '.'

# Aktuelle Cache-Größe und -Einträge
mysql -u user -p -e "SELECT COUNT(*), SUM(data_size_bytes)/1024/1024 as size_mb FROM enhanced_cache;"
```

**Lösungsschritte:**
1. Cache-Warming-Queue prüfen: `SELECT * FROM cache_warming_queue WHERE status = 'failed';`
2. TTL-Strategien überprüfen
3. Predictive-Score-Algorithmus kalibrieren
4. Falls nötig, Cache-Warming manuell starten: `/usr/bin/php /path/to/scripts/manual-cache-warm.php`

#### Problem: API-Limits erreicht

**Sofortmaßnahmen:**
```bash
# Aktuelle API-Nutzung prüfen
curl -s http://localhost/api/admin/api-usage/current | jq '.'

# Fallback-Status prüfen
curl -s http://localhost/api/admin/fallback-status | jq '.'
```

**Lösungsschritte:**
1. Cache-Hit-Rate für betroffene API erhöhen
2. Fallback-Strategien aktivieren
3. Bei Notfall: API-Limits temporär zurücksetzen (nur Admin)
4. Langfristig: Cache-Strategien optimieren

#### Problem: Performance-Tests schlagen fehl

**Diagnose:**
```bash
# Letze Test-Logs prüfen
tail -50 /path/to/tests/Performance/reports/latest-test.log

# System-Ressourcen während Test
top -b -n1 | head -20
```

**Lösungsschritte:**
1. Server-Ressourcen prüfen (CPU, Memory, Disk I/O)
2. Aktive Benutzer-Sessions prüfen
3. Database-Performance analysieren
4. Bei wiederholten Fehlern: Alert-Schwellenwerte anpassen

### Performance-Notfall-Prozeduren

#### Kritischer Performance-Einbruch

**Sofort-Checkliste:**
1. [ ] **System-Ressourcen** prüfen (`top`, `free -h`, `df -h`)
2. [ ] **Database-Status** prüfen (`SHOW PROCESSLIST;`)
3. [ ] **Cache-System** restart (`systemctl restart cache-service`)
4. [ ] **PHP-FPM** restart (`systemctl restart php8.3-fpm`)
5. [ ] **Load-Balancer** Status prüfen

**Eskalation:**
- Bei > 5 Min Downtime: Stakeholder informieren
- Bei > 15 Min Downtime: Rollback in Erwägung ziehen

#### API-Kosten-Explosion

**Sofort-Maßnahmen:**
1. **Cache-Warming** pausieren: `echo "pause" > /tmp/cache-warming.lock`
2. **API-Rate-Limits** verschärfen
3. **Fallback-Modi** aktivieren
4. **Admin-Team** benachrichtigen

## Monitoring und Alerting

### Alert-Konfiguration

#### Kritische Alerts (SMS + E-Mail)
- Performance Score < 90
- API-Kosten > Tagesbudget
- Fehlerrate > 10%
- System-Downtime > 2 Minuten

#### Wichtige Alerts (E-Mail)
- Cache-Hit-Rate < 70%
- Response-Zeit > SLA + 50%
- API-Limits bei 90%

#### Informative Alerts (Dashboard)
- Cache-Hit-Rate < 80%
- Performance-Budget-Verletzungen
- Ungewöhnliche Traffic-Patterns

### Dashboard-URLs

**Haupt-Dashboard:**
`https://metropol-portal.de/admin/performance-dashboard`

**API-Monitoring:**
`https://metropol-portal.de/admin/api-dashboard`

**Cache-Performance:**
`https://metropol-portal.de/admin/cache-monitoring`

**Load-Test-Ergebnisse:**
`https://metropol-portal.de/admin/load-test-results`

## Kontakte und Eskalation

### Support-Kontakte

**Level 1 - System-Administration:**
- E-Mail: sysadmin@2brands-media.de
- Verfügbarkeit: Mo-Fr 08:00-18:00

**Level 2 - Performance-Engineering:**
- E-Mail: performance@2brands-media.de
- Verfügbarkeit: Mo-Fr 09:00-17:00

**Level 3 - Entwicklungsteam:**
- E-Mail: dev@2brands-media.de
- Notfall-Hotline: [Interne Nummer]

### Eskalations-Matrix

| Problem-Schwere | Zeit bis Eskalation | Kontakt |
|-----------------|-------------------|---------|
| **Kritisch** | Sofort | Level 3 + Geschäftsführung |
| **Hoch** | 30 Minuten | Level 2 |
| **Mittel** | 2 Stunden | Level 1 |
| **Niedrig** | Nächster Werktag | Level 1 |

## Backup und Recovery

### Performance-System Backup

**Täglich:**
- Performance-Konfigurationen
- Cache-Warming-Strategien
- Alert-Konfigurationen

**Wöchentlich:**
- Vollständige Performance-Datenbank
- Test-Konfigurationen
- Custom-Scripts

### Recovery-Verfahren

**Cache-System Recovery:**
```bash
# Cache-Datenbank wiederherstellen
mysql -u user -p metropol_portal < backup/enhanced_cache_backup.sql

# Cache-Warming neu starten
/usr/bin/php /path/to/scripts/emergency-cache-warm.php
```

**Performance-Test-Suite Recovery:**
```bash
# Test-Konfigurationen wiederherstellen
cp -r backup/test-configs/* /path/to/tests/Performance/

# Test-Integrität prüfen
./run-all-tests.ts --test-type smoke --dry-run
```

---

**Letzte Aktualisierung:** 30.07.2025  
**Nächste Review:** 30.08.2025  
**Version:** 1.0.0

**© 2025 2Brands Media GmbH. Vertrauliches Dokument.**

*Dieses Runbook ist ein lebendiges Dokument und wird regelmäßig basierend auf operativen Erfahrungen aktualisiert.*