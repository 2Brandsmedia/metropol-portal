# Metropol Portal - Single Source of Truth

## Projektübersicht
Ein mehrsprachiges Mitarbeiter-Portal mit intelligenter Routenplanung für die tägliche Aufgabenverteilung. Entwickelt von 2Brands Media GmbH.

### Kernfunktionen
- **Tagesrouten-Management**: Bis zu 20 Stopps pro Tag mit optimierter Route
- **Live-Traffic-Integration**: Echtzeit-Verkehrsdaten für präzise Zeitplanung
- **Mehrsprachigkeit**: Deutsch, Englisch und Türkisch ohne URL-Änderung
- **Mobile-First**: Optimiert für Smartphones der Außendienstmitarbeiter
- **DSGVO-konform**: Vollständiger Datenschutz nach EU-Standards

## Technologie-Stack

### Backend
- **PHP 8.3+** mit schlankem Micro-Framework
- **MySQL 8.0** für relationale Datenhaltung
- **Composer** für Dependency Management
- **PHPUnit** für Unit-Tests

### Frontend
- **TypeScript** für typsichere Entwicklung
- **TailwindCSS** für Utility-First Styling
- **Leaflet** für interaktive Karten
- **Alpine.js** für reaktive UI-Komponenten

### Qualitätssicherung
- **Playwright MCP** für Cross-Browser E2E-Tests
- **ref-tools MCP** für Dokumentations-Validierung
- **shadcn MCP** für konsistente UI-Komponenten
- **ESLint** und **PHP CodeSniffer** für Code-Standards

### Deployment
- **All-Inkl Shared Hosting** mit PHP 8.3
- **Git-basiertes Deployment** via Webhook
- **Zero-Downtime** Deployment-Strategie

## WICHTIGES ENTWICKLUNGSPRINZIP: Agent-First

### Grundregel
**IMMER Agenten für ALLE Aufgaben nutzen!** Kein direktes Coding ohne Agent-Koordination.

### Warum Agent-First?
- **Spezialisierung**: Jeder Agent ist Experte in seinem Bereich
- **Qualität**: Automatische Checks und Validierungen
- **Konsistenz**: Einheitliche Patterns und Standards
- **Effizienz**: Parallelisierung und optimierte Workflows

### Entwicklungs-Workflow mit Agents

1. **Neue Features**: TestAgent → FeatureAgent → QualityAgent → DeployAgent
2. **Bugfixes**: TestAgent → DebugAgent → QualityAgent → DeployAgent  
3. **Performance**: MonitorAgent → PerformanceAgent → TestAgent → DeployAgent
4. **Security**: SecurityAgent → PatchAgent → TestAgent → DeployAgent

### Agent-Nutzung in der Praxis

```bash
# FALSCH - Direktes Editieren
Edit file.php

# RICHTIG - Agent nutzen
Task: "FeatureAgent: Implement user registration validation"
```

### Agent-Kommunikation

Agents arbeiten IMMER zusammen:
- TestAgent validiert JEDEN Code
- QualityAgent prüft JEDEN Commit
- SecurityAgent scannt JEDE Änderung
- MonitorAgent überwacht ALLES in Production

### Beispiel: Abnahme- und Leistungstests implementieren

```bash
# Schritt 1: Performance-Baseline erstellen
Task: "PerformanceTestAgent: Create performance baseline for all critical user paths"

# Schritt 2: API-Monitoring aufsetzen  
Task: "APILimitAgent: Setup monitoring dashboard for Google Maps, Nominatim and ORS APIs"

# Schritt 3: Load Tests durchführen
Task: "LoadTestAgent: Execute morning rush scenario with 100 concurrent users"

# Schritt 4: Cache-Optimierung
Task: "CacheAgent: Optimize caching strategy to reduce API calls by 50%"

# Schritt 5: Qualitätssicherung
Task: "QualityAgent: Validate all performance improvements and create report"
```

### Agent-Hierarchie für komplexe Aufgaben

Bei größeren Aufgaben koordiniert ein Lead-Agent die anderen:

```
PerformanceOptimizationLead
├── PerformanceTestAgent (Messungen)
├── APILimitAgent (Limits überwachen)
├── LoadTestAgent (Last simulieren)
├── CacheAgent (Optimierungen)
└── QualityAgent (Validierung)
```

## Agentenarchitektur

### Kern-Agents

#### AuthAgent
- **Zweck**: Sichere Authentifizierung und Session-Management
- **Erfolgskriterium**: Login-Response < 100ms
- **Verantwortlichkeiten**:
  - Benutzer-Authentifizierung
  - Session-Verwaltung
  - Rechteverwaltung (Admin/Mitarbeiter)
  - CSRF-Token-Generierung

#### PlaylistAgent
- **Zweck**: Verwaltung der Tagesrouten
- **Erfolgskriterium**: Änderungen ohne Fehler persistiert
- **Verantwortlichkeiten**:
  - CRUD-Operationen für Playlists
  - Stopp-Verwaltung (bis zu 20 pro Tag)
  - Status-Tracking (erledigt/offen)
  - Arbeitszeit-Kalkulation pro Stopp

#### GeoAgent
- **Zweck**: Adress-Geokodierung und Caching
- **Erfolgskriterium**: Trefferquote > 98%
- **Verantwortlichkeiten**:
  - Adresse zu Koordinaten
  - Reverse Geocoding
  - Ergebnis-Caching
  - Fallback-Strategien

#### RouteAgent
- **Zweck**: Routenberechnung mit Live-Traffic
- **Erfolgskriterium**: Response < 300ms
- **Verantwortlichkeiten**:
  - Optimale Routenberechnung
  - Live-Traffic-Integration
  - Zeitschätzungen
  - Alternative Routen

#### I18nAgent
- **Zweck**: Mehrsprachigkeits-Management
- **Erfolgskriterium**: 100% Übersetzungsabdeckung
- **Verantwortlichkeiten**:
  - Sprachdatei-Verwaltung
  - Cookie-basiertes Sprach-Switching
  - Fallback-Mechanismen
  - Übersetzungs-Validierung

#### DeployAgent
- **Zweck**: Automatisiertes Deployment
- **Erfolgskriterium**: Zero-Downtime
- **Verantwortlichkeiten**:
  - Git-Hook-Management
  - Datenbank-Migrationen
  - Asset-Kompilierung
  - Rollback-Funktionalität

### Support-Agents

#### SecurityAgent
- **Zweck**: Sicherheits-Überwachung
- **Erfolgskriterium**: Keine Sicherheitslücken
- **Verantwortlichkeiten**:
  - DSGVO-Compliance
  - SQL-Injection-Schutz
  - XSS-Prevention
  - Security-Header-Management
  - Penetration-Test-Vorbereitung

#### QualityAgent
- **Zweck**: Code-Qualitätssicherung
- **Erfolgskriterium**: 0 Lint-Fehler, 0 TypeScript-Fehler
- **Verantwortlichkeiten**:
  - Automatische Code-Reviews
  - Lint-Checks (ESLint, PHP CS)
  - TypeScript-Validierung
  - Code-Coverage-Analyse
  - Performance-Profiling

#### TestAgent
- **Zweck**: Automatisierte Tests
- **Erfolgskriterium**: 100% kritische Pfade getestet
- **Verantwortlichkeiten**:
  - E2E-Tests mit Playwright
  - Unit-Tests mit PHPUnit
  - Integration-Tests
  - Load-Tests
  - Regressionstest-Suite

#### UIAgent
- **Zweck**: UI/UX-Konsistenz
- **Erfolgskriterium**: WCAG 2.1 AA konform
- **Verantwortlichkeiten**:
  - shadcn-Komponenten-Integration
  - Responsive Design-Validierung
  - Barrierefreiheits-Prüfung
  - Design-System-Pflege
  - Mobile-Optimierung

#### DocAgent
- **Zweck**: Dokumentations-Management
- **Erfolgskriterium**: Vollständige API-Docs
- **Verantwortlichkeiten**:
  - Code-Dokumentation
  - API-Dokumentation (OpenAPI)
  - Benutzerhandbuch
  - Entwickler-Dokumentation
  - Changelog-Pflege

#### MonitorAgent
- **Zweck**: System-Überwachung
- **Erfolgskriterium**: 99.9% Uptime
- **Verantwortlichkeiten**:
  - Performance-Metriken
  - Error-Tracking
  - API-Nutzungs-Monitoring
  - Resource-Überwachung
  - Alert-Management

#### DataAgent
- **Zweck**: Datenbank-Optimierung
- **Erfolgskriterium**: Query-Response < 50ms
- **Verantwortlichkeiten**:
  - Query-Optimierung
  - Index-Management
  - Backup-Strategien
  - Daten-Migration
  - GDPR-konforme Löschung

### Spezial-Agents für Abnahmetests

#### PerformanceTestAgent
- **Zweck**: Ladezeiten und Performance-Tests unter realistischen Bedingungen
- **Erfolgskriterium**: Alle Performance-Metriken im grünen Bereich
- **Verantwortlichkeiten**:
  - Lighthouse-Tests automatisieren (Score > 95)
  - Core Web Vitals messen (FCP < 1.5s, TTI < 3s, CLS < 0.1)
  - Load Testing mit Apache JMeter/Locust
  - Performance-Baseline etablieren
  - Bottleneck-Analyse und Reporting

#### APILimitAgent
- **Zweck**: API-Nutzung überwachen und Limits verwalten
- **Erfolgskriterium**: Keine API-Limit-Überschreitungen
- **Verantwortlichkeiten**:
  - Google Maps API Monitoring (25k/Tag)
  - Nominatim Rate Limiting (1 req/s)
  - OpenRouteService Quota-Tracking
  - Warnstufen implementieren (80% gelb, 90% rot, 95% blockieren)
  - Fallback-Strategien bei Limit-Erreichen
  - Dashboard für API-Nutzungsstatistiken

#### LoadTestAgent
- **Zweck**: Realistische Last-Szenarien simulieren
- **Erfolgskriterium**: 100+ gleichzeitige Nutzer ohne Degradation
- **Verantwortlichkeiten**:
  - Morning Rush Simulation (7-9 Uhr, 80% Tagesrouten)
  - Lunch Update Tests (12-13 Uhr, Traffic-Updates)
  - Evening Close Tests (17-18 Uhr, Status-Updates)
  - Stress-Tests mit 200+ Nutzern
  - Response-Zeit-Monitoring (< 300ms)
  - Skalierungs-Empfehlungen

#### CacheAgent
- **Zweck**: Intelligente Cache-Strategien für API-Optimierung
- **Erfolgskriterium**: 50% Reduktion externer API-Calls
- **Verantwortlichkeiten**:
  - Multi-Layer-Caching (Redis, MySQL, Browser)
  - Cache-Warming für häufige Routen
  - TTL-Optimierung basierend auf Nutzungsmuster
  - Cache-Invalidierung bei Updates
  - Hit-Rate-Monitoring und Optimierung

## Code-Standards

### PHP
- **PSR-12** Coding Standard
- **Strict Types** in allen Dateien
- **Type Hints** für alle Parameter und Returns
- **DocBlocks** für alle öffentlichen Methoden

### JavaScript/TypeScript
- **Airbnb Style Guide**
- **Strict Mode** aktiviert
- **No Any** Policy für TypeScript
- **JSDoc** für komplexe Funktionen

### CSS
- **TailwindCSS** Utility-First
- **BEM** für Custom-Komponenten
- **Mobile-First** Responsive Design
- **CSS Variables** für Theming

## Datenbank-Schema

### Tabellen
- `users`: Benutzer und Authentifizierung
- `playlists`: Tagesrouten
- `stops`: Einzelne Stopps einer Playlist
- `geocache`: Geocoding-Cache
- `sessions`: PHP-Sessions
- `audit_log`: Änderungsprotokoll

## API-Endpunkte

### Authentifizierung
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/status`

### Playlists
- `GET /api/playlists` (Liste)
- `POST /api/playlists` (Erstellen)
- `PUT /api/playlists/{id}` (Aktualisieren)
- `DELETE /api/playlists/{id}` (Löschen)

### Routing
- `POST /api/route/calculate`
- `GET /api/route/traffic`

### Internationalisierung
- `POST /api/i18n/switch`
- `GET /api/i18n/translations`

## Deployment-Prozess

1. **Pre-Deployment**
   - Alle Tests müssen grün sein
   - Lint-Checks bestanden
   - TypeScript-Kompilierung erfolgreich

2. **Deployment**
   - Git Push zu Production Branch
   - Webhook triggert Deployment-Skript
   - Datenbank-Migrationen laufen
   - Assets werden kompiliert
   - Cache wird geleert

3. **Post-Deployment**
   - Health-Checks laufen
   - Monitoring wird aktiviert
   - Rollback bereit bei Fehlern

## Entwicklungs-Workflow

1. **Feature-Entwicklung**
   - Feature-Branch erstellen
   - Tests zuerst schreiben (TDD)
   - Implementation
   - Code-Review durch QualityAgent
   - Merge nach main

2. **Qualitätssicherung**
   - Pre-commit Hooks aktiv
   - CI/CD Pipeline läuft
   - Automatische Security-Scans
   - Performance-Tests

## Performance-Ziele

- **Ladezeit**: < 1 Sekunde
- **API-Response**: < 200ms
- **Route-Berechnung**: < 300ms
- **Login**: < 100ms
- **Lighthouse Score**: > 95

## Sicherheits-Maßnahmen

- **HTTPS** erzwungen
- **Content Security Policy** aktiv
- **Rate Limiting** für APIs
- **Input Validation** auf allen Ebenen
- **Prepared Statements** für DB-Queries
- **2FA** Option für Admins

## Monitoring und Alerts

- **Uptime Monitoring**: 24/7
- **Error Tracking**: Sentry-Integration
- **Performance Monitoring**: New Relic
- **Security Scanning**: Wöchentlich
- **Backup-Validierung**: Täglich

## Wartung und Support

- **Regelmäßige Updates**: Monatlich
- **Security Patches**: Sofort
- **Backup-Strategie**: 3-2-1 Regel
- **Disaster Recovery**: RTO < 4h
- **Support-Level**: Business Hours

---

**Entwickelt von**: 2Brands Media GmbH  
**Version**: 1.0.0  
**Letzte Aktualisierung**: 2025-07-30