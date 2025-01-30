<?php
namespace DeepBlogger;

/**
 * Class handling plugin activation and deactivation
 */
class Activation {
    /**
     * Plugin activation hook
     */
    public static function activate() {
        // Set default settings
        if (!get_option('deepblogger_post_schedule')) {
            update_option('deepblogger_post_schedule', 'daily');
        }

        if (!get_option('deepblogger_topics')) {
            update_option('deepblogger_topics', [
                'WordPress Development',
                'SEO Optimization',
                'Content Marketing',
                'Social Media Marketing',
                'Online Business'
            ]);
        }

        // Set user capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_deepblogger');
        }

        // Perform database updates
        self::update_database();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Disable scheduler
        wp_clear_scheduled_hook('deepblogger_generate_post');

        // Remove user capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_deepblogger');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create or update plugin database tables
     */
    private static function update_database() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'deepblogger_log';

        // SQL for log table
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            topic varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
} 