<?php

namespace DOM\Traits;

/**
 * Singleton Trait
 * For classes that should have only one instance
 */
trait Singleton {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
