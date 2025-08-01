<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\I18nAgent;

/**
 * I18n Controller - API-Endpunkte für Internationalisierung
 * 
 * @author 2Brands Media GmbH
 */
class I18nController
{
    private I18nAgent $i18n;

    public function __construct(I18nAgent $i18n)
    {
        $this->i18n = $i18n;
    }

    /**
     * Gibt alle Übersetzungen für eine Sprache zurück
     * GET /api/i18n/translations/{lang}
     */
    public function getTranslations(Request $request, string $lang): Response
    {
        $response = new Response();
        
        // Sprache validieren
        if (!$this->i18n->hasLanguage($lang)) {
            return $response->json([
                'error' => 'Invalid language'
            ], 400);
        }
        
        // Aktuelle Sprache temporär speichern
        $currentLang = $this->i18n->getLanguage();
        
        // Sprache wechseln um Übersetzungen zu laden
        $this->i18n->setLanguage($lang);
        
        // Alle Übersetzungen holen
        $translations = $this->i18n->getAllTranslations();
        
        // Sprache zurücksetzen
        $this->i18n->setLanguage($currentLang);
        
        // Cache-Header setzen
        return $response
            ->json($translations)
            ->cache(60); // 1 Stunde cachen
    }

    /**
     * Setzt die aktuelle Sprache
     * POST /api/i18n/language
     */
    public function setLanguage(Request $request): Response
    {
        $response = new Response();
        
        $data = $request->json();
        $lang = $data['language'] ?? null;
        
        if (!$lang || !$this->i18n->hasLanguage($lang)) {
            return $response->json([
                'success' => false,
                'error' => 'Invalid language'
            ], 400);
        }
        
        // Sprache setzen
        $this->i18n->setLanguage($lang);
        
        return $response->json([
            'success' => true,
            'language' => $lang
        ]);
    }

    /**
     * Gibt Informationen zu verfügbaren Sprachen zurück
     * GET /api/i18n/languages
     */
    public function getLanguages(Request $request): Response
    {
        $response = new Response();
        
        $info = $this->i18n->getLanguageInfo();
        
        return $response->json([
            'current' => $info['current'],
            'default' => $info['default'],
            'available' => array_map(function($code) use ($info) {
                return [
                    'code' => $code,
                    'name' => $info['names'][$code] ?? $code,
                    'active' => $code === $info['current']
                ];
            }, $info['available'])
        ]);
    }

    /**
     * Prüft die Übersetzungsabdeckung
     * GET /api/i18n/coverage
     */
    public function getCoverage(Request $request): Response
    {
        $response = new Response();
        
        // Nur für Admins
        if (!$request->getAttribute('auth')->isAdmin()) {
            return $response->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        $coverage = $this->i18n->checkCoverage();
        
        return $response->json([
            'coverage' => $coverage,
            'complete' => min($coverage) === 100.0
        ]);
    }

    /**
     * Übersetzt einen einzelnen Schlüssel
     * POST /api/i18n/translate
     */
    public function translate(Request $request): Response
    {
        $response = new Response();
        
        $data = $request->json();
        $key = $data['key'] ?? null;
        $replacements = $data['replacements'] ?? [];
        $lang = $data['language'] ?? null;
        
        if (!$key) {
            return $response->json([
                'error' => 'Translation key required'
            ], 400);
        }
        
        // Temporär Sprache wechseln wenn angegeben
        $currentLang = null;
        if ($lang && $this->i18n->hasLanguage($lang)) {
            $currentLang = $this->i18n->getLanguage();
            $this->i18n->setLanguage($lang);
        }
        
        // Übersetzen
        $translation = $this->i18n->translate($key, $replacements);
        
        // Sprache zurücksetzen
        if ($currentLang) {
            $this->i18n->setLanguage($currentLang);
        }
        
        return $response->json([
            'key' => $key,
            'translation' => $translation,
            'language' => $lang ?? $this->i18n->getLanguage()
        ]);
    }
}