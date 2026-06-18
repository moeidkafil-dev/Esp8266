<?php

namespace DOM;

use DOM\Database\Schema;

/**
 * Plugin Uninstall Handler
 */
class Uninstall {

    /**
     * Cleanup plugin data on uninstall
     */
    public static function cleanup(): void {
        // Check if user wants to keep data
        if (defined('DOM_KEEP_DATA') && DOM_KEEP_DATA) {
            return;
        }

        // Drop database table
        $schema = new Schema();
        $schema->drop_table();

        // Remove all options
        delete_option('dom_max_downloads');
        delete_option('dom_expiry_days');
        delete_option('dom_db_version');

        // Clear any transients
        delete_transient('dom_orders_count');
    }
}
