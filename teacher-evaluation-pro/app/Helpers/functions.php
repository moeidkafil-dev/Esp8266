<?php
/**
 * Helper Functions - Global utility functions
 */

declare(strict_types=1);

use TEP\Core\Container;
use TEP\Core\Config;
use TEP\Core\Database;
use TEP\Core\EventManager;

/**
 * Get the service container instance
 *
 * @return Container
 */
function tep_container(): Container {
    static $container = null;
    
    if ($container === null) {
        $container = new Container();
    }
    
    return $container;
}

/**
 * Resolve a service from the container
 *
 * @template T
 * @param class-string<T> $abstract
 * @return T
 */
function tep_resolve(string $abstract): mixed {
    return tep_container()->make($abstract);
}

/**
 * Get a configuration value
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function tep_config(string $key, mixed $default = null): mixed {
    return Config::get($key, $default);
}

/**
 * Set a configuration value
 *
 * @param string $key
 * @param mixed $value
 * @return void
 */
function tep_config_set(string $key, mixed $value): void {
    Config::set($key, $value);
}

/**
 * Get the event manager instance
 *
 * @return EventManager
 */
function tep_event(): EventManager {
    static $eventManager = null;
    
    if ($eventManager === null) {
        $eventManager = new EventManager();
    }
    
    return $eventManager;
}

/**
 * Dispatch an event
 *
 * @param string $event
 * @param array<mixed> $payload
 * @param bool $halt
 * @return array<mixed>|mixed|null
 */
function tep_dispatch(string $event, array $payload = [], bool $halt = false): array|mixed|null {
    return tep_event()->dispatch($event, $payload, $halt);
}

/**
 * Log a message
 *
 * @param string $message
 * @param string $level
 * @param array<mixed> $context
 * @return void
 */
function tep_log(string $message, string $level = 'info', array $context = []): void {
    error_log("[TEP][{$level}] {$message}");
}

/**
 * Get database instance
 *
 * @return \wpdb
 */
function tep_db(): \wpdb {
    return Database::getInstance();
}

/**
 * Get table name with prefix
 *
 * @param string $table
 * @return string
 */
function tep_table(string $table): string {
    return Database::table($table);
}

/**
 * Sanitize input data
 *
 * @param mixed $data
 * @return mixed
 */
function tep_sanitize(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('tep_sanitize', $data);
    }
    
    return wp_kses_post($data);
}

/**
 * Format a date
 *
 * @param string|\DateTime $date
 * @param string $format
 * @return string
 */
function tep_format_date(string|\DateTime $date, string $format = 'Y-m-d H:i:s'): string {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    
    return $date->format($format);
}

/**
 * Generate a unique ID
 *
 * @return string
 */
function tep_uuid(): string {
    return wp_generate_uuid4();
}

/**
 * Generate a random token
 *
 * @param int $length
 * @return string
 */
function tep_token(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash a password
 *
 * @param string $password
 * @return string
 */
function tep_hash_password(string $password): string {
    return wp_hash_password($password);
}

/**
 * Verify a password hash
 *
 * @param string $password
 * @param string $hash
 * @return bool
 */
function tep_verify_password(string $password, string $hash): bool {
    return wp_check_password($password, $hash);
}

/**
 * Get current user ID
 *
 * @return int
 */
function tep_current_user_id(): int {
    return get_current_user_id();
}

/**
 * Check if user has capability
 *
 * @param string $capability
 * @param int|null $userId
 * @return bool
 */
function tep_user_can(string $capability, ?int $userId = null): bool {
    if ($userId === null) {
        $userId = tep_current_user_id();
    }
    
    return user_can($userId, $capability);
}

/**
 * Redirect to a URL
 *
 * @param string $url
 * @param int $status
 * @return void
 */
function tep_redirect(string $url, int $status = 302): void {
    wp_redirect($url, $status);
    exit;
}

/**
 * Send JSON response
 *
 * @param mixed $data
 * @param int $status
 * @param array<string, string> $headers
 * @return void
 */
function tep_json_response(mixed $data, int $status = 200, array $headers = []): void {
    status_header($status);
    
    foreach ($headers as $name => $value) {
        header("$name: $value");
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 *
 * @param string $message
 * @param int $status
 * @param mixed $errors
 * @return void
 */
function tep_error_response(string $message, int $status = 400, mixed $errors = null): void {
    tep_json_response([
        'success' => false,
        'error' => $message,
        'errors' => $errors,
    ], $status);
}

/**
 * Send success response
 *
 * @param mixed $data
 * @param string $message
 * @return void
 */
function tep_success_response(mixed $data = null, string $message = 'Success'): void {
    tep_json_response([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ]);
}

/**
 * Get asset URL
 *
 * @param string $path
 * @return string
 */
function tep_asset(string $path): string {
    return TEP_PLUGIN_URL . 'public/' . ltrim($path, '/');
}

/**
 * Get view path
 *
 * @param string $view
 * @return string
 */
function tep_view_path(string $view): string {
    return TEP_PLUGIN_DIR . 'resources/views/' . ltrim($view, '/') . '.php';
}

/**
 * Render a view
 *
 * @param string $view
 * @param array<string, mixed> $data
 * @return void
 */
function tep_render_view(string $view, array $data = []): void {
    extract($data);
    include tep_view_path($view);
}

/**
 * Get translated string
 *
 * @param string $text
 * @param string $domain
 * @return string
 */
function tep__(string $text, string $domain = 'teacher-evaluation-pro'): string {
    return __($text, $domain);
}

/**
 * Echo translated string
 *
 * @param string $text
 * @param string $domain
 * @return void
 */
function tep_e(string $text, string $domain = 'teacher-evaluation-pro'): void {
    _e($text, $domain);
}

/**
 * Check if request is AJAX
 *
 * @return bool
 */
function tep_is_ajax(): bool {
    return wp_doing_ajax() || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

/**
 * Get request input
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function tep_request(string $key, mixed $default = null): mixed {
    return $_REQUEST[$key] ?? $default;
}

/**
 * Get POST input
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function tep_post(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $default;
}

/**
 * Get GET input
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function tep_get(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

/**
 * Verify nonce
 *
 * @param string $nonce
 * @param string $action
 * @return bool
 */
function tep_verify_nonce(string $nonce, string $action): bool {
    return wp_verify_nonce($nonce, $action) !== false;
}

/**
 * Create nonce
 *
 * @param string $action
 * @return string
 */
function tep_create_nonce(string $action): string {
    return wp_create_nonce($action);
}

/**
 * Schedule a cron event
 *
 * @param string $hook
 * @param int $timestamp
 * @param array<mixed> $args
 * @return bool
 */
function tep_schedule_event(string $hook, int $timestamp, array $args = []): bool {
    return wp_schedule_single_event($timestamp, $hook, $args);
}

/**
 * Clear scheduled cron event
 *
 * @param string $hook
 * @param array<mixed> $args
 * @return bool
 */
function tep_clear_scheduled_hook(string $hook, array $args = []): bool {
    return wp_clear_scheduled_hook($hook, $args);
}

/**
 * Get cache
 *
 * @param string $key
 * @param string $group
 * @return mixed
 */
function tep_cache_get(string $key, string $group = 'tep'): mixed {
    return wp_cache_get($key, $group);
}

/**
 * Set cache
 *
 * @param string $key
 * @param mixed $data
 * @param string $group
 * @param int $expire
 * @return bool
 */
function tep_cache_set(string $key, mixed $data, string $group = 'tep', int $expire = 3600): bool {
    return wp_cache_set($key, $data, $group, $expire);
}

/**
 * Delete cache
 *
 * @param string $key
 * @param string $group
 * @return bool
 */
function tep_cache_delete(string $key, string $group = 'tep'): bool {
    return wp_cache_delete($key, $group);
}
