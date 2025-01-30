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
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
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

        // Registriere AJAX-Handler
        add_action('wp_ajax_deepblogger_save_settings', array($this, 'handle_save_settings'));

        // Schedule the cron event if not already scheduled
        if (!wp_next_scheduled('deepblogger_generate_scheduled_posts')) {
            wp_schedule_event(time(), 'deepblogger_custom_interval', 'deepblogger_generate_scheduled_posts');
        }

        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Add AJAX handler for post generation
        add_action('wp_ajax_deepblogger_generate_posts', [$this, 'handle_generate_posts']);
        
        // Add scheduled post generation handler
        add_action('deepblogger_generate_scheduled_posts', [$this, 'handle_generate_posts']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('DeepBlogger', 'deepblogger'),
            __('DeepBlogger', 'deepblogger'),
            'manage_options',
            'deepblogger',
            [$this, 'display_admin_page'],
            'dashicons-edit'
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings
        register_setting('deepblogger_options', 'deepblogger_openai_api_key');
        register_setting('deepblogger_options', 'deepblogger_openai_model');
        register_setting('deepblogger_options', 'deepblogger_post_schedule_unit');
        register_setting('deepblogger_options', 'deepblogger_post_schedule_value');
        register_setting('deepblogger_options', 'deepblogger_post_categories');
        register_setting('deepblogger_options', 'deepblogger_content_language');
        register_setting('deepblogger_options', 'deepblogger_writing_style');
        register_setting('deepblogger_options', 'deepblogger_content_length');
        register_setting('deepblogger_options', 'deepblogger_generate_images');
        register_setting('deepblogger_options', 'deepblogger_post_status');
        register_setting('deepblogger_options', 'deepblogger_custom_prompt');
        register_setting('deepblogger_options', 'deepblogger_default_prompt');
        register_setting('deepblogger_options', 'deepblogger_posts_per_category', [
            'type' => 'integer',
            'default' => 1,
            'sanitize_callback' => function($value) {
                return max(1, min(10, intval($value)));
            }
        ]);

        // Add settings section
        add_settings_section(
            'deepblogger_main_section',
            __('DeepBlogger Einstellungen', 'deepblogger'),
            null,
            'deepblogger_options'
        );

        // Add settings fields
        add_settings_field(
            'deepblogger_openai_api_key',
            __('OpenAI API Key', 'deepblogger'),
            [$this, 'render_openai_api_key_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_openai_model',
            __('OpenAI Model', 'deepblogger'),
            [$this, 'render_openai_model_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_post_categories',
            __('Kategorien', 'deepblogger'),
            [$this, 'render_categories_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_content_language',
            __('Sprache', 'deepblogger'),
            [$this, 'render_language_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_writing_style',
            __('Schreibstil', 'deepblogger'),
            [$this, 'render_writing_style_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_content_length',
            __('Artikellänge', 'deepblogger'),
            [$this, 'render_content_length_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_custom_prompt',
            __('Prompt', 'deepblogger'),
            [$this, 'render_prompt_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_post_status',
            __('Publikationsstatus', 'deepblogger'),
            [$this, 'render_post_status_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        add_settings_field(
            'deepblogger_posts_per_category',
            __('Beiträge pro Kategorie', 'deepblogger'),
            [$this, 'render_posts_per_category_field'],
            'deepblogger_options',
            'deepblogger_main_section'
        );

        // Set default prompt if not already set
        if (!get_option('deepblogger_default_prompt')) {
            update_option('deepblogger_default_prompt', 
                __('Schreibe einen informativen und ansprechenden Blogbeitrag zum Thema [KATEGORIE]. Der Artikel sollte gut strukturiert sein und wichtige Aspekte des Themas abdecken. Verwende einen [SCHREIBSTIL] Schreibstil und halte den Text etwa [LÄNGE] Wörter lang.', 'deepblogger')
            );
        }
    }

    /**
     * Render OpenAI API Key field
     */
    public function render_openai_api_key_field() {
        $value = get_option('deepblogger_openai_api_key');
        ?>
        <input type="password" 
               id="deepblogger_openai_api_key" 
               name="deepblogger_openai_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <p class="description">
            <?php 
            printf(
                /* translators: %s: URL to OpenAI API keys page */
                esc_html__('Get your API key from %s', 'deepblogger'),
                '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI API Keys</a>'
            ); 
            ?>
        </p>
        <?php
    }

    /**
     * Render OpenAI Model field
     */
    public function render_openai_model_field() {
        $value = get_option('deepblogger_openai_model', 'gpt-3.5-turbo');
        $api_key = get_option('deepblogger_openai_api_key');
        
        try {
            $openai_service = new \DeepBlogger\Services\OpenAIService($api_key);
            $models = $openai_service->get_available_models();
            ?>
            <select id="deepblogger_openai_model" 
                    name="deepblogger_openai_model">
                <?php foreach ($models as $model): ?>
                    <option value="<?php echo esc_attr($model['id']); ?>" 
                            <?php selected($value, $model['id']); ?>>
                        <?php echo esc_html($model['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        } catch (\Exception $e) {
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($e->getMessage()); ?></p>
            </div>
            <select id="deepblogger_openai_model" 
                    name="deepblogger_openai_model">
                <option value="gpt-4" <?php selected($value, 'gpt-4'); ?>>
                    <?php esc_html_e('GPT-4 (Leistungsfähigstes)', 'deepblogger'); ?>
                </option>
                <option value="gpt-3.5-turbo" <?php selected($value, 'gpt-3.5-turbo'); ?>>
                    <?php esc_html_e('GPT-3.5 Turbo (Schneller & günstiger)', 'deepblogger'); ?>
                </option>
            </select>
            <?php
        }
    }

    /**
     * Render categories field
     */
    public function render_categories_field() {
        $categories = get_categories(['hide_empty' => false]);
        $selected_categories = get_option('deepblogger_post_categories', []);
        foreach ($categories as $category) {
            ?>
            <label>
                <input type="checkbox" 
                       name="deepblogger_post_categories[]" 
                       value="<?php echo esc_attr($category->term_id); ?>"
                       <?php checked(in_array($category->term_id, $selected_categories)); ?>>
                <?php echo esc_html($category->name); ?>
            </label><br>
            <?php
        }
    }

    /**
     * Render language field
     */
    public function render_language_field() {
        $value = get_option('deepblogger_content_language', 'site_language');
        ?>
        <select id="deepblogger_content_language" name="deepblogger_content_language">
            <option value="site_language" <?php selected($value, 'site_language'); ?>>
                <?php esc_html_e('WordPress Sprache verwenden', 'deepblogger'); ?>
            </option>
            <option value="de" <?php selected($value, 'de'); ?>>
                <?php esc_html_e('Deutsch', 'deepblogger'); ?>
            </option>
            <option value="en" <?php selected($value, 'en'); ?>>
                <?php esc_html_e('Englisch', 'deepblogger'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render writing style field
     */
    public function render_writing_style_field() {
        $value = get_option('deepblogger_writing_style', 'professional');
        ?>
        <select id="deepblogger_writing_style" name="deepblogger_writing_style">
            <option value="professional" <?php selected($value, 'professional'); ?>>
                <?php esc_html_e('Professionell', 'deepblogger'); ?>
            </option>
            <option value="casual" <?php selected($value, 'casual'); ?>>
                <?php esc_html_e('Locker', 'deepblogger'); ?>
            </option>
            <option value="academic" <?php selected($value, 'academic'); ?>>
                <?php esc_html_e('Akademisch', 'deepblogger'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render content length field
     */
    public function render_content_length_field() {
        $value = get_option('deepblogger_content_length', 'medium');
        ?>
        <select id="deepblogger_content_length" name="deepblogger_content_length">
            <option value="short" <?php selected($value, 'short'); ?>>
                <?php esc_html_e('Kurz (~300 Wörter)', 'deepblogger'); ?>
            </option>
            <option value="medium" <?php selected($value, 'medium'); ?>>
                <?php esc_html_e('Mittel (~600 Wörter)', 'deepblogger'); ?>
            </option>
            <option value="long" <?php selected($value, 'long'); ?>>
                <?php esc_html_e('Lang (~1200 Wörter)', 'deepblogger'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render prompt field
     */
    public function render_prompt_field() {
        $default_prompt = get_option('deepblogger_default_prompt');
        $custom_prompt = get_option('deepblogger_custom_prompt', '');
        ?>
        <textarea id="deepblogger_custom_prompt" 
                  name="deepblogger_custom_prompt" 
                  rows="4" 
                  class="large-text"
                  placeholder="<?php echo esc_attr($default_prompt); ?>"
        ><?php echo esc_textarea($custom_prompt ? $custom_prompt : $default_prompt); ?></textarea>
        <p class="description">
            <?php esc_html_e('Passe den Prompt für die Beitragsgenerierung an. Der Standardprompt kann ergänzt oder vollständig ersetzt werden. Verfügbare Platzhalter: [KATEGORIE], [SCHREIBSTIL], [LÄNGE], [VORHANDENER_INHALT]', 'deepblogger'); ?>
        </p>
        <?php
    }

    /**
     * Render post status field
     */
    public function render_post_status_field() {
        $value = get_option('deepblogger_post_status', 'publish');
        ?>
        <select id="deepblogger_post_status" name="deepblogger_post_status">
            <option value="publish" <?php selected($value, 'publish'); ?>>
                <?php esc_html_e('Sofort veröffentlichen', 'deepblogger'); ?>
            </option>
            <option value="draft" <?php selected($value, 'draft'); ?>>
                <?php esc_html_e('Als Entwurf speichern', 'deepblogger'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render posts per category field
     */
    public function render_posts_per_category_field() {
        $value = get_option('deepblogger_posts_per_category', 1);
        ?>
        <input type="number" 
               id="deepblogger_posts_per_category" 
               name="deepblogger_posts_per_category" 
               value="<?php echo esc_attr($value); ?>"
               min="1"
               max="10"
               class="small-text">
        <p class="description">
            <?php esc_html_e('Anzahl der Beiträge, die pro Kategorie bei jedem Generierungslauf erstellt werden sollen (1-10).', 'deepblogger'); ?>
        </p>
        <?php
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

    /**
     * Handle AJAX request for post generation
     */
    public function handle_generate_posts() {
        check_ajax_referer('deepblogger_generate_posts', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'deepblogger'));
        }

        try {
            // Send initial status
            wp_send_json_progress([
                'step' => 'preparing',
                'message' => __('Vorbereitung...', 'deepblogger')
            ]);

            $openai_service = new \DeepBlogger\Services\OpenAIService();
            $post_generator = new \DeepBlogger\Services\PostGeneratorService($openai_service);
            
            $categories = get_option('deepblogger_post_categories', []);
            if (empty($categories)) {
                wp_send_json_error(__('No categories selected', 'deepblogger'));
            }

            $posts_created = 0;
            foreach ($categories as $category_id) {
                $category = get_category($category_id);
                if ($category) {
                    // Send progress update for content generation
                    wp_send_json_progress([
                        'step' => 'generating_content',
                        'message' => sprintf(__('Generiere Inhalt für Kategorie "%s"...', 'deepblogger'), $category->name)
                    ]);

                    $post_id = $post_generator->generate_and_publish_post(
                        $category->name,
                        [
                            'category_id' => $category_id,
                            'language' => get_option('deepblogger_content_language', 'site_language'),
                            'writing_style' => get_option('deepblogger_writing_style', 'professional'),
                            'content_length' => get_option('deepblogger_content_length', 'medium'),
                            'generate_image' => get_option('deepblogger_generate_images', '1'),
                            'post_status' => get_option('deepblogger_post_status', 'publish')
                        ]
                    );

                    if ($post_id) {
                        $posts_created++;
                        // Send progress update for successful post creation
                        wp_send_json_progress([
                            'step' => 'publishing',
                            'message' => sprintf(__('Beitrag %d von %d erstellt', 'deepblogger'), $posts_created, count($categories))
                        ]);
                    }
                }
            }

            wp_send_json_success(sprintf(
                /* translators: %d: number of posts created */
                __('%d posts created successfully', 'deepblogger'),
                $posts_created
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX Handler zum Speichern der Einstellungen
     */
    public function handle_save_settings() {
        // Überprüfe die Berechtigung
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'deepblogger'));
        }

        // Überprüfe den Nonce
        if (!check_ajax_referer('deepblogger_save_settings', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'deepblogger'));
        }

        // Hole die Einstellungen aus dem Request
        $settings = array(
            'deepblogger_openai_api_key' => sanitize_text_field($_POST['api_key']),
            'deepblogger_openai_model' => sanitize_text_field($_POST['model']),
            'deepblogger_posts_per_category' => absint($_POST['posts_per_category'])
        );

        // Speichere die Einstellungen
        foreach ($settings as $option_name => $option_value) {
            update_option($option_name, $option_value);
        }

        wp_send_json_success(__('Settings saved successfully', 'deepblogger'));
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include the admin page template
        require_once DEEPBLOGGER_PLUGIN_DIR . 'admin/admin-page.php';
    }
}

// Initialize plugin
DeepBlogger::get_instance(); 