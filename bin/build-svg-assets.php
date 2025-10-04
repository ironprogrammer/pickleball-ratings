<?php
/**
 * SVG Assets Generator
 *
 * Generates a PHP file containing all SVG assets for runtime use.
 * Run this during the build process to eliminate runtime file system calls.
 *
 * @package Pickleball_Ratings
 */

// Ensure we're in the plugin root directory.
if ( ! is_dir( 'images' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "Error: images directory not found. Run this script from the plugin root.\n";
	exit( 1 );
}

// Scan for all SVG files.
$svg_files = glob( 'images/*.svg' );
if ( empty( $svg_files ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "Error: No SVG files found in images directory.\n";
	exit( 1 );
}

// Build assets array.
$assets = array();
foreach ( $svg_files as $file ) {
	$name = basename( $file, '.svg' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$content = file_get_contents( $file );

	if ( false === $content ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "Error: Failed to read SVG file: {$file}\n";
		exit( 1 );
	}

	$assets[ $name ] = $content;
}

// Generate the assets file.
$assets_content  = '<?php' . "\n";
$assets_content .= '/**' . "\n";
$assets_content .= ' * SVG Assets - Auto-generated file' . "\n";
$assets_content .= ' * Generated: ' . gmdate( 'Y-m-d H:i:s' ) . "\n";
$assets_content .= ' */' . "\n\n";
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
$assets_content .= 'return ' . var_export( $assets, true ) . ';' . "\n";

// Ensure build directory exists.
if ( ! is_dir( 'build' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	mkdir( 'build', 0755, true );
}

// Write the assets file.
$assets_file = 'build/svg-assets.php';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
if ( false === file_put_contents( $assets_file, $assets_content ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "Error: Failed to write assets file: {$assets_file}\n";
	exit( 1 );
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo "Success: Generated SVG assets file: {$assets_file}\n";
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo 'Assets included: ' . implode( ', ', array_keys( $assets ) ) . "\n";
