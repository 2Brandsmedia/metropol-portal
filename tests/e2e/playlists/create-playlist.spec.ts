import { test, expect } from '../../fixtures/auth.fixture';
import { DashboardPage } from '../../page-objects/DashboardPage';
import { PlaylistPage } from '../../page-objects/PlaylistPage';

/**
 * Playlist-Erstellung E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Playlist-Erstellung', () => {
  let dashboardPage: DashboardPage;
  let playlistPage: PlaylistPage;

  test.beforeEach(async ({ authenticatedPage }) => {
    dashboardPage = new DashboardPage(authenticatedPage);
    playlistPage = new PlaylistPage(authenticatedPage);
    await dashboardPage.goto();
  });

  test('Neue Playlist mit 5 Stopps erstellen', async ({ authenticatedPage }) => {
    const initialCount = await dashboardPage.getPlaylistCount();
    
    await dashboardPage.createNewPlaylist();
    
    // Playlist-Details eingeben
    await playlistPage.createPlaylist('Montag Route Nord', [
      { address: 'Alexanderplatz 1, 10178 Berlin', workTime: 30, notes: 'Haupteingang benutzen' },
      { address: 'Potsdamer Platz 1, 10785 Berlin', workTime: 45 },
      { address: 'Kurfürstendamm 21, 10719 Berlin', workTime: 20 },
      { address: 'Friedrichstraße 43-45, 10117 Berlin', workTime: 30 },
      { address: 'Karl-Marx-Allee 33, 10178 Berlin', workTime: 25 }
    ]);
    
    // Überprüfe Weiterleitung zum Dashboard
    await expect(authenticatedPage).toHaveURL('/dashboard');
    
    // Überprüfe, dass Playlist erstellt wurde
    const newCount = await dashboardPage.getPlaylistCount();
    expect(newCount).toBe(initialCount + 1);
  });

  test('Playlist mit maximal 20 Stopps', async ({ authenticatedPage }) => {
    await dashboardPage.createNewPlaylist();
    
    const stops = Array.from({ length: 20 }, (_, i) => ({
      address: `Teststraße ${i + 1}, 10115 Berlin`,
      workTime: 15 + (i % 4) * 5 // 15, 20, 25, 30 Minuten
    }));
    
    await playlistPage.playlistTitle.fill('Maximale Route Test');
    
    for (const stop of stops) {
      await playlistPage.addStop(stop.address, stop.workTime);
    }
    
    // Versuche 21. Stopp hinzuzufügen
    await playlistPage.addStopButton.click();
    
    // Sollte Limit-Warnung zeigen
    await expect(authenticatedPage.locator('.alert-warning')).toContainText('Maximal 20 Stopps');
    
    // Trotzdem Route berechnen und speichern
    await playlistPage.calculateRoute();
    await playlistPage.save();
    
    await expect(authenticatedPage).toHaveURL('/dashboard');
  });

  test('Playlist mit ungültiger Adresse', async ({ authenticatedPage }) => {
    await dashboardPage.createNewPlaylist();
    
    await playlistPage.playlistTitle.fill('Test ungültige Adresse');
    await playlistPage.addStop('Diese Adresse existiert nicht 999999', 30);
    
    // Geocoding sollte fehlschlagen
    await expect(authenticatedPage.locator('.geocoding-error')).toBeVisible();
    
    // Route-Berechnung sollte nicht möglich sein
    await expect(playlistPage.calculateRouteButton).toBeDisabled();
  });

  test('Leere Playlist kann nicht gespeichert werden', async ({ authenticatedPage }) => {
    await dashboardPage.createNewPlaylist();
    
    await playlistPage.playlistTitle.fill('Leere Playlist');
    
    // Ohne Stopps sollte Speichern deaktiviert sein
    await expect(playlistPage.saveButton).toBeDisabled();
    
    // Fehlermeldung sollte sichtbar sein
    await expect(authenticatedPage.locator('.alert-info')).toContainText('Mindestens ein Stopp erforderlich');
  });

  test('Duplikat-Check für Playlist-Namen', async ({ authenticatedPage }) => {
    // Erste Playlist erstellen
    await dashboardPage.createNewPlaylist();
    await playlistPage.createPlaylist('Eindeutiger Name', [
      { address: 'Alexanderplatz 1, 10178 Berlin', workTime: 30 }
    ]);
    
    // Zweite Playlist mit gleichem Namen
    await dashboardPage.createNewPlaylist();
    await playlistPage.playlistTitle.fill('Eindeutiger Name');
    await playlistPage.addStop('Potsdamer Platz 1, 10785 Berlin', 20);
    await playlistPage.calculateRoute();
    await playlistPage.save();
    
    // Sollte Warnung zeigen
    await expect(authenticatedPage.locator('.alert-warning')).toContainText('Name bereits vorhanden');
  });

  test('Arbeitszeit-Validierung', async ({ authenticatedPage }) => {
    await dashboardPage.createNewPlaylist();
    
    await playlistPage.playlistTitle.fill('Arbeitszeit Test');
    
    // Negative Arbeitszeit
    await playlistPage.addStopButton.click();
    const newStop = playlistPage.stopsList.locator('.stop-item').last();
    await newStop.locator('input[name="address"]').fill('Alexanderplatz 1, 10178 Berlin');
    await newStop.locator('input[name="work-time"]').fill('-10');
    
    // Sollte auf 0 korrigiert werden
    const workTimeValue = await newStop.locator('input[name="work-time"]').inputValue();
    expect(parseInt(workTimeValue)).toBeGreaterThanOrEqual(0);
    
    // Sehr hohe Arbeitszeit (über 8 Stunden)
    await newStop.locator('input[name="work-time"]').fill('500');
    
    // Sollte Warnung zeigen
    await expect(authenticatedPage.locator('.field-warning')).toContainText('Ungewöhnlich lange Arbeitszeit');
  });

  test('Notizen mit Sonderzeichen', async ({ authenticatedPage }) => {
    await dashboardPage.createNewPlaylist();
    
    const specialNotes = 'Test mit Sonderzeichen: äöü ÄÖÜ ß @#$%^&*()';
    
    await playlistPage.createPlaylist('Sonderzeichen Test', [
      { address: 'Alexanderplatz 1, 10178 Berlin', workTime: 30, notes: specialNotes }
    ]);
    
    // Playlist sollte erfolgreich gespeichert werden
    await expect(authenticatedPage).toHaveURL('/dashboard');
    
    // Öffne Playlist wieder
    await authenticatedPage.click('.playlist-card:last-child');
    
    // Notizen sollten korrekt angezeigt werden
    const savedNotes = await playlistPage.stopsList.locator('textarea[name="notes"]').first().inputValue();
    expect(savedNotes).toBe(specialNotes);
  });
});