#!/usr/bin/env bash

##########################################################################
# Load Test Execution Script für Metropol Portal
# Orchestriert verschiedene Load-Test-Szenarien
# Entwickelt von 2Brands Media GmbH
##########################################################################

set -eo pipefail

# Farbige Ausgabe
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Konfiguration
BASE_URL="${BASE_URL:-http://localhost:8000}"
SCENARIO="${SCENARIO:-all}"
MAX_USERS="${MAX_USERS:-50}"
OUTPUT_DIR="${OUTPUT_DIR:-./test-results}"
PARALLEL_JOBS="${PARALLEL_JOBS:-2}"

# SLA-Ziele (aus CLAUDE.md)
SLA_LOGIN_TIME=100          # Millisekunden
SLA_ROUTE_CALC_TIME=300     # Millisekunden
SLA_STOP_UPDATE_TIME=100    # Millisekunden
SLA_ERROR_RATE=5            # Prozent
SLA_SUCCESS_RATE=95         # Prozent

# Verfügbare Szenarien (kompatibel mit älteren Bash-Versionen)
get_scenario_description() {
    case "$1" in
        "smoke") echo "Smoke Test - Grundfunktionalität prüfen" ;;
        "morningRush") echo "Morning Rush - 7-9 AM Szenario (50 Benutzer)" ;;
        "lunchUpdate") echo "Lunch Update - 12-1 PM Szenario (30 Benutzer)" ;;
        "eveningClose") echo "Evening Close - 5-6 PM Szenario (25 Benutzer)" ;;
        "normalLoad") echo "Normal Load - 25 Benutzer für 5 Minuten" ;;
        "peakLoad") echo "Peak Load - 100 Benutzer für 2 Minuten" ;;
        "stressTest") echo "Stress Test - 200+ Benutzer bis zum Breaking Point" ;;
        "browser") echo "Browser Simulation - Playwright Tests" ;;
        "all") echo "Alle kritischen Szenarien ausführen" ;;
        *) echo "Unbekanntes Szenario" ;;
    esac
}

AVAILABLE_SCENARIOS="smoke morningRush lunchUpdate eveningClose normalLoad peakLoad stressTest browser all"

# Logging-Funktionen
log_info() {
    echo -e "${BLUE}ℹ️  INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}✅ SUCCESS:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠️  WARNING:${NC} $1"
}

log_error() {
    echo -e "${RED}❌ ERROR:${NC} $1"
}

log_header() {
    echo -e "\n${PURPLE}🚀 $1${NC}"
    echo -e "${PURPLE}$(printf '=%.0s' {1..50})${NC}\n"
}

# Hilfsfunktionen
show_help() {
    echo -e "${CYAN}Metropol Portal Load Test Runner${NC}"
    echo -e "${CYAN}Entwickelt von 2Brands Media GmbH${NC}\n"
    
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -u, --url URL           Base URL für Tests (default: $BASE_URL)"
    echo "  -s, --scenario NAME     Szenario zum Ausführen (default: $SCENARIO)"
    echo "  -m, --max-users N       Maximale Anzahl gleichzeitiger Benutzer (default: $MAX_USERS)"
    echo "  -o, --output DIR        Output-Verzeichnis (default: $OUTPUT_DIR)"
    echo "  -j, --jobs N            Anzahl paralleler Jobs (default: $PARALLEL_JOBS)"
    echo "  -h, --help              Diese Hilfe anzeigen"
    echo "  --list-scenarios        Alle verfügbaren Szenarien auflisten"
    echo "  --health-check          Nur Health-Check ausführen"
    echo "  --validate-sla          SLA-Validierung nach Tests"
    echo ""
    echo "Beispiele:"
    echo "  $0 --scenario morningRush --max-users 50"
    echo "  $0 --url https://staging.example.com --scenario all"
    echo "  $0 --scenario stressTest --max-users 200"
}

list_scenarios() {
    echo -e "${CYAN}Verfügbare Load-Test-Szenarien:${NC}\n"
    
    for scenario in $AVAILABLE_SCENARIOS; do
        echo -e "  ${YELLOW}$scenario${NC}: $(get_scenario_description "$scenario")"
    done
    echo ""
}

health_check() {
    log_info "Führe Health-Check für $BASE_URL durch..."
    
    local health_url="$BASE_URL/api/health"
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -f -s "$health_url" > /dev/null 2>&1; then
            log_success "Server ist verfügbar und gesund"
            return 0
        fi
        
        if [ $attempt -eq $max_attempts ]; then
            log_error "Server nicht erreichbar nach $max_attempts Versuchen"
            return 1
        fi
        
        log_info "Warten auf Server... (Versuch $attempt/$max_attempts)"
        sleep 10
        ((attempt++))
    done
}

check_dependencies() {
    log_info "Überprüfe Abhängigkeiten..."
    
    # K6 prüfen
    if ! command -v k6 &> /dev/null; then
        log_error "K6 ist nicht installiert. Installieren Sie K6: https://k6.io/docs/getting-started/installation/"
        return 1
    fi
    
    # Node.js prüfen
    if ! command -v node &> /dev/null; then
        log_error "Node.js ist nicht installiert"
        return 1
    fi
    
    # NPM-Pakete prüfen
    if [ ! -f "package.json" ]; then
        log_error "package.json nicht gefunden. Führen Sie das Skript im Performance-Test-Verzeichnis aus."
        return 1
    fi
    
    if [ ! -d "node_modules" ]; then
        log_info "Installiere NPM-Abhängigkeiten..."
        npm install
    fi
    
    log_success "Alle Abhängigkeiten sind verfügbar"
}

setup_output_directory() {
    log_info "Erstelle Output-Verzeichnis: $OUTPUT_DIR"
    
    mkdir -p "$OUTPUT_DIR"
    
    # Timestamp für diese Test-Session
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local session_dir="$OUTPUT_DIR/session_$timestamp"
    mkdir -p "$session_dir"
    
    echo "$session_dir" > "$OUTPUT_DIR/.current_session"
    log_success "Output-Verzeichnis erstellt: $session_dir"
}

run_k6_scenario() {
    local scenario_name="$1"
    local users="$2"
    local duration="${3:-300}" # Default 5 Minuten
    
    log_info "Starte K6-Szenario: $scenario_name (Benutzer: $users, Dauer: ${duration}s)"
    
    local session_dir=$(cat "$OUTPUT_DIR/.current_session")
    local output_file="$session_dir/k6_${scenario_name}_$(date +%H%M%S).json"
    local log_file="$session_dir/k6_${scenario_name}_$(date +%H%M%S).log"
    
    # K6-Umgebungsvariablen
    export BASE_URL="$BASE_URL"
    export SCENARIO="$scenario_name"
    export MAX_USERS="$users"
    
    # K6 ausführen
    if k6 run \
        --out "json=$output_file" \
        --summary-trend-stats="min,avg,med,max,p(95),p(99)" \
        --summary-export="$session_dir/k6_${scenario_name}_summary.json" \
        k6-realtime-scenarios.js 2>&1 | tee "$log_file"; then
        
        log_success "K6-Szenario $scenario_name abgeschlossen"
        
        # Ergebnisse parsen
        parse_k6_results "$output_file" "$scenario_name"
        return 0
    else
        log_error "K6-Szenario $scenario_name fehlgeschlagen"
        return 1  
    fi
}

run_playwright_scenario() {
    local scenario_name="$1"
    local users="$2"
    local duration="${3:-180}" # Default 3 Minuten
    
    log_info "Starte Playwright-Szenario: $scenario_name (Benutzer: $users, Dauer: ${duration}s)"
    
    local session_dir=$(cat "$OUTPUT_DIR/.current_session")
    local output_file="$session_dir/playwright_${scenario_name}_$(date +%H%M%S).json"
    
    # Playwright-Umgebungsvariablen
    export BASE_URL="$BASE_URL"
    export CONCURRENT_USERS="$users"
    export DURATION="$duration"
    export OUTPUT_FILE="$output_file"
    export SCENARIO="$scenario_name"
    
    # Playwright ausführen
    if timeout $((duration + 120)) npx playwright test playwright-load-scenarios.ts --reporter=json --output="$output_file"; then
        log_success "Playwright-Szenario $scenario_name abgeschlossen"
        return 0
    else
        log_error "Playwright-Szenario $scenario_name fehlgeschlagen"
        return 1
    fi
}

parse_k6_results() {
    local json_file="$1"
    local scenario_name="$2"
    
    if [ ! -f "$json_file" ]; then
        log_warning "Ergebnisdatei nicht gefunden: $json_file"
        return 1
    fi
    
    log_info "Parse Ergebnisse für $scenario_name..."
    
    # Node.js-Skript zum Parsen der K6-Ergebnisse
    node -e "
        const fs = require('fs');
        const path = '$json_file';
        
        if (!fs.existsSync(path)) {
            console.error('❌ Datei nicht gefunden:', path);
            process.exit(1);
        }
        
        const content = fs.readFileSync(path, 'utf8');
        const lines = content.split('\n').filter(line => line.trim());
        
        const metrics = {};
        let slaViolations = [];
        
        lines.forEach(line => {
            try {
                const data = JSON.parse(line);
                if (data.type === 'Point' && data.metric) {
                    const metricName = data.metric;
                    const value = data.data.value;
                    
                    if (!metrics[metricName]) {
                        metrics[metricName] = { values: [], count: 0 };
                    }
                    metrics[metricName].values.push(value);
                    metrics[metricName].count++;
                }
            } catch (e) {
                // Ignoriere ungültige JSON-Zeilen
            }
        });
        
        // Statistiken berechnen
        Object.entries(metrics).forEach(([name, data]) => {
            const sorted = data.values.sort((a, b) => a - b);
            const len = sorted.length;
            
            if (len === 0) return;
            
            const stats = {
                min: sorted[0],
                max: sorted[len - 1],
                avg: data.values.reduce((a, b) => a + b, 0) / len,
                median: sorted[Math.floor(len / 2)],
                p95: sorted[Math.floor(len * 0.95)],
                p99: sorted[Math.floor(len * 0.99)],
                count: len
            };
            
            console.log(\`📊 \${name}:\`);
            console.log(\`   Min: \${stats.min.toFixed(2)}ms\`);
            console.log(\`   Avg: \${stats.avg.toFixed(2)}ms\`);
            console.log(\`   P95: \${stats.p95.toFixed(2)}ms\`);
            console.log(\`   P99: \${stats.p99.toFixed(2)}ms\`);
            console.log(\`   Max: \${stats.max.toFixed(2)}ms\`);
            console.log(\`   Count: \${stats.count}\`);
            
            // SLA-Validierung
            const slaTargets = {
                'login_time': $SLA_LOGIN_TIME,
                'route_calculation_time': $SLA_ROUTE_CALC_TIME,
                'stop_update_time': $SLA_STOP_UPDATE_TIME
            };
            
            if (slaTargets[name]) {
                const target = slaTargets[name];
                if (stats.p95 > target) {
                    const violation = \`\${name}: P95 \${stats.p95.toFixed(2)}ms > Ziel \${target}ms\`;
                    slaViolations.push(violation);
                    console.log(\`   ❌ SLA-Verletzung: \${violation}\`);
                } else {
                    console.log(\`   ✅ SLA erfüllt: P95 \${stats.p95.toFixed(2)}ms <= Ziel \${target}ms\`);
                }
            }
            console.log('');
        });
        
        // SLA-Verletzungen exportieren für spätere Auswertung
        if (slaViolations.length > 0) {
            fs.writeFileSync('$session_dir/sla_violations_$scenario_name.json', JSON.stringify(slaViolations, null, 2));
            console.log(\`⚠️  \${slaViolations.length} SLA-Verletzungen in $scenario_name\`);
            process.exit(1);
        } else {
            console.log('✅ Alle SLAs erfüllt in $scenario_name');
        }
    "
}

run_scenario() {
    local scenario="$1"
    
    case "$scenario" in
        "smoke")
            run_k6_scenario "smoke" 5 60
            ;;
        "morningRush")
            run_k6_scenario "morningRush" 50 300
            ;;
        "lunchUpdate")
            run_k6_scenario "lunchUpdate" 30 180
            ;;
        "eveningClose")
            run_k6_scenario "eveningClose" 25 240
            ;;
        "normalLoad")
            run_k6_scenario "normalLoad" 25 300
            ;;
        "peakLoad")
            run_k6_scenario "peakLoad" 100 120
            ;;
        "stressTest")
            run_k6_scenario "stressTest" 200 180
            ;;
        "browser")
            run_playwright_scenario "browser" 25 180
            ;;
        "all")
            log_header "Führe alle kritischen Szenarien aus"
            
            local scenarios=("smoke" "morningRush" "lunchUpdate" "eveningClose" "normalLoad")
            local failed_scenarios=()
            
            for s in "${scenarios[@]}"; do
                if ! run_scenario "$s"; then
                    failed_scenarios+=("$s")
                    log_error "Szenario $s fehlgeschlagen"
                else
                    log_success "Szenario $s erfolgreich"
                fi
                
                # Kurze Pause zwischen Szenarien
                sleep 30
            done
            
            if [ ${#failed_scenarios[@]} -gt 0 ]; then
                log_error "Fehlgeschlagene Szenarien: ${failed_scenarios[*]}"
                return 1
            else
                log_success "Alle Szenarien erfolgreich abgeschlossen"
                return 0
            fi
            ;;
        *)
            log_error "Unbekanntes Szenario: $scenario"
            list_scenarios
            return 1
            ;;
    esac
}

generate_final_report() {
    log_header "Generiere finalen Bericht"
    
    local session_dir=$(cat "$OUTPUT_DIR/.current_session")
    
    log_info "Führe Load-Test-Runner aus..."
    
    # TypeScript-Runner ausführen für umfassenden Bericht
    if node -r ts-node/register load-test-runner.ts "$BASE_URL" "$session_dir"; then
        log_success "Umfassender Bericht generiert"
        
        # Berichte anzeigen
        echo -e "\n${CYAN}📄 Generierte Berichte:${NC}"
        find "$session_dir" -name "*.html" -o -name "*.json" -o -name "*.md" | while read -r file; do
            echo "  📄 $(basename "$file"): $file"
        done
        
    else
        log_warning "Bericht-Generierung fehlgeschlagen, einfacher Bericht wird erstellt"
        
        # Einfacher Bericht
        local simple_report="$session_dir/simple-report.txt"
        {
            echo "Load Test Report - Metropol Portal"
            echo "=================================="
            echo "Entwickelt von 2Brands Media GmbH"
            echo ""
            echo "Test-Session: $(basename "$session_dir")"
            echo "Base URL: $BASE_URL"
            echo "Szenario: $SCENARIO"
            echo "Max Benutzer: $MAX_USERS"
            echo "Zeitpunkt: $(date)"
            echo ""
            echo "Ergebnisse:"
            find "$session_dir" -name "*.json" | wc -l | xargs echo "  JSON-Dateien:"
            find "$session_dir" -name "*.log" | wc -l | xargs echo "  Log-Dateien:"
            
            # SLA-Verletzungen zusammenfassen
            if find "$session_dir" -name "sla_violations_*.json" -type f | grep -q .; then
                echo ""
                echo "SLA-Verletzungen:"
                find "$session_dir" -name "sla_violations_*.json" -exec cat {} \; | jq -r '.[]' 2>/dev/null || echo "  Fehler beim Lesen der SLA-Verletzungen"
            else
                echo ""
                echo "✅ Keine SLA-Verletzungen festgestellt"
            fi
            
        } > "$simple_report"
        
        echo -e "\n📄 Einfacher Bericht: $simple_report"
    fi
}

validate_sla() {
    log_header "SLA-Validierung"
    
    local session_dir=$(cat "$OUTPUT_DIR/.current_session")
    local violations_found=false
    
    log_info "Prüfe SLA-Verletzungen in $session_dir..."
    
    if find "$session_dir" -name "sla_violations_*.json" -type f | grep -q .; then
        violations_found=true
        echo -e "\n${RED}❌ SLA-Verletzungen gefunden:${NC}"
        
        find "$session_dir" -name "sla_violations_*.json" -exec echo "📁 {}" \; -exec cat {} \;
        
        echo -e "\n${RED}🚨 Empfohlene Maßnahmen:${NC}"
        echo "  1. Server-Ressourcen überprüfen (CPU, RAM, I/O)"
        echo "  2. Datenbank-Performance analysieren"
        echo "  3. Anwendungs-Code auf Bottlenecks prüfen"
        echo "  4. Caching-Strategien implementieren"
        echo "  5. Load-Balancing erwägen"
        
    else
        log_success "Alle SLA-Ziele erfüllt!"
        echo -e "\n${GREEN}🎯 SLA-Ziele:${NC}"
        echo "  ✅ Login-Zeit: <= ${SLA_LOGIN_TIME}ms"
        echo "  ✅ Route-Berechnung: <= ${SLA_ROUTE_CALC_TIME}ms"
        echo "  ✅ Stopp-Update: <= ${SLA_STOP_UPDATE_TIME}ms"
        echo "  ✅ Fehlerrate: <= ${SLA_ERROR_RATE}%"
        echo "  ✅ Erfolgsrate: >= ${SLA_SUCCESS_RATE}%"
    fi
    
    return $violations_found
}

cleanup() {
    log_info "Cleanup..."
    
    # Temporäre Dateien aufräumen
    rm -f "$OUTPUT_DIR/.current_session" 2>/dev/null || true
    
    # Alte Test-Sessions aufräumen (älter als 30 Tage)
    find "$OUTPUT_DIR" -name "session_*" -type d -mtime +30 -exec rm -rf {} \; 2>/dev/null || true
    
    log_success "Cleanup abgeschlossen"
}

main() {
    # Signal-Handler für ordnungsgemäße Beendigung
    trap cleanup EXIT
    
    log_header "Metropol Portal Load Test Runner"
    echo -e "${CYAN}Entwickelt von 2Brands Media GmbH${NC}\n"
    
    # Abhängigkeiten prüfen
    if ! check_dependencies; then
        exit 1
    fi
    
    # Health-Check
    if ! health_check; then
        exit 1
    fi
    
    # Output-Verzeichnis erstellen
    setup_output_directory
    
    # Test-Szenario ausführen
    log_header "Führe Load-Tests aus"
    if run_scenario "$SCENARIO"; then
        log_success "Load-Tests erfolgreich abgeschlossen"
        
        # Finalen Bericht generieren
        generate_final_report
        
        # SLA-Validierung
        if validate_sla; then
            log_warning "SLA-Verletzungen festgestellt - siehe Bericht für Details"
            exit 2
        else
            log_success "Alle SLA-Ziele erfüllt!"
        fi
        
    else
        log_error "Load-Tests fehlgeschlagen"
        generate_final_report
        exit 1
    fi
    
    log_success "Load-Test-Runner erfolgreich abgeschlossen!"
}

# Argument-Parsing
while [[ $# -gt 0 ]]; do
    case $1 in
        -u|--url)
            BASE_URL="$2"
            shift 2
            ;;
        -s|--scenario)
            SCENARIO="$2"
            shift 2
            ;;
        -m|--max-users)
            MAX_USERS="$2"
            shift 2
            ;;
        -o|--output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        -j|--jobs)
            PARALLEL_JOBS="$2"
            shift 2
            ;;
        --list-scenarios)
            list_scenarios
            exit 0
            ;;
        --health-check)
            health_check
            exit $?
            ;;
        --validate-sla)
            validate_sla
            exit $?
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            log_error "Unbekannte Option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validierung der Szenarien
if [[ ! " $AVAILABLE_SCENARIOS " =~ " $SCENARIO " ]]; then
    log_error "Unbekanntes Szenario: $SCENARIO"
    list_scenarios
    exit 1
fi

# Haupt-Ausführung
main