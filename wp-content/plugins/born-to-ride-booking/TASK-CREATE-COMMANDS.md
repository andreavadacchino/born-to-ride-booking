# 🚀 Task Create Commands - Born to Ride Payment System

## Comandi completi per creare la struttura gerarchica Epic > Story > Task > Subtask

### 🎯 EPIC 1: Backend Payment System Implementation

```bash
# Create Epic 1
/task create epic "Backend Payment System Implementation" \
  --description "Implementare l'infrastruttura backend completa per gestire pagamenti flessibili inclusi database schema, payment plans logic, gateway integration e sistemi automatizzati" \
  --duration "10-12 giorni" \
  --team "2 Backend Developers, 1 Architect" \
  --priority high \
  --tags "backend,payment,core,infrastructure"

# Story 1.1: Database Schema & Migration System
/task create story "Database Schema & Migration System" \
  --parent "Backend Payment System Implementation" \
  --description "Creare schema database e sistema di migrazione robusto per gestire payment shares e tracking" \
  --effort "3-4 giorni" \
  --priority high

# Task 1.1.1
/task create task "Create btr_order_shares Table" \
  --parent "Database Schema & Migration System" \
  --hours 8 \
  --persona backend \
  --dependencies none \
  --success-criteria "Tabella creata con tutti i campi necessari, Indici ottimizzati per performance, Supporto charset UTF8MB4, Foreign key constraints implementate" \
  --subtasks "Design definitivo schema tabella (2h), Scrittura SQL migration script (2h), Implementazione rollback mechanism (2h), Test su diversi ambienti (2h)"

# Task 1.1.2
/task create task "Implement BTR_Database_Manager Class" \
  --parent "Database Schema & Migration System" \
  --hours 12 \
  --persona "backend,architect" \
  --dependencies "Create btr_order_shares Table" \
  --success-criteria "Classe singleton implementata, Metodi CRUD completi, Transaction support, Error handling robusto" \
  --subtasks "Struttura base classe (2h), Metodi install/upgrade (3h), Metodi CRUD operations (4h), Unit tests completi (3h)"

# Task 1.1.3
/task create task "Create Migration Scripts & Version Management" \
  --parent "Database Schema & Migration System" \
  --hours 6 \
  --persona "backend,devops" \
  --dependencies "Implement BTR_Database_Manager Class" \
  --success-criteria "Sistema versioning database, Migration automatiche su plugin update, Rollback capability, Logging migrations" \
  --subtasks "Version tracking system (2h), Auto-migration hooks (2h), Rollback procedures (1h), Documentation (1h)"

# Story 1.2: Payment Plans Core Logic
/task create story "Payment Plans Core Logic" \
  --parent "Backend Payment System Implementation" \
  --description "Estendere sistema payment plans per supportare modalità gruppo e deposito" \
  --effort "4-5 giorni" \
  --priority high

# Task 1.2.1
/task create task "Extend BTR_Payment_Plans Class" \
  --parent "Payment Plans Core Logic" \
  --hours 16 \
  --persona backend \
  --dependencies "Database Schema & Migration System" \
  --success-criteria "Classe estesa con nuovi metodi, Backward compatibility mantenuta, Tutti i payment modes supportati, Code coverage >80%" \
  --subtasks "Analisi classe esistente (2h), Implementazione inheritance structure (3h), Nuovi metodi payment handling (6h), Integration tests (3h), Refactoring e ottimizzazione (2h)"

# Task 1.2.2
/task create task "Implement Group Payment Shares Creation" \
  --parent "Payment Plans Core Logic" \
  --hours 12 \
  --persona backend \
  --dependencies "Extend BTR_Payment_Plans Class" \
  --success-criteria "Creazione shares atomica, Distribuzione quote corretta, Validazione totali, Email notification trigger" \
  --subtasks "Share calculation algorithm (3h), Database transaction handling (3h), Validation logic (2h), Email integration (2h), Error recovery (2h)"

# Task 1.2.3
/task create task "Implement Deposit Payment Logic" \
  --parent "Payment Plans Core Logic" \
  --hours 10 \
  --persona backend \
  --dependencies "Extend BTR_Payment_Plans Class" \
  --success-criteria "Calcolo deposito/saldo corretto, Meta ordine aggiornate, Stati ordine gestiti, Scadenze tracking" \
  --subtasks "Deposit calculation engine (3h), Order state management (3h), Due date tracking (2h), Balance payment handling (2h)"

# Story 1.3: Payment Gateway Integration
/task create story "Payment Gateway Integration" \
  --parent "Backend Payment System Implementation" \
  --description "Integrare gateway pagamento per gestire link individuali e pagamenti multipli" \
  --effort "4-5 giorni" \
  --priority high

# Task 1.3.1
/task create task "Create Individual Payment Endpoints" \
  --parent "Payment Gateway Integration" \
  --hours 12 \
  --persona "backend,security" \
  --dependencies "Payment Plans Core Logic" \
  --success-criteria "Endpoint REST sicuri, Token validation, Rate limiting, CORS handling" \
  --subtasks "REST API structure (3h), Security layer implementation (3h), Token generation/validation (3h), API documentation (3h)"

# Task 1.3.2
/task create task "Integrate with Stripe/PayPal" \
  --parent "Payment Gateway Integration" \
  --hours 16 \
  --persona backend \
  --dependencies "Create Individual Payment Endpoints" \
  --success-criteria "Stripe integration completa, PayPal integration completa, Webhook handling, Payment reconciliation" \
  --subtasks "Stripe SDK integration (4h), PayPal SDK integration (4h), Webhook endpoints (4h), Payment status sync (4h)"

# Task 1.3.3
/task create task "Implement Payment Callbacks & Webhooks" \
  --parent "Payment Gateway Integration" \
  --hours 10 \
  --persona "backend,security" \
  --dependencies "Integrate with Stripe/PayPal" \
  --success-criteria "Webhook security verificata, Status update automatici, Retry mechanism, Logging completo" \
  --subtasks "Webhook signature validation (3h), Status update handlers (3h), Retry queue implementation (2h), Monitoring setup (2h)"

# Story 1.4: Automated Systems
/task create story "Automated Systems" \
  --parent "Backend Payment System Implementation" \
  --description "Implementare automazioni per reminder, notifiche e gestione scadenze" \
  --effort "3-4 giorni" \
  --priority medium

# Task 1.4.1
/task create task "Implement Cron Jobs for Reminders" \
  --parent "Automated Systems" \
  --hours 8 \
  --persona backend \
  --dependencies "Payment Gateway Integration" \
  --success-criteria "Cron job registrati, Scheduling affidabile, Performance ottimizzata, Error handling" \
  --subtasks "WP Cron setup (2h), Reminder logic implementation (3h), Queue management (2h), Performance optimization (1h)"

# Task 1.4.2
/task create task "Create Email Templates" \
  --parent "Automated Systems" \
  --hours 10 \
  --persona "backend,scribe" \
  --dependencies "Implement Cron Jobs for Reminders" \
  --success-criteria "Template multilingua, Responsive design, Personalizzazione dinamica, A/B test ready" \
  --subtasks "Template structure design (2h), HTML/Text versions (3h), Dynamic content system (3h), Localization setup (2h)"

# Task 1.4.3
/task create task "Setup Reminder Escalation Logic" \
  --parent "Automated Systems" \
  --hours 8 \
  --persona backend \
  --dependencies "Create Email Templates" \
  --success-criteria "Escalation rules configurabili, Admin notifications, Auto-cancellation logic, Reporting dashboard data" \
  --subtasks "Escalation rules engine (3h), Admin notification system (2h), Auto-cancellation logic (2h), Metrics collection (1h)"
```

### 🎨 EPIC 2: Frontend & UX Implementation

```bash
# Create Epic 2
/task create epic "Frontend & UX Implementation" \
  --description "Implementare interfacce utente moderne e responsive per payment selection, individual payment pages e admin dashboard" \
  --duration "8-10 giorni" \
  --team "2 Frontend Developers, 1 UX Designer" \
  --priority high \
  --tags "frontend,ux,ui,responsive"

# Story 2.1: Payment Selection Page Enhancement
/task create story "Payment Selection Page Enhancement" \
  --parent "Frontend & UX Implementation" \
  --description "Unificare design system e migliorare UX della pagina selezione pagamento" \
  --effort "3-4 giorni" \
  --priority high

# Task 2.1.1
/task create task "Unify CSS with Design System" \
  --parent "Payment Selection Page Enhancement" \
  --hours 10 \
  --persona frontend \
  --dependencies none \
  --success-criteria "Variabili CSS unificate, Componenti riutilizzabili, Mobile-first approach, Performance ottimizzata" \
  --subtasks "CSS audit esistente (2h), Variable system setup (2h), Component library creation (3h), Style migration (3h)"

# Task 2.1.2
/task create task "Implement Payment Method Selection UI" \
  --parent "Payment Selection Page Enhancement" \
  --hours 12 \
  --persona frontend \
  --dependencies "Unify CSS with Design System" \
  --success-criteria "UI intuitiva e accessibile, Animazioni fluide, Feedback visivo immediato, WCAG 2.1 compliant" \
  --subtasks "Component structure (3h), Interactive states (3h), Animation implementation (2h), Accessibility testing (2h), Cross-browser testing (2h)"

# Task 2.1.3
/task create task "Add Real-time Validation" \
  --parent "Payment Selection Page Enhancement" \
  --hours 8 \
  --persona frontend \
  --dependencies "Implement Payment Method Selection UI" \
  --success-criteria "Validazione inline, Error messaging chiaro, Success feedback, Performance <100ms" \
  --subtasks "Validation rules setup (2h), Error state handling (2h), Success animations (2h), Performance optimization (2h)"

# Story 2.2: Individual Payment Page
/task create story "Individual Payment Page" \
  --parent "Frontend & UX Implementation" \
  --description "Creare pagina pagamento individuale per partecipanti con tracking real-time" \
  --effort "3-4 giorni" \
  --priority high

# Task 2.2.1
/task create task "Create Payment Page Template" \
  --parent "Individual Payment Page" \
  --hours 10 \
  --persona "frontend,backend" \
  --dependencies "Payment Selection Page Enhancement" \
  --success-criteria "Template WordPress completo, Responsive design, SEO optimized, Loading <3s" \
  --subtasks "Template structure (3h), WordPress integration (3h), Responsive implementation (2h), Performance optimization (2h)"

# Task 2.2.2
/task create task "Implement Payment Shortcode" \
  --parent "Individual Payment Page" \
  --hours 8 \
  --persona "backend,frontend" \
  --dependencies "Create Payment Page Template" \
  --success-criteria "Shortcode funzionante, Parametri configurabili, Caching implementato, Documentation completa" \
  --subtasks "Shortcode registration (2h), Parameter handling (2h), Rendering logic (2h), Documentation (2h)"

# Task 2.2.3
/task create task "Add Payment Progress Tracking" \
  --parent "Individual Payment Page" \
  --hours 10 \
  --persona frontend \
  --dependencies "Implement Payment Shortcode" \
  --success-criteria "Real-time updates, Progress visualization, Status messaging, Error recovery UX" \
  --subtasks "Progress component (3h), WebSocket/polling setup (3h), Status state machine (2h), Error handling UI (2h)"

# Story 2.3: Admin Dashboard Integration
/task create story "Admin Dashboard Integration" \
  --parent "Frontend & UX Implementation" \
  --description "Estendere admin dashboard per gestione completa pagamenti e reporting" \
  --effort "2-3 giorni" \
  --priority medium

# Task 2.3.1
/task create task "Add Payment Status Tab" \
  --parent "Admin Dashboard Integration" \
  --hours 8 \
  --persona "frontend,backend" \
  --dependencies "Backend Payment System Implementation" \
  --success-criteria "Tab integrato in ordini, Dati real-time, Filtri e ricerca, Bulk actions" \
  --subtasks "Tab integration (2h), Data table setup (3h), Filter implementation (2h), Bulk action handlers (1h)"

# Task 2.3.2
/task create task "Create Admin Actions Interface" \
  --parent "Admin Dashboard Integration" \
  --hours 10 \
  --persona "frontend,backend" \
  --dependencies "Add Payment Status Tab" \
  --success-criteria "Azioni admin complete, Confirmation dialogs, Success feedback, Audit logging" \
  --subtasks "Action buttons UI (2h), Modal dialogs (3h), AJAX handlers (3h), Logging integration (2h)"

# Task 2.3.3
/task create task "Implement Payment Reports" \
  --parent "Admin Dashboard Integration" \
  --hours 8 \
  --persona "backend,frontend" \
  --dependencies "Create Admin Actions Interface" \
  --success-criteria "Report generation, Export capabilities, Visualizzazioni grafiche, Scheduled reports" \
  --subtasks "Report queries (3h), Export functionality (2h), Chart integration (2h), Scheduling system (1h)"
```

### 🧪 EPIC 3: Testing, Deployment & Monitoring

```bash
# Create Epic 3
/task create epic "Testing, Deployment & Monitoring" \
  --description "Garantire qualità, sicurezza e affidabilità del sistema attraverso testing completo, deployment sicuro e monitoring continuo" \
  --duration "6-8 giorni" \
  --team "1 QA Engineer, 1 DevOps, 1 Security Expert" \
  --priority high \
  --tags "testing,deployment,security,monitoring"

# Story 3.1: Testing Suite
/task create story "Testing Suite" \
  --parent "Testing, Deployment & Monitoring" \
  --description "Implementare test coverage completo con unit tests, E2E tests e security audit" \
  --effort "3-4 giorni" \
  --priority high

# Task 3.1.1
/task create task "Unit Tests for Payment Logic" \
  --parent "Testing Suite" \
  --hours 12 \
  --persona "qa,backend" \
  --dependencies "Backend Payment System Implementation" \
  --success-criteria "Coverage >80%, All edge cases covered, Mock implementations, CI/CD integration" \
  --subtasks "Test framework setup (2h), Payment logic tests (4h), Database operation tests (3h), Mock services setup (3h)"

# Task 3.1.2
/task create task "E2E Tests with Playwright" \
  --parent "Testing Suite" \
  --hours 16 \
  --persona qa \
  --dependencies "Frontend & UX Implementation" \
  --success-criteria "All user flows tested, Cross-browser coverage, Mobile testing, Visual regression tests" \
  --subtasks "Playwright setup (2h), User flow scripts (6h), Cross-browser config (3h), Visual testing setup (3h), Performance benchmarks (2h)"

# Task 3.1.3
/task create task "Security Audit" \
  --parent "Testing Suite" \
  --hours 12 \
  --persona security \
  --dependencies "Unit Tests for Payment Logic,E2E Tests with Playwright" \
  --success-criteria "OWASP compliance, PCI requirements met, Penetration test passed, Security report clean" \
  --subtasks "Code security review (4h), Penetration testing (4h), Compliance verification (2h), Security documentation (2h)"

# Story 3.2: Deployment & Infrastructure
/task create story "Deployment & Infrastructure" \
  --parent "Testing, Deployment & Monitoring" \
  --description "Deploy sicuro e scalabile in produzione con monitoring e alerting" \
  --effort "2-3 giorni" \
  --priority high

# Task 3.2.1
/task create task "Prepare Deployment Scripts" \
  --parent "Deployment & Infrastructure" \
  --hours 8 \
  --persona devops \
  --dependencies "Testing Suite" \
  --success-criteria "Automated deployment, Rollback capability, Zero-downtime deploy, Environment configs" \
  --subtasks "Deployment script creation (3h), Rollback procedures (2h), Environment configuration (2h), Documentation (1h)"

# Task 3.2.2
/task create task "Setup Monitoring & Alerting" \
  --parent "Deployment & Infrastructure" \
  --hours 10 \
  --persona devops \
  --dependencies "Prepare Deployment Scripts" \
  --success-criteria "APM configured, Error tracking active, Performance monitoring, Alert rules defined" \
  --subtasks "APM tool setup (3h), Error tracking config (2h), Custom metrics setup (3h), Alert configuration (2h)"

# Task 3.2.3
/task create task "Create Documentation" \
  --parent "Deployment & Infrastructure" \
  --hours 12 \
  --persona "scribe,architect" \
  --dependencies "All tasks" \
  --success-criteria "Technical docs complete, User guides ready, API documentation, Video tutorials" \
  --subtasks "Technical documentation (4h), User guide creation (3h), API docs generation (3h), Video tutorial scripts (2h)"
```

### 🎯 Quick Create Script

```bash
# Script per creare tutta la gerarchia in una volta
/task create-batch <<EOF
# EPIC 1: Backend Payment System Implementation
epic "Backend Payment System Implementation" --description "Implementare l'infrastruttura backend completa per gestire pagamenti flessibili" --duration "10-12 giorni" --priority high

  story "Database Schema & Migration System" --description "Creare schema database e sistema di migrazione robusto" --effort "3-4 giorni"
    task "Create btr_order_shares Table" --hours 8 --persona backend
    task "Implement BTR_Database_Manager Class" --hours 12 --persona "backend,architect"
    task "Create Migration Scripts & Version Management" --hours 6 --persona "backend,devops"

  story "Payment Plans Core Logic" --description "Estendere sistema payment plans" --effort "4-5 giorni"
    task "Extend BTR_Payment_Plans Class" --hours 16 --persona backend
    task "Implement Group Payment Shares Creation" --hours 12 --persona backend
    task "Implement Deposit Payment Logic" --hours 10 --persona backend

  story "Payment Gateway Integration" --description "Integrare gateway pagamento" --effort "4-5 giorni"
    task "Create Individual Payment Endpoints" --hours 12 --persona "backend,security"
    task "Integrate with Stripe/PayPal" --hours 16 --persona backend
    task "Implement Payment Callbacks & Webhooks" --hours 10 --persona "backend,security"

  story "Automated Systems" --description "Implementare automazioni" --effort "3-4 giorni"
    task "Implement Cron Jobs for Reminders" --hours 8 --persona backend
    task "Create Email Templates" --hours 10 --persona "backend,scribe"
    task "Setup Reminder Escalation Logic" --hours 8 --persona backend

# EPIC 2: Frontend & UX Implementation
epic "Frontend & UX Implementation" --description "Implementare interfacce utente moderne e responsive" --duration "8-10 giorni" --priority high

  story "Payment Selection Page Enhancement" --description "Unificare design system" --effort "3-4 giorni"
    task "Unify CSS with Design System" --hours 10 --persona frontend
    task "Implement Payment Method Selection UI" --hours 12 --persona frontend
    task "Add Real-time Validation" --hours 8 --persona frontend

  story "Individual Payment Page" --description "Creare pagina pagamento individuale" --effort "3-4 giorni"
    task "Create Payment Page Template" --hours 10 --persona "frontend,backend"
    task "Implement Payment Shortcode" --hours 8 --persona "backend,frontend"
    task "Add Payment Progress Tracking" --hours 10 --persona frontend

  story "Admin Dashboard Integration" --description "Estendere admin dashboard" --effort "2-3 giorni"
    task "Add Payment Status Tab" --hours 8 --persona "frontend,backend"
    task "Create Admin Actions Interface" --hours 10 --persona "frontend,backend"
    task "Implement Payment Reports" --hours 8 --persona "backend,frontend"

# EPIC 3: Testing, Deployment & Monitoring
epic "Testing, Deployment & Monitoring" --description "Garantire qualità, sicurezza e affidabilità" --duration "6-8 giorni" --priority high

  story "Testing Suite" --description "Test coverage completo" --effort "3-4 giorni"
    task "Unit Tests for Payment Logic" --hours 12 --persona "qa,backend"
    task "E2E Tests with Playwright" --hours 16 --persona qa
    task "Security Audit" --hours 12 --persona security

  story "Deployment & Infrastructure" --description "Deploy sicuro e scalabile" --effort "2-3 giorni"
    task "Prepare Deployment Scripts" --hours 8 --persona devops
    task "Setup Monitoring & Alerting" --hours 10 --persona devops
    task "Create Documentation" --hours 12 --persona "scribe,architect"
EOF
```

### 📊 Task Summary Command

```bash
# Genera summary di tutti i task creati
/task summary "Born to Ride Payment System" \
  --show-dependencies \
  --show-critical-path \
  --export-gantt \
  --calculate-timeline
```

### 🔄 Task Dependencies Visualization

```bash
# Visualizza dipendenze tra task
/task visualize-dependencies "Backend Payment System Implementation" \
  --format mermaid \
  --highlight-critical-path \
  --show-personas
```

### 📈 Progress Tracking

```bash
# Setup progress tracking
/task track-progress "Born to Ride Payment System" \
  --create-dashboard \
  --setup-alerts \
  --enable-burndown-chart \
  --slack-integration
```