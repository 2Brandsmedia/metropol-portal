# Debug-Anleitung für "Internal Server Error"

## Schnelle Lösung:

### 1. .htaccess ersetzen
Benennen Sie die Dateien um:
- `.htaccess` → `.htaccess_backup`
- `.htaccess_simple` → `.htaccess`

### 2. Falls das nicht hilft, .htaccess komplett löschen
Dann direkt aufrufen:
- `ihre-domain.de/metropol-portal/install.php`

### 3. Error Log prüfen
Im All-Inkl KAS:
1. Login → Webspace → Logfiles
2. Error-Log der Domain öffnen
3. Letzte Einträge prüfen

## Häufige Ursachen bei All-Inkl:

### Problem 1: PHP-Version
- Stellen Sie sicher, dass PHP 8.1+ aktiviert ist
- KAS → Einstellungen → PHP-Version → PHP 8.3 wählen

### Problem 2: .htaccess Direktiven
Folgende Zeilen können Probleme machen:
- `php_value` und `php_flag` Direktiven
- `Header` Direktiven
- Komplexe `RewriteCond` Regeln

### Problem 3: Verzeichnisstruktur
Prüfen Sie:
- Ist das ZIP richtig entpackt?
- Liegen alle Dateien im richtigen Ordner?
- Stimmen die Dateiberechtigungen? (755 für Ordner, 644 für Dateien)

## Notfall-Installation ohne .htaccess:

1. Löschen Sie alle .htaccess Dateien
2. Öffnen Sie direkt: `ihre-domain.de/metropol-portal/install.php`
3. Nach der Installation:
   - Erstellen Sie eine neue .htaccess nur mit:
   ```
   RewriteEngine On
   RewriteCond %{REQUEST_URI} !^/public/
   RewriteRule ^(.*)$ public/$1 [L]
   ```

## Test-Datei erstellen:

Erstellen Sie `test.php` im Hauptverzeichnis:
```php
<?php
phpinfo();
```

Wenn diese funktioniert, liegt es an der .htaccess.

## Support:

Senden Sie mir den Error-Log Eintrag, dann kann ich gezielt helfen!