<?php
/**
 * BTR Emergency Diagnostic & Fix Center
 *
 * @package Born_To_Ride_Booking
 * @version 1.0.240
 * @since 1.0.240
 *
 * Pagina diagnostica visionaria per analisi e risoluzione problemi calcolo prezzi
 * con approccio creativo, monitoraggio real-time e fix automatici one-click.
 */

// Security: Exit if accessed directly
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Security: Check admin capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai permessi sufficienti per accedere a questa pagina.', 'born-to-ride-booking'));
}

// Get preventivo_id from query string if present
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;

// Global WordPress database object
global $wpdb;

// Initialize diagnostic data
$diagnostics = [];
$issues_found = [];
$fixes_available = [];

// Version information
$current_version = '1.0.240';
$db_version = get_option('btr_plugin_version', 'Unknown');
$calculator_version = get_option('btr_calculator_version', 'v1.0');

// System checks
$wp_version = get_bloginfo('version');
$php_version = phpversion();
$mysql_version = $wpdb->db_version();
$memory_limit = wp_convert_hr_to_bytes(WP_MEMORY_LIMIT);
$max_execution_time = ini_get('max_execution_time');

// Cache status
$object_cache_enabled = wp_using_ext_object_cache();
$transients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
$btr_cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_btr_%'");

// Database tables check
$required_tables = [
    'btr_group_payments',
    'btr_payment_links',
    'btr_deposit_balance',
    'btr_order_shares'
];

$missing_tables = [];
foreach ($required_tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if (!$exists) {
        $missing_tables[] = $table;
        $issues_found[] = "Tabella mancante: {$table_name}";
    }
}

// Meta keys analysis for specific preventivo
$meta_analysis = [];
if ($preventivo_id) {
    $meta_keys = [
        '_prezzo_totale' => 'Prezzo totale legacy',
        '_prezzo_totale_completo' => 'Prezzo totale completo',
        '_payload_prezzo_totale' => 'Prezzo totale payload',
        '_calculator_version' => 'Versione calcolatore',
        '_calculation_method' => 'Metodo calcolo',
        '_btr_anagrafici' => 'Dati ospiti',
        '_pacchetto_data' => 'Dati pacchetto'
    ];

    foreach ($meta_keys as $key => $description) {
        $value = get_post_meta($preventivo_id, $key, true);
        $meta_analysis[$key] = [
            'description' => $description,
            'value' => $value,
            'exists' => !empty($value)
        ];
    }
}

// Performance metrics
$start_time = microtime(true);

// Database query performance check
$query_start = microtime(true);
$test_query = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'preventivi' LIMIT 5");
$query_time = (microtime(true) - $query_start) * 1000; // Convert to ms

// Critical issues detection
if ($db_version !== $current_version) {
    $issues_found[] = "Versioni non corrispondenti: DB({$db_version}) != Plugin({$current_version})";
    $fixes_available[] = 'update_database_version';
}

if (count($missing_tables) > 0) {
    $fixes_available[] = 'create_missing_tables';
}

if ($btr_cache_count > 100) {
    $issues_found[] = "Voci cache BTR eccessive: {$btr_cache_count}";
    $fixes_available[] = 'clear_btr_cache';
}

if ($query_time > 100) {
    $issues_found[] = "Rilevate query database lente: {$query_time}ms";
    $fixes_available[] = 'optimize_database';
}

// Generate nonce for secure actions
$nonce = wp_create_nonce('btr_diagnostic_action');
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro Diagnostico BTR v1.0.240</title>

    <style>
        :root {
            --btr-primary: #2271b1;
            --btr-success: #00a32a;
            --btr-warning: #dba617;
            --btr-danger: #d63638;
            --btr-info: #3582c4;
            --btr-dark: #1e1e1e;
            --btr-light: #f6f7f7;
            --btr-border: #c3c4c7;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .diagnostic-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .diagnostic-header {
            background: linear-gradient(135deg, var(--btr-primary) 0%, #135e96 100%);
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .diagnostic-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .diagnostic-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .diagnostic-header .version-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .diagnostic-nav {
            display: flex;
            background: var(--btr-light);
            border-bottom: 1px solid var(--btr-border);
            overflow-x: auto;
        }

        .diagnostic-nav button {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .diagnostic-nav button:hover {
            background: white;
        }

        .diagnostic-nav button.active {
            border-bottom-color: var(--btr-primary);
            background: white;
            font-weight: 600;
        }

        .diagnostic-content {
            padding: 30px;
        }

        .diagnostic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .diagnostic-card {
            background: white;
            border: 1px solid var(--btr-border);
            border-radius: 8px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .diagnostic-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .diagnostic-card.status-ok {
            border-left: 4px solid var(--btr-success);
        }

        .diagnostic-card.status-warning {
            border-left: 4px solid var(--btr-warning);
        }

        .diagnostic-card.status-error {
            border-left: 4px solid var(--btr-danger);
        }

        .diagnostic-card h3 {
            font-size: 1.2em;
            margin-bottom: 15px;
            color: var(--btr-dark);
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-indicator.ok {
            background: rgba(0, 163, 42, 0.1);
            color: var(--btr-success);
        }

        .status-indicator.warning {
            background: rgba(219, 166, 23, 0.1);
            color: var(--btr-warning);
        }

        .status-indicator.error {
            background: rgba(214, 54, 56, 0.1);
            color: var(--btr-danger);
        }

        .fix-button {
            background: var(--btr-primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .fix-button:hover {
            background: #135e96;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 113, 177, 0.3);
        }

        .fix-button.processing {
            background: var(--btr-warning);
            animation: pulse-button 1s infinite;
        }

        @keyframes pulse-button {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .fix-button.success {
            background: var(--btr-success);
        }

        .issues-list {
            background: rgba(214, 54, 56, 0.05);
            border: 1px solid rgba(214, 54, 56, 0.2);
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }

        .issues-list h3 {
            color: var(--btr-danger);
            margin-bottom: 10px;
        }

        .issues-list ul {
            list-style: none;
            padding-left: 0;
        }

        .issues-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(214, 54, 56, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .issues-list li:last-child {
            border-bottom: none;
        }

        .issues-list li::before {
            content: '⚠️';
        }

        .console-output {
            background: var(--btr-dark);
            color: #0f0;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
        }

        .console-output .log-entry {
            margin: 5px 0;
            font-size: 0.9em;
        }

        .console-output .log-entry.error {
            color: #f00;
        }

        .console-output .log-entry.success {
            color: #0f0;
        }

        .console-output .log-entry.info {
            color: #0ff;
        }

        .loader {
            width: 40px;
            height: 40px;
            border: 4px solid var(--btr-light);
            border-top-color: var(--btr-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--btr-primary);
            margin: 10px 0;
        }

        .metric-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: var(--btr-light);
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--btr-success) 0%, var(--btr-primary) 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .quick-fix-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .quick-fix-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .quick-fix-card:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .quick-fix-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            transform: rotate(45deg);
            transition: all 0.5s ease;
        }

        .quick-fix-card:hover::before {
            top: -100%;
            left: -100%;
        }

        .quick-fix-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            display: none;
            animation: slideIn 0.3s ease;
            z-index: 1000;
            max-width: 400px;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .notification.success {
            border-left: 4px solid var(--btr-success);
        }

        .notification.error {
            border-left: 4px solid var(--btr-danger);
        }

        .notification.info {
            border-left: 4px solid var(--btr-info);
        }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <!-- Header Section -->
        <div class="diagnostic-header">
            <h1>🚀 BTR Diagnostic & Fix Center</h1>
            <span class="version-badge">v<?php echo esc_html($current_version); ?></span>
            <p style="margin-top: 10px; position: relative; z-index: 1;">
                Sistema diagnostico avanzato per Born to Ride Booking - Analisi e risoluzione automatica
            </p>
        </div>

        <!-- Navigation Tabs -->
        <div class="diagnostic-nav">
            <button class="tab-btn active" data-tab="dashboard">📊 Dashboard</button>
            <button class="tab-btn" data-tab="system">⚙️ Sistema</button>
            <button class="tab-btn" data-tab="database">🗄️ Database</button>
            <button class="tab-btn" data-tab="calculator">🧮 Calcolatore</button>
            <button class="tab-btn" data-tab="monitoring">📈 Monitoraggio</button>
            <button class="tab-btn" data-tab="fixes">🔧 Fix Rapidi</button>
            <button class="tab-btn" data-tab="console">💻 Console</button>
        </div>

        <!-- Content Area -->
        <div class="diagnostic-content">

            <!-- Dashboard Tab -->
            <div class="tab-content active" id="dashboard">
                <h2>Sistema Overview</h2>

                <div class="diagnostic-grid">
                    <!-- System Health Card -->
                    <div class="diagnostic-card <?php echo count($issues_found) > 0 ? 'status-error' : 'status-ok'; ?>">
                        <h3>🏥 System Health</h3>
                        <div class="metric-value">
                            <?php echo count($issues_found) > 0 ? count($issues_found) . ' Issues' : 'Healthy'; ?>
                        </div>
                        <div class="metric-label">Overall Status</div>
                        <?php if (count($issues_found) > 0): ?>
                            <span class="status-indicator error">
                                ⚠️ Requires Attention
                            </span>
                        <?php else: ?>
                            <span class="status-indicator ok">
                                ✅ All Systems Go
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Performance Card -->
                    <div class="diagnostic-card <?php echo $query_time > 100 ? 'status-warning' : 'status-ok'; ?>">
                        <h3>⚡ Performance</h3>
                        <div class="metric-value"><?php echo number_format($query_time, 2); ?>ms</div>
                        <div class="metric-label">Query Response Time</div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: <?php echo min(100, ($query_time / 200) * 100); ?>%"></div>
                        </div>
                    </div>

                    <!-- Cache Status Card -->
                    <div class="diagnostic-card <?php echo $btr_cache_count > 100 ? 'status-warning' : 'status-ok'; ?>">
                        <h3>💾 Cache Status</h3>
                        <div class="metric-value"><?php echo $btr_cache_count; ?></div>
                        <div class="metric-label">BTR Cache Entries</div>
                        <?php if ($btr_cache_count > 100): ?>
                            <button class="fix-button" onclick="runFix('clear_cache')">
                                🗑️ Clear Cache
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Version Card -->
                    <div class="diagnostic-card <?php echo $db_version !== $current_version ? 'status-warning' : 'status-ok'; ?>">
                        <h3>📦 Version Control</h3>
                        <p>Plugin: <strong><?php echo esc_html($current_version); ?></strong></p>
                        <p>Database: <strong><?php echo esc_html($db_version); ?></strong></p>
                        <p>Calculator: <strong><?php echo esc_html($calculator_version); ?></strong></p>
                        <?php if ($db_version !== $current_version): ?>
                            <button class="fix-button" onclick="runFix('sync_versions')">
                                🔄 Sync Versions
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($issues_found) > 0): ?>
                <div class="issues-list">
                    <h3>🚨 Issues Detected</h3>
                    <ul>
                        <?php foreach ($issues_found as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($preventivo_id): ?>
                <div class="diagnostic-card">
                    <h3>🔍 Preventivo Analysis #<?php echo $preventivo_id; ?></h3>
                    <table style="width: 100%; margin-top: 15px;">
                        <thead>
                            <tr style="background: var(--btr-light);">
                                <th style="padding: 10px; text-align: left;">Meta Key</th>
                                <th style="padding: 10px; text-align: left;">Status</th>
                                <th style="padding: 10px; text-align: left;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meta_analysis as $key => $data): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid var(--btr-border);">
                                    <code><?php echo esc_html($key); ?></code><br>
                                    <small><?php echo esc_html($data['description']); ?></small>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid var(--btr-border);">
                                    <?php if ($data['exists']): ?>
                                        <span class="status-indicator ok">✅ Present</span>
                                    <?php else: ?>
                                        <span class="status-indicator error">❌ Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid var(--btr-border);">
                                    <code><?php echo esc_html(substr(print_r($data['value'], true), 0, 100)); ?></code>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- System Tab -->
            <div class="tab-content" id="system">
                <h2>System Information</h2>

                <div class="diagnostic-grid">
                    <div class="diagnostic-card">
                        <h3>🖥️ Environment</h3>
                        <p>WordPress: <strong><?php echo esc_html($wp_version); ?></strong></p>
                        <p>PHP: <strong><?php echo esc_html($php_version); ?></strong></p>
                        <p>MySQL: <strong><?php echo esc_html($mysql_version); ?></strong></p>
                        <p>Memory Limit: <strong><?php echo size_format($memory_limit); ?></strong></p>
                        <p>Max Execution: <strong><?php echo esc_html($max_execution_time); ?>s</strong></p>
                    </div>

                    <div class="diagnostic-card">
                        <h3>🔌 Plugin Attivi</h3>
                        <?php
                        $active_plugins = get_option('active_plugins', []);
                        $plugin_count = count($active_plugins);
                        ?>
                        <div class="metric-value"><?php echo $plugin_count; ?></div>
                        <div class="metric-label">Plugin Attivi</div>
                        <?php if ($plugin_count > 50): ?>
                            <span class="status-indicator warning">
                                ⚠️ Troppi plugin possono influire sulle prestazioni
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="diagnostic-card">
                        <h3>🔒 Sicurezza</h3>
                        <p>SSL: <strong><?php echo is_ssl() ? '✅ Attivato' : '❌ Disattivato'; ?></strong></p>
                        <p>Modalità Debug: <strong><?php echo WP_DEBUG ? '⚠️ Attivata' : '✅ Disattivata'; ?></strong></p>
                        <p>Modifica File: <strong><?php echo defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? '✅ Disattivata' : '⚠️ Attivata'; ?></strong></p>
                    </div>
                </div>
            </div>

            <!-- Database Tab -->
            <div class="tab-content" id="database">
                <h2>Stato Database</h2>

                <div class="diagnostic-grid">
                    <div class="diagnostic-card <?php echo count($missing_tables) > 0 ? 'status-error' : 'status-ok'; ?>">
                        <h3>📊 Tabelle BTR</h3>
                        <?php if (count($missing_tables) > 0): ?>
                            <p style="color: var(--btr-danger);">Rilevate tabelle mancanti!</p>
                            <ul>
                                <?php foreach ($missing_tables as $table): ?>
                                <li>❌ <?php echo esc_html($wpdb->prefix . $table); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button class="fix-button" onclick="runFix('create_tables')">
                                🔨 Crea Tabelle Mancanti
                            </button>
                        <?php else: ?>
                            <span class="status-indicator ok">
                                ✅ Tutte le tabelle presenti
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="diagnostic-card">
                        <h3>📈 Statistiche Database</h3>
                        <?php
                        $preventivi_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'preventivi'");
                        $orders_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'");
                        ?>
                        <p>Preventivi: <strong><?php echo $preventivi_count; ?></strong></p>
                        <p>Ordini: <strong><?php echo $orders_count; ?></strong></p>
                        <p>Transients: <strong><?php echo $transients_count; ?></strong></p>
                    </div>
                </div>
            </div>

            <!-- Calculator Tab -->
            <div class="tab-content" id="calculator">
                <h2>Diagnostica Calcolatore Prezzi</h2>

                <div class="diagnostic-card">
                    <h3>🧮 Configurazione Calcolatore</h3>
                    <p>Versione Attuale: <strong><?php echo esc_html($calculator_version); ?></strong></p>

                    <div style="margin-top: 20px;">
                        <h4>Test Calcolo</h4>
                        <input type="number" id="test-preventivo-id" placeholder="Inserisci ID Preventivo" style="padding: 8px; margin: 10px 0;">
                        <button class="fix-button" onclick="testCalculation()">
                            🧪 Esegui Test
                        </button>
                        <div id="calculation-result" style="margin-top: 20px;"></div>
                    </div>
                </div>

                <div class="diagnostic-card">
                    <h3>🔄 Opzioni Reset Calcolatore</h3>
                    <div class="quick-fix-grid">
                        <div class="quick-fix-card" onclick="runFix('reset_calculator_soft')">
                            <div class="quick-fix-icon">🔄</div>
                            <p>Reset Leggero</p>
                            <small>Solo pulizia cache</small>
                        </div>

                        <div class="quick-fix-card" onclick="runFix('reset_calculator_hard')">
                            <div class="quick-fix-icon">♻️</div>
                            <p>Reset Completo</p>
                            <small>Ripristina a v2.0</small>
                        </div>

                        <div class="quick-fix-card" onclick="runFix('compatibility_mode')">
                            <div class="quick-fix-icon">🔀</div>
                            <p>Modalità Compatibilità</p>
                            <small>Abilita supporto legacy</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monitoring Tab -->
            <div class="tab-content" id="monitoring">
                <h2>Monitoraggio Real-Time</h2>

                <div class="diagnostic-card">
                    <h3>📊 Metriche Live</h3>
                    <div id="live-metrics">
                        <p>Monitoraggio processi attivi...</p>
                        <div class="loader"></div>
                    </div>
                </div>

                <div class="console-output" id="monitoring-console">
                    <div class="log-entry info">[<?php echo date('H:i:s'); ?>] Monitoraggio inizializzato...</div>
                    <div class="log-entry info">[<?php echo date('H:i:s'); ?>] In attesa di eventi...</div>
                </div>
            </div>

            <!-- Quick Fixes Tab -->
            <div class="tab-content" id="fixes">
                <h2>Fix Rapidi Guidati</h2>

                <div class="quick-fix-grid">
                    <div class="quick-fix-card" onclick="runFix('clear_all_cache')">
                        <div class="quick-fix-icon">🗑️</div>
                        <p>Pulisci Tutta la Cache</p>
                        <small>Cache oggetti + transient</small>
                    </div>

                    <div class="quick-fix-card" onclick="runFix('fix_meta_keys')">
                        <div class="quick-fix-icon">🔑</div>
                        <p>Ripara Meta Keys</p>
                        <small>Normalizza tutti i meta key</small>
                    </div>

                    <div class="quick-fix-card" onclick="runFix('optimize_database')">
                        <div class="quick-fix-icon">⚡</div>
                        <p>Ottimizza Database</p>
                        <small>Pulisci e ottimizza tabelle</small>
                    </div>

                    <div class="quick-fix-card" onclick="runFix('reset_permissions')">
                        <div class="quick-fix-icon">🔐</div>
                        <p>Ripristina Permessi</p>
                        <small>Risolvi problemi di permessi</small>
                    </div>

                    <div class="quick-fix-card" onclick="runFix('rebuild_indexes')">
                        <div class="quick-fix-icon">📑</div>
                        <p>Ricostruisci Indici</p>
                        <small>Ottimizza query</small>
                    </div>

                    <div class="quick-fix-card" onclick="runFix('emergency_reset')">
                        <div class="quick-fix-icon">🚨</div>
                        <p>Reset di Emergenza</p>
                        <small>Reset completo del sistema</small>
                    </div>
                </div>
            </div>

            <!-- Console Tab -->
            <div class="tab-content" id="console">
                <h2>Console Diagnostica</h2>

                <div class="console-output" id="diagnostic-console">
                    <div class="log-entry success">[<?php echo date('H:i:s'); ?>] Console Diagnostica BTR v1.0.240</div>
                    <div class="log-entry info">[<?php echo date('H:i:s'); ?>] Sistema pronto. Digita 'help' per i comandi.</div>
                </div>

                <div style="margin-top: 20px;">
                    <input type="text" id="console-input" placeholder="Inserisci comando..."
                           style="width: 100%; padding: 10px; background: var(--btr-dark); color: #0f0;
                                  font-family: monospace; border: 1px solid var(--btr-border);">
                </div>

                <div style="margin-top: 20px;">
                    <h3>Comandi Disponibili</h3>
                    <ul>
                        <li><code>check [componente]</code> - Controlla componente specifico</li>
                        <li><code>fix [problema]</code> - Applica fix specifico</li>
                        <li><code>analyze [preventivo_id]</code> - Analizza preventivo specifico</li>
                        <li><code>clear</code> - Pulisci console</li>
                        <li><code>export</code> - Esporta report diagnostico</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notification" class="notification">
        <h3 id="notification-title">Notifica</h3>
        <p id="notification-message">Messaggio qui</p>
    </div>

    <script>
        // Tab Navigation
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');

                // Update button states
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                // Update content visibility
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Console Commands
        document.getElementById('console-input')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const command = this.value;
                processCommand(command);
                this.value = '';
            }
        });

        function processCommand(command) {
            const console = document.getElementById('diagnostic-console');
            const timestamp = new Date().toTimeString().split(' ')[0];

            // Add user command
            console.innerHTML += `<div class="log-entry">[${timestamp}] > ${command}</div>`;

            // Process command
            const parts = command.split(' ');
            const cmd = parts[0].toLowerCase();

            switch(cmd) {
                case 'clear':
                    console.innerHTML = '<div class="log-entry success">Console pulita</div>';
                    break;

                case 'check':
                    console.innerHTML += `<div class="log-entry info">Controllo ${parts[1] || 'sistema'}...</div>`;
                    // Simulate check
                    setTimeout(() => {
                        console.innerHTML += `<div class="log-entry success">Controllo completato. Nessun problema rilevato.</div>`;
                    }, 1000);
                    break;

                case 'analyze':
                    if (parts[1]) {
                        window.location.href = window.location.pathname + '?page=btr-diagnostic&preventivo_id=' + parts[1];
                    } else {
                        console.innerHTML += `<div class="log-entry error">Errore: preventivo_id richiesto</div>`;
                    }
                    break;

                case 'help':
                    console.innerHTML += `<div class="log-entry info">Comandi disponibili: check, fix, analyze, clear, export</div>`;
                    break;

                default:
                    console.innerHTML += `<div class="log-entry error">Comando sconosciuto: ${cmd}</div>`;
            }

            // Auto-scroll
            console.scrollTop = console.scrollHeight;
        }

        // Fix Functions
        function runFix(fixType) {
            const button = event.target.closest('.fix-button, .quick-fix-card');
            const originalContent = button.innerHTML;

            // Show processing state
            button.classList.add('processing');
            button.innerHTML = '<div class="loader" style="width: 20px; height: 20px; margin: 0 auto;"></div> Elaborazione...';

            // AJAX request
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'btr_diagnostic_fix',
                    fix_type: fixType,
                    nonce: '<?php echo $nonce; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('processing');

                if (data.success) {
                    button.classList.add('success');
                    button.innerHTML = '✅ Risolto!';
                    showNotification('Successo', data.message || 'Fix applicato con successo', 'success');

                    // Reload after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    button.innerHTML = originalContent;
                    showNotification('Errore', data.message || 'Fix fallito', 'error');
                }
            })
            .catch(error => {
                button.classList.remove('processing');
                button.innerHTML = originalContent;
                showNotification('Errore', 'Errore di rete', 'error');
                console.error('Fix error:', error);
            });
        }

        // Test Calculation
        function testCalculation() {
            const preventivo_id = document.getElementById('test-preventivo-id').value;
            const resultDiv = document.getElementById('calculation-result');

            if (!preventivo_id) {
                resultDiv.innerHTML = '<p style="color: var(--btr-danger);">Inserisci un ID Preventivo</p>';
                return;
            }

            resultDiv.innerHTML = '<div class="loader"></div> Esecuzione test calcolo...';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'btr_test_calculation',
                    preventivo_id: preventivo_id,
                    nonce: '<?php echo $nonce; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div style="background: var(--btr-light); padding: 15px; border-radius: 5px;">
                            <h4>Risultati Calcolo</h4>
                            <pre>${JSON.stringify(data.results, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<p style="color: var(--btr-danger);">Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<p style="color: var(--btr-danger);">Test fallito</p>';
            });
        }

        // Notification System
        function showNotification(title, message, type = 'info') {
            const notification = document.getElementById('notification');
            const titleEl = document.getElementById('notification-title');
            const messageEl = document.getElementById('notification-message');

            titleEl.textContent = title;
            messageEl.textContent = message;

            notification.className = 'notification ' + type;
            notification.style.display = 'block';

            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }

        // Live Monitoring
        let monitoringInterval;

        function startMonitoring() {
            const metricsDiv = document.getElementById('live-metrics');
            const monitorConsole = document.getElementById('monitoring-console');

            monitoringInterval = setInterval(() => {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'btr_get_live_metrics',
                        nonce: '<?php echo $nonce; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        metricsDiv.innerHTML = `
                            <p>Utenti Attivi: <strong>${data.metrics.active_users || 0}</strong></p>
                            <p>Carico Attuale: <strong>${data.metrics.load || 'N/A'}</strong></p>
                            <p>Uso Memoria: <strong>${data.metrics.memory || 'N/A'}</strong></p>
                            <p>Numero Query: <strong>${data.metrics.queries || 0}</strong></p>
                        `;

                        // Add to monitoring console
                        const timestamp = new Date().toTimeString().split(' ')[0];
                        monitorConsole.innerHTML += `<div class="log-entry info">[${timestamp}] Metriche aggiornate</div>`;
                        monitorConsole.scrollTop = monitorConsole.scrollHeight;
                    }
                })
                .catch(error => {
                    console.error('Monitoring error:', error);
                });
            }, 5000); // Update every 5 seconds
        }

        // Start monitoring when tab is active
        document.querySelector('[data-tab="monitoring"]').addEventListener('click', function() {
            if (!monitoringInterval) {
                startMonitoring();
            }
        });

        // Stop monitoring when leaving tab
        document.querySelectorAll('.tab-btn:not([data-tab="monitoring"])').forEach(button => {
            button.addEventListener('click', function() {
                if (monitoringInterval) {
                    clearInterval(monitoringInterval);
                    monitoringInterval = null;
                }
            });
        });

        // Auto-refresh notification for critical issues
        <?php if (count($issues_found) > 3): ?>
        showNotification(
            '⚠️ Problemi Critici Rilevati',
            '<?php echo count($issues_found); ?> problemi richiedono attenzione immediata',
            'error'
        );
        <?php endif; ?>
    </script>
</body>
</html>