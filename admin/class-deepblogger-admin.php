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

        // Registriere AJAX-Handler
        add_action('wp_ajax_deepblogger_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_deepblogger_generate_posts', array($this, 'handle_generate_posts'));
        add_action('wp_ajax_deepblogger_analyze_category', array($this, 'handle_analyze_category'));
        add_action('wp_ajax_deepblogger_generate_post', array($this, 'handle_generate_post'));
        add_action('wp_ajax_deepblogger_get_models', array($this, 'handle_get_models'));
    }

    /**
     * Registriert die Stylesheets für den Admin-Bereich
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        if (!in_array($screen->id, array('toplevel_page_deepblogger'))) {
            return;
        }

        wp_enqueue_style(
            'deepblogger-admin',
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
        if (!in_array($screen->id, array('toplevel_page_deepblogger'))) {
            return;
        }

        wp_enqueue_script(
            'deepblogger-admin',
            plugin_dir_url(__FILE__) . 'js/deepblogger-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            'deepblogger-admin',
            'deepbloggerAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepblogger_settings_nonce'),
                'strings' => array(
                    'selectModel' => __('Bitte wählen...', 'deepblogger'),
                    'modelsLoaded' => __('Modelle erfolgreich geladen', 'deepblogger'),
                    'modelLoadError' => __('Fehler beim Laden der Modelle', 'deepblogger'),
                    'saveError' => __('Fehler beim Speichern der Einstellungen', 'deepblogger'),
                    'loading' => __('Lade...', 'deepblogger'),
                    'saving' => __('Speichere...', 'deepblogger'),
                    'saved' => __('Einstellungen gespeichert', 'deepblogger'),
                    'error' => __('Fehler', 'deepblogger'),
                    'loadingModels' => __('Lade Modelle...', 'deepblogger')
                ),
                'debug' => WP_DEBUG
            )
        );
    }

    /**
     * Fügt den Menüpunkt im Admin-Bereich hinzu
     */
    public function add_plugin_admin_menu() {
        // Main menu item
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
        // Allgemeine Einstellungen Sektion
        add_settings_section(
            'deepblogger_general_section',
            __('Allgemeine Einstellungen', 'deepblogger'),
            array($this, 'general_section_callback'),
            'deepblogger_options'
        );

        // Allgemeine Felder
        add_settings_field(
            'deepblogger_posts_per_category',
            __('Beiträge pro Kategorie', 'deepblogger'),
            array($this, 'posts_per_category_field_callback'),
            'deepblogger_options',
            'deepblogger_general_section'
        );

        add_settings_field(
            'deepblogger_post_categories',
            __('Kategorien', 'deepblogger'),
            array($this, 'categories_field_callback'),
            'deepblogger_options',
            'deepblogger_general_section'
        );

        // KI-Provider Sektion
        add_settings_section(
            'deepblogger_ai_provider_section',
            __('KI-Einstellungen', 'deepblogger'),
            array($this, 'ai_provider_section_callback'),
            'deepblogger_options'
        );

        // KI-Provider Feld
        add_settings_field(
            'deepblogger_ai_provider',
            __('KI-Anbieter', 'deepblogger'),
            array($this, 'ai_provider_field_callback'),
            'deepblogger_options',
            'deepblogger_ai_provider_section'
        );

        // OpenAI Sektion
        add_settings_section(
            'deepblogger_openai_section',
            __('OpenAI Einstellungen', 'deepblogger'),
            array($this, 'openai_section_callback'),
            'deepblogger_options'
        );

        // OpenAI Felder
        add_settings_field(
            'deepblogger_openai_api_key',
            __('OpenAI API Key', 'deepblogger'),
            array($this, 'openai_api_key_field_callback'),
            'deepblogger_options',
            'deepblogger_openai_section'
        );

        add_settings_field(
            'deepblogger_openai_model',
            __('OpenAI Modell', 'deepblogger'),
            array($this, 'openai_model_field_callback'),
            'deepblogger_options',
            'deepblogger_openai_section'
        );

        // Deepseek Sektion
        add_settings_section(
            'deepblogger_deepseek_section',
            __('Deepseek Einstellungen', 'deepblogger'),
            array($this, 'deepseek_section_callback'),
            'deepblogger_options'
        );

        // Deepseek Felder
        add_settings_field(
            'deepblogger_deepseek_api_key',
            __('Deepseek API Key', 'deepblogger'),
            array($this, 'deepseek_api_key_field_callback'),
            'deepblogger_options',
            'deepblogger_deepseek_section'
        );

        add_settings_field(
            'deepblogger_deepseek_model',
            __('Deepseek Modell', 'deepblogger'),
            array($this, 'deepseek_model_field_callback'),
            'deepblogger_options',
            'deepblogger_deepseek_section'
        );

        // Registriere die Einstellungen
        register_setting(
            'deepblogger_options',
            'deepblogger_ai_provider',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'openai'
            )
        );

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
                'type' => 'select',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'deepblogger_options',
            'deepblogger_deepseek_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'deepblogger_options',
            'deepblogger_deepseek_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'deepseek-chat'
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

        register_setting(
            'deepblogger_options',
            'deepblogger_post_categories',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_categories'),
                'default' => array()
            )
        );
    }

    /**
     * Callback für die allgemeine Sektion
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Konfigurieren Sie hier die grundlegenden Einstellungen für die Beitragsgenerierung.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für die KI-Provider Sektion
     */
    public function ai_provider_section_callback() {
        echo '<p>' . esc_html__('Wählen Sie Ihren bevorzugten KI-Anbieter und konfigurieren Sie die allgemeinen Einstellungen.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für das KI-Provider Auswahlfeld
     */
    public function ai_provider_field_callback() {
        $provider = get_option('deepblogger_ai_provider', 'openai');
        $providers = array(
            'openai' => 'OpenAI',
            'deepseek' => 'Deepseek'
        );

        echo '<select id="deepblogger_ai_provider" name="deepblogger_ai_provider" class="ai-provider-select">';
        foreach ($providers as $provider_id => $provider_name) {
            echo '<option value="' . esc_attr($provider_id) . '"' . selected($provider, $provider_id, false) . '>' . esc_html($provider_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Wählen Sie den KI-Anbieter für die Beitragsgenerierung.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für das OpenAI API Key Feld
     */
    public function openai_api_key_field_callback() {
        $api_key = get_option('deepblogger_openai_api_key', '');
        echo '<input type="password" id="deepblogger_openai_api_key" name="deepblogger_openai_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Geben Sie hier Ihren OpenAI API-Schlüssel ein.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für das Deepseek API Key Feld
     */
    public function deepseek_api_key_field_callback() {
        $api_key = get_option('deepblogger_deepseek_api_key', '');
        echo '<input type="password" id="deepblogger_deepseek_api_key" name="deepblogger_deepseek_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Geben Sie hier Ihren Deepseek API-Schlüssel ein.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für die OpenAI Einstellungssektion
     */
    public function openai_section_callback() {
        echo '<p>' . esc_html__('Konfigurieren Sie hier Ihre OpenAI-Einstellungen für die Beitragsgenerierung.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für die Deepseek Einstellungssektion
     */
    public function deepseek_section_callback() {
        echo '<p>' . esc_html__('Konfigurieren Sie hier Ihre Deepseek-Einstellungen für die Themenanalyse.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für das Deepseek-Modell-Feld
     */
    public function deepseek_model_field_callback() {
        $model = get_option('deepblogger_deepseek_model', 'deepseek-chat');
        $models = array(
            'deepseek-chat' => 'Deepseek Chat',
            'deepseek-coder' => 'Deepseek Coder',
            'deepseek-research' => 'Deepseek Research'
        );
        
        echo '<select id="deepblogger_deepseek_model" name="deepblogger_deepseek_model">';
        foreach ($models as $model_id => $model_name) {
            echo '<option value="' . esc_attr($model_id) . '"' . selected($model, $model_id, false) . '>' . esc_html($model_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Wählen Sie das zu verwendende Deepseek-Modell für die Themenanalyse.', 'deepblogger') . '</p>';
    }

    /**
     * Callback für das OpenAI Modell-Feld
     */
    public function openai_model_field_callback() {
        $model = get_option('deepblogger_openai_model', '');
        
        echo '<div class="model-field-wrapper">';
        echo '<div class="model-select-container">';
        
        // Select-Feld mit gespeichertem Wert
        echo '<select id="deepblogger_openai_model" name="deepblogger_openai_model" class="model-select" data-saved-model="' . esc_attr($model) . '">';
        echo '<option value="">' . esc_html__('Lade Modelle...', 'deepblogger') . '</option>';
        echo '</select>';
        
        // WordPress Spinner (ohne is-active Klasse)
        echo '<span class="spinner"></span>';
        
        // Refresh Button
        echo '<button type="button" class="button refresh-models" data-provider="openai">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Modelle aktualisieren', 'deepblogger');
        echo '</button>';
        
        echo '</div>'; // Ende .model-select-container
        
        // Beschreibung und Status
        echo '<p class="description">' . esc_html__('Wählen Sie das zu verwendende OpenAI-Modell.', 'deepblogger') . '</p>';
        echo '<div class="model-status"></div>';
        
        echo '</div>'; // Ende .model-field-wrapper
    }

    /**
     * Callback für das Posts-per-Category-Feld
     */
    public function posts_per_category_field_callback() {
        $posts_per_category = get_option('deepblogger_posts_per_category', 1);
        echo '<input type="number" id="deepblogger_posts_per_category" name="deepblogger_posts_per_category" value="' . esc_attr($posts_per_category) . '" min="1" max="10">';
    }

    /**
     * Sanitize categories array
     */
    public function sanitize_categories($categories) {
        if (!is_array($categories)) {
            return array();
        }
        return array_map('absint', $categories);
    }

    /**
     * Callback für das Kategorien-Feld
     */
    public function categories_field_callback() {
        $selected_categories = get_option('deepblogger_post_categories', array());
        $categories = get_categories(array('hide_empty' => false));
        
        if (empty($categories)) {
            echo '<p>' . esc_html__('Keine Kategorien gefunden. Bitte erstellen Sie zuerst einige Kategorien.', 'deepblogger') . '</p>';
            return;
        }

        echo '<div class="categories-wrapper">';
        foreach ($categories as $category) {
            printf(
                '<label><input type="checkbox" name="deepblogger_post_categories[]" value="%1$s" %2$s> %3$s</label><br>',
                esc_attr($category->term_id),
                checked(in_array($category->term_id, $selected_categories), true, false),
                esc_html($category->name)
            );
        }
        echo '</div>';
        echo '<p class="description">' . esc_html__('Wählen Sie die Kategorien aus, für die Beiträge generiert werden sollen.', 'deepblogger') . '</p>';
    }

    /**
     * AJAX-Handler für das Abrufen der verfügbaren Modelle
     */
    public function handle_get_models() {
        check_ajax_referer('deepblogger_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $force_refresh = isset($_POST['force_refresh']) ? (bool)$_POST['force_refresh'] : false;

        if (empty($provider)) {
            wp_send_json_error('Kein Provider angegeben');
            return;
        }

        try {
            $models = array();
            
            switch ($provider) {
                case 'openai':
                    $api_key = get_option('deepblogger_openai_api_key', '');
                    $openai_service = new OpenAIService($api_key);
                    $models = $openai_service->get_available_models($force_refresh);
                    break;
                    
                case 'deepseek':
                    $models = array(
                        array(
                            'id' => 'deepseek-chat',
                            'name' => 'Deepseek Chat'
                        ),
                        array(
                            'id' => 'deepseek-coder',
                            'name' => 'Deepseek Coder'
                        ),
                        array(
                            'id' => 'deepseek-research',
                            'name' => 'Deepseek Research'
                        )
                    );
                    break;
                    
                default:
                    wp_send_json_error('Ungültiger Provider');
                    return;
            }
            
            wp_send_json_success(array('models' => $models));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
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

        // KI-Provider
        if (isset($_POST['deepblogger_ai_provider'])) {
            update_option('deepblogger_ai_provider', sanitize_text_field($_POST['deepblogger_ai_provider']));
        }

        // OpenAI Einstellungen
        if (isset($_POST['deepblogger_openai_api_key'])) {
            update_option('deepblogger_openai_api_key', sanitize_text_field($_POST['deepblogger_openai_api_key']));
        }

        if (isset($_POST['deepblogger_openai_model'])) {
            update_option('deepblogger_openai_model', sanitize_text_field($_POST['deepblogger_openai_model']));
        }

        // Deepseek Einstellungen
        if (isset($_POST['deepblogger_deepseek_api_key'])) {
            update_option('deepblogger_deepseek_api_key', sanitize_text_field($_POST['deepblogger_deepseek_api_key']));
        }

        if (isset($_POST['deepblogger_deepseek_model'])) {
            update_option('deepblogger_deepseek_model', sanitize_text_field($_POST['deepblogger_deepseek_model']));
        }

        // Beiträge pro Kategorie
        if (isset($_POST['deepblogger_posts_per_category'])) {
            $posts_per_category = absint($_POST['deepblogger_posts_per_category']);
            if ($posts_per_category < 1) $posts_per_category = 1;
            if ($posts_per_category > 10) $posts_per_category = 10;
            update_option('deepblogger_posts_per_category', $posts_per_category);
        }

        // Kategorien
        if (isset($_POST['deepblogger_post_categories'])) {
            $categories = json_decode(stripslashes($_POST['deepblogger_post_categories']), true);
            if (is_array($categories)) {
                $categories = array_map('absint', $categories);
                update_option('deepblogger_post_categories', $categories);
            }
        }

        // Hole den aktuellen Provider
        $current_provider = get_option('deepblogger_ai_provider', 'openai');
        
        wp_send_json_success(array(
            'message' => __('Einstellungen erfolgreich gespeichert.', 'deepblogger'),
            'api_key' => get_option('deepblogger_' . $current_provider . '_api_key'),
            'model' => get_option('deepblogger_' . $current_provider . '_model'),
            'posts_per_category' => get_option('deepblogger_posts_per_category'),
            'categories' => get_option('deepblogger_post_categories')
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

    /**
     * AJAX-Handler für die Kategorieanalyse
     */
    public function handle_analyze_category() {
        check_ajax_referer('deepblogger_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        if (!$category_id) {
            wp_send_json_error('Ungültige Kategorie-ID');
            return;
        }

        try {
            $deepseek_service = new DeepseekService();
            $suggestions = $deepseek_service->analyze_category($category_id);
            wp_send_json_success($suggestions);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX-Handler für die Beitragsgenerierung aus Vorschlägen
     */
    public function handle_generate_post() {
        check_ajax_referer('deepblogger_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $keywords = isset($_POST['keywords']) ? array_map('sanitize_text_field', (array)$_POST['keywords']) : array();

        if (empty($title)) {
            wp_send_json_error('Titel ist erforderlich');
            return;
        }

        try {
            $openai_service = new OpenAIService();
            $post_id = $openai_service->generate_post($title, $description, $keywords);
            
            wp_send_json_success(array(
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'url')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
} 