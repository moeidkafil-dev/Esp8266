<?php

namespace DOM\Admin;

/**
 * Settings API Registration
 */
class Settings {

    /**
     * Register settings
     */
    public function register_settings(): void {
        // Register settings
        register_setting(
            'dom_settings_group',
            'dom_max_downloads',
            [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_max_downloads'],
                'default' => 5,
            ]
        );

        register_setting(
            'dom_settings_group',
            'dom_expiry_days',
            [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_expiry_days'],
                'default' => 7,
            ]
        );

        // Add sections
        add_settings_section(
            'dom_general_section',
            __('تنظیمات عمومی', 'digital-orders-manager'),
            [$this, 'render_general_section_desc'],
            'dom-settings'
        );

        // Add fields
        add_settings_field(
            'dom_max_downloads',
            __('حداکثر تعداد دانلود', 'digital-orders-manager'),
            [$this, 'render_max_downloads_field'],
            'dom-settings',
            'dom_general_section'
        );

        add_settings_field(
            'dom_expiry_days',
            __('مدت انقضا (روز)', 'digital-orders-manager'),
            [$this, 'render_expiry_days_field'],
            'dom-settings',
            'dom_general_section'
        );
    }

    /**
     * Render section description
     */
    public function render_general_section_desc(): void {
        echo '<p>' . esc_html__('تنظیمات مربوط به مدیریت سفارشات دیجیتال را وارد کنید.', 'digital-orders-manager') . '</p>';
    }

    /**
     * Render max downloads field
     */
    public function render_max_downloads_field(): void {
        $value = get_option('dom_max_downloads', 5);
        ?>
        <input type="number" name="dom_max_downloads" value="<?php echo esc_attr($value); ?>" min="1" class="small-text" />
        <p class="description"><?php esc_html_e('حداکثر تعداد دفعات مجاز برای دانلود هر فایل', 'digital-orders-manager'); ?></p>
        <?php
    }

    /**
     * Render expiry days field
     */
    public function render_expiry_days_field(): void {
        $value = get_option('dom_expiry_days', 7);
        ?>
        <input type="number" name="dom_expiry_days" value="<?php echo esc_attr($value); ?>" min="1" class="small-text" />
        <p class="description"><?php esc_html_e('تعداد روزهای اعتبار لینک دانلود پس از ثبت سفارش', 'digital-orders-manager'); ?></p>
        <?php
    }

    /**
     * Sanitize max downloads
     */
    public function sanitize_max_downloads($value): int {
        $value = intval($value);
        return max(1, $value);
    }

    /**
     * Sanitize expiry days
     */
    public function sanitize_expiry_days($value): int {
        $value = intval($value);
        return max(1, $value);
    }
}
