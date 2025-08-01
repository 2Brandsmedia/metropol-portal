# 🚀 LoadTestAgent - Comprehensive Performance Testing Suite

**Entwickelt von:** 2Brands Media GmbH  
**Für:** Metropol Portal  
**Version:** 1.0.0

## Schnellstart

```bash
# Alle verfügbaren Szenarien anzeigen
./run-load-tests.sh --list-scenarios

# Health-Check
./run-load-tests.sh --health-check

# Morning Rush Szenario (kritisch)
./run-load-tests.sh --scenario morningRush --max-users 50

# Vollständige Test-Suite
./run-load-tests.sh --scenario all
```

## 📋 Realistische Test-Szenarien

### 🌅 Tageszeit-basierte Szenarien
- **Morning Rush (7-9 AM)**: 50 Benutzer, 5 Min - Außendienstmitarbeiter starten ihre Touren
- **Lunch Update (12-1 PM)**: 30 Benutzer, 3 Min - Schnelle Status-Updates und Traffic-Daten
- **Evening Close (5-6 PM)**: 25 Benutzer, 4 Min - Touren abschließen, Berichte generieren

### 💪 Kapazitäts-Tests
- **Normal Load**: 25 Benutzer, 5 Min - Normale Betriebslast
- **Peak Load**: 100 Benutzer, 2 Min - Spitzenlast-Szenarien
- **Stress Test**: 200+ Benutzer, 3 Min - Breaking Point ermitteln

### 🌐 Browser-Simulation
- **Playwright-Tests**: 25 Benutzer, realistische Browser-Interaktionen
- Multi-Browser: Chrome (70%), Firefox (20%), Safari (10%)
- Multi-Device: Desktop (35%), Mobile (55%), Tablet (10%)

## 🎯 SLA-Ziele (aus CLAUDE.md)

| Metrik | Ziel | Priorität |
|--------|------|-----------|
| **Login-Zeit** | ≤ 100ms | 🔴 Kritisch |
| **Route-Berechnung** | ≤ 300ms | 🔴 Kritisch |
| **Stopp-Update** | ≤ 100ms | 🟡 Hoch |
| **API-Response** | ≤ 200ms | 🟡 Hoch |
| **Fehlerrate** | ≤ 5% | 🔴 Kritisch |
| **Erfolgsrate** | ≥ 95% | 🔴 Kritisch |

## 📁 Test-Suite Komponenten

### Core Test Files
- `k6-realtime-scenarios.js` - Haupttest-Szenarien mit K6
- `k6-tests.js` - Erweiterte K6-Tests
- `playwright-load-scenarios.ts` - Browser-basierte Load-Tests
- `load-test-runner.ts` - Test-Orchestrierung und Report-Generation

### Configuration & Execution
- `load-testing.config.ts` - Test-Konfigurationen
- `run-load-tests.sh` - Bash-Ausführungsskript
- `ci-pipeline-integration.yml` - GitHub Actions Pipeline

### Documentation & Monitoring
- `LOAD_TESTING_DOCUMENTATION.md` - Umfassende Dokumentation
- `monitoring-dashboard.html` - Performance-Dashboard
- `baseline-metrics.ts` - Baseline-Performance-Metriken

## 🔄 CI/CD Integration

### GitHub Actions Pipeline
- **Täglich 06:00 UTC**: Kritische Tests auf Staging
- **Wöchentlich So 02:00 UTC**: Vollständige Test-Suite
- **Bei Releases**: Kritische Validierung
- **Pull Requests**: Smoke Tests

### Manuelle Pipeline-Ausführung
```bash
# GitHub Actions Workflow Dispatch
workflow_dispatch:
  inputs:
    environment: staging|production
    test_suite: all|critical|daily_scenarios
    max_users: "50"
```

## 📊 Report-Generierung

### Automatische Reports
- **JSON**: Maschinell lesbare Metriken
- **HTML**: Visuelle Performance-Dashboards
- **Markdown**: GitHub-integrierte Berichte
- **SLA-Compliance**: Automatische Validierung

### Report-Inhalte
- ✅ SLA-Compliance-Status
- 📈 Performance-Metriken (Min, Avg, P95, P99, Max)
- 🚨 SLA-Verletzungen mit Empfehlungen
- 🏗️ Scaling-Empfehlungen
- 🔧 Infrastruktur-Optimierungen

## 🛠️ Installation & Setup

### Abhängigkeiten
```bash
# K6 installieren
# macOS
brew install k6

# Ubuntu/Debian
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 379CE192D401AB61
echo "deb https://dl.bintray.com/loadimpact/deb stable main" | sudo tee -a /etc/apt/sources.list
sudo apt-get update && sudo apt-get install k6

# Node.js Dependencies
npm install

# Playwright Browser
npx playwright install
```

### Schnelle Validierung
```bash
# Health-Check
./run-load-tests.sh --health-check --url https://your-domain.com

# Smoke Test (minimal)
./run-load-tests.sh --scenario smoke

# SLA-Validierung
./run-load-tests.sh --validate-sla
```

## 🚨 Troubleshooting

### Häufige Probleme
1. **Server nicht erreichbar**: Health-Check fehlgeschlagen
2. **K6 nicht gefunden**: Installation prüfen
3. **Node.js Dependencies**: `npm install` ausführen
4. **Playwright Browser**: `npx playwright install`

### Debug-Modus
```bash
# Verbose Logging
export DEBUG=1
./run-load-tests.sh --scenario smoke

# Custom Output-Verzeichnis
./run-load-tests.sh --scenario morningRush --output ./debug-results
```

## 🏆 Best Practices

### Test-Ausführung
1. **Health-Check zuerst**: Immer Erreichbarkeit prüfen
2. **Smoke Test**: Bei Änderungen erst minimal testen
3. **Stufenweise Steigerung**: Smoke → Critical → Full Suite
4. **Monitoring**: System-Metriken während Tests überwachen

### SLA-Management
1. **Baseline etablieren**: Initiale Performance-Messung
2. **Regelmäßige Validierung**: Wöchentliche kritische Tests
3. **Trend-Analyse**: Performance-Entwicklung verfolgen
4. **Proaktive Optimierung**: Bei 80% SLA-Ziel handeln

### Scaling-Strategien
1. **< 50 Users**: Single-Server-Optimierung
2. **50-100 Users**: Load-Balancer + 2 Web-Server
3. **100+ Users**: Multi-Tier mit Database-Clustering

## 📞 Support & Kontakt

**2Brands Media GmbH**  
LoadTestAgent v1.0.0  
Entwickelt für Metropol Portal

### Nützliche Links
- [Vollständige Dokumentation](./LOAD_TESTING_DOCUMENTATION.md)
- [CLAUDE.md Projekt-Spezifikation](../../CLAUDE.md)
- [K6 Documentation](https://k6.io/docs/)
- [Playwright Testing](https://playwright.dev/docs/test-runners)

---

## 🔍 Quick Reference

### Alle Szenarien
```bash
# Tageszeit-Szenarien
./run-load-tests.sh --scenario morningRush    # 50 users, 5min
./run-load-tests.sh --scenario lunchUpdate    # 30 users, 3min  
./run-load-tests.sh --scenario eveningClose   # 25 users, 4min

# Kapazitäts-Tests
./run-load-tests.sh --scenario normalLoad     # 25 users, 5min
./run-load-tests.sh --scenario peakLoad       # 100 users, 2min
./run-load-tests.sh --scenario stressTest     # 200+ users, 3min

# Browser-Simulation
./run-load-tests.sh --scenario browser        # 25 users, Playwright

# Vollständige Suite
./run-load-tests.sh --scenario all            # Alle kritischen Tests
```

### Erweiterte Optionen
```bash
# Custom URL
./run-load-tests.sh --url https://staging.example.com --scenario morningRush

# Custom Output
./run-load-tests.sh --scenario all --output ./custom-results

# Parallel Jobs begrenzen
./run-load-tests.sh --scenario all --jobs 1

# SLA-Only Validierung
./run-load-tests.sh --validate-sla
```

---

*LoadTestAgent - Realistische Load-Testing-Suite für production-ready Performance-Validierung*