# 🔧 Troubleshooting Guide für firmenpro.de

## Aktuelle Situation
- **Status**: 500 Internal Server Error
- **SSL**: Zertifikat-Fehler (falscher Hostname)
- **Server**: All-Inkl (Apache, kasserver.com)

## 📋 Schritt-für-Schritt Fehlersuche

### 1. Erste Diagnose (5 Minuten)

**Dateien per FTP hochladen:**
```
firmenpro.de/
├── debug.php       (Zeigt PHP-Info und Fehler)
├── check.php       (Prüft Installation)
├── .htaccess       (aus .htaccess.debug kopieren)
└── .env            (aus .env.firmenpro kopieren)
```

**URLs testen:**
- http://firmenpro.de/debug.php
- http://firmenpro.de/check.php

### 2. Häufige Fehlerursachen

#### A) PHP-Version falsch
**Symptom**: 500 Error, keine Details
**Lösung**: 
- KAS → Domain → PHP-Einstellungen
- PHP 8.3 oder 8.4 aktivieren

#### B) .htaccess Probleme
**Symptom**: 500 Error sofort
**Lösung**:
```apache
# Minimale .htaccess zum Testen
AddHandler application/x-httpd-php83 .php
DirectoryIndex index.php
Options -Indexes
```

#### C) Fehlende Dateien
**Symptom**: Class not found Fehler
**Lösung**:
- `vendor/` Ordner komplett hochladen
- Oder SSH: `composer install`

#### D) Datenbankfehler
**Symptom**: PDO Exception
**Lösung**:
- `.env` Datei prüfen
- Datenbank-Zugangsdaten korrekt?
- Datenbank existiert?

### 3. Logs überprüfen

**PHP Error Log**:
```bash
# Per FTP herunterladen
/logs/php_errors.log
```

**Apache Error Log** (im KAS):
- Tools → Logfiles
- Error-Log auswählen

### 4. Schritt-für-Schritt Installation

Falls Portal noch nicht installiert:

1. **Backup der aktuellen Dateien**
2. **Minimale Struktur hochladen**:
   ```
   - index.php (Root)
   - install.php
   - .htaccess.debug (als .htaccess)
   - installer/ (Ordner)
   ```
3. **http://firmenpro.de/install.php** aufrufen
4. **Installer folgen**

### 5. Emergency Recovery

Falls nichts funktioniert:

#### A) Blanko PHP-Test
Erstellen Sie `test.php`:
```php
<?php
phpinfo();
```
Funktioniert http://firmenpro.de/test.php?

#### B) Statische HTML
Erstellen Sie `test.html`:
```html
<!DOCTYPE html>
<html>
<body>
<h1>Test OK</h1>
</body>
</html>
```
Funktioniert http://firmenpro.de/test.html?

#### C) Kompletter Reset
1. Alle Dateien löschen (Backup!)
2. Nur `index.html` mit "Coming Soon"
3. Schrittweise Dateien hinzufügen

## 🎯 Quick Fixes

### Fix 1: Composer lokal ausführen
```bash
# Lokal auf Ihrem Rechner
composer install --no-dev
# Dann vendor/ Ordner hochladen
```

### Fix 2: Installation erzwingen
```bash
# .env löschen
# Dann install.php aufrufen
```

### Fix 3: Public-Ordner direkt
```
# Testen Sie:
http://firmenpro.de/public/index.php
```

## 📞 Eskalation

Falls nach 30 Minuten keine Lösung:

1. **All-Inkl Support**
   - "500 Error auf Domain"
   - PHP Error Log anfordern
   - .htaccess überprüfen lassen

2. **Entwickler-Support**
   - Exakte Fehlermeldung
   - debug.php Output
   - Durchgeführte Schritte

## ✅ Erfolgs-Checkliste

- [ ] debug.php zeigt keine Fehler
- [ ] check.php zeigt alles grün
- [ ] Login-Seite erscheint
- [ ] Keine 500 Errors
- [ ] SSL funktioniert

---

**Wichtig**: Nach Behebung alle Debug-Dateien löschen!