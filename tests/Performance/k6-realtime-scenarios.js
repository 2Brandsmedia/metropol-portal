import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter, Gauge } from 'k6/metrics';
import { randomString, randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

/**
 * K6 Realistische Tageszeit-Szenarien für Metropol Portal
 * Simuliert echte Benutzerverhalten zu verschiedenen Tageszeiten
 * Entwickelt von 2Brands Media GmbH
 */

// Custom Metrics für spezifische SLA-Tracking
const loginTime = new Trend('login_time');
const routeCalculationTime = new Trend('route_calculation_time');
const stopUpdateTime = new Trend('stop_update_time');
const trafficUpdateTime = new Trend('traffic_update_time');
const apiResponseTime = new Trend('api_response_time');

const authenticatedUsers = new Gauge('authenticated_users');
const activeRoutes = new Gauge('active_routes');
const routeOptimizations = new Counter('route_optimizations');
const trafficRequests = new Counter('traffic_requests');
const errorRate = new Rate('error_rate');
const slaViolations = new Counter('sla_violations');

// Basis-Konfiguration
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const SCENARIO = __ENV.SCENARIO || 'morningRush';

// Test-Benutzer mit realistischer Verteilung
const testUsers = {
  fieldWorkers: [
    { email: 'field1@test.com', password: 'field123', id: 1, region: 'Nord' },
    { email: 'field2@test.com', password: 'field123', id: 2, region: 'Süd' },
    { email: 'field3@test.com', password: 'field123', id: 3, region: 'Ost' },
    { email: 'field4@test.com', password: 'field123', id: 4, region: 'West' },
    { email: 'field5@test.com', password: 'field123', id: 5, region: 'Zentrum' },
    { email: 'field6@test.com', password: 'field123', id: 6, region: 'Nord' },
    { email: 'field7@test.com', password: 'field123', id: 7, region: 'Süd' },
    { email: 'field8@test.com', password: 'field123', id: 8, region: 'Ost' },
  ],
  admins: [
    { email: 'admin@test.com', password: 'admin123', id: 10, role: 'admin' },
    { email: 'admin2@test.com', password: 'admin123', id: 11, role: 'admin' },
  ],
  supervisors: [
    { email: 'supervisor@test.com', password: 'super123', id: 20, role: 'supervisor' },
    { email: 'supervisor2@test.com', password: 'super123', id: 21, role: 'supervisor' },
  ]
};

// Realistische Berliner Routen-Daten
const berlinRoutes = {
  nord: [
    'Alexanderplatz 5, 10178 Berlin',
    'Hackescher Markt 2, 10178 Berlin',
    'Prenzlauer Berg 15, 10405 Berlin',
    'Wedding Müllerstraße 100, 13353 Berlin',
    'Gesundbrunnen Center, 13357 Berlin'
  ],
  sued: [
    'Potsdamer Platz 1, 10785 Berlin',
    'Kreuzberg SO36, 10997 Berlin',
    'Tempelhof Airport, 12101 Berlin',
    'Neukölln Hermannplatz, 12051 Berlin',
    'Steglitz Schlossstraße, 12163 Berlin'
  ],
  ost: [
    'Friedrichshain Warschauer, 10243 Berlin',
    'Lichtenberg Frankfurter Allee, 10365 Berlin',
    'Karlshorst Treskowallee, 10318 Berlin',
    'Marzahn Eastgate, 12679 Berlin',
    'Hellersdorf Helle Mitte, 12627 Berlin'
  ],
  west: [
    'Kurfürstendamm 100, 10711 Berlin',
    'Charlottenburg Palace, 14059 Berlin',
    'Spandau Altstadt, 13597 Berlin',
    'Wilmersdorf Fehrbelliner, 10707 Berlin',
    'Zehlendorf Clayallee, 14195 Berlin'
  ],
  zentrum: [
    'Unter den Linden 77, 10117 Berlin',
    'Friedrichstraße 50, 10117 Berlin',
    'Gendarmenmarkt 1, 10117 Berlin',
    'Brandenburger Tor, 10117 Berlin',
    'Checkpoint Charlie, 10969 Berlin'
  ]
};

// Session-Management
let sessionToken = null;
let currentUser = null;
let userStats = {
  loginCount: 0,
  routeCalculations: 0,
  stopUpdates: 0,
  errors: 0
};

/**
 * Authentifizierung mit SLA-Tracking
 */
function authenticateUser(user) {
  const startTime = Date.now();
  
  const response = http.post(`${BASE_URL}/api/auth/login`, JSON.stringify({
    email: user.email,
    password: user.password,
  }), {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'User-Agent': `MetropolPortal-LoadTest-${SCENARIO}/1.0`
    },
    timeout: '10s'
  });

  const duration = Date.now() - startTime;
  loginTime.add(duration);
  apiResponseTime.add(duration);

  const loginSuccess = check(response, {
    'Login erfolgreich': (r) => r.status === 200,
    'Login SLA <100ms': (r) => duration < 100,
    'Session-Token erhalten': (r) => {
      try {
        return r.json('token') !== undefined;
      } catch {
        return false;
      }
    }
  });

  // SLA-Verletzung tracking
  if (duration >= 100) {
    slaViolations.add(1, { metric: 'login_time', target: '100ms' });
  }

  if (loginSuccess && response.status === 200) {
    try {
      const responseData = response.json();
      sessionToken = responseData.token;
      currentUser = user;
      authenticatedUsers.add(1);
      userStats.loginCount++;
      return true;
    } catch (e) {
      errorRate.add(1);
      userStats.errors++;
      return false;
    }
  }

  errorRate.add(1);
  userStats.errors++;
  return false;
}

/**
 * Authentifizierte API-Anfrage mit Retry-Logic
 */
function authenticatedRequest(method, url, payload = null, timeout = '5s') {
  const headers = {
    'Authorization': `Bearer ${sessionToken}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'User-Agent': `MetropolPortal-LoadTest-${SCENARIO}/1.0`
  };

  const startTime = Date.now();
  let response;

  try {
    if (method === 'GET') {
      response = http.get(url, { headers, timeout });
    } else if (method === 'POST') {
      response = http.post(url, JSON.stringify(payload), { headers, timeout });
    } else if (method === 'PUT') {
      response = http.put(url, JSON.stringify(payload), { headers, timeout });
    } else if (method === 'DELETE') {
      response = http.del(url, null, { headers, timeout });
    }

    const duration = Date.now() - startTime;
    apiResponseTime.add(duration);

    // Rate-Limiting-Behandlung
    if (response.status === 429) {
      const retryAfter = response.headers['Retry-After'] || 1;
      console.warn(`Rate limit erreicht, warte ${retryAfter}s`);
      sleep(parseInt(retryAfter));
      
      // Retry
      return authenticatedRequest(method, url, payload, timeout);
    }

    return response;
  } catch (error) {
    errorRate.add(1);
    userStats.errors++;
    console.error(`API Request Fehler: ${error}`);
    return null;
  }
}

/**
 * Szenario 1: Morning Rush (7-9 AM)
 * 80% der Tagesrouten werden erstellt, Field Workers starten ihre Touren
 */
export function morningRushScenario() {
  const userType = Math.random();
  let user;
  
  // 75% Field Workers, 20% Admins, 5% Supervisors
  if (userType < 0.75) {
    user = testUsers.fieldWorkers[randomIntBetween(0, testUsers.fieldWorkers.length - 1)];
    fieldWorkerMorningRoutine(user);
  } else if (userType < 0.95) {
    user = testUsers.admins[randomIntBetween(0, testUsers.admins.length - 1)];
    adminMorningPlaylistCreation(user);
  } else {
    user = testUsers.supervisors[randomIntBetween(0, testUsers.supervisors.length - 1)];
    supervisorMorningOverview(user);
  }
}

/**
 * Szenario 2: Lunch Update (12-1 PM)
 * Traffic-Updates für aktive Routen, kurze Status-Updates
 */
export function lunchUpdateScenario() {
  const userType = Math.random();
  let user;
  
  // 85% Field Workers (aktiv), 10% Admins, 5% Supervisors
  if (userType < 0.85) {
    user = testUsers.fieldWorkers[randomIntBetween(0, testUsers.fieldWorkers.length - 1)];
    fieldWorkerLunchUpdate(user);
  } else if (userType < 0.95) {
    user = testUsers.admins[randomIntBetween(0, testUsers.admins.length - 1)];
    adminLunchMonitoring(user);
  } else {
    user = testUsers.supervisors[randomIntBetween(0, testUsers.supervisors.length - 1)];
    supervisorLunchCheck(user);
  }
}

/**
 * Szenario 3: Evening Close (5-6 PM)
 * Status-Updates, Abschluss der Routen, Reports
 */
export function eveningCloseScenario() {
  const userType = Math.random();
  let user;
  
  // 60% Field Workers, 25% Admins, 15% Supervisors
  if (userType < 0.60) {
    user = testUsers.fieldWorkers[randomIntBetween(0, testUsers.fieldWorkers.length - 1)];
    fieldWorkerEveningClose(user);
  } else if (userType < 0.85) {
    user = testUsers.admins[randomIntBetween(0, testUsers.admins.length - 1)];
    adminEveningReports(user);
  } else {
    user = testUsers.supervisors[randomIntBetween(0, testUsers.supervisors.length - 1)];
    supervisorEveningReview(user);
  }
}

/**
 * Field Worker - Morgenroutine
 */
function fieldWorkerMorningRoutine(user) {
  if (!authenticateUser(user)) return;

  // 1. Dashboard laden
  sleep(randomIntBetween(2, 5));
  const dashboardResponse = authenticatedRequest('GET', `${BASE_URL}/api/dashboard/stats`);
  check(dashboardResponse, {
    'Dashboard geladen': (r) => r && r.status === 200,
    'Dashboard <200ms': (r) => r && r.timings.duration < 200
  });

  // 2. Heutige Playlist abrufen
  sleep(randomIntBetween(3, 8));
  const today = new Date().toISOString().split('T')[0];
  const playlistsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists?date=${today}&assigned_to=${user.id}`);
  
  check(playlistsResponse, {
    'Playlists abgerufen': (r) => r && r.status === 200,
    'Playlist-Laden <150ms': (r) => r && r.timings.duration < 150
  });

  if (playlistsResponse && playlistsResponse.status === 200) {
    let playlists;
    try {
      playlists = playlistsResponse.json();
    } catch (e) {
      errorRate.add(1);
      return;
    }

    if (playlists && playlists.length > 0) {
      const playlist = playlists[0];
      
      sleep(randomIntBetween(5, 10));

      // 3. Route berechnen (kritisch für Performance)
      const routeStart = Date.now();
      const routeResponse = authenticatedRequest('POST', `${BASE_URL}/api/routes/calculate/${playlist.id}`, {
        optimizeFor: Math.random() < 0.7 ? 'time' : 'distance',
        includeTraffic: true,
        avoidTolls: Math.random() < 0.3
      }, '15s');

      const routeDuration = Date.now() - routeStart;
      routeCalculationTime.add(routeDuration);
      userStats.routeCalculations++;
      routeOptimizations.add(1);

      const routeSuccess = check(routeResponse, {
        'Route berechnet': (r) => r && r.status === 200,
        'Route SLA <300ms': (r) => routeDuration < 300,
        'Route enthält Stopps': (r) => {
          try {
            return r && r.json('route.stops') && r.json('route.stops').length > 0;
          } catch {
            return false;
          }
        }
      });

      // SLA-Verletzung tracking
      if (routeDuration >= 300) {
        slaViolations.add(1, { metric: 'route_calculation', target: '300ms' });
      }

      if (routeSuccess) {
        activeRoutes.add(1);
        
        sleep(randomIntBetween(10, 15));

        // 4. Route starten
        const startResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${playlist.id}`, {
          status: 'in_progress',
          startedAt: new Date().toISOString(),
          currentLocation: berlinRoutes[user.region.toLowerCase()][0]
        });

        check(startResponse, {
          'Route gestartet': (r) => r && r.status === 200
        });
      }
    }
  }

  sleep(randomIntBetween(5, 10));
  logoutUser();
}

/**
 * Field Worker - Lunch Update (schnelle Updates während der Arbeit)
 */
function fieldWorkerLunchUpdate(user) {
  if (!authenticateUser(user)) return;

  // Aktive Playlist finden
  const activePlaylistResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists?status=in_progress&assigned_to=${user.id}`);
  
  if (activePlaylistResponse && activePlaylistResponse.status === 200) {
    let playlists;
    try {
      playlists = activePlaylistResponse.json();
    } catch (e) {
      errorRate.add(1);
      return;
    }

    if (playlists && playlists.length > 0) {
      const playlist = playlists[0];
      
      // Traffic-Update für aktive Route
      const trafficStart = Date.now();
      const trafficResponse = authenticatedRequest('GET', `${BASE_URL}/api/routes/traffic/${playlist.id}`);
      const trafficDuration = Date.now() - trafficStart;
      
      trafficUpdateTime.add(trafficDuration);
      trafficRequests.add(1);

      check(trafficResponse, {
        'Traffic-Daten abgerufen': (r) => r && r.status === 200,
        'Traffic-Update <200ms': (r) => trafficDuration < 200
      });

      sleep(randomIntBetween(1, 3));

      // Schnelles Stopp-Update (70% Wahrscheinlichkeit)
      if (Math.random() < 0.7) {
        const stopsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists/${playlist.id}/stops`);
        
        if (stopsResponse && stopsResponse.status === 200) {
          let stops;
          try {
            stops = stopsResponse.json();
          } catch (e) {
            errorRate.add(1);
            return;
          }

          const openStops = stops ? stops.filter(stop => stop.status === 'pending') : [];
          
          if (openStops.length > 0) {
            const stop = openStops[0]; // Nächster Stopp
            
            const updateStart = Date.now();
            const updateResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${playlist.id}/stops/${stop.id}`, {
              status: Math.random() < 0.8 ? 'completed' : 'in_progress',
              completedAt: new Date().toISOString(),
              actualDuration: randomIntBetween(10, 45),
              notes: `Update ${new Date().toLocaleTimeString()}`
            });

            const updateDuration = Date.now() - updateStart;
            stopUpdateTime.add(updateDuration);
            userStats.stopUpdates++;

            check(updateResponse, {
              'Stopp aktualisiert': (r) => r && r.status === 200,
              'Stopp-Update SLA <100ms': (r) => updateDuration < 100
            });

            // SLA-Verletzung tracking
            if (updateDuration >= 100) {
              slaViolations.add(1, { metric: 'stop_update', target: '100ms' });
            }
          }
        }
      }
    }
  }

  logoutUser();
}

/**
 * Field Worker - Evening Close
 */
function fieldWorkerEveningClose(user) {
  if (!authenticateUser(user)) return;

  // Aktive Playlists abschließen
  const activePlaylistResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists?status=in_progress&assigned_to=${user.id}`);
  
  if (activePlaylistResponse && activePlaylistResponse.status === 200) {
    let playlists;
    try {
      playlists = activePlaylistResponse.json();
    } catch (e) {
      errorRate.add(1);
      return;
    }

    if (playlists && playlists.length > 0) {
      for (const playlist of playlists) {
        sleep(randomIntBetween(5, 10));

        // Verbleibende Stopps als erledigt markieren
        const stopsResponse = authenticatedRequest('GET', `${BASE_URL}/api/playlists/${playlist.id}/stops`);
        
        if (stopsResponse && stopsResponse.status === 200) {
          let stops;
          try {
            stops = stopsResponse.json();
          } catch (e) {
            continue;
          }

          const openStops = stops ? stops.filter(stop => stop.status === 'pending') : [];
          
          for (const stop of openStops.slice(0, 3)) { // Max 3 Stopps
            const updateResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${playlist.id}/stops/${stop.id}`, {
              status: 'completed',
              completedAt: new Date().toISOString(),
              actualDuration: randomIntBetween(15, 60),
              notes: `Abgeschlossen am Ende des Arbeitstages`
            });

            check(updateResponse, {
              'Stopp abgeschlossen': (r) => r && r.status === 200
            });

            sleep(randomIntBetween(2, 5));
          }
        }

        // Playlist als abgeschlossen markieren
        const completeResponse = authenticatedRequest('PUT', `${BASE_URL}/api/playlists/${playlist.id}`, {
          status: 'completed',
          completedAt: new Date().toISOString(),
          totalDistance: randomIntBetween(50, 200),
          totalDuration: randomIntBetween(300, 480) // 5-8 Stunden
        });

        check(completeResponse, {
          'Playlist abgeschlossen': (r) => r && r.status === 200
        });

        activeRoutes.add(-1);
      }
    }
  }

  logoutUser();
}

/**
 * Admin - Morning Playlist Creation
 */
function adminMorningPlaylistCreation(user) {
  if (!authenticateUser(user)) return;

  sleep(randomIntBetween(5, 10));

  // Neue Playlist erstellen
  const regionKeys = Object.keys(berlinRoutes);
  const randomRegion = regionKeys[randomIntBetween(0, regionKeys.length - 1)];
  const addresses = berlinRoutes[randomRegion];
  const stopCount = randomIntBetween(8, 20); // Bis zu 20 Stopps

  const stops = [];
  for (let i = 0; i < Math.min(stopCount, addresses.length); i++) {
    stops.push({
      address: addresses[i % addresses.length],
      duration: randomIntBetween(15, 60),
      priority: randomIntBetween(1, 5),
      notes: `Stop ${i + 1} - ${randomRegion} Region`
    });
  }

  const playlistData = {
    name: `Morning-Route-${randomRegion}-${Date.now()}`,
    description: `Automatisch generierte Route für ${randomRegion} Region`,
    date: new Date().toISOString().split('T')[0],
    assignedTo: testUsers.fieldWorkers[randomIntBetween(0, testUsers.fieldWorkers.length - 1)].id,
    stops: stops
  };

  const createStart = Date.now();
  const createResponse = authenticatedRequest('POST', `${BASE_URL}/api/playlists`, playlistData, '20s');
  const createDuration = Date.now() - createStart;

  check(createResponse, {
    'Playlist erstellt': (r) => r && r.status === 201,
    'Erstellung <500ms': (r) => createDuration < 500
  });

  if (createResponse && createResponse.status === 201) {
    let newPlaylist;
    try {
      newPlaylist = createResponse.json();
    } catch (e) {
      errorRate.add(1);
      logoutUser();
      return;
    }

    sleep(randomIntBetween(10, 20));

    // Route optimieren
    const optimizeResponse = authenticatedRequest('POST', `${BASE_URL}/api/routes/optimize/${newPlaylist.id}`, {
      algorithm: 'genetic',
      includeTraffic: true,
      maxOptimizationTime: 30000
    }, '30s');

    check(optimizeResponse, {
      'Route optimiert': (r) => r && r.status === 200,
      'Optimierung <800ms': (r) => r && r.timings.duration < 800
    });

    routeOptimizations.add(1);
  }

  logoutUser();
}

/**
 * Admin - Evening Reports
 */
function adminEveningReports(user) {
  if (!authenticateUser(user)) return;

  sleep(randomIntBetween(10, 20));

  // Tages-Performance-Bericht generieren
  const reportResponse = authenticatedRequest('GET', `${BASE_URL}/api/reports/performance?period=today&format=summary`, '30s');
  
  check(reportResponse, {
    'Performance-Bericht generiert': (r) => r && r.status === 200,
    'Bericht <1s': (r) => r && r.timings.duration < 1000
  });

  sleep(randomIntBetween(15, 30));

  // Team-Übersicht
  const teamResponse = authenticatedRequest('GET', `${BASE_URL}/api/reports/team-summary?date=${new Date().toISOString().split('T')[0]}`);
  
  check(teamResponse, {
    'Team-Bericht geladen': (r) => r && r.status === 200
  });

  logoutUser();
}

/**
 * Supervisor - Monitoring
 */
function supervisorMorningOverview(user) {
  if (!authenticateUser(user)) return;

  sleep(randomIntBetween(5, 15));

  // Team-Status prüfen
  const progressResponse = authenticatedRequest('GET', `${BASE_URL}/api/reports/progress`);
  
  check(progressResponse, {
    'Team-Fortschritt abgerufen': (r) => r && r.status === 200,
    'Progress <300ms': (r) => r && r.timings.duration < 300
  });

  sleep(randomIntBetween(10, 20));

  // Individuelle Fortschritte prüfen
  for (const fieldWorker of testUsers.fieldWorkers.slice(0, 3)) {
    const individualResponse = authenticatedRequest('GET', `${BASE_URL}/api/reports/progress?user=${fieldWorker.id}`);
    
    check(individualResponse, {
      'Individueller Fortschritt': (r) => r && r.status === 200
    });
    
    sleep(randomIntBetween(5, 10));
  }

  logoutUser();
}

function supervisorLunchCheck(user) {
  if (!authenticateUser(user)) return;

  // Schneller Team-Status-Check
  const statusResponse = authenticatedRequest('GET', `${BASE_URL}/api/dashboard/team-status`);
  
  check(statusResponse, {
    'Team-Status abgerufen': (r) => r && r.status === 200
  });

  logoutUser();
}

function supervisorEveningReview(user) {
  if (!authenticateUser(user)) return;

  sleep(randomIntBetween(15, 30));

  // Ausführliche Tagesauswertung
  const dayReviewResponse = authenticatedRequest('GET', `${BASE_URL}/api/reports/daily-review?date=${new Date().toISOString().split('T')[0]}`, null, '45s');
  
  check(dayReviewResponse, {
    'Tagesauswertung generiert': (r) => r && r.status === 200,
    'Review <2s': (r) => r && r.timings.duration < 2000
  });

  logoutUser();
}

/**
 * Benutzer abmelden
 */
function logoutUser() {
  if (sessionToken) {
    const logoutResponse = authenticatedRequest('POST', `${BASE_URL}/api/auth/logout`);
    check(logoutResponse, {
      'Logout erfolgreich': (r) => r && r.status === 200
    });
    
    sessionToken = null;
    currentUser = null;
    authenticatedUsers.add(-1);
  }
  
  sleep(randomIntBetween(1, 3));
}

/**
 * Standard-Test-Funktion - Route basierend auf Szenario
 */
export default function() {
  switch (SCENARIO) {
    case 'morningRush':
      morningRushScenario();
      break;
    case 'lunchUpdate':
      lunchUpdateScenario();
      break;
    case 'eveningClose':  
      eveningCloseScenario();
      break;
    default:
      // Mixed scenario für allgemeine Last-Tests
      const scenarios = [morningRushScenario, lunchUpdateScenario, eveningCloseScenario];
      const randomScenario = scenarios[randomIntBetween(0, scenarios.length - 1)];
      randomScenario();
  }
}

/**
 * Szenario-spezifische Optionen
 */
export let options = {
  scenarios: {},
  
  thresholds: {
    // Allgemeine Performance-Ziele
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.05'],
    
    // SLA-spezifische Ziele aus CLAUDE.md
    login_time: ['p(95)<100'],                    // Login unter 100ms
    route_calculation_time: ['p(95)<300'],        // Route unter 300ms  
    stop_update_time: ['p(95)<100'],              // Stopp-Update unter 100ms
    traffic_update_time: ['p(95)<200'],           // Traffic-Update unter 200ms
    api_response_time: ['p(95)<200'],             // API-Response unter 200ms
    
    // Zuverlässigkeit
    error_rate: ['rate<0.05'],                    // Fehlerrate unter 5%
    sla_violations: ['count<10'],                 // Max. 10 SLA-Verletzungen
    
    // System-Kapazität
    authenticated_users: ['value<=200'],          // Max. 200 gleichzeitige Benutzer
    active_routes: ['value<=150'],                // Max. 150 aktive Routen
  },
  
  // Benutzer-Agent für Identifikation
  userAgent: `MetropolPortal-LoadTest-${SCENARIO}/1.0 (2Brands Media GmbH)`,
  
  // Timeout-Konfiguration
  setupTimeout: '60s',
  teardownTimeout: '30s',
  
  // HTTP-Konfiguration
  http: {
    responseCallback: http.expectedStatuses(200, 201, 204, 400, 401, 403, 404, 422, 429),
  }
};

// Szenario-spezifische Konfiguration
switch (SCENARIO) {
  case 'morningRush':
    options.scenarios.morningRush = {
      executor: 'ramping-vus',
      stages: [
        { duration: '2m', target: 30 },   // Schneller Anstieg - viele Logins gleichzeitig
        { duration: '3m', target: 50 },   // Peak-Zeit
        { duration: '3m', target: 35 },   // Abflachen
        { duration: '2m', target: 0 },    // Ende
      ],
      env: { SCENARIO: 'morningRush' }
    };
    break;
    
  case 'lunchUpdate':
    options.scenarios.lunchUpdate = {
      executor: 'ramping-vus',
      stages: [
        { duration: '30s', target: 20 },  // Schneller Aufbau
        { duration: '2m', target: 30 },   // Kurze intensive Phase
        { duration: '30s', target: 0 },   // Schneller Abbau
      ],
      env: { SCENARIO: 'lunchUpdate' }
    };
    break;
    
  case 'eveningClose':
    options.scenarios.eveningClose = {
      executor: 'ramping-vus',
      stages: [
        { duration: '1m', target: 15 },   // Langsamer Aufbau
        { duration: '4m', target: 25 },   // Stabile Last
        { duration: '2m', target: 0 },    // Langsamer Abbau
      ],
      env: { SCENARIO: 'eveningClose' }
    };
    break;
    
  case 'normalLoad':
    options.scenarios.normalLoad = {
      executor: 'ramping-vus',
      stages: [
        { duration: '2m', target: 15 },
        { duration: '8m', target: 25 },
        { duration: '2m', target: 0 },
      ],
      env: { SCENARIO: 'mixed' }
    };
    break;
    
  case 'peakLoad':
    options.scenarios.peakLoad = {
      executor: 'ramping-vus',
      stages: [
        { duration: '1m', target: 50 },
        { duration: '2m', target: 100 },
        { duration: '1m', target: 0 },
      ],
      env: { SCENARIO: 'mixed' }
    };
    break;
    
  case 'stressTest':
    options.scenarios.stressTest = {
      executor: 'ramping-vus',
      stages: [
        { duration: '2m', target: 100 },
        { duration: '3m', target: 200 },
        { duration: '2m', target: 0 },
      ],
      env: { SCENARIO: 'mixed' }
    };
    // Relaxierte Thresholds für Stress-Test
    options.thresholds.http_req_duration = ['p(95)<2000'];
    options.thresholds.http_req_failed = ['rate<0.15'];
    break;
    
  default:
    // Standard Mixed Scenario
    options.scenarios.mixed = {
      executor: 'ramping-vus', 
      stages: [
        { duration: '2m', target: 10 },
        { duration: '5m', target: 20 },
        { duration: '2m', target: 0 },
      ]
    };
}

/**
 * Setup - Einmalige Initialisierung
 */
export function setup() {
  console.log(`🚀 Starte ${SCENARIO} Load-Test für Metropol Portal`);
  console.log('Entwickelt von 2Brands Media GmbH');
  
  // Health-Check
  const healthResponse = http.get(`${BASE_URL}/api/health`);
  if (healthResponse.status !== 200) {
    console.error('❌ Server nicht erreichbar');
    return { skipTests: true };
  }
  
  console.log('✅ Server verfügbar, starte Tests');
  return { baseUrl: BASE_URL, scenario: SCENARIO };
}

/**
 * Teardown - Cleanup nach Tests
 */
export function teardown(data) {
  if (data && data.skipTests) return;
  
  console.log('🏁 Load-Test abgeschlossen');
  console.log(`📊 Benutzer-Statistiken:`);
  console.log(`   - Logins: ${userStats.loginCount}`);
  console.log(`   - Route-Berechnungen: ${userStats.routeCalculations}`);
  console.log(`   - Stopp-Updates: ${userStats.stopUpdates}`);
  console.log(`   - Fehler: ${userStats.errors}`);
  console.log('Entwickelt von 2Brands Media GmbH');
}