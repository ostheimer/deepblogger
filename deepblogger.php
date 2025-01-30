<?php
/**
 * Plugin Name: DeepBlogger
 * Plugin URI: https://github.com/andreasostheimer/deepblogger
 * Description: A WordPress plugin for automatic blog post generation using ChatGPT
 * Version: 1.0.0
 * Author: Andreas Ostheimer
 * Author URI: https://github.com/andreasostheimer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deepblogger
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DEEPBLOGGER_VERSION', '1.0.0');
define('DEEPBLOGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEEPBLOGGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require the admin class
require_once DEEPBLOGGER_PLUGIN_DIR . 'admin/class-deepblogger-admin.php';
require_once DEEPBLOGGER_PLUGIN_DIR . 'includes/ai/class-openai-service.php';

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'DeepBlogger\\';
    $base_dir = DEEPBLOGGER_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
class DeepBlogger {
    private static $instance = null;
    private $admin = null;

    /**
     * Get singleton instance
     *
     * @return DeepBlogger
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize admin
        $this->admin = new DeepBlogger_Admin('deepblogger', DEEPBLOGGER_VERSION);
        
        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this->admin, 'add_plugin_admin_menu']);
            add_action('admin_init', [$this->admin, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_styles']);
            add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_scripts']);
        }

        // Plugin initialization
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Activation logic
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Deactivation logic
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('deepblogger', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Schedule the cron event if not already scheduled
        if (!wp_next_scheduled('deepblogger_generate_scheduled_posts')) {
            wp_schedule_event(time(), 'deepblogger_custom_interval', 'deepblogger_generate_scheduled_posts');
        }

        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
    }

    /**
     * Add custom cron interval based on settings
     */
    public function add_cron_interval($schedules) {
        $unit = get_option('deepblogger_post_schedule_unit', 'days');
        $value = (int)get_option('deepblogger_post_schedule_value', 1);

        // Convert to seconds
        $seconds = match($unit) {
            'minutes' => $value * 60,
            'hours' => $value * 3600,
            'days' => $value * 86400,
            default => 86400 // Default to daily
        };

        $schedules['deepblogger_custom_interval'] = [
            'interval' => $seconds,
            'display' => sprintf(
                /* translators: 1: number, 2: time unit */
                _n('Every %1$s %2$s', 'Every %1$s %2$s', $value, 'deepblogger'),
                $value,
                $unit
            )
        ];

        return $schedules;
    }
}

// Initialize plugin
DeepBlogger::get_instance(); 