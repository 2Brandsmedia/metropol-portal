/**
 * Routes JavaScript für Routenberechnung und -visualisierung
 * 
 * @author 2Brands Media GmbH
 */

window.Routes = (function() {
    'use strict';
    
    let map = null;
    let routeLayer = null;
    let markersLayer = null;
    let trafficLayer = null;
    let markers = [];
    let onMarkerClickCallback = null;
    
    /**
     * Initialisiert die Karte
     */
    function initMap(containerId, options = {}) {
        const {
            center = [49.4875, 8.4660], // Mannheim
            zoom = 13
        } = options;
        
        // Karte erstellen
        map = L.map(containerId).setView(center, zoom);
        
        // OSM Tiles hinzufügen
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Layer für Route und Marker
        routeLayer = L.layerGroup().addTo(map);
        markersLayer = L.layerGroup().addTo(map);
        
        // Traffic-Layer Control hinzufügen
        if (options.enableTrafficControl) {
            addTrafficControl();
        }
        
        // Marker-Click Callback speichern
        if (options.onMarkerClick) {
            onMarkerClickCallback = options.onMarkerClick;
        }
        
        return map;
    }
    
    /**
     * Berechnet Route für Playlist
     */
    async function calculateRoute(playlistId, options = {}) {
        try {
            const response = await fetch(`/api/routes/calculate/${playlistId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(options)
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Routenberechnung fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Route calculation error:', error);
            throw error;
        }
    }
    
    /**
     * Optimiert Route für Playlist
     */
    async function optimizeRoute(playlistId, options = {}) {
        try {
            const response = await fetch(`/api/routes/optimize/${playlistId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(options)
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Routenoptimierung fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Route optimization error:', error);
            throw error;
        }
    }
    
    /**
     * Vorschau einer Route
     */
    async function previewRoute(coordinates, options = {}) {
        try {
            const response = await fetch('/api/routes/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    coordinates,
                    ...options
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Routenvorschau fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Route preview error:', error);
            throw error;
        }
    }
    
    /**
     * Fügt Traffic-Control zur Karte hinzu
     */
    function addTrafficControl() {
        const TrafficControl = L.Control.extend({
            options: {
                position: 'topright'
            },
            
            onAdd: function(map) {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                const button = L.DomUtil.create('a', 'traffic-toggle', container);
                
                button.href = '#';
                button.title = 'Live-Traffic anzeigen';
                button.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                `;
                
                L.DomEvent.on(button, 'click', function(e) {
                    L.DomEvent.preventDefault(e);
                    toggleTrafficLayer();
                    button.classList.toggle('active');
                });
                
                return container;
            }
        });
        
        map.addControl(new TrafficControl());
    }
    
    /**
     * Traffic-Layer ein/ausschalten
     */
    function toggleTrafficLayer() {
        if (trafficLayer) {
            map.removeLayer(trafficLayer);
            trafficLayer = null;
        } else {
            // Google Maps Traffic Tiles als Overlay
            trafficLayer = L.tileLayer('https://mt0.google.com/vt?lyrs=h@159000000,traffic|seconds_into_week:-1&style=3&x={x}&y={y}&z={z}', {
                maxZoom: 20,
                opacity: 0.7,
                attribution: 'Traffic © Google'
            });
            
            map.addLayer(trafficLayer);
        }
    }
    
    /**
     * Zeigt Route auf Karte an mit nummerierten Markern
     */
    function displayRouteWithNumbers(routeData, stopsData) {
        if (!map) {
            console.error('Karte nicht initialisiert');
            return;
        }
        
        // Alte Route und Marker löschen
        routeLayer.clearLayers();
        markersLayer.clearLayers();
        markers = [];
        
        // Route zeichnen
        if (routeData.geometry) {
            drawPolyline(routeData);
        }
        
        // Nummerierte Marker für jeden Stopp
        if (stopsData && stopsData.length > 0) {
            stopsData.forEach((stop, index) => {
                const marker = createNumberedMarker(stop, index + 1, routeData.stops ? routeData.stops[index] : null);
                markers.push(marker);
                markersLayer.addLayer(marker);
            });
            
            // Karte auf Route zentrieren
            const group = new L.featureGroup([routeLayer, markersLayer]);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
    
    /**
     * Zeichnet Polyline für Route
     */
    function drawPolyline(routeData) {
        let routeGeoJSON;
        
        // GeoJSON Geometry dekodieren wenn nötig
        if (typeof routeData.geometry === 'string') {
            routeGeoJSON = JSON.parse(routeData.geometry);
        } else {
            routeGeoJSON = routeData.geometry;
        }
        
        // Route zeichnen
        const routeLine = L.geoJSON(routeGeoJSON, {
            style: {
                color: '#4F46E5',
                weight: 4,
                opacity: 0.8
            }
        });
        
        routeLayer.addLayer(routeLine);
    }
    
    /**
     * Erstellt nummerierten Marker
     */
    function createNumberedMarker(stop, number, routeInfo) {
        const icon = L.divIcon({
            className: 'numbered-marker',
            html: `<div class="numbered-marker-inner">${number}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
        
        const marker = L.marker([stop.latitude, stop.longitude], { icon });
        
        // Popup mit Details
        const popupContent = `
            <div class="stop-popup">
                <h4>Stopp ${number}</h4>
                <p><strong>${stop.address}</strong></p>
                <p>Arbeitszeit: ${stop.work_duration} Min.</p>
                ${routeInfo && routeInfo.travel_duration_min ? `<p>Fahrt zum nächsten: ${routeInfo.travel_duration_min} Min.</p>` : ''}
                ${routeInfo && routeInfo.distance_m ? `<p>Distanz: ${(routeInfo.distance_m / 1000).toFixed(1)} km</p>` : ''}
                ${routeInfo && routeInfo.travel_duration_in_traffic_min ? `
                    <p class="traffic-info">Mit Verkehr: ${routeInfo.travel_duration_in_traffic_min} Min. 
                    <span class="traffic-indicator traffic-${routeInfo.traffic_severity || 'unknown'}"></span></p>
                ` : ''}
            </div>
        `;
        
        marker.bindPopup(popupContent);
        
        // Click Event
        marker.on('click', function() {
            if (onMarkerClickCallback) {
                onMarkerClickCallback(number - 1); // 0-basierter Index
            }
        });
        
        return marker;
    }
    
    /**
     * Legacy displayRoute für Kompatibilität
     */
    function displayRoute(routeData) {
        if (!map) {
            console.error('Karte nicht initialisiert');
            return;
        }
        
        // Alte Route löschen
        routeLayer.clearLayers();
        markersLayer.clearLayers();
        markers = [];
        
        // GeoJSON Geometry dekodieren wenn nötig
        if (routeData.geometry) {
            let routeGeoJSON;
            
            // Prüfen ob bereits GeoJSON oder encoded polyline
            if (typeof routeData.geometry === 'string') {
                routeGeoJSON = JSON.parse(routeData.geometry);
            } else {
                routeGeoJSON = routeData.geometry;
            }
            
            // Route zeichnen
            const routeLine = L.geoJSON(routeGeoJSON, {
                style: {
                    color: '#4F46E5',
                    weight: 4,
                    opacity: 0.8
                }
            });
            
            routeLayer.addLayer(routeLine);
        }
        
        // Stopps als Marker hinzufügen
        if (routeData.stops && routeData.stops.length > 0) {
            routeData.stops.forEach((stop, index) => {
                const marker = createStopMarker(stop, index + 1);
                markersLayer.addLayer(marker);
            });
            
            // Karte auf Route zentrieren
            const group = new L.featureGroup([routeLayer, markersLayer]);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
    
    /**
     * Erstellt Marker für Stopp
     */
    function createStopMarker(stop, position) {
        const icon = L.divIcon({
            className: 'stop-marker',
            html: `<div class="stop-marker-inner">${position}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        const marker = L.marker([stop.latitude, stop.longitude], { icon });
        
        // Popup mit Details
        const popupContent = `
            <div class="stop-popup">
                <h4>Stopp ${position}</h4>
                <p><strong>${stop.address}</strong></p>
                <p>Arbeitszeit: ${stop.work_duration_min || stop.work_duration} Min.</p>
                ${stop.travel_duration_min ? `<p>Fahrt zum nächsten: ${stop.travel_duration_min} Min.</p>` : ''}
                ${stop.distance_m ? `<p>Distanz: ${(stop.distance_m / 1000).toFixed(1)} km</p>` : ''}
            </div>
        `;
        
        marker.bindPopup(popupContent);
        
        return marker;
    }
    
    /**
     * Zeigt Routen-Statistiken an
     */
    function displayStats(containerId, routeData) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Traffic-Schweregrad zu CSS-Klasse
        const trafficClass = routeData.traffic_severity ? `traffic-${routeData.traffic_severity}` : '';
        
        const statsHTML = `
            <div class="route-stats">
                <div class="stat-item">
                    <div class="stat-label">${window.i18n?.t('playlist.total_distance') || 'Gesamtstrecke'}</div>
                    <div class="stat-value">${routeData.total_distance_km} km</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">${window.i18n?.t('playlist.total_time') || 'Fahrzeit'}</div>
                    <div class="stat-value">${formatDuration(routeData.total_duration_min)}</div>
                </div>
                ${routeData.total_duration_in_traffic_min ? `
                    <div class="stat-item ${trafficClass}">
                        <div class="stat-label">${window.i18n?.t('map.traffic.with_traffic') || 'Mit Verkehr'}</div>
                        <div class="stat-value">${formatDuration(routeData.total_duration_in_traffic_min)}</div>
                        ${routeData.traffic_delay_min > 0 ? `
                            <div class="stat-extra">+${routeData.traffic_delay_min} ${window.i18n?.t('units.minutes_abbr') || 'Min.'} ${window.i18n?.t('map.traffic.delay') || 'Verzögerung'}</div>
                        ` : ''}
                    </div>
                ` : ''}
                ${routeData.savings ? `
                    <div class="stat-item success">
                        <div class="stat-label">${window.i18n?.t('playlist.savings') || 'Ersparnis'}</div>
                        <div class="stat-value">${routeData.savings.duration_saved_min} ${window.i18n?.t('units.minutes_abbr') || 'Min.'} (${routeData.savings.percentage_saved}%)</div>
                    </div>
                ` : ''}
                ${routeData.using_google_maps ? `
                    <div class="stat-item info">
                        <div class="stat-label">${window.i18n?.t('map.traffic.live_traffic') || 'Live-Traffic'}</div>
                        <div class="stat-value">
                            <span class="traffic-indicator ${trafficClass}"></span>
                            ${getTrafficSeverityText(routeData.traffic_severity)}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
        
        container.innerHTML = statsHTML;
    }
    
    /**
     * Formatiert Dauer
     */
    function formatDuration(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        
        if (hours > 0) {
            return `${hours} Std. ${mins} Min.`;
        }
        return `${mins} Min.`;
    }
    
    /**
     * Route-Widget für Playlist-Seite
     */
    function createRouteWidget(playlistId, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Widget HTML
        container.innerHTML = `
            <div class="route-widget">
                <div class="route-actions">
                    <button id="calculate-route" class="btn btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Route berechnen
                    </button>
                    <button id="optimize-route" class="btn btn-secondary" disabled>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                        Route optimieren
                    </button>
                </div>
                <div id="route-stats" class="route-stats-container"></div>
                <div id="route-map" class="route-map"></div>
            </div>
        `;
        
        // Event-Listener
        document.getElementById('calculate-route').addEventListener('click', async () => {
            try {
                showLoading('calculate-route');
                const routeData = await calculateRoute(playlistId);
                displayStats('route-stats', routeData);
                initMap('route-map', { enableTrafficControl: true });
                displayRoute(routeData);
                document.getElementById('optimize-route').disabled = false;
            } catch (error) {
                showError(error.message);
            } finally {
                hideLoading('calculate-route');
            }
        });
        
        document.getElementById('optimize-route').addEventListener('click', async () => {
            try {
                showLoading('optimize-route');
                const routeData = await optimizeRoute(playlistId);
                displayStats('route-stats', routeData);
                displayRoute(routeData);
                showSuccess('Route erfolgreich optimiert!');
            } catch (error) {
                showError(error.message);
            } finally {
                hideLoading('optimize-route');
            }
        });
    }
    
    /**
     * Aktualisiert Traffic-Daten
     */
    async function refreshTrafficData(playlistId) {
        try {
            const response = await fetch(`/api/routes/traffic/${playlistId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Traffic-Update fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Traffic refresh error:', error);
            throw error;
        }
    }
    
    /**
     * Zeigt Loading-State
     */
    function showLoading(buttonId) {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = true;
            button.classList.add('loading');
            // Spinner hinzufügen
            const icon = button.querySelector('svg');
            if (icon) {
                icon.classList.add('animate-spin');
            }
        }
    }
    
    /**
     * Versteckt Loading-State
     */
    function hideLoading(buttonId) {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = false;
            button.classList.remove('loading');
            // Spinner entfernen
            const icon = button.querySelector('svg');
            if (icon) {
                icon.classList.remove('animate-spin');
            }
        }
    }
    
    /**
     * Zeigt Erfolgsmeldung
     */
    function showSuccess(message) {
        // Toast-Notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-up';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('animate-fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    /**
     * Zeigt Fehlermeldung
     */
    function showError(message) {
        // Toast-Notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-up';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('animate-fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    /**
     * Übersetzt Traffic-Schweregrad
     */
    function getTrafficSeverityText(severity) {
        const translations = {
            'low': window.i18n?.t('map.traffic.low') || 'Wenig Verkehr',
            'medium': window.i18n?.t('map.traffic.medium') || 'Mäßiger Verkehr',
            'high': window.i18n?.t('map.traffic.high') || 'Viel Verkehr',
            'severe': window.i18n?.t('map.traffic.severe') || 'Sehr viel Verkehr',
            'unknown': window.i18n?.t('map.traffic.unknown') || 'Unbekannt'
        };
        return translations[severity] || severity;
    }
    
    /**
     * Zeichnet Route mit Traffic-Farben
     */
    function drawRouteWithTraffic(routeData) {
        if (!routeData.segments || !routeData.geometry) {
            return;
        }
        
        // Route in Segmente aufteilen und mit Traffic-Farben zeichnen
        routeData.segments.forEach((segment, index) => {
            if (segment.traffic_severity) {
                const color = getTrafficColor(segment.traffic_severity);
                // Hier würde man idealerweise die Segment-Geometrie haben
                // Für jetzt nutzen wir eine einheitliche Farbe für die ganze Route
            }
        });
    }
    
    /**
     * Gibt Farbe für Traffic-Schweregrad zurück
     */
    function getTrafficColor(severity) {
        const colors = {
            'low': '#22c55e',    // Grün
            'medium': '#f59e0b', // Orange
            'high': '#ef4444',   // Rot
            'severe': '#991b1b', // Dunkelrot
            'unknown': '#6b7280' // Grau
        };
        return colors[severity] || colors.unknown;
    }
    
    // CSS für Marker und Traffic-Controls
    if (!document.getElementById('routes-styles')) {
        const style = document.createElement('style');
        style.id = 'routes-styles';
        style.textContent = `
            .stop-marker {
                background: transparent;
            }
            
            .stop-marker-inner {
                width: 30px;
                height: 30px;
                background: #4F46E5;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 14px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                border: 2px solid white;
            }
            
            .numbered-marker {
                background: transparent;
            }
            
            .numbered-marker-inner {
                width: 32px;
                height: 32px;
                background: #4F46E5;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 16px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                border: 3px solid white;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .numbered-marker-inner:hover {
                transform: scale(1.1);
                box-shadow: 0 4px 8px rgba(0,0,0,0.4);
            }
            
            .stop-popup {
                min-width: 200px;
            }
            
            .stop-popup h4 {
                margin: 0 0 10px 0;
                font-size: 16px;
                font-weight: bold;
            }
            
            .stop-popup p {
                margin: 5px 0;
                font-size: 14px;
            }
            
            .route-widget {
                background: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .route-actions {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
            }
            
            .route-map {
                height: 400px;
                border-radius: 8px;
                overflow: hidden;
                margin-top: 20px;
            }
            
            .route-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            
            .stat-item {
                text-align: center;
                padding: 15px;
                background: #F3F4F6;
                border-radius: 8px;
            }
            
            .stat-item.success {
                background: #D1FAE5;
                color: #065F46;
            }
            
            .stat-label {
                font-size: 12px;
                color: #6B7280;
                margin-bottom: 5px;
            }
            
            .stat-value {
                font-size: 20px;
                font-weight: bold;
                color: #111827;
            }
            
            .btn.loading {
                opacity: 0.6;
                cursor: not-allowed;
            }
            
            .traffic-toggle {
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: white;
                border-radius: 4px;
                transition: all 0.2s;
            }
            
            .traffic-toggle:hover {
                background: #f3f4f6;
            }
            
            .traffic-toggle.active {
                background: #4F46E5;
                color: white;
            }
            
            .traffic-toggle svg {
                width: 20px;
                height: 20px;
            }
            
            .traffic-indicator {
                display: inline-block;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-right: 5px;
            }
            
            .traffic-indicator.traffic-low {
                background: #22c55e;
            }
            
            .traffic-indicator.traffic-medium {
                background: #f59e0b;
            }
            
            .traffic-indicator.traffic-high {
                background: #ef4444;
            }
            
            .traffic-indicator.traffic-severe {
                background: #991b1b;
            }
            
            .stat-item.traffic-high .stat-value {
                color: #ef4444;
            }
            
            .stat-item.traffic-severe .stat-value {
                color: #991b1b;
            }
            
            .animate-spin {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .animate-slide-up {
                animation: slideUp 0.3s ease-out;
            }
            
            @keyframes slideUp {
                from { 
                    transform: translateY(100%);
                    opacity: 0;
                }
                to { 
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .animate-fade-out {
                animation: fadeOut 0.3s ease-out forwards;
            }
            
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            
            .traffic-info {
                display: flex;
                align-items: center;
                gap: 5px;
                margin-top: 5px;
                font-weight: 500;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Public API
    return {
        initMap,
        calculateRoute,
        optimizeRoute,
        previewRoute,
        displayRoute,
        displayRouteWithNumbers,
        displayStats,
        createRouteWidget,
        formatDuration,
        toggleTrafficLayer,
        getTrafficColor,
        getTrafficSeverityText,
        refreshTrafficData,
        showLoading,
        hideLoading,
        showSuccess,
        showError,
        markers
    };
})();