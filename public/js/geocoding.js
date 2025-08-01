/**
 * Geocoding JavaScript für Frontend-Integration
 * 
 * @author 2Brands Media GmbH
 */

window.Geocoding = (function() {
    'use strict';
    
    let debounceTimer = null;
    const DEBOUNCE_DELAY = 500; // ms
    
    /**
     * Geokodiert eine einzelne Adresse
     */
    async function geocode(address) {
        try {
            const response = await fetch('/api/geo/geocode', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ address })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Geokodierung fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Geocoding error:', error);
            return null;
        }
    }
    
    /**
     * Geokodiert mehrere Adressen
     */
    async function geocodeBatch(addresses) {
        try {
            const response = await fetch('/api/geo/batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ addresses })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Batch-Geokodierung fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Batch geocoding error:', error);
            return [];
        }
    }
    
    /**
     * Reverse Geocoding
     */
    async function reverseGeocode(latitude, longitude) {
        try {
            const response = await fetch('/api/geo/reverse', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ latitude, longitude })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Reverse Geokodierung fehlgeschlagen');
            }
            
            return data.data;
        } catch (error) {
            console.error('Reverse geocoding error:', error);
            return null;
        }
    }
    
    /**
     * Auto-Geokodierung für Adressfelder mit Debouncing
     */
    function attachToAddressField(inputElement, options = {}) {
        const {
            onSuccess = null,
            onError = null,
            latitudeField = null,
            longitudeField = null,
            mapContainer = null,
            minLength = 10
        } = options;
        
        inputElement.addEventListener('input', function(e) {
            const address = e.target.value.trim();
            
            // Debounce löschen
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            
            // Mindestlänge prüfen
            if (address.length < minLength) {
                return;
            }
            
            // Debounced Geocoding
            debounceTimer = setTimeout(async () => {
                // Loading-Indikator
                inputElement.classList.add('geocoding-loading');
                
                const result = await geocode(address);
                
                inputElement.classList.remove('geocoding-loading');
                
                if (result) {
                    // Erfolg
                    inputElement.classList.remove('geocoding-error');
                    inputElement.classList.add('geocoding-success');
                    
                    // Koordinaten in Felder schreiben
                    if (latitudeField) {
                        latitudeField.value = result.latitude;
                    }
                    if (longitudeField) {
                        longitudeField.value = result.longitude;
                    }
                    
                    // Karte aktualisieren
                    if (mapContainer && window.L) {
                        updateMap(mapContainer, result.latitude, result.longitude, address);
                    }
                    
                    // Callback
                    if (onSuccess) {
                        onSuccess(result);
                    }
                } else {
                    // Fehler
                    inputElement.classList.remove('geocoding-success');
                    inputElement.classList.add('geocoding-error');
                    
                    if (onError) {
                        onError('Adresse konnte nicht geokodiert werden');
                    }
                }
            }, DEBOUNCE_DELAY);
        });
    }
    
    /**
     * Karte mit Marker aktualisieren
     */
    function updateMap(mapContainer, latitude, longitude, address) {
        const mapId = mapContainer.id || 'geocoding-map';
        let map = window[mapId + '_instance'];
        let marker = window[mapId + '_marker'];
        
        // Karte erstellen falls nicht vorhanden
        if (!map) {
            map = L.map(mapContainer).setView([latitude, longitude], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            window[mapId + '_instance'] = map;
        }
        
        // Marker aktualisieren
        if (marker) {
            marker.setLatLng([latitude, longitude]);
            marker.setPopupContent(address);
        } else {
            marker = L.marker([latitude, longitude])
                .addTo(map)
                .bindPopup(address)
                .openPopup();
            window[mapId + '_marker'] = marker;
        }
        
        // Karte zentrieren
        map.setView([latitude, longitude], 15);
    }
    
    /**
     * Initialisierung für alle Adressfelder
     */
    function init() {
        // Alle Felder mit data-geocoding Attribut
        document.querySelectorAll('input[data-geocoding="true"]').forEach(input => {
            const options = {
                latitudeField: document.querySelector(input.dataset.latitudeField),
                longitudeField: document.querySelector(input.dataset.longitudeField),
                mapContainer: document.querySelector(input.dataset.mapContainer),
                minLength: parseInt(input.dataset.minLength) || 10
            };
            
            attachToAddressField(input, options);
        });
        
        // CSS für Loading/Success/Error States
        if (!document.getElementById('geocoding-styles')) {
            const style = document.createElement('style');
            style.id = 'geocoding-styles';
            style.textContent = `
                .geocoding-loading {
                    background-image: url('data:image/svg+xml;charset=utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg>');
                    background-repeat: no-repeat;
                    background-position: right 10px center;
                    background-size: 20px;
                    padding-right: 40px !important;
                }
                
                .geocoding-success {
                    border-color: #10b981 !important;
                    background-color: #f0fdf4 !important;
                }
                
                .geocoding-error {
                    border-color: #ef4444 !important;
                    background-color: #fef2f2 !important;
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Auto-Initialisierung bei DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Public API
    return {
        geocode,
        geocodeBatch,
        reverseGeocode,
        attachToAddressField,
        init
    };
})();