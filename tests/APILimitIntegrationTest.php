<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Config;
use App\Agents\APILimitAgent;
use App\Services\APIUsageTracker;
use App\Services\APIFallbackService;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests für API Limit Management System
 * 
 * @author 2Brands Media GmbH
 */
class APILimitIntegrationTest extends TestCase
{
    private Database $db;
    private Config $config;
    private APILimitAgent $limitAgent;
    private APIUsageTracker $tracker;
    private APIFallbackService $fallback;

    protected function setUp(): void
    {
        // Mock Database und Config
        $this->db = $this->createMock(Database::class);
        $this->config = $this->createMock(Config::class);
        
        // Test-Konfiguration
        $this->config->method('get')
                    ->willReturnMap([
                        ['admin.emails', [], ['admin@metropol-portal.de']],
                        ['api.ors_key', null, 'test-ors-key'],
                        ['geocoding.nominatim_url', 'https://nominatim.openstreetmap.org', 'https://nominatim.openstreetmap.org']
                    ]);
        
        $this->limitAgent = new APILimitAgent($this->db, $this->config);
        $this->tracker = new APIUsageTracker($this->db, $this->config);
        $this->fallback = new APIFallbackService($this->db, $this->config);
    }

    public function testAPILimitCheckingFlow(): void
    {
        // Mock Database-Responses für verschiedene Limit-Szenarien
        $this->db->method('selectOne')
                ->willReturnOnConsecutiveCalls(
                    // Erste Prüfung: 80% Nutzung (Yellow Warning)
                    ['request_count' => 20000, 'error_count' => 0, 'avg_response_time' => 150.0],
                    ['request_count' => 2000, 'error_count' => 0, 'avg_response_time' => 150.0],
                    
                    // Zweite Prüfung: 95% Nutzung (Blocked)
                    ['request_count' => 23750, 'error_count' => 50, 'avg_response_time' => 200.0],
                    ['request_count' => 2400, 'error_count' => 10, 'avg_response_time' => 200.0]
                );

        // Test 1: Yellow Warning bei 80% Nutzung
        $checkResult = $this->limitAgent->checkApiRequest(APIUsageTracker::API_GOOGLE_MAPS, 'routing');
        
        $this->assertTrue($checkResult['allowed']);
        $this->assertEquals('yellow', $checkResult['warning_level']);
        $this->assertStringContains('80%', $checkResult['user_message']['message']);
        
        // Test 2: Blocked bei 95% Nutzung
        $checkResult = $this->limitAgent->checkApiRequest(APIUsageTracker::API_GOOGLE_MAPS, 'routing');
        
        $this->assertFalse($checkResult['allowed']);
        $this->assertNotNull($checkResult['fallback_mode']);
        $this->assertEquals('blocked', $checkResult['user_message']['type']);
    }

    public function testFallbackStrategies(): void
    {
        // Test Cache-Only Fallback für Geocoding
        $this->db->method('selectOne')
                ->willReturn([
                    'latitude' => 52.5200,
                    'longitude' => 13.4050,
                    'address' => 'Berlin, Deutschland',
                    'provider' => 'nominatim',
                    'confidence' => 0.95
                ]);

        $result = $this->fallback->geocodingCacheOnly('Berlin');
        
        $this->assertNotNull($result);
        $this->assertTrue($result['fallback_used']);
        $this->assertEquals('cache_only', $result['fallback_type']);
        $this->assertEquals(52.5200, $result['latitude']);

        // Test Alternative API für Routing
        $waypoints = [
            ['latitude' => 52.5200, 'longitude' => 13.4050],
            ['latitude' => 48.1351, 'longitude' => 11.5820]
        ];
        
        $result = $this->fallback->routingAlternativeApi($waypoints);
        
        $this->assertArrayHasKey('fallback_used', $result);
        $this->assertTrue($result['fallback_used']);
    }

    public function testUserMessages(): void
    {
        // Test verschiedene Buffer-Messages
        $yellowMessage = $this->fallback->getBufferMessage(
            APIUsageTracker::API_GOOGLE_MAPS, 
            'routing', 
            'yellow'
        );
        
        $this->assertEquals('warning', $yellowMessage['type']);
        $this->assertStringContains('verlangsamt', $yellowMessage['message']);
        $this->assertTrue($yellowMessage['show_progress']);

        $blockedMessage = $this->fallback->getBufferMessage(
            APIUsageTracker::API_NOMINATIM, 
            'geocoding', 
            'blocked'
        );
        
        $this->assertEquals('blocked', $blockedMessage['type']);
        $this->assertStringContains('nicht verfügbar', $blockedMessage['message']);
        $this->assertNotEmpty($blockedMessage['alternatives']);
    }

    public function testUsageTracking(): void
    {
        // Mock für erfolgreiche Request-Tracking
        $this->db->expects($this->exactly(2))
                ->method('query')
                ->with($this->stringContains('INSERT INTO api_usage'));

        // Track erfolgreiche Anfrage
        $this->tracker->trackRequest(
            APIUsageTracker::API_GOOGLE_MAPS,
            'directions',
            true,
            150.5
        );

        // Track fehlgeschlagene Anfrage
        $this->tracker->trackRequest(
            APIUsageTracker::API_NOMINATIM,
            'search',
            false,
            0.0
        );

        $this->assertTrue(true); // Test bestanden wenn keine Exception
    }

    public function testDashboardDataGeneration(): void
    {
        // Mock für Dashboard-Daten
        $this->db->method('select')
                ->willReturn([
                    [
                        'api_provider' => 'google_maps',
                        'warning_level' => 'yellow',
                        'daily_requests' => 20000,
                        'hourly_requests' => 2000
                    ]
                ]);

        $dashboardData = $this->limitAgent->getDashboardData();
        
        $this->assertArrayHasKey('apis', $dashboardData);
        $this->assertArrayHasKey('alerts', $dashboardData);
        $this->assertArrayHasKey('recommendations', $dashboardData);
        $this->assertArrayHasKey('fallback_status', $dashboardData);
    }

    public function testRateLimitingLogic(): void
    {
        // Test Rate Limiting für Nominatim (1 req/sec)
        $this->db->method('selectOne')
                ->willReturnOnConsecutiveCalls(
                    ['last_request' => time()], // Gerade eben eine Anfrage
                    ['last_request' => time() - 2] // Vor 2 Sekunden
                );

        // Erste Prüfung: Blockiert wegen Rate Limit
        $checkResult = $this->limitAgent->checkApiRequest(APIUsageTracker::API_NOMINATIM);
        $this->assertFalse($checkResult['allowed']);
        $this->assertEquals(1, $checkResult['retry_after']);

        // Zweite Prüfung: Erlaubt nach Wartezeit
        $checkResult = $this->limitAgent->checkApiRequest(APIUsageTracker::API_NOMINATIM);
        $this->assertTrue($checkResult['allowed']);
    }

    public function testFallbackQualityEvaluation(): void
    {
        // Test Qualitätsbewertung verschiedener Fallbacks
        
        // Cache-Only mit hoher Confidence
        $quality = $this->fallback->evaluateFallbackQuality(
            APIUsageTracker::API_NOMINATIM,
            'cache_only',
            ['confidence' => 0.9, 'cache_age_hours' => 1]
        );
        $this->assertGreaterThan(0.7, $quality);

        // Alternative API
        $quality = $this->fallback->evaluateFallbackQuality(
            APIUsageTracker::API_GOOGLE_MAPS,
            'alternative_api',
            []
        );
        $this->assertEquals(0.8, $quality);

        // Luftlinie (niedrigste Qualität)
        $quality = $this->fallback->evaluateFallbackQuality(
            APIUsageTracker::API_GOOGLE_MAPS,
            'airline_distance',
            []
        );
        $this->assertEquals(0.3, $quality);
    }

    public function testHaversineDistance(): void
    {
        // Test Luftlinien-Berechnung zwischen bekannten Punkten
        $waypoints = [
            ['latitude' => 52.5200, 'longitude' => 13.4050], // Berlin
            ['latitude' => 48.1351, 'longitude' => 11.5820]  // München
        ];
        
        $result = $this->fallback->calculateAirlineDistance($waypoints, []);
        
        $this->assertArrayHasKey('total_distance', $result);
        $this->assertArrayHasKey('fallback_used', $result);
        $this->assertTrue($result['fallback_used']);
        $this->assertEquals('airline_calculation', $result['provider']);
        
        // Ungefähre Distanz Berlin-München: ~504km
        $distanceKm = $result['total_distance_km'];
        $this->assertGreaterThan(480, $distanceKm);
        $this->assertLessThan(520, $distanceKm);
    }

    public function testErrorHandling(): void
    {
        // Test Fehlerbehandlung bei Database-Fehlern
        $this->db->method('selectOne')
                ->willThrowException(new \Exception('Database connection failed'));

        try {
            $this->limitAgent->checkApiRequest(APIUsageTracker::API_GOOGLE_MAPS);
            $this->fail('Exception sollte geworfen werden');
        } catch (\Exception $e) {
            $this->assertStringContains('Database', $e->getMessage());
        }
    }

    public function testConfigurationValidation(): void
    {
        // Test verschiedene Konfigurationsszenarien
        $limits = [
            'daily' => 25000,
            'hourly' => 2500,
            'per_second' => 50
        ];
        
        $this->assertTrue($this->isValidConfiguration($limits));
        
        // Ungültige Konfiguration
        $invalidLimits = [
            'daily' => -1,
            'hourly' => 'invalid'
        ];
        
        $this->assertFalse($this->isValidConfiguration($invalidLimits));
    }

    private function isValidConfiguration(array $limits): bool
    {
        $requiredKeys = ['daily', 'hourly', 'per_second'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($limits[$key]) || !is_numeric($limits[$key]) || $limits[$key] < 0) {
                return false;
            }
        }
        
        return true;
    }

    public function testIntegrationFlow(): void
    {
        // Vollständiger Integrationstest: Request -> Limit Check -> Fallback -> Tracking
        
        // 1. Mock hohe API-Nutzung
        $this->db->method('selectOne')
                ->willReturn(['request_count' => 24000, 'error_count' => 0]); // 96% Nutzung
        
        // 2. API-Anfrage prüfen
        $checkResult = $this->limitAgent->checkApiRequest(APIUsageTracker::API_GOOGLE_MAPS, 'routing');
        
        // 3. Sollte blockiert sein
        $this->assertFalse($checkResult['allowed']);
        $this->assertNotNull($checkResult['fallback_mode']);
        
        // 4. Fallback ausführen
        if ($checkResult['fallback_mode']) {
            $fallbackResult = $this->limitAgent->executeFallbackStrategy(
                APIUsageTracker::API_GOOGLE_MAPS,
                $checkResult['fallback_mode'],
                ['test' => 'data']
            );
            
            $this->assertArrayHasKey('fallback_used', $fallbackResult);
            $this->assertTrue($fallbackResult['fallback_used']);
        }
        
        // 5. User-Message generieren
        $userMessage = $this->limitAgent->getUserMessage(
            APIUsageTracker::API_GOOGLE_MAPS,
            'blocked',
            'routing'
        );
        
        $this->assertEquals('blocked', $userMessage['type']);
        $this->assertNotEmpty($userMessage['message']);
    }
}

/**
 * Mock-Klassen für Tests
 */
class MockDatabase extends Database
{
    private array $mockData = [];
    
    public function setMockData(string $method, array $data): void
    {
        $this->mockData[$method] = $data;
    }
    
    public function selectOne(string $query, array $params = []): ?array
    {
        return $this->mockData['selectOne'] ?? null;
    }
    
    public function select(string $query, array $params = []): array
    {
        return $this->mockData['select'] ?? [];
    }
    
    public function query(string $query, array $params = []): bool
    {
        return true;
    }
}

class MockConfig extends Config
{
    private array $mockConfig = [];
    
    public function setMockConfig(array $config): void
    {
        $this->mockConfig = $config;
    }
    
    public function get(string $key, $default = null)
    {
        return $this->mockConfig[$key] ?? $default;
    }
    
    public function has(string $key): bool
    {
        return isset($this->mockConfig[$key]);
    }
}