import { test, expect } from '../../fixtures/auth.fixture';
import { DashboardPage } from '../../page-objects/DashboardPage';
import { PlaylistPage } from '../../page-objects/PlaylistPage';

/**
 * Maps & Navigation E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Maps & Navigation', () => {
  let dashboardPage: DashboardPage;
  let playlistPage: PlaylistPage;

  test.beforeEach(async ({ authenticatedPage }) => {
    dashboardPage = new DashboardPage(authenticatedPage);
    playlistPage = new PlaylistPage(authenticatedPage);
    await dashboardPage.goto();
  });

  test('Karte wird korrekt initialisiert', async ({ authenticatedPage }) => {
    await dashboardPage.waitForMapLoad();
    
    // Überprüfe Leaflet-Initialisierung
    const hasMap = await authenticatedPage.evaluate(() => {
      return window.L && window.L.map && document.querySelector('.leaflet-container') !== null;
    });
    
    expect(hasMap).toBe(true);
    
    // Überprüfe Standard-Zoom und Center (Berlin)
    const mapState = await authenticatedPage.evaluate(() => {
      const mapElement = document.querySelector('.leaflet-container');
      if (mapElement && mapElement._leaflet_id) {
        const map = window.L.map._instances[mapElement._leaflet_id];
        return {
          center: map.getCenter(),
          zoom: map.getZoom()
        };
      }
      return null;
    });
    
    expect(mapState).not.toBeNull();
    expect(mapState.center.lat).toBeCloseTo(52.52, 1); // Berlin Latitude
    expect(mapState.center.lng).toBeCloseTo(13.405, 1); // Berlin Longitude
  });

  test('Marker werden für Playlist-Stopps angezeigt', async ({ authenticatedPage }) => {
    // Erstelle Playlist mit Stopps
    await dashboardPage.createNewPlaylist();
    await playlistPage.createPlaylist('Map Test Route', [
      { address: 'Alexanderplatz 1, 10178 Berlin', workTime: 30 },
      { address: 'Potsdamer Platz 1, 10785 Berlin', workTime: 45 },
      { address: 'Brandenburger Tor, 10117 Berlin', workTime: 20 }
    ]);
    
    // Öffne Playlist
    await authenticatedPage.click('.playlist-card:last-child');
    await playlistPage.waitForPageLoad();
    
    // Warte auf Map und Marker
    await authenticatedPage.waitForFunction(() => {
      const markers = document.querySelectorAll('.leaflet-marker-icon');
      return markers.length >= 3;
    });
    
    // Überprüfe Anzahl der Marker
    const markerCount = await authenticatedPage.locator('.leaflet-marker-icon').count();
    expect(markerCount).toBeGreaterThanOrEqual(3);
  });

  test('Route wird auf Karte dargestellt', async ({ authenticatedPage }) => {
    // Öffne existierende Playlist mit Route
    await dashboardPage.openPlaylist(await getFirstPlaylistId(authenticatedPage));
    
    // Berechne Route
    await playlistPage.calculateRoute();
    
    // Warte auf Route-Polyline
    await authenticatedPage.waitForFunction(() => {
      const polylines = document.querySelectorAll('.leaflet-interactive');
      return polylines.length > 0;
    });
    
    // Route sollte sichtbar sein
    const routePath = await authenticatedPage.locator('path.leaflet-interactive').first();
    await expect(routePath).toBeVisible();
    
    // Route sollte Stil haben (z.B. blaue Farbe)
    const strokeColor = await routePath.evaluate((el) => {
      return window.getComputedStyle(el).stroke;
    });
    expect(strokeColor).toBeTruthy();
  });

  test('Marker-Popup zeigt Stopp-Informationen', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(await getFirstPlaylistId(authenticatedPage));
    
    // Klicke auf ersten Marker
    await authenticatedPage.click('.leaflet-marker-icon:first-child');
    
    // Popup sollte erscheinen
    await authenticatedPage.waitForSelector('.leaflet-popup-content', { state: 'visible' });
    
    const popupContent = await authenticatedPage.locator('.leaflet-popup-content').textContent();
    
    // Popup sollte relevante Informationen enthalten
    expect(popupContent).toContain('Stopp');
    expect(popupContent).toMatch(/\d+ Minuten/); // Arbeitszeit
  });

  test('Karten-Zoom und Pan-Funktionalität', async ({ authenticatedPage }) => {
    await dashboardPage.waitForMapLoad();
    
    // Zoom In
    await authenticatedPage.click('.leaflet-control-zoom-in');
    await authenticatedPage.waitForTimeout(500); // Warte auf Animation
    
    const zoomAfterIn = await authenticatedPage.evaluate(() => {
      const mapElement = document.querySelector('.leaflet-container');
      if (mapElement && mapElement._leaflet_id) {
        const map = window.L.map._instances[mapElement._leaflet_id];
        return map.getZoom();
      }
      return 0;
    });
    
    // Zoom Out
    await authenticatedPage.click('.leaflet-control-zoom-out');
    await authenticatedPage.click('.leaflet-control-zoom-out');
    await authenticatedPage.waitForTimeout(500);
    
    const zoomAfterOut = await authenticatedPage.evaluate(() => {
      const mapElement = document.querySelector('.leaflet-container');
      if (mapElement && mapElement._leaflet_id) {
        const map = window.L.map._instances[mapElement._leaflet_id];
        return map.getZoom();
      }
      return 0;
    });
    
    expect(zoomAfterIn).toBeGreaterThan(zoomAfterOut);
  });

  test('Traffic-Layer Toggle', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(await getFirstPlaylistId(authenticatedPage));
    
    // Aktiviere Traffic
    await playlistPage.toggleTraffic();
    
    // Überprüfe, ob Traffic-Layer hinzugefügt wurde
    const hasTrafficLayer = await authenticatedPage.evaluate(() => {
      return document.querySelector('.leaflet-layer.traffic-layer') !== null ||
             document.querySelector('[class*="traffic"]') !== null;
    });
    
    expect(hasTrafficLayer).toBe(true);
    
    // Deaktiviere Traffic wieder
    await playlistPage.toggleTraffic();
    
    const hasTrafficLayerAfter = await authenticatedPage.evaluate(() => {
      return document.querySelector('.leaflet-layer.traffic-layer') !== null;
    });
    
    expect(hasTrafficLayerAfter).toBe(false);
  });

  test('Karte passt sich an Route an (Fit Bounds)', async ({ authenticatedPage }) => {
    await dashboardPage.createNewPlaylist();
    
    // Erstelle Route mit weit entfernten Punkten
    await playlistPage.playlistTitle.fill('Weitstrecken-Test');
    await playlistPage.addStop('Alexanderplatz 1, 10178 Berlin', 30);
    await playlistPage.addStop('Olympiastadion Berlin, 14053 Berlin', 45); // Weit entfernt
    
    await playlistPage.calculateRoute();
    
    // Karte sollte sich anpassen
    await authenticatedPage.waitForFunction(() => {
      const mapElement = document.querySelector('.leaflet-container');
      if (mapElement && mapElement._leaflet_id) {
        const map = window.L.map._instances[mapElement._leaflet_id];
        const bounds = map.getBounds();
        // Überprüfe, ob Bounds beide Punkte enthalten
        return bounds.getSouthWest().lat < 52.5 && bounds.getNorthEast().lat > 52.5;
      }
      return false;
    });
    
    const mapBounds = await authenticatedPage.evaluate(() => {
      const mapElement = document.querySelector('.leaflet-container');
      if (mapElement && mapElement._leaflet_id) {
        const map = window.L.map._instances[mapElement._leaflet_id];
        const bounds = map.getBounds();
        return {
          sw: bounds.getSouthWest(),
          ne: bounds.getNorthEast()
        };
      }
      return null;
    });
    
    expect(mapBounds).not.toBeNull();
    // Bounds sollten beide Punkte umfassen
    expect(mapBounds.ne.lat - mapBounds.sw.lat).toBeGreaterThan(0.05);
  });

  test('Vollbild-Modus für Karte', async ({ authenticatedPage }) => {
    await dashboardPage.waitForMapLoad();
    
    // Suche Vollbild-Button
    const fullscreenBtn = authenticatedPage.locator('[data-testid="map-fullscreen-btn"]');
    
    if (await fullscreenBtn.isVisible()) {
      await fullscreenBtn.click();
      
      // Karte sollte Vollbild sein
      await authenticatedPage.waitForFunction(() => {
        const mapContainer = document.querySelector('#map-container');
        return mapContainer?.classList.contains('fullscreen') || 
               document.fullscreenElement === mapContainer;
      });
      
      // ESC zum Beenden
      await authenticatedPage.keyboard.press('Escape');
      
      // Sollte nicht mehr Vollbild sein
      await authenticatedPage.waitForFunction(() => {
        return !document.fullscreenElement;
      });
    }
  });

  test('Geolocation - Aktueller Standort', async ({ authenticatedPage, context }) => {
    // Gewähre Geolocation-Berechtigung
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: 52.520008, longitude: 13.404954 }); // Berlin
    
    await dashboardPage.waitForMapLoad();
    
    // Klicke auf "Mein Standort" Button
    const locationBtn = authenticatedPage.locator('[data-testid="my-location-btn"]');
    
    if (await locationBtn.isVisible()) {
      await locationBtn.click();
      
      // Warte auf Standort-Marker
      await authenticatedPage.waitForFunction(() => {
        return document.querySelector('.user-location-marker') !== null;
      });
      
      // Karte sollte zum Standort zentriert sein
      const mapCenter = await authenticatedPage.evaluate(() => {
        const mapElement = document.querySelector('.leaflet-container');
        if (mapElement && mapElement._leaflet_id) {
          const map = window.L.map._instances[mapElement._leaflet_id];
          return map.getCenter();
        }
        return null;
      });
      
      expect(mapCenter.lat).toBeCloseTo(52.520008, 2);
      expect(mapCenter.lng).toBeCloseTo(13.404954, 2);
    }
  });
});

// Hilfsfunktion
async function getFirstPlaylistId(page) {
  const firstCard = page.locator('.playlist-card').first();
  return await firstCard.getAttribute('data-playlist-id') || 'test-id';
}