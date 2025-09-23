<?php
/**
 * BTR GitHub Updater - Sistema di aggiornamento automatico via GitHub
 *
 * ATTENZIONE: Questo sistema ha alcuni limiti importanti:
 * - GitHub API ha rate limits (60 req/h senza auth, 5000 con token)
 * - I token per repo privati sono visibili nel codice (rischio sicurezza)
 * - Dipendenza da disponibilità GitHub
 * - Nessuna verifica firma digitale dei pacchetti
 *
 * @package BornToRideBooking
 * @version 1.0.0
 * @since 1.0.250
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_GitHub_Updater {

    /**
     * Plugin data
     */
    private $plugin_file;
    private $plugin_slug;
    private $plugin_version;
    private $plugin_data;

    /**
     * GitHub configuration
     */
    private $github_username;
    private $github_repository;
    private $github_token;

    /**
     * Cache settings
     */
    private $cache_key = 'btr_github_updater_release';
    private $cache_expiration = 43200; // 12 ore per evitare rate limits

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @param string $plugin_file Main plugin file path
     * @return BTR_GitHub_Updater
     */
    public static function get_instance($plugin_file = null) {
        if (null === self::$instance && null !== $plugin_file) {
            self::$instance = new self($plugin_file);
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param string $plugin_file Main plugin file
     */
    private function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($this->plugin_file);

        // Load configuration
        $this->load_configuration();

        // Initialize only in admin to save resources
        if (is_admin()) {
            $this->init();
        }
    }

    /**
     * Load configuration from JSON file or defaults
     */
    private function load_configuration() {
        // Default configuration
        $defaults = [
            'github_username' => '',
            'github_repository' => '',
            'github_token' => '',
            'cache_expiration' => 43200
        ];

        // Try to load from config file
        $config_file = dirname($this->plugin_file) . '/updater-config.json';

        if (file_exists($config_file)) {
            $contents = file_get_contents($config_file);
            if ($contents) {
                $config = json_decode($contents, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($config)) {
                    $config = array_merge($defaults, $config);
                } else {
                    btr_debug_log('GitHub Updater: Invalid JSON in config file');
                    $config = $defaults;
                }
            } else {
                $config = $defaults;
            }
        } else {
            $config = $defaults;
        }

        // Assign configuration
        $this->github_username = sanitize_text_field($config['github_username']);
        $this->github_repository = sanitize_text_field($config['github_repository']);
        $this->github_token = sanitize_text_field($config['github_token']);
        $this->cache_expiration = absint($config['cache_expiration']);

        // Validate configuration
        if (empty($this->github_username) || empty($this->github_repository)) {
            btr_debug_log('GitHub Updater: Missing required configuration (username/repository)');
        }
    }

    /**
     * Initialize hooks
     */
    private function init() {
        // Get plugin data from headers
        $this->plugin_data = get_file_data($this->plugin_file, [
            'Version' => 'Version',
            'Plugin Name' => 'Plugin Name',
            'Description' => 'Description',
            'Author' => 'Author',
            'Update URI' => 'Update URI'
        ]);

        $this->plugin_version = $this->plugin_data['Version'];

        // Bail if no configuration
        if (empty($this->github_username) || empty($this->github_repository)) {
            return;
        }

        // WordPress update hooks - correct filter names per WordPress docs
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_source_selection', [$this, 'rename_github_folder'], 10, 3);

        // Admin UI enhancements
        add_filter('plugin_row_meta', [$this, 'add_plugin_links'], 10, 2);

        // Handle force check
        add_action('admin_init', [$this, 'maybe_force_check']);

        // Clear cache after update
        add_action('upgrader_process_complete', [$this, 'clear_cache_after_update'], 10, 2);
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get latest release from GitHub
        $github_data = $this->get_github_release();

        if (!$github_data) {
            return $transient;
        }

        // Extract and compare versions
        $github_version = $this->normalize_version($github_data->tag_name);
        $current_version = $this->plugin_version;

        if (version_compare($github_version, $current_version, '>')) {
            // Build update object
            $update = new stdClass();
            $update->id = $this->plugin_slug;
            $update->slug = dirname($this->plugin_slug);
            $update->plugin = $this->plugin_slug;
            $update->new_version = $github_version;
            $update->url = $github_data->html_url;
            $update->package = $this->get_download_url($github_data);
            $update->tested = $this->get_tested_wp_version();
            $update->requires = '5.0';
            $update->requires_php = '7.2';
            $update->icons = $this->get_plugin_icons();

            $transient->response[$this->plugin_slug] = $update;
        }

        return $transient;
    }

    /**
     * Provide plugin information for WordPress
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $github_data = $this->get_github_release();

        if (!$github_data) {
            return $result;
        }

        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_data['Plugin Name'];
        $plugin_info->slug = dirname($this->plugin_slug);
        $plugin_info->version = $this->normalize_version($github_data->tag_name);
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->author_profile = 'https://github.com/' . $this->github_username;
        $plugin_info->last_updated = $github_data->published_at;
        $plugin_info->homepage = $github_data->html_url;
        $plugin_info->download_link = $this->get_download_url($github_data);
        $plugin_info->sections = [
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->parse_changelog($github_data->body),
        ];
        $plugin_info->banners = [];
        $plugin_info->icons = $this->get_plugin_icons();

        return $plugin_info;
    }

    /**
     * Get latest release from GitHub API
     *
     * @return object|false GitHub release data or false on error
     */
    private function get_github_release() {
        // Check cache first
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Build API URL
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repository
        );

        // Prepare request args
        $args = [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ],
        ];

        // Add authentication if token provided
        if (!empty($this->github_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }

        // Make API request
        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            btr_debug_log('GitHub Updater Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        // Handle rate limiting
        if ($response_code === 403) {
            $headers = wp_remote_retrieve_headers($response);
            if (isset($headers['x-ratelimit-remaining']) && $headers['x-ratelimit-remaining'] === '0') {
                btr_debug_log('GitHub Updater: API rate limit exceeded');
            }
            return false;
        }

        if ($response_code !== 200) {
            btr_debug_log('GitHub Updater: API returned ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || !is_object($data)) {
            btr_debug_log('GitHub Updater: Invalid API response');
            return false;
        }

        // Cache the result
        set_transient($this->cache_key, $data, $this->cache_expiration);

        return $data;
    }

    /**
     * Get download URL from release data
     *
     * @param object $release_data GitHub release data
     * @return string Download URL
     */
    private function get_download_url($release_data) {
        // Look for .zip asset first
        if (!empty($release_data->assets) && is_array($release_data->assets)) {
            foreach ($release_data->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    $url = $asset->browser_download_url;

                    // Add token for private repos
                    if (!empty($this->github_token)) {
                        $url = add_query_arg('token', $this->github_token, $url);
                    }

                    return $url;
                }
            }
        }

        // Fallback to zipball
        $url = $release_data->zipball_url;
        if (!empty($this->github_token)) {
            $url = add_query_arg('token', $this->github_token, $url);
        }

        return $url;
    }

    /**
     * Rename GitHub's generated folder to match plugin slug
     *
     * @param string $source
     * @param string $remote_source
     * @param WP_Upgrader $upgrader
     * @return string|WP_Error
     */
    public function rename_github_folder($source, $remote_source, $upgrader) {
        global $wp_filesystem;

        // Only for our plugin
        if (!isset($upgrader->skin->plugin_info) || $upgrader->skin->plugin_info !== $this->plugin_slug) {
            return $source;
        }

        // GitHub creates username-repo-hash folder, we need plugin-name folder
        $corrected_source = trailingslashit($remote_source) . dirname($this->plugin_slug);

        if ($source !== $corrected_source) {
            // Check if corrected source exists (in case of retry)
            if ($wp_filesystem->exists($corrected_source)) {
                $wp_filesystem->delete($corrected_source, true);
            }

            // Move to correct location
            if (!$wp_filesystem->move($source, $corrected_source)) {
                return new WP_Error('rename_failed', 'Unable to rename download folder');
            }

            return $corrected_source;
        }

        return $source;
    }

    /**
     * Add plugin action links
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function add_plugin_links($links, $file) {
        if ($file !== $this->plugin_slug) {
            return $links;
        }

        // Add GitHub link
        $github_data = get_transient($this->cache_key);
        if ($github_data) {
            $links[] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($github_data->html_url),
                __('View on GitHub', 'born-to-ride-booking')
            );
        }

        // Add force check link for admins
        if (current_user_can('update_plugins')) {
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    add_query_arg('force-check-btr', '1'),
                    'force_check_btr',
                    'btr_nonce'
                ),
                __('Check for updates', 'born-to-ride-booking')
            );
        }

        return $links;
    }

    /**
     * Handle force update check
     */
    public function maybe_force_check() {
        if (!isset($_GET['force-check-btr'])) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            return;
        }

        if (!wp_verify_nonce($_GET['btr_nonce'] ?? '', 'force_check_btr')) {
            return;
        }

        // Clear cache
        delete_transient($this->cache_key);

        // Force WordPress to check for updates
        delete_site_transient('update_plugins');
        wp_update_plugins();

        // Redirect back
        wp_redirect(remove_query_arg(['force-check-btr', 'btr_nonce']));
        exit;
    }

    /**
     * Clear cache after successful update
     *
     * @param WP_Upgrader $upgrader
     * @param array $options
     */
    public function clear_cache_after_update($upgrader, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            if (isset($options['plugins']) && in_array($this->plugin_slug, $options['plugins'])) {
                delete_transient($this->cache_key);
            }
        }
    }

    /**
     * Normalize version string
     *
     * @param string $version
     * @return string
     */
    private function normalize_version($version) {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');

        // Ensure it's a valid version string
        if (!preg_match('/^\d+\.\d+(\.\d+)?/', $version)) {
            return '0.0.0';
        }

        return $version;
    }

    /**
     * Get tested WordPress version
     *
     * @return string
     */
    private function get_tested_wp_version() {
        global $wp_version;

        // Report current version as tested
        return $wp_version;
    }

    /**
     * Get plugin icons
     *
     * @return array
     */
    private function get_plugin_icons() {
        $icons = [];

        // Check for local icons
        $plugin_dir = dirname($this->plugin_file);
        $plugin_url = plugin_dir_url($this->plugin_file);

        if (file_exists($plugin_dir . '/assets/icon-128x128.png')) {
            $icons['1x'] = $plugin_url . 'assets/icon-128x128.png';
        }

        if (file_exists($plugin_dir . '/assets/icon-256x256.png')) {
            $icons['2x'] = $plugin_url . 'assets/icon-256x256.png';
        }

        // Default icon
        if (empty($icons)) {
            $icons['default'] = 'dashicons-admin-plugins';
        }

        return $icons;
    }

    /**
     * Parse changelog from markdown
     *
     * @param string $markdown
     * @return string HTML
     */
    private function parse_changelog($markdown) {
        // Basic markdown to HTML conversion
        $html = $markdown;

        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $html);

        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Lists
        $html = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Line breaks
        $html = nl2br($html);

        return wp_kses_post($html);
    }
}