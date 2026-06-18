<?php
/**
 * Agent Module
 */

declare(strict_types=1);

namespace TEP\Modules\Agent;

use TEP\Core\Container;

class AgentModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register Agent functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize Agent module
    }
}
