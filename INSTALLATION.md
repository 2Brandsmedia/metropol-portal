# Metropol Portal - Installationsanleitung

## Schnellstart (5 Minuten)

### 1. Dateien hochladen
Laden Sie den kompletten Ordner `metropol-portal` per FTP auf Ihren Webspace hoch.

### 2. Installation starten
Öffnen Sie in Ihrem Browser:
```
https://ihre-domain.de/metropol-portal/install.php
```

### 3. Installationsassistent folgen
Der Assistent führt Sie durch 6 einfache Schritte:

1. **Sprache wählen** - Deutsch, Englisch oder Türkisch
2. **Systemprüfung** - Automatische Prüfung aller Anforderungen
3. **Datenbank** - Zugangsdaten eingeben (erhalten Sie von Ihrem Hoster)
4. **Administrator** - Ihr Admin-Account für die Anmeldung
5. **Konfiguration** - Basis-Einstellungen und optionale API-Keys
6. **Installation** - Automatische Einrichtung

### 4. Fertig!
Nach erfolgreicher Installation:
- Der Installer löscht sich automatisch
- Sie werden zum Login weitergeleitet
- Melden Sie sich mit Ihrem Admin-Account an

## Systemanforderungen

### Mindestanforderungen
- **PHP** 8.1 oder höher
- **MySQL** 5.7 oder höher / MariaDB 10.3+
- **Webserver**: Apache mit mod_rewrite
- **PHP-Extensions**: PDO, PDO_MySQL, JSON, mbstring, curl, zip

### Empfohlen
- PHP 8.3
- MySQL 8.0
- 128 MB PHP Memory Limit
- OPcache aktiviert

## Detaillierte Anleitung

### Schritt 1: Vorbereitung

#### Bei All-Inkl Hosting:
1. Loggen Sie sich ins KAS ein
2. Erstellen Sie eine neue MySQL-Datenbank
3. Notieren Sie sich:
   - Datenbankname
   - Benutzername
   - Passwort
   - Host (meist localhost)

### Schritt 2: Upload

1. Entpacken Sie `metropol-portal-v1.0.0.zip`
2. Verbinden Sie sich per FTP mit Ihrem Server
3. Laden Sie den kompletten Ordner hoch
4. Stellen Sie sicher, dass die Dateiberechtigungen korrekt sind:
   - Ordner: 755
   - Dateien: 644

### Schritt 3: Installation

1. Öffnen Sie `https://ihre-domain.de/metropol-portal/install.php`
2. **Sprache**: Wählen Sie Ihre bevorzugte Sprache
3. **Systemcheck**: Alle Punkte sollten grün sein
4. **Datenbank**: 
   - Host: `localhost` (oder wie vom Hoster angegeben)
   - Port: `3306` (Standard)
   - Datenbankname: Wie in Schritt 1 erstellt
   - Benutzername: Wie in Schritt 1 erstellt
   - Passwort: Wie in Schritt 1 notiert
5. **Administrator**:
   - Wählen Sie einen sicheren Benutzernamen
   - Verwenden Sie ein starkes Passwort (min. 8 Zeichen)
   - Geben Sie eine gültige E-Mail-Adresse an
6. **Konfiguration**:
   - Site-Name: Name Ihres Portals
   - Zeitzone: Ihre lokale Zeitzone
   - API-Keys: Optional, können später ergänzt werden

### Schritt 4: Nach der Installation

- Der Installer wird automatisch deaktiviert
- Die `.env` Datei wird mit sicheren Berechtigungen erstellt
- Alle Datenbanktabellen werden angelegt

## API-Keys einrichten (Optional)

### OpenRouteService (Kostenlos)
1. Registrieren auf https://openrouteservice.org
2. API-Key generieren
3. Im Portal unter Einstellungen eintragen

### Google Maps (Kostenpflichtig)
1. Google Cloud Console öffnen
2. Maps JavaScript API aktivieren
3. API-Key erstellen und einschränken
4. Im Portal unter Einstellungen eintragen

## Fehlerbehebung

### "Datenbankverbindung fehlgeschlagen"
- Prüfen Sie die Zugangsdaten
- Stellen Sie sicher, dass die Datenbank existiert
- Kontaktieren Sie ggf. Ihren Hoster

### "Schreibrechte fehlen"
- Setzen Sie die Ordnerberechtigungen auf 755
- Das Hauptverzeichnis muss beschreibbar sein

### "PHP-Version zu alt"
- Wechseln Sie im Hosting-Panel zu PHP 8.1+
- Bei All-Inkl: KAS → Einstellungen → PHP-Version

## Sicherheitshinweise

- Ändern Sie regelmäßig Ihr Admin-Passwort
- Erstellen Sie regelmäßige Backups
- Halten Sie PHP und MySQL aktuell
- Verwenden Sie HTTPS (SSL-Zertifikat)

## Support

Bei Problemen wenden Sie sich an:
- **E-Mail**: support@2brands-media.de
- **Dokumentation**: docs.metropol-portal.de

---

Entwickelt von **2Brands Media GmbH**