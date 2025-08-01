/**
 * Client-seitige i18n-Integration
 * 
 * @author 2Brands Media GmbH
 */

class I18n {
    constructor() {
        this.translations = {};
        this.currentLang = 'de';
        this.fallbackLang = 'de';
        this.loaded = false;
        this.listeners = [];
    }

    /**
     * Initialisiert i18n
     */
    async init() {
        // Aktuelle Sprache aus Cookie oder Browser ermitteln
        this.currentLang = this.detectLanguage();
        
        // Übersetzungen laden
        await this.loadTranslations(this.currentLang);
        
        // Event-Listener für Sprachwechsel
        this.setupLanguageSwitch();
        
        this.loaded = true;
        this.notifyListeners('loaded');
    }

    /**
     * Erkennt die aktuelle Sprache
     */
    detectLanguage() {
        // Aus Cookie
        const cookieLang = this.getCookie('lang');
        if (cookieLang && this.isValidLanguage(cookieLang)) {
            return cookieLang;
        }
        
        // Aus Browser
        const browserLang = navigator.language.substring(0, 2);
        if (this.isValidLanguage(browserLang)) {
            return browserLang;
        }
        
        return this.fallbackLang;
    }

    /**
     * Prüft ob Sprache gültig ist
     */
    isValidLanguage(lang) {
        return ['de', 'en', 'tr'].includes(lang);
    }

    /**
     * Lädt Übersetzungen
     */
    async loadTranslations(lang) {
        try {
            const response = await fetch(`/api/i18n/translations/${lang}`);
            if (!response.ok) {
                throw new Error(`Failed to load translations for ${lang}`);
            }
            
            this.translations = await response.json();
            this.currentLang = lang;
            
            // Cookie setzen
            this.setCookie('lang', lang, 365);
            
            // UI aktualisieren
            this.updateUI();
            
            this.notifyListeners('languageChanged', lang);
        } catch (error) {
            console.error('Error loading translations:', error);
            
            // Fallback
            if (lang !== this.fallbackLang) {
                await this.loadTranslations(this.fallbackLang);
            }
        }
    }

    /**
     * Übersetzt einen Schlüssel
     */
    t(key, replacements = {}) {
        const keys = key.split('.');
        let value = this.translations;
        
        for (const k of keys) {
            if (typeof value !== 'object' || !value[k]) {
                console.warn(`Translation not found: ${key}`);
                return key;
            }
            value = value[k];
        }
        
        if (typeof value !== 'string') {
            console.warn(`Translation is not a string: ${key}`);
            return key;
        }
        
        // Platzhalter ersetzen
        let translated = value;
        for (const [placeholder, replacement] of Object.entries(replacements)) {
            translated = translated.replace(`:${placeholder}`, String(replacement));
        }
        
        return translated;
    }

    /**
     * Setzt die Sprache
     */
    async setLanguage(lang) {
        if (!this.isValidLanguage(lang)) {
            console.error(`Invalid language: ${lang}`);
            return;
        }
        
        if (lang === this.currentLang) {
            return;
        }
        
        await this.loadTranslations(lang);
    }

    /**
     * Gibt die aktuelle Sprache zurück
     */
    getLanguage() {
        return this.currentLang;
    }

    /**
     * Gibt alle verfügbaren Sprachen zurück
     */
    getAvailableLanguages() {
        return [
            { code: 'de', name: 'Deutsch' },
            { code: 'en', name: 'English' },
            { code: 'tr', name: 'Türkçe' }
        ];
    }

    /**
     * Formatiert ein Datum
     */
    formatDate(date, format = 'default') {
        const formats = {
            de: {
                default: { day: '2-digit', month: '2-digit', year: 'numeric' },
                long: { day: 'numeric', month: 'long', year: 'numeric' },
                time: { hour: '2-digit', minute: '2-digit' },
                datetime: { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
            },
            en: {
                default: { month: '2-digit', day: '2-digit', year: 'numeric' },
                long: { month: 'long', day: 'numeric', year: 'numeric' },
                time: { hour: '2-digit', minute: '2-digit', hour12: true },
                datetime: { month: '2-digit', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }
            },
            tr: {
                default: { day: '2-digit', month: '2-digit', year: 'numeric' },
                long: { day: 'numeric', month: 'long', year: 'numeric' },
                time: { hour: '2-digit', minute: '2-digit' },
                datetime: { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
            }
        };
        
        const langFormats = formats[this.currentLang] || formats.de;
        const dateFormat = langFormats[format] || langFormats.default;
        
        return new Intl.DateTimeFormat(this.currentLang, dateFormat).format(date);
    }

    /**
     * Formatiert eine Zahl
     */
    formatNumber(number, decimals = 0) {
        return new Intl.NumberFormat(this.currentLang, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    /**
     * Formatiert eine Währung
     */
    formatCurrency(amount, currency = 'EUR') {
        return new Intl.NumberFormat(this.currentLang, {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    /**
     * Aktualisiert die UI
     */
    updateUI() {
        // Alle Elemente mit data-i18n aktualisieren
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            element.textContent = this.t(key);
        });
        
        // Alle Elemente mit data-i18n-placeholder aktualisieren
        document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
            const key = element.getAttribute('data-i18n-placeholder');
            element.placeholder = this.t(key);
        });
        
        // Alle Elemente mit data-i18n-title aktualisieren
        document.querySelectorAll('[data-i18n-title]').forEach(element => {
            const key = element.getAttribute('data-i18n-title');
            element.title = this.t(key);
        });
        
        // HTML lang-Attribut aktualisieren
        document.documentElement.lang = this.currentLang;
    }

    /**
     * Setup für Sprachwechsel
     */
    setupLanguageSwitch() {
        // Klick auf Sprachlinks abfangen
        document.addEventListener('click', (e) => {
            const langLink = e.target.closest('a[href*="?lang="]');
            if (langLink) {
                e.preventDefault();
                
                const url = new URL(langLink.href);
                const lang = url.searchParams.get('lang');
                
                if (lang && this.isValidLanguage(lang)) {
                    this.setLanguage(lang);
                }
            }
        });
    }

    /**
     * Cookie-Helfer
     */
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    }

    setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
    }

    /**
     * Event-Listener hinzufügen
     */
    on(event, callback) {
        this.listeners.push({ event, callback });
    }

    /**
     * Event-Listener benachrichtigen
     */
    notifyListeners(event, data = null) {
        this.listeners
            .filter(l => l.event === event)
            .forEach(l => l.callback(data));
    }

    /**
     * Wartet bis i18n geladen ist
     */
    ready() {
        return new Promise((resolve) => {
            if (this.loaded) {
                resolve();
            } else {
                this.on('loaded', resolve);
            }
        });
    }
}

// Globale Instanz
window.i18n = new I18n();

// Automatisch initialisieren wenn DOM bereit
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.i18n.init());
} else {
    window.i18n.init();
}

// Alpine.js Integration (falls vorhanden)
if (typeof Alpine !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        Alpine.data('i18n', () => ({
            t: (key, replacements) => window.i18n.t(key, replacements),
            currentLang: window.i18n.currentLang,
            setLanguage: async (lang) => {
                await window.i18n.setLanguage(lang);
                this.currentLang = window.i18n.currentLang;
            }
        }));
        
        // Magic property
        Alpine.magic('t', () => {
            return (key, replacements) => window.i18n.t(key, replacements);
        });
    });
}