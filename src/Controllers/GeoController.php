<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\GeoAgent;
use App\Middleware\AuthMiddleware;

/**
 * GeoController - API-Endpunkte für Geokodierung
 * 
 * @author 2Brands Media GmbH
 */
class GeoController extends BaseController
{
    private GeoAgent $geoAgent;

    public function __construct()
    {
        parent::__construct();
        $this->geoAgent = $this->container->get('geo');
        
        // Authentifizierung erforderlich
        $this->middleware(AuthMiddleware::class);
    }

    /**
     * Geokodiert eine einzelne Adresse
     * 
     * POST /api/geo/geocode
     */
    public function geocode(Request $request, Response $response): Response
    {
        $data = $request->json();
        
        // Validierung
        if (empty($data['address'])) {
            return $response->json([
                'error' => true,
                'message' => 'Adresse erforderlich'
            ], 400);
        }
        
        // Geokodierung durchführen
        $result = $this->geoAgent->geocode($data['address']);
        
        if ($result === null) {
            return $response->json([
                'error' => true,
                'message' => 'Adresse konnte nicht geokodiert werden'
            ], 404);
        }
        
        return $response->json([
            'success' => true,
            'data' => [
                'address' => $data['address'],
                'latitude' => $result['latitude'],
                'longitude' => $result['longitude'],
                'confidence' => $result['confidence'],
                'provider' => $result['provider']
            ]
        ]);
    }

    /**
     * Geokodiert mehrere Adressen gleichzeitig
     * 
     * POST /api/geo/batch
     */
    public function batch(Request $request, Response $response): Response
    {
        $data = $request->json();
        
        // Validierung
        if (empty($data['addresses']) || !is_array($data['addresses'])) {
            return $response->json([
                'error' => true,
                'message' => 'Adressen-Array erforderlich'
            ], 400);
        }
        
        // Maximal 50 Adressen gleichzeitig
        if (count($data['addresses']) > 50) {
            return $response->json([
                'error' => true,
                'message' => 'Maximal 50 Adressen gleichzeitig erlaubt'
            ], 400);
        }
        
        // Batch-Geokodierung
        $results = $this->geoAgent->geocodeBatch($data['addresses']);
        
        // Ergebnisse formatieren
        $formatted = [];
        foreach ($results as $address => $result) {
            if ($result !== null) {
                $formatted[] = [
                    'address' => $address,
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'confidence' => $result['confidence'],
                    'provider' => $result['provider'],
                    'success' => true
                ];
            } else {
                $formatted[] = [
                    'address' => $address,
                    'success' => false,
                    'error' => 'Geokodierung fehlgeschlagen'
                ];
            }
        }
        
        return $response->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Reverse Geocoding - Koordinaten zu Adresse
     * 
     * POST /api/geo/reverse
     */
    public function reverse(Request $request, Response $response): Response
    {
        $data = $request->json();
        
        // Validierung
        if (!isset($data['latitude']) || !isset($data['longitude'])) {
            return $response->json([
                'error' => true,
                'message' => 'Latitude und Longitude erforderlich'
            ], 400);
        }
        
        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];
        
        // Koordinaten-Bereich prüfen
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $response->json([
                'error' => true,
                'message' => 'Ungültige Koordinaten'
            ], 400);
        }
        
        // Reverse Geocoding
        $address = $this->geoAgent->reverseGeocode($latitude, $longitude);
        
        if ($address === null) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Adresse für diese Koordinaten gefunden'
            ], 404);
        }
        
        return $response->json([
            'success' => true,
            'data' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $address
            ]
        ]);
    }

    /**
     * Cache-Statistiken abrufen (nur Admin)
     * 
     * GET /api/geo/stats
     */
    public function stats(Request $request, Response $response): Response
    {
        // Nur Admins
        if (!$this->auth->isAdmin()) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Berechtigung'
            ], 403);
        }
        
        $stats = $this->geoAgent->getCacheStats();
        
        return $response->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Cache bereinigen (nur Admin)
     * 
     * POST /api/geo/clean-cache
     */
    public function cleanCache(Request $request, Response $response): Response
    {
        // Nur Admins
        if (!$this->auth->isAdmin()) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Berechtigung'
            ], 403);
        }
        
        $data = $request->json();
        $daysOld = (int) ($data['days_old'] ?? 90);
        
        // Mindestens 30 Tage alt
        if ($daysOld < 30) {
            $daysOld = 30;
        }
        
        $deleted = $this->geoAgent->cleanCache($daysOld);
        
        return $response->json([
            'success' => true,
            'message' => "{$deleted} alte Cache-Einträge gelöscht",
            'data' => [
                'deleted' => $deleted,
                'days_old' => $daysOld
            ]
        ]);
    }
}