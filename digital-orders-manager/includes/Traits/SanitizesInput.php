<?php

namespace DOM\Traits;

/**
 * Sanitization Trait
 * Common sanitization methods for input data
 */
trait SanitizesInput {

    /**
     * Sanitize text field
     */
    protected function sanitize_text(string $value): string {
        return sanitize_text_field(wp_unslash($value));
    }

    /**
     * Sanitize email
     */
    protected function sanitize_email(string $value): string {
        return sanitize_email(wp_unslash($value));
    }

    /**
     * Sanitize integer
     */
    protected function sanitize_int($value): int {
        return intval($value);
    }

    /**
     * Sanitize float
     */
    protected function sanitize_float($value): float {
        return floatval($value);
    }

    /**
     * Sanitize URL
     */
    protected function sanitize_url(string $value): string {
        return esc_url_raw(wp_unslash($value));
    }

    /**
     * Sanitize key (alphanumeric and underscores)
     */
    protected function sanitize_key(string $value): string {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value);
    }
}
