<?php
/**
 * Workflow Module
 */

declare(strict_types=1);

namespace TEP\Modules\Workflow;

use TEP\Core\Container;

class WorkflowModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register Workflow functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize Workflow module
    }
}
