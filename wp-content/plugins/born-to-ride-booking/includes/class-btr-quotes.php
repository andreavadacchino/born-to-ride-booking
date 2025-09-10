<?php
class BTR_Quotes {
    public function save_quote($data) {
        global $wpdb;

        $hash = md5(json_encode($data));
        
        $wpdb->insert($wpdb->prefix . 'btr_quotes', [
            'hash' => $hash,
            'product_id' => $data['product_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'price' => $data['total_price'],
            'configuration' => json_encode($data['options']),
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + DAY_IN_SECONDS)
        ]);

        return $hash;
    }

    public function get_quote($hash) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_quotes WHERE hash = %s",
            $hash
        ));
    }
}

