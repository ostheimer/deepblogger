jQuery(document).ready(function($) {
    'use strict';

    // Funktion zum Speichern der Einstellungen
    function saveSettings(event) {
        event.preventDefault();
        
        // Button deaktivieren
        const $submitButton = $(this).find('input[type="submit"]');
        $submitButton.prop('disabled', true);
        
        // Formulardaten sammeln
        const formData = new FormData(this);
        formData.append('action', 'deepblogger_save_settings');
        formData.append('nonce', deepbloggerAdmin.nonce);
        
        // AJAX-Anfrage senden
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#settings-saved')
                        .show()
                        .text('Einstellungen erfolgreich gespeichert');
                } else {
                    $('#settings-error')
                        .show()
                        .text(response.data.message || 'Ein Fehler ist aufgetreten');
                }
            },
            error: function() {
                $('#settings-error')
                    .show()
                    .text('Fehler: Konnte keine Verbindung zum Server herstellen');
            },
            complete: function() {
                // Button wieder aktivieren
                $submitButton.prop('disabled', false);
            }
        });
    }

    // Funktion zum Generieren von Beiträgen
    function generatePosts(event) {
        event.preventDefault();
        
        // Button deaktivieren
        const $generateButton = $('#deepblogger_generate_now');
        $generateButton.prop('disabled', true);
        
        // Status aktualisieren
        $('#status_preparing').addClass('active').show();
        
        // AJAX-Anfrage senden
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_generate_posts',
                nonce: deepbloggerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#status_preparing').removeClass('active');
                    $('#status_success').addClass('active').show();
                } else {
                    $('#status_preparing').removeClass('active');
                    $('#status_error')
                        .addClass('active')
                        .show()
                        .text(response.data.message || 'Ein Fehler ist aufgetreten');
                }
            },
            error: function() {
                $('#status_preparing').removeClass('active');
                $('#status_error')
                    .addClass('active')
                    .show()
                    .text('Fehler: Konnte keine Verbindung zum Server herstellen');
            },
            complete: function() {
                // Button wieder aktivieren
                $generateButton.prop('disabled', false);
            }
        });
    }

    // Event-Listener registrieren
    $('#deepblogger-settings-form').on('submit', saveSettings);
    $('#deepblogger_generate_now').on('click', generatePosts);
}); 