<?php

/**
 * Web-Routen
 * 
 * @author 2Brands Media GmbH
 */

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Controllers\ApiLimitController;

$app = Application::getInstance();
$router = $app->getRouter();
$container = $app->getContainer();

// Login-Seite
$router->get('/login', function(Request $request) use ($container) {
    $response = new Response();
    $i18n = $container->get('i18n');
    $auth = $container->get('auth');
    
    // Wenn bereits eingeloggt, zum Dashboard weiterleiten
    if ($auth->check()) {
        return $response->redirect('/dashboard');
    }
    
    // Template-Variablen
    $lang = $i18n->getLanguage();
    $t = function($key, $replacements = []) use ($i18n) {
        return $i18n->translate($key, $replacements);
    };
    $csrfToken = $auth->generateCsrfToken();
    
    // Template rendern
    ob_start();
    include __DIR__ . '/../templates/auth/login.php';
    $content = ob_get_clean();
    
    return $response->setContent($content);
});

// Logout-Seite (GET für direkten Link)
$router->get('/logout', function(Request $request) use ($container) {
    $auth = $container->get('auth');
    $auth->logout();
    
    $response = new Response();
    return $response->redirect('/login');
});

// Dashboard (geschützt)
$router->get('/dashboard', function(Request $request) use ($container) {
    $response = new Response();
    $i18n = $container->get('i18n');
    $auth = $container->get('auth');
    $ui = $container->get('ui');
    
    // Template-Variablen
    $user = $auth->user();
    $lang = $i18n->getLanguage();
    $t = function($key, $replacements = []) use ($i18n) {
        return $i18n->translate($key, $replacements);
    };
    
    // Dashboard-Template rendern
    ob_start();
    include __DIR__ . '/../templates/dashboard.php';
    $content = ob_get_clean();
    
    return $response->setContent($content);
})->middleware(new AuthMiddleware($container->get('auth')));

// Homepage
$router->get('/', function(Request $request) use ($container) {
    $auth = $container->get('auth');
    $response = new Response();
    
    // Wenn eingeloggt, zum Dashboard
    if ($auth->check()) {
        return $response->redirect('/dashboard');
    }
    
    // Sonst zur Login-Seite
    return $response->redirect('/login');
});

// Playlist-Routen (geschützt)
$router->group('/playlists', function($router) use ($container) {
    // Playlist-Übersicht
    $router->get('/', function(Request $request) use ($container) {
        $response = new Response();
        $i18n = $container->get('i18n');
        $auth = $container->get('auth');
        $ui = $container->get('ui');
        
        // Template-Variablen
        $user = $auth->user();
        $lang = $i18n->getLanguage();
        $t = function($key, $replacements = []) use ($i18n) {
            return $i18n->translate($key, $replacements);
        };
        
        ob_start();
        include __DIR__ . '/../templates/playlists/index.php';
        $content = ob_get_clean();
        
        return $response->setContent($content);
    });
    
    // Neue Playlist erstellen
    $router->get('/create', function(Request $request) use ($container) {
        $response = new Response();
        $i18n = $container->get('i18n');
        $auth = $container->get('auth');
        $ui = $container->get('ui');
        
        // Template-Variablen
        $user = $auth->user();
        $lang = $i18n->getLanguage();
        $t = function($key, $replacements = []) use ($i18n) {
            return $i18n->translate($key, $replacements);
        };
        $csrfToken = $auth->generateCsrfToken();
        
        ob_start();
        include __DIR__ . '/../templates/playlists/create.php';
        $content = ob_get_clean();
        
        return $response->setContent($content);
    });
    
    // Playlist bearbeiten
    $router->get('/{id}/edit', function(Request $request, int $id) use ($container) {
        $response = new Response();
        $i18n = $container->get('i18n');
        $auth = $container->get('auth');
        $ui = $container->get('ui');
        $playlistAgent = $container->get('playlist');
        
        // Playlist laden
        $playlist = $playlistAgent->getPlaylist($id);
        if (!$playlist || ($playlist['user_id'] != $auth->id() && !$auth->isAdmin())) {
            return $response->redirect('/playlists');
        }
        
        // Template-Variablen
        $user = $auth->user();
        $lang = $i18n->getLanguage();
        $t = function($key, $replacements = []) use ($i18n) {
            return $i18n->translate($key, $replacements);
        };
        $csrfToken = $auth->generateCsrfToken();
        
        ob_start();
        include __DIR__ . '/../templates/playlists/edit.php';
        $content = ob_get_clean();
        
        return $response->setContent($content);
    });
    
    // Playlist anzeigen
    $router->get('/{id}', function(Request $request, int $id) use ($container) {
        $response = new Response();
        $i18n = $container->get('i18n');
        $auth = $container->get('auth');
        $ui = $container->get('ui');
        $playlistAgent = $container->get('playlist');
        
        // Playlist laden
        $playlist = $playlistAgent->getPlaylist($id);
        if (!$playlist || ($playlist['user_id'] != $auth->id() && !$auth->isAdmin())) {
            return $response->redirect('/playlists');
        }
        
        // Template-Variablen
        $user = $auth->user();
        $lang = $i18n->getLanguage();
        $t = function($key, $replacements = []) use ($i18n) {
            return $i18n->translate($key, $replacements);
        };
        
        ob_start();
        include __DIR__ . '/../templates/playlists/view.php';
        $content = ob_get_clean();
        
        return $response->setContent($content);
    });
})->middleware(new AuthMiddleware($container->get('auth')));

// API Limits Dashboard (geschützt)
$router->get('/api-limits', function(Request $request) use ($container) {
    $controller = new ApiLimitController($container->get('db'), $container->get('config'));
    return $controller->dashboard($request);
})->middleware(new AuthMiddleware($container->get('auth')));

// Weitere Web-Routen hier hinzufügen...