<?php

namespace Webentwicklerin\TaxonomyNavigation;

use WP_Block;

if (! defined('ABSPATH')) {
    exit;
}

final class Taxonomy_Navigation_Block
{
    /**
     * Register block type.
     *
     * @return void
     */
    public static function register()
    {
        register_block_type_from_metadata(
            PLUGIN_PATH . 'blocks/taxonomy-navigation',
            array(
                'render_callback' => array(__CLASS__, 'render'),
            )
        );
    }

    /**
     * Render callback for taxonomy navigation.
     *
     * @param array  $attributes Block attributes.
     * @param string $content Block content.
     * @param mixed  $block Block instance.
     * @return string
     */
    public static function render($attributes, $content, $block)
    {
        error_log('WE Taxonomy Nav: Taxonomy Navigation Block render called');

        // Force cache invalidation by including cache buster
        $cache_buster = get_option('we_taxonomy_nav_cache_buster', 0);

        $attributes = wp_parse_args(
            $attributes,
            array(
                'taxonomy'        => 'category',
                'includeEmpty'    => true,
                'orderBy'         => 'name',
                'order'           => 'ASC',
                'showHierarchy'   => true,
                'maxDepth'        => 0,
            )
        );

        $taxonomy = sanitize_key($attributes['taxonomy']);
        if (empty($taxonomy) || ! taxonomy_exists($taxonomy)) {
            $taxonomy = 'category';
        }

        // Get taxonomy links (ALWAYS fresh, bypasses cache)
        $links = Navigation_Renderer::build_links_for_attributes($taxonomy, $attributes);

        // Parse innerBlocks from content if available, otherwise use block object
        $inner_blocks_data = null;
        if (is_object($block) && ! empty($block->inner_blocks)) {
            $inner_blocks_data = $block->inner_blocks;
        } elseif (! empty($content)) {
            // Try to parse innerBlocks from content
            $parsed = parse_blocks($content);
            if (! empty($parsed)) {
                $inner_blocks_data = $parsed;
            }
        }

        if (! empty($inner_blocks_data)) {
            $nav_block = $inner_blocks_data[0];
            if (is_object($nav_block) && isset($nav_block->name) && $nav_block->name === 'core/navigation') {
                // Remove ref attribute to prevent loading saved navigation
                if (isset($nav_block->attrs['ref'])) {
                    unset($nav_block->attrs['ref']);
                }

                // Replace the navigation block's inner blocks with taxonomy links
                if (is_array($links)) {
                    // Helper to create WP_Block recursively
                    $create_block_recursive = function ($link_data) use (&$create_block_recursive) {
                        $block_name = $link_data['blockName'] ?? 'core/navigation-link';
                        $attrs = $link_data['attrs'] ?? array();
                        $inner_data = $link_data['innerBlocks'] ?? array();

                        // Create the WP_Block with only block data and context
                        $wp_block = new WP_Block(
                            array(
                                'blockName' => $block_name,
                                'attrs' => $attrs,
                                'innerBlocks' => $inner_data,
                                'innerHTML' => '',
                                'innerContent' => array(),
                            ),
                            array()  // Context
                        );

                        // Set inner_blocks property after creation
                        if (!empty($inner_data)) {
                            $inner_blocks = array();
                            foreach ($inner_data as $inner) {
                                $inner_blocks[] = $create_block_recursive($inner);
                            }
                            $wp_block->inner_blocks = $inner_blocks;
                        }

                        return $wp_block;
                    };

                    $taxonomy_blocks = array();
                    foreach ($links as $link) {
                        $taxonomy_blocks[] = $create_block_recursive($link);
                    }

                    // Get existing blocks from navigation, keep non-taxonomy ones
                    $existing = $nav_block->inner_blocks ?? array();
                    $user_blocks = array();
                    foreach ($existing as $existing_block) {
                        $class_name = $existing_block->attributes['className'] ?? '';
                        if (strpos($class_name, 'taxonomy-generated-') === false) {
                            $user_blocks[] = $existing_block;
                        }
                    }

                    // Combine: taxonomy blocks + user blocks
                    $nav_block->inner_blocks = array_merge($taxonomy_blocks, $user_blocks);
                }

                // Get ALL attributes from the navigation block (both attrs and attributes)
                $nav_attrs = $nav_block->attributes ?? $nav_block->attrs ?? array();

                // Remove ref attribute to prevent loading saved navigation
                if (isset($nav_attrs['ref'])) {
                    unset($nav_attrs['ref']);
                }

                // CRITICAL: Convert WP_Block objects to block definitions (arrays) for fresh rendering
                // This bypasses WordPress caching by creating a completely new block structure
                $convert_to_block_def = function ($wp_block) use (&$convert_to_block_def) {
                    if (is_object($wp_block) && $wp_block instanceof WP_Block) {
                        $def = array(
                            'blockName' => $wp_block->name,
                            'attrs' => $wp_block->attributes ?? array(),
                        );
                        if (!empty($wp_block->inner_blocks)) {
                            $def['innerBlocks'] = array_map($convert_to_block_def, $wp_block->inner_blocks);
                        }
                        return $def;
                    }
                    return $wp_block;
                };

                $fresh_inner_blocks = array_map($convert_to_block_def, $nav_block->inner_blocks ?? array());

                // Create block data with ALL original attributes (colors, overlay, etc.)
                $nav_block_data = array(
                    'blockName' => 'core/navigation',
                    'attrs' => $nav_attrs, // Use merged attributes with all styling
                    'innerBlocks' => $fresh_inner_blocks,
                    'innerHTML' => '',
                    'innerContent' => array(),
                );

                // Create fresh block instance with fresh innerBlocks
                $fresh_nav_block = new WP_Block($nav_block_data, array());

                // Render with all original navigation attributes (colors, etc.)
                $nav_html = $fresh_nav_block->render();

                // Build wrapper classes and styles from the taxonomy navigation block
                $wrapper_classes = array('wp-block-we-taxonomy-navigation-taxonomy-navigation');
                $wrapper_styles = array();

                // Get all attributes from the parent block (merging with passed attributes)
                $all_attrs = isset($block->attrs) ? $block->attrs : array();
                $all_attrs = array_merge($all_attrs, $attributes);

                // Alignment support (alignfull, alignwide, etc.)
                if (!empty($all_attrs['align'])) {
                    $wrapper_classes[] = 'align' . $all_attrs['align'];
                }

                // Background color support (only background, text color is handled by navigation)
                if (!empty($all_attrs['backgroundColor'])) {
                    $wrapper_classes[] = 'has-' . $all_attrs['backgroundColor'] . '-background-color';
                    $wrapper_classes[] = 'has-background';
                }
                if (!empty($all_attrs['style']['color']['background'])) {
                    $wrapper_styles[] = 'background-color:' . $all_attrs['style']['color']['background'];
                }

                // Helper function to convert spacing values
                $convert_spacing_value = function ($value) {
                    if (empty($value)) {
                        return '';
                    }
                    // Convert var:preset|spacing|X to var(--wp--preset--spacing--X)
                    if (preg_match('/^var:preset\|spacing\|(.+)$/', $value, $matches)) {
                        return 'var(--wp--preset--spacing--' . $matches[1] . ')';
                    }
                    // Convert var:preset|font-size|X to var(--wp--preset--font-size--X)
                    if (preg_match('/^var:preset\|font-size\|(.+)$/', $value, $matches)) {
                        return 'var(--wp--preset--font-size--' . $matches[1] . ')';
                    }
                    // Also handle old format var(preset|spacing|X)
                    if (preg_match('/var\(preset\|spacing\|([^)]+)\)/', $value, $matches)) {
                        return 'var(--wp--preset--spacing--' . $matches[1] . ')';
                    }
                    return $value;
                };

                // Spacing support (padding, margin)
                if (!empty($all_attrs['style']['spacing']['padding'])) {
                    $padding = $all_attrs['style']['spacing']['padding'];
                    if (is_string($padding)) {
                        $wrapper_styles[] = 'padding:' . $convert_spacing_value($padding);
                    } elseif (is_array($padding)) {
                        foreach (array('top', 'right', 'bottom', 'left') as $side) {
                            if (isset($padding[$side]) && $padding[$side] !== '') {
                                $wrapper_styles[] = 'padding-' . $side . ':' . $convert_spacing_value($padding[$side]);
                            }
                        }
                    }
                }
                if (!empty($all_attrs['style']['spacing']['margin'])) {
                    $margin = $all_attrs['style']['spacing']['margin'];
                    if (is_string($margin)) {
                        $wrapper_styles[] = 'margin:' . $convert_spacing_value($margin);
                    } elseif (is_array($margin)) {
                        foreach (array('top', 'right', 'bottom', 'left') as $side) {
                            if (isset($margin[$side]) && $margin[$side] !== '') {
                                $wrapper_styles[] = 'margin-' . $side . ':' . $convert_spacing_value($margin[$side]);
                            }
                        }
                    }
                }

                // Override layout margins - remove the 24px margin-block-start with !important
                $wrapper_styles[] = 'margin-block-start:0!important';
                $wrapper_styles[] = 'margin-block-end:0!important';

                // Ensure padding doesn't make the wrapper wider
                $wrapper_styles[] = 'box-sizing:border-box';

                // Custom className
                if (!empty($all_attrs['className'])) {
                    $wrapper_classes[] = $all_attrs['className'];
                }

                // Build the wrapper attributes string
                $wrapper_attributes = sprintf('class="%s"', esc_attr(implode(' ', $wrapper_classes)));
                if (!empty($wrapper_styles)) {
                    $wrapper_attributes .= sprintf(' style="%s"', esc_attr(implode(';', $wrapper_styles)));
                }

                // Add CSS to style submenus with overlay colors and add dropdown indicator
                $submenu_css = '<style>
                    .wp-block-navigation .taxonomy-generated-submenu .wp-block-navigation__submenu-container{
                        background-color:var(--wp--preset--color--base-5)!important;
                        color:var(--wp--preset--color--contrast)!important;
                    }
                    .wp-block-navigation .taxonomy-generated-submenu.has-child:not(:has(.wp-block-navigation__submenu-icon)) > .wp-block-navigation-item__content::after{
                        content: "";
                        display: inline-block;
                        width: 0;
                        height: 0;
                        margin-left: 0.5em;
                        margin-bottom: 0.125em;
                        border-left: 4px solid transparent;
                        border-right: 4px solid transparent;
                        border-top: 5px solid currentColor;
                        vertical-align: middle;
                    }
                </style>';

                // Wrap the navigation in a div with the taxonomy navigation block's attributes
                // Include cache buster to force WordPress to regenerate cached blocks
                return sprintf(
                    '<!-- cache:%s -->%s<div %s>%s</div>',
                    esc_attr($cache_buster),
                    $submenu_css,
                    $wrapper_attributes,
                    $nav_html
                );
            }
        }

        return '';
    }
}
