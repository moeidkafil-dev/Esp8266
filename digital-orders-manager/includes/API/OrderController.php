<?php

namespace DOM\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DOM\Database\Schema;

/**
 * REST API Controller for Orders
 */
class OrderController extends WP_REST_Controller {

    private Schema $schema;

    public function __construct() {
        $this->namespace = 'dom/v1';
        $this->rest_base = 'order';
        $this->schema = new Schema();
    }

    /**
     * Register REST routes
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<order_key>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => [
                    'order_key' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check permissions for getting an order
     */
    public function get_item_permissions_check(WP_REST_Request $request): bool|WP_Error {
        $order_key = sanitize_text_field($request->get_param('order_key'));
        
        if (empty($order_key)) {
            return new WP_Error('rest_invalid_param', __('کلید سفارش معتبر نیست.', 'digital-orders-manager'), ['status' => 400]);
        }

        // Get order from database
        global $wpdb;
        $table_name = $this->schema->get_table_name();
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_key = %s",
            $order_key
        ), ARRAY_A);

        if (!$order) {
            return new WP_Error('rest_not_found', __('سفارش یافت نشد.', 'digital-orders-manager'), ['status' => 404]);
        }

        // Check if current user is admin
        if (current_user_can('manage_options')) {
            return true;
        }

        // Check if current user owns this order
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && intval($order['user_id']) === $current_user_id) {
            return true;
        }

        // Check if email matches logged-in user email
        $current_user = wp_get_current_user();
        if ($current_user->exists() && $current_user->user_email === $order['customer_email']) {
            return true;
        }

        return new WP_Error('rest_forbidden', __('شما مجوز دسترسی به این سفارش را ندارید.', 'digital-orders-manager'), ['status' => 403]);
    }

    /**
     * Get single order
     */
    public function get_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $order_key = sanitize_text_field($request->get_param('order_key'));

        global $wpdb;
        $table_name = $this->schema->get_table_name();
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_key = %s",
            $order_key
        ), ARRAY_A);

        if (!$order) {
            return new WP_Error('rest_not_found', __('سفارش یافت نشد.', 'digital-orders-manager'), ['status' => 404]);
        }

        // Prepare response data
        $data = [
            'id' => intval($order['id']),
            'order_key' => $order['order_key'],
            'product_name' => $order['product_name'],
            'status' => $order['status'],
            'amount' => floatval($order['amount']),
            'download_count' => intval($order['download_count']),
            'max_downloads' => intval($order['max_downloads']),
            'expires_at' => $order['expires_at'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'],
        ];

        return rest_ensure_response($data);
    }
}
