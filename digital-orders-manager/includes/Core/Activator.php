<?php

namespace DOM\Core;

use DOM\Database\Schema;

/**
 * Plugin Activation Handler
 */
class Activator {

    /**
     * Activate plugin - create database tables and set defaults
     */
    public static function activate(): void {
        // Create database schema
        $schema = new Schema();
        $schema->upgrade();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules for API endpoints
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options(): void {
        if (!get_option('dom_max_downloads')) {
            add_option('dom_max_downloads', 5);
        }

        if (!get_option('dom_expiry_days')) {
            add_option('dom_expiry_days', 7);
        }
    }
}
