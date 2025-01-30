<?php
/**
 * PHPUnit bootstrap file
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

$_core_dir = getenv( 'WP_CORE_DIR' );
if ( ! $_core_dir ) {
    $_core_dir = '/tmp/wordpress';
}

// Definiere ABSPATH
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $_core_dir . '/' );
}

// Composer Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Lade WordPress Test Suite
require_once $_tests_dir . '/includes/functions.php';

/**
 * Lade unser Plugin manuell, da es nicht automatisch geladen wird
 */
function _manually_load_plugin() {
    require dirname( dirname( __FILE__ ) ) . '/deepblogger.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Stelle sicher, dass wir in einer Testumgebung sind
if (!defined('WP_TEST_MODE')) {
    define('WP_TEST_MODE', true);
}

// Initialisiere die Testumgebung
function _init_test_env() {
    // Deaktiviere E-Mail-Benachrichtigungen während der Tests
    add_filter('wp_mail', '__return_false');
    
    // Setze Test-Optionen
    update_option('deepblogger_openai_api_key', 'test_key');
    update_option('deepblogger_openai_model', 'gpt-3.5-turbo');
    update_option('deepblogger_post_status', 'draft');
    
    // Erstelle Test-Kategorie
    $category_id = wp_create_category('Test Kategorie');
    update_option('deepblogger_test_category', $category_id);
}

// Starte WordPress Test Suite
require $_tests_dir . '/includes/bootstrap.php';

// Führe Test-Initialisierung aus
_init_test_env(); 