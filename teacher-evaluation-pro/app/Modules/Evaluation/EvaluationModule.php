<?php
/**
 * Evaluation Module
 */

declare(strict_types=1);

namespace TEP\Modules\Evaluation;

use TEP\Core\Container;

class EvaluationModule {
    
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register(): void {
        // Register Evaluation functionality
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize Evaluation module
    }
}
