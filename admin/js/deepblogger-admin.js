jQuery(document).ready(function($) {
    'use strict';

    // Überprüfe, ob deepbloggerAdmin verfügbar ist
    if (typeof deepbloggerAdmin === 'undefined') {
        console.error('deepbloggerAdmin ist nicht definiert');
        return;
    }

    // Debug logging
    function debugLog(message, data) {
        if (window.console && window.console.log) {
            console.log('DeepBlogger Debug:', message, data || '');
        }
    }

    // Show/hide settings sections based on selected provider
    function toggleProviderSections() {
        var selectedProvider = $('#deepblogger_ai_provider').val();
        debugLog('Selected provider:', selectedProvider);
        
        $('.provider-section').removeClass('active');
        $('#' + selectedProvider + '-settings').addClass('active');
    }

    // Load available models for the selected provider
    function loadAvailableModels(provider) {
        debugLog('Loading models for provider:', provider);
        
        var $modelSelect = $('#' + provider + '_model');
        var $modelStatus = $modelSelect.siblings('.model-status');
        var currentModel = $modelSelect.data('current-model');
        
        if (!$modelSelect.length) {
            debugLog('Model select not found for provider:', provider);
            return;
        }

        $modelSelect.prop('disabled', true);
        $modelStatus.text($modelSelect.data('loading'));

        $.ajax({
            url: deepbloggerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_get_models',
                provider: provider,
                nonce: deepbloggerAdmin.nonce
            },
            success: function(response) {
                debugLog('Models response:', response);
                
                if (response.success && response.data.models) {
                    $modelSelect.empty().append($('<option>', {
                        value: '',
                        text: deepbloggerAdmin.strings.selectModel
                    }));

                    $.each(response.data.models, function(id, name) {
                        $modelSelect.append($('<option>', {
                            value: id,
                            text: name,
                            selected: id === currentModel
                        }));
                    });

                    $modelStatus.text(deepbloggerAdmin.strings.modelsLoaded)
                        .removeClass('error')
                        .addClass('success');
                } else {
                    debugLog('Error in models response:', response);
                    $modelStatus.text(response.data.message || deepbloggerAdmin.strings.modelLoadError)
                        .removeClass('success')
                        .addClass('error');
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX error:', { status: status, error: error });
                $modelStatus.text(deepbloggerAdmin.strings.modelLoadError)
                    .removeClass('success')
                    .addClass('error');
            },
            complete: function() {
                $modelSelect.prop('disabled', false);
            }
        });
    }

    // Initialize provider sections
    toggleProviderSections();

    // Handle provider change
    $('#deepblogger_ai_provider').on('change', function() {
        toggleProviderSections();
        loadAvailableModels($(this).val());
    });

    // Load models on page load if API key is set
    $('.ai-provider-api-key').each(function() {
        var provider = $(this).data('provider');
        if ($(this).val()) {
            loadAvailableModels(provider);
        }
    });

    // Load models when API key is changed
    $('.ai-provider-api-key').on('change', function() {
        var provider = $(this).data('provider');
        if ($(this).val()) {
            loadAvailableModels(provider);
        }
    });

    // Handle settings form submission
    $('#deepblogger-settings-form').on('submit', function(e) {
        e.preventDefault();
        debugLog('Saving settings...');

        var $form = $(this);
        var $submitButton = $form.find(':submit');
        var $notice = $('#settings-saved');
        var $errorNotice = $('#settings-error');

        $submitButton.prop('disabled', true);

        $.ajax({
            url: deepbloggerAdmin.ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=deepblogger_save_settings&nonce=' + deepbloggerAdmin.nonce,
            success: function(response) {
                debugLog('Settings response:', response);
                
                if (response.success) {
                    $notice.slideDown().delay(3000).slideUp();
                    $errorNotice.slideUp();
                } else {
                    $errorNotice.text(response.data.message || deepbloggerAdmin.strings.saveError).slideDown();
                    $notice.slideUp();
                }
            },
            error: function(xhr, status, error) {
                debugLog('Settings error:', { status: status, error: error });
                $errorNotice.text(deepbloggerAdmin.strings.saveError).slideDown();
                $notice.slideUp();
            },
            complete: function() {
                $submitButton.prop('disabled', false);
            }
        });
    });

    // Generiere-Button Handler
    $('#deepblogger_generate_now').on('click', function() {
        const $button = $(this);
        const $status = $('#deepblogger_detailed_status');
        
        $button.prop('disabled', true);
        $('.status-step').removeClass('active completed').hide();
        
        function updateStatus(step, isCompleted = false) {
            const $step = $('#status_' + step);
            $('.status-step').removeClass('active');
            $step.addClass('active ' + (isCompleted ? 'completed' : 'loading')).show();
        }
        
        updateStatus('preparing');
        
        $.ajax({
            url: deepbloggerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_generate_posts',
                nonce: deepbloggerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatus('preparing', true);
                    updateStatus('generating_content', true);
                    updateStatus('publishing', true);
                    
                    setTimeout(function() {
                        $('.status-step').fadeOut();
                    }, 3000);
                } else {
                    $('#settings-error p').text(response.data || 'Ein Fehler ist aufgetreten');
                    $('#settings-error').show();
                }
            },
            error: function() {
                $('#settings-error p').text('Fehler bei der Kommunikation mit dem Server');
                $('#settings-error').show();
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
}); 