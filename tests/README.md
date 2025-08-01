# Metropol Portal - Playwright Test Suite

Entwickelt von 2Brands Media GmbH

## Übersicht

Diese Test-Suite bietet umfassende E2E-, Performance- und Accessibility-Tests für das Metropol Portal. Die Tests sind mit Playwright implementiert und decken alle kritischen User-Journeys ab.

## Test-Struktur

```
tests/
├── e2e/                    # End-to-End Tests
│   ├── auth/              # Authentifizierung & Session-Management
│   ├── playlists/         # Playlist-Verwaltung
│   ├── maps/              # Karten & Navigation
│   └── i18n/              # Mehrsprachigkeit
├── page-objects/          # Page Object Models
├── fixtures/              # Test-Fixtures (Auth, etc.)
├── performance/           # Performance & Accessibility Tests
├── api/                   # API-Integration Tests
└── load/                  # Load-Testing (K6)
```

## Schnellstart

### Installation
```bash
npm install
npm run playwright:install
```

### Tests ausführen

```bash
# Alle Tests
npm test

# Spezifische Test-Suites
npm run test:auth        # Nur Authentifizierungs-Tests
npm run test:playlists   # Nur Playlist-Tests
npm run test:api         # Nur API-Tests
npm run test:perf        # Nur Performance-Tests

# Tests im UI-Modus (interaktiv)
npm run test:ui

# Tests mit sichtbarem Browser
npm run test:headed

# Debug-Modus
npm run test:debug
```

### Test-Reports

Nach der Ausführung:
```bash
npm run test:report
```

## Wichtige Test-Szenarien

### 1. Authentifizierung
- ✅ Login/Logout für verschiedene Benutzertypen
- ✅ Session-Management und Remember-Me
- ✅ CSRF-Token-Validierung
- ✅ Performance: Login < 100ms

### 2. Playlist-Management
- ✅ Erstellen von Playlists (1-20 Stopps)
- ✅ Bearbeiten, Löschen, Neuanordnung
- ✅ Route-Berechnung mit Live-Traffic
- ✅ Performance: Route-Berechnung < 300ms

### 3. Maps & Navigation
- ✅ Karten-Initialisierung und Interaktion
- ✅ Marker und Route-Visualisierung
- ✅ Traffic-Layer Toggle
- ✅ Geolocation-Support

### 4. Internationalisierung
- ✅ Sprachwechsel (DE/EN/TR)
- ✅ Cookie-basierte Persistierung
- ✅ Datum/Zeit/Zahlen-Formatierung
- ✅ Vollständige UI-Übersetzung

### 5. Performance
- ✅ Lighthouse Score > 95
- ✅ Core Web Vitals (FCP < 1.5s, LCP < 2.5s, CLS < 0.1)
- ✅ Bundle-Size-Optimierung
- ✅ Memory-Leak-Detection

### 6. Accessibility
- ✅ WCAG 2.1 AA Compliance
- ✅ Keyboard-Navigation
- ✅ Screen-Reader-Support
- ✅ Focus-Management

## Performance-Ziele (aus CLAUDE.md)

| Metrik | Ziel | Test-Coverage |
|--------|------|---------------|
| Login | < 100ms | ✅ |
| API-Response | < 200ms | ✅ |
| Route-Berechnung | < 300ms | ✅ |
| Dashboard-Load | < 1s | ✅ |
| Lighthouse Score | > 95 | ✅ |

## CI/CD Integration

Die Tests sind für GitHub Actions vorbereitet:
- PR-Checks: Smoke-Tests bei jedem Pull Request
- Nightly: Vollständige Test-Suite täglich
- Performance: Wöchentliche Performance-Regression-Tests

## Debugging

### Screenshots bei Fehlern
Bei fehlgeschlagenen Tests werden automatisch Screenshots in `test-results/` gespeichert.

### Video-Aufzeichnung
Videos werden bei Fehlern aufgezeichnet und in `test-results/` gespeichert.

### Trace-Viewer
```bash
npx playwright show-trace trace.zip
```

## Best Practices

1. **Page Objects nutzen**: Alle Seiten-Interaktionen über Page Objects
2. **Fixtures verwenden**: Auth-Fixture für eingeloggte Tests
3. **Parallele Ausführung**: Tests sind isoliert und parallel ausführbar
4. **Keine harten Waits**: `waitFor*` statt `setTimeout`
5. **Aussagekräftige Assertions**: Klare Fehlermeldungen bei Test-Fehlern

## Wartung

### Neue Tests hinzufügen
1. Page Object erstellen/erweitern in `page-objects/`
2. Test-Datei in entsprechendem Verzeichnis anlegen
3. `npm test` lokal ausführen
4. PR mit Tests erstellen

### Test-Daten
- Test-Benutzer: `test.user@metropol.de` / `TestPassword123!`
- Admin-Benutzer: `admin@metropol.de` / `AdminPassword123!`
- Test-Adressen: Bekannte Berlin-Adressen verwenden

## Troubleshooting

### Browser-Installation
```bash
npx playwright install --with-deps
```

### Port bereits belegt
```bash
lsof -ti:8080 | xargs kill -9
```

### Tests timeout
- `playwright.config.ts` anpassen
- Timeouts in einzelnen Tests erhöhen
- Network-Conditions prüfen

---

Bei Fragen oder Problemen: Entwicklungsteam der 2Brands Media GmbH kontaktieren.