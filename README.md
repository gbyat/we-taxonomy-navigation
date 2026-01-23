# WE Taxonomy Navigation

**Contributors:** gbyat  
**Tags:** navigation, taxonomy, blocks, gutenberg, menu  
**Requires at least:** 6.0  
**Tested up to:** 6.4  
**Requires PHP:** 7.4  
**Stable tag:** 0.1.5  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that provides Gutenberg blocks for displaying taxonomy terms as navigation menus and lists, with automatic updates when terms are modified.

## Description

WE Taxonomy Navigation adds two powerful Gutenberg blocks to WordPress:

1. **Taxonomy List Block** - Display taxonomy terms as dropdowns, filterable lists, or ordered/unordered lists
2. **Taxonomy Navigation Block** - A full-featured navigation block that automatically populates with taxonomy terms, mimicking the native WordPress Navigation block with all its styling options (colors, overlay, typography, etc.)

### Key Features

* **Automatic Updates**: When taxonomy terms are modified (name, slug, hierarchy), the navigation automatically updates in the frontend
* **Hierarchical Support**: Display hierarchical taxonomies with submenus and dropdown functionality
* **Full Navigation Styling**: Access to all native WordPress Navigation block settings (colors, overlay, typography, layout)
* **Multiple Display Options**: Choose from dropdown, filterable list, or standard list formats
* **Template Part Support**: Works seamlessly in headers, footers, and other template parts
* **Cache Busting**: Intelligent cache management ensures fresh content after term updates

## Installation

### Manual Installation

1. Download the plugin zip file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Activate the plugin

### Via Git

```bash
cd wp-content/plugins
git clone https://github.com/gbyat/we-taxonomy-navigation.git
```

Then activate the plugin through the WordPress admin.

## Usage

### Taxonomy Navigation Block

1. Add a new block in the editor
2. Search for "Taxonomy Navigation" or "WE Taxonomy Navigation"
3. Configure the taxonomy settings:
   - **Taxonomy**: Select which taxonomy to display (default: category)
   - **Include Empty Terms**: Show terms with no posts
   - **Order By**: Name, slug, ID, or count
   - **Order**: Ascending or descending
   - **Show Hierarchy**: Enable hierarchical display with submenus
   - **Max Depth**: Limit the depth of hierarchical menus (0 = unlimited)
4. Use the Navigation block settings to customize:
   - Colors (text, background, overlay)
   - Typography
   - Layout and alignment
   - Responsive behavior
   - Overlay settings

The block automatically populates with taxonomy terms and updates when terms are modified.

### Taxonomy List Block

1. Add a new block in the editor
2. Search for "Taxonomy List" or "WE Taxonomy List"
3. Choose a display style:
   - **Dropdown**: Select menu with optional "Show selection" button
   - **Filterable**: Searchable list of terms
   - **List**: Standard ordered or unordered list
4. Configure taxonomy settings (same as Taxonomy Navigation)
5. For dropdowns, optionally enable auto-navigation (navigate directly on selection)

## Block Settings

### Taxonomy Navigation Block

**Taxonomy Settings:**
- Taxonomy selection (category, post_tag, custom taxonomies)
- Include/exclude empty terms
- Order by (name, slug, ID, count)
- Sort order (ASC/DESC)
- Hierarchical display toggle
- Maximum depth for hierarchical menus

**Navigation Settings:**
- All native WordPress Navigation block settings
- Color customization (text, background, overlay)
- Typography options
- Layout and alignment
- Responsive menu behavior
- Overlay settings

**Wrapper Settings:**
- Background color
- Padding and margin
- Alignment (full width, wide, etc.)

### Taxonomy List Block

**Display Options:**
- Dropdown with auto-navigation
- Filterable list with search
- Ordered/unordered list

**Taxonomy Settings:**
- Same options as Taxonomy Navigation block

## Requirements

* WordPress 6.0 or higher
* PHP 7.4 or higher
* Gutenberg editor (included in WordPress 5.0+)

## Frequently Asked Questions

### Does this work with custom taxonomies?

Yes! The blocks work with any hierarchical taxonomy registered in WordPress, including custom taxonomies.

### Will changes to term names update automatically?

Yes! When you modify a taxonomy term (name, slug, or hierarchy), the navigation automatically updates in the frontend. No need to manually refresh or resave templates.

### Can I use this in template parts?

Absolutely! The blocks work perfectly in headers, footers, and other template parts. Changes to terms automatically update across all instances.

### Does this affect other navigation blocks?

No. The plugin only modifies `we-taxonomy-navigation/taxonomy-navigation` blocks. Other navigation blocks (like footer navigation) remain completely unaffected.

### Can I add custom blocks (like search) inside the navigation?

Yes! The Taxonomy Navigation block preserves any custom blocks you add (like search blocks, icons, etc.) alongside the automatically generated taxonomy links.

## Screenshots

1. Taxonomy Navigation block in the editor
2. Taxonomy Navigation with hierarchical submenus
3. Taxonomy List block with dropdown display
4. Navigation styling options

## Changelog

### 0.1.0
* Initial release
* Taxonomy Navigation block with full navigation styling support
* Taxonomy List block with multiple display options
* Automatic term update functionality
* Hierarchical submenu support
* Template part support
* Cache management for fresh content

## Development

### Building

```bash
npm install
npm run build
```

### File Structure

```
we-taxonomy-navigation/
├── blocks/              # Built block files
├── includes/            # PHP classes
│   ├── class-plugin.php
│   ├── class-taxonomy-navigation-block.php
│   ├── class-taxonomy-list-block.php
│   └── class-navigation-renderer.php
├── src/                 # Source files
│   └── blocks/
├── scripts/             # Build scripts
└── we-taxonomy-navigation.php
```

## Support

For issues, feature requests, or contributions, please visit the [GitHub repository](https://github.com/gbyat/we-taxonomy-navigation).

## Credits

**Author:** webentwicklerin, Gabriele Laesser  
**Author URI:** https://webentwicklerin.at

## License

GPLv2 or later
