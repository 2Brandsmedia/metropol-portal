# Metropol Portal

Ein mehrsprachiges Mitarbeiter-Portal mit intelligenter Routenplanung für effiziente Tagesabläufe.

## 🚀 Funktionen

- **Intelligente Routenplanung**: Optimierung von bis zu 20 Stopps pro Tag
- **Live-Traffic-Integration**: Echtzeit-Verkehrsdaten für präzise Zeitplanung
- **Mehrsprachigkeit**: Deutsch, Englisch und Türkisch ohne URL-Änderung
- **Mobile-First Design**: Optimiert für Smartphones im Außendienst
- **DSGVO-konform**: Vollständiger Datenschutz nach EU-Standards

## 🛠 Technologie-Stack

### Backend
- PHP 8.3+ mit Slim Framework
- MySQL 8.0
- JWT-basierte Authentifizierung
- RESTful API

### Frontend
- TypeScript für typsichere Entwicklung
- TailwindCSS für modernes UI-Design
- Leaflet für interaktive Karten
- Alpine.js für reaktive Komponenten

### Qualitätssicherung
- Playwright für Cross-Browser E2E-Tests
- PHPUnit für Backend-Tests
- ESLint & PHP CodeSniffer für Code-Standards
- Pre-commit Hooks für automatische Prüfungen

## 📋 Voraussetzungen

- PHP 8.3 oder höher
- MySQL 8.0 oder höher
- Node.js 18+ und npm 9+
- Composer 2.x
- Git

## 🔧 Installation

1. **Repository klonen**
   ```bash
   git clone https://github.com/2brands/metropol-portal.git
   cd metropol-portal
   ```

2. **PHP-Dependencies installieren**
   ```bash
   composer install
   ```

3. **Node-Dependencies installieren**
   ```bash
   npm install
   ```

4. **Umgebungsvariablen konfigurieren**
   ```bash
   cp .env.example .env
   # .env Datei mit Ihren Datenbank-Zugangsdaten anpassen
   ```

5. **Datenbank migrieren**
   ```bash
   composer run migrate
   ```

6. **Frontend-Assets kompilieren**
   ```bash
   npm run build
   ```

## 🚀 Entwicklung

### Development Server starten
```bash
# Backend (PHP Built-in Server)
php -S localhost:8000 -t public

# Frontend (Vite Dev Server)
npm run dev
```

### Code-Qualität prüfen
```bash
# Alle Checks ausführen
npm run pre-commit

# Einzelne Checks
npm run lint          # ESLint
npm run typecheck     # TypeScript
composer run cs       # PHP CodeSniffer
composer run stan     # PHPStan
```

### Tests ausführen
```bash
# E2E Tests mit Playwright
npm run test:e2e

# Unit Tests mit PHPUnit
composer run test

# Alle Tests
npm test
```

## 📱 Nutzung

### Als Administrator
1. Melden Sie sich mit Ihren Admin-Zugangsdaten an
2. Erstellen Sie Tagesrouten mit bis zu 20 Stopps
3. Weisen Sie Routen Mitarbeitern zu
4. Verwalten Sie Benutzer und Einstellungen

### Als Mitarbeiter
1. Melden Sie sich mit Ihren Zugangsdaten an
2. Sehen Sie Ihre Tagesroute als Liste und auf der Karte
3. Markieren Sie erledigte Stopps
4. Nutzen Sie die Live-Navigation

## 🌐 Mehrsprachigkeit

Die Anwendung unterstützt drei Sprachen:
- 🇩🇪 Deutsch
- 🇬🇧 Englisch
- 🇹🇷 Türkisch

Die Sprache kann jederzeit über den Sprachschalter geändert werden.

## 🚀 Deployment

Das Projekt ist für All-Inkl Shared Hosting optimiert:

1. **Git Push zu Production Branch**
   ```bash
   git push production main
   ```

2. **Automatisches Deployment** via Git-Hook
   - Datenbank-Migrationen werden automatisch ausgeführt
   - Assets werden kompiliert
   - Cache wird geleert

## 🔒 Sicherheit

- HTTPS wird erzwungen
- Content Security Policy aktiv
- SQL-Injection-Schutz durch Prepared Statements
- XSS-Prevention auf allen Ebenen
- Rate Limiting für API-Endpunkte
- CSRF-Token-Schutz

## 📊 Performance-Ziele

- Ladezeit: < 1 Sekunde
- API-Response: < 200ms
- Route-Berechnung: < 300ms
- Lighthouse Score: > 95

## 🤝 Mitwirken

Bitte lesen Sie [CONTRIBUTING.md](CONTRIBUTING.md) für Details zu unserem Code of Conduct und dem Prozess für Pull Requests.

## 📄 Lizenz

Dieses Projekt ist proprietäre Software der 2Brands Media GmbH.

## 👥 Entwickelt von

**2Brands Media GmbH**  
[https://2brands.de](https://2brands.de)

---

Für weitere Informationen siehe [CLAUDE.md](CLAUDE.md)