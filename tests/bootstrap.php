<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Pickleball_Ratings
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    // Try common macOS/Linux temp locations as fallbacks.
    $candidates = array(
        '/tmp/wordpress-tests-lib',
        '/private/tmp/wordpress-tests-lib',
    );
    foreach ( $candidates as $candidate ) {
        if ( file_exists( "$candidate/includes/functions.php" ) ) {
            $_tests_dir = $candidate;
            break;
        }
    }
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php.\n";
    echo "Tip: run bin/install-wp-tests.sh or set WP_TESTS_DIR to your install (e.g. /tmp/wordpress-tests-lib)." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    $plugin_dir = dirname( dirname( __FILE__ ) );
    require $plugin_dir . '/pickleball-ratings.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
