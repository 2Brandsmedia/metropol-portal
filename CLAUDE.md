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
**Letzte Aktualisierung**: ${new Date().toISOString().split('T')[0]}