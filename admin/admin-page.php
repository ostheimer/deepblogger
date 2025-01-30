<?php
/**
 * Admin page template for DeepBlogger settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    return;
}

// Enqueue admin scripts and styles
wp_enqueue_script('deepblogger-admin', plugin_dir_url(__FILE__) . 'js/deepblogger-admin.js', array('jquery'), '1.0.0', true);
wp_localize_script('deepblogger-admin', 'deepbloggerAdmin', array(
    'nonce' => wp_create_nonce('deepblogger_settings_nonce'),
    'ajaxurl' => admin_url('admin-ajax.php')
));
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="settings-saved" class="notice notice-success is-dismissible" style="display: none;">
        <p><?php esc_html_e('Einstellungen gespeichert.', 'deepblogger'); ?></p>
    </div>

    <div id="settings-error" class="notice notice-error is-dismissible" style="display: none;">
        <p></p>
    </div>

    <form id="deepblogger-settings-form" method="post">
        <?php wp_nonce_field('deepblogger_save_settings', 'deepblogger_settings_nonce'); ?>
        <?php
        settings_fields('deepblogger_options');
        do_settings_sections('deepblogger_options');
        submit_button(__('Einstellungen speichern', 'deepblogger'));
        ?>

        <!-- Generate Now Button -->
        <div class="generate-posts-section">
            <button type="button" 
                    id="deepblogger_generate_now" 
                    class="button button-primary">
                <?php esc_html_e('Beiträge jetzt generieren', 'deepblogger'); ?>
            </button>
        </div>

        <!-- Status Display -->
        <div class="status-section">
            <h3><?php esc_html_e('Status', 'deepblogger'); ?></h3>
            <div id="deepblogger_detailed_status">
                <div class="status-step" id="status_preparing">
                    <span class="dashicons"></span> <?php esc_html_e('Vorbereitung...', 'deepblogger'); ?>
                </div>
                <div class="status-step" id="status_generating_content">
                    <span class="dashicons"></span> <?php esc_html_e('Generiere Inhalt...', 'deepblogger'); ?>
                </div>
                <div class="status-step" id="status_generating_image">
                    <span class="dashicons"></span> <?php esc_html_e('Generiere Artikelbild...', 'deepblogger'); ?>
                </div>
                <div class="status-step" id="status_publishing">
                    <span class="dashicons"></span> <?php esc_html_e('Veröffentliche Beitrag...', 'deepblogger'); ?>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.status-section {
    margin-top: 2em;
    padding: 1em;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.generate-posts-section {
    margin: 2em 0;
    padding: 1em;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.status-step {
    margin: 5px 0;
    display: none;
    padding: 5px;
}

.status-step .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
    margin-right: 5px;
}

.status-step.active {
    display: block;
    background: #f8f9fa;
    border-left: 4px solid #646970;
}

.status-step.loading .dashicons:before {
    content: "\f463";
    animation: dashicons-spin 1s infinite;
    display: inline-block;
}

.status-step.completed .dashicons:before {
    content: "\f147";
    color: #46b450;
}

@keyframes dashicons-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style> 