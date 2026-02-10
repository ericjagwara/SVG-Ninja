<?php
/**
 * Plugin Name: SVG Ninja
 * Plugin URI: https://github.com/ericjagwara/svg-ninja
 * Description: Upload vectors. Strip junk. Stay fast. Secure SVG uploads with metadata cleaning and zero frontend footprint.
 * Version: 1.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Eric Jagwara
 * Author URI: https://github.com/ericjagwara/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: svg-ninja
 * Domain Path: /languages
 *
 * @package SVG_Ninja
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants with guards to prevent fatal errors on double-load.
if ( ! defined( 'SVG_NINJA_VERSION' ) ) {
	define( 'SVG_NINJA_VERSION', '1.1.0' );
}
if ( ! defined( 'SVG_NINJA_PLUGIN_DIR' ) ) {
	define( 'SVG_NINJA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SVG_NINJA_PLUGIN_URL' ) ) {
	define( 'SVG_NINJA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SVG_NINJA_PLUGIN_BASENAME' ) ) {
	define( 'SVG_NINJA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Load core classes.
require_once SVG_NINJA_PLUGIN_DIR . 'includes/class-svg-ninja-core.php';
require_once SVG_NINJA_PLUGIN_DIR . 'includes/class-svg-ninja-processor.php';
require_once SVG_NINJA_PLUGIN_DIR . 'includes/class-svg-ninja-admin.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function svg_ninja_init() {
	$plugin = SVG_Ninja_Core::get_instance();
	$plugin->run();
}

// Hook into plugins_loaded to ensure WordPress is fully loaded.
add_action( 'plugins_loaded', 'svg_ninja_init' );

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function svg_ninja_activate() {
	// Check minimum PHP version.
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'SVG Ninja requires PHP 7.4 or higher.', 'svg-ninja' ),
			esc_html__( 'Plugin Activation Error', 'svg-ninja' ),
			array( 'back_link' => true )
		);
	}

	// Set default options.
	add_option( 'svg_ninja_admin_only', '1' );
	add_option( 'svg_ninja_strip_metadata', '1' );
}
register_activation_hook( __FILE__, 'svg_ninja_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function svg_ninja_deactivate() {
	delete_transient( 'svg_ninja_processed_notice' );
}
register_deactivation_hook( __FILE__, 'svg_ninja_deactivate' );
