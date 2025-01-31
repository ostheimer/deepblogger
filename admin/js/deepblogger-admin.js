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
    function loadAvailableModels(provider, forceRefresh = false) {
        debugLog('Loading models for provider:', provider);
        
        var $modelSelect = $('#' + provider + '_model');
        var $modelStatus = $('.model-status');
        var $refreshButton = $('.refresh-models[data-provider="' + provider + '"]');
        var $container = $modelSelect.closest('.model-select-container');
        var $spinner = $container.find('.spinner');
        
        if (!$modelSelect.length) {
            debugLog('Model select not found for provider:', provider);
            return;
        }

        // Show loading state immediately
        $modelSelect.prop('disabled', true)
            .addClass('loading');
        $refreshButton.prop('disabled', true)
            .find('.dashicons')
            .addClass('dashicons-update-spin');
        $spinner.addClass('is-active');

        // Zeige Ladestatus mit Animation
        var $loadingOption = $('<option>', {
            value: '',
            text: 'Lade Modelle',
            class: 'loading-text'
        });
        $modelSelect.empty().append($loadingOption);
        
        var loadingInterval = setInterval(function() {
            var currentText = $loadingOption.text();
            var dots = currentText.match(/\./g) || [];
            $loadingOption.text('Lade Modelle' + (dots.length >= 3 ? '' : '.'.repeat(dots.length + 1)));
        }, 500);

        // Perform the AJAX request
        return $.ajax({
            url: deepbloggerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_get_models',
                provider: provider,
                force_refresh: forceRefresh,
                nonce: deepbloggerAdmin.nonce
            },
            timeout: 30000 // 30 Sekunden Timeout
        })
        .done(function(response) {
            debugLog('Models response:', response);
            clearInterval(loadingInterval);
            
            if (response.success && response.data && response.data.models && response.data.models.length > 0) {
                $modelSelect.empty().append($('<option>', {
                    value: '',
                    text: deepbloggerAdmin.strings.selectModel || 'Modell auswählen'
                }));

                // Direkte Verwendung des Models-Array
                response.data.models.forEach(function(model) {
                    $modelSelect.append($('<option>', {
                        value: model.id,
                        text: model.name
                    }));
                });

                // Setze den gespeicherten Wert, falls vorhanden
                var savedModel = $modelSelect.data('saved-model');
                if (savedModel) {
                    $modelSelect.val(savedModel);
                }

                $modelStatus.text(deepbloggerAdmin.strings.modelsLoaded || 'Modelle erfolgreich geladen')
                    .removeClass('loading error')
                    .addClass('success')
                    .delay(2000)
                    .fadeOut();
            } else {
                var errorMessage = response.data && response.data.message ? 
                    response.data.message : 
                    (deepbloggerAdmin.strings.modelLoadError || 'Fehler beim Laden der Modelle');
                
                debugLog('Error in models response:', response);
                $modelSelect.empty().append($('<option>', {
                    value: '',
                    text: errorMessage
                }));
                
                $modelStatus.text(errorMessage)
                    .removeClass('loading success')
                    .addClass('error');
            }
        })
        .fail(function(xhr, status, error) {
            clearInterval(loadingInterval);
            
            var errorMessage = '';
            if (status === 'timeout') {
                errorMessage = 'Zeitüberschreitung beim Laden der Modelle';
            } else if (xhr.status === 0) {
                errorMessage = 'Keine Verbindung zum Server möglich';
            } else {
                errorMessage = 'Fehler beim Laden der Modelle: ' + (error || status);
            }
            
            debugLog('Ajax error:', {xhr: xhr, status: status, error: error});
            $modelSelect.empty().append($('<option>', {
                value: '',
                text: errorMessage
            }));
            
            $modelStatus.text(errorMessage)
                .removeClass('loading success')
                .addClass('error');
        })
        .always(function() {
            $modelSelect.prop('disabled', false)
                .removeClass('loading');
            $refreshButton.prop('disabled', false)
                .find('.dashicons')
                .removeClass('dashicons-update-spin');
            $spinner.removeClass('is-active');
        });
    }

    // Initialize provider sections and load models asynchronously
    function initializeProviderSettings() {
        toggleProviderSections();
        
        // Load models for providers with API keys
        $('.ai-provider-api-key').each(function() {
            var provider = $(this).data('provider');
            if ($(this).val()) {
                // Delay each provider load slightly to prevent overwhelming the server
                setTimeout(function() {
                    loadAvailableModels(provider);
                }, 100);
            }
        });
    }

    // Initialize on page load
    initializeProviderSettings();

    // Handle provider change
    $('#deepblogger_ai_provider').on('change', function() {
        toggleProviderSections();
        loadAvailableModels($(this).val());
    });

    // Handle refresh button click
    $('.refresh-models').on('click', function() {
        var provider = $(this).data('provider');
        var $button = $(this);
        
        // Disable button and show loading state
        $button.prop('disabled', true)
            .find('.dashicons')
            .addClass('dashicons-update-spin');
        
        loadAvailableModels(provider, true)
            .always(function() {
                // Re-enable button and stop spinning
                $button.prop('disabled', false)
                    .find('.dashicons')
                    .removeClass('dashicons-update-spin');
            });
    });

    // Load models when API key is changed (with debounce)
    var apiKeyChangeTimeout;
    $('.ai-provider-api-key').on('input', function() {
        var $input = $(this);
        var provider = $input.data('provider');
        
        clearTimeout(apiKeyChangeTimeout);
        
        if ($input.val()) {
            apiKeyChangeTimeout = setTimeout(function() {
                loadAvailableModels(provider);
            }, 500); // Wait 500ms after last input before loading
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