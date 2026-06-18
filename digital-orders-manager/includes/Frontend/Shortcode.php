<?php

namespace DOM\Frontend;

use DOM\Database\Schema;

/**
 * Frontend Shortcodes and Download Handler
 */
class Shortcode {

    private Schema $schema;

    public function __construct() {
        $this->schema = new Schema();
        
        // Handle form submission on init hook
        add_action('init', [$this, 'handle_form_submission'], 1);
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes(): void {
        add_shortcode('dom_create_order', [$this, 'render_create_order_form']);
        add_shortcode('dom_order_status', [$this, 'render_order_status']);
    }

    /**
     * Render create order form
     */
    public function render_create_order_form(array $atts): string {
        $atts = shortcode_atts([
            'redirect_url' => '',
        ], $atts, 'dom_create_order');

        ob_start();
        ?>
        <div class="dom-create-order-form">
            <?php
            if (isset($_GET['dom_order_created']) && $_GET['dom_order_created'] === '1') {
                echo '<div class="dom-notice dom-notice-success">' . esc_html__('سفارش شما با موفقیت ثبت شد. ایمیل حاوی اطلاعات سفارش برای شما ارسال گردید.', 'digital-orders-manager') . '</div>';
            }
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('dom_create_order_action', 'dom_create_order_nonce'); ?>
                
                <p>
                    <label for="dom_customer_email"><?php esc_html_e('ایمیل *', 'digital-orders-manager'); ?></label>
                    <input type="email" name="dom_customer_email" id="dom_customer_email" required value="<?php echo esc_attr($_POST['dom_customer_email'] ?? ''); ?>" />
                </p>
                
                <p>
                    <label for="dom_product_name"><?php esc_html_e('نام محصول *', 'digital-orders-manager'); ?></label>
                    <input type="text" name="dom_product_name" id="dom_product_name" required value="<?php echo esc_attr($_POST['dom_product_name'] ?? ''); ?>" />
                </p>
                
                <p>
                    <label for="dom_amount"><?php esc_html_e('مبلغ (اختیاری)', 'digital-orders-manager'); ?></label>
                    <input type="number" name="dom_amount" id="dom_amount" step="0.01" min="0" value="<?php echo esc_attr($_POST['dom_amount'] ?? '0'); ?>" />
                </p>
                
                <p>
                    <button type="submit" name="dom_submit_order" class="button button-primary"><?php esc_html_e('ثبت سفارش', 'digital-orders-manager'); ?></button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission(): void {
        if (!isset($_POST['dom_submit_order'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['dom_create_order_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dom_create_order_nonce'])), 'dom_create_order_action')) {
            wp_die(__('خطای امنیتی. لطفاً صفحه را رفرش کرده و مجدد تلاش کنید.', 'digital-orders-manager'));
        }

        // Sanitize inputs
        $email = sanitize_email(wp_unslash($_POST['dom_customer_email'] ?? ''));
        $product_name = sanitize_text_field(wp_unslash($_POST['dom_product_name'] ?? ''));
        $amount = floatval($_POST['dom_amount'] ?? 0);

        // Validate
        if (empty($email) || empty($product_name)) {
            wp_die(__('لطفاً فیلدهای الزامی را پر کنید.', 'digital-orders-manager'));
        }

        if (!is_email($email)) {
            wp_die(__('ایمیل وارد شده معتبر نیست.', 'digital-orders-manager'));
        }

        // Create order
        $order_key = $this->create_order($email, $product_name, $amount);

        // Send email
        $this->send_order_email($email, $order_key, $product_name, $amount);

        // Redirect
        wp_safe_redirect(add_query_arg('dom_order_created', '1', remove_query_arg('dom_order_created')));
        exit;
    }

    /**
     * Create order in database
     */
    private function create_order(string $email, string $product_name, float $amount): string {
        global $wpdb;
        $table_name = $this->schema->get_table_name();

        // Generate unique order key
        $order_key = wp_generate_uuid4();

        // Get settings
        $max_downloads = intval(get_option('dom_max_downloads', 5));
        $expiry_days = intval(get_option('dom_expiry_days', 7));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));

        // Prepare data
        $data = [
            'order_key' => $order_key,
            'user_id' => get_current_user_id(),
            'customer_email' => $email,
            'customer_ip' => DOM_Helper::get_client_ip(),
            'product_name' => $product_name,
            'amount' => $amount,
            'status' => 'pending',
            'file_url' => '',
            'download_count' => 0,
            'max_downloads' => $max_downloads,
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $wpdb->insert($table_name, $data);

        return $order_key;
    }

    /**
     * Send order confirmation email
     */
    private function send_order_email(string $to, string $order_key, string $product_name, float $amount): void {
        $subject = sprintf(__('سفارش جدید: %s', 'digital-orders-manager'), $product_name);
        
        $message = sprintf(
            __('سلام،%1$sسفارش شما با موفقیت ثبت شد.%2$sمحصول: %3$s%2$sمبلغ: %4$s%2$sکلید سفارش: %5$s%2$sبرای مشاهده وضعیت سفارش از لینک زیر استفاده کنید:%2$s%6$s', 'digital-orders-manager'),
            "\n",
            "\n",
            $product_name,
            number_format($amount, 0),
            $order_key,
            home_url('/?order_key=' . $order_key)
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Render order status
     */
    public function render_order_status(array $atts): string {
        $atts = shortcode_atts([
            'order_key' => isset($_GET['order_key']) ? sanitize_text_field(wp_unslash($_GET['order_key'])) : '',
        ], $atts, 'dom_order_status');

        $order_key = sanitize_text_field($atts['order_key']);

        if (empty($order_key)) {
            return '<p>' . esc_html__('لطفاً کلید سفارش را وارد کنید.', 'digital-orders-manager') . '</p>';
        }

        global $wpdb;
        $table_name = $this->schema->get_table_name();
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_key = %s",
            $order_key
        ), ARRAY_A);

        if (!$order) {
            return '<p>' . esc_html__('سفارش یافت نشد.', 'digital-orders-manager') . '</p>';
        }

        ob_start();
        ?>
        <div class="dom-order-status">
            <h3><?php esc_html_e('وضعیت سفارش', 'digital-orders-manager'); ?></h3>
            <table class="dom-order-table">
                <tr>
                    <th><?php esc_html_e('محصول:', 'digital-orders-manager'); ?></th>
                    <td><?php echo esc_html($order['product_name']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('وضعیت:', 'digital-orders-manager'); ?></th>
                    <td><?php echo esc_html($this->get_status_label($order['status'])); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('تعداد دانلود:', 'digital-orders-manager'); ?></th>
                    <td><?php echo intval($order['download_count']); ?> / <?php echo intval($order['max_downloads']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('تاریخ انقضا:', 'digital-orders-manager'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order['expires_at']))); ?></td>
                </tr>
                <?php if ($order['status'] === 'paid' && $order['download_count'] < $order['max_downloads'] && strtotime($order['expires_at']) > time()): ?>
                <tr>
                    <th><?php esc_html_e('دانلود:', 'digital-orders-manager'); ?></th>
                    <td>
                        <a href="<?php echo esc_url(wp_nonce_url(home_url('/?dom_download=1&order_key=' . $order_key), 'dom_download_' . $order_key)); ?>" class="button button-primary">
                            <?php esc_html_e('دانلود فایل', 'digital-orders-manager'); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get status label
     */
    private function get_status_label(string $status): string {
        $labels = [
            'pending' => __('در انتظار', 'digital-orders-manager'),
            'paid' => __('پرداخت شده', 'digital-orders-manager'),
            'expired' => __('منقضی شده', 'digital-orders-manager'),
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * Handle download request
     */
    public function handle_download(): void {
        if (!isset($_GET['dom_download']) || $_GET['dom_download'] !== '1') {
            return;
        }

        // Check nonce
        $order_key = sanitize_text_field(wp_unslash($_GET['order_key'] ?? ''));
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (!wp_verify_nonce($nonce, 'dom_download_' . $order_key)) {
            wp_die(__('خطای امنیتی.', 'digital-orders-manager'));
        }

        // Get order
        global $wpdb;
        $table_name = $this->schema->get_table_name();
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_key = %s",
            $order_key
        ), ARRAY_A);

        if (!$order) {
            wp_die(__('سفارش یافت نشد.', 'digital-orders-manager'));
        }

        // Validate download permissions
        if ($order['status'] !== 'paid') {
            wp_die(__('این سفارش هنوز پرداخت نشده است.', 'digital-orders-manager'));
        }

        if ($order['download_count'] >= $order['max_downloads']) {
            wp_die(__('تعداد دفعات دانلود مجاز به پایان رسیده است.', 'digital-orders-manager'));
        }

        if (strtotime($order['expires_at']) < time()) {
            wp_die__('لینک دانلود منقضی شده است.');
        }

        if (empty($order['file_url'])) {
            wp_die(__('فایلی برای دانلود وجود ندارد.', 'digital-orders-manager'));
        }

        // Increment download count
        $wpdb->update(
            $table_name,
            [
                'download_count' => intval($order['download_count']) + 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => intval($order['id'])]
        );

        // Serve file
        $file_url = esc_url_raw($order['file_url']);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_url) . '"');
        header('Content-Length: ' . filesize($file_url));
        
        readfile($file_url);
        exit;
    }
}

// Helper class for utility functions
class DOM_Helper {
    public static function get_client_ip(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])))[0];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
