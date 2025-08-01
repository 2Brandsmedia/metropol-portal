# 🚀 Metropol Portal - Schnellstart-Anleitung

## Was wurde vorbereitet:

Alle Dateien für Ihr Metropol Portal sind fertig vorbereitet und müssen nur noch hochgeladen werden.

### 📁 Wichtige Dateien:

1. **SETUP_DATABASE.php** - Automatisches Datenbank-Setup (im Root-Verzeichnis)
2. **config/production.php** - Ihre Konfiguration mit allen Zugangsdaten
3. **database/migrations/create_all_tables.sql** - Alle Datenbank-Tabellen
4. **.htaccess** - Bereits für All-Inkl und PHP 8.4 konfiguriert

## 🔧 Einfachste Upload-Methode:

### Option 1: Mit FileZilla (Empfohlen)

1. **FileZilla öffnen** und verbinden:
   - Server: `w019e3c7.kasserver.com`
   - Benutzer: `w019e3c7`
   - Passwort: Ihr FTP-Passwort
   - Port: 21

2. **Navigieren Sie zu**: `/w019e3c7/firmenpro.de/`

3. **Laden Sie diese Ordner hoch**:
   ```
   📁 src/        (alle PHP-Klassen)
   📁 public/     (Webroot mit index.php)
   📁 config/     (Konfiguration)
   📁 database/   (SQL-Dateien)
   📁 templates/  (HTML-Templates)
   📁 lang/       (Übersetzungen)
   📁 routes/     (URL-Routing)
   📁 scripts/    (Wartungsskripte)
   📄 composer.json
   📄 SETUP_DATABASE.php (ins public/ Verzeichnis!)
   ```

4. **Erstellen Sie diese leeren Ordner**:
   ```
   📁 cache/
   📁 logs/
   📁 uploads/
   📁 temp/
   📁 backups/
   ```

### Option 2: Mit All-Inkl WebFTP

1. Loggen Sie sich in All-Inkl KAS ein
2. Gehen Sie zu "FTP" → "WebFTP"
3. Navigieren Sie zu `/www/htdocs/w019e3c7/firmenpro.de/`
4. Laden Sie die Ordner einzeln hoch

## 🗄️ Datenbank einrichten:

1. **Öffnen Sie im Browser**: https://firmenpro.de/SETUP_DATABASE.php

2. Das Skript wird automatisch:
   - Alle Tabellen erstellen
   - Admin-Benutzer anlegen
   - Initiale Daten einfügen

3. **WICHTIG**: Klicken Sie am Ende auf "Diese Setup-Datei jetzt löschen"!

## ✅ Fertig! Erste Anmeldung:

- **URL**: https://firmenpro.de
- **E-Mail**: admin@firmenpro.de  
- **Passwort**: Admin2025!

### Sofort nach Login:
1. Ändern Sie das Admin-Passwort
2. Testen Sie die Sprachumschaltung (DE/EN/TR)
3. Erstellen Sie eine Test-Playlist

## 🕐 Cron-Jobs einrichten (Optional, später):

Im All-Inkl KAS unter "Tools" → "Cronjobs":

```bash
# Alle 5 Minuten
php /www/htdocs/w019e3c7/firmenpro.de/scripts/health-monitor.php

# Täglich um 3:00 Uhr
php /www/htdocs/w019e3c7/firmenpro.de/scripts/maintenance-scheduler.php daily
```

## ❓ Hilfe bei Problemen:

**Weißer Bildschirm?**
- Prüfen Sie ob alle Dateien hochgeladen wurden
- Schauen Sie in `/logs/php_errors.log`

**Login funktioniert nicht?**
- Haben Sie SETUP_DATABASE.php ausgeführt?
- Wurde der Admin-User angelegt?

**Google Maps wird nicht angezeigt?**
- API-Key ist bereits in `/config/production.php` hinterlegt
- Prüfen Sie ob Maps JavaScript API aktiviert ist

---

Das System ist vollständig vorbereitet und muss nur noch hochgeladen werden! 🎉

Entwickelt von 2Brands Media GmbH