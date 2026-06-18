<?php
/**
 * Factor Module
 */

declare(strict_types=1);

namespace TEP\Modules\Factor;

use TEP\Core\Container;

class FactorModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register Factor functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize Factor module
    }
}
