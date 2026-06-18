<?php

namespace DOM\Admin;

/**
 * Admin Menu Registration
 */
class Menu {

    /**
     * Add admin menu and submenus
     */
    public function add_admin_menu(): void {
        // Main menu
        add_menu_page(
            __('Digital Orders', 'digital-orders-manager'),
            __('سفارشات دیجیتال', 'digital-orders-manager'),
            'manage_options',
            'dom-orders',
            [$this, 'render_orders_page'],
            'dashicons-download',
            30
        );

        // Orders submenu
        add_submenu_page(
            'dom-orders',
            __('All Orders', 'digital-orders-manager'),
            __('لیست سفارشات', 'digital-orders-manager'),
            'manage_options',
            'dom-orders',
            [$this, 'render_orders_page']
        );

        // Settings submenu
        add_submenu_page(
            'dom-orders',
            __('Settings', 'digital-orders-manager'),
            __('تنظیمات', 'digital-orders-manager'),
            'manage_options',
            'dom-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render orders page
     */
    public function render_orders_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $list_table = new OrderListTable();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('سفارشات دیجیتال', 'digital-orders-manager'); ?></h1>
            
            <?php
            if (isset($_GET['message'])) {
                $message = sanitize_text_field(wp_unslash($_GET['message']));
                if ($message === 'updated') {
                    echo '<div class="notice notice-success"><p>' . esc_html__('سفارش با موفقیت به‌روزرسانی شد.', 'digital-orders-manager') . '</p></div>';
                } elseif ($message === 'deleted') {
                    echo '<div class="notice notice-success"><p>' . esc_html__('سفارش حذف شد.', 'digital-orders-manager') . '</p></div>';
                }
            }
            ?>
            
            <form method="get">
                <input type="hidden" name="page" value="dom-orders">
                <?php
                $list_table->search_box(__('جستجو', 'digital-orders-manager'), 'dom-search');
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('تنظیمات سفارشات دیجیتال', 'digital-orders-manager'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('dom_settings_group');
                do_settings_sections('dom-settings');
                submit_button();
                ?>
            </form>
            <div id="dom-admin-notice"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'digital-orders-manager_page_dom-settings') {
            return;
        }

        wp_enqueue_script(
            'dom-admin-settings',
            DOM_PLUGIN_URL . 'assets/js/admin-settings.js',
            ['jquery'],
            DOM_VERSION,
            true
        );

        wp_localize_script('dom-admin-settings', 'domAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dom_admin_nonce'),
        ]);
    }
}
