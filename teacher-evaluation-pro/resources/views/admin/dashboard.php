<?php
/**
 * Admin Dashboard View
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tep-dashboard">
    <div class="tep-dashboard-header">
        <h1><?php echo esc_html__('Teacher Evaluation Pro Dashboard', 'teacher-evaluation-pro'); ?></h1>
        <p class="tep-dashboard-subtitle"><?php echo esc_html__('AI-Powered Educational Assessment System', 'teacher-evaluation-pro'); ?></p>
    </div>
    
    <div class="tep-dashboard-grid">
        <!-- Statistics Cards -->
        <div class="tep-stat-card">
            <div class="tep-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="tep-stat-content">
                <h3><?php echo number_format_i18n(get_users(['count' => true])); ?></h3>
                <p><?php echo esc_html__('Total Users', 'teacher-evaluation-pro'); ?></p>
            </div>
        </div>
        
        <div class="tep-stat-card">
            <div class="tep-stat-icon">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="tep-stat-content">
                <h3><?php echo number_format_i18n(tep_db()->get_var("SELECT COUNT(*) FROM " . tep_table('evaluations'))); ?></h3>
                <p><?php echo esc_html__('Evaluations', 'teacher-evaluation-pro'); ?></p>
            </div>
        </div>
        
        <div class="tep-stat-card">
            <div class="tep-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="tep-stat-content">
                <h3><?php echo number_format_i18n(tep_db()->get_var("SELECT COUNT(*) FROM " . tep_table('factors') . " WHERE is_active = 1")); ?></h3>
                <p><?php echo esc_html__('Active Factors', 'teacher-evaluation-pro'); ?></p>
            </div>
        </div>
        
        <div class="tep-stat-card">
            <div class="tep-stat-icon">
                <span class="dashicons dashicons-admin-network"></span>
            </div>
            <div class="tep-stat-content">
                <h3><?php echo number_format_i18n(tep_db()->get_var("SELECT COUNT(*) FROM " . tep_table('agents') . " WHERE status = 'active'")); ?></h3>
                <p><?php echo esc_html__('AI Agents', 'teacher-evaluation-pro'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="tep-quick-actions">
        <h2><?php echo esc_html__('Quick Actions', 'teacher-evaluation-pro'); ?></h2>
        <div class="tep-action-buttons">
            <a href="<?php echo admin_url('admin.php?page=tep-evaluations&action=new'); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php echo esc_html__('New Evaluation', 'teacher-evaluation-pro'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=tep-reports'); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php echo esc_html__('View Reports', 'teacher-evaluation-pro'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=tep-agents'); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-admin-users"></span>
                <?php echo esc_html__('Manage AI Agents', 'teacher-evaluation-pro'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=tep-settings'); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html__('Settings', 'teacher-evaluation-pro'); ?>
            </a>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="tep-recent-activity">
        <h2><?php echo esc_html__('Recent Activity', 'teacher-evaluation-pro'); ?></h2>
        <div class="tep-activity-list">
            <p class="tep-no-activity"><?php echo esc_html__('No recent activity to display.', 'teacher-evaluation-pro'); ?></p>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="tep-system-status">
        <h2><?php echo esc_html__('System Status', 'teacher-evaluation-pro'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Component', 'teacher-evaluation-pro'); ?></th>
                    <th><?php echo esc_html__('Status', 'teacher-evaluation-pro'); ?></th>
                    <th><?php echo esc_html__('Version', 'teacher-evaluation-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo esc_html__('Plugin Version', 'teacher-evaluation-pro'); ?></td>
                    <td><span class="tep-status-indicator tep-status-active"></span> <?php echo esc_html__('Active', 'teacher-evaluation-pro'); ?></td>
                    <td><?php echo esc_html(TEP_VERSION); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('PHP Version', 'teacher-evaluation-pro'); ?></td>
                    <td><span class="tep-status-indicator tep-status-<?php echo version_compare(PHP_VERSION, '8.2', '>=') ? 'active' : 'inactive'; ?>"></span> <?php echo esc_html(PHP_VERSION); ?></td>
                    <td><?php echo version_compare(PHP_VERSION, '8.2', '>=') ? esc_html__('OK', 'teacher-evaluation-pro') : esc_html__('Update Required', 'teacher-evaluation-pro'); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('WordPress Version', 'teacher-evaluation-pro'); ?></td>
                    <td><span class="tep-status-indicator tep-status-active"></span> <?php echo esc_html(get_bloginfo('version')); ?></td>
                    <td><?php echo esc_html__('OK', 'teacher-evaluation-pro'); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Database', 'teacher-evaluation-pro'); ?></td>
                    <td><span class="tep-status-indicator tep-status-active"></span> <?php echo esc_html__('Connected', 'teacher-evaluation-pro'); ?></td>
                    <td>MySQL <?php echo esc_html(tep_db()->db_version()); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
