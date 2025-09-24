<?php
/**
 * BTR Diagnostic Menu Integration
 *
 * @package Born_To_Ride_Booking
 * @version 1.0.240
 * @since 1.0.240
 *
 * Integra il sistema diagnostico nel menu admin di WordPress
 */

class BTR_Diagnostic_Menu {

    /**
     * Initialize the diagnostic menu system
     */
    public static function init() {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 100);

        // Register admin styles and scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // Add admin notices for critical issues
        add_action('admin_notices', [__CLASS__, 'show_critical_issues_notice']);

        // Add toolbar quick access
        add_action('admin_bar_menu', [__CLASS__, 'add_toolbar_item'], 999);
    }

    /**
     * Add diagnostic page to admin menu
     */
    public static function add_admin_menu() {
        // Add main diagnostic page with custom icon
        $icon = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEwIDJDNS41ODIgMiAyIDUuNTgyIDIgMTBDMiAxNC40MTggNS41ODIgMTggMTAgMThDMTQuNDE4IDE4IDE4IDE0LjQxOCAxOCAxMEMxOCA1LjU4MiAxNC40MTggMiAxMCAyWk0xMCA0QzEzLjMxNCA0IDE2IDYuNjg2IDE2IDEwQzE2IDEzLjMxNCAxMy4zMTQgMTYgMTAgMTZDNi42ODYgMTYgNCAxMy4zMTQgNCAxMEM0IDYuNjg2IDYuNjg2IDQgMTAgNFpNMTAgNkM4LjkgNiA4IDYuOSA4IDhDOCA5LjEgOC45IDEwIDEwIDEwQzExLjEgMTAgMTIgOS4xIDEyIDhDMTIgNi45IDExLjEgNiAxMCA2WiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+';

        add_menu_page(
            __('BTR Diagnostics', 'born-to-ride-booking'),
            __('🚀 BTR Diagnostic', 'born-to-ride-booking'),
            'manage_options',
            'btr-diagnostic',
            [__CLASS__, 'render_diagnostic_page'],
            $icon,
            3  // Position near top of menu
        );

        // Add submenu for quick fixes
        add_submenu_page(
            'btr-diagnostic',
            __('Quick Fixes', 'born-to-ride-booking'),
            __('⚡ Quick Fixes', 'born-to-ride-booking'),
            'manage_options',
            'btr-diagnostic-fixes',
            [__CLASS__, 'render_quick_fixes_page']
        );

        // Add submenu for system info
        add_submenu_page(
            'btr-diagnostic',
            __('System Info', 'born-to-ride-booking'),
            __('ℹ️ System Info', 'born-to-ride-booking'),
            'manage_options',
            'btr-diagnostic-info',
            [__CLASS__, 'render_system_info_page']
        );

        // Add submenu for live monitoring
        add_submenu_page(
            'btr-diagnostic',
            __('Live Monitor', 'born-to-ride-booking'),
            __('📊 Live Monitor', 'born-to-ride-booking'),
            'manage_options',
            'btr-diagnostic-monitor',
            [__CLASS__, 'render_monitoring_page']
        );
    }

    /**
     * Render the main diagnostic page
     */
    public static function render_diagnostic_page() {
        // Include the diagnostic page
        if (file_exists(BTR_PLUGIN_DIR . 'admin/btr-emergency-diagnostic.php')) {
            require_once BTR_PLUGIN_DIR . 'admin/btr-emergency-diagnostic.php';
        } else {
            echo '<div class="notice notice-error"><p>Diagnostic page file not found!</p></div>';
        }
    }

    /**
     * Render quick fixes page
     */
    public static function render_quick_fixes_page() {
        ?>
        <div class="wrap">
            <h1>⚡ <?php _e('BTR Quick Fixes', 'born-to-ride-booking'); ?></h1>

            <style>
                .btr-fixes-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                .btr-fix-card {
                    background: white;
                    border: 1px solid #ccd0d4;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
                }
                .btr-fix-card:hover {
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .btr-fix-card h3 {
                    margin-top: 0;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .risk-safe { color: #00a32a; }
                .risk-moderate { color: #dba617; }
                .risk-high { color: #d63638; }
            </style>

            <div class="btr-fixes-grid">
                <!-- Clear Cache -->
                <div class="btr-fix-card">
                    <h3>🗑️ <?php _e('Clear Cache', 'born-to-ride-booking'); ?></h3>
                    <p><?php _e('Removes all BTR transients and cache entries', 'born-to-ride-booking'); ?></p>
                    <p><span class="risk-safe">✓ Safe Operation</span></p>
                    <button class="button button-primary btr-quick-fix" data-fix="clear_cache">
                        <?php _e('Run Fix', 'born-to-ride-booking'); ?>
                    </button>
                </div>

                <!-- Sync Versions -->
                <div class="btr-fix-card">
                    <h3>🔄 <?php _e('Sync Versions', 'born-to-ride-booking'); ?></h3>
                    <p><?php _e('Aligns plugin and database versions', 'born-to-ride-booking'); ?></p>
                    <p><span class="risk-safe">✓ Safe Operation</span></p>
                    <button class="button button-primary btr-quick-fix" data-fix="sync_versions">
                        <?php _e('Run Fix', 'born-to-ride-booking'); ?>
                    </button>
                </div>

                <!-- Create Tables -->
                <div class="btr-fix-card">
                    <h3>📊 <?php _e('Create Tables', 'born-to-ride-booking'); ?></h3>
                    <p><?php _e('Creates missing database tables', 'born-to-ride-booking'); ?></p>
                    <p><span class="risk-moderate">⚠ Moderate Risk</span></p>
                    <button class="button button-primary btr-quick-fix" data-fix="create_tables">
                        <?php _e('Run Fix', 'born-to-ride-booking'); ?>
                    </button>
                </div>

                <!-- Fix Meta Keys -->
                <div class="btr-fix-card">
                    <h3>🔑 <?php _e('Fix Meta Keys', 'born-to-ride-booking'); ?></h3>
                    <p><?php _e('Normalizes meta keys for all preventivi', 'born-to-ride-booking'); ?></p>
                    <p><span class="risk-moderate">⚠ Moderate Risk</span></p>
                    <button class="button button-primary btr-quick-fix" data-fix="fix_meta_keys">
                        <?php _e('Run Fix', 'born-to-ride-booking'); ?>
                    </button>
                </div>

                <!-- Optimize Database -->
                <div class="btr-fix-card">
                    <h3>⚡ <?php _e('Optimize Database', 'born-to-ride-booking'); ?></h3>
                    <p><?php _e('Optimizes BTR database tables', 'born-to-ride-booking'); ?></p>
                    <p><span class="risk-moderate">⚠ Moderate Risk</span></p>
                    <button class="button button-primary btr-quick-fix" data-fix="optimize_database">
                        <?php _e('Run Fix', 'born-to-ride-booking'); ?>
                    </button>
                </div>

                <!-- Emergency Reset -->
                <div class="btr-fix-card">
                    <h3>🚨 <?php _e('Emergency Reset', 'born-to-ride-booking'); ?></h3>
                    <p><?php _e('Complete system reset - use with extreme caution!', 'born-to-ride-booking'); ?></p>
                    <p><span class="risk-high">⛔ High Risk</span></p>
                    <button class="button button-secondary btr-quick-fix" data-fix="emergency_reset"
                            onclick="return confirm('⚠️ WARNING: This will reset the entire BTR system! Are you absolutely sure?');">
                        <?php _e('Emergency Reset', 'born-to-ride-booking'); ?>
                    </button>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                $('.btr-quick-fix').on('click', function() {
                    var button = $(this);
                    var fixType = button.data('fix');
                    var originalText = button.text();

                    button.prop('disabled', true).text('Processing...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'btr_diagnostic_fix',
                            fix_type: fixType,
                            nonce: '<?php echo wp_create_nonce('btr_diagnostic_action'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                button.text('✅ Fixed!').css('background', '#00a32a');
                                alert(response.data.message || 'Fix applied successfully!');
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                alert('❌ Error: ' + (response.data.message || 'Fix failed'));
                                button.prop('disabled', false).text(originalText);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('❌ Network error: ' + error);
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render system info page
     */
    public static function render_system_info_page() {
        global $wpdb;

        // Collect detailed system information
        $system_info = self::collect_system_info();
        ?>
        <div class="wrap">
            <h1>ℹ️ <?php _e('BTR System Information', 'born-to-ride-booking'); ?></h1>

            <div class="notice notice-info">
                <p><?php _e('Use this information when requesting support or reporting issues.', 'born-to-ride-booking'); ?></p>
            </div>

            <div style="margin: 20px 0;">
                <button class="button button-primary" onclick="copySystemInfo()">
                    📋 <?php _e('Copy to Clipboard', 'born-to-ride-booking'); ?>
                </button>
                <button class="button button-secondary" onclick="downloadSystemInfo()">
                    💾 <?php _e('Download Report', 'born-to-ride-booking'); ?>
                </button>
            </div>

            <?php foreach ($system_info as $section => $data): ?>
            <div class="card" style="margin: 20px 0; padding: 20px;">
                <h2><?php echo esc_html($section); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <?php foreach ($data as $key => $value): ?>
                        <tr>
                            <td style="width: 40%;"><strong><?php echo esc_html($key); ?></strong></td>
                            <td><code><?php echo esc_html($value); ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <textarea id="system-info-text" style="display:none;"><?php
                foreach ($system_info as $section => $data) {
                    echo "=== $section ===\n";
                    foreach ($data as $key => $value) {
                        echo "$key: $value\n";
                    }
                    echo "\n";
                }
            ?></textarea>

            <script>
            function copySystemInfo() {
                var text = document.getElementById('system-info-text').value;
                navigator.clipboard.writeText(text).then(function() {
                    alert('✅ System info copied to clipboard!');
                });
            }

            function downloadSystemInfo() {
                var text = document.getElementById('system-info-text').value;
                var blob = new Blob([text], {type: 'text/plain'});
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'btr-system-info-' + Date.now() + '.txt';
                a.click();
                window.URL.revokeObjectURL(url);
            }
            </script>
        </div>
        <?php
    }

    /**
     * Render monitoring page
     */
    public static function render_monitoring_page() {
        ?>
        <div class="wrap">
            <h1>📊 <?php _e('BTR Live Monitor', 'born-to-ride-booking'); ?></h1>

            <div class="notice notice-info">
                <p><?php _e('Real-time monitoring of BTR system metrics', 'born-to-ride-booking'); ?></p>
            </div>

            <div id="monitoring-dashboard" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h3>🎯 Active Users</h3>
                    <div class="metric-value" style="font-size: 2em; color: #2271b1;">
                        <span id="active-users">-</span>
                    </div>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3>💾 Memory Usage</h3>
                    <div class="metric-value" style="font-size: 2em; color: #2271b1;">
                        <span id="memory-usage">-</span>
                    </div>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3>🔍 Queries</h3>
                    <div class="metric-value" style="font-size: 2em; color: #2271b1;">
                        <span id="query-count">-</span>
                    </div>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3>⚡ Cache Hits</h3>
                    <div class="metric-value" style="font-size: 2em; color: #2271b1;">
                        <span id="cache-hits">-</span>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 20px; padding: 20px;">
                <h3>📝 Activity Log</h3>
                <div id="activity-log" style="background: #1e1e1e; color: #0f0; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 400px; overflow-y: auto;">
                    <div>[<?php echo date('H:i:s'); ?>] Monitoring started...</div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                function updateMetrics() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'btr_get_live_metrics',
                            nonce: '<?php echo wp_create_nonce('btr_diagnostic_action'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#active-users').text(response.data.metrics.active_users || '0');
                                $('#memory-usage').text(response.data.metrics.memory || '-');
                                $('#query-count').text(response.data.metrics.queries || '0');
                                $('#cache-hits').text(response.data.metrics.cache_hits || '0');

                                // Add to log
                                var timestamp = new Date().toTimeString().split(' ')[0];
                                var log = $('#activity-log');
                                log.append('<div>[' + timestamp + '] Metrics updated</div>');
                                log.scrollTop(log[0].scrollHeight);
                            }
                        }
                    });
                }

                // Update every 5 seconds
                updateMetrics();
                setInterval(updateMetrics, 5000);
            });
            </script>
        </div>
        <?php
    }

    /**
     * Collect system information
     */
    private static function collect_system_info() {
        global $wpdb;

        return [
            'WordPress Environment' => [
                'Version' => get_bloginfo('version'),
                'Language' => get_locale(),
                'Timezone' => wp_timezone_string(),
                'Debug Mode' => WP_DEBUG ? '✅ Enabled' : '❌ Disabled',
                'Memory Limit' => WP_MEMORY_LIMIT,
                'Max Memory' => WP_MAX_MEMORY_LIMIT,
                'Multisite' => is_multisite() ? 'Yes' : 'No',
                'Site URL' => get_site_url()
            ],
            'PHP Configuration' => [
                'Version' => phpversion(),
                'Memory Limit' => ini_get('memory_limit'),
                'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
                'Max Input Vars' => ini_get('max_input_vars'),
                'Post Max Size' => ini_get('post_max_size'),
                'Upload Max Size' => ini_get('upload_max_filesize')
            ],
            'Database' => [
                'Version' => $wpdb->db_version(),
                'Charset' => $wpdb->charset,
                'Collate' => $wpdb->collate,
                'Prefix' => $wpdb->prefix,
                'BTR Tables' => self::check_btr_tables()
            ],
            'BTR Plugin Status' => [
                'Plugin Version' => '1.0.240',
                'DB Version' => get_option('btr_plugin_version', 'Not Set'),
                'Calculator Version' => get_option('btr_calculator_version', 'Not Set'),
                'Unified Calculator' => get_option('btr_use_unified_calculator') ? '✅ Enabled' : '❌ Disabled',
                'Compatibility Mode' => get_option('btr_compatibility_mode') ? '✅ Enabled' : '❌ Disabled',
                'Preventivi Count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'preventivi'")
            ]
        ];
    }

    /**
     * Check BTR tables status
     */
    private static function check_btr_tables() {
        global $wpdb;

        $tables = ['btr_group_payments', 'btr_payment_links', 'btr_deposit_balance', 'btr_order_shares'];
        $status = [];

        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'");
            $status[] = $table . ': ' . ($exists ? '✅' : '❌');
        }

        return implode(', ', $status);
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        // Only load on BTR diagnostic pages
        if (strpos($hook, 'btr-diagnostic') === false) {
            return;
        }

        wp_enqueue_style('wp-admin');
        wp_enqueue_script('jquery');
    }

    /**
     * Show critical issues notice
     */
    public static function show_critical_issues_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $issues = [];

        // Check for missing tables
        $tables = ['btr_group_payments', 'btr_payment_links'];
        foreach ($tables as $table) {
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'")) {
                $issues[] = "Tabella mancante: {$wpdb->prefix}{$table}";
            }
        }

        // Check version mismatch
        $plugin_version = '1.0.240';
        $db_version = get_option('btr_plugin_version', '');
        if ($db_version && $db_version !== $plugin_version) {
            $issues[] = "Versioni non corrispondenti: Plugin ($plugin_version) ≠ Database ($db_version)";
        }

        if (!empty($issues)) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>🚨 BTR Plugin Issues Detected:</strong></p>
                <ul style="list-style: disc; margin-left: 30px;">
                    <?php foreach ($issues as $issue): ?>
                    <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=btr-diagnostic'); ?>" class="button button-primary">
                        🚀 Open BTR Diagnostics
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Add toolbar indicator
     */
    public static function add_toolbar_item($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Quick health check
        global $wpdb;
        $has_issues = false;

        $tables = ['btr_group_payments', 'btr_payment_links'];
        foreach ($tables as $table) {
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'")) {
                $has_issues = true;
                break;
            }
        }

        $icon = $has_issues ? '🔴' : '🟢';
        $title = $has_issues ? 'BTR: Issues!' : 'BTR: OK';

        // Add main node
        $wp_admin_bar->add_node([
            'id' => 'btr-diagnostic-toolbar',
            'title' => $icon . ' ' . $title,
            'href' => admin_url('admin.php?page=btr-diagnostic')
        ]);

        // Add child nodes
        $wp_admin_bar->add_node([
            'id' => 'btr-diagnostic-dashboard',
            'parent' => 'btr-diagnostic-toolbar',
            'title' => '📊 Dashboard',
            'href' => admin_url('admin.php?page=btr-diagnostic')
        ]);

        $wp_admin_bar->add_node([
            'id' => 'btr-diagnostic-fixes',
            'parent' => 'btr-diagnostic-toolbar',
            'title' => '⚡ Quick Fixes',
            'href' => admin_url('admin.php?page=btr-diagnostic-fixes')
        ]);

        if ($has_issues) {
            $wp_admin_bar->add_node([
                'id' => 'btr-diagnostic-alert',
                'parent' => 'btr-diagnostic-toolbar',
                'title' => '<span style="color:#ff0000;">🚨 Fix Issues Now!</span>',
                'href' => admin_url('admin.php?page=btr-diagnostic')
            ]);
        }
    }
}

// Initialize the diagnostic menu system
add_action('init', ['BTR_Diagnostic_Menu', 'init']);