<?php
/**
 * Notification Module
 */

declare(strict_types=1);

namespace TEP\Modules\Notification;

use TEP\Core\Container;

class NotificationModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register Notification functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize Notification module
    }
}
