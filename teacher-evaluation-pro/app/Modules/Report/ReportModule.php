<?php
/**
 * Report Module
 */

declare(strict_types=1);

namespace TEP\Modules\Report;

use TEP\Core\Container;

class ReportModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register Report functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize Report module
    }
}
