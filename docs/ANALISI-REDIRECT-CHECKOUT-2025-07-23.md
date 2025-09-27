# 🔍 Analisi Problema Redirect al Checkout

## 📅 Data: 23 Gennaio 2025

## 🎯 Problema
Cliccando "Continua" nel form anagrafici, l'utente viene reindirizzato direttamente al checkout invece che alla pagina di selezione del metodo di pagamento.

## 🔍 Analisi Tecnica

### 1. **Flusso del Form**
- **Tipo submit**: Form standard con `action="admin-post.php"` (non AJAX)
- **Action**: `btr_convert_to_checkout`
- **Gestito da**: `class-btr-preventivi-ordini.php` → `convert_to_checkout()`

### 2. **Logica di Redirect**
```php
// Default: va al checkout
$redirect_url = wc_get_checkout_url(); // riga 868

// Solo SE tutte queste condizioni sono vere, cambia redirect:
if (!$existing_plan && $totale > 0 && pagina_configurata) {
    $redirect_url = pagina_selezione_pagamento;
}
```

### 3. **Possibili Cause del Problema**

#### ❌ **Causa 1: Piano di Pagamento Esistente**
- Se il preventivo ha già un `_payment_plan_id` salvato
- La condizione `!$existing_plan` fallisce
- Risultato: redirect diretto al checkout

#### ❌ **Causa 2: Totale Preventivo = 0**
- Se `_totale_preventivo` è 0 o non valido
- La condizione `$totale > 0` fallisce
- Risultato: redirect diretto al checkout

#### ❌ **Causa 3: Pagina Non Configurata**
- Se `btr_payment_selection_page_id` non è impostato
- Se la pagina con slug `selezione-piano-pagamento` non esiste
- Risultato: redirect diretto al checkout

## 🐛 Script di Debug

### 1. **debug-convert-to-checkout.php**
Verifica tutte le condizioni per il preventivo specifico:
- Esistenza e totale del preventivo
- Piano di pagamento esistente
- Configurazione pagina
- Simula il redirect

### 2. **debug-redirect-pagamento.php**
Verifica la configurazione generale del sistema:
- Piani di pagamento abilitati
- Pagina configurata
- Shortcode registrato

## 🔧 Soluzioni

### Soluzione 1: **Verifica Configurazione**
1. Esegui `/tests/debug-convert-to-checkout.php`
2. Identifica quale condizione fallisce
3. Risolvi il problema specifico:
   - Reset piano esistente
   - Verifica totale preventivo
   - Configura pagina

### Soluzione 2: **Fix Temporaneo**
Forza il redirect modificando `class-btr-preventivi-ordini.php`:

```php
// Riga 903, cambia:
if (!$existing_plan) {

// In:
if (true) { // Forza sempre selezione pagamento
```

### Soluzione 3: **Reset Piano di Pagamento**
Se il preventivo ha già un piano:
```php
delete_post_meta($preventivo_id, '_payment_plan_id');
delete_post_meta($preventivo_id, '_payment_plan_type');
```

## 📋 Checklist Verifica

- [ ] Pagina con shortcode `[btr_payment_selection]` esiste
- [ ] Pagina configurata in Born to Ride > Impostazioni Pagamento
- [ ] Preventivo ha totale > 0
- [ ] Preventivo non ha piano esistente
- [ ] Classe `BTR_Payment_Selection_Shortcode` istanziata

## 🚀 Prossimi Passi

1. **Esegui debug script** per identificare la causa esatta
2. **Applica la soluzione** appropriata
3. **Testa il flusso** completo
4. **Considera**: modificare la logica per mostrare sempre la selezione pagamento