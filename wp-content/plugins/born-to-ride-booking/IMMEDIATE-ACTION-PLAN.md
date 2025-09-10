# 🚀 Piano di Azione Immediato - Sistema Metodi di Pagamento

## 📅 Settimana 1: Foundation & Quick Wins

### 🎯 Giorno 1-2: Setup e Analisi
```bash
# Task immediati da eseguire
```

#### ✅ Task 1: Backup e Setup Ambiente
```bash
# 1. Backup completo
wp db export backup-$(date +%Y%m%d-%H%M%S).sql

# 2. Crea branch di sviluppo
git checkout -b feature/flexible-payment-methods

# 3. Setup ambiente test
cp .env .env.backup
```

#### ✅ Task 2: Audit Codice Esistente
```php
// File da analizzare prioritariamente:
1. /includes/class-btr-payment-plans.php
2. /includes/class-btr-payment-selection-shortcode.php
3. /includes/class-btr-group-payments.php
4. /templates/payment-selection-page.php
```

**Output atteso**: Documento con lista funzionalità esistenti vs mancanti

#### ✅ Task 3: Database Schema Creation
```sql
-- Esegui questo SQL nel tuo ambiente di sviluppo
CREATE TABLE IF NOT EXISTS `wp_btr_order_shares` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) NOT NULL,
    `participant_id` bigint(20) NOT NULL,
    `participant_name` varchar(255) NOT NULL,
    `participant_email` varchar(255) NOT NULL,
    `amount_assigned` decimal(10,2) NOT NULL,
    `amount_paid` decimal(10,2) DEFAULT 0.00,
    `payment_method` varchar(50) DEFAULT NULL,
    `payment_status` enum('pending','paid','expired','cancelled') DEFAULT 'pending',
    `payment_link` varchar(255) DEFAULT NULL,
    `payment_token` varchar(64) UNIQUE,
    `paid_at` datetime DEFAULT NULL,
    `reminder_sent_at` datetime DEFAULT NULL,
    `reminder_count` int DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_participant_email` (`participant_email`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_payment_token` (`payment_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 🎯 Giorno 3-4: Core Implementation

#### ✅ Task 4: Estendi Payment Plans Manager
Crea il file `/includes/class-btr-payment-plans-extended.php`:

```php
<?php
/**
 * Extended Payment Plans functionality
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

class BTR_Payment_Plans_Extended extends BTR_Payment_Plans {
    
    /**
     * Create payment shares for group payment
     */
    public function create_group_payment_shares($order_id, $participants_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'btr_order_shares';
        
        $shares_created = [];
        
        foreach ($participants_data as $participant) {
            $token = wp_generate_password(32, false);
            $payment_link = $this->generate_payment_link($order_id, $token);
            
            $result = $wpdb->insert($table_name, [
                'order_id' => $order_id,
                'participant_id' => $participant['id'],
                'participant_name' => sanitize_text_field($participant['name']),
                'participant_email' => sanitize_email($participant['email']),
                'amount_assigned' => floatval($participant['amount']),
                'payment_link' => esc_url_raw($payment_link),
                'payment_token' => $token
            ]);
            
            if ($result) {
                $shares_created[] = $wpdb->insert_id;
                
                // Invia email al partecipante
                $this->send_payment_link_email($participant['email'], $payment_link, $participant);
            }
        }
        
        // Aggiorna meta ordine
        update_post_meta($order_id, '_btr_payment_mode', 'group_split');
        update_post_meta($order_id, '_btr_payment_shares', $shares_created);
        
        return $shares_created;
    }
    
    /**
     * Generate secure payment link
     */
    private function generate_payment_link($order_id, $token) {
        return add_query_arg([
            'btr_payment' => 'individual',
            'order' => $order_id,
            'token' => $token
        ], home_url('/pagamento-individuale/'));
    }
    
    /**
     * Handle deposit payment creation
     */
    public function create_deposit_payment($order_id, $deposit_percentage) {
        $order = wc_get_order($order_id);
        if (!$order) return false;
        
        $total = $order->get_total();
        $deposit_amount = $total * ($deposit_percentage / 100);
        $balance_amount = $total - $deposit_amount;
        
        // Salva informazioni deposito
        update_post_meta($order_id, '_btr_payment_mode', 'deposit_balance');
        update_post_meta($order_id, '_btr_deposit_percentage', $deposit_percentage);
        update_post_meta($order_id, '_btr_deposit_amount', $deposit_amount);
        update_post_meta($order_id, '_btr_balance_amount', $balance_amount);
        update_post_meta($order_id, '_btr_deposit_paid', false);
        
        // Aggiorna totale ordine per il deposito
        $order->set_total($deposit_amount);
        $order->save();
        
        // Aggiungi nota all'ordine
        $order->add_order_note(sprintf(
            __('Modalità caparra attivata. Caparra: %s (%d%%), Saldo: %s', 'born-to-ride-booking'),
            wc_price($deposit_amount),
            $deposit_percentage,
            wc_price($balance_amount)
        ));
        
        return true;
    }
}
```

#### ✅ Task 5: Update AJAX Handler
Modifica `/includes/class-btr-payment-selection-shortcode.php`:

```php
// Aggiungi questo metodo alla classe esistente
public function handle_create_payment_plan() {
    // Verifica nonce
    if (!isset($_POST['payment_nonce']) || !wp_verify_nonce($_POST['payment_nonce'], 'btr_payment_plan_nonce')) {
        wp_send_json_error(['message' => __('Errore di sicurezza.', 'born-to-ride-booking')]);
    }
    
    $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
    $payment_plan = isset($_POST['payment_plan']) ? sanitize_text_field($_POST['payment_plan']) : '';
    
    // Converti preventivo in ordine se necessario
    $order_id = $this->get_or_create_order_from_preventivo($preventivo_id);
    
    if (!$order_id) {
        wp_send_json_error(['message' => __('Errore nella creazione dell\'ordine.', 'born-to-ride-booking')]);
    }
    
    $payment_manager = new BTR_Payment_Plans_Extended();
    $success = false;
    
    switch ($payment_plan) {
        case 'full':
            // Pagamento completo - standard flow
            update_post_meta($order_id, '_btr_payment_mode', 'full');
            $success = true;
            break;
            
        case 'deposit_balance':
            $deposit_percentage = isset($_POST['deposit_percentage']) ? intval($_POST['deposit_percentage']) : 30;
            $success = $payment_manager->create_deposit_payment($order_id, $deposit_percentage);
            break;
            
        case 'group_split':
            $participants = isset($_POST['group_participants']) ? $_POST['group_participants'] : [];
            if (!empty($participants)) {
                $success = $payment_manager->create_group_payment_shares($order_id, $participants);
            }
            break;
    }
    
    if ($success) {
        wp_send_json_success([
            'redirect_url' => wc_get_checkout_url(),
            'order_id' => $order_id
        ]);
    } else {
        wp_send_json_error(['message' => __('Errore nella configurazione del pagamento.', 'born-to-ride-booking')]);
    }
}
```

### 🎯 Giorno 5: Frontend Integration

#### ✅ Task 6: Unifica Stili CSS
Crea `/assets/css/btr-payment-unified.css`:

```css
/**
 * Unified Payment Styles
 * Integrates with existing Born to Ride design system
 */

/* Import global variables */
@import url('btr-global-variables.css');

/* Payment Selection Page - Use existing patterns */
.btr-payment-selection-page {
  background: var(--btr-gray-100);
  padding: var(--btr-spacing-xl) 0;
}

.btr-payment-selection-page .btr-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 var(--btr-spacing-md);
}

/* Reuse card component */
.btr-payment-options {
  background: white;
  border-radius: var(--btr-border-radius);
  padding: var(--btr-spacing-lg);
  box-shadow: var(--btr-shadow);
  margin-bottom: var(--btr-spacing-lg);
}

/* Payment option cards */
.btr-payment-option {
  border: 2px solid var(--btr-gray-300);
  border-radius: var(--btr-border-radius);
  padding: var(--btr-spacing-lg);
  margin-bottom: var(--btr-spacing-md);
  transition: var(--btr-transition);
  cursor: pointer;
}

.btr-payment-option:hover {
  border-color: var(--btr-primary);
  box-shadow: var(--btr-shadow);
}

.btr-payment-option.selected {
  border-color: var(--btr-primary);
  background: var(--btr-primary-light);
}

/* Reuse button styles */
.btr-payment-submit {
  /* Extends .btr-btn and .btr-btn-primary */
  padding: 16px 32px;
  background: var(--btr-primary);
  color: white;
  border: none;
  border-radius: var(--btr-border-radius-sm);
  font-size: 1.125rem;
  font-weight: 500;
  cursor: pointer;
  transition: var(--btr-transition);
}

.btr-payment-submit:hover {
  background: var(--btr-primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 151, 197, 0.3);
}
```

## 📋 Checklist Settimanale

### Week 1 Deliverables
- [ ] Database schema creato e testato
- [ ] Classi PHP base implementate
- [ ] AJAX handlers funzionanti
- [ ] Stili unificati con design system
- [ ] Test di base superati

### Metriche di Successo
- Nessun breaking change al sistema esistente
- Tutti i test esistenti passano
- Nuove funzionalità testate in ambiente dev
- Code review completata

## 🚨 Quick Fixes Necessari

### Fix 1: Totale Preventivo Vuoto
```php
// In class-btr-preventivi-ordini.php, dopo riga 846
WC()->cart->calculate_totals();

// Aggiungi:
$cart_total = WC()->cart->get_total('');
if ($cart_total > 0) {
    update_post_meta($preventivo_id, '_totale_preventivo', $cart_total);
}
```

### Fix 2: Session Management
```php
// All'inizio di ogni metodo che usa la sessione
if (!WC()->session) {
    WC()->initialize_session();
}
```

## 📞 Support & Resources

### Documentazione di Riferimento
- [WooCommerce Payment Gateway API](https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Born to Ride Internal Docs](./docs/)

### Contatti Team
- **Tech Lead**: [Da definire]
- **QA Lead**: [Da definire]
- **DevOps**: [Da definire]

## 🎯 Next Steps

1. **Immediato**: Esegui Task 1-3 (Setup e Analisi)
2. **Domani**: Inizia implementazione core (Task 4-5)
3. **Fine settimana**: Prima demo funzionante

---

*Ultimo aggiornamento: <?php echo date('d/m/Y H:i'); ?>*