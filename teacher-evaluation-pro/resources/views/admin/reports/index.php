<?php
/**
 * Admin View Index
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tep-admin-view">
    <div class="tep-view-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>
    
    <div class="tep-view-content" id="tep-react-app">
        <div class="tep-loading">
            <span class="spinner is-active"></span>
            <p><?php echo esc_html__('Loading...', 'teacher-evaluation-pro'); ?></p>
        </div>
    </div>
</div>
