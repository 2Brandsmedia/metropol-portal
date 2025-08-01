// K6 Smoke Test Konfiguration - Metropol Portal
// Schnelle Funktionalitätsprüfung bei minimaler Last
// Entwickelt von 2Brands Media GmbH

export let options = {
  stages: [
    { duration: '30s', target: 2 },  // Sanfter Anstieg auf 2 Benutzer
    { duration: '30s', target: 0 },  // Zurück auf 0
  ],
  
  thresholds: {
    // Grundlegende Funktionalität
    http_req_duration: ['p(95)<1000'],      // 95% unter 1s
    http_req_failed: ['rate<0.1'],          // Fehlerrate unter 10%
    
    // Spezifische Endpunkte
    'http_req_duration{name:login}': ['p(95)<200'],
    'http_req_duration{name:dashboard}': ['p(95)<500'],
    'http_req_duration{name:playlists}': ['p(95)<300'],
    
    // Systemstabilität
    'http_reqs': ['rate>1'], // Mindestens 1 Request/Sekunde
  },
  
  // Browser-ähnliches Verhalten
  userAgent: 'MetropolPortal-SmokeTest/1.0 (2Brands Media GmbH)',
  
  // Kurze Timeouts für schnelle Tests
  setupTimeout: '10s',
  teardownTimeout: '5s',
  
  // HTTP-Konfiguration
  httpDebug: 'full',
  insecureSkipTLSVerify: true,
  noConnectionReuse: false,
};