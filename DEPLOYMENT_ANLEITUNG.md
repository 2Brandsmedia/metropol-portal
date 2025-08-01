# Metropol Portal - Deployment-Anleitung für All-Inkl

## Ihre Zugangsdaten:
- **FTP-Server**: w019e3c7.kasserver.com
- **FTP-User**: w019e3c7
- **Domain**: https://firmenpro.de
- **MySQL-DB**: d0446399 (User: d0446399)

## Schritt-für-Schritt Anleitung:

### 1. FTP-Upload (mit FileZilla oder ähnlichem FTP-Client)

Verbinden Sie sich mit dem FTP-Server und laden Sie folgende Verzeichnisse hoch nach `/w019e3c7/firmenpro.de/`:

```
📁 /src          → /w019e3c7/firmenpro.de/src
📁 /public       → /w019e3c7/firmenpro.de/public  
📁 /config       → /w019e3c7/firmenpro.de/config
📁 /database     → /w019e3c7/firmenpro.de/database
📁 /templates    → /w019e3c7/firmenpro.de/templates
📁 /lang         → /w019e3c7/firmenpro.de/lang
📁 /routes       → /w019e3c7/firmenpro.de/routes
📁 /scripts      → /w019e3c7/firmenpro.de/scripts
📄 composer.json → /w019e3c7/firmenpro.de/composer.json
```

### 2. Verzeichnisse erstellen

Erstellen Sie folgende leere Verzeichnisse auf dem Server:
```
/w019e3c7/firmenpro.de/cache     (Berechtigung: 755)
/w019e3c7/firmenpro.de/logs      (Berechtigung: 755)
/w019e3c7/firmenpro.de/uploads   (Berechtigung: 755)
/w019e3c7/firmenpro.de/temp      (Berechtigung: 755)
/w019e3c7/firmenpro.de/backups   (Berechtigung: 755)
```

### 3. Datenbank einrichten

1. Loggen Sie sich in phpMyAdmin ein (über All-Inkl KAS)
2. Wählen Sie Datenbank `d0446399`
3. Importieren Sie: `/database/migrations/create_all_tables.sql`

### 4. Admin-Passwort setzen

Nach dem SQL-Import, führen Sie folgendes SQL aus:
```sql
UPDATE users 
SET password = '$2y$12$8K1J8kG6nW5qV2oJHhPqOuEgMBZYr4vGxN5LmNwKfQzXhH7dG8qXK' 
WHERE email = 'admin@firmenpro.de';
```

**Login-Daten:**
- E-Mail: admin@firmenpro.de
- Passwort: Admin2025!

### 5. Cron-Jobs einrichten (All-Inkl KAS)

Fügen Sie folgende Cron-Jobs hinzu:

**Alle 5 Minuten:**
```bash
php /www/htdocs/w019e3c7/firmenpro.de/scripts/monitor-health.php
```

**Alle 15 Minuten:**
```bash
php /www/htdocs/w019e3c7/firmenpro.de/scripts/i18n-monitor.php
php /www/htdocs/w019e3c7/firmenpro.de/scripts/api-limit-monitor.php
```

**Täglich um 3:00 Uhr:**
```bash
php /www/htdocs/w019e3c7/firmenpro.de/scripts/maintenance-scheduler.php daily
```

**Wöchentlich Sonntags 3:00 Uhr:**
```bash
php /www/htdocs/w019e3c7/firmenpro.de/scripts/maintenance-scheduler.php weekly
```

### 6. SSL aktivieren

Im All-Inkl KAS:
1. Domain-Verwaltung → firmenpro.de
2. SSL-Zertifikat → Let's Encrypt aktivieren
3. HTTPS-Weiterleitung aktivieren

### 7. Erste Tests

1. Öffnen Sie https://firmenpro.de
2. Melden Sie sich mit admin@firmenpro.de an
3. Prüfen Sie:
   - Dashboard lädt korrekt
   - Sprachumschaltung funktioniert (DE/EN/TR)
   - Neue Playlist erstellen funktioniert
   - Google Maps wird angezeigt

### 8. E-Mail-Konfiguration (später)

Wenn Sie E-Mail-Versand aktivieren möchten:
1. Bearbeiten Sie `/config/production.php`
2. Ändern Sie mail.enabled auf `true`
3. Konfigurieren Sie SMTP-Einstellungen

### 9. Wichtige Sicherheitshinweise

- **Ändern Sie das Admin-Passwort** nach dem ersten Login
- **Löschen Sie** `/scripts/deploy-to-allinkl.php` nach dem Upload
- **Prüfen Sie** Datei-Berechtigungen (644 für Dateien, 755 für Verzeichnisse)
- **Aktivieren Sie** 2FA für Admin-Accounts (in Einstellungen)

## Troubleshooting

**Weißer Bildschirm?**
- Prüfen Sie `/logs/php_errors.log`
- Stellen Sie sicher, dass PHP 8.4 aktiv ist

**Datenbank-Fehler?**
- Prüfen Sie Zugangsdaten in `/config/production.php`
- Stellen Sie sicher, dass alle Tabellen erstellt wurden

**Google Maps funktioniert nicht?**
- Prüfen Sie API-Key in `/config/production.php`
- Aktivieren Sie Maps JavaScript API in Google Cloud Console

## Support

Bei Problemen prüfen Sie:
- Monitoring-Dashboard: https://firmenpro.de/monitoring-dashboard.php?token=maintenance_token_123
- API-Limits: https://firmenpro.de/api-limits
- System-Logs: `/logs/` Verzeichnis

---
Entwickelt von 2Brands Media GmbH