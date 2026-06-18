<?php
/**
 * File Module
 */

declare(strict_types=1);

namespace TEP\Modules\File;

use TEP\Core\Container;

class FileModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register File functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize File module
    }
}
