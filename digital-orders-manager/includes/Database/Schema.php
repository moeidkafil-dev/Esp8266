<?php

namespace DOM\Database;

use WPDB;

/**
 * Database Schema Manager
 */
class Schema {

    private WPDB $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get table name with prefix
     */
    public function get_table_name(): string {
        return $this->wpdb->prefix . 'digital_orders';
    }

    /**
     * Create or upgrade database table
     */
    public function upgrade(): void {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table_name = $this->get_table_name();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_key VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            customer_email VARCHAR(100) NOT NULL,
            customer_ip VARCHAR(45) DEFAULT '',
            product_name VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            file_url TEXT,
            download_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
            max_downloads INT(11) UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_key (order_key),
            KEY customer_email (customer_email),
            KEY status (status),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Version tracking for future upgrades
        update_option('dom_db_version', DOM_VERSION);
    }

    /**
     * Drop table on uninstall
     */
    public function drop_table(): void {
        $table_name = $this->get_table_name();
        $this->wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    /**
     * Check if table exists
     */
    public function table_exists(): bool {
        $table_name = $this->get_table_name();
        return $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }
}
