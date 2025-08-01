# SSL-Zertifikat Problem beheben - firmenpro.de

## 🔐 Problem

Das aktuelle SSL-Zertifikat ist für `*.kasserver.com` ausgestellt, nicht für `firmenpro.de`.

**Fehlermeldung:**
```
Hostname/IP does not match certificate's altnames: 
Host: firmenpro.de. is not in the cert's altnames: 
DNS:*.kasserver.com, DNS:kasserver.com
```

## 🛠️ Lösung im All-Inkl KAS

### Option 1: Let's Encrypt (Empfohlen)

1. **Login ins KAS** (Kundenadministrationssystem)
   - https://kas.all-inkl.com

2. **Domain-Verwaltung**
   - Tools → Domain
   - firmenpro.de auswählen

3. **SSL-Schutz aktivieren**
   - SSL-Schutz → Bearbeiten
   - "Let's Encrypt" auswählen
   - ✅ "SSL erzwingen (Weiterleitung)" aktivieren
   - Speichern

4. **Warten**
   - Zertifikat wird automatisch generiert (5-15 Minuten)
   - Automatische Verlängerung alle 90 Tage

### Option 2: Eigenes SSL-Zertifikat

Falls Sie bereits ein SSL-Zertifikat haben:

1. **SSL-Verwaltung öffnen**
   - Tools → SSL-Schutz
   - "Eigenes SSL-Zertifikat" wählen

2. **Zertifikat hochladen**
   - Zertifikat (CRT/PEM)
   - Privater Schlüssel (KEY)
   - Zwischenzertifikat (CA Bundle)

3. **Domain zuweisen**
   - firmenpro.de auswählen
   - Speichern

## 📝 .htaccess Anpassungen

Nach SSL-Aktivierung in der `.htaccess`:

```apache
# HTTPS erzwingen (nach SSL-Aktivierung)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# HSTS Header (optional aber empfohlen)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

## ⏱️ Temporäre Lösung

Bis das SSL-Zertifikat aktiv ist:

1. **HTTP-only Betrieb**
   ```apache
   # In .htaccess - HTTPS-Redirect deaktivieren
   # RewriteCond %{HTTPS} off
   # RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
   ```

2. **Environment anpassen**
   ```env
   # In .env
   APP_URL=http://firmenpro.de
   FORCE_HTTPS=false
   SESSION_SECURE=false
   ```

## ✅ Verifizierung

Nach SSL-Aktivierung prüfen:

1. **Browser-Test**
   - https://firmenpro.de aufrufen
   - Grünes Schloss-Symbol?
   - Keine Zertifikat-Warnung?

2. **SSL-Checker**
   - https://www.ssllabs.com/ssltest/
   - Domain eingeben und testen

3. **Zertifikat-Details**
   ```bash
   openssl s_client -connect firmenpro.de:443 -servername firmenpro.de
   ```

## 🚨 Wichtige Hinweise

- **Mixed Content vermeiden**: Nach SSL-Aktivierung alle Ressourcen über HTTPS laden
- **301-Redirects**: Suchmaschinen über permanente Weiterleitung informieren
- **HSTS**: Erst aktivieren wenn SSL stabil läuft
- **Monitoring**: SSL-Ablauf überwachen (Let's Encrypt erneuert automatisch)

## 📞 Support

Bei Problemen mit SSL im All-Inkl:
- KAS Support-Ticket öffnen
- Kategorie: "SSL/TLS"
- Domain: firmenpro.de angeben

---

**Tipp**: Let's Encrypt ist kostenlos und erneuert sich automatisch - ideal für die meisten Projekte!