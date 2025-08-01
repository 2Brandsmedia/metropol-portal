<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\PlaylistAgent;
use App\Agents\AuthAgent;
use Exception;

/**
 * Playlist Controller - API-Endpunkte für Playlist-Verwaltung
 * 
 * @author 2Brands Media GmbH
 */
class PlaylistController
{
    private PlaylistAgent $playlistAgent;
    private AuthAgent $authAgent;

    public function __construct(PlaylistAgent $playlistAgent, AuthAgent $authAgent)
    {
        $this->playlistAgent = $playlistAgent;
        $this->authAgent = $authAgent;
    }

    /**
     * Listet alle Playlists des aktuellen Benutzers
     * GET /api/playlists
     */
    public function index(Request $request): Response
    {
        $response = new Response();
        
        try {
            $userId = $this->authAgent->id();
            $date = $request->query('date'); // Optional: Filter nach Datum
            
            $playlists = $this->playlistAgent->getUserPlaylists($userId, $date);
            
            return $response->json([
                'success' => true,
                'data' => $playlists
            ]);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Zeigt eine einzelne Playlist mit allen Stopps
     * GET /api/playlists/{id}
     */
    public function show(Request $request, int $id): Response
    {
        $response = new Response();
        
        try {
            $playlist = $this->playlistAgent->getPlaylist($id);
            
            if (!$playlist) {
                return $response->json([
                    'success' => false,
                    'message' => 'Playlist nicht gefunden'
                ], 404);
            }
            
            // Zugriffsprüfung
            if ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin()) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            return $response->json([
                'success' => true,
                'data' => $playlist
            ]);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Erstellt eine neue Playlist
     * POST /api/playlists
     */
    public function create(Request $request): Response
    {
        $response = new Response();
        
        try {
            $data = $request->json();
            $data['user_id'] = $this->authAgent->id();
            
            $playlist = $this->playlistAgent->createPlaylist($data);
            
            return $response->json([
                'success' => true,
                'data' => $playlist,
                'message' => 'Playlist erfolgreich erstellt'
            ], 201);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Aktualisiert eine Playlist
     * PUT /api/playlists/{id}
     */
    public function update(Request $request, int $id): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung
            $playlist = $this->playlistAgent->getPlaylist($id);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $data = $request->json();
            $updated = $this->playlistAgent->updatePlaylist($id, $data);
            
            return $response->json([
                'success' => true,
                'data' => $updated,
                'message' => 'Playlist erfolgreich aktualisiert'
            ]);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Löscht eine Playlist
     * DELETE /api/playlists/{id}
     */
    public function delete(Request $request, int $id): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung
            $playlist = $this->playlistAgent->getPlaylist($id);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $deleted = $this->playlistAgent->deletePlaylist($id);
            
            if ($deleted) {
                return $response->json([
                    'success' => true,
                    'message' => 'Playlist erfolgreich gelöscht'
                ]);
            } else {
                return $response->json([
                    'success' => false,
                    'message' => 'Playlist konnte nicht gelöscht werden'
                ], 400);
            }
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fügt einen Stopp zur Playlist hinzu
     * POST /api/playlists/{id}/stops
     */
    public function addStop(Request $request, int $playlistId): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung
            $playlist = $this->playlistAgent->getPlaylist($playlistId);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $data = $request->json();
            $stop = $this->playlistAgent->addStop($playlistId, $data);
            
            return $response->json([
                'success' => true,
                'data' => $stop,
                'message' => 'Stopp erfolgreich hinzugefügt'
            ], 201);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Aktualisiert einen Stopp
     * PUT /api/playlists/{playlistId}/stops/{stopId}
     */
    public function updateStop(Request $request, int $playlistId, int $stopId): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung über Playlist
            $playlist = $this->playlistAgent->getPlaylist($playlistId);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $data = $request->json();
            $stop = $this->playlistAgent->updateStop($stopId, $data);
            
            return $response->json([
                'success' => true,
                'data' => $stop,
                'message' => 'Stopp erfolgreich aktualisiert'
            ]);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Löscht einen Stopp
     * DELETE /api/playlists/{playlistId}/stops/{stopId}
     */
    public function deleteStop(Request $request, int $playlistId, int $stopId): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung über Playlist
            $playlist = $this->playlistAgent->getPlaylist($playlistId);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $deleted = $this->playlistAgent->deleteStop($stopId);
            
            if ($deleted) {
                return $response->json([
                    'success' => true,
                    'message' => 'Stopp erfolgreich gelöscht'
                ]);
            } else {
                return $response->json([
                    'success' => false,
                    'message' => 'Stopp konnte nicht gelöscht werden'
                ], 400);
            }
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ändert die Reihenfolge der Stopps
     * PUT /api/playlists/{id}/stops/reorder
     */
    public function reorderStops(Request $request, int $playlistId): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung
            $playlist = $this->playlistAgent->getPlaylist($playlistId);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $data = $request->json();
            
            if (!isset($data['stop_ids']) || !is_array($data['stop_ids'])) {
                return $response->json([
                    'success' => false,
                    'message' => 'stop_ids Array erforderlich'
                ], 400);
            }
            
            $this->playlistAgent->reorderStops($playlistId, $data['stop_ids']);
            
            return $response->json([
                'success' => true,
                'message' => 'Reihenfolge erfolgreich geändert'
            ]);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Klont eine Playlist für einen anderen Tag
     * POST /api/playlists/{id}/clone
     */
    public function clonePlaylist(Request $request, int $id): Response
    {
        $response = new Response();
        
        try {
            // Zugriffsprüfung
            $playlist = $this->playlistAgent->getPlaylist($id);
            if (!$playlist || ($playlist['user_id'] != $this->authAgent->id() && !$this->authAgent->isAdmin())) {
                return $response->json([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ], 403);
            }
            
            $data = $request->json();
            
            if (!isset($data['target_date'])) {
                return $response->json([
                    'success' => false,
                    'message' => 'Zieldatum erforderlich'
                ], 400);
            }
            
            $cloned = $this->playlistAgent->clonePlaylist(
                $id, 
                $data['target_date'], 
                $this->authAgent->id()
            );
            
            return $response->json([
                'success' => true,
                'data' => $cloned,
                'message' => 'Playlist erfolgreich kopiert'
            ], 201);
            
        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}