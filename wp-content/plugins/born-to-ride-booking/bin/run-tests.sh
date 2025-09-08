#!/bin/bash

# Born to Ride Booking - Test Runner Script
# Esegue tutti i test del sistema pagamento con report coverage

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directory
PLUGIN_DIR="$(dirname "$(dirname "$(realpath "$0")")")"
TESTS_DIR="$PLUGIN_DIR/tests"
COVERAGE_DIR="$TESTS_DIR/coverage-report"
VENDOR_DIR="$PLUGIN_DIR/vendor"

# Funzioni utility
print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Verifica ambiente
check_environment() {
    print_header "Verifica Ambiente Test"
    
    # Verifica PHPUnit
    if ! command -v phpunit &> /dev/null; then
        if [ ! -f "$VENDOR_DIR/bin/phpunit" ]; then
            print_error "PHPUnit non trovato. Installa con: composer install --dev"
            exit 1
        else
            PHPUNIT_CMD="$VENDOR_DIR/bin/phpunit"
            print_success "PHPUnit trovato in vendor/"
        fi
    else
        PHPUNIT_CMD="phpunit"
        print_success "PHPUnit disponibile globalmente"
    fi
    
    # Verifica WordPress test
    if [ -z "$WP_TESTS_DIR" ]; then
        export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
        print_warning "WP_TESTS_DIR impostato a $WP_TESTS_DIR"
    else
        print_success "WP_TESTS_DIR: $WP_TESTS_DIR"
    fi
    
    # Verifica database test
    if [ -z "$WP_TEST_DB_NAME" ]; then
        export WP_TEST_DB_NAME="btr_test"
        print_warning "WP_TEST_DB_NAME impostato a $WP_TEST_DB_NAME"
    fi
    
    echo ""
}

# Setup database di test
setup_test_database() {
    print_header "Setup Database Test"
    
    # Crea database se non esiste
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS $WP_TEST_DB_NAME;" 2>/dev/null || {
        print_warning "Impossibile creare database (potrebbero servire credenziali)"
    }
    
    # Installa WordPress test se necessario
    if [ ! -d "$WP_TESTS_DIR" ]; then
        print_warning "Installazione WordPress test suite..."
        bash "$PLUGIN_DIR/bin/install-wp-tests.sh" \
            "$WP_TEST_DB_NAME" \
            "root" \
            "" \
            "localhost" \
            "latest"
    else
        print_success "WordPress test suite già installato"
    fi
    
    echo ""
}

# Esegui test specifici
run_test_suite() {
    local suite=$1
    local description=$2
    
    print_header "Esecuzione Test: $description"
    
    cd "$PLUGIN_DIR"
    
    case $suite in
        "unit")
            $PHPUNIT_CMD --testsuite="Unit" --colors=always
            ;;
        "integration")
            $PHPUNIT_CMD --testsuite="Integration" --colors=always
            ;;
        "e2e")
            $PHPUNIT_CMD --testsuite="E2E" --colors=always
            ;;
        "payment")
            $PHPUNIT_CMD --testsuite="Payment System" --colors=always
            ;;
        "coverage")
            $PHPUNIT_CMD --coverage-html="$COVERAGE_DIR" --coverage-text --colors=always
            ;;
        "all")
            $PHPUNIT_CMD --colors=always
            ;;
        *)
            print_error "Suite non riconosciuta: $suite"
            return 1
            ;;
    esac
    
    if [ $? -eq 0 ]; then
        print_success "$description completati con successo"
    else
        print_error "$description falliti"
        return 1
    fi
    
    echo ""
}

# Genera report coverage
generate_coverage_report() {
    print_header "Generazione Report Coverage"
    
    cd "$PLUGIN_DIR"
    
    # Coverage HTML
    $PHPUNIT_CMD --coverage-html="$COVERAGE_DIR" --coverage-text="$TESTS_DIR/coverage.txt" --coverage-clover="$TESTS_DIR/coverage.xml" > /dev/null 2>&1
    
    if [ -f "$COVERAGE_DIR/index.html" ]; then
        print_success "Report HTML generato: $COVERAGE_DIR/index.html"
    fi
    
    if [ -f "$TESTS_DIR/coverage.txt" ]; then
        echo "Coverage Summary:"
        cat "$TESTS_DIR/coverage.txt" | tail -10
        echo ""
    fi
    
    if [ -f "$TESTS_DIR/coverage.xml" ]; then
        print_success "Report Clover generato: $TESTS_DIR/coverage.xml"
    fi
}

# Verifica qualità test
check_test_quality() {
    print_header "Verifica Qualità Test"
    
    local test_files=$(find "$TESTS_DIR" -name "*Test.php" | wc -l)
    local unit_tests=$(find "$TESTS_DIR/Unit" -name "*Test.php" 2>/dev/null | wc -l || echo 0)
    local integration_tests=$(find "$TESTS_DIR/Integration" -name "*Test.php" 2>/dev/null | wc -l || echo 0)
    local e2e_tests=$(find "$TESTS_DIR/E2E" -name "*Test.php" 2>/dev/null | wc -l || echo 0)
    
    echo "Statistiche Test:"
    echo "- File test totali: $test_files"
    echo "- Test unitari: $unit_tests"
    echo "- Test integrazione: $integration_tests" 
    echo "- Test E2E: $e2e_tests"
    
    if [ "$test_files" -gt 0 ]; then
        print_success "Suite test ben strutturata"
    else
        print_warning "Nessun test trovato"
    fi
    
    echo ""
}

# Funzione principale
main() {
    local command=${1:-"all"}
    local show_help=false
    
    # Parse argomenti
    case $command in
        "help"|"-h"|"--help")
            show_help=true
            ;;
        "unit")
            check_environment
            setup_test_database
            run_test_suite "unit" "Test Unitari"
            ;;
        "integration")
            check_environment
            setup_test_database
            run_test_suite "integration" "Test Integrazione"
            ;;
        "e2e")
            check_environment
            setup_test_database
            run_test_suite "e2e" "Test End-to-End"
            ;;
        "payment")
            check_environment
            setup_test_database
            run_test_suite "payment" "Test Sistema Pagamento"
            ;;
        "coverage")
            check_environment
            setup_test_database
            run_test_suite "coverage" "Test con Coverage"
            generate_coverage_report
            ;;
        "quality")
            check_test_quality
            ;;
        "all")
            check_environment
            setup_test_database
            check_test_quality
            run_test_suite "all" "Tutti i Test"
            generate_coverage_report
            ;;
        *)
            print_error "Comando non riconosciuto: $command"
            show_help=true
            ;;
    esac
    
    if [ "$show_help" = true ]; then
        cat << EOF
Born to Ride Booking - Test Runner

Utilizzo: $0 [comando]

Comandi disponibili:
  all          Esegue tutti i test con coverage (default)
  unit         Esegue solo i test unitari
  integration  Esegue solo i test di integrazione
  e2e          Esegue solo i test end-to-end
  payment      Esegue solo i test del sistema pagamento
  coverage     Esegue tutti i test e genera report coverage
  quality      Verifica qualità e struttura dei test
  help         Mostra questo help

Esempi:
  $0                    # Esegue tutti i test
  $0 unit              # Solo test unitari
  $0 coverage          # Test con report coverage
  $0 quality           # Verifica qualità test

Configurazione ambiente:
  WP_TESTS_DIR         Directory WordPress test suite
  WP_TEST_DB_NAME      Nome database test (default: btr_test)

EOF
        exit 0
    fi
}

# Esegui funzione principale
main "$@"