jQuery(document).ready(function($) {
    'use strict';

    // Überprüfe, ob deepbloggerAdmin verfügbar ist
    if (typeof deepbloggerAdmin === 'undefined') {
        console.error('deepbloggerAdmin ist nicht definiert');
        return;
    }

    const $results = $('.deepseek-results');
    const $loading = $('.deepseek-loading');
    const $suggestions = $('.deepseek-suggestions');
    const suggestionTemplate = $('#deepseek-suggestion-template').html();

    // Event-Handler für Kategorie-Analyse
    $('.analyze-category').on('click', function(e) {
        e.preventDefault();
        const categoryId = $(this).data('category-id');
        analyzeCategoryContent(categoryId);
    });

    // Event-Handler für Beitragsgenerierung
    $(document).on('click', '.generate-post', function(e) {
        e.preventDefault();
        const $card = $(this).closest('.suggestion-card');
        const title = $card.find('.suggestion-title').text();
        const description = $card.find('.suggestion-description').text();
        const keywords = $card.find('.suggestion-keywords').data('keywords');
        generatePost(title, description, keywords);
    });

    /**
     * Analysiert den Inhalt einer Kategorie
     * 
     * @param {number} categoryId Die Kategorie-ID
     */
    function analyzeCategoryContent(categoryId) {
        $results.show();
        $loading.show();
        $suggestions.empty();

        $.ajax({
            url: deepbloggerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_analyze_category',
                nonce: deepbloggerAdmin.nonce,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    displaySuggestions(response.data);
                } else {
                    displayError(response.data.message || 'Ein Fehler ist aufgetreten');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Fehler:', error);
                displayError('Fehler bei der Kommunikation mit dem Server');
            },
            complete: function() {
                $loading.hide();
            }
        });
    }

    /**
     * Zeigt die Themenvorschläge an
     * 
     * @param {Array} suggestions Array mit Themenvorschlägen
     */
    function displaySuggestions(suggestions) {
        if (!Array.isArray(suggestions)) {
            displayError('Ungültige Daten vom Server erhalten');
            return;
        }

        suggestions.forEach(function(suggestion) {
            const $card = $(suggestionTemplate);
            
            $card.find('.suggestion-title').text(suggestion.title);
            $card.find('.suggestion-description').text(suggestion.description);
            
            const $keywords = $card.find('.suggestion-keywords');
            $keywords.data('keywords', suggestion.keywords);
            suggestion.keywords.forEach(function(keyword) {
                $keywords.append($('<span>').text(keyword));
            });

            $suggestions.append($card);
        });
    }

    /**
     * Zeigt eine Fehlermeldung an
     * 
     * @param {string} message Die Fehlermeldung
     */
    function displayError(message) {
        $suggestions.html(
            $('<div>')
                .addClass('notice notice-error')
                .text(message)
        );
    }

    /**
     * Generiert einen neuen Beitrag
     * 
     * @param {string} title Der Titel des Beitrags
     * @param {string} description Die Beschreibung
     * @param {Array} keywords Die Keywords
     */
    function generatePost(title, description, keywords) {
        $.ajax({
            url: deepbloggerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'deepblogger_generate_post',
                nonce: deepbloggerAdmin.nonce,
                title: title,
                description: description,
                keywords: keywords
            },
            success: function(response) {
                if (response.success) {
                    // Öffne den generierten Beitrag im Editor
                    window.location.href = response.data.edit_url;
                } else {
                    alert(response.data.message || 'Ein Fehler ist aufgetreten');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Fehler:', error);
                alert('Fehler bei der Kommunikation mit dem Server');
            }
        });
    }
}); 