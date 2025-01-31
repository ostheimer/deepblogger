<?php

/**
 * Definiert die Internationalisierungsfunktionalität
 *
 * Lädt und definiert die Internationalisierungsdateien für dieses Plugin,
 * damit es übersetzbar ist.
 */
class DeepBlogger_i18n {

    /**
     * Lädt die Übersetzungsdateien für das Plugin
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'deepblogger',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
} 