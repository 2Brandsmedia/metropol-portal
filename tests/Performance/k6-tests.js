import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { randomString, randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

/**
 * K6 Load-Testing-Skripte für Metropol Portal
 * Realistische Benutzerszenarien und Performance-Metriken
 * Entwickelt von 2Brands Media GmbH
 */

// Custom Metrics
const loginDuration = new Trend('login_duration');
const routeCalculationDuration = new Trend('route_calculation_duration');
const playlistCreationDuration = new Trend('playlist_creation_duration');
const stopUpdateDuration = new Trend('stop_update_duration');
const errorRate = new Rate('error_rate');
const authFailures = new Counter('auth_failures');

// Basis-URL (über Umgebungsvariable konfigurierbar)
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// Test-Daten
const testUsers = [
  { username: 'admin@test.com', password: 'admin123', role: 'admin' },
  { username: 'field1@test.com', password: 'field123', role: 'field_worker' },
  { username: 'field2@test.com', password: 'field123', role: 'field_worker' },
  { username: 'supervisor@test.com', password: 'super123', role: 'supervisor' },
];

const testAddresses = [
  'Hauptstraße 1, 10115 Berlin',
  'Friedrichstraße 50, 10117 Berlin',
  'Unter den Linden 77, 10117 Berlin',
  'Alexanderplatz 5, 10178 Berlin',
  'Potsdamer Platz 1, 10785 Berlin',
  'Kurfürstendamm 100, 10711 Berlin',
  'Hackescher Markt 2, 10178 Berlin',
  'Gendarmenmarkt 1, 10117 Berlin',
  'Brandenburger Tor, 10117 Berlin',
  'Checkpoint Charlie, 10969 Berlin',
];

// Session-Verwaltung
let sessionToken = null;
let currentUser = null;

/**
 * Test-Setup - wird einmal zu Beginn ausgeführt
 */
export function setup() {
  console.log('🚀 Starte Load-Tests für Metropol Portal');
  console.log('Entwickelt von 2Brands Media GmbH');
  
  // Basis-Gesundheitscheck
  const healthCheck = http.get(`${BASE_URL}/api/health`);
  check(healthCheck, {
    'Gesundheitscheck erfolgreich': (r) => r.status === 200,
    'Server antwortet': (r) => r.status !== 0,
  });

  if (healthCheck.status !== 200) {
    console.error('❌ Server nicht erreichbar - Tests abgebrochen');
    return { skipTests: true };
  }

  return {
    baseUrl: BASE_URL,
    testUsers: testUsers,
    testAddresses: testAddresses,
    skipTests: false,
  };
}

/**
 * Hilfsfunktionen
 */

// Authentifizierung
function authenticate(user) {
  const loginStart = Date.now();
  
  const response = http.post(`${BASE_URL}/api/auth/login`, JSON.stringify({
    email: user.username,
    password: user.password,
  }), {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  });

  const duration = Date.now() - loginStart;
  loginDuration.add(duration);

  const loginSuccess = check(response, {
    'Login erfolgreich': (r) => r.status === 200,
    'Login unter 100ms': (r) => duration < 100, // Ziel aus CLAUDE.md
    'Session-Token erhalten': (r) => r.json('token') !== undefined,
  });

  if (!loginSuccess) {
    authFailures.add(1);
    errorRate.add(1);
    return null;
  }

  const responseData = response.json();
  sessionToken = responseData.token;
  currentUser = user;

  return {
    token: sessionToken,
    user: responseData.user,
  };
}

// Authentifizierte Anfrage
function authenticatedRequest(method, url, payload = null) {
  const headers = {
    'Authorization': `Bearer ${sessionToken}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  let response;
  if (method === 'GET') {
    response = http.get(url, { headers });
  } else if (method === 'POST') {
    response = http.post(url, JSON.stringify(payload), { headers });
  } else if (method === 'PUT') {
    response = http.put(url, JSON.stringify(payload), { headers });
  } else if (method === 'DELETE') {
    response = http.del(url, null, { headers });
  }

  return response;
}

// Zufällige Playlist-Daten generieren
function generatePlaylistData(stopsCount = 10) {
  const stops = [];
  const usedAddresses = new Set();

  for (let i = 0; i < stopsCount; i++) {
    let address;
    do {
      address = testAddresses[randomIntBetween(0, testAddresses.length - 1)];
    } while (usedAddresses.has(address) && usedAddresses.size < testAddresses.length);
    
    usedAddresses.add(address);

    stops.push({
      address: address,
      duration: randomIntBetween(15, 60), // 15-60 Minuten pro Stopp
      priority: randomIntBetween(1, 5),
      notes: `Test-Stopp ${i + 1} - ${randomString(20)}`,
    });
  }

  return {
    name: `Test-Playlist-${randomString(8)}`,
    description: `Automatisch generierte Playlist für Load-Test`,
    date: new Date().toISOString().split('T')[0],
    stops: stops,
  };
}

/**
 * Haupt-Test-Szenarien
 */

// Szenario 1: Außendienstmitarbeiter - Morgenroutine
export function fieldWorkerMorning() {
  const user = testUsers[randomIntBetween(1, 2)]; // field1 oder field2
  
  // 1. Anmeldung
  const auth = authenticate(user);
  if (!auth) return;

  sleep(randomIntBetween(2, 5)); // Realistische Denkzeit

  // 2. Dashboard laden
  const dashboardResponse = authenticatedRequest('GET', `${BASE_URL}/api/dashboard/stats`);
  check(dashboardResponse, {
    'Dashboard geladen': (r) => r.status === 200,
    'Dashboard unter 200ms': (r) => r.timings.duration < 200,
  });

  sleep(randomIntBetween(3, 8));

  // 3. Heutige Playlist abrufen
  const playlistsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists?date=today`);
  check(playlistsResponse, {
    'Playlists abgerufen': (r) => r.status === 200,
    'Playlist-Laden unter 150ms': (r) => r.timings.duration < 150,
  });

  const playlists = playlistsResponse.json();
  if (playlists && playlists.length > 0) {
    const playlist = playlists[0];
    
    sleep(randomIntBetween(5, 10));

    // 4. Route berechnen
    const routeStart = Date.now();
    const routeResponse = authenticatedRequest('POST', `${BASE_URL}/api/route/calculate`, {
      playlistId: playlist.id,
      optimizeFor: 'time',
      includeTraffic: true,
    });

    const routeDuration = Date.now() - routeStart;
    routeCalculationDuration.add(routeDuration);

    check(routeResponse, {
      'Route berechnet': (r) => r.status === 200,
      'Route unter 300ms': (r) => routeDuration < 300, // Ziel aus CLAUDE.md
      'Route enthält Stopps': (r) => r.json('route.stops').length > 0,
    });

    sleep(randomIntBetween(10, 15));

    // 5. Route starten (Status-Update)
    const startResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${playlist.id}`, {
      status: 'in_progress',
      startedAt: new Date().toISOString(),
    });

    check(startResponse, {
      'Route gestartet': (r) => r.status === 200,
    });
  }

  sleep(randomIntBetween(5, 10));

  // 6. Abmeldung
  const logoutResponse = authenticatedRequest('POST', `${BASE_URL}/api/auth/logout`);
  check(logoutResponse, {
    'Abmeldung erfolgreich': (r) => r.status === 200,
  });
}

// Szenario 2: Aktiver Außendienstmitarbeiter
export function fieldWorkerActive() {
  const user = testUsers[randomIntBetween(1, 2)];
  
  const auth = authenticate(user);
  if (!auth) return;

  // Aktuelle Playlist finden
  const playlistsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists?status=in_progress`);
  const playlists = playlistsResponse.json();

  if (playlists && playlists.length > 0) {
    const playlist = playlists[0];
    
    // Stopps der Playlist abrufen
    const stopsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists/${playlist.id}/stops`);
    const stops = stopsResponse.json();

    if (stops && stops.length > 0) {
      // Zufälligen offenen Stopp auswählen
      const openStops = stops.filter(stop => stop.status === 'pending');
      if (openStops.length > 0) {
        const stop = openStops[randomIntBetween(0, openStops.length - 1)];
        
        sleep(randomIntBetween(1, 3));

        // Stopp als erledigt markieren
        const updateStart = Date.now();
        const updateResponse = authenticatedRequest('PUT', `${BASE_URL}/api/stops/${stop.id}/status`, {
          status: 'completed',
          completedAt: new Date().toISOString(),
          actualDuration: randomIntBetween(10, 45),
        });

        const updateDuration = Date.now() - updateStart;
        stopUpdateDuration.add(updateDuration);

        check(updateResponse, {
          'Stopp aktualisiert': (r) => r.status === 200,
          'Update unter 100ms': (r) => updateDuration < 100,
        });

        sleep(randomIntBetween(2, 5));

        // Optional: Notizen hinzufügen
        if (Math.random() < 0.6) { // 60% Wahrscheinlichkeit
          const notesResponse = authenticatedRequest('POST', `${BASE_URL}/api/stops/${stop.id}/notes`, {
            notes: `Stopp abgeschlossen - ${randomString(30)}`,
            timestamp: new Date().toISOString(),
          });

          check(notesResponse, {
            'Notizen gespeichert': (r) => r.status === 200,
          });

          sleep(randomIntBetween(10, 30)); // Zeit für Notiz-Eingabe
        }

        // Nächsten Stopp anzeigen
        sleep(randomIntBetween(2, 5));
        const nextStopResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists/${playlist.id}/next-stop`);
        check(nextStopResponse, {
          'Nächster Stopp abgerufen': (r) => r.status === 200,
        });
      }
    }
  }

  // Logout
  authenticatedRequest('POST', `${BASE_URL}/api/auth/logout`);
}

// Szenario 3: Administrator - Playlist-Management
export function adminPlaylistManagement() {
  const admin = testUsers[0]; // Admin-Benutzer
  
  const auth = authenticate(admin);
  if (!auth) return;

  sleep(randomIntBetween(3, 8));

  // Alle Playlists anzeigen
  const allPlaylistsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists`);
  check(allPlaylistsResponse, {
    'Alle Playlists geladen': (r) => r.status === 200,
  });

  sleep(randomIntBetween(5, 10));

  // Neue Playlist erstellen (70% Wahrscheinlichkeit)
  if (Math.random() < 0.7) {
    const stopsCount = randomIntBetween(5, 20); // Bis zu 20 Stopps
    const playlistData = generatePlaylistData(stopsCount);

    const createStart = Date.now();
    const createResponse = authenticatedRequest('POST', `${BASE_URL}/api/playlists`, playlistData);
    const createDuration = Date.now() - createStart;
    
    playlistCreationDuration.add(createDuration);

    const createSuccess = check(createResponse, {
      'Playlist erstellt': (r) => r.status === 201,
      'Erstellung unter 500ms': (r) => createDuration < 500,
      'Playlist-ID erhalten': (r) => r.json('id') !== undefined,
    });

    if (createSuccess) {
      const newPlaylist = createResponse.json();
      
      sleep(randomIntBetween(20, 40));

      // Route optimieren
      const optimizeResponse = authenticatedRequest('POST', `${BASE_URL}/api/route/optimize`, {
        playlistId: newPlaylist.id,
        algorithm: 'genetic',
        includeTraffic: true,
      });

      check(optimizeResponse, {
        'Route optimiert': (r) => r.status === 200,
        'Optimierung unter 800ms': (r) => r.timings.duration < 800,
      });

      sleep(randomIntBetween(10, 20));

      // Playlist einem Benutzer zuweisen (75% Wahrscheinlichkeit)
      if (Math.random() < 0.75) {
        const fieldWorker = testUsers[randomIntBetween(1, 2)];
        const assignResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${newPlaylist.id}`, {
          assignedTo: fieldWorker.username,
          assignedAt: new Date().toISOString(),
        });

        check(assignResponse, {
          'Playlist zugewiesen': (r) => r.status === 200,
        });
      }
    }

    sleep(randomIntBetween(5, 10));
  }

  // Bestehende Playlist bearbeiten (80% Wahrscheinlichkeit)
  if (Math.random() < 0.8) {
    const existingPlaylistsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists?limit=5`);
    const existingPlaylists = existingPlaylistsResponse.json();

    if (existingPlaylists && existingPlaylists.length > 0) {
      const playlist = existingPlaylists[randomIntBetween(0, existingPlaylists.length - 1)];
      
      sleep(randomIntBetween(5, 15));

      // Playlist-Details laden
      const detailsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists/${playlist.id}`);
      check(detailsResponse, {
        'Playlist-Details geladen': (r) => r.status === 200,
      });

      sleep(randomIntBetween(20, 40));

      // Playlist aktualisieren
      const updateData = {
        name: playlist.name + ' (aktualisiert)',
        description: playlist.description + ' - Bearbeitet am ' + new Date().toLocaleDateString('de-DE'),
      };

      const updateResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${playlist.id}`, updateData);
      check(updateResponse, {
        'Playlist aktualisiert': (r) => r.status === 200,
      });
    }
  }

  sleep(randomIntBetween(5, 10));

  // Logout
  authenticatedRequest('POST', `${BASE_URL}/api/auth/logout`);
}

// Szenario 4: Supervisor - Überwachung
export function supervisorMonitoring() {
  const supervisor = testUsers[3]; // Supervisor
  
  const auth = authenticate(supervisor);
  if (!auth) return;

  sleep(randomIntBetween(5, 10));

  // Team-Fortschritt anzeigen
  const progressResponse = authenticatedRequest('GET', `${BASE_URL}/api/reports/progress`);
  check(progressResponse, {
    'Fortschritts-Bericht geladen': (r) => r.status === 200,
    'Bericht unter 300ms': (r) => r.timings.duration < 300,
  });

  sleep(randomIntBetween(10, 20));

  // Individuelle Fortschritte prüfen (80% Wahrscheinlichkeit)
  if (Math.random() < 0.8) {
    const fieldWorkers = testUsers.filter(u => u.role === 'field_worker');
    
    for (const worker of fieldWorkers) {
      const individualProgressResponse = authenticatedRequest('GET', 
        `${BASE_URL}/api/reports/progress?user=${worker.username}`);
      
      check(individualProgressResponse, {
        'Individueller Fortschritt geladen': (r) => r.status === 200,
      });

      sleep(randomIntBetween(5, 15));
    }
  }

  // Performance-Berichte generieren (60% Wahrscheinlichkeit)
  if (Math.random() < 0.6) {
    sleep(randomIntBetween(15, 30));

    const performanceResponse = authenticatedRequest('GET', 
      `${BASE_URL}/api/reports/performance?period=today`);
    
    check(performanceResponse, {
      'Performance-Bericht generiert': (r) => r.status === 200,
      'Bericht-Generierung unter 1s': (r) => r.timings.duration < 1000,
    });
  }

  sleep(randomIntBetween(10, 20));

  // Logout
  authenticatedRequest('POST', `${BASE_URL}/api/auth/logout`);
}

/**
 * Standard-Test-Funktionen für verschiedene Load-Test-Modi
 */

// Standard-Test mit gemischten Szenarien
export default function() {
  const scenario = Math.random();
  
  if (scenario < 0.4) {
    fieldWorkerActive(); // 40% - Häufigste Nutzung
  } else if (scenario < 0.7) {
    fieldWorkerMorning(); // 30% - Morgenroutine
  } else if (scenario < 0.9) {
    adminPlaylistManagement(); // 20% - Admin-Aufgaben
  } else {
    supervisorMonitoring(); // 10% - Überwachung
  }
}

/**
 * Test-Optionen werden aus externer Konfiguration geladen
 */
export let options = {
  stages: [
    { duration: '2m', target: 10 },  // Ramp-up
    { duration: '5m', target: 15 },  // Stay at 15 users
    { duration: '2m', target: 20 },  // Ramp-up to 20 users
    { duration: '5m', target: 20 },  // Stay at 20 users
    { duration: '2m', target: 0 },   // Ramp-down
  ],
  
  thresholds: {
    // Allgemeine Performance-Ziele
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.05'],
    
    // Spezifische Ziele aus CLAUDE.md
    login_duration: ['p(95)<100'],           // Login unter 100ms
    route_calculation_duration: ['p(95)<300'], // Route unter 300ms
    stop_update_duration: ['p(95)<100'],     // Stopp-Update unter 100ms
    
    // Zuverlässigkeit
    error_rate: ['rate<0.05'],
    auth_failures: ['count<10'],
    
    // Durchsatz
    http_reqs: ['rate>50'], // Mindestens 50 Requests/Sekunde
  },
  
  // Browser-ähnliches Verhalten
  userAgent: 'MetropolPortal-LoadTest/1.0 (2Brands Media GmbH)',
  
  // Erweiterte Optionen
  setupTimeout: '30s',
  teardownTimeout: '30s',
  noConnectionReuse: false,
  noVUConnectionReuse: false,
};

/**
 * Teardown - Aufräumen nach Tests
 */
export function teardown(data) {
  if (data.skipTests) {
    console.log('⏭️  Tests wurden übersprungen');
    return;
  }

  console.log('🏁 Load-Tests abgeschlossen');
  console.log('📊 Berichte werden in k6-Ausgabe angezeigt');
  console.log('Entwickelt von 2Brands Media GmbH');
}