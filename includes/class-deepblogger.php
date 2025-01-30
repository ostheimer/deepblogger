<?php

/**
 * Die Hauptklasse des DeepBlogger-Plugins
 */
class DeepBlogger {

    /**
     * Der eindeutige Bezeichner dieses Plugins
     */
    protected $plugin_name;

    /**
     * Die aktuelle Version des Plugins
     */
    protected $version;

    /**
     * Initialisiert die Klasse und setzt ihre Eigenschaften
     */
    public function __construct() {
        $this->plugin_name = 'deepblogger';
        $this->version = '1.0.0';

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Lädt die erforderlichen Abhängigkeiten für dieses Plugin
     */
    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-deepblogger-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Services/OpenAIService.php';
    }

    /**
     * Registriert alle Hooks im Zusammenhang mit der Admin-Funktionalität
     * des Plugins
     */
    private function define_admin_hooks() {
        $plugin_admin = new DeepBlogger_Admin($this->get_plugin_name(), $this->get_version());

        // Admin-Menü und Einstellungsseite
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        add_action('admin_init', array($plugin_admin, 'register_settings'));

        // Admin-Skripte und Styles
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));

        // AJAX-Handler
        add_action('wp_ajax_deepblogger_save_settings', array($plugin_admin, 'handle_save_settings'));
        add_action('wp_ajax_deepblogger_generate_posts', array($plugin_admin, 'handle_generate_posts'));
    }

    /**
     * Gibt den Namen des Plugins zurück
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Gibt die Version des Plugins zurück
     */
    public function get_version() {
        return $this->version;
    }
} 