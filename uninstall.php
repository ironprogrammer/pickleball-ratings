<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package Pickleball_Ratings
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/upgrade.php';

pickleball_ratings_delete_legacy_options();
delete_option( 'pickleball_ratings_version' );
delete_option( 'pickleball_ratings_upgrading' );
