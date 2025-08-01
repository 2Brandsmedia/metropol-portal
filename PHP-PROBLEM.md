# ⚠️ PHP-Ausführungsproblem auf firmenpro.de

## Aktuelle Situation:

### ✅ Was funktioniert:
1. **Deployment via GitHub Actions** - Alle Dateien werden korrekt hochgeladen
2. **PHP 8.4 ist im KAS aktiviert** - Screenshot bestätigt
3. **Domain ist als Webspace konfiguriert** - Korrekt eingestellt
4. **test.php funktioniert** - Eine bereits existierende Datei wird ausgeführt
5. **HTTPS-Weiterleitung** - funktioniert (301 Redirect)

### ❌ Was NICHT funktioniert:
- **Neue PHP-Dateien** werden als Text angezeigt statt ausgeführt
- debug.php, check.php, phpinfo.php zeigen nur Quellcode

## 🔍 Mögliche Ursachen:

### 1. **Dateiberechtigungen**
Die neuen Dateien haben möglicherweise falsche Permissions:
- test.php (funktioniert) hat wahrscheinlich 644 oder 755
- Neue Dateien könnten 600 oder andere Rechte haben

### 2. **Verzeichnis-spezifische Einstellung**
- Möglicherweise gibt es eine übergeordnete .htaccess
- Oder spezielle Einstellungen für das Hauptverzeichnis

### 3. **All-Inkl Sicherheitseinstellung**
- "Nur signierte PHP-Dateien ausführen"
- Oder ähnliche Sicherheitsmechanismen

## 🆘 Support-Anfrage bei All-Inkl:

**Betreff:** PHP-Dateien werden nicht ausgeführt auf firmenpro.de

**Nachricht:**
```
Hallo,

auf meiner Domain firmenpro.de werden PHP-Dateien als Quelltext angezeigt statt ausgeführt.

Details:
- PHP 8.4 ist im KAS aktiviert
- Die Datei test.php funktioniert korrekt
- Neue PHP-Dateien (debug.php, phpinfo.php) zeigen nur Quellcode
- .htaccess mit "AddHandler application/x-httpd-php84 .php" ist vorhanden

Können Sie bitte prüfen, warum neue PHP-Dateien nicht ausgeführt werden?

Vielen Dank!
```

## 🔧 Workaround:

Bis das Problem gelöst ist, können Sie:
1. Die funktionierende test.php als Basis nutzen
2. Den Inhalt von test.php durch unseren Code ersetzen

## 📝 Zu prüfen im KAS:

Falls es weitere Menüpunkte gibt, suchen Sie nach:
- **"Sicherheit"** → PHP-Ausführung
- **"Erweiterte Einstellungen"** → Script-Sicherheit
- **"PHP-Optionen"** → Ausführungsmodus