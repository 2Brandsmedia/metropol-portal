# Troubleshooting Guide - Performance-Systeme
# Metropol Portal - Problemlösung und Fehlerdiagnose

**Entwickelt von: 2Brands Media GmbH**  
**Version: 1.0.0**  
**Letzte Aktualisierung: 30. Juli 2025**

## Inhaltsverzeichnis

1. [Diagnosewerkzeuge](#diagnosewerkzeuge)
2. [Performance-Probleme](#performance-probleme)
3. [Cache-System-Probleme](#cache-system-probleme)
4. [API-Limit-Probleme](#api-limit-probleme)
5. [Load-Test-Probleme](#load-test-probleme)
6. [Monitoring-Probleme](#monitoring-probleme)
7. [Notfall-Recovery](#notfall-recovery)

## Diagnosewerkzeuge

### Quick Health Check

```bash
#!/bin/bash
# system-health-check.sh

echo "=== METROPOL PORTAL HEALTH CHECK ==="
echo "Timestamp: $(date)"
echo

# 1. System-Ressourcen
echo "1. SYSTEM RESOURCES:"
echo "CPU: $(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1"%"}')"
echo "Memory: $(free -h | awk '/^Mem:/ {print $3"/"$2}')"
echo "Disk: $(df -h / | awk 'NR==2 {print $3"/"$2 " ("$5")"}')"
echo

# 2. Performance-Services
echo "2. PERFORMANCE SERVICES:"
curl -s http://localhost/api/health/performance | jq -r '.status' > /dev/null && echo "✅ Performance API: OK" || echo "❌ Performance API: ERROR"
curl -s http://localhost/api/cache/health | jq -r '.status' > /dev/null && echo "✅ Cache System: OK" || echo "❌ Cache System: ERROR"
curl -s http://localhost/api/admin/api-usage/status | jq -r '.status' > /dev/null && echo "✅ API Monitoring: OK" || echo "❌ API Monitoring: ERROR"
echo

# 3. Database-Verbindung
echo "3. DATABASE:"
mysql -u root -p -e "SELECT 1 as status;" 2>/dev/null && echo "✅ MySQL: Connected" || echo "❌ MySQL: Connection Error"
echo

# 4. Cache-Performance
echo "4. CACHE PERFORMANCE:"
CACHE_STATS=$(curl -s http://localhost/api/cache/stats)
echo "Hit-Rate: $(echo $CACHE_STATS | jq -r '.hit_rate_percent')%"
echo "Memory Cache: $(echo $CACHE_STATS | jq -r '.memory_cache_entries') entries"
echo

# 5. API-Status
echo "5. API STATUS:"
API_STATUS=$(curl -s http://localhost/api/admin/api-usage/current)
echo "Google Maps: $(echo $API_STATUS | jq -r '.google_maps.daily_percentage')% used"
echo "Nominatim: $(echo $API_STATUS | jq -r '.nominatim.daily_percentage')% used"
echo

echo "=== HEALTH CHECK COMPLETE ==="
```

### Performance-Diagnose-Tool

```bash
#!/bin/bash
# performance-diagnose.sh

echo "=== PERFORMANCE DIAGNOSIS ==="

# Lighthouse-Score prüfen
echo "1. LIGHTHOUSE PERFORMANCE:"
cd /path/to/tests/Performance
node lighthouse.runner.js --url=http://localhost --scenario=smoke

# Load-Test schnell durchführen
echo "2. QUICK LOAD TEST:"
k6 run --duration=30s --vus=5 k6-smoke-config.js

# Cache-Hit-Rates prüfen
echo "3. CACHE ANALYSIS:"
mysql -u root -p -e "
SELECT 
    cache_type,
    COUNT(*) as total_entries,
    AVG(hit_rate) as avg_hit_rate,
    SUM(hit_count) as total_hits,
    SUM(miss_count) as total_misses
FROM enhanced_cache 
GROUP BY cache_type;"

# Slow-Query-Check
echo "4. SLOW QUERIES:"
mysql -u root -p -e "
SELECT 
    query_time,
    lock_time,
    rows_sent,
    rows_examined,
    LEFT(sql_text, 100) as query
FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY query_time DESC 
LIMIT 10;"
```

## Performance-Probleme

### Problem: Langsame Seitenladezeiten (>2 Sekunden)

#### Symptome
- Lighthouse Score < 90
- First Contentful Paint > 2s
- Users beschweren sich über Langsamkeit

#### Diagnose
```bash
# 1. Lighthouse-Analyse durchführen
cd /path/to/tests/Performance
node lighthouse.runner.js --url=http://localhost/dashboard

# 2. Server-Response-Zeit messen
curl -w "@curl-format.txt" -o /dev/null -s http://localhost/dashboard

# 3. Database-Performance prüfen
mysql -u root -p -e "SHOW PROCESSLIST;" | grep -v Sleep

# 4. PHP-FPM-Status prüfen
curl -s http://localhost/fpm-status | grep -E "(active|total)"
```

#### Lösungsschritte

**1. Cache-Optimierung**
```bash
# Cache-Hit-Rate für kritische Bereiche prüfen
curl -s http://localhost/api/cache/stats | jq '.by_type'

# Bei niedriger Hit-Rate (<70%): Cache-Warming intensivieren
/usr/bin/php /path/to/scripts/manual-cache-warm.php --priority=high
```

**2. Database-Optimierung**
```sql
-- Slow-Queries identifizieren
SET SESSION long_query_time = 1;
SELECT * FROM mysql.slow_log WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Indizes prüfen
EXPLAIN SELECT * FROM playlists WHERE user_id = 1 AND date = CURDATE();
```

**3. PHP-FPM-Tuning**
```ini
; /etc/php/8.3/fpm/pool.d/www.conf
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

**4. Asset-Optimierung**
```bash
# CSS/JS-Komprimierung prüfen
gzip -t /path/to/public/assets/css/app.css
gzip -t /path/to/public/assets/js/app.js

# Image-Optimierung
find /path/to/public/assets/images -name "*.jpg" -exec jpegoptim {} \;
```

### Problem: Mobile Performance schlecht (<80 Score)

#### Diagnose
```bash
# Mobile-spezifische Lighthouse-Tests
cd /path/to/tests/Performance
node mobile-performance.js --device=mobile --network=slow-3g
```

#### Lösungsschritte

**1. Mobile-First Caching**
```php
// Mobile-spezifische Cache-Strategien
if ($this->isMobileDevice()) {
    $ttl = $ttl * 1.5; // Längere TTL für Mobile
    $this->compressData($data); // Daten komprimieren
}
```

**2. Critical CSS inlinen**
```html
<style>
/* Critical Above-the-fold CSS inline */
.header, .nav, .main-content { /* Kritische Styles */ }
</style>
```

**3. JavaScript-Optimierung**
```javascript
// Lazy Loading für nicht-kritische Features
if ('IntersectionObserver' in window) {
    // Implementierung für moderne Browser
} else {
    // Fallback für ältere Browser
}
```

## Cache-System-Probleme

### Problem: Niedrige Cache-Hit-Rate (<70%)

#### Diagnose
```bash
# Detaillierte Cache-Analyse
curl -s http://localhost/api/cache/detailed-stats | jq '.'

# Cache-Keys mit niedrigen Hit-Rates
mysql -u root -p -e "
SELECT cache_key, cache_type, hit_rate, hit_count, miss_count 
FROM enhanced_cache 
WHERE hit_rate < 0.7 
ORDER BY miss_count DESC 
LIMIT 20;"

# Fuzzy-Matching-Effectiveness
mysql -u root -p -e "
SELECT COUNT(*) as fuzzy_matches 
FROM cache_fuzzy_matches 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);"
```

#### Lösungsschritte

**1. TTL-Strategien optimieren**
```php
// TTL-Anpassung basierend auf Datenanalyse
private function optimizeTTL(string $type, array $data): int
{
    $usage = $this->getHistoricalUsage($type);
    
    if ($usage['frequent_access']) {
        return $this->baseTTL * 2; // Doppelte TTL für häufig genutzte Daten
    }
    
    return $this->baseTTL;
}
```

**2. Predictive Caching verbessern**
```bash
# Prediction-Score-Analyse
mysql -u root -p -e "
SELECT cache_type, AVG(prediction_score), COUNT(*) 
FROM enhanced_cache 
WHERE hit_count > 0 
GROUP BY cache_type;"

# Low-Score-Items identifizieren
mysql -u root -p -e "
SELECT cache_key, cache_type, prediction_score, hit_count 
FROM enhanced_cache 
WHERE prediction_score < 0.5 AND hit_count > 5 
ORDER BY hit_count DESC;"
```

**3. Cache-Warming intensivieren**
```bash
# Warming-Queue-Status prüfen
mysql -u root -p -e "
SELECT status, COUNT(*), AVG(attempts) 
FROM cache_warming_queue 
GROUP BY status;"

# Fehlgeschlagene Warming-Jobs analysieren
mysql -u root -p -e "
SELECT cache_type, error_message, COUNT(*) 
FROM cache_warming_queue 
WHERE status = 'failed' 
GROUP BY cache_type, error_message;"
```

### Problem: Cache-System-Ausfall

#### Sofort-Maßnahmen
```bash
# 1. Cache-System-Restart
systemctl restart cache-service

# 2. Database-Cache-Table prüfen
mysql -u root -p -e "CHECK TABLE enhanced_cache;"

# 3. Corruption-Check
mysql -u root -p -e "REPAIR TABLE enhanced_cache;"

# 4. Fallback auf Memory-Only-Cache
echo "memory_only" > /tmp/cache-fallback-mode
```

#### Recovery-Schritte
```bash
# 1. Cache-Database wiederherstellen
mysql -u root -p metropol_portal < backup/enhanced_cache_backup.sql

# 2. Cache-Warming neu initialisieren
/usr/bin/php /path/to/scripts/initialize-cache.php

# 3. Warming-Queue aufbauen
/usr/bin/php /path/to/scripts/build-warming-queue.php --priority=critical

# 4. Fallback-Mode deaktivieren
rm /tmp/cache-fallback-mode
```

## API-Limit-Probleme

### Problem: API-Limits erreicht (95%+)

#### Sofort-Diagnose
```bash
# Aktuelle API-Nutzung prüfen
curl -s http://localhost/api/admin/api-usage/current | jq '.'

# Stündliche Nutzung der letzten 24h
mysql -u root -p -e "
SELECT 
    api_provider,
    DATE_FORMAT(period_key, '%H:00') as hour,
    request_count,
    error_count
FROM api_usage 
WHERE period_type = 'hourly' 
  AND period_key >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY period_key DESC;"
```

#### Sofort-Maßnahmen
```bash
# 1. Cache-Hit-Rate maximieren
/usr/bin/php /path/to/scripts/emergency-cache-warm.php

# 2. Fallback-Modi aktivieren
curl -X POST http://localhost/api/admin/enable-fallback \
  -H "Content-Type: application/json" \
  -d '{"provider": "google_maps", "mode": "alternative_api"}'

# 3. Rate-Limiting verschärfen
mysql -u root -p -e "
UPDATE api_rate_limits 
SET requests_per_second = requests_per_second * 0.5 
WHERE api_provider = 'google_maps';"
```

#### Langfristige Optimierung
```php
// Cache-First-Strategie implementieren
public function makeApiRequest(string $endpoint, array $params): array
{
    // 1. Immer zuerst Cache prüfen
    $cached = $this->cache->get($this->buildCacheKey($params));
    if ($cached !== null) {
        return $cached;
    }
    
    // 2. API-Limit prüfen
    $limitCheck = $this->apiLimitAgent->checkApiRequest('google_maps');
    if (!$limitCheck['allowed']) {
        return $this->executeFallback($limitCheck['fallback_mode'], $params);
    }
    
    // 3. API-Request nur wenn wirklich nötig
    return $this->performApiRequest($endpoint, $params);
}
```

### Problem: Fallback-Qualität zu niedrig

#### Diagnose
```bash
# Fallback-Usage-Statistiken
mysql -u root -p -e "
SELECT 
    fallback_mode,
    COUNT(*) as usage_count,
    AVG(quality_score) as avg_quality,
    AVG(response_time_ms) as avg_response_time
FROM api_fallback_usage 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY fallback_mode;"

# User-Feedback zu Fallback-Quality
mysql -u root -p -e "
SELECT fallback_mode, AVG(user_rating), COUNT(*) 
FROM user_feedback 
WHERE feedback_type = 'fallback_quality' 
GROUP BY fallback_mode;"
```

#### Verbesserungsmaßnahmen
```php
// Fallback-Qualität verbessern
private function improveFallbackQuality(string $mode, array $data): array
{
    switch ($mode) {
        case 'cache_only':
            // Fuzzy-Matching-Threshold senken für mehr Matches
            return $this->findSimilarCachedData($key, $type, ['similarity_threshold' => 0.75]);
            
        case 'alternative_api':
            // Multiple alternative APIs versuchen
            $alternatives = ['openrouteservice', 'mapquest', 'here'];
            foreach ($alternatives as $provider) {
                $result = $this->tryAlternativeProvider($provider, $data);
                if ($result['quality_score'] > 0.8) {
                    return $result;
                }
            }
            break;
    }
}
```

## Load-Test-Probleme

### Problem: Load-Tests schlagen fehl

#### Diagnose
```bash
# Test-Logs analysieren
tail -100 /path/to/tests/Performance/reports/latest-test.log | grep -i error

# K6-Metriken prüfen
cat /path/to/tests/Performance/reports/k6-results.json | jq '.metrics'

# System-Ressourcen während Test
echo "CPU Usage during test:"
sar -u 1 10

echo "Memory Usage during test:"
sar -r 1 10
```

#### Häufige Ursachen und Lösungen

**1. Insufficient System Resources**
```bash
# Lösung: Test-Parameter anpassen
# In k6-config.js:
export let options = {
  stages: [
    { duration: '2m', target: 10 },  // Reduzierte User
    { duration: '5m', target: 10 },
    { duration: '2m', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // Weniger strenge Limits
  }
};
```

**2. Database-Locks während Test**
```sql
-- Lock-Situation analysieren
SHOW ENGINE INNODB STATUS\G

-- Lange laufende Transaktionen
SELECT * FROM information_schema.innodb_trx WHERE trx_started < DATE_SUB(NOW(), INTERVAL 10 SECOND);

-- Lösung: Connection-Pool optimieren
SET GLOBAL max_connections = 200;
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB
```

**3. Test-Environment-Instabilität**
```bash
# Test-Environment isolieren
docker run -d --name metropol-test \
  --network isolated-test-net \
  -p 8080:80 \
  metropol-portal:test

# Load-Tests gegen isolierte Umgebung
export BASE_URL="http://localhost:8080"
./run-load-tests.sh --scenario smoke
```

### Problem: Performance-Regression erkannt

#### Regression-Analyse
```bash
# Baseline vs. aktuelle Performance
cd /path/to/tests/Performance
node baseline-metrics.js --compare-with-last

# Git-Commit-Range für Regression identifizieren
git log --oneline --since="1 week ago" | head -10

# Performance-Impact pro Commit
for commit in $(git log --oneline --since="1 week ago" | cut -d' ' -f1); do
    echo "Testing commit: $commit"
    git checkout $commit
    ./run-load-tests.sh --scenario smoke --output="/tmp/perf-$commit.json"
done
```

#### Regression-Fix
```bash
# 1. Problem-Commit identifizieren
git bisect start
git bisect bad HEAD
git bisect good <last-good-commit>

# 2. Code-Review der problematischen Änderungen
git show <problematic-commit>

# 3. Hot-Fix oder Rollback
git revert <problematic-commit>
# oder
git reset --hard <last-good-commit>
```

## Monitoring-Probleme

### Problem: Alerts werden nicht versendet

#### Diagnose
```bash
# Alert-System-Status prüfen
systemctl status alert-manager

# E-Mail-Konfiguration testen
echo "Test-Alert" | mail -s "Alert Test" admin@metropol-portal.de

# Alert-Rules validieren
curl -s http://localhost/api/admin/alert-rules/validate | jq '.'

# Letzte Alert-Versuche prüfen
mysql -u root -p -e "
SELECT * FROM alert_log 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
ORDER BY created_at DESC 
LIMIT 20;"
```

#### Lösungsschritte
```bash
# 1. SMTP-Konfiguration prüfen
tail -50 /var/log/mail.log | grep -i error

# 2. Alert-Service neu starten
systemctl restart alert-manager
systemctl restart postfix

# 3. Test-Alert senden
curl -X POST http://localhost/api/admin/test-alert \
  -H "Content-Type: application/json" \
  -d '{"type": "test", "message": "Alert system test"}'
```

### Problem: Dashboard zeigt veraltete Daten

#### Diagnose
```bash
# Datenaktualität prüfen
mysql -u root -p -e "
SELECT 
    'performance_metrics' as table_name,
    MAX(updated_at) as last_update,
    COUNT(*) as record_count
FROM performance_metrics
UNION
SELECT 
    'cache_stats',
    MAX(updated_at),
    COUNT(*)
FROM cache_stats;"

# Cron-Jobs-Status prüfen
crontab -l | grep -E "(cache|performance|stats)"
systemctl status cron
```

#### Lösungsschritte
```bash
# 1. Datensammlung manuell ausführen
/usr/bin/php /path/to/scripts/collect-performance-metrics.php

# 2. Dashboard-Cache leeren
redis-cli FLUSHDB  # Falls Redis verwendet
# oder
rm -rf /tmp/dashboard-cache/*

# 3. Cron-Jobs neu starten
systemctl restart cron

# 4. Database-Trigger prüfen
mysql -u root -p -e "SHOW TRIGGERS FROM metropol_portal;"
```

## Notfall-Recovery

### Kompletter System-Ausfall

#### Emergency-Checklist
```bash
# 1. System-Status prüfen
systemctl status nginx php8.3-fpm mysql

# 2. Logs prüfen
tail -50 /var/log/nginx/error.log
tail -50 /var/log/php8.3-fpm.log
tail -50 /var/log/mysql/error.log

# 3. Services neu starten
systemctl restart nginx php8.3-fpm mysql

# 4. Database-Integrität prüfen
mysql -u root -p -e "CHECK TABLE playlists, stops, enhanced_cache;"

# 5. Performance-Services reaktivieren
systemctl restart cache-service
systemctl restart alert-manager
```

#### Rollback-Prozedur
```bash
# 1. Aktuelle Version sichern
cp -r /var/www/metropol-portal /var/www/metropol-portal.backup

# 2. Letzte stabile Version wiederherstellen
git checkout <last-stable-tag>

# 3. Database-Rollback falls nötig
mysql -u root -p metropol_portal < backup/database-before-deployment.sql

# 4. Cache-System zurücksetzen
mysql -u root -p -e "TRUNCATE TABLE enhanced_cache;"
/usr/bin/php /path/to/scripts/initialize-cache.php

# 5. System-Test durchführen
curl -f http://localhost/api/health
./run-load-tests.sh --scenario smoke
```

### Performance-Notfall (>10s Response-Zeit)

#### Sofort-Maßnahmen
```bash
# 1. System-Load prüfen
uptime
top -b -n1 | head -20

# 2. Database-Connections limitieren
mysql -u root -p -e "SET GLOBAL max_connections = 50;"

# 3. Cache-System in Emergency-Mode
echo "emergency" > /tmp/cache-mode
systemctl restart cache-service

# 4. Rate-Limiting aktivieren
nginx -s reload  # Mit emergency rate-limiting config

# 5. Non-essential Services stoppen
systemctl stop backup-service
systemctl stop log-analyzer
```

#### Performance-Recovery
```bash
# 1. Problematische Queries identifizieren
mysql -u root -p -e "
SELECT * FROM information_schema.processlist 
WHERE time > 10 AND command = 'Query';"

# 2. Lange Transaktionen beenden
mysql -u root -p -e "KILL <process_id>;"

# 3. Cache aggressiv vorwärmen
/usr/bin/php /path/to/scripts/emergency-cache-warm.php --all

# 4. Services schrittweise reaktivieren
systemctl start backup-service
rm /tmp/cache-mode
nginx -s reload  # Normale Konfiguration
```

## Kontakte für kritische Situationen

### Eskalations-Kontakte

**Level 1 - System-Admin (24/7):**
- E-Mail: sysadmin@2brands-media.de
- Handy: [Interne Nummer]

**Level 2 - Performance-Team:**
- E-Mail: performance@2brands-media.de
- Slack: #performance-alerts

**Level 3 - Entwicklung:**
- E-Mail: dev@2brands-media.de
- Notfall-Hotline: [Interne Nummer]

### Externe Dienstleister

**Hosting-Provider (All-Inkl):**
- Support: support@all-inkl.com
- Hotline: +49 (0) 911 93170-0

**Google Maps API Support:**
- Console: https://console.cloud.google.com/support

### Backup-Kontakte

**Geschäftsführung:**
- Bei kritischen Business-Impact (>2h Downtime)

**Kunden-Support:**
- Bei längeren Ausfällen (>30min)

---

**© 2025 2Brands Media GmbH. Vertrauliches Dokument.**

*Dieses Troubleshooting-Guide ist ein lebendiges Dokument und wird basierend auf realen Problemfällen kontinuierlich erweitert.*