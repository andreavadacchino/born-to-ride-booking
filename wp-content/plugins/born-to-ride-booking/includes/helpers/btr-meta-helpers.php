<?php
/**
 * Helper functions per gestire i meta fields in modo consistente
 * 
 * @package BornToRideBooking
 * @since 1.0.100
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ottiene il numero di adulti con fallback
 */
function btr_get_adults_count($preventivo_id) {
    $count = intval(get_post_meta($preventivo_id, '_num_adults', true));
    if (!$count) {
        $count = intval(get_post_meta($preventivo_id, '_numero_adulti', true));
    }
    return $count;
}

/**
 * Ottiene il numero di bambini con fallback
 */
function btr_get_children_count($preventivo_id) {
    $count = intval(get_post_meta($preventivo_id, '_num_children', true));
    if (!$count) {
        $count = intval(get_post_meta($preventivo_id, '_numero_bambini', true));
    }
    return $count;
}

/**
 * Ottiene il numero di neonati
 */
function btr_get_infants_count($preventivo_id) {
    return intval(get_post_meta($preventivo_id, '_num_neonati', true));
}

/**
 * Ottiene il totale partecipanti
 */
function btr_get_total_participants($preventivo_id) {
    return btr_get_adults_count($preventivo_id) + 
           btr_get_children_count($preventivo_id) + 
           btr_get_infants_count($preventivo_id);
}

/**
 * Fallback: conta partecipanti dagli anagrafici
 */
function btr_count_participants_from_anagrafici($anagrafici) {
    $counts = [
        'adults' => 0,
        'children' => 0,
        'infants' => 0
    ];
    
    if (!empty($anagrafici) && is_array($anagrafici)) {
        foreach ($anagrafici as $persona) {
            if (!empty($persona['tipo_persona'])) {
                switch ($persona['tipo_persona']) {
                    case 'adulto':
                        $counts['adults']++;
                        break;
                    case 'bambino':
                    case 'ragazzo':
                        $counts['children']++;
                        break;
                    case 'neonato':
                        $counts['infants']++;
                        break;
                    default:
                        // Se tipo_persona non è definito, determina dall'età
                        if (!empty($persona['data_nascita'])) {
                            $age = btr_calculate_age($persona['data_nascita']);
                            if ($age < 3) {
                                $counts['infants']++;
                            } elseif ($age < 18) {
                                $counts['children']++;
                            } else {
                                $counts['adults']++;
                            }
                        } else {
                            // Default ad adulto se non possiamo determinare
                            $counts['adults']++;
                        }
                }
            }
        }
    }
    
    return $counts;
}

/**
 * Calcola l'età da una data di nascita
 */
function btr_calculate_age($birth_date) {
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

/**
 * Ottiene i dati dei partecipanti con fallback agli anagrafici
 */
function btr_get_participants_data($preventivo_id) {
    $data = [
        'adults' => btr_get_adults_count($preventivo_id),
        'children' => btr_get_children_count($preventivo_id),
        'infants' => btr_get_infants_count($preventivo_id)
    ];
    
    // Se tutti sono zero, prova con gli anagrafici
    if (($data['adults'] + $data['children'] + $data['infants']) === 0) {
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!empty($anagrafici)) {
            $counts = btr_count_participants_from_anagrafici($anagrafici);
            $data = $counts;
        }
    }
    
    return $data;
}

/**
 * Salva i conteggi dei partecipanti in modo consistente
 */
function btr_save_participants_count($preventivo_id, $adults, $children, $infants) {
    // Salva con entrambi i nomi per retrocompatibilità
    update_post_meta($preventivo_id, '_num_adults', intval($adults));
    update_post_meta($preventivo_id, '_numero_adulti', intval($adults));
    
    update_post_meta($preventivo_id, '_num_children', intval($children));
    update_post_meta($preventivo_id, '_numero_bambini', intval($children));
    
    update_post_meta($preventivo_id, '_num_neonati', intval($infants));
}

/**
 * Valida che la somma degli importi individuali coincida con il totale atteso
 *
 * @param float $expected_total Totale del preventivo
 * @param array $amounts        Importi individuali
 * @param float $tolerance      Tolleranza ammessa
 * @return array{is_valid:bool,expected:float,sum:float,difference:float}
 */
function btr_group_payment_amounts_are_valid($expected_total, array $amounts, $tolerance = 0.01) {
    $expected = round((float) $expected_total, 2);
    $sum = 0.0;

    foreach ($amounts as $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $sum += (float) $value;
    }

    $sum = round($sum, 2);
    $difference = round($sum - $expected, 2);

    return [
        'is_valid'   => abs($difference) <= $tolerance,
        'expected'   => $expected,
        'sum'        => $sum,
        'difference' => $difference,
    ];
}

/**
 * Valida che il numero di quote assegnate copra tutti i partecipanti
 *
 * @param int   $expected_shares Numero totale di partecipanti da coprire
 * @param array $shares          Quote assegnate (solo partecipanti selezionati)
 * @return array{is_valid:bool,expected:int,sum:int}
 */
function btr_group_payment_shares_are_valid($expected_shares, array $shares) {
    $expected = (int) $expected_shares;

    if ($expected <= 0) {
        return [
            'is_valid' => true,
            'expected' => $expected,
            'sum'      => array_sum(array_map('intval', $shares)),
        ];
    }

    $sum = 0;
    foreach ($shares as $value) {
        $sum += (int) $value;
    }

    return [
        'is_valid' => ($sum === $expected),
        'expected' => $expected,
        'sum'      => $sum,
    ];
}
