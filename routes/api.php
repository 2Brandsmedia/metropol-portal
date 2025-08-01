<?php

/**
 * API-Routen
 * 
 * @author 2Brands Media GmbH
 */

use App\Core\Application;
use App\Controllers\AuthController;
use App\Controllers\I18nController;
use App\Controllers\I18nMaintenanceController;
use App\Controllers\PlaylistController;
use App\Controllers\GeoController;
use App\Controllers\RouteController;
use App\Controllers\CacheController;
use App\Controllers\ApiLimitController;
use App\Controllers\MaintenanceController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;

$app = Application::getInstance();
$router = $app->getRouter();
$container = $app->getContainer();

// Auth-Routen (ohne Auth-Middleware)
$router->group('/api/auth', function($router) use ($container) {
    // Rate-Limiting für Login
    $rateLimitMiddleware = RateLimitMiddleware::forLogin($container->get('db'));
    
    $router->post('/login', [AuthController::class, 'login'])
        ->middleware($rateLimitMiddleware);
        
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/status', [AuthController::class, 'status']);
    $router->post('/refresh', [AuthController::class, 'refresh']);
    $router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

// I18n-Routen (öffentlich)
$router->group('/api/i18n', function($router) {
    $router->get('/translations/{lang}', [I18nController::class, 'getTranslations']);
    $router->get('/languages', [I18nController::class, 'getLanguages']);
    $router->post('/translate', [I18nController::class, 'translate']);
    $router->post('/language', [I18nController::class, 'setLanguage']);
});

// Geschützte I18n-Routen (nur für Admins)
$router->group('/api/i18n', function($router) {
    $router->get('/coverage', [I18nController::class, 'getCoverage']);
})->middleware(new AuthMiddleware($container->get('auth')));

// I18n Wartungs-System (nur für Admins)
$router->group('/api/i18n/maintenance', function($router) {
    // Dashboard und Status
    $router->get('/dashboard', [I18nMaintenanceController::class, 'dashboard']);
    $router->get('/status', [I18nMaintenanceController::class, 'status']);
    
    // Wartungsaktionen
    $router->post('/check', [I18nMaintenanceController::class, 'check']);
    $router->post('/sync', [I18nMaintenanceController::class, 'sync']);
    $router->post('/backup', [I18nMaintenanceController::class, 'backup']);
    $router->post('/full-maintenance', [I18nMaintenanceController::class, 'fullMaintenance']);
    
    // Analysen und Berichte
    $router->get('/report', [I18nMaintenanceController::class, 'report']);
    $router->get('/unused-keys', [I18nMaintenanceController::class, 'unusedKeys']);
    $router->get('/missing-translations', [I18nMaintenanceController::class, 'missingTranslations']);
    $router->get('/validate-placeholders', [I18nMaintenanceController::class, 'validatePlaceholders']);
    
    // Export-Funktionen
    $router->get('/export', [I18nMaintenanceController::class, 'exportStats']);
})->middleware(new AuthMiddleware($container->get('auth')));

// Playlist-Routen (geschützt)
$router->group('/api/playlists', function($router) {
    $router->get('/', [PlaylistController::class, 'index']);
    $router->get('/{id}', [PlaylistController::class, 'show']);
    $router->post('/', [PlaylistController::class, 'create']);
    $router->put('/{id}', [PlaylistController::class, 'update']);
    $router->delete('/{id}', [PlaylistController::class, 'delete']);
    
    // Stopp-Verwaltung
    $router->post('/{id}/stops', [PlaylistController::class, 'addStop']);
    $router->put('/{playlistId}/stops/{stopId}', [PlaylistController::class, 'updateStop']);
    $router->delete('/{playlistId}/stops/{stopId}', [PlaylistController::class, 'deleteStop']);
    $router->put('/{id}/stops/reorder', [PlaylistController::class, 'reorderStops']);
    
    // Zusätzliche Features
    $router->post('/{id}/clone', [PlaylistController::class, 'clonePlaylist']);
})->middleware(new AuthMiddleware($container->get('auth')));

// Geo-Routen (geschützt)
$router->group('/api/geo', function($router) {
    $router->post('/geocode', [GeoController::class, 'geocode']);
    $router->post('/batch', [GeoController::class, 'batch']);
    $router->post('/reverse', [GeoController::class, 'reverse']);
    $router->get('/stats', [GeoController::class, 'stats']);
    $router->post('/clean-cache', [GeoController::class, 'cleanCache']);
})->middleware(new AuthMiddleware($container->get('auth')));

// Route-Routen (geschützt)
$router->group('/api/routes', function($router) {
    $router->post('/calculate/{playlistId}', [RouteController::class, 'calculate']);
    $router->post('/optimize/{playlistId}', [RouteController::class, 'optimize']);
    $router->post('/preview', [RouteController::class, 'preview']);
    $router->get('/etas/{playlistId}', [RouteController::class, 'etas']);
    $router->get('/profiles', [RouteController::class, 'profiles']);
    $router->get('/traffic/{playlistId}', [RouteController::class, 'traffic']);
})->middleware(new AuthMiddleware($container->get('auth')));

// Cache-Management (nur für Admins)
$router->group('/api/cache', function($router) {
    $router->get('/stats', [CacheController::class, 'stats']);
    $router->post('/clean', [CacheController::class, 'clean']);
    $router->get('/search', [CacheController::class, 'search']);
})->middleware(new AuthMiddleware($container->get('auth')));

// API Limit Management (geschützt)
$router->group('/api/limits', function($router) {
    // Dashboard und Live-Daten
    $router->get('/dashboard', [ApiLimitController::class, 'getDashboardData']);
    $router->get('/realtime', [ApiLimitController::class, 'getRealTimeDashboard']);
    
    // Limit-Management
    $router->put('/update', [ApiLimitController::class, 'updateLimits']);
    $router->post('/reset', [ApiLimitController::class, 'resetLimits']); // Nur Admins
    $router->post('/detect-changes', [ApiLimitController::class, 'detectLimitChanges']);
    
    // Monitoring und Health
    $router->get('/health', [ApiLimitController::class, 'checkApiHealth']);
    $router->get('/health/{provider}', [ApiLimitController::class, 'checkApiHealth']);
    $router->get('/status', [ApiLimitController::class, 'getSystemStatus']);
    
    // Kosten und Projektionen
    $router->get('/cost-projection', [ApiLimitController::class, 'getCostProjection']);
    $router->post('/budget/toggle', [ApiLimitController::class, 'toggleBudgetMonitoring']);
    
    // Berichte
    $router->get('/reports/{type}', [ApiLimitController::class, 'generateReport']);
    $router->get('/reports/compliance', [ApiLimitController::class, 'getComplianceReport']);
    $router->get('/reports/export', [ApiLimitController::class, 'exportReport']);
    
    // Alerts
    $router->get('/alerts', [ApiLimitController::class, 'getActiveAlerts']);
    $router->post('/alerts/read', [ApiLimitController::class, 'markAlertAsRead']);
    
    // Fallback-System
    $router->post('/fallback/test', [ApiLimitController::class, 'testFallback']);
})->middleware(new AuthMiddleware($container->get('auth')));

// Maintenance Management (nur für Admins)
$router->group('/api/maintenance', function($router) {
    // System-Gesundheit und Status
    $router->get('/health', [MaintenanceController::class, 'getHealthStatus']);
    $router->get('/status', [MaintenanceController::class, 'getMaintenanceStatus']);
    $router->get('/diagnostics', [MaintenanceController::class, 'getDiagnostics']);
    $router->get('/metrics', [MaintenanceController::class, 'getMetrics']);
    $router->get('/logs', [MaintenanceController::class, 'getLogs']);
    
    // Wartungsausführung
    $router->post('/run', [MaintenanceController::class, 'runMaintenance']);
    $router->post('/emergency', [MaintenanceController::class, 'runEmergencyMaintenance']);
    $router->post('/task/{task}', [MaintenanceController::class, 'runTask']);
    
    // Spezielle Wartungsaufgaben
    $router->post('/cache/clear', [MaintenanceController::class, 'clearCache']);
    $router->post('/database/optimize', [MaintenanceController::class, 'optimizeDatabase']);
})->middleware(new AuthMiddleware($container->get('auth')));

// Weitere API-Routen hier hinzufügen...