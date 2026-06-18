<?php
/**
 * Plugin Name: Digital Orders Manager
 * Plugin URI: https://example.com/dom
 * Description: Advanced digital orders management system for WordPress
 * Version: 1.0.0
 * Author: Developer
 * License: GPL v2 or later
 * Text Domain: digital-orders-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DOM_VERSION', '1.0.0');
define('DOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DOM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
if (file_exists(DOM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once DOM_PLUGIN_DIR . 'vendor/autoload.php';
}

use DOM\Core\Loader;
use DOM\Core\Activator;

/**
 * Main Plugin Class
 */
final class Digital_Orders_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    private function define_constants() {
        // Additional constants can be defined here
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [Activator::class, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, [$this, 'uninstall']);

        add_action('plugins_loaded', [$this, 'initialize'], 10);
    }

    public function initialize() {
        $loader = new Loader();
        $loader->register_admin_hooks();
        $loader->register_frontend_hooks();
        $loader->register_api_hooks();
        $loader->run();
    }

    public function deactivate() {
        // Cleanup on deactivation if needed
    }

    public function uninstall() {
        require_once DOM_PLUGIN_DIR . 'uninstall.php';
        DOM\Uninstall::cleanup();
    }
}

// Initialize the plugin
Digital_Orders_Manager::get_instance();
