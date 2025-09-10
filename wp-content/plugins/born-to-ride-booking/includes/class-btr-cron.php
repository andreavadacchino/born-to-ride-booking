<?php
class BTR_Cron {
    public function init() {
        add_action('init', [$this, 'schedule_events']);
    }

    public function schedule_events() {
        if (!wp_next_scheduled('btr_clean_expired_quotes')) {
            wp_schedule_event(time(), 'daily', 'btr_clean_expired_quotes');
        }

        add_action('btr_clean_expired_quotes', [$this, 'clean_expired_quotes']);
    }

    public function clean_expired_quotes() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}btr_quotes WHERE expires_at < NOW()"
        );
    }
}

