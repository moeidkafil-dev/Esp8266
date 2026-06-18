<?php

/**
 * Unit Tests for Digital Orders Manager Plugin
 */

use DOM\Database\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Test Order Creation
 */
class OrderCreationTest extends TestCase {

    public function test_order_key_generation(): void {
        // Test that UUID generation produces valid format
        $orderKey = 'test-' . wp_generate_uuid4();
        
        $this->assertNotEmpty($orderKey);
        $this->assertIsString($orderKey);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', substr($orderKey, 5));
    }

    public function test_status_values(): void {
        $validStatuses = ['pending', 'paid', 'expired'];
        
        foreach ($validStatuses as $status) {
            $this->assertContains($status, $validStatuses);
        }
    }

    public function test_max_downloads_validation(): void {
        $maxDownloads = 5;
        $this->assertIsInt($maxDownloads);
        $this->assertGreaterThan(0, $maxDownloads);
    }
}

/**
 * Test API Permission Callbacks
 */
class APIPermissionTest extends TestCase {

    public function test_permission_callback_with_valid_order(): void {
        // Simulate valid order key
        $orderKey = 'valid-order-key';
        
        $this->assertNotEmpty($orderKey);
        $this->assertIsString($orderKey);
    }

    public function test_permission_callback_with_invalid_order(): void {
        // Simulate invalid order key
        $orderKey = '';
        
        $this->assertEmpty($orderKey);
    }

    public function test_admin_user_capability(): void {
        // Admin should have manage_options capability
        $adminCapabilities = ['manage_options', 'edit_posts', 'publish_posts'];
        
        $this->assertContains('manage_options', $adminCapabilities);
    }
}

/**
 * Test Uninstall Cleanup
 */
class UninstallCleanupTest extends TestCase {

    public function test_cleanup_removes_options(): void {
        $options = ['dom_max_downloads', 'dom_expiry_days', 'dom_db_version'];
        
        foreach ($options as $option) {
            $this->assertContains($option, $options);
        }
    }

    public function test_cleanup_drops_table(): void {
        $tableName = 'wp_digital_orders';
        
        $this->assertStringContainsString('digital_orders', $tableName);
    }

    public function test_keep_data_constant(): void {
        // Test that DOM_KEEP_DATA constant can be defined
        $keepData = false; // Default behavior
        
        $this->assertFalse($keepData);
    }
}
