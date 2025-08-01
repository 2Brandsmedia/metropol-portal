# Traffic-Daten Migration

## Überblick

Die Migration `018_add_traffic_data_to_routes.php` fügt Traffic-Unterstützung zum Metropol Portal hinzu.

## Neue Features

### 1. Traffic-Daten in Echtzeit
- Live-Verkehrsinformationen von Google Maps
- Verzögerungen und Schweregrad pro Segment
- Automatische Updates bei Routenberechnung

### 2. Neue Datenbankfelder

#### Tabelle `playlists`:
- `total_travel_time_in_traffic` - Gesamtfahrzeit mit Verkehr
- `total_traffic_delay` - Gesamtverzögerung durch Verkehr
- `overall_traffic_severity` - Gesamt-Verkehrslage (low/medium/high/severe)
- `last_traffic_update` - Zeitstempel des letzten Updates

#### Tabelle `stops`:
- `travel_duration_in_traffic` - Fahrzeit zum nächsten Stopp mit Verkehr
- `traffic_delay` - Verzögerung in Sekunden
- `traffic_status` - Verkehrsstatus für dieses Segment

#### Tabelle `route_optimizations`:
- `traffic_delay` - Verkehrsverzögerung bei Optimierung
- `traffic_data` - Detaillierte Traffic-Informationen (JSON)
- `traffic_severity` - Schweregrad der Verkehrslage

### 3. Neue Tabellen

#### `traffic_history`:
Speichert historische Traffic-Daten für Analysen:
- Verkehrsdaten pro Playlist über Zeit
- Normale vs. tatsächliche Fahrzeiten
- Segment-Details als JSON

#### `traffic_alerts`:
Verkehrswarnungen und Ereignisse:
- Unfälle, Staus, Baustellen etc.
- Schweregrad und geschätzte Verzögerungen
- Zeiträume und betroffene Bereiche

## Migration ausführen

```bash
cd /path/to/metropol-portal
php database/migrate.php
```

## Rollback

Falls nötig, kann die Migration rückgängig gemacht werden:

```bash
php database/migrate.php rollback
```

## Nach der Migration

1. **Cache leeren**: Der Application-Cache sollte geleert werden
2. **Tests ausführen**: Alle Traffic-bezogenen Features testen
3. **Monitoring**: Traffic-History überwachen

## Wichtige Hinweise

- Die Migration ist abwärtskompatibel - bestehende Funktionen bleiben erhalten
- Traffic-Daten werden nur gespeichert wenn Google Maps aktiv ist
- History-Daten können für Analysen verwendet werden (z.B. beste Fahrzeiten)

---

**Entwickelt von**: 2Brands Media GmbH