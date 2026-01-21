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

        // Get taxonomy links
        $links = Navigation_Renderer::build_links_for_attributes($taxonomy, $attributes);

        if (is_object($block) && ! empty($block->inner_blocks)) {
            $nav_block = $block->inner_blocks[0];
            if (isset($nav_block->name) && $nav_block->name === 'core/navigation') {
                // Remove ref attribute to prevent loading saved navigation
                if (isset($nav_block->attrs['ref'])) {
                    unset($nav_block->attrs['ref']);
                }

                // Replace inner blocks with taxonomy links
                if (is_array($links)) {
                    $link_blocks = array();
                    foreach ($links as $link) {
                        $link_block = new WP_Block(
                            array(
                                'blockName'    => 'core/navigation-link',
                                'attrs'        => isset($link['attrs']) ? $link['attrs'] : array(),
                                'innerBlocks'  => isset($link['innerBlocks']) ? $link['innerBlocks'] : array(),
                                'innerHTML'    => '',
                                'innerContent' => array(),
                            )
                        );
                        $link_blocks[] = $link_block;
                    }
                    $nav_block->inner_blocks = $link_blocks;
                }

                // Render the navigation block with its original attributes and styles
                $nav_html = $nav_block->render();

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

                // Wrap the navigation in a div with the taxonomy navigation block's attributes
                return sprintf(
                    '<div %s>%s</div>',
                    $wrapper_attributes,
                    $nav_html
                );
            }
        }

        return '';
    }
}
