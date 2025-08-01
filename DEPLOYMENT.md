# Deployment-Anleitung für firmenpro.de (All-Inkl)

## 🚨 Aktueller Status: 500 Internal Server Error

### Identifizierte Probleme:
1. **500 Error** - Server kann Anfrage nicht verarbeiten
2. **SSL-Zertifikat** - Falsche Domain (*.kasserver.com statt firmenpro.de)
3. **Unbekannter Installationsstatus** - Unklar ob Installation durchgeführt wurde

## 📋 Sofort-Maßnahmen

### 1. Debug-Dateien hochladen
Laden Sie folgende Dateien per FTP hoch:
- `debug.php` → Zeigt detaillierte Fehlerinformationen
- `check.php` → Prüft Installationsstatus
- `.htaccess.debug` → Als `.htaccess` hochladen (vorherige sichern!)

### 2. Debug-URLs aufrufen
Nach dem Upload testen Sie:
- http://firmenpro.de/debug.php
- http://firmenpro.de/check.php

### 3. Fehler analysieren
Die Debug-Ausgabe zeigt:
- PHP-Version und Extensions
- Fehlende Dateien/Verzeichnisse
- Datenbankverbindung
- Installationsstatus

## 🔧 Konfiguration anpassen

### All-Inkl spezifische Anpassungen

#### 1. PHP-Version
Im KAS (Kundenadministrationssystem):
- Domain → firmenpro.de → PHP-Einstellungen
- PHP 8.3 oder 8.4 aktivieren

#### 2. SSL-Zertifikat
Im KAS:
- Domain → firmenpro.de → SSL-Schutz
- Let's Encrypt aktivieren ODER
- Eigenes Zertifikat hochladen

#### 3. Dateirechte
Per FTP oder SSH:
```bash
chmod 755 logs/
chmod 755 storage/
chmod 755 public/uploads/
chmod 644 .htaccess
chmod 644 .env
```

## 📦 Installation durchführen

### Falls NICHT installiert:

1. **.env Datei vorbereiten**
   - `.env.firmenpro` zu `.env` umbenennen
   - Datenbank-Zugangsdaten eintragen
   - API-Keys eintragen

2. **Datenbank einrichten**
   Im KAS:
   - MySQL-Datenbank anlegen
   - Benutzername/Passwort notieren
   - In .env eintragen

3. **Installation starten**
   - http://firmenpro.de/install.php aufrufen
   - Installer-Wizard folgen

### Falls bereits installiert:

1. **Composer ausführen**
   Per SSH oder lokalen Upload:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Datenbank-Migration**
   ```bash
   php migrate.php
   ```

## 🚀 Deployment-Checkliste

### Vor dem Upload:
- [ ] `.env.firmenpro` mit korrekten Daten gefüllt
- [ ] Composer lokal ausgeführt
- [ ] Build-Prozess durchgeführt (CSS/JS)

### Upload per FTP:
```
firmenpro.de/
├── .htaccess (aus .htaccess.debug)
├── .env (aus .env.firmenpro)
├── debug.php
├── check.php
├── install.php
├── composer.json
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── [assets]
├── vendor/ (kompletter Ordner)
├── src/
├── routes/
├── resources/
├── logs/ (Ordner erstellen)
└── storage/ (Ordner erstellen)
```

### Nach dem Upload:
- [ ] http://firmenpro.de/check.php aufrufen
- [ ] Alle Checks grün?
- [ ] Installation durchführen falls nötig
- [ ] debug.php und check.php löschen
- [ ] .htaccess für Production anpassen

## 🔍 Fehlersuche

### Häufige Probleme:

1. **500 Error bleibt**
   - PHP Error Log prüfen: `/logs/php_errors.log`
   - Apache Error Log im KAS prüfen

2. **Composer Fehler**
   - Lokal `composer install` ausführen
   - vendor/ Ordner komplett hochladen

3. **Datenbank-Fehler**
   - Verbindungsdaten in .env prüfen
   - Datenbank im KAS prüfen

4. **Pfad-Probleme**
   - Document Root prüfen
   - RewriteBase in .htaccess anpassen

## 📞 Support-Kontakte

- **All-Inkl Support**: Bei Server-Problemen
- **2Brands Media GmbH**: Bei Anwendungs-Problemen

## ✅ Erfolgs-Kriterien

Die Installation ist erfolgreich wenn:
1. http://firmenpro.de ohne Fehler lädt
2. Login-Seite erscheint
3. HTTPS funktioniert
4. Keine 500-Fehler mehr

---

**Wichtig**: Nach erfolgreicher Installation alle Debug-Dateien löschen!