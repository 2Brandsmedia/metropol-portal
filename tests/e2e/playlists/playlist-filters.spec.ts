import { test, expect } from '../../fixtures/auth.fixture';
import { DashboardPage } from '../../page-objects/DashboardPage';

/**
 * Playlist-Filter und Suche E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Playlist-Filter und Suche', () => {
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ authenticatedPage }) => {
    dashboardPage = new DashboardPage(authenticatedPage);
    await dashboardPage.goto();
    
    // Erstelle Test-Playlists mit verschiedenen Eigenschaften
    // Dies würde normalerweise über API oder Fixtures gemacht
  });

  test('Playlists nach Datum filtern', async ({ authenticatedPage }) => {
    const dateFilter = authenticatedPage.locator('[data-testid="date-filter"]');
    
    // Filter für heute
    await dateFilter.selectOption('today');
    await authenticatedPage.waitForLoadState('networkidle');
    
    const visiblePlaylists = await dashboardPage.playlistGrid.locator('.playlist-card:visible').count();
    const allDates = await dashboardPage.playlistGrid.locator('.playlist-date').allTextContents();
    
    // Alle sichtbaren Playlists sollten von heute sein
    const today = new Date().toLocaleDateString('de-DE');
    allDates.forEach(date => {
      expect(date).toContain(today);
    });
  });

  test('Playlists nach Status filtern', async ({ authenticatedPage }) => {
    const statusFilter = authenticatedPage.locator('[data-testid="status-filter"]');
    
    // Filter für "In Bearbeitung"
    await statusFilter.selectOption('in-progress');
    await authenticatedPage.waitForLoadState('networkidle');
    
    const statusBadges = await dashboardPage.playlistGrid
      .locator('.playlist-card:visible .status-badge')
      .allTextContents();
    
    statusBadges.forEach(status => {
      expect(status).toContain('In Bearbeitung');
    });
  });

  test('Playlists nach Mitarbeiter filtern', async ({ authenticatedPage }) => {
    const employeeFilter = authenticatedPage.locator('[data-testid="employee-filter"]');
    
    // Wähle spezifischen Mitarbeiter
    await employeeFilter.selectOption('test.user@metropol.de');
    await authenticatedPage.waitForLoadState('networkidle');
    
    const assignedTo = await dashboardPage.playlistGrid
      .locator('.playlist-card:visible .assigned-to')
      .allTextContents();
    
    assignedTo.forEach(employee => {
      expect(employee).toContain('test.user@metropol.de');
    });
  });

  test('Volltextsuche in Playlists', async ({ authenticatedPage }) => {
    const searchInput = authenticatedPage.locator('[data-testid="playlist-search"]');
    
    await searchInput.fill('Alexanderplatz');
    await searchInput.press('Enter');
    
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Alle sichtbaren Playlists sollten "Alexanderplatz" enthalten
    const visibleCards = dashboardPage.playlistGrid.locator('.playlist-card:visible');
    const count = await visibleCards.count();
    
    for (let i = 0; i < count; i++) {
      const cardText = await visibleCards.nth(i).textContent();
      expect(cardText?.toLowerCase()).toContain('alexanderplatz');
    }
  });

  test('Kombinierte Filter', async ({ authenticatedPage }) => {
    // Datum + Status
    await authenticatedPage.locator('[data-testid="date-filter"]').selectOption('today');
    await authenticatedPage.locator('[data-testid="status-filter"]').selectOption('completed');
    
    await authenticatedPage.waitForLoadState('networkidle');
    
    const visibleCards = await dashboardPage.playlistGrid.locator('.playlist-card:visible').count();
    
    // Überprüfe, dass Filter kombiniert wirken
    if (visibleCards > 0) {
      const firstCard = dashboardPage.playlistGrid.locator('.playlist-card:visible').first();
      const date = await firstCard.locator('.playlist-date').textContent();
      const status = await firstCard.locator('.status-badge').textContent();
      
      expect(date).toContain(new Date().toLocaleDateString('de-DE'));
      expect(status).toContain('Abgeschlossen');
    }
  });

  test('Filter zurücksetzen', async ({ authenticatedPage }) => {
    // Setze mehrere Filter
    await authenticatedPage.locator('[data-testid="date-filter"]').selectOption('today');
    await authenticatedPage.locator('[data-testid="status-filter"]').selectOption('in-progress');
    await authenticatedPage.locator('[data-testid="playlist-search"]').fill('Test');
    
    const filteredCount = await dashboardPage.getPlaylistCount();
    
    // Reset-Button
    await authenticatedPage.click('[data-testid="reset-filters-btn"]');
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Alle Filter sollten zurückgesetzt sein
    const dateFilter = await authenticatedPage.locator('[data-testid="date-filter"]').inputValue();
    const statusFilter = await authenticatedPage.locator('[data-testid="status-filter"]').inputValue();
    const searchInput = await authenticatedPage.locator('[data-testid="playlist-search"]').inputValue();
    
    expect(dateFilter).toBe('all');
    expect(statusFilter).toBe('all');
    expect(searchInput).toBe('');
    
    // Mehr Playlists sollten sichtbar sein
    const totalCount = await dashboardPage.getPlaylistCount();
    expect(totalCount).toBeGreaterThanOrEqual(filteredCount);
  });

  test('Sortierung nach verschiedenen Kriterien', async ({ authenticatedPage }) => {
    const sortDropdown = authenticatedPage.locator('[data-testid="sort-dropdown"]');
    
    // Sortiere nach Datum (neueste zuerst)
    await sortDropdown.selectOption('date-desc');
    await authenticatedPage.waitForLoadState('networkidle');
    
    const dates = await dashboardPage.playlistGrid
      .locator('.playlist-date')
      .allTextContents();
    
    // Konvertiere zu Date-Objekten und überprüfe Sortierung
    const dateObjects = dates.map(d => new Date(d));
    for (let i = 1; i < dateObjects.length; i++) {
      expect(dateObjects[i-1].getTime()).toBeGreaterThanOrEqual(dateObjects[i].getTime());
    }
  });

  test('Pagination bei vielen Playlists', async ({ authenticatedPage }) => {
    // Annahme: Es gibt mehr als 20 Playlists
    const totalPlaylists = await authenticatedPage.locator('[data-testid="total-playlists"]').textContent();
    const total = parseInt(totalPlaylists || '0');
    
    if (total > 20) {
      // Erste Seite sollte 20 Items haben
      let visibleCount = await dashboardPage.getPlaylistCount();
      expect(visibleCount).toBe(20);
      
      // Gehe zur zweiten Seite
      await authenticatedPage.click('[data-testid="pagination-next"]');
      await authenticatedPage.waitForLoadState('networkidle');
      
      // URL sollte Page-Parameter haben
      expect(authenticatedPage.url()).toContain('page=2');
      
      // Andere Playlists sollten sichtbar sein
      const firstPlaylistId = await dashboardPage.playlistGrid
        .locator('.playlist-card')
        .first()
        .getAttribute('data-playlist-id');
      
      // Zurück zur ersten Seite
      await authenticatedPage.click('[data-testid="pagination-prev"]');
      await authenticatedPage.waitForLoadState('networkidle');
      
      const firstPlaylistIdPage1 = await dashboardPage.playlistGrid
        .locator('.playlist-card')
        .first()
        .getAttribute('data-playlist-id');
      
      expect(firstPlaylistId).not.toBe(firstPlaylistIdPage1);
    }
  });
});