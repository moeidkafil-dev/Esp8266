<?php
/**
 * Plugin Uninstall Handler
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data including database tables and options.
 */

declare(strict_types=1);

// Exit if not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Prevent execution during multisite network activation check
if (is_multisite()) {
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        tep_uninstall_plugin();
        restore_current_blog();
    }
} else {
    tep_uninstall_plugin();
}

/**
 * Uninstall the plugin
 */
function tep_uninstall_plugin(): void {
    global $wpdb;
    
    // Drop all plugin tables
    $tables = [
        'evaluations',
        'evaluation_factors',
        'factors',
        'agents',
        'workflows',
        'notifications',
        'reports',
        'analytics',
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tep_{$table}");
    }
    
    // Delete all plugin options
    $options = [
        'tep_version',
        'tep_settings',
        'tep_environment',
        'tep_capabilities',
        'tep_flush_rewrite_rules',
    ];
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Delete all transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tep_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tep_%'");
    
    // Remove user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'tep_%'");
    
    // Delete scheduled events
    wp_clear_scheduled_hook('tep_cron_hourly');
    wp_clear_scheduled_hook('tep_cron_daily');
    wp_clear_scheduled_hook('tep_cron_weekly');
    
    /**
     * Fires after the plugin has been uninstalled
     */
    do_action('tep_uninstalled_complete');
}
