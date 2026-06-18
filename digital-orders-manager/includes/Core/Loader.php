<?php

namespace DOM\Core;

use DOM\Admin\Menu;
use DOM\Admin\Settings;
use DOM\API\OrderController;
use DOM\Frontend\Shortcode;

/**
 * Central Loader for registering all hooks
 */
class Loader {

    /**
     * Array of actions
     */
    protected array $actions = [];

    /**
     * Array of filters
     */
    protected array $filters = [];

    /**
     * Add action hook
     */
    public function add_action(string $hook, $component, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    /**
     * Add filter hook
     */
    public function add_filter(string $hook, $component, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    /**
     * Register admin hooks
     */
    public function register_admin_hooks(): void {
        $menu = new Menu();
        $settings = new Settings();

        $this->add_action('admin_menu', $menu, 'add_admin_menu');
        $this->add_action('admin_init', $settings, 'register_settings');
        $this->add_action('admin_enqueue_scripts', $menu, 'enqueue_assets');
    }

    /**
     * Register frontend hooks
     */
    public function register_frontend_hooks(): void {
        $shortcode = new Shortcode();

        $this->add_action('init', $shortcode, 'register_shortcodes');
        $this->add_action('template_redirect', $shortcode, 'handle_download');
    }

    /**
     * Register REST API hooks
     */
    public function register_api_hooks(): void {
        $api = new OrderController();

        $this->add_action('rest_api_init', $api, 'register_routes');
    }

    /**
     * Run all registered hooks
     */
    public function run(): void {
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }
    }
}
