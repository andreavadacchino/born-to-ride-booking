# Istruzioni Aggiornamento v1.0.39

## 🚀 Aggiornamento Rapido

### Per Ambiente di Sviluppo
```bash
# 1. Crea backup
git tag backup-pre-1.0.39

# 2. Aggiorna alla v1.0.39
git checkout v1.0.39

# 3. Svuota cache
wp cache flush
```

### Per Produzione

#### Opzione 1: Aggiornamento Completo (Consigliato)
1. Backup del database e dei file
2. Carica tutti i file della v1.0.39
3. Svuota cache browser (Ctrl+F5)
4. Verifica nel log: `[BTR] Versione: 1.0.39`

#### Opzione 2: Patch Rapida (Temporanea)
Se non puoi aggiornare subito tutto il plugin:

1. Carica solo questi file:
   - `includes/class-btr-hotfix-loader.php`
   - `includes/patches/patch-extra-nights.js`
   - `born-to-ride-booking.php` (aggiornato con il loader)

2. La patch si attiverà automaticamente

## ✅ Verifica Post-Aggiornamento

### 1. Test Rapido Console
```javascript
// Esegui nella console del browser sulla pagina di booking
console.log('=== VERIFICA AGGIORNAMENTO v1.0.39 ===');
console.log('Versione plugin:', typeof BTR_VERSION !== 'undefined' ? BTR_VERSION : 'NON DEFINITA');
console.log('Patch attiva?', document.querySelector('script[src*="patch-extra-nights.js"]') ? 'SÌ' : 'NO');
console.log('btrExtraNightsCount:', window.btrExtraNightsCount);
```

### 2. Test Funzionale
1. Vai alla pagina di prenotazione
2. Seleziona: 2 adulti + 2 bambini
3. Attiva 1 notte extra
4. Verifica che il totale sia corretto (non +€20)

### 3. Verifica Log
Cerca nella console:
- `[BTR PATCH] ✅ Patch notti extra applicata` (se versione < 1.0.37)
- `[BTR] ✅ Numero notti extra dal backend: 1` (se versione ≥ 1.0.37)

## 🔍 Troubleshooting

### Problema: Il totale è ancora errato (€914,21)
**Soluzioni:**
1. Svuota cache browser (Ctrl+F5)
2. Verifica che i file siano stati caricati correttamente
3. Esegui manualmente:
   ```javascript
   window.btrExtraNightsCount = 1;
   updateRoomPrices();
   ```

### Problema: La patch non si carica
**Verifiche:**
1. Controlla che `class-btr-hotfix-loader.php` sia stato incluso
2. Verifica di essere su una pagina con il booking form
3. Controlla errori JavaScript nella console

### Problema: Errori JavaScript
**Fix:**
```javascript
// Reset completo
delete window.btrExtraNightsCount;
location.reload();
```

## 📋 Checklist Deployment

- [ ] Backup database e file
- [ ] Upload file v1.0.39
- [ ] Svuota cache server (se presente)
- [ ] Test su dispositivo desktop
- [ ] Test su dispositivo mobile
- [ ] Verifica totali corretti
- [ ] Controlla log per errori

## 🆘 Supporto

Se riscontri problemi:
1. Controlla `DOCUMENTAZIONE-v1.0.39.md` per dettagli tecnici
2. Usa `tests/force-fix-extra-nights.php` per soluzioni alternative
3. Verifica i log nella console del browser

## 📌 Note Importanti

- La patch è **temporanea** e si disattiva con versioni ≥ 1.0.37
- Non modifica dati nel database
- Completamente reversibile
- Non impatta le performance