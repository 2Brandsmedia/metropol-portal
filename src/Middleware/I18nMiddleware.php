<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Agents\I18nAgent;

/**
 * I18nMiddleware - Automatische Spracherkennung und -verwaltung
 * 
 * @author 2Brands Media GmbH
 */
class I18nMiddleware
{
    private I18nAgent $i18n;

    public function __construct(I18nAgent $i18n)
    {
        $this->i18n = $i18n;
    }

    /**
     * Middleware-Handler
     */
    public function handle(Request $request, callable $next): Response
    {
        // Sprache aus Query-Parameter prüfen
        $queryLang = $request->query('lang');
        
        if ($queryLang && $this->i18n->hasLanguage($queryLang)) {
            // Neue Sprache setzen (speichert in Session und Cookie)
            $this->i18n->setLanguage($queryLang);
            
            // Redirect ohne lang-Parameter
            $url = $request->path();
            $queryParams = $request->query();
            unset($queryParams['lang']);
            
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }
            
            $response = new Response();
            return $response->redirect($url);
        }
        
        // I18n-Helper für Templates bereitstellen
        $request->setAttribute('i18n', $this->i18n);
        $request->setAttribute('lang', $this->i18n->getLanguage());
        $request->setAttribute('t', function(string $key, array $replacements = []) {
            return $this->i18n->translate($key, $replacements);
        });
        
        // Response-Header setzen
        $response = $next($request);
        $response->header('Content-Language', $this->i18n->getLanguage());
        
        return $response;
    }
}