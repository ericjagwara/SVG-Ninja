<?php
/**
 * Uninstall script for SVG Ninja.
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 *
 * @package SVG_Ninja
 * @since 1.0.0
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin options and transients.
 */
function svg_ninja_uninstall() {
	delete_option( 'svg_ninja_admin_only' );
	delete_option( 'svg_ninja_strip_metadata' );
	// Legacy: this option existed in v1.0.0 but was removed in v1.1.0.
	delete_option( 'svg_ninja_sanitize' );

	delete_transient( 'svg_ninja_cleaned_notice' );
	delete_transient( 'svg_ninja_missing_library' );
}

svg_ninja_uninstall();
