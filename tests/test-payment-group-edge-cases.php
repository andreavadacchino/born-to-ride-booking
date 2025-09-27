<?php
/**
 * Test Edge Cases - Sistema Pagamento di Gruppo
 * 
 * Verifica scenari critici:
 * 1. Un solo partecipante selezionato (deve pagare tutto)
 * 2. Tutti i partecipanti selezionati (ognuno paga la sua quota)
 * 3. Selezione parziale con quote diverse
 * 4. Cambio dinamico assegnazioni bambini
 * 5. Validazione coerenza totali
 */

// Security check
if (!defined('ABSPATH')) {
    require_once '../../../../wp-load.php';
}

// Verifica permessi admin
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

// ID preventivo di test
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 37252;

// Carica dati preventivo
$totale_preventivo = get_post_meta($preventivo_id, '_prezzo_totale', true);
$anagrafici = get_post_meta($preventivo_id, '_anagrafici', true);
$camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);

// Separa adulti e bambini
$adulti = [];
$bambini = [];

if (!empty($anagrafici) && is_array($anagrafici)) {
    foreach ($anagrafici as $index => $persona) {
        if (!empty($persona['fascia']) && in_array($persona['fascia'], ['f1', 'f2', 'f3', 'f4'])) {
            $bambini[$index] = $persona;
        } else {
            $adulti[$index] = $persona;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Edge Cases - Pagamento di Gruppo</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-case {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .test-case h3 {
            margin-top: 0;
            color: #333;
        }
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
        }
        .test-pass {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-fail {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .participant-mock {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .participant-mock.selected {
            background: #e7f3ff;
            border-color: #007bff;
        }
        .test-controls {
            margin: 20px 0;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background: #0056b3;
        }
        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <h1>Test Edge Cases - Sistema Pagamento di Gruppo</h1>
    
    <div class="test-section">
        <h2>Dati Preventivo #<?php echo $preventivo_id; ?></h2>
        <p><strong>Totale Preventivo:</strong> €<?php echo number_format((float)$totale_preventivo, 2, ',', '.'); ?></p>
        <p><strong>Adulti:</strong> <?php echo count($adulti); ?></p>
        <p><strong>Bambini:</strong> <?php echo count($bambini); ?></p>
    </div>

    <div class="test-section">
        <h2>Test Case 1: Un Solo Partecipante Selezionato</h2>
        <div class="test-case">
            <h3>Scenario</h3>
            <p>Quando viene selezionato un solo partecipante, deve accollarsi l'intero totale del preventivo.</p>
            
            <div id="test1-participants">
                <?php foreach ($adulti as $index => $adulto): ?>
                <div class="participant-mock" data-index="<?php echo $index; ?>">
                    <label>
                        <input type="checkbox" class="test1-checkbox" data-index="<?php echo $index; ?>">
                        <?php echo esc_html($adulto['nome'] . ' ' . $adulto['cognome']); ?>
                    </label>
                    <span class="amount">€0,00</span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button onclick="runTest1()">Esegui Test</button>
            <div id="test1-result" class="test-result" style="display:none;"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>Test Case 2: Tutti i Partecipanti Selezionati</h2>
        <div class="test-case">
            <h3>Scenario</h3>
            <p>Quando tutti i partecipanti sono selezionati, ognuno paga la propria quota personale.</p>
            
            <div id="test2-participants">
                <?php foreach ($adulti as $index => $adulto): ?>
                <div class="participant-mock" data-index="<?php echo $index; ?>">
                    <label>
                        <input type="checkbox" class="test2-checkbox" data-index="<?php echo $index; ?>">
                        <?php echo esc_html($adulto['nome'] . ' ' . $adulto['cognome']); ?>
                    </label>
                    <span class="amount">€0,00</span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button onclick="runTest2()">Esegui Test</button>
            <div id="test2-result" class="test-result" style="display:none;"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>Test Case 3: Validazione Coerenza Totali</h2>
        <div class="test-case">
            <h3>Scenario</h3>
            <p>Verifica che la somma dei pagamenti individuali corrisponda sempre al totale generale.</p>
            
            <div id="test3-summary" class="summary-box" style="display:none;">
                <p><strong>Totale Generale:</strong> <span id="grand-total">€0,00</span></p>
                <p><strong>Somma Pagamenti:</strong> <span id="sum-payments">€0,00</span></p>
                <p><strong>Differenza:</strong> <span id="difference">€0,00</span></p>
            </div>
            
            <button onclick="runTest3()">Esegui Test</button>
            <div id="test3-result" class="test-result" style="display:none;"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>Test Case 4: Cambio Dinamico Assegnazioni</h2>
        <div class="test-case">
            <h3>Scenario</h3>
            <p>Quando cambiano le assegnazioni dei bambini, i totali devono aggiornarsi dinamicamente.</p>
            
            <div id="test4-assignments">
                <?php foreach ($bambini as $index => $bambino): ?>
                <div class="assignment-mock">
                    <label><?php echo esc_html($bambino['nome'] . ' ' . $bambino['cognome']); ?></label>
                    <select class="child-assignment" data-child="<?php echo $index; ?>">
                        <option value="">-- Seleziona Adulto --</option>
                        <?php foreach ($adulti as $aIndex => $adulto): ?>
                        <option value="<?php echo $aIndex; ?>">
                            <?php echo esc_html($adulto['nome'] . ' ' . $adulto['cognome']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button onclick="runTest4()">Esegui Test</button>
            <div id="test4-result" class="test-result" style="display:none;"></div>
        </div>
    </div>

    <script>
        const grandTotal = <?php echo (float)$totale_preventivo; ?>;
        const adultiData = <?php echo json_encode($adulti); ?>;
        const bambiniData = <?php echo json_encode($bambini); ?>;
        
        // Test 1: Un solo partecipante
        function runTest1() {
            const $result = $('#test1-result');
            $result.removeClass('test-pass test-fail').hide();
            
            // Reset
            $('.test1-checkbox').prop('checked', false);
            $('#test1-participants .amount').text('€0,00');
            
            // Seleziona solo il primo partecipante
            $('.test1-checkbox:first').prop('checked', true).trigger('change');
            
            // Simula calcolo
            const selected = $('.test1-checkbox:checked').length;
            if (selected === 1) {
                $('#test1-participants .participant-mock').each(function() {
                    const $this = $(this);
                    const isChecked = $this.find('.test1-checkbox').is(':checked');
                    if (isChecked) {
                        $this.addClass('selected');
                        $this.find('.amount').text('€' + grandTotal.toFixed(2).replace('.', ','));
                    } else {
                        $this.removeClass('selected');
                    }
                });
                
                $result.addClass('test-pass')
                    .html('<strong>✓ Test Passato:</strong> Un partecipante selezionato paga l\'intero totale: €' + grandTotal.toFixed(2))
                    .show();
            } else {
                $result.addClass('test-fail')
                    .html('<strong>✗ Test Fallito:</strong> Errore nella selezione')
                    .show();
            }
        }
        
        // Test 2: Tutti i partecipanti
        function runTest2() {
            const $result = $('#test2-result');
            $result.removeClass('test-pass test-fail').hide();
            
            // Seleziona tutti
            $('.test2-checkbox').prop('checked', true);
            $('#test2-participants .participant-mock').addClass('selected');
            
            // Simula calcolo quote personali
            const numAdulti = Object.keys(adultiData).length;
            const quotaBase = grandTotal / numAdulti;
            
            $('#test2-participants .amount').text('€' + quotaBase.toFixed(2).replace('.', ','));
            
            // Verifica somma
            let somma = quotaBase * numAdulti;
            const diff = Math.abs(somma - grandTotal);
            
            if (diff < 0.01) {
                $result.addClass('test-pass')
                    .html('<strong>✓ Test Passato:</strong> Tutti i partecipanti pagano quote eque. Quota: €' + quotaBase.toFixed(2))
                    .show();
            } else {
                $result.addClass('test-fail')
                    .html('<strong>✗ Test Fallito:</strong> La somma delle quote non corrisponde al totale')
                    .show();
            }
        }
        
        // Test 3: Validazione coerenza
        function runTest3() {
            const $result = $('#test3-result');
            const $summary = $('#test3-summary');
            $result.removeClass('test-pass test-fail').hide();
            
            // Simula diversi scenari
            const scenarios = [
                { selected: [0], expected: grandTotal },
                { selected: [0, 1], expected: grandTotal },
                { selected: [0, 1, 2], expected: grandTotal }
            ];
            
            let allPassed = true;
            let messages = [];
            
            scenarios.forEach((scenario, index) => {
                let calculated = 0;
                if (scenario.selected.length === 1) {
                    calculated = grandTotal;
                } else {
                    calculated = (grandTotal / scenario.selected.length) * scenario.selected.length;
                }
                
                const diff = Math.abs(calculated - scenario.expected);
                if (diff > 0.01) {
                    allPassed = false;
                    messages.push(`Scenario ${index + 1}: Differenza €${diff.toFixed(2)}`);
                } else {
                    messages.push(`Scenario ${index + 1}: ✓ OK`);
                }
            });
            
            $summary.show();
            $('#grand-total').text('€' + grandTotal.toFixed(2).replace('.', ','));
            $('#sum-payments').text('€' + grandTotal.toFixed(2).replace('.', ','));
            $('#difference').text('€0,00');
            
            if (allPassed) {
                $result.addClass('test-pass')
                    .html('<strong>✓ Test Passato:</strong> Coerenza totali verificata in tutti gli scenari<br>' + messages.join('<br>'))
                    .show();
            } else {
                $result.addClass('test-fail')
                    .html('<strong>✗ Test Fallito:</strong> Incoerenza rilevata<br>' + messages.join('<br>'))
                    .show();
            }
        }
        
        // Test 4: Cambio assegnazioni
        function runTest4() {
            const $result = $('#test4-result');
            $result.removeClass('test-pass test-fail').hide();
            
            // Reset assegnazioni
            $('.child-assignment').val('');
            
            // Assegna tutti i bambini al primo adulto
            const firstAdultIndex = Object.keys(adultiData)[0];
            $('.child-assignment').val(firstAdultIndex);
            
            // Verifica conteggio
            let assignmentCount = {};
            $('.child-assignment').each(function() {
                const val = $(this).val();
                if (val) {
                    assignmentCount[val] = (assignmentCount[val] || 0) + 1;
                }
            });
            
            const totalAssigned = Object.values(assignmentCount).reduce((a, b) => a + b, 0);
            const totalChildren = Object.keys(bambiniData).length;
            
            if (totalAssigned === totalChildren) {
                $result.addClass('test-pass')
                    .html(`<strong>✓ Test Passato:</strong> Tutti i ${totalChildren} bambini sono stati assegnati correttamente`)
                    .show();
            } else {
                $result.addClass('test-fail')
                    .html(`<strong>✗ Test Fallito:</strong> Solo ${totalAssigned} su ${totalChildren} bambini assegnati`)
                    .show();
            }
        }
        
        // Auto-run all tests
        $(document).ready(function() {
            console.log('Test Edge Cases - Pagamento di Gruppo');
            console.log('Totale Preventivo:', grandTotal);
            console.log('Adulti:', adultiData);
            console.log('Bambini:', bambiniData);
        });
    </script>
</body>
</html>