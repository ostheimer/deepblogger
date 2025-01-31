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

settings_errors('deepblogger_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="settings-saved" class="notice notice-success is-dismissible" style="display: none;">
        <p><?php esc_html_e('Einstellungen gespeichert.', 'deepblogger'); ?></p>
    </div>

    <div id="settings-error" class="notice notice-error is-dismissible" style="display: none;">
        <p></p>
    </div>

    <!-- Allgemeine Einstellungen -->
    <div class="deepblogger-section">
        <form method="post" action="options.php" id="deepblogger-settings-form">
            <?php
                settings_fields('deepblogger_options');
                do_settings_sections('deepblogger_options');
                submit_button(__('Einstellungen speichern', 'deepblogger'));
            ?>
        </form>
    </div>

    <!-- Beitragsgenerierung -->
    <div class="deepblogger-section">
        <h2><?php echo esc_html__('Beitragsgenerierung', 'deepblogger'); ?></h2>
        <p><?php echo esc_html__('Klicken Sie auf den Button, um Beiträge für die ausgewählten Kategorien zu generieren.', 'deepblogger'); ?></p>
        
        <button type="button" id="deepblogger_generate_now" class="button button-primary">
            <?php echo esc_html__('Beiträge jetzt generieren', 'deepblogger'); ?>
        </button>

        <div id="deepblogger_detailed_status" class="status-section">
            <div class="status-step" id="status_preparing">
                <span class="dashicons"></span> <?php echo esc_html__('Vorbereitung...', 'deepblogger'); ?>
            </div>
            <div class="status-step" id="status_generating_content">
                <span class="dashicons"></span> <?php echo esc_html__('Generiere Inhalt...', 'deepblogger'); ?>
            </div>
            <div class="status-step" id="status_publishing">
                <span class="dashicons"></span> <?php echo esc_html__('Veröffentliche Beitrag...', 'deepblogger'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Debug-Bereich wird dynamisch durch JavaScript hinzugefügt -->

<style>
.deepblogger-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.ki-settings-box {
    background: #f9f9f9;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-top: 20px;
}
.status-section {
    margin-top: 20px;
}
.status-step {
    display: none;
    padding: 10px;
    margin: 5px 0;
    background: #f8f9fa;
    border-left: 4px solid #646970;
}
.status-step.active {
    display: block;
}
.status-step.loading .dashicons:before {
    content: "\f463";
    animation: dashicons-spin 1s infinite;
    display: inline-block;
}
@keyframes dashicons-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
.model-status {
    margin-top: 5px;
    padding: 8px;
    border-radius: 4px;
}
.model-status.notice-success {
    background-color: #f0f6e8;
    border-left: 4px solid #46b450;
}
.model-status.notice-error {
    background-color: #fbeaea;
    border-left: 4px solid #dc3232;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Lade verfügbare Modelle
    function loadAvailableModels(provider) {
        var $modelSelect = provider === 'openai' ? $('#deepblogger_openai_model') : $('#deepblogger_deepseek_model');
        var $modelStatus = $modelSelect.siblings('.model-status');
        
        $modelSelect.prop('disabled', true);
        $modelStatus.text('Lade verfügbare Modelle...').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_get_models',
                provider: provider,
                nonce: deepbloggerAdmin.nonce
            },
            success: function(response) {
                console.log('API Response:', response); // Debug-Ausgabe

                if (response.success && response.data && Array.isArray(response.data.models)) {
                    $modelSelect.empty().append($('<option>', {
                        value: '',
                        text: '<?php echo esc_js(__('Bitte wählen...', 'deepblogger')); ?>'
                    }));

                    response.data.models.forEach(function(model) {
                        if (model && model.id && model.name) {
                            $modelSelect.append($('<option>', {
                                value: model.id,
                                text: model.name
                            }));
                        }
                    });

                    if (response.data.message) {
                        $modelStatus.text(response.data.message)
                            .addClass('notice-success')
                            .removeClass('notice-error');
                    } else {
                        $modelStatus.hide();
                    }
                } else {
                    console.error('Ungültige API-Antwort:', response); // Debug-Ausgabe
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : '<?php echo esc_js(__('Fehler beim Laden der Modelle', 'deepblogger')); ?>';
                    
                    $modelStatus.text(errorMsg)
                        .addClass('notice-error')
                        .removeClass('notice-success');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error}); // Debug-Ausgabe
                var errorMsg = '<?php echo esc_js(__('Verbindungsfehler beim Laden der Modelle', 'deepblogger')); ?>';
                $modelStatus.text(errorMsg)
                    .addClass('notice-error')
                    .removeClass('notice-success');
            },
            complete: function() {
                $modelSelect.prop('disabled', false);
            }
        });
    }

    // Lade Modelle beim Ändern des Providers
    $('#deepblogger_ai_provider').on('change', function() {
        var provider = $(this).val();
        $('.provider-section').hide();
        $('#' + provider + '-settings').show();
        
        // Prüfe ob API Key vorhanden ist
        var apiKey = provider === 'openai' 
            ? $('#deepblogger_openai_api_key').val() 
            : $('#deepblogger_deepseek_api_key').val();
            
        if (apiKey) {
            loadAvailableModels(provider);
        } else {
            var $modelSelect = provider === 'openai' 
                ? $('#deepblogger_openai_model') 
                : $('#deepblogger_deepseek_model');
            var $modelStatus = $modelSelect.siblings('.model-status');
            
            $modelStatus.text('<?php echo esc_js(__('Bitte geben Sie zuerst einen API-Schlüssel ein', 'deepblogger')); ?>')
                .addClass('notice-error')
                .removeClass('notice-success')
                .show();
        }
    }).trigger('change');

    // Lade Modelle wenn API Key geändert wird
    $('#deepblogger_openai_api_key, #deepblogger_deepseek_api_key').on('change', function() {
        var provider = $(this).attr('id').includes('openai') ? 'openai' : 'deepseek';
        if ($(this).val()) {
            loadAvailableModels(provider);
        }
    });
});
</script> 