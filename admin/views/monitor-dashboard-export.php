<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BTR Monitor Dashboard Export Handler
 * 
 * Gestisce l'export dei dati di monitoring in vari formati
 */

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Verifica nonce
if (!wp_verify_nonce($_GET['nonce'] ?? '', 'btr_monitor_export')) {
    wp_die('Security check failed');
}

// Ottieni parametri
$timeframe = sanitize_text_field($_GET['timeframe'] ?? '1 DAY');
$format = sanitize_text_field($_GET['format'] ?? 'csv');
$components = array_map('sanitize_text_field', $_GET['components'] ?? ['all']);

// Initialize monitor
$monitor = BTR_Monitor::get_instance();

if (!$monitor->is_enabled()) {
    wp_die('Monitoring not enabled');
}

// Ottieni dati per export
$export_data = get_export_data($timeframe, $components);

// Export in base al formato
switch ($format) {
    case 'json':
        export_json($export_data, $timeframe);
        break;
    case 'csv':
        export_csv($export_data, $timeframe);
        break;
    case 'pdf':
        export_pdf($export_data, $timeframe);
        break;
    default:
        wp_die('Invalid export format');
}

/**
 * Ottiene dati per export
 */
function get_export_data($timeframe, $components) {
    global $wpdb;
    
    $data = [];
    
    // Calcola periodo
    $since = date('Y-m-d H:i:s', strtotime("-{$timeframe}"));
    
    $metrics_table = $wpdb->prefix . 'btr_monitor_metrics';
    $errors_table = $wpdb->prefix . 'btr_monitor_errors';
    $journey_table = $wpdb->prefix . 'btr_monitor_journey';
    $health_table = $wpdb->prefix . 'btr_monitor_health';
    
    // Export metrics
    if (in_array('all', $components) || in_array('metrics', $components)) {
        $data['metrics'] = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$metrics_table}
            WHERE timestamp >= %s
            ORDER BY timestamp DESC
        ", $since), ARRAY_A);
    }
    
    // Export errors
    if (in_array('all', $components) || in_array('errors', $components)) {
        $data['errors'] = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$errors_table}
            WHERE timestamp >= %s
            ORDER BY timestamp DESC
        ", $since), ARRAY_A);
    }
    
    // Export journey
    if (in_array('all', $components) || in_array('journey', $components)) {
        $data['journey'] = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$journey_table}
            WHERE timestamp >= %s
            ORDER BY timestamp DESC
        ", $since), ARRAY_A);
    }
    
    // Export health
    if (in_array('all', $components) || in_array('health', $components)) {
        $data['health'] = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$health_table}
            WHERE timestamp >= %s
            ORDER BY timestamp DESC
        ", $since), ARRAY_A);
    }
    
    // Aggiungi metadata
    $data['export_info'] = [
        'site_name' => get_bloginfo('name'),
        'site_url' => get_site_url(),
        'export_time' => current_time('mysql'),
        'timeframe' => $timeframe,
        'period_start' => $since,
        'period_end' => current_time('mysql'),
        'components' => $components,
        'total_records' => array_sum(array_map('count', array_filter($data, 'is_array')))
    ];
    
    return $data;
}

/**
 * Export JSON
 */
function export_json($data, $timeframe) {
    $filename = 'btr-monitor-export-' . date('Y-m-d-H-i-s') . '-' . sanitize_file_name($timeframe) . '.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Export CSV
 */
function export_csv($data, $timeframe) {
    $filename = 'btr-monitor-export-' . date('Y-m-d-H-i-s') . '-' . sanitize_file_name($timeframe) . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header con info export
    fputcsv($output, ['BTR Monitor Export Report']);
    fputcsv($output, ['Site', get_bloginfo('name')]);
    fputcsv($output, ['Export Time', current_time('mysql')]);
    fputcsv($output, ['Timeframe', $timeframe]);
    fputcsv($output, ['Period', $data['export_info']['period_start'] . ' to ' . $data['export_info']['period_end']]);
    fputcsv($output, []);
    
    // Export ogni sezione
    foreach ($data as $section => $records) {
        if ($section === 'export_info' || !is_array($records) || empty($records)) {
            continue;
        }
        
        // Section header
        fputcsv($output, [strtoupper($section) . ' DATA']);
        fputcsv($output, []);
        
        // Column headers
        if (!empty($records)) {
            fputcsv($output, array_keys($records[0]));
            
            // Data rows
            foreach ($records as $record) {
                fputcsv($output, $record);
            }
        }
        
        fputcsv($output, []);
    }
    
    fclose($output);
    exit;
}

/**
 * Export PDF
 */
function export_pdf($data, $timeframe) {
    // Check se TCPDF è disponibile (usato dal plugin BTR)
    if (!class_exists('TCPDF')) {
        wp_die('PDF export requires TCPDF library');
    }
    
    $filename = 'btr-monitor-export-' . date('Y-m-d-H-i-s') . '-' . sanitize_file_name($timeframe) . '.pdf';
    
    // Create PDF
    $pdf = new TCPDF();
    $pdf->SetCreator('BTR Monitor');
    $pdf->SetAuthor(get_bloginfo('name'));
    $pdf->SetTitle('BTR System Monitoring Report');
    $pdf->SetSubject('System Performance and Error Report');
    
    // Settings
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'BTR System Monitoring Report', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Info box
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 30, '', 1, 1, 'C', 1); // Background box
    $pdf->SetXY(20, 35);
    
    $info_lines = [
        'Site: ' . get_bloginfo('name'),
        'Export Time: ' . current_time('mysql'),
        'Timeframe: ' . $timeframe,
        'Period: ' . $data['export_info']['period_start'] . ' to ' . $data['export_info']['period_end'],
        'Total Records: ' . number_format($data['export_info']['total_records'])
    ];
    
    foreach ($info_lines as $line) {
        $pdf->Cell(0, 5, $line, 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Summary statistiche
    generate_pdf_summary($pdf, $data);
    
    // Detailed sections
    foreach (['metrics', 'errors', 'journey', 'health'] as $section) {
        if (isset($data[$section]) && !empty($data[$section])) {
            generate_pdf_section($pdf, $section, $data[$section]);
        }
    }
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    $pdf->Output($filename, 'D');
    exit;
}

/**
 * Genera summary per PDF
 */
function generate_pdf_summary($pdf, $data) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Executive Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(3);
    
    $summary = [];
    
    // Metrics summary
    if (isset($data['metrics'])) {
        $metrics = $data['metrics'];
        $performance_metrics = array_filter($metrics, function($m) { return $m['metric_type'] === 'performance'; });
        $error_metrics = array_filter($metrics, function($m) { return $m['metric_type'] === 'errors'; });
        
        $avg_response_time = 0;
        $response_time_metrics = array_filter($performance_metrics, function($m) { return $m['metric_name'] === 'request_duration'; });
        if (!empty($response_time_metrics)) {
            $avg_response_time = array_sum(array_column($response_time_metrics, 'metric_value')) / count($response_time_metrics);
        }
        
        $summary[] = sprintf('Average Response Time: %.2f ms', $avg_response_time);
        $summary[] = sprintf('Total Performance Metrics: %d', count($performance_metrics));
    }
    
    // Error summary
    if (isset($data['errors'])) {
        $errors = $data['errors'];
        $critical_errors = array_filter($errors, function($e) { return $e['error_level'] === 'critical'; });
        $warning_errors = array_filter($errors, function($e) { return $e['error_level'] === 'warning'; });
        
        $summary[] = sprintf('Total Errors: %d', count($errors));
        $summary[] = sprintf('Critical Errors: %d', count($critical_errors));
        $summary[] = sprintf('Warning Errors: %d', count($warning_errors));
    }
    
    // Journey summary
    if (isset($data['journey'])) {
        $journey = $data['journey'];
        $sessions = count(array_unique(array_column($journey, 'session_id')));
        $conversions = count(array_filter($journey, function($j) { return $j['journey_stage'] === 'conversion'; }));
        $conversion_rate = $sessions > 0 ? ($conversions / $sessions) * 100 : 0;
        
        $summary[] = sprintf('Unique Sessions: %d', $sessions);
        $summary[] = sprintf('Conversions: %d', $conversions);
        $summary[] = sprintf('Conversion Rate: %.2f%%', $conversion_rate);
    }
    
    foreach ($summary as $line) {
        $pdf->Cell(0, 6, '• ' . $line, 0, 1);
    }
    
    $pdf->Ln(10);
}

/**
 * Genera sezione dettagliata per PDF
 */
function generate_pdf_section($pdf, $section, $records) {
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($section) . ' Details', 0, 1);
    $pdf->Ln(5);
    
    if (empty($records)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 6, 'No data available for this section.', 0, 1);
        return;
    }
    
    $pdf->SetFont('helvetica', '', 8);
    
    // Limita a prime 50 righe per PDF
    $display_records = array_slice($records, 0, 50);
    
    // Table headers
    $headers = array_keys($display_records[0]);
    $col_width = 180 / count($headers); // Distribute width
    
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 8);
    
    foreach ($headers as $header) {
        $pdf->Cell($col_width, 8, ucfirst($header), 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Table rows
    $pdf->SetFont('helvetica', '', 7);
    $fill = 0;
    
    foreach ($display_records as $record) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        
        foreach ($record as $value) {
            // Truncate long values
            $display_value = strlen($value) > 30 ? substr($value, 0, 27) . '...' : $value;
            $pdf->Cell($col_width, 6, $display_value, 1, 0, 'L', 1);
        }
        $pdf->Ln();
        
        $fill = !$fill;
        
        // Check page break
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            
            // Repeat headers
            $pdf->SetFillColor(200, 200, 200);
            $pdf->SetFont('helvetica', 'B', 8);
            foreach ($headers as $header) {
                $pdf->Cell($col_width, 8, ucfirst($header), 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 7);
        }
    }
    
    if (count($records) > 50) {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 6, sprintf('Note: Showing first 50 of %d total records', count($records)), 0, 1);
    }
}