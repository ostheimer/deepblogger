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

        // Lade die Abhängigkeiten
        $this->load_dependencies();
        
        // Initialisiere die Internationalisierung
        $this->set_locale();
        
        // Registriere die Admin-Hooks
        $this->define_admin_hooks();
    }

    /**
     * Lädt die erforderlichen Abhängigkeiten für dieses Plugin
     */
    private function load_dependencies() {
        // Logger-Klasse muss als erstes geladen werden
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-deepblogger-logger.php';

        // Loader für die Plugin-Hooks
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-deepblogger-loader.php';

        // Internationalisierung
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-deepblogger-i18n.php';

        // Admin-Funktionalität
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-deepblogger-admin.php';

        // AI Services
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-openai-service.php';

        $this->loader = new DeepBlogger_Loader();
    }

    /**
     * Definiert die Locale für dieses Plugin für Internationalisierung.
     */
    private function set_locale() {
        $plugin_i18n = new DeepBlogger_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
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