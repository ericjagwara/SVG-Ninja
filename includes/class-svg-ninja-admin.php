<?php
/**
 * Admin functionality class.
 *
 * @package SVG_Ninja
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG Ninja Admin Class.
 *
 * Handles admin interface, settings, and notices.
 *
 * @since 1.0.0
 */
class SVG_Ninja_Admin {

	/**
	 * Settings page hook suffix.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected $page_hook = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_notices', array( $this, 'show_processed_notice' ) );
		add_filter( 'plugin_action_links_' . SVG_NINJA_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add settings page to WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		$this->page_hook = add_options_page(
			__( 'SVG Ninja Settings', 'svg-ninja' ),
			__( 'SVG Ninja', 'svg-ninja' ),
			'manage_options',
			'svg-ninja',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles only on the plugin settings page.
	 *
	 * @since 1.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_styles( $hook_suffix ) {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->get_settings_css() );
	}

	/**
	 * Get CSS for the settings page.
	 *
	 * @since 1.1.0
	 *
	 * @return string CSS rules.
	 */
	protected function get_settings_css() {
		return '
			.svg-ninja-intro {
				background: #fff;
				padding: 20px;
				margin: 20px 0;
				border-left: 4px solid #2271b1;
			}
			.svg-ninja-intro h2 {
				margin-top: 0;
			}
			.svg-ninja-intro p.lead {
				font-size: 15px;
				line-height: 1.6;
			}
			.svg-ninja-intro p.footprint {
				font-size: 14px;
				color: #646970;
			}
			.svg-ninja-info {
				background: #f9f9f9;
				padding: 15px;
				margin-top: 30px;
				border: 1px solid #ddd;
			}
			.svg-ninja-info ul {
				list-style: disc;
				margin-left: 20px;
			}
		';
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'svg_ninja_settings',
			'svg_ninja_admin_only',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);

		register_setting(
			'svg_ninja_settings',
			'svg_ninja_strip_metadata',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);

		add_settings_section(
			'svg_ninja_main_section',
			__( 'Performance Settings', 'svg-ninja' ),
			array( $this, 'render_section_description' ),
			'svg-ninja'
		);

		add_settings_field(
			'svg_ninja_admin_only',
			__( 'Admin-Only Uploads', 'svg-ninja' ),
			array( $this, 'render_admin_only_field' ),
			'svg-ninja',
			'svg_ninja_main_section'
		);

		add_settings_field(
			'svg_ninja_strip_metadata',
			__( 'Strip Metadata', 'svg-ninja' ),
			array( $this, 'render_strip_metadata_field' ),
			'svg-ninja',
			'svg_ninja_main_section'
		);
	}

	/**
	 * Sanitize checkbox values.
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_checkbox( $value ) {
		return '1' === $value ? '1' : '0';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="svg-ninja-intro">
				<h2><?php esc_html_e( 'SVG Ninja Active', 'svg-ninja' ); ?></h2>
				<p class="lead">
					<?php esc_html_e( 'Your WordPress site can now upload SVG files. SVG Ninja automatically strips bloated metadata and ensures they display correctly in the Media Library.', 'svg-ninja' ); ?>
				</p>
				<p class="footprint">
					<strong><?php esc_html_e( 'Minimal Frontend Footprint:', 'svg-ninja' ); ?></strong>
					<?php esc_html_e( 'Processing and admin UI only run in the dashboard. No CSS or JS is added to your public pages.', 'svg-ninja' ); ?>
				</p>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'svg_ninja_settings' );
				do_settings_sections( 'svg-ninja' );
				submit_button( __( 'Save Settings', 'svg-ninja' ) );
				?>
			</form>

			<div class="svg-ninja-info">
				<h3><?php esc_html_e( 'What This Plugin Does:', 'svg-ninja' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Enables SVG and SVGZ file uploads', 'svg-ninja' ); ?></li>
					<li><?php esc_html_e( 'Strips Adobe, Figma, and Sketch metadata bloat', 'svg-ninja' ); ?></li>
					<li><?php esc_html_e( 'Auto-corrects missing viewBox attributes', 'svg-ninja' ); ?></li>
					<li><?php esc_html_e( 'Fixes broken thumbnails in Media Library', 'svg-ninja' ); ?></li>
					<li><?php esc_html_e( 'Restricts uploads to admins (configurable)', 'svg-ninja' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render section description.
	 *
	 * @since 1.0.0
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configure how SVG Ninja handles your vector files.', 'svg-ninja' ) . '</p>';
	}

	/**
	 * Render admin-only field.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_only_field() {
		$value = get_option( 'svg_ninja_admin_only', '1' );
		?>
		<label>
			<input type="checkbox" name="svg_ninja_admin_only" value="1" <?php checked( '1', $value ); ?>>
			<?php esc_html_e( 'Only allow Administrators to upload SVG files', 'svg-ninja' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Recommended for security. Developers can modify the required capability using the svg_ninja_upload_capability filter.', 'svg-ninja' ); ?>
		</p>
		<?php
	}

	/**
	 * Render strip metadata field.
	 *
	 * @since 1.0.0
	 */
	public function render_strip_metadata_field() {
		$value = get_option( 'svg_ninja_strip_metadata', '1' );
		?>
		<label>
			<input type="checkbox" name="svg_ninja_strip_metadata" value="1" <?php checked( '1', $value ); ?>>
			<?php esc_html_e( 'Remove bloated metadata from design apps', 'svg-ninja' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Cleans out proprietary tags from Adobe Illustrator, Figma, and Sketch.', 'svg-ninja' ); ?>
		</p>
		<?php
	}

	/**
	 * Show processed file notice after a successful upload.
	 *
	 * @since 1.0.0
	 */
	public function show_processed_notice() {
		if ( get_transient( 'svg_ninja_processed_notice' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'SVG Ninja:', 'svg-ninja' ); ?></strong>
					<?php esc_html_e( 'Your SVG has been processed and optimized.', 'svg-ninja' ); ?>
				</p>
			</div>
			<?php
			delete_transient( 'svg_ninja_processed_notice' );
		}
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=svg-ninja' ) ),
			esc_html__( 'Settings', 'svg-ninja' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
