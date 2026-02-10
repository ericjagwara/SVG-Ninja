# SVG Ninja

WordPress plugin for secure SVG uploads with automatic metadata cleaning and zero frontend footprint.

## Features

- **SVG/SVGZ Upload Support** - Enable vector file uploads in WordPress
- **Metadata Stripping** - Remove bloated tags from Adobe Illustrator, Figma, and Sketch
- **ViewBox Auto-Correction** - Ensure proper responsive scaling
- **Media Library Fixes** - Proper thumbnail rendering for SVG files
- **Admin-Only Uploads** - Configurable upload restrictions
- **Zero Frontend Footprint** - No CSS or JS added to public pages

## Installation

1. Upload the `svg-ninja` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings at Settings > SVG Ninja

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher

## Configuration

### Admin-Only Uploads

By default, only administrators can upload SVG files. This can be changed in Settings > SVG Ninja or customized using the `svg_ninja_upload_capability` filter:

```php
add_filter( 'svg_ninja_upload_capability', function( $capability ) {
    return 'edit_posts'; // Allow editors to upload SVGs
} );
```

### Metadata Stripping

Metadata stripping is enabled by default and removes:
- Adobe Illustrator proprietary tags
- Figma editor data
- Sketch application metadata
- XML comments

This can be toggled in Settings > SVG Ninja.

## What It Does

1. **Enables SVG Mime Type** - Adds `image/svg+xml` support
2. **Validates XML Structure** - Ensures files are valid SVG documents
3. **Strips Metadata** - Removes design application bloat
4. **Corrects ViewBox** - Adds missing viewBox attributes
5. **Handles SVGZ** - Automatic compression/decompression
6. **Fixes Media Library** - Proper thumbnail display

## What It Doesn't Do

- Does not modify SVG content beyond metadata removal
- Does not add any frontend resources (CSS/JS)
- Does not affect existing uploaded SVG files

## Security

- XML External Entity (XXE) attack protection
- Admin-only upload capability by default
- Valid XML structure validation
- LIBXML_NONET flag for network isolation

## Development

### File Structure

```
svg-ninja/
├── includes/
│   ├── class-svg-ninja-core.php      # Main plugin logic
│   ├── class-svg-ninja-processor.php # SVG processing
│   └── class-svg-ninja-admin.php     # Admin interface
├── languages/                         # Translation files
├── svg-ninja.php                      # Plugin bootstrap
└── readme.txt                         # WordPress.org readme
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

GPL v2 or later

## Credits

Created by Eric Jagwara

## Support

For issues and feature requests, please use the plugin support forum or GitHub issues.
