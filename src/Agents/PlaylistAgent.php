<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Core\Config;
use Exception;
use PDO;

/**
 * PlaylistAgent - Verwaltung der Tagesrouten
 * 
 * Erfolgskriterium: Änderungen ohne Fehler persistiert
 * 
 * @author 2Brands Media GmbH
 */
class PlaylistAgent
{
    private Database $db;
    private Config $config;
    private ?GeoAgent $geoAgent = null;
    private ?RouteAgent $routeAgent = null;
    private const MAX_STOPS_PER_PLAYLIST = 20;
    private const MIN_WORK_DURATION = 5; // Minuten
    private const MAX_WORK_DURATION = 480; // 8 Stunden

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * GeoAgent Setter für Dependency Injection
     */
    public function setGeoAgent(GeoAgent $geoAgent): void
    {
        $this->geoAgent = $geoAgent;
    }
    
    /**
     * RouteAgent Setter für Dependency Injection
     */
    public function setRouteAgent(RouteAgent $routeAgent): void
    {
        $this->routeAgent = $routeAgent;
    }

    /**
     * Erstellt eine neue Playlist
     */
    public function createPlaylist(array $data): array
    {
        // Validierung
        $this->validatePlaylistData($data);
        
        // Prüfen ob bereits eine Playlist für diesen Tag existiert
        $existing = $this->db->selectOne(
            'SELECT id FROM playlists WHERE user_id = ? AND date = ?',
            [$data['user_id'], $data['date']]
        );
        
        if ($existing) {
            throw new Exception('Es existiert bereits eine Route für diesen Tag');
        }
        
        // Playlist erstellen
        $id = $this->db->insert(
            'INSERT INTO playlists (user_id, date, name, status) VALUES (?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['date'],
                $data['name'],
                $data['status'] ?? 'draft'
            ]
        );
        
        return $this->getPlaylist($id);
    }

    /**
     * Aktualisiert eine Playlist
     */
    public function updatePlaylist(int $id, array $data): array
    {
        // Playlist prüfen
        $playlist = $this->getPlaylist($id);
        if (!$playlist) {
            throw new Exception('Playlist nicht gefunden');
        }
        
        // Nur erlaubte Felder aktualisieren
        $allowedFields = ['name', 'status'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $playlist;
        }
        
        $params[] = $id;
        
        $this->db->update(
            "UPDATE playlists SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );
        
        return $this->getPlaylist($id);
    }

    /**
     * Löscht eine Playlist
     */
    public function deletePlaylist(int $id): bool
    {
        return $this->db->delete(
            'DELETE FROM playlists WHERE id = ?',
            [$id]
        ) > 0;
    }

    /**
     * Holt eine Playlist mit allen Stopps
     */
    public function getPlaylist(int $id): ?array
    {
        $playlist = $this->db->selectOne(
            'SELECT p.*, u.name as user_name 
             FROM playlists p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.id = ?',
            [$id]
        );
        
        if (!$playlist) {
            return null;
        }
        
        // Stopps laden
        $playlist['stops'] = $this->getStops($id);
        
        return $playlist;
    }

    /**
     * Holt alle Playlists eines Benutzers
     */
    public function getUserPlaylists(int $userId, ?string $date = null): array
    {
        $query = 'SELECT * FROM playlists WHERE user_id = ?';
        $params = [$userId];
        
        if ($date) {
            $query .= ' AND date = ?';
            $params[] = $date;
        }
        
        $query .= ' ORDER BY date DESC';
        
        return $this->db->select($query, $params);
    }

    /**
     * Fügt einen Stopp hinzu
     */
    public function addStop(int $playlistId, array $data): array
    {
        // Playlist prüfen
        $playlist = $this->getPlaylist($playlistId);
        if (!$playlist) {
            throw new Exception('Playlist nicht gefunden');
        }
        
        // Max. Anzahl prüfen
        if (count($playlist['stops']) >= self::MAX_STOPS_PER_PLAYLIST) {
            throw new Exception('Maximale Anzahl von ' . self::MAX_STOPS_PER_PLAYLIST . ' Stopps erreicht');
        }
        
        // Validierung
        $this->validateStopData($data);
        
        // Nächste Position ermitteln
        $nextPosition = $this->db->selectOne(
            'SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM stops WHERE playlist_id = ?',
            [$playlistId]
        )['next_pos'];
        
        // Geokodierung durchführen
        $coordinates = null;
        if ($this->geoAgent) {
            $geoResult = $this->geoAgent->geocode($data['address']);
            if ($geoResult) {
                $coordinates = [
                    'latitude' => $geoResult['latitude'],
                    'longitude' => $geoResult['longitude']
                ];
            }
        }
        
        // Stopp einfügen
        $stopId = $this->db->insert(
            'INSERT INTO stops (playlist_id, position, address, latitude, longitude, work_duration, notes) 
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $playlistId,
                $data['position'] ?? $nextPosition,
                $data['address'],
                $coordinates['latitude'] ?? null,
                $coordinates['longitude'] ?? null,
                $data['work_duration'],
                $data['notes'] ?? null
            ]
        );
        
        // Playlist-Statistiken aktualisieren
        $this->updatePlaylistStats($playlistId);
        
        return $this->getStop($stopId);
    }

    /**
     * Aktualisiert einen Stopp
     */
    public function updateStop(int $stopId, array $data): array
    {
        $stop = $this->getStop($stopId);
        if (!$stop) {
            throw new Exception('Stopp nicht gefunden');
        }
        
        // Validierung
        if (isset($data['work_duration'])) {
            $this->validateWorkDuration($data['work_duration']);
        }
        
        // Erlaubte Felder
        $allowedFields = ['address', 'work_duration', 'notes', 'status'];
        $updates = [];
        $params = [];
        
        // Bei Adressänderung neu geokodieren
        if (isset($data['address']) && $data['address'] !== $stop['address']) {
            if ($this->geoAgent) {
                $geoResult = $this->geoAgent->geocode($data['address']);
                if ($geoResult) {
                    $data['latitude'] = $geoResult['latitude'];
                    $data['longitude'] = $geoResult['longitude'];
                    $allowedFields[] = 'latitude';
                    $allowedFields[] = 'longitude';
                }
            }
        }
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $stop;
        }
        
        // Status-Änderungen tracken
        if (isset($data['status'])) {
            if ($data['status'] === 'in_progress' && !$stop['started_at']) {
                $updates[] = "started_at = NOW()";
            } elseif ($data['status'] === 'completed' && !$stop['completed_at']) {
                $updates[] = "completed_at = NOW()";
            }
        }
        
        $params[] = $stopId;
        
        $this->db->update(
            "UPDATE stops SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );
        
        // Playlist-Statistiken aktualisieren
        $this->updatePlaylistStats($stop['playlist_id']);
        
        return $this->getStop($stopId);
    }

    /**
     * Löscht einen Stopp
     */
    public function deleteStop(int $stopId): bool
    {
        $stop = $this->getStop($stopId);
        if (!$stop) {
            return false;
        }
        
        $playlistId = $stop['playlist_id'];
        
        // Stopp löschen
        $deleted = $this->db->delete(
            'DELETE FROM stops WHERE id = ?',
            [$stopId]
        ) > 0;
        
        if ($deleted) {
            // Positionen neu nummerieren
            $this->reorderPositions($playlistId);
            
            // Playlist-Statistiken aktualisieren
            $this->updatePlaylistStats($playlistId);
        }
        
        return $deleted;
    }

    /**
     * Ändert die Reihenfolge der Stopps
     */
    public function reorderStops(int $playlistId, array $stopIds): bool
    {
        // Transaktion starten
        $this->db->beginTransaction();
        
        try {
            // Alle Stopps der Playlist prüfen
            $existingStops = $this->db->select(
                'SELECT id FROM stops WHERE playlist_id = ?',
                [$playlistId]
            );
            
            $existingIds = array_column($existingStops, 'id');
            
            // Prüfen ob alle IDs vorhanden sind
            if (count($stopIds) !== count($existingIds) || 
                count(array_diff($stopIds, $existingIds)) > 0) {
                throw new Exception('Ungültige Stopp-IDs');
            }
            
            // Neue Positionen setzen
            foreach ($stopIds as $position => $stopId) {
                $this->db->update(
                    'UPDATE stops SET position = ? WHERE id = ? AND playlist_id = ?',
                    [$position + 1, $stopId, $playlistId]
                );
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Holt alle Stopps einer Playlist
     */
    private function getStops(int $playlistId): array
    {
        return $this->db->select(
            'SELECT * FROM stops 
             WHERE playlist_id = ? 
             ORDER BY position ASC',
            [$playlistId]
        );
    }

    /**
     * Holt einen einzelnen Stopp
     */
    private function getStop(int $stopId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM stops WHERE id = ?',
            [$stopId]
        );
    }

    /**
     * Aktualisiert Playlist-Statistiken
     */
    private function updatePlaylistStats(int $playlistId): void
    {
        // Stopp-Statistiken berechnen
        $stats = $this->db->selectOne(
            'SELECT 
                COUNT(*) as total_stops,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_stops,
                SUM(work_duration) as estimated_duration
             FROM stops 
             WHERE playlist_id = ?',
            [$playlistId]
        );
        
        // Playlist aktualisieren
        $this->db->update(
            'UPDATE playlists 
             SET total_stops = ?, 
                 completed_stops = ?, 
                 estimated_duration = ?
             WHERE id = ?',
            [
                $stats['total_stops'],
                $stats['completed_stops'],
                $stats['estimated_duration'] ?? 0,
                $playlistId
            ]
        );
        
        // Status automatisch auf 'completed' setzen wenn alle Stopps erledigt
        if ($stats['total_stops'] > 0 && $stats['total_stops'] == $stats['completed_stops']) {
            $this->db->update(
                'UPDATE playlists SET status = "completed" WHERE id = ?',
                [$playlistId]
            );
        }
    }

    /**
     * Nummeriert Positionen neu
     */
    private function reorderPositions(int $playlistId): void
    {
        $stops = $this->db->select(
            'SELECT id FROM stops WHERE playlist_id = ? ORDER BY position',
            [$playlistId]
        );
        
        foreach ($stops as $index => $stop) {
            $this->db->update(
                'UPDATE stops SET position = ? WHERE id = ?',
                [$index + 1, $stop['id']]
            );
        }
    }
    
    /**
     * Berechnet Route für Playlist
     */
    public function calculateRoute(int $playlistId, array $options = []): array
    {
        if (!$this->routeAgent) {
            throw new Exception('RouteAgent nicht verfügbar');
        }
        
        try {
            // Route berechnen
            $result = $this->routeAgent->calculateRoute($playlistId, $options);
            
            // Statistiken aktualisieren
            $this->updatePlaylistStats($playlistId);
            
            return $result;
        } catch (Exception $e) {
            error_log("Routenberechnung fehlgeschlagen: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Optimiert Route für Playlist
     */
    public function optimizeRoute(int $playlistId, array $options = []): array
    {
        if (!$this->routeAgent) {
            throw new Exception('RouteAgent nicht verfügbar');
        }
        
        try {
            // Route optimieren
            $result = $this->routeAgent->optimizeRoute($playlistId, $options);
            
            // Bei erfolgreicher Optimierung Stopps neu sortieren
            if (!empty($result['optimized_order'])) {
                $this->applyOptimizedOrder($playlistId, $result['optimized_order']);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Routenoptimierung fehlgeschlagen: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Wendet optimierte Reihenfolge an
     */
    private function applyOptimizedOrder(int $playlistId, array $optimizedOrder): void
    {
        $stops = $this->getStops($playlistId);
        
        if (count($stops) !== count($optimizedOrder)) {
            throw new Exception('Optimierte Reihenfolge passt nicht zur Anzahl der Stopps');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Neue Positionen setzen
            foreach ($optimizedOrder as $newPos => $oldPos) {
                if (isset($stops[$oldPos])) {
                    $this->db->update(
                        'UPDATE stops SET position = ? WHERE id = ?',
                        [$newPos + 1, $stops[$oldPos]['id']]
                    );
                }
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Validiert Playlist-Daten
     */
    private function validatePlaylistData(array $data): void
    {
        if (empty($data['user_id'])) {
            throw new Exception('Benutzer-ID erforderlich');
        }
        
        if (empty($data['date'])) {
            throw new Exception('Datum erforderlich');
        }
        
        if (empty($data['name'])) {
            throw new Exception('Name erforderlich');
        }
        
        // Datum validieren
        $date = \DateTime::createFromFormat('Y-m-d', $data['date']);
        if (!$date || $date->format('Y-m-d') !== $data['date']) {
            throw new Exception('Ungültiges Datumsformat');
        }
    }

    /**
     * Validiert Stopp-Daten
     */
    private function validateStopData(array $data): void
    {
        if (empty($data['address'])) {
            throw new Exception('Adresse erforderlich');
        }
        
        if (!isset($data['work_duration'])) {
            throw new Exception('Arbeitszeit erforderlich');
        }
        
        $this->validateWorkDuration($data['work_duration']);
    }

    /**
     * Validiert Arbeitszeit
     */
    private function validateWorkDuration($duration): void
    {
        if (!is_numeric($duration) || $duration < self::MIN_WORK_DURATION || $duration > self::MAX_WORK_DURATION) {
            throw new Exception(
                "Arbeitszeit muss zwischen " . self::MIN_WORK_DURATION . 
                " und " . self::MAX_WORK_DURATION . " Minuten liegen"
            );
        }
    }

    /**
     * Klont eine Playlist für einen anderen Tag
     */
    public function clonePlaylist(int $sourceId, string $targetDate, int $userId): array
    {
        $source = $this->getPlaylist($sourceId);
        if (!$source) {
            throw new Exception('Quell-Playlist nicht gefunden');
        }
        
        // Neue Playlist erstellen
        $newPlaylist = $this->createPlaylist([
            'user_id' => $userId,
            'date' => $targetDate,
            'name' => $source['name'] . ' (Kopie)',
            'status' => 'draft'
        ]);
        
        // Stopps kopieren
        foreach ($source['stops'] as $stop) {
            $this->addStop($newPlaylist['id'], [
                'address' => $stop['address'],
                'work_duration' => $stop['work_duration'],
                'notes' => $stop['notes'],
                'position' => $stop['position']
            ]);
        }
        
        return $this->getPlaylist($newPlaylist['id']);
    }
}