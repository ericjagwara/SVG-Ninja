<?php
/**
 * SVG Processor class.
 *
 * @package SVG_Ninja
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG Ninja Processor Class.
 *
 * Handles metadata stripping and ViewBox correction.
 *
 * @since 1.0.0
 */
class SVG_Ninja_Processor {

	/**
	 * Previous state of the entity loader for XXE protection.
	 *
	 * @since 1.1.0
	 * @var bool|null
	 */
	protected $previous_entity_loader_state = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Initialize processor.
	}

	/**
	 * Strip bloated metadata from design applications.
	 *
	 * @since 1.0.0
	 *
	 * @param string $svg_content SVG content.
	 * @return string|false Cleaned SVG content, or false on parse failure.
	 */
	public function strip_metadata( $svg_content ) {
		$this->disable_xxe();
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;

		if ( ! $dom->loadXML( $svg_content, LIBXML_NONET ) ) {
			libxml_clear_errors();
			$this->restore_xxe();
			return false;
		}

		$xpath = new DOMXPath( $dom );

		// Register common namespaces used by design tools.
		$namespaces = array(
			'svg'    => 'http://www.w3.org/2000/svg',
			'adobe'  => 'http://ns.adobe.com/AdobeSVGViewerExtensions/3.0/',
			'i'      => 'http://ns.adobe.com/AdobeIllustrator/10.0/',
			'a'      => 'http://ns.adobe.com/AdobeSVGViewerExtensions/3.0/',
			'graph'  => 'http://ns.adobe.com/Graphs/1.0/',
			'x'      => 'http://ns.adobe.com/Extensibility/1.0/',
			'sketch' => 'http://www.bohemiancoding.com/sketch/ns',
			'figma'  => 'https://www.figma.com/figma/ns',
		);

		foreach ( $namespaces as $prefix => $uri ) {
			$xpath->registerNamespace( $prefix, $uri );
		}

		// Elements to remove.
		$junk_elements = array(
			'//adobe:*',
			'//i:pgf',
			'//i:pgfRef',
			'//a:*',
			'//graph:*',
			'//x:*',
			'//sketch:*',
			'//figma:*',
			'//svg:metadata',
			'//metadata',
		);

		foreach ( $junk_elements as $query ) {
			$nodes = $xpath->query( $query );
			if ( $nodes ) {
				foreach ( $nodes as $node ) {
					if ( $node->parentNode ) {
						$node->parentNode->removeChild( $node );
					}
				}
			}
		}

		// Remove common junk attributes from the root <svg> element.
		$junk_attributes = array(
			'xmlns:adobe',
			'xmlns:i',
			'xmlns:a',
			'xmlns:graph',
			'xmlns:x',
			'xmlns:sketch',
			'xmlns:figma',
			'enable-background',
			'xml:space',
		);

		$svg_element = $dom->documentElement;
		if ( $svg_element ) {
			foreach ( $junk_attributes as $attr ) {
				if ( $svg_element->hasAttribute( $attr ) ) {
					$svg_element->removeAttribute( $attr );
				}
			}
		}

		// Remove XML comments.
		$this->remove_comments( $dom );

		libxml_clear_errors();
		$this->restore_xxe();

		$cleaned = $dom->saveXML();
		return ( false !== $cleaned && ! empty( $cleaned ) ) ? $cleaned : false;
	}

	/**
	 * Remove XML comments from DOM.
	 *
	 * @param DOMDocument $dom DOM document.
	 */
	protected function remove_comments( DOMDocument $dom ) {
		$xpath    = new DOMXPath( $dom );
		$comments = $xpath->query( '//comment()' );

		if ( $comments ) {
			foreach ( $comments as $comment ) {
				if ( $comment->parentNode ) {
					$comment->parentNode->removeChild( $comment );
				}
			}
		}
	}

	/**
	 * Ensure SVG has a viewBox attribute for proper responsive scaling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $svg_content SVG content.
	 * @return string SVG with viewBox (or original on parse failure).
	 */
	public function ensure_viewbox( $svg_content ) {
		$this->disable_xxe();
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();

		if ( ! $dom->loadXML( $svg_content, LIBXML_NONET ) ) {
			libxml_clear_errors();
			$this->restore_xxe();
			return $svg_content;
		}

		$svg = $dom->documentElement;

		if ( $svg && ! $svg->hasAttribute( 'viewBox' ) ) {
			$width  = $svg->getAttribute( 'width' );
			$height = $svg->getAttribute( 'height' );

			if ( $width && $height ) {
				$width_num  = (float) preg_replace( '/[^0-9.]/', '', $width );
				$height_num = (float) preg_replace( '/[^0-9.]/', '', $height );

				if ( $width_num > 0 && $height_num > 0 ) {
					$w = rtrim( rtrim( number_format( $width_num, 2, '.', '' ), '0' ), '.' );
					$h = rtrim( rtrim( number_format( $height_num, 2, '.', '' ), '0' ), '.' );
					$svg->setAttribute( 'viewBox', "0 0 $w $h" );
				}
			}
		}

		libxml_clear_errors();
		$this->restore_xxe();

		$result = $dom->saveXML();
		return ( false !== $result ) ? $result : $svg_content;
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
	 * Restore entity loading to its previous state.
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
