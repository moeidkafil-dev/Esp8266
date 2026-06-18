<?php
/**
 * Plugin Name: Teacher Evaluation Pro
 * Plugin URI: https://teacherevaluationpro.com
 * Description: AI-Powered Educational Assessment System with intelligent analytics, automated assessment, multi-tier evaluation workflows, and autonomous AI agents.
 * Version: 1.0.0
 * Author: Teacher Evaluation Pro Team
 * Author URI: https://teacherevaluationpro.com
 * License: Proprietary
 * License URI: https://teacherevaluationpro.com/license
 * Text Domain: teacher-evaluation-pro
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.2
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TEP_VERSION', '1.0.0');
define('TEP_PLUGIN_FILE', __FILE__);
define('TEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TEP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'TEP\\';
    $base_dir = TEP_PLUGIN_DIR . 'app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
final class TeacherEvaluationPro {
    
    private static ?TeacherEvaluationPro $instance = null;
    
    private ?\TEP\Core\Container $container = null;
    
    public static function getInstance(): TeacherEvaluationPro {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->checkRequirements();
        $this->initConstants();
        $this->loadDependencies();
        $this->initHooks();
    }
    
    private function checkRequirements(): void {
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            add_action('admin_notices', function(): void {
                echo '<div class="notice notice-error"><p>' . 
                    __('Teacher Evaluation Pro requires PHP 8.2 or higher.', 'teacher-evaluation-pro') . 
                    '</p></div>';
            });
            deactivate_plugins(TEP_PLUGIN_BASENAME);
            return;
        }
        
        if (version_compare(get_bloginfo('version'), '6.5', '<')) {
            add_action('admin_notices', function(): void {
                echo '<div class="notice notice-error"><p>' . 
                    __('Teacher Evaluation Pro requires WordPress 6.5 or higher.', 'teacher-evaluation-pro') . 
                    '</p></div>';
            });
            deactivate_plugins(TEP_PLUGIN_BASENAME);
            return;
        }
    }
    
    private function initConstants(): void {
        // Additional plugin constants can be defined here
    }
    
    private function loadDependencies(): void {
        // Load core files
        require_once TEP_PLUGIN_DIR . 'app/Core/Container.php';
        require_once TEP_PLUGIN_DIR . 'app/Core/Config.php';
        require_once TEP_PLUGIN_DIR . 'app/Core/Database.php';
        require_once TEP_PLUGIN_DIR . 'app/Core/EventManager.php';
        
        // Initialize service container
        $this->container = new \TEP\Core\Container();
    }
    
    private function initHooks(): void {
        // Activation hook
        register_activation_hook(TEP_PLUGIN_FILE, [$this, 'activate']);
        
        // Deactivation hook
        register_deactivation_hook(TEP_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Uninstall hook
        register_uninstall_hook(TEP_PLUGIN_FILE, ['TeacherEvaluationPro', 'uninstall']);
        
        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init'], 0);
        
        // Admin initialization
        add_action('admin_init', [$this, 'adminInit']);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        
        // Add admin menu
        add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // Register custom post types and taxonomies
        add_action('init', [$this, 'registerPostTypes']);
        
        // Register custom capabilities
        add_action('init', [$this, 'registerCapabilities']);
    }
    
    public function init(): void {
        // Load text domain
        load_plugin_textdomain('teacher-evaluation-pro', false, dirname(TEP_PLUGIN_BASENAME) . '/languages');
        
        // Initialize modules
        $this->initModules();
        
        /**
         * Fires when the plugin is fully initialized
         */
        do_action('tep_initialized');
    }
    
    public function adminInit(): void {
        // Admin-specific initialization
    }
    
    private function initModules(): void {
        // Initialize core modules
        $modules = [
            \TEP\Modules\Auth\AuthModule::class,
            \TEP\Modules\Evaluation\EvaluationModule::class,
            \TEP\Modules\Factor\FactorModule::class,
            \TEP\Modules\Report\ReportModule::class,
            \TEP\Modules\File\FileModule::class,
            \TEP\Modules\AI\AIModule::class,
            \TEP\Modules\Agent\AgentModule::class,
            \TEP\Modules\Workflow\WorkflowModule::class,
            \TEP\Modules\Notification\NotificationModule::class,
            \TEP\Modules\Analytics\AnalyticsModule::class,
            \TEP\Modules\Settings\SettingsModule::class,
        ];
        
        foreach ($modules as $module) {
            if (class_exists($module)) {
                $moduleInstance = new $module($this->container);
                if (method_exists($moduleInstance, 'register')) {
                    $moduleInstance->register();
                }
            }
        }
    }
    
    public function activate(): void {
        // Create database tables
        $this->createDatabaseTables();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        /**
         * Fires on plugin activation
         */
        do_action('tep_activated');
    }
    
    public function deactivate(): void {
        // Clear rewrite rules
        flush_rewrite_rules();
        
        /**
         * Fires on plugin deactivation
         */
        do_action('tep_deactivated');
    }
    
    public static function uninstall(): void {
        // Clean up options
        delete_option('tep_version');
        delete_option('tep_settings');
        
        /**
         * Fires on plugin uninstall
         */
        do_action('tep_uninstalled');
    }
    
    private function createDatabaseTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Evaluation tables
        $sql_evaluations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_evaluations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluator_id bigint(20) UNSIGNED NOT NULL,
            evaluatee_id bigint(20) UNSIGNED NOT NULL,
            evaluation_type varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'draft',
            score decimal(5,2) DEFAULT 0,
            feedback text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY evaluator_id (evaluator_id),
            KEY evaluatee_id (evaluatee_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Factor tables
        $sql_factors = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_factors (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100) DEFAULT '',
            weight decimal(5,2) DEFAULT 1.00,
            is_active tinyint(1) DEFAULT 1,
            is_ai_generated tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Evaluation factors relation
        $sql_evaluation_factors = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_evaluation_factors (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluation_id bigint(20) UNSIGNED NOT NULL,
            factor_id bigint(20) UNSIGNED NOT NULL,
            score decimal(5,2) DEFAULT 0,
            weight decimal(5,2) DEFAULT 1.00,
            feedback text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY evaluation_id (evaluation_id),
            KEY factor_id (factor_id)
        ) $charset_collate;";
        
        // AI agents table
        $sql_agents = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_agents (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(100) NOT NULL,
            status varchar(50) DEFAULT 'active',
            config longtext,
            last_run datetime DEFAULT NULL,
            next_run datetime DEFAULT NULL,
            execution_count bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Workflows table
        $sql_workflows = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_workflows (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            type varchar(100) NOT NULL,
            status varchar(50) DEFAULT 'active',
            steps longtext,
            triggers longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Notifications table
        $sql_notifications = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_notifications (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            type varchar(100) NOT NULL,
            channel varchar(50) DEFAULT 'in-app',
            subject varchar(255) DEFAULT '',
            message text NOT NULL,
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY channel (channel)
        ) $charset_collate;";
        
        // Reports table
        $sql_reports = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_reports (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(100) NOT NULL,
            format varchar(20) DEFAULT 'pdf',
            config longtext,
            schedule varchar(50) DEFAULT '',
            last_generated datetime DEFAULT NULL,
            next_generation datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Analytics table
        $sql_analytics = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tep_analytics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            entity_type varchar(100) DEFAULT '',
            entity_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY entity_type (entity_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta($sql_evaluations);
        dbDelta($sql_factors);
        dbDelta($sql_evaluation_factors);
        dbDelta($sql_agents);
        dbDelta($sql_workflows);
        dbDelta($sql_notifications);
        dbDelta($sql_reports);
        dbDelta($sql_analytics);
    }
    
    private function setDefaultOptions(): void {
        add_option('tep_version', TEP_VERSION);
        add_option('tep_settings', [
            'general' => [
                'institution_name' => '',
                'timezone' => wp_timezone_string(),
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format'),
            ],
            'evaluation' => [
                'enable_self_evaluation' => true,
                'enable_peer_evaluation' => true,
                'enable_student_evaluation' => true,
                'enable_manager_evaluation' => true,
                'default_evaluation_period' => 'quarterly',
                'auto_reminder_days' => 7,
            ],
            'ai' => [
                'enabled' => true,
                'provider' => 'openai',
                'api_key' => '',
                'model' => 'gpt-4',
                'temperature' => 0.7,
            ],
            'notifications' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => true,
            ],
            'security' => [
                'enable_2fa' => false,
                'session_timeout' => 3600,
                'max_login_attempts' => 5,
            ],
        ]);
    }
    
    public function registerPostTypes(): void {
        // Register custom post types for evaluations, reports, etc.
        $post_types = [
            'tep_evaluation' => [
                'labels' => [
                    'name' => __('Evaluations', 'teacher-evaluation-pro'),
                    'singular_name' => __('Evaluation', 'teacher-evaluation-pro'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => ['title', 'editor', 'author'],
            ],
            'tep_report' => [
                'labels' => [
                    'name' => __('Reports', 'teacher-evaluation-pro'),
                    'singular_name' => __('Report', 'teacher-evaluation-pro'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => ['title'],
            ],
        ];
        
        foreach ($post_types as $post_type => $args) {
            register_post_type($post_type, $args);
        }
    }
    
    public function registerCapabilities(): void {
        $roles = [
            'tep_admin' => [
                'display_name' => 'TEP Administrator',
                'capabilities' => [
                    'tep_manage_all' => true,
                    'tep_manage_evaluations' => true,
                    'tep_manage_factors' => true,
                    'tep_manage_reports' => true,
                    'tep_manage_users' => true,
                    'tep_manage_settings' => true,
                    'tep_manage_ai_agents' => true,
                    'tep_view_analytics' => true,
                ],
            ],
            'tep_evaluator' => [
                'display_name' => 'TEP Evaluator',
                'capabilities' => [
                    'tep_create_evaluations' => true,
                    'tep_view_evaluations' => true,
                    'tep_edit_own_evaluations' => true,
                    'tep_view_reports' => true,
                ],
            ],
            'tep_teacher' => [
                'display_name' => 'TEP Teacher',
                'capabilities' => [
                    'tep_view_own_evaluations' => true,
                    'tep_self_evaluate' => true,
                    'tep_view_own_reports' => true,
                ],
            ],
        ];
        
        foreach ($roles as $role_name => $role_data) {
            remove_role($role_name);
            add_role($role_name, $role_data['display_name'], $role_data['capabilities']);
        }
    }
    
    public function addAdminMenu(): void {
        // Top-level menu
        add_menu_page(
            __('Teacher Evaluation Pro', 'teacher-evaluation-pro'),
            __('TEP', 'teacher-evaluation-pro'),
            'tep_manage_all',
            'tep-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-chart-bar',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'tep-dashboard',
            __('Dashboard', 'teacher-evaluation-pro'),
            __('Dashboard', 'teacher-evaluation-pro'),
            'tep_manage_all',
            'tep-dashboard',
            [$this, 'renderDashboard']
        );
        
        // Evaluations submenu
        add_submenu_page(
            'tep-dashboard',
            __('Evaluations', 'teacher-evaluation-pro'),
            __('Evaluations', 'teacher-evaluation-pro'),
            'tep_manage_evaluations',
            'tep-evaluations',
            [$this, 'renderEvaluations']
        );
        
        // Factors submenu
        add_submenu_page(
            'tep-dashboard',
            __('Factors', 'teacher-evaluation-pro'),
            __('Factors', 'teacher-evaluation-pro'),
            'tep_manage_factors',
            'tep-factors',
            [$this, 'renderFactors']
        );
        
        // Reports submenu
        add_submenu_page(
            'tep-dashboard',
            __('Reports', 'teacher-evaluation-pro'),
            __('Reports', 'teacher-evaluation-pro'),
            'tep_manage_reports',
            'tep-reports',
            [$this, 'renderReports']
        );
        
        // AI Agents submenu
        add_submenu_page(
            'tep-dashboard',
            __('AI Agents', 'teacher-evaluation-pro'),
            __('AI Agents', 'teacher-evaluation-pro'),
            'tep_manage_ai_agents',
            'tep-agents',
            [$this, 'renderAgents']
        );
        
        // Analytics submenu
        add_submenu_page(
            'tep-dashboard',
            __('Analytics', 'teacher-evaluation-pro'),
            __('Analytics', 'teacher-evaluation-pro'),
            'tep_view_analytics',
            'tep-analytics',
            [$this, 'renderAnalytics']
        );
        
        // Settings submenu
        add_submenu_page(
            'tep-dashboard',
            __('Settings', 'teacher-evaluation-pro'),
            __('Settings', 'teacher-evaluation-pro'),
            'tep_manage_settings',
            'tep-settings',
            [$this, 'renderSettings']
        );
    }
    
    public function renderDashboard(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/dashboard.php';
    }
    
    public function renderEvaluations(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/evaluations/index.php';
    }
    
    public function renderFactors(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/factors/index.php';
    }
    
    public function renderReports(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/reports/index.php';
    }
    
    public function renderAgents(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/agents/index.php';
    }
    
    public function renderAnalytics(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/analytics/index.php';
    }
    
    public function renderSettings(): void {
        include TEP_PLUGIN_DIR . 'resources/views/admin/settings/index.php';
    }
    
    public function enqueueAdminAssets(string $hook): void {
        // Only load on TEP pages
        if (strpos($hook, 'tep-') === false) {
            return;
        }
        
        // Styles
        wp_enqueue_style(
            'tep-admin-css',
            TEP_PLUGIN_URL . 'public/css/admin.css',
            [],
            TEP_VERSION
        );
        
        // Scripts
        wp_enqueue_script(
            'tep-admin-js',
            TEP_PLUGIN_URL . 'public/js/admin.js',
            ['jquery', 'wp-api'],
            TEP_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('tep-admin-js', 'tepConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('tep/v1/'),
        ]);
    }
    
    public function enqueuePublicAssets(): void {
        // Public-facing styles and scripts
        wp_enqueue_style(
            'tep-public-css',
            TEP_PLUGIN_URL . 'public/css/public.css',
            [],
            TEP_VERSION
        );
        
        wp_enqueue_script(
            'tep-public-js',
            TEP_PLUGIN_URL . 'public/js/public.js',
            ['jquery'],
            TEP_VERSION,
            true
        );
    }
    
    public function getContainer(): \TEP\Core\Container {
        return $this->container;
    }
}

// Initialize the plugin
function tep(): TeacherEvaluationPro {
    return TeacherEvaluationPro::getInstance();
}

// Start the plugin
tep();
