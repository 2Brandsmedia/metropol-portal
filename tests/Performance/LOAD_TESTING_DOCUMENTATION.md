# 🚀 Load Testing Documentation - Metropol Portal

**Entwickelt von:** 2Brands Media GmbH  
**Version:** 1.0.0  
**Letzte Aktualisierung:** $(date +"%Y-%m-%d")

## Übersicht

Dieses Dokument beschreibt die umfassende Load-Testing-Infrastruktur für das Metropol Portal. Die Tests simulieren realistische Benutzerszenarien und validieren Performance-Ziele (SLAs) aus der Projektspezifikation.

## 📋 Inhalt

1. [Test-Architektur](#test-architektur)
2. [Realistische Szenarien](#realistische-szenarien)
3. [Performance-Ziele (SLAs)](#performance-ziele-slas)
4. [Test-Ausführung](#test-ausführung)
5. [CI/CD Integration](#cicd-integration)
6. [Ergebnis-Interpretation](#ergebnis-interpretation)
7. [Scaling-Empfehlungen](#scaling-empfehlungen)
8. [Troubleshooting](#troubleshooting)

## Test-Architektur

### 🛠️ Technologie-Stack

- **K6**: HTTP-basierte Load-Tests mit JavaScript
- **Playwright**: Browser-basierte End-to-End Tests
- **Node.js/TypeScript**: Test-Orchestrierung und Berichterstattung
- **GitHub Actions**: CI/CD Pipeline Integration

### 📁 Datei-Struktur

```
tests/Performance/
├── k6-realtime-scenarios.js      # K6 Haupttest-Szenarien
├── k6-tests.js                   # Erweiterte K6 Tests
├── playwright-load-scenarios.ts  # Browser-basierte Load-Tests
├── load-test-runner.ts           # Test-Orchestrierung
├── load-testing.config.ts        # Konfigurationen
├── run-load-tests.sh            # Bash-Ausführungsskript
├── ci-pipeline-integration.yml   # GitHub Actions Pipeline
├── baseline-metrics.ts          # Baseline-Metriken
├── monitoring-dashboard.html    # Performance-Monitoring
└── LOAD_TESTING_DOCUMENTATION.md # Diese Dokumentation
```

## Realistische Szenarien

### 🌅 Morning Rush (7-9 AM)
- **Benutzer**: 50 gleichzeitig
- **Dauer**: 5 Minuten
- **Verhalten**: 
  - 75% Außendienstmitarbeiter loggen sich ein
  - Laden ihre Tagesrouten
  - Berechnen optimierte Routen
  - Starten ihre Touren

```bash
./run-load-tests.sh --scenario morningRush --max-users 50
```

### 🍽️ Lunch Update (12-1 PM)
- **Benutzer**: 30 gleichzeitig
- **Dauer**: 3 Minuten
- **Verhalten**:
  - 85% Außendienstmitarbeiter aktualisieren Status
  - Traffic-Updates für aktive Routen
  - Kurze Stopp-Updates

```bash
./run-load-tests.sh --scenario lunchUpdate --max-users 30
```

### 🌆 Evening Close (5-6 PM)
- **Benutzer**: 25 gleichzeitig
- **Dauer**: 4 Minuten
- **Verhalten**:
  - 60% Außendienstmitarbeiter schließen Touren ab
  - 25% Admins generieren Berichte
  - 15% Supervisors überprüfen Fortschritt

```bash
./run-load-tests.sh --scenario eveningClose --max-users 25
```

### 💪 Kapazitäts-Tests

#### Normal Load
- **Benutzer**: 25 gleichzeitig für 5 Minuten
- **Zweck**: Normale Betriebszeit simulieren

#### Peak Load
- **Benutzer**: 100 gleichzeitig für 2 Minuten
- **Zweck**: Spitzenlast-Szenarien testen

#### Stress Test
- **Benutzer**: 200+ gleichzeitig für 3 Minuten
- **Zweck**: Breaking Point ermitteln

```bash
# Alle Kapazitäts-Tests ausführen
./run-load-tests.sh --scenario all
```

## Performance-Ziele (SLAs)

### 🎯 Primäre SLA-Ziele (aus CLAUDE.md)

| Metrik | Ziel | Kritikalität |
|--------|------|--------------|
| **Login-Zeit** | ≤ 100ms | Kritisch |
| **Route-Berechnung** | ≤ 300ms | Kritisch |
| **Stopp-Update** | ≤ 100ms | Hoch |
| **API-Response** | ≤ 200ms | Hoch |
| **Fehlerrate** | ≤ 5% | Kritisch |
| **Erfolgsrate** | ≥ 95% | Kritisch |

### 📊 Zusätzliche Performance-Metriken

- **Dashboard-Ladezeit**: ≤ 200ms
- **Playlist-Laden**: ≤ 150ms
- **Traffic-Updates**: ≤ 200ms
- **Geocoding**: ≤ 200ms
- **Durchsatz**: ≥ 50 req/s

## Test-Ausführung

### 🚀 Schnellstart

```bash
# Health-Check
./run-load-tests.sh --health-check

# Smoke Test (minimale Last)
./run-load-tests.sh --scenario smoke

# Vollständige Test-Suite
./run-load-tests.sh --scenario all --max-users 100

# Spezifisches Szenario
./run-load-tests.sh --scenario morningRush --url https://staging.example.com
```

### 🔧 Erweiterte Optionen

```bash
# Alle verfügbaren Szenarien anzeigen
./run-load-tests.sh --list-scenarios

# Custom Output-Verzeichnis
./run-load-tests.sh --scenario peakLoad --output ./custom-results

# Parallel Jobs begrenzen
./run-load-tests.sh --scenario all --jobs 1
```

### 📱 Browser-Simulation

```bash
# Playwright-basierte Tests
./run-load-tests.sh --scenario browser --max-users 25
```

Simuliert verschiedene:
- **Browser**: Chrome (70%), Firefox (20%), Safari (10%)
- **Geräte**: Desktop (35%), Mobile (55%), Tablet (10%)
- **Realistische Benutzerinteraktionen**

## CI/CD Integration

### 🔄 GitHub Actions Pipeline

Die Pipeline (`ci-pipeline-integration.yml`) führt automatisch Load-Tests in verschiedenen Szenarien aus:

#### Trigger
- **Täglich**: 06:00 UTC (kritische Tests)
- **Wöchentlich**: Sonntags 02:00 UTC (vollständige Suite)
- **Bei Releases**: Kritische Tests
- **Pull Requests**: Smoke Tests

#### Environments
- **Staging**: Vollständige Test-Suite
- **Production**: Nur kritische Tests mit reduzierten Benutzerzahlen

```yaml
# Manueller Pipeline-Trigger
workflow_dispatch:
  inputs:
    environment: staging|production
    test_suite: all|critical|daily_scenarios
    max_users: "50"
```

### 📊 Pipeline-Ergebnisse

Die Pipeline generiert:
- **JSON-Reports**: Maschinell lesbare Metriken
- **HTML-Reports**: Visuelle Darstellung
- **Markdown-Reports**: Für GitHub-Integration
- **SLA-Compliance-Reports**: Validierung der Performance-Ziele

## Ergebnis-Interpretation

### ✅ Erfolgreiche Tests

```
📊 login_time:
   Min: 45.23ms
   Avg: 67.45ms
   P95: 89.12ms    ✅ SLA erfüllt: <= 100ms
   P99: 95.67ms
   Max: 102.34ms
   Count: 1248
```

### ❌ SLA-Verletzungen

```
📊 route_calculation_time:
   Min: 156.78ms
   Avg: 289.45ms
   P95: 456.23ms   ❌ SLA-Verletzung: > 300ms
   P99: 523.45ms
   Max: 678.90ms
   Count: 523
```

### 🚨 Empfohlene Maßnahmen bei SLA-Verletzungen

1. **Login-Performance**:
   - Session-Management optimieren
   - Datenbank-Indizes prüfen
   - Authentication-Cache implementieren

2. **Route-Berechnung**:
   - Routing-Algorithmus optimieren
   - Route-Caching einführen
   - Parallele Verarbeitung

3. **Stopp-Updates**:
   - Batch-Updates implementieren
   - Datenbankverbindungs-Pool optimieren
   - Asynchrone Verarbeitung

## Scaling-Empfehlungen

### 🏗️ Horizontale Skalierung

#### Bis 50 gleichzeitige Benutzer
- **Setup**: Single-Server mit optimierter Konfiguration
- **Empfehlung**: 
  - 4 CPU Cores
  - 8GB RAM
  - SSD Storage
  - PHP-FPM: 20-30 Worker

#### 50-100 gleichzeitige Benutzer
- **Setup**: Load-Balancer mit 2 Web-Servern
- **Empfehlung**:
  - Load Balancer (nginx/HAProxy)
  - 2x Web-Server (je 4 Cores, 8GB RAM)
  - Shared Database Server
  - Redis für Session-Storage

#### 100+ gleichzeitige Benutzer
- **Setup**: Multi-Tier-Architektur
- **Empfehlung**:
  - Load Balancer
  - 3+ Web-Server
  - Database Master-Slave Setup
  - Separate API-Server
  - CDN für statische Assets

### 🚀 Performance-Optimierungen

#### Caching-Strategien
```php
// Route-Cache (30min TTL)
Redis::setex("route_{$playlistId}", 1800, $routeData);

// Geocoding-Cache (24h TTL)
Redis::setex("geocode_{$address}", 86400, $coordinates);

// Session-Cache
Redis::setex("session_{$sessionId}", 3600, $sessionData);
```

#### Datenbank-Optimierungen
```sql
-- Index für häufige Queries
CREATE INDEX idx_playlists_user_date ON playlists(user_id, date);
CREATE INDEX idx_stops_playlist_status ON stops(playlist_id, status);

-- Query-Optimierung
EXPLAIN SELECT * FROM playlists WHERE user_id = ? AND date = ?;
```

#### PHP-FPM Optimierung
```ini
; php-fpm.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

## Troubleshooting

### 🔍 Häufige Probleme

#### 1. Server nicht erreichbar
```bash
# Health-Check
curl -f https://your-domain.com/api/health

# DNS-Auflösung prüfen
nslookup your-domain.com

# Firewall-Regeln prüfen
telnet your-domain.com 443
```

#### 2. K6 Installation
```bash
# Ubuntu/Debian
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 379CE192D401AB61
echo "deb https://dl.bintray.com/loadimpact/deb stable main" | sudo tee -a /etc/apt/sources.list
sudo apt-get update
sudo apt-get install k6

# macOS
brew install k6

# Windows
choco install k6
```

#### 3. Node.js Dependencies
```bash
# NPM-Pakete installieren
npm install

# TypeScript-Kompilierung prüfen
npx tsc --noEmit

# Playwright Browser installieren
npx playwright install
```

### 📊 Monitoring während Tests

#### System-Metriken überwachen
```bash
# CPU-Auslastung
top -p $(pgrep -f php-fpm)

# Memory-Verbrauch
free -h

# Datenbankverbindungen
mysql -e "SHOW PROCESSLIST;"

# Nginx/Apache-Status
curl localhost/nginx_status
```

#### Application-Metriken
```bash
# PHP-FPM Status
curl localhost/fpm-status

# OpCache-Status
curl localhost/opcache-status

# Queue-Status (falls Redis verwendet)
redis-cli info
```

### 🚨 Alert-Schwellenwerte

| Metrik | Warning | Critical |
|--------|---------|----------|
| CPU-Auslastung | >70% | >85% |
| RAM-Verbrauch | >80% | >90% |
| Disk-I/O | >70% | >85% |
| Response-Zeit | >300ms | >500ms |
| Fehlerrate | >3% | >5% |

## 📈 Continuous Improvement

### Baseline-Metriken etablieren
1. **Initiale Messung**: Aktuelle Performance dokumentieren
2. **Regelmäßige Tests**: Wöchentliche Ausführung
3. **Trend-Analyse**: Performance-Entwicklung überwachen
4. **SLA-Anpassung**: Ziele basierend auf Business-Anforderungen

### Performance-Budget
```javascript
// Performance-Budget Definition
const performanceBudget = {
  loginTime: { max: 100, target: 80 },        // ms
  routeCalculation: { max: 300, target: 200 }, // ms
  apiResponse: { max: 200, target: 150 },     // ms
  errorRate: { max: 5, target: 2 },          // %
  throughput: { min: 50, target: 100 }       // req/s
};
```

### Reporting-Dashboard
- **Grafana**: Performance-Visualisierung
- **New Relic/DataDog**: Application Monitoring
- **Custom Dashboard**: `monitoring-dashboard.html`

---

## 📞 Support

Bei Fragen oder Problemen:

**2Brands Media GmbH**  
LoadTestAgent v1.0.0  
Entwickelt für Metropol Portal

### Nützliche Kommandos

```bash
# Test-Suite Status
./run-load-tests.sh --list-scenarios

# Schneller Health-Check
./run-load-tests.sh --health-check --url https://your-domain.com

# SLA-Validierung nach Tests
./run-load-tests.sh --validate-sla

# Vollständige Test-Suite mit Report
./run-load-tests.sh --scenario all --max-users 100 --output ./results
```

### Weiterführende Dokumentation

- [K6 Documentation](https://k6.io/docs/)
- [Playwright Testing](https://playwright.dev/docs/test-runners)
- [Performance Testing Best Practices](https://k6.io/docs/testing-guides/performance-testing-best-practices/)
- [CLAUDE.md](../../CLAUDE.md) - Projekt-Spezifikation

---

*Diese Dokumentation wird regelmäßig aktualisiert. Letzte Änderung: $(date +"%Y-%m-%d")*