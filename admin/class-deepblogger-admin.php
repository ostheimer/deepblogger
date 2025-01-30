<?php

/**
 * Die Admin-spezifische Funktionalität des Plugins
 */
class DeepBlogger_Admin {

    /**
     * Der eindeutige Bezeichner dieses Plugins
     */
    private $plugin_name;

    /**
     * Die aktuelle Version des Plugins
     */
    private $version;

    /**
     * Initialisiert die Klasse und setzt ihre Eigenschaften
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Registriert die Stylesheets für den Admin-Bereich
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_deepblogger') {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/deepblogger-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Registriert die JavaScript-Dateien für den Admin-Bereich
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_deepblogger') {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/deepblogger-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'deepbloggerAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepblogger_settings_nonce')
            )
        );
    }

    /**
     * Fügt den Menüpunkt im Admin-Bereich hinzu
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'DeepBlogger Settings',
            'DeepBlogger',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_admin_page'),
            'dashicons-admin-generic',
            20
        );
    }

    /**
     * Rendert die Admin-Seite
     */
    public function display_plugin_admin_page() {
        include_once 'admin-page.php';
    }

    /**
     * Registriert die Plugin-Einstellungen
     */
    public function register_settings() {
        register_setting(
            'deepblogger_options',
            'deepblogger_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'deepblogger_options',
            'deepblogger_openai_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4'
            )
        );

        register_setting(
            'deepblogger_options',
            'deepblogger_posts_per_category',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1
            )
        );

        add_settings_section(
            'deepblogger_settings_section',
            __('OpenAI Einstellungen', 'deepblogger'),
            array($this, 'settings_section_callback'),
            'deepblogger_options'
        );

        add_settings_field(
            'deepblogger_openai_api_key',
            __('API Key', 'deepblogger'),
            array($this, 'api_key_field_callback'),
            'deepblogger_options',
            'deepblogger_settings_section'
        );

        add_settings_field(
            'deepblogger_openai_model',
            __('Modell', 'deepblogger'),
            array($this, 'model_field_callback'),
            'deepblogger_options',
            'deepblogger_settings_section'
        );

        add_settings_field(
            'deepblogger_posts_per_category',
            __('Beiträge pro Kategorie', 'deepblogger'),
            array($this, 'posts_per_category_field_callback'),
            'deepblogger_options',
            'deepblogger_settings_section'
        );
    }

    /**
     * Callback für die Einstellungssektion
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Konfigurieren Sie hier Ihre OpenAI-Einstellungen.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für das API-Key-Feld
     */
    public function api_key_field_callback() {
        $api_key = get_option('deepblogger_openai_api_key');
        echo '<input type="text" id="deepblogger_openai_api_key" name="deepblogger_openai_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    }

    /**
     * Callback für das Modell-Feld
     */
    public function model_field_callback() {
        $model = get_option('deepblogger_openai_model', 'gpt-4');
        echo '<select id="deepblogger_openai_model" name="deepblogger_openai_model">';
        echo '<option value="gpt-4"' . selected($model, 'gpt-4', false) . '>GPT-4</option>';
        echo '<option value="gpt-3.5-turbo"' . selected($model, 'gpt-3.5-turbo', false) . '>GPT-3.5 Turbo</option>';
        echo '</select>';
    }

    /**
     * Callback für das Posts-per-Category-Feld
     */
    public function posts_per_category_field_callback() {
        $posts_per_category = get_option('deepblogger_posts_per_category', 1);
        echo '<input type="number" id="deepblogger_posts_per_category" name="deepblogger_posts_per_category" value="' . esc_attr($posts_per_category) . '" min="1" max="10">';
    }

    /**
     * AJAX-Handler für das Speichern der Einstellungen
     */
    public function handle_save_settings() {
        check_ajax_referer('deepblogger_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        $model = sanitize_text_field($_POST['model']);
        $posts_per_category = absint($_POST['posts_per_category']);

        update_option('deepblogger_openai_api_key', $api_key);
        update_option('deepblogger_openai_model', $model);
        update_option('deepblogger_posts_per_category', $posts_per_category);

        wp_send_json_success(array(
            'message' => __('Einstellungen erfolgreich gespeichert', 'deepblogger')
        ));
    }

    /**
     * AJAX-Handler für das Generieren von Beiträgen
     */
    public function handle_generate_posts() {
        check_ajax_referer('deepblogger_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        try {
            $openai_service = new OpenAIService();
            $result = $openai_service->generate_posts();
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
} 