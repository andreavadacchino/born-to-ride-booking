<?php
/**
 * Classe per la generazione di PDF per i preventivi
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Impedisce l'accesso diretto al file
}

/**
 * Verifica e carica la libreria TCPDF
 */
function btr_load_tcpdf() {
    // Se TCPDF è già caricato, non fare nulla
    if (class_exists('TCPDF')) {
        return true;
    }
    
    // Percorsi possibili per TCPDF
    $tcpdf_paths = array(
        // Percorso vendor standard
        plugin_dir_path(dirname(__FILE__)) . 'vendor/tcpdf/tcpdf.php',
        // Percorso nella directory lib
        plugin_dir_path(dirname(__FILE__)) . 'lib/tcpdf/tcpdf.php',
        // Percorso alternativo nella directory includes
        plugin_dir_path(dirname(__FILE__)) . 'includes/lib/tcpdf/tcpdf.php',
        // Percorso alternativo nella directory assets
        plugin_dir_path(dirname(__FILE__)) . 'assets/lib/tcpdf/tcpdf.php',
        // Plugin TCPDF Library per WordPress
        WP_PLUGIN_DIR . '/tcpdf/tcpdf.php',
        // Percorso WordPress
        ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php',
        // Percorso WordPress alternativo
        ABSPATH . 'wp-includes/tcpdf/tcpdf.php',
    );
    
    // Cerca TCPDF nei percorsi possibili
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // TCPDF non trovato
    error_log('TCPDF non trovato. Utilizzo della classe fallback per i PDF.');
    return false;
}

// Carica TCPDF o definisci una classe fallback
if (!btr_load_tcpdf() && !class_exists('TCPDF')) {
    /**
     * Classe fallback per TCPDF
     * Implementa solo i metodi essenziali per evitare errori fatali
     */
    class TCPDF {
        protected $title = '';
        protected $author = '';
        protected $subject = '';
        protected $keywords = '';
        
        public function SetCreator($creator) { }
        public function SetAuthor($author) { $this->author = $author; }
        public function SetTitle($title) { $this->title = $title; }
        public function SetSubject($subject) { $this->subject = $subject; }
        public function SetKeywords($keywords) { $this->keywords = $keywords; }
        public function setPrintHeader($val) { }
        public function setPrintFooter($val) { }
        public function SetMargins($left, $top, $right = -1) { }
        public function SetAutoPageBreak($auto, $margin = 0) { }
        public function AddPage($orientation = '', $format = '', $keepmargins = false) { }
        public function SetFont($family, $style = '', $size = 0) { }
        public function SetTextColor($r, $g = -1, $b = -1) { }
        public function SetY($y) { }
        public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false) { }
        public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) { }
        public function Ln($h = '') { }
        public function SetDrawColor($r, $g = -1, $b = -1) { }
        public function Line($x1, $y1, $x2, $y2) { }
        public function GetY() { return 0; }
        public function Image($file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false) { }
        public function Output($name = 'doc.pdf', $dest = 'I') {
            // Genera un file di testo semplice come fallback
            $content = "PDF FALLBACK\n\n";
            $content .= "Title: {$this->title}\n";
            $content .= "Author: {$this->author}\n";
            $content .= "Subject: {$this->subject}\n";
            $content .= "Keywords: {$this->keywords}\n\n";
            $content .= "Questo è un file di testo generato come fallback perché la libreria TCPDF non è disponibile.\n";
            $content .= "Per favore, installa la libreria TCPDF per generare PDF completi.\n";
            
            if ($dest === 'F') {
                // Salva il file
                file_put_contents($name, $content);
                return $name;
            }
            
            return false;
        }
    }
}

class BTR_PDF_Generator {
    /**
     * Genera un PDF per un preventivo
     *
     * @param int $preventivo_id ID del preventivo
     * @return string|bool Path del file PDF generato o false in caso di errore
     */
    public function generate_preventivo_pdf($preventivo_id) {
        if (empty($preventivo_id)) {
            error_log('ID preventivo non valido per la generazione del PDF');
            return false;
        }

        $preventivo = get_post($preventivo_id);
        if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
            error_log('Preventivo non trovato o tipo non valido');
            return false;
        }

        // Recupera i dati del preventivo
        $cliente_nome = get_post_meta($preventivo_id, '_cliente_nome', true);
        $cliente_cognome = get_post_meta($preventivo_id, '_cliente_cognome', true);
        $cliente_email = get_post_meta($preventivo_id, '_cliente_email', true);
        $cliente_telefono = get_post_meta($preventivo_id, '_cliente_telefono', true);
        $pacchetto_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $nome_pacchetto = get_the_title($pacchetto_id);
        $prezzo_totale = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        $data_pacchetto = get_post_meta($preventivo_id, '_data_pacchetto', true);
        $stato_preventivo = get_post_meta($preventivo_id, '_stato_preventivo', true) ?: 'creato';
        
        // Calcola numero di adulti e bambini
        $num_adults = 0;
        $num_children = 0;
        
        // Calcola durata
        $durata = '';
        $data_inizio = get_post_meta($pacchetto_id, '_data_inizio', true);
        $data_fine = get_post_meta($pacchetto_id, '_data_fine', true);
        
        if (!empty($data_inizio) && !empty($data_fine)) {
            $data_inizio_obj = DateTime::createFromFormat('Y-m-d', $data_inizio);
            $data_fine_obj = DateTime::createFromFormat('Y-m-d', $data_fine);
            
            if ($data_inizio_obj && $data_fine_obj) {
                $diff = $data_inizio_obj->diff($data_fine_obj);
                $giorni = $diff->days;
                $notti = $giorni;
                
                if ($giorni > 0) {
                    $durata = sprintf(
                        _n('%d giorno', '%d giorni', $giorni, 'born-to-ride-booking'),
                        $giorni
                    );
                    
                    if ($notti > 0) {
                        $durata .= ' / ' . sprintf(
                            _n('%d notte', '%d notti', $notti, 'born-to-ride-booking'),
                            $notti
                        );
                    }
                }
            }
        }
        
        // Formatta la data del pacchetto
        $data_scelta = '';
        if (!empty($data_pacchetto)) {
            $data_obj = DateTime::createFromFormat('Y-m-d', $data_pacchetto);
            if ($data_obj) {
                $data_scelta = $data_obj->format('d/m/Y');
            } else {
                $data_scelta = $data_pacchetto;
            }
        }
        
        // Prepara il riepilogo delle camere
        $riepilogo_stringa = [];
        if (!empty($camere_selezionate) && is_array($camere_selezionate)) {
            foreach ($camere_selezionate as $camera) {
                $tipo = $camera['tipo'] ?? '';
                $quantita = intval($camera['quantita'] ?? 0);
                $numero_persone = $this->determine_number_of_persons($tipo);
                $totale_persone = $numero_persone * $quantita;
                
                if ($quantita > 0) {
                    $riepilogo_stringa[] = "{$quantita} {$tipo}";
                    $num_adults += $totale_persone; // Semplificazione: consideriamo tutti adulti
                }
            }
        }

        // Inizializza PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // Imposta metadati
        $pdf->SetCreator('Born To Ride Booking');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Preventivo #' . $preventivo_id);
        $pdf->SetSubject('Preventivo di viaggio');
        $pdf->SetKeywords('preventivo, viaggio, booking');
        
        // Disabilita header e footer predefiniti
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Imposta margini
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Aggiungi pagina
        $pdf->AddPage();
        
        // Stile per il documento
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 151, 197); // Colore primario
        
        // Logo e intestazione
        $logo_path = plugin_dir_path(dirname(__FILE__)) . 'assets/img/logo.png';
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, 15, 15, 40);
            $pdf->SetY(30);
        } else {
            $pdf->Cell(0, 10, get_bloginfo('name'), 0, 1, 'L');
        }
        
        // Titolo
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'PREVENTIVO', 0, 1, 'R');
        
        // Info preventivo
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, 'Preventivo #' . $preventivo_id . ' - ' . date_i18n('d/m/Y', strtotime($preventivo->post_date)), 0, 1, 'R');
        
        // Stato preventivo
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 6, 'Stato: ' . ucfirst($stato_preventivo), 0, 1, 'R');
        
        $pdf->Ln(5);
        
        // Linea separatrice
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        
        $pdf->Ln(5);
        
        // DETTAGLI PACCHETTO
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 10, 'Dettagli Pacchetto', 0, 1, 'L');
        
        // Riepilogo
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 8, 'Riepilogo', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        
        // Riepilogo items
        $pdf->Cell(60, 6, ($num_adults + $num_children) . ' partecipanti', 0, 1, 'L');
        $pdf->Cell(60, 6, implode(' - ', $riepilogo_stringa), 0, 1, 'L');
        $pdf->Cell(60, 6, $durata, 0, 1, 'L');
        
        $pdf->Ln(3);
        
        // Griglia informazioni
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(45, 6, 'Pacchetto:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $nome_pacchetto, 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(45, 6, 'Data:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $data_scelta, 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(45, 6, 'Durata:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $durata, 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(45, 6, 'Partecipanti:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $num_adults . ' adulti' . ($num_children > 0 ? ' + ' . $num_children . ' bambini' : ''), 0, 1, 'L');
        
        $pdf->Ln(5);
        
        // DATI CLIENTE
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 10, 'Dati Cliente', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(45, 6, 'Nome:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $cliente_nome . ' ' . $cliente_cognome, 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(45, 6, 'Email:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $cliente_email, 0, 1, 'L');
        
        if (!empty($cliente_telefono)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(45, 6, 'Telefono:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, $cliente_telefono, 0, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // DETTAGLI PARTECIPANTI
        if (!empty($anagrafici) && is_array($anagrafici)) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor(0, 151, 197);
            $pdf->Cell(0, 10, 'Dettagli Partecipanti', 0, 1, 'L');
            
            foreach ($anagrafici as $index => $persona) {
                $p_nome = $persona['nome'] ?? '';
                $p_cognome = $persona['cognome'] ?? '';
                $p_email = $persona['email'] ?? '';
                $p_telefono = $persona['telefono'] ?? '';
                $p_nascita = $persona['data_nascita'] ?? '';
                $p_citta_nascita = $persona['citta_nascita'] ?? '';
                
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->Cell(0, 8, 'Partecipante ' . ($index + 1) . ': ' . $p_nome . ' ' . $p_cognome, 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 10);
                
                if (!empty($p_email)) {
                    $pdf->Cell(45, 6, 'Email:', 0, 0, 'L');
                    $pdf->Cell(0, 6, $p_email, 0, 1, 'L');
                }
                
                if (!empty($p_telefono)) {
                    $pdf->Cell(45, 6, 'Telefono:', 0, 0, 'L');
                    $pdf->Cell(0, 6, $p_telefono, 0, 1, 'L');
                }
                
                if (!empty($p_nascita)) {
                    $pdf->Cell(45, 6, 'Data di Nascita:', 0, 0, 'L');
                    $pdf->Cell(0, 6, $p_nascita, 0, 1, 'L');
                }
                
                if (!empty($p_citta_nascita)) {
                    $pdf->Cell(45, 6, 'Città di Nascita:', 0, 0, 'L');
                    $pdf->Cell(0, 6, $p_citta_nascita, 0, 1, 'L');
                }
                
                // Assicurazioni
                $assicurazioni_dettagliate = $persona['assicurazioni_dettagliate'] ?? [];
                if (!empty($assicurazioni_dettagliate)) {
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell(0, 8, 'Assicurazioni scelte:', 0, 1, 'L');
                    
                    $pdf->SetFont('helvetica', '', 10);
                    foreach ($assicurazioni_dettagliate as $slug => $dett) {
                        $descr = $dett['descrizione'] ?? $slug;
                        $importo = floatval($dett['importo'] ?? 0);
                        $perc = floatval($dett['percentuale'] ?? 0);
                        
                        $pdf->Cell(100, 6, '• ' . $descr, 0, 0, 'L');
                        
                        $prezzo_text = '';
                        if ($importo > 0) {
                            $prezzo_text .= '€' . number_format($importo, 2);
                        }
                        if ($perc > 0) {
                            $prezzo_text .= ' (+' . $perc . '%)';
                        }
                        
                        $pdf->Cell(0, 6, $prezzo_text, 0, 1, 'R');
                    }
                }
                
                $pdf->Ln(3);
            }
        }
        
        // DETTAGLI CAMERE
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 10, 'Dettagli Camere', 0, 1, 'L');
        
        if (!empty($camere_selezionate) && is_array($camere_selezionate)) {
            // Intestazione tabella
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(35, 7, 'Tipologia', 1, 0, 'C');
            $pdf->Cell(20, 7, 'Quantità', 1, 0, 'C');
            $pdf->Cell(20, 7, 'Persone', 1, 0, 'C');
            $pdf->Cell(30, 7, 'Prezzo/persona', 1, 0, 'C');
            $pdf->Cell(20, 7, 'Sconto', 1, 0, 'C');
            $pdf->Cell(30, 7, 'Supplemento', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Totale', 1, 1, 'C');
            
            // Dati tabella
            $pdf->SetFont('helvetica', '', 9);
            $totale_complessivo = 0;
            
            foreach ($camere_selezionate as $camera) {
                $tipo = $camera['tipo'] ?? '';
                $quantita = intval($camera['quantita'] ?? 0);
                $prezzo_per_persona = floatval($camera['prezzo_per_persona'] ?? 0);
                $sconto_percentuale = floatval($camera['sconto'] ?? 0);
                $supplemento = floatval($camera['supplemento'] ?? 0);
                $numero_persone = $this->determine_number_of_persons($tipo);
                $supplemento_totale = $supplemento * $numero_persone * $quantita;
                $totale_camera = ($prezzo_per_persona * $numero_persone * $quantita) + $supplemento_totale;
                $totale_complessivo += $totale_camera;
                
                $pdf->Cell(35, 7, $tipo, 1, 0, 'L');
                $pdf->Cell(20, 7, $quantita, 1, 0, 'C');
                $pdf->Cell(20, 7, $numero_persone * $quantita, 1, 0, 'C');
                $pdf->Cell(30, 7, '€' . number_format($prezzo_per_persona, 2), 1, 0, 'R');
                $pdf->Cell(20, 7, $sconto_percentuale . '%', 1, 0, 'C');
                $pdf->Cell(30, 7, '€' . number_format($supplemento_totale, 2), 1, 0, 'R');
                $pdf->Cell(25, 7, '€' . number_format($totale_camera, 2), 1, 1, 'R');
            }
            
            // Calcola totale assicurazioni
            $totale_assicurazioni = 0;
            if (!empty($anagrafici) && is_array($anagrafici)) {
                foreach ($anagrafici as $persona) {
                    if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
                        foreach ($persona['assicurazioni_dettagliate'] as $ass) {
                            $importo = floatval($ass['importo'] ?? 0);
                            $totale_assicurazioni += $importo;
                        }
                    }
                }
            }
            $totale_finale = $totale_complessivo + $totale_assicurazioni;
            
            // Totali
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(155, 7, 'Totale Camere', 1, 0, 'R');
            $pdf->Cell(25, 7, '€' . number_format($totale_complessivo, 2), 1, 1, 'R');
            
            $pdf->Cell(155, 7, 'Totale Assicurazioni', 1, 0, 'R');
            $pdf->Cell(25, 7, '€' . number_format($totale_assicurazioni, 2), 1, 1, 'R');
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(0, 151, 197);
            $pdf->Cell(155, 7, 'Totale Finale', 1, 0, 'R');
            $pdf->Cell(25, 7, '€' . number_format($totale_finale, 2), 1, 1, 'R');
        } else {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(0, 7, 'Nessuna camera selezionata.', 0, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // Note e condizioni
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 10, 'Note e Condizioni', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell(0, 5, 'Il presente preventivo ha validità di 7 giorni dalla data di emissione. Per confermare la prenotazione è necessario procedere al pagamento secondo le modalità indicate. Per maggiori informazioni sulle condizioni di prenotazione e cancellazione, vi preghiamo di consultare il nostro sito web o contattarci direttamente.', 0, 'L', false);

        // Link al preventivo online
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 8, 'Visualizza il preventivo online:', 0, 1, 'L');
        
        $preventivo_url = home_url('/riepilogo-preventivo/?preventivo_id=' . $preventivo_id);
        $pdf->SetFont('helvetica', 'U', 10);
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Cell(0, 6, $preventivo_url, 0, 1, 'L', false, $preventivo_url);

        // Footer con informazioni di contatto
        $pdf->SetY(-40);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 151, 197);
        $pdf->Cell(0, 6, 'Born To Ride Booking', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 5, get_bloginfo('name') . ' - ' . get_bloginfo('url'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Email: info@' . str_replace('www.', '', $_SERVER['HTTP_HOST']), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Tel: +39 123 456 7890', 0, 1, 'C');

        // Genera il PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/btr-preventivi';
        
        // Crea la directory se non esiste
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Crea un file .htaccess per proteggere la directory
        $htaccess_file = $pdf_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Genera il nome del file
        $filename = 'preventivo-' . $preventivo_id . '-' . sanitize_title($cliente_nome) . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;
        
        // Salva il PDF
        $pdf->Output($filepath, 'F');
        
        // Salva il percorso del file nei metadati del preventivo
        update_post_meta($preventivo_id, '_pdf_path', $filepath);
        
        return $filepath;
    }

    /**
     * Determina il numero di persone in base al tipo di stanza
     *
     * @param string $tipo Tipo di stanza (es. 'Singola', 'Doppia')
     * @return int Numero di persone
     */
    private function determine_number_of_persons($tipo) {
        switch (strtolower($tipo)) {
            case 'singola':
                return 1;
            case 'doppia':
                return 2;
            case 'tripla':
                return 3;
            case 'quadrupla':
                return 4;
            case 'quintupla':
                return 5;
            case 'condivisa':
                return 1; // Modifica se necessario
            default:
                return 1; // Default a 1 se il tipo non è riconosciuto
        }
    }
}
