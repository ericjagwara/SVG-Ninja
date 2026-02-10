=== SVG Ninja ===
Contributors: ericjagwara
Tags: svg, upload, media, vector, images
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload vectors. Strip junk. Stay fast. Secure SVG uploads with metadata cleaning and zero frontend footprint.

== Description ==

SVG Ninja enables SVG and SVGZ file uploads in WordPress with automatic metadata stripping and viewBox correction.

= Features =

* SVG and SVGZ file upload support
* Automatic metadata removal from Adobe, Figma, and Sketch
* ViewBox auto-correction for responsive scaling
* Media Library thumbnail rendering fixes
* Admin-only upload restriction (configurable)
* Zero frontend footprint - no CSS or JS added to public pages

= What It Does =

1. Enables SVG/SVGZ mime types
2. Strips bloated metadata from design applications
3. Auto-corrects missing viewBox attributes
4. Fixes broken thumbnails in Media Library
5. Validates XML structure
6. Handles SVGZ compression/decompression

== Installation ==

1. Upload the `svg-ninja` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings at Settings > SVG Ninja

== Frequently Asked Questions ==

= Can I restrict SVG uploads to certain users? =

Yes, by default only administrators can upload SVG files. This can be configured in Settings > SVG Ninja or customized using the `svg_ninja_upload_capability` filter.

= Does this work with SVGZ files? =

Yes, the plugin handles both SVG and SVGZ (compressed SVG) files automatically.

= Will this slow down my site? =

No, processing only happens during upload in the admin dashboard. No frontend resources are loaded.

== Changelog ==

= 1.1.0 =
* Fixed SVG mime type detection
* Improved error handling
* Enhanced SVGZ support
* Added proper XXE protection for PHP 8.0+

= 1.0.0 =
* Initial release
* SVG upload support
* Metadata stripping
* ViewBox auto-correction
* Media Library thumbnail fixes
* Admin-only upload restriction
