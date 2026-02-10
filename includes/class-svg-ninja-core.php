<?php
/**
 * Core plugin class.
 *
 * @package SVG_Ninja
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main SVG Ninja Core Class.
 *
 * @since 1.0.0
 */
class SVG_Ninja_Core {

	/**
	 * Single instance of the class.
	 *
	 * @var SVG_Ninja_Core
	 */
	protected static $instance = null;

	/**
	 * Processor instance.
	 *
	 * @var SVG_Ninja_Processor
	 */
	protected $processor;

	/**
	 * Admin instance.
	 *
	 * @var SVG_Ninja_Admin
	 */
	protected $admin;

	/**
	 * Previous state of the entity loader for XXE protection.
	 *
	 * @since 1.1.0
	 * @var bool|null
	 */
	protected $previous_entity_loader_state = null;

	/**
	 * Get singleton instance.
	 *
	 * @return SVG_Ninja_Core
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->processor = new SVG_Ninja_Processor();

		if ( is_admin() ) {
			$this->admin = new SVG_Ninja_Admin();
		}
	}

	/**
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Enable SVG mime type.
		add_filter( 'upload_mimes', array( $this, 'enable_svg_mime_type' ) );

		// Fix WordPress real mime-type detection for SVGs.
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_mime_type_detection' ), 10, 5 );

		// Admin-Only Upload Guard.
		add_filter( 'upload_mimes', array( $this, 'restrict_svg_to_admins' ), 99 );

		// Process SVG BEFORE it is moved to the uploads directory.
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'process_on_upload' ) );

		// Media Library Rendering (admin only).
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'fix_svg_thumbnails' ) );
			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'fix_svg_attachment_data' ), 10, 2 );
		}
	}

	/**
	 * Enable SVG and SVGZ mime types.
	 *
	 * @param array $mimes Existing mime types.
	 * @return array Modified mime types.
	 */
	public function enable_svg_mime_type( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Fix WordPress real mime-type detection for SVG files.
	 *
	 * @since 1.1.0
	 *
	 * @param array       $data     Values for the extension, mime type, and corrected filename.
	 * @param string      $file     Full path to the file.
	 * @param string      $filename The name of the file.
	 * @param string[]    $mimes    Array of mime types keyed by extension.
	 * @param string|bool $real_mime The actual mime type, or false if undetermined.
	 * @return array Modified data.
	 */
	public function fix_mime_type_detection( $data, $file, $filename, $mimes, $real_mime = false ) {
		// Only intervene when WordPress couldn't determine the type.
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'svg', 'svgz' ), true ) ) {
			return $data;
		}

		// Read and validate the file is actually SVG content.
		$file_contents = $this->get_svg_contents( $file );

		if ( false === $file_contents ) {
			return $data;
		}

		// Verify this is valid XML with an <svg> root element.
		if ( $this->validate_svg_xml( $file_contents ) ) {
			$data['ext']             = $ext;
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $filename;
		}

		return $data;
	}

	/**
	 * Restrict SVG uploads to administrators only.
	 *
	 * @param array $mimes Existing mime types.
	 * @return array Modified mime types.
	 */
	public function restrict_svg_to_admins( $mimes ) {
		if ( get_option( 'svg_ninja_admin_only', '1' ) !== '1' ) {
			return $mimes;
		}

		$required_cap = apply_filters( 'svg_ninja_upload_capability', 'manage_options' );

		if ( ! current_user_can( $required_cap ) ) {
			unset( $mimes['svg'], $mimes['svgz'] );
		}

		return $mimes;
	}

	/**
	 * Processing pipeline run BEFORE the file is moved to uploads.
	 *
	 * Handles: validation, SVGZ decompression, metadata stripping,
	 * viewBox correction, and writing the clean content back to the
	 * temp file — all before WordPress moves it.
	 *
	 * @since 1.1.0
	 *
	 * @param array $file File upload data from $_FILES.
	 * @return array Modified file data, or data with 'error' key set on failure.
	 */
	public function process_on_upload( $file ) {
		if ( ! $this->is_svg_file( $file ) ) {
			return $file;
		}

		// Capability check.
		$required_cap = apply_filters( 'svg_ninja_upload_capability', 'manage_options' );
		if ( get_option( 'svg_ninja_admin_only', '1' ) === '1' && ! current_user_can( $required_cap ) ) {
			$file['error'] = __( 'Sorry, you do not have permission to upload SVG files.', 'svg-ninja' );
			return $file;
		}

		// Read file contents (handles SVGZ decompression).
		$file_contents = $this->get_svg_contents( $file['tmp_name'] );

		if ( false === $file_contents || empty( $file_contents ) ) {
			$file['error'] = __( 'The uploaded SVG file appears to be empty or could not be read.', 'svg-ninja' );
			return $file;
		}

		// Validate XML structure with XXE protection.
		if ( ! $this->validate_svg_xml( $file_contents ) ) {
			$file['error'] = __( 'The uploaded file is not a valid SVG document.', 'svg-ninja' );
			return $file;
		}

		// Metadata stripping (optional).
		if ( get_option( 'svg_ninja_strip_metadata', '1' ) === '1' ) {
			$stripped = $this->processor->strip_metadata( $file_contents );
			if ( false !== $stripped && ! empty( $stripped ) ) {
				$file_contents = $stripped;
			}
		}

		// ViewBox auto-correction.
		$file_contents = $this->processor->ensure_viewbox( $file_contents );

		// Determine if we need to re-compress (SVGZ).
		$is_svgz = $this->is_svgz_file( $file );

		$write_contents = $is_svgz ? gzencode( $file_contents, 9 ) : $file_contents;

		if ( false === $write_contents ) {
			$file['error'] = __( 'Failed to compress the processed SVG file.', 'svg-ninja' );
			return $file;
		}

		// Write processed content back to the temp file BEFORE WP moves it.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$bytes_written = file_put_contents( $file['tmp_name'], $write_contents );

		if ( false === $bytes_written ) {
			$file['error'] = __( 'Failed to write the processed SVG file. Please check server permissions.', 'svg-ninja' );
			return $file;
		}

		// Update file size so WordPress records the correct value.
		$file['size'] = $bytes_written;

		// Flag for admin notice.
		set_transient( 'svg_ninja_processed_notice', true, 30 );

		return $file;
	}

	/**
	 * Fix SVG thumbnails in Media Library with inline CSS.
	 *
	 * @since 1.0.0
	 */
	public function fix_svg_thumbnails() {
		$css = '
			.attachment.type-image.subtype-svg-xml .thumbnail img,
			.attachment-preview.type-image.subtype-svg-xml .thumbnail img {
				width: 100% !important;
				height: auto !important;
			}
			table.media .column-title .media-icon img[src$=".svg"],
			table.media .column-title .media-icon img[src$=".svgz"] {
				width: 60px;
				height: 60px;
			}
		';
		wp_add_inline_style( 'wp-admin', $css );
	}

	/**
	 * Fix SVG attachment data for JavaScript rendering.
	 *
	 * @since 1.1.0
	 *
	 * @param array   $response   Attachment data.
	 * @param WP_Post $attachment Attachment object.
	 * @return array Modified attachment data.
	 */
	public function fix_svg_attachment_data( $response, $attachment ) {
		if ( 'image/svg+xml' !== $response['mime'] ) {
			return $response;
		}

		$file = get_attached_file( $attachment->ID );
		$dimensions = $this->get_svg_dimensions( $file );

		$width  = $dimensions['width'];
		$height = $dimensions['height'];

		// Thumbnail: scale proportionally to fit 150px box.
		$thumb_width  = 150;
		$thumb_height = 150;
		if ( $width > 0 && $height > 0 ) {
			$ratio        = $width / $height;
			$thumb_width  = ( $ratio >= 1 ) ? 150 : (int) round( 150 * $ratio );
			$thumb_height = ( $ratio >= 1 ) ? (int) round( 150 / $ratio ) : 150;
		}

		$response['image'] = array(
			'src'    => $response['url'],
			'width'  => $width,
			'height' => $height,
		);

		$response['thumb'] = array(
			'src'    => $response['url'],
			'width'  => $thumb_width,
			'height' => $thumb_height,
		);

		$response['sizes']['full'] = array(
			'url'         => $response['url'],
			'width'       => $width,
			'height'      => $height,
			'orientation' => ( $width >= $height ) ? 'landscape' : 'portrait',
		);

		return $response;
	}

	/**
	 * Get the actual dimensions from an SVG file.
	 *
	 * @since 1.1.0
	 *
	 * @param string $file Path to the SVG file.
	 * @return array Array with width and height.
	 */
	protected function get_svg_dimensions( $file ) {
		$defaults = array(
			'width'  => 300,
			'height' => 300,
		);

		if ( ! $file || ! file_exists( $file ) ) {
			return $defaults;
		}

		$contents = $this->get_svg_contents( $file );
		if ( false === $contents ) {
			return $defaults;
		}

		$this->disable_xxe();
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		if ( ! $dom->loadXML( $contents, LIBXML_NONET ) ) {
			libxml_clear_errors();
			$this->restore_xxe();
			return $defaults;
		}

		$svg = $dom->documentElement;
		if ( ! $svg ) {
			libxml_clear_errors();
			$this->restore_xxe();
			return $defaults;
		}

		// Try viewBox first.
		if ( $svg->hasAttribute( 'viewBox' ) ) {
			$parts = preg_split( '/[\s,]+/', trim( $svg->getAttribute( 'viewBox' ) ) );
			if ( count( $parts ) === 4 ) {
				$vb_width  = (float) $parts[2];
				$vb_height = (float) $parts[3];
				if ( $vb_width > 0 && $vb_height > 0 ) {
					libxml_clear_errors();
					$this->restore_xxe();
					return array(
						'width'  => (int) round( $vb_width ),
						'height' => (int) round( $vb_height ),
					);
				}
			}
		}

		// Fall back to width/height attributes.
		$width  = (float) preg_replace( '/[^0-9.]/', '', $svg->getAttribute( 'width' ) );
		$height = (float) preg_replace( '/[^0-9.]/', '', $svg->getAttribute( 'height' ) );

		libxml_clear_errors();
		$this->restore_xxe();

		if ( $width > 0 && $height > 0 ) {
			return array(
				'width'  => (int) round( $width ),
				'height' => (int) round( $height ),
			);
		}

		return $defaults;
	}

	/**
	 * Read SVG file contents, decompressing SVGZ if necessary.
	 *
	 * @since 1.1.0
	 *
	 * @param string $file_path Path to the file.
	 * @return string|false SVG XML string, or false on failure.
	 */
	protected function get_svg_contents( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $file_path );

		if ( false === $raw || empty( $raw ) ) {
			return false;
		}

		// Detect gzip (SVGZ) by magic bytes.
		if ( strlen( $raw ) >= 2 && "\x1f\x8b" === substr( $raw, 0, 2 ) ) {
			$decompressed = @gzdecode( $raw );
			return ( false !== $decompressed ) ? $decompressed : false;
		}

		return $raw;
	}

	/**
	 * Validate that a string is well-formed XML with an <svg> root element.
	 *
	 * @since 1.1.0
	 *
	 * @param string $content The XML string to validate.
	 * @return bool True if valid SVG.
	 */
	protected function validate_svg_xml( $content ) {
		$this->disable_xxe();
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$loaded = $dom->loadXML( $content, LIBXML_NONET );

		libxml_clear_errors();
		$this->restore_xxe();

		if ( ! $loaded || ! $dom->documentElement ) {
			return false;
		}

		// Root element must be <svg>.
		return 'svg' === strtolower( $dom->documentElement->localName );
	}

	/**
	 * Check if uploaded file is SVG.
	 *
	 * @param array $file File upload data.
	 * @return bool True if SVG file.
	 */
	protected function is_svg_file( $file ) {
		if ( isset( $file['type'] ) && 'image/svg+xml' === $file['type'] ) {
			return true;
		}

		if ( isset( $file['name'] ) ) {
			$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			return in_array( $ext, array( 'svg', 'svgz' ), true );
		}

		return false;
	}

	/**
	 * Check if uploaded file is specifically SVGZ (gzip-compressed).
	 *
	 * @since 1.1.0
	 *
	 * @param array $file File upload data.
	 * @return bool True if SVGZ file.
	 */
	protected function is_svgz_file( $file ) {
		if ( isset( $file['name'] ) ) {
			return 'svgz' === strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		}
		return false;
	}

	/**
	 * Disable XXE (XML External Entity) processing for security.
	 *
	 * @since 1.1.0
	 */
	protected function disable_xxe() {
		if ( PHP_VERSION_ID < 80000 ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.libxml_disable_entity_loaderDeprecated
			$this->previous_entity_loader_state = @libxml_disable_entity_loader( true );
		}
	}

	/**
	 * Restore XXE processing to its previous state.
	 *
	 * @since 1.1.0
	 */
	protected function restore_xxe() {
		if ( PHP_VERSION_ID < 80000 && null !== $this->previous_entity_loader_state ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.libxml_disable_entity_loaderDeprecated
			@libxml_disable_entity_loader( $this->previous_entity_loader_state );
			$this->previous_entity_loader_state = null;
		}
	}
}
