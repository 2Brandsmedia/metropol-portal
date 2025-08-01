# API Limit Management System

**Entwickelt von**: 2Brands Media GmbH  
**Version**: 1.0.0  
**Erstellt**: Januar 2025

## Überblick

Das API Limit Management System für das Metropol Portal bietet umfassende Überwachung und intelligente Limitierung aller externen API-Aufrufe. Das System verhindert proaktiv Überschreitungen von API-Limits und bietet benutzerfreundliche Fallback-Strategien.

## Kernfunktionen

### 1. API-Überwachung
- **Echtzeit-Tracking** aller API-Anfragen
- **Progressive Warnsysteme** (80% gelb, 90% rot, 95% blockieren)
- **Detaillierte Metriken** (Response-Zeit, Fehlerrate, Kosten)
- **Historische Datenanalyse** für Trend-Erkennung

### 2. Intelligente Limits
- **Google Maps API**: 25.000 Anfragen/Tag
- **Nominatim OSM**: 1 Anfrage/Sekunde (86.400/Tag)
- **OpenRouteService**: 2.000 Anfragen/Tag (Free Plan)

### 3. Fallback-Strategien
- **Cache-Only**: Verwendung gespeicherter Daten
- **Alternative APIs**: Wechsel zu anderen Anbietern
- **Degraded Service**: Reduzierte Funktionalität
- **Graceful Degradation**: Benutzerfreundliche Fehlermeldungen

### 4. Admin-Dashboard
- **Real-time Monitoring** aller APIs
- **Interaktive Charts** für Nutzungstrends
- **Kostenanalyse** und Budgetüberwachung
- **Admin-Aktionen** (Limits zurücksetzen, Konfiguration)

## Architektur

### Komponenten

```
APILimitAgent (Hauptsteuerung)
├── APIUsageTracker (Tracking & Statistiken)
├── APIFallbackService (Fallback-Strategien)
├── ApiDashboardController (Admin-Interface)
└── Database (Persistierung)
```

### Datenbank-Schema

**Haupttabellen:**
- `api_usage` - Nutzungsstatistiken (stündlich/täglich)
- `api_warnings` - Limit-Warnungen und Alerts
- `api_errors` - Detaillierte Fehlerprotokolle
- `api_rate_limits` - Rate-Limiting-Status
- `route_history` / `address_history` - User-History für Prefetching

### Integration in bestehende Agents

Alle API-calls durchlaufen automatisch das Limit-System:

```php
// Beispiel: GoogleMapsAgent
private function makeApiRequest(string $endpoint, array $params): array
{
    // 1. Limit-Check
    $limitCheck = $this->limitAgent->checkApiRequest(
        APIUsageTracker::API_GOOGLE_MAPS, 
        'routing'
    );
    
    // 2. Fallback wenn blockiert
    if (!$limitCheck['allowed']) {
        return $this->executeFallback($limitCheck);
    }
    
    // 3. Request ausführen
    $response = $this->performRequest($endpoint, $params);
    
    // 4. Erfolg/Fehler tracken
    $this->limitAgent->trackRequest($endpoint, $success, $responseTime);
    
    return $response;
}
```

## Konfiguration

### API-Limits anpassen

```json
{
    "api_limits": {
        "google_maps": {
            "daily": 25000,
            "hourly": 2500,
            "per_second": 50,
            "cost_per_request": 0.005
        },
        "nominatim": {
            "daily": 86400,
            "hourly": 3600,
            "per_second": 1,
            "cost_per_request": 0.0
        }
    }
}
```

### Warnschwellen konfigurieren

```php
// In APILimitAgent.php
public const WARNING_YELLOW = 0.8; // 80%
public const WARNING_RED = 0.9;    // 90%
public const BLOCK_LEVEL = 0.95;   // 95%
```

### E-Mail-Benachrichtigungen

```php
// config/app.php
'admin' => [
    'emails' => [
        'admin@metropol-portal.de',
        'tech@2brands-media.de'
    ]
]
```

## Installation

### 1. Datenbank-Migrationen ausführen

```sql
mysql -u root -p metropol_portal < database/migrations/create_api_monitoring_tables.sql
```

### 2. Composer-Dependencies installieren

```bash
composer install
```

### 3. Konfiguration anpassen

```bash
cp config/api_limits.example.json config/api_limits.json
# Limits nach Bedarf anpassen
```

### 4. Cron-Jobs einrichten

```bash
# Täglich um 00:30 - Alte Daten bereinigen
30 0 * * * /usr/bin/php /path/to/cleanup_api_data.php

# Alle 5 Minuten - Cache aufwärmen
*/5 * * * * /usr/bin/php /path/to/warmup_cache.php
```

## Monitoring & Alerts

### Dashboard-Zugriff

```
https://metropol-portal.de/admin/api-dashboard
```

**Berechtigung**: Admin-Rolle erforderlich

### Alert-Arten

1. **Yellow Warning (80%)**
   - E-Mail an Admins
   - User-Benachrichtigung über verlangsamte Performance
   - Erhöhtes Cache-Prefetching

2. **Red Alert (90%)**
   - Sofortige E-Mail an alle Admins
   - Benutzer-Warnungen über eingeschränkte Funktionen
   - Automatische Fallback-Aktivierung

3. **Blocked (95%)**
   - Kritische Alert-E-Mail
   - API vollständig blockiert
   - Nur Fallback-Services verfügbar

### Metriken

**Performance-Kennzahlen:**
- Durchschnittliche Response-Zeit
- Fehlerrate pro API
- Cache-Hit-Rate
- Kostenanalyse

**Geschäftskennzahlen:**
- Tägliche/monatliche API-Kosten
- Nutzungstrends
- Effizienz-Bewertung
- ROI-Analysen

## Fallback-Strategien im Detail

### 1. Cache-Only Fallback
- **Anwendung**: Geocoding, häufige Routen
- **Qualität**: 70-90% (abhängig von Cache-Alter)
- **Performance**: Sehr schnell (<50ms)

### 2. Alternative APIs
- **Google Maps → OpenRouteService**
- **Nominatim → Google Geocoding** (wenn verfügbar)
- **Qualität**: 80-95%
- **Performance**: Normal (200-500ms)

### 3. Degraded Service
- **Funktionen**: Basis-Routing ohne Live-Traffic
- **Qualität**: 60-70%
- **Performance**: Schnell (100-200ms)

### 4. Airline Distance
- **Anwendung**: Notfall-Routing
- **Qualität**: 30% (nur Luftlinie)
- **Performance**: Sehr schnell (<10ms)

## Benutzer-Experience

### Buffer-Messages

Das System zeigt kontextuelle Nachrichten basierend auf:
- **API-Status** (verfügbar/verlangsamt/blockiert)
- **Kontext** (Routing/Geocoding/Maps)
- **User-Rolle** (Admin/Standard-User)

**Beispiel-Messages:**

```
🟡 Gelbe Warnung:
"Unser System verarbeitet aktuell viele Anfragen. 
Ihre Route wird möglicherweise etwas langsamer berechnet."

🔴 Rote Warnung:
"Live-Verkehrsdaten sind momentan nicht verfügbar. 
Wir zeigen Ihnen die beste geschätzte Route."

🚫 Blockiert:
"Die Routenberechnung ist temporär nicht verfügbar. 
Nutzen Sie alternative Navigationshilfen."
```

### Progressive Enhancement

1. **Normal**: Alle Features verfügbar
2. **Throttled**: Verzögerte Responses, alle Features
3. **Limited**: Reduzierte Features, Cache-bevorzugt
4. **Emergency**: Nur kritische Funktionen, Fallbacks

## API-Dokumentation

### Endpunkte

#### Admin Dashboard
```
GET  /admin/api-dashboard          # Dashboard-Übersicht
GET  /api/dashboard/stats          # JSON-Statistiken
GET  /api/dashboard/history        # Historische Daten
POST /api/dashboard/reset-limits   # Limits zurücksetzen
POST /api/dashboard/update-limits  # Limits aktualisieren
```

#### Status-Abfragen
```
GET /api/status/{provider}         # API-Status abrufen
GET /api/dashboard/alerts          # Aktuelle Alerts
```

### Response-Formate

#### Limit-Check Response
```json
{
    "allowed": true,
    "warning_level": "yellow",
    "user_message": {
        "type": "warning",
        "title": "Hohe Systemlast",
        "message": "Routenberechnung kann verzögert sein",
        "action": "Bitte haben Sie Geduld"
    },
    "usage_stats": {
        "daily_requests": 20000,
        "daily_errors": 45,
        "hourly_requests": 1800
    },
    "retry_after": null
}
```

## Wartung & Optimierung

### Regelmäßige Aufgaben

**Täglich:**
- Alte API-Logs bereinigen (>14 Tage)
- Cache-Optimierung
- Kostenkontrolle

**Wöchentlich:**
- Performance-Analyse
- Limit-Optimierung
- Fallback-Qualität bewerten

**Monatlich:**
- API-Kosten-Review
- Nutzungstrend-Analyse
- Kapazitätsplanung

### Performance-Optimierung

1. **Database-Indizes** optimieren
2. **Cache-Strategien** verfeinern
3. **Prefetching** basierend auf User-Mustern
4. **Rate-Limiting** fine-tunen

### Troubleshooting

#### Häufige Probleme

**Problem**: API plötzlich blockiert
**Lösung**: 
1. Dashboard prüfen → Nutzungsspitzen identifizieren
2. Cache-Hit-Rate erhöhen
3. Temporär Limits erhöhen
4. Prefetching optimieren

**Problem**: Fallback-Qualität zu niedrig
**Lösung**:
1. Cache-Strategien verbessern
2. Alternative APIs konfigurieren
3. User-Education für bessere Cache-Nutzung

**Problem**: Hohe API-Kosten
**Lösung**:
1. Unnötige Requests identifizieren
2. Cache-Lifetime erhöhen
3. Batch-Processing implementieren
4. User-Verhalten analysieren

## Sicherheit

### Datenschutz
- **DSGVO-konform**: Keine persönlichen Daten in API-Logs
- **Anonymisierung**: IP-Adressen werden gehashed
- **Retention**: Automatische Löschung alter Daten

### API-Keys
- **Verschlüsselte Speicherung** aller API-Keys
- **Rotation**: Regelmäßiger Key-Wechsel
- **Monitoring**: Verdächtige Nutzungsmuster erkennen

### Rate-Limiting
- **Brute-Force-Schutz** gegen API-Missbrauch
- **User-basiertes Limiting** für faire Nutzung
- **IP-basiertes Limiting** als Backup

## Support & Weiterentwicklung

### Version 1.1 (geplant)
- **Machine Learning** für Nutzungsvorhersagen
- **Auto-Scaling** der Limits basierend auf Mustern
- **Advanced Analytics** mit Predictive Models
- **Multi-Region** Fallback-Support

### Kontakt
- **E-Mail**: tech@2brands-media.de
- **Dokumentation**: [Interne Wiki]
- **Issue-Tracking**: [Internes System]

---

**© 2025 2Brands Media GmbH. Alle Rechte vorbehalten.**

*Dieses System wurde speziell für das Metropol Portal entwickelt und implementiert modernste API-Management-Praktiken für optimale Performance und Kosteneffizienz.*