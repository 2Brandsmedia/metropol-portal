<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Config;
use App\Agents\I18nAgent;
use Exception;

/**
 * UIAgent - UI-Komponenten und Layouts
 * 
 * Erfolgskriterium: Vollständige Komponentenbibliothek
 * 
 * @author 2Brands Media GmbH
 */
class UIAgent
{
    private Config $config;
    private I18nAgent $i18n;
    private array $componentCache = [];
    
    public function __construct(Config $config, I18nAgent $i18n)
    {
        $this->config = $config;
        $this->i18n = $i18n;
    }

    /**
     * Rendert den Sprachschalter
     */
    public function renderLanguageSwitcher(array $options = []): string
    {
        $currentLang = $this->i18n->getLanguage();
        $languages = $this->i18n->getLanguageInfo();
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Standard-Optionen
        $options = array_merge([
            'style' => 'dropdown', // dropdown, buttons, flags
            'showNames' => true,
            'showFlags' => false,
            'class' => 'language-switcher'
        ], $options);
        
        switch ($options['style']) {
            case 'dropdown':
                return $this->renderDropdownSwitcher($currentLang, $languages, $currentUrl, $options);
            case 'buttons':
                return $this->renderButtonSwitcher($currentLang, $languages, $currentUrl, $options);
            case 'flags':
                return $this->renderFlagSwitcher($currentLang, $languages, $currentUrl, $options);
            default:
                return $this->renderDropdownSwitcher($currentLang, $languages, $currentUrl, $options);
        }
    }

    /**
     * Rendert Dropdown-Sprachschalter
     */
    private function renderDropdownSwitcher(string $currentLang, array $languages, string $currentUrl, array $options): string
    {
        $html = '<div class="' . htmlspecialchars($options['class']) . '" x-data="{ open: false }">';
        $html .= '<button @click="open = !open" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">';
        
        if ($options['showFlags']) {
            $html .= $this->getFlagIcon($currentLang);
        }
        
        if ($options['showNames']) {
            $html .= '<span>' . htmlspecialchars($languages['names'][$currentLang]) . '</span>';
        }
        
        $html .= '<svg class="w-5 h-5 ml-2 -mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
        $html .= '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</button>';
        
        $html .= '<div x-show="open" @click.away="open = false" x-transition class="absolute z-10 mt-2 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5">';
        $html .= '<div class="py-1" role="menu">';
        
        foreach ($languages['available'] as $lang) {
            if ($lang === $currentLang) continue;
            
            $url = $this->buildLanguageUrl($currentUrl, $lang);
            $html .= '<a href="' . htmlspecialchars($url) . '" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem">';
            
            if ($options['showFlags']) {
                $html .= $this->getFlagIcon($lang);
            }
            
            if ($options['showNames']) {
                $html .= '<span>' . htmlspecialchars($languages['names'][$lang]) . '</span>';
            }
            
            $html .= '</a>';
        }
        
        $html .= '</div></div></div>';
        
        return $html;
    }

    /**
     * Rendert Button-Sprachschalter
     */
    private function renderButtonSwitcher(string $currentLang, array $languages, string $currentUrl, array $options): string
    {
        $html = '<div class="' . htmlspecialchars($options['class']) . ' inline-flex rounded-md shadow-sm" role="group">';
        
        foreach ($languages['available'] as $lang) {
            $url = $this->buildLanguageUrl($currentUrl, $lang);
            $isActive = $lang === $currentLang;
            
            $classes = $isActive 
                ? 'text-white bg-indigo-600 hover:bg-indigo-700' 
                : 'text-gray-900 bg-white hover:bg-gray-100';
            
            $baseClasses = 'px-4 py-2 text-sm font-medium border border-gray-200 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700';
            
            // Erste/Letzte Button-Klassen
            if ($lang === reset($languages['available'])) {
                $baseClasses .= ' rounded-l-lg';
            }
            if ($lang === end($languages['available'])) {
                $baseClasses .= ' rounded-r-md';
            }
            
            $html .= '<a href="' . htmlspecialchars($url) . '" class="' . $baseClasses . ' ' . $classes . '">';
            
            if ($options['showFlags']) {
                $html .= $this->getFlagIcon($lang);
            }
            
            if ($options['showNames']) {
                $shortName = strtoupper($lang);
                $html .= '<span>' . $shortName . '</span>';
            }
            
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Rendert Flag-Sprachschalter
     */
    private function renderFlagSwitcher(string $currentLang, array $languages, string $currentUrl, array $options): string
    {
        $html = '<div class="' . htmlspecialchars($options['class']) . ' flex items-center gap-2">';
        
        foreach ($languages['available'] as $lang) {
            $url = $this->buildLanguageUrl($currentUrl, $lang);
            $isActive = $lang === $currentLang;
            
            $classes = $isActive ? 'ring-2 ring-indigo-500' : 'opacity-60 hover:opacity-100';
            
            $html .= '<a href="' . htmlspecialchars($url) . '" class="block rounded-full overflow-hidden transition-all ' . $classes . '" title="' . htmlspecialchars($languages['names'][$lang]) . '">';
            $html .= $this->getFlagIcon($lang, 'w-8 h-8');
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Gibt Flag-Icon zurück (Emoji)
     */
    private function getFlagIcon(string $lang, string $class = 'w-5 h-5'): string
    {
        $flags = [
            'de' => '🇩🇪', // Deutschland
            'en' => '🇬🇧', // Großbritannien
            'tr' => '🇹🇷'  // Türkei
        ];
        
        return '<span class="' . $class . ' flex items-center justify-center">' . ($flags[$lang] ?? '?') . '</span>';
    }

    /**
     * Baut Language-URL
     */
    private function buildLanguageUrl(string $currentUrl, string $lang): string
    {
        $parts = parse_url($currentUrl);
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';
        
        // Query-Parameter parsen
        parse_str($query, $params);
        $params['lang'] = $lang;
        
        return $path . '?' . http_build_query($params);
    }

    /**
     * Rendert eine Alert-Box
     */
    public function renderAlert(string $message, string $type = 'info', array $options = []): string
    {
        $types = [
            'info' => ['bg' => 'blue-50', 'text' => 'blue-800', 'icon' => 'blue-400'],
            'success' => ['bg' => 'green-50', 'text' => 'green-800', 'icon' => 'green-400'],
            'warning' => ['bg' => 'yellow-50', 'text' => 'yellow-800', 'icon' => 'yellow-400'],
            'error' => ['bg' => 'red-50', 'text' => 'red-800', 'icon' => 'red-400']
        ];
        
        $colors = $types[$type] ?? $types['info'];
        $dismissible = $options['dismissible'] ?? true;
        
        $html = '<div class="rounded-md bg-' . $colors['bg'] . ' p-4"';
        if ($dismissible) {
            $html .= ' x-data="{ show: true }" x-show="show"';
        }
        $html .= '>';
        
        $html .= '<div class="flex">';
        $html .= '<div class="flex-shrink-0">';
        $html .= $this->getAlertIcon($type, $colors['icon']);
        $html .= '</div>';
        
        $html .= '<div class="ml-3">';
        $html .= '<p class="text-sm font-medium text-' . $colors['text'] . '">';
        $html .= htmlspecialchars($message);
        $html .= '</p>';
        $html .= '</div>';
        
        if ($dismissible) {
            $html .= '<div class="ml-auto pl-3">';
            $html .= '<div class="-mx-1.5 -my-1.5">';
            $html .= '<button @click="show = false" class="inline-flex bg-' . $colors['bg'] . ' rounded-md p-1.5 text-' . $colors['icon'] . ' hover:bg-' . $colors['bg'] . ' focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-' . $colors['bg'] . ' focus:ring-' . $colors['icon'] . '">';
            $html .= '<span class="sr-only">' . $this->i18n->t('ui.close') . '</span>';
            $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
            $html .= '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L13.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />';
            $html .= '</svg>';
            $html .= '</button>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Gibt Alert-Icon zurück
     */
    private function getAlertIcon(string $type, string $color): string
    {
        $icons = [
            'info' => '<svg class="h-5 w-5 text-' . $color . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>',
            'success' => '<svg class="h-5 w-5 text-' . $color . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>',
            'warning' => '<svg class="h-5 w-5 text-' . $color . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>',
            'error' => '<svg class="h-5 w-5 text-' . $color . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>'
        ];
        
        return $icons[$type] ?? $icons['info'];
    }

    /**
     * Rendert Breadcrumbs
     */
    public function renderBreadcrumbs(array $items): string
    {
        if (empty($items)) {
            return '';
        }
        
        $html = '<nav class="flex" aria-label="Breadcrumb">';
        $html .= '<ol class="flex items-center space-x-4">';
        
        foreach ($items as $index => $item) {
            $isLast = $index === count($items) - 1;
            
            $html .= '<li>';
            $html .= '<div class="flex items-center">';
            
            if ($index > 0) {
                $html .= '<svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
                $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />';
                $html .= '</svg>';
            }
            
            if ($isLast) {
                $html .= '<span class="' . ($index > 0 ? 'ml-4 ' : '') . 'text-sm font-medium text-gray-500">' . htmlspecialchars($item['label']) . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($item['url']) . '" class="' . ($index > 0 ? 'ml-4 ' : '') . 'text-sm font-medium text-gray-500 hover:text-gray-700">' . htmlspecialchars($item['label']) . '</a>';
            }
            
            $html .= '</div>';
            $html .= '</li>';
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }

    /**
     * Rendert Pagination
     */
    public function renderPagination(int $currentPage, int $totalPages, string $baseUrl): string
    {
        if ($totalPages <= 1) {
            return '';
        }
        
        $html = '<nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0">';
        
        // Mobile
        $html .= '<div class="-mt-px flex w-0 flex-1">';
        if ($currentPage > 1) {
            $html .= '<a href="' . $this->buildPaginationUrl($baseUrl, $currentPage - 1) . '" class="inline-flex items-center border-t-2 border-transparent pt-4 pr-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">';
            $html .= '<svg class="mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>';
            $html .= $this->i18n->t('ui.previous');
            $html .= '</a>';
        }
        $html .= '</div>';
        
        // Desktop - Seitenzahlen
        $html .= '<div class="hidden md:-mt-px md:flex">';
        
        $range = $this->getPaginationRange($currentPage, $totalPages);
        
        foreach ($range as $page) {
            if ($page === '...') {
                $html .= '<span class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>';
            } elseif ($page == $currentPage) {
                $html .= '<span class="inline-flex items-center border-t-2 border-indigo-500 px-4 pt-4 text-sm font-medium text-indigo-600">' . $page . '</span>';
            } else {
                $html .= '<a href="' . $this->buildPaginationUrl($baseUrl, $page) . '" class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">' . $page . '</a>';
            }
        }
        
        $html .= '</div>';
        
        // Mobile - Weiter
        $html .= '<div class="-mt-px flex w-0 flex-1 justify-end">';
        if ($currentPage < $totalPages) {
            $html .= '<a href="' . $this->buildPaginationUrl($baseUrl, $currentPage + 1) . '" class="inline-flex items-center border-t-2 border-transparent pt-4 pl-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">';
            $html .= $this->i18n->t('ui.next');
            $html .= '<svg class="ml-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>';
            $html .= '</a>';
        }
        $html .= '</div>';
        
        $html .= '</nav>';
        
        return $html;
    }

    /**
     * Berechnet Pagination-Range
     */
    private function getPaginationRange(int $current, int $total): array
    {
        $delta = 2;
        $range = [];
        $rangeWithDots = [];
        $l = null;
        
        for ($i = 1; $i <= $total; $i++) {
            if ($i == 1 || $i == $total || ($i >= $current - $delta && $i <= $current + $delta)) {
                $range[] = $i;
            }
        }
        
        foreach ($range as $i) {
            if ($l !== null && $i - $l > 1) {
                $rangeWithDots[] = '...';
            }
            $rangeWithDots[] = $i;
            $l = $i;
        }
        
        return $rangeWithDots;
    }

    /**
     * Baut Pagination-URL
     */
    private function buildPaginationUrl(string $baseUrl, int $page): string
    {
        $parts = parse_url($baseUrl);
        $query = $parts['query'] ?? '';
        
        parse_str($query, $params);
        $params['page'] = $page;
        
        return ($parts['path'] ?? '') . '?' . http_build_query($params);
    }
}