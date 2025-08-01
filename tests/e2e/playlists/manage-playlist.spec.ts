import { test, expect } from '../../fixtures/auth.fixture';
import { DashboardPage } from '../../page-objects/DashboardPage';
import { PlaylistPage } from '../../page-objects/PlaylistPage';

/**
 * Playlist-Verwaltung E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Playlist-Verwaltung', () => {
  let dashboardPage: DashboardPage;
  let playlistPage: PlaylistPage;
  let testPlaylistId: string;

  test.beforeEach(async ({ authenticatedPage }) => {
    dashboardPage = new DashboardPage(authenticatedPage);
    playlistPage = new PlaylistPage(authenticatedPage);
    
    // Erstelle Test-Playlist
    await dashboardPage.goto();
    await dashboardPage.createNewPlaylist();
    await playlistPage.createPlaylist('Test Playlist', [
      { address: 'Alexanderplatz 1, 10178 Berlin', workTime: 30 },
      { address: 'Potsdamer Platz 1, 10785 Berlin', workTime: 45 },
      { address: 'Kurfürstendamm 21, 10719 Berlin', workTime: 20 }
    ]);
    
    // Hole Playlist-ID aus URL nach Speichern
    const playlistCard = authenticatedPage.locator('.playlist-card').last();
    testPlaylistId = await playlistCard.getAttribute('data-playlist-id') || '';
  });

  test('Playlist bearbeiten - Titel ändern', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    await playlistPage.playlistTitle.clear();
    await playlistPage.playlistTitle.fill('Geänderter Titel');
    await playlistPage.save();
    
    // Überprüfe Änderung im Dashboard
    await dashboardPage.goto();
    const updatedCard = authenticatedPage.locator(`[data-playlist-id="${testPlaylistId}"]`);
    await expect(updatedCard.locator('.playlist-title')).toContainText('Geänderter Titel');
  });

  test('Stopp hinzufügen zu bestehender Playlist', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    const initialStopCount = await playlistPage.getStopCount();
    
    await playlistPage.addStop('Brandenburger Tor, 10117 Berlin', 40, 'Touristengruppe beachten');
    await playlistPage.calculateRoute();
    await playlistPage.save();
    
    // Überprüfe neuen Stopp
    await dashboardPage.openPlaylist(testPlaylistId);
    const newStopCount = await playlistPage.getStopCount();
    expect(newStopCount).toBe(initialStopCount + 1);
  });

  test('Stopp entfernen', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    const initialStopCount = await playlistPage.getStopCount();
    
    // Entferne mittleren Stopp
    await playlistPage.removeStop(1);
    await playlistPage.calculateRoute();
    await playlistPage.save();
    
    // Überprüfe Anzahl
    await dashboardPage.openPlaylist(testPlaylistId);
    const newStopCount = await playlistPage.getStopCount();
    expect(newStopCount).toBe(initialStopCount - 1);
  });

  test('Stopps neu anordnen', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    // Hole ursprüngliche Reihenfolge
    const stopsBefore = await playlistPage.stopsList.locator('input[name="address"]').allTextContents();
    
    // Verschiebe ersten Stopp an letzte Position
    await playlistPage.reorderStop(0, 2);
    
    await playlistPage.calculateRoute();
    await playlistPage.save();
    
    // Überprüfe neue Reihenfolge
    await dashboardPage.openPlaylist(testPlaylistId);
    const stopsAfter = await playlistPage.stopsList.locator('input[name="address"]').allTextContents();
    
    expect(stopsAfter[0]).toBe(stopsBefore[1]);
    expect(stopsAfter[2]).toBe(stopsBefore[0]);
  });

  test('Stopp als erledigt markieren', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    // Markiere ersten und zweiten Stopp als erledigt
    await playlistPage.markStopComplete(0);
    await playlistPage.markStopComplete(1);
    await playlistPage.save();
    
    // Überprüfe Status
    await dashboardPage.goto();
    const stats = await dashboardPage.getStats();
    expect(stats.completedStops).toBeGreaterThanOrEqual(2);
    
    // Überprüfe visuelle Markierung
    await dashboardPage.openPlaylist(testPlaylistId);
    const firstStopCheckbox = playlistPage.stopsList.locator('.stop-item').first()
      .locator('[data-testid="complete-stop-checkbox"]');
    await expect(firstStopCheckbox).toBeChecked();
  });

  test('Route mit Live-Traffic neu berechnen', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    // Erste Route-Info ohne Traffic
    await playlistPage.calculateRoute();
    const routeInfoNoTraffic = await playlistPage.getRouteInfo();
    
    // Aktiviere Traffic
    await playlistPage.toggleTraffic();
    
    // Warte auf Traffic-Update
    await authenticatedPage.waitForResponse(resp => 
      resp.url().includes('/api/routes/traffic') && resp.status() === 200
    );
    
    const routeInfoWithTraffic = await playlistPage.getRouteInfo();
    
    // Traffic-Dauer sollte sich unterscheiden
    expect(routeInfoWithTraffic.trafficDuration).toBeGreaterThanOrEqual(routeInfoNoTraffic.duration);
  });

  test('Playlist löschen', async ({ authenticatedPage }) => {
    const initialCount = await dashboardPage.getPlaylistCount();
    
    await dashboardPage.openPlaylist(testPlaylistId);
    await playlistPage.delete();
    
    // Überprüfe Weiterleitung
    await expect(authenticatedPage).toHaveURL('/dashboard');
    
    // Überprüfe, dass Playlist gelöscht wurde
    const newCount = await dashboardPage.getPlaylistCount();
    expect(newCount).toBe(initialCount - 1);
    
    // Playlist sollte nicht mehr existieren
    await expect(authenticatedPage.locator(`[data-playlist-id="${testPlaylistId}"]`)).not.toBeVisible();
  });

  test('Änderungen verwerfen', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    const originalTitle = await playlistPage.playlistTitle.inputValue();
    
    // Mache Änderungen
    await playlistPage.playlistTitle.clear();
    await playlistPage.playlistTitle.fill('Verworfener Titel');
    await playlistPage.addStop('Neue Adresse 123, Berlin', 30);
    
    // Verwerfe Änderungen durch Navigation
    await authenticatedPage.click('[data-testid="cancel-btn"]');
    
    // Bestätige im Dialog
    await authenticatedPage.click('[data-testid="confirm-discard-btn"]');
    
    // Öffne Playlist erneut
    await dashboardPage.openPlaylist(testPlaylistId);
    
    // Titel sollte unverändert sein
    const currentTitle = await playlistPage.playlistTitle.inputValue();
    expect(currentTitle).toBe(originalTitle);
  });

  test('Performance: Route-Berechnung unter 300ms', async ({ authenticatedPage }) => {
    await dashboardPage.openPlaylist(testPlaylistId);
    
    const startTime = Date.now();
    await playlistPage.calculateRoute();
    const endTime = Date.now();
    
    const calculationTime = endTime - startTime;
    
    // Performance-Ziel aus CLAUDE.md
    expect(calculationTime).toBeLessThan(300);
  });
});