<?php

namespace DOM\Admin;

use WP_List_Table;
use DOM\Database\Schema;

/**
 * Custom List Table for Orders
 */
class OrderListTable extends WP_List_Table {

    private Schema $schema;

    public function __construct() {
        parent::__construct([
            'singular' => 'order',
            'plural' => 'orders',
            'ajax' => false,
        ]);
        $this->schema = new Schema();
    }

    /**
     * Get columns
     */
    public function get_columns(): array {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'digital-orders-manager'),
            'order_key' => __('کلید سفارش', 'digital-orders-manager'),
            'customer_email' => __('ایمیل', 'digital-orders-manager'),
            'product_name' => __('محصول', 'digital-orders-manager'),
            'amount' => __('مبلغ', 'digital-orders-manager'),
            'status' => __('وضعیت', 'digital-orders-manager'),
            'download_count' => __('دانلودها', 'digital-orders-manager'),
            'expires_at' => __('انقضا', 'digital-orders-manager'),
            'created_at' => __('تاریخ ثبت', 'digital-orders-manager'),
        ];
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array {
        return [
            'id' => ['id', true],
            'customer_email' => ['customer_email', false],
            'amount' => ['amount', false],
            'status' => ['status', false],
            'created_at' => ['created_at', true],
        ];
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions(): array {
        return [
            'delete' => __('حذف', 'digital-orders-manager'),
        ];
    }

    /**
     * Prepare items
     */
    public function prepare_items(): void {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Build query
        $table_name = $this->schema->get_table_name();
        $where = 'WHERE 1=1';
        $params = [];

        // Search
        if (!empty($_REQUEST['s'])) {
            $search = sanitize_text_field(wp_unslash($_REQUEST['s']));
            $where .= $wpdb->prepare(' AND (customer_email LIKE %s OR product_name LIKE %s)', "%$search%", "%$search%");
        }

        // Filter by status
        if (!empty($_REQUEST['status'])) {
            $status = sanitize_text_field(wp_unslash($_REQUEST['status']));
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }

        // Date filter
        if (!empty($_REQUEST['date_from'])) {
            $date_from = sanitize_text_field(wp_unslash($_REQUEST['date_from']));
            $where .= $wpdb->prepare(' AND created_at >= %s', $date_from);
        }

        if (!empty($_REQUEST['date_to'])) {
            $date_to = sanitize_text_field(wp_unslash($_REQUEST['date_to']));
            $where .= $wpdb->prepare(' AND created_at <= %s', $date_to . ' 23:59:59');
        }

        // Sorting
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'created_at';
        $order = !empty($_REQUEST['order']) && in_array(strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))), ['ASC', 'DESC']) 
            ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) 
            : 'DESC';

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");

        // Get items
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $items;
    }

    /**
     * Column default
     */
    public function column_default($item, $column_name): string {
        return esc_html($item[$column_name] ?? '');
    }

    /**
     * Column checkbox
     */
    public function column_cb($item): string {
        return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], $item['id']);
    }

    /**
     * Column status with actions
     */
    public function column_status($item): string {
        $status_labels = [
            'pending' => __('در انتظار', 'digital-orders-manager'),
            'paid' => __('پرداخت شده', 'digital-orders-manager'),
            'expired' => __('منقضی شده', 'digital-orders-manager'),
        ];

        $status = $item['status'];
        $label = $status_labels[$status] ?? $status;

        // Action links
        $actions = [];
        
        if ($status !== 'paid') {
            $nonce = wp_create_nonce('dom_update_status_' . $item['id']);
            $actions['mark_paid'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(admin_url('admin.php?page=dom-orders&action=mark_paid&order_id=' . $item['id'] . '&_wpnonce=' . $nonce), 'dom_update_status_' . $item['id']),
                __('پرداخت شده', 'digital-orders-manager')
            );
        }

        if ($status !== 'expired') {
            $nonce = wp_create_nonce('dom_update_status_' . $item['id']);
            $actions['mark_expired'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(admin_url('admin.php?page=dom-orders&action=mark_expired&order_id=' . $item['id'] . '&_wpnonce=' . $nonce), 'dom_update_status_' . $item['id']),
                __('منقضی شده', 'digital-orders-manager')
            );
        }

        return sprintf('%s %s', $label, $this->row_actions($actions));
    }

    /**
     * Handle bulk actions
     */
    public function process_bulk_action(): string {
        global $wpdb;

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['order']) ? array_map('intval', $_REQUEST['order']) : [];
            
            if (!empty($ids)) {
                $table_name = $this->schema->get_table_name();
                $ids_list = implode(',', $ids);
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_list)");
                
                return 'deleted';
            }
        }

        // Handle single action (update status)
        if (isset($_GET['action']) && in_array($_GET['action'], ['mark_paid', 'mark_expired'])) {
            $order_id = intval($_GET['order_id'] ?? 0);
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

            if (!wp_verify_nonce($nonce, 'dom_update_status_' . $order_id)) {
                return '';
            }

            $new_status = $_GET['action'] === 'mark_paid' ? 'paid' : 'expired';
            $table_name = $this->schema->get_table_name();
            
            $wpdb->update(
                $table_name,
                ['status' => $new_status, 'updated_at' => current_time('mysql')],
                ['id' => $order_id]
            );

            return 'updated';
        }

        return '';
    }

    /**
     * Extra controls for filters
     */
    protected function extra_tablenav($which): void {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php esc_html_e('همه وضعیت‌ها', 'digital-orders-manager'); ?></option>
                <option value="pending"><?php esc_html_e('در انتظار', 'digital-orders-manager'); ?></option>
                <option value="paid"><?php esc_html_e('پرداخت شده', 'digital-orders-manager'); ?></option>
                <option value="expired"><?php esc_html_e('منقضی شده', 'digital-orders-manager'); ?></option>
            </select>
            
            <input type="date" name="date_from" placeholder="<?php esc_attr_e('از تاریخ', 'digital-orders-manager'); ?>" />
            <input type="date" name="date_to" placeholder="<?php esc_attr_e('تا تاریخ', 'digital-orders-manager'); ?>" />
            
            <?php submit_button(__('فیلتر', 'digital-orders-manager'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
}
