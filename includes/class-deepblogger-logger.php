<?php
/**
 * DeepBlogger Logger
 */

class DeepBlogger_Logger {
    /**
     * Log-Level Konstanten
     */
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * Gibt die Singleton-Instanz zurück
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Schreibt eine Debug-Nachricht
     */
    public function debug($message, $context = array()) {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Schreibt eine Info-Nachricht
     */
    public function info($message, $context = array()) {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Schreibt eine Warnung
     */
    public function warning($message, $context = array()) {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Schreibt einen Fehler
     */
    public function error($message, $context = array()) {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Schreibt eine Log-Nachricht
     */
    private function log($level, $message, $context = array()) {
        if (!WP_DEBUG) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = $this->format_message($timestamp, $level, $message, $context);
        
        error_log($formatted_message);
    }

    /**
     * Formatiert die Log-Nachricht
     */
    private function format_message($timestamp, $level, $message, $context = array()) {
        $formatted = sprintf(
            "[%s] [DeepBlogger] [%s] %s",
            $timestamp,
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $formatted .= " Context: " . wp_json_encode($context);
        }

        return $formatted;
    }
} 