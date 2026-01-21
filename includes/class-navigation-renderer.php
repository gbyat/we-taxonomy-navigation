<?php

namespace Webentwicklerin\TaxonomyNavigation;

if (! defined('ABSPATH')) {
    exit;
}

final class Navigation_Renderer
{
    /**
     * Filter navigation block output for taxonomy-based menus.
     *
     * @param string $block_content Block content.
     * @param array  $block Block data.
     * @return string
     */
    /**
     * Build navigation link blocks.
     *
     * @param string $taxonomy Taxonomy.
     * @param array  $attrs Attributes.
     * @return array
     */
    public static function build_links_for_attributes($taxonomy, $attrs)
    {
        $include_empty = ! empty($attrs['includeEmpty']);
        $order_by      = isset($attrs['orderBy']) ? sanitize_key($attrs['orderBy']) : 'name';
        $order         = isset($attrs['order']) && 'DESC' === strtoupper($attrs['order']) ? 'DESC' : 'ASC';
        $hierarchy     = ! empty($attrs['showHierarchy']) && is_taxonomy_hierarchical($taxonomy);
        $max_depth     = isset($attrs['maxDepth']) ? absint($attrs['maxDepth']) : 0;

        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => $include_empty ? 0 : 1,
            'orderby'    => $order_by,
            'order'      => $order,
        );

        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        if (! $hierarchy) {
            return self::build_flat_links($terms, $max_depth);
        }

        return self::build_hierarchical_links($taxonomy, $terms, $max_depth);
    }

    /**
     * Build flat navigation links.
     *
     * @param array $terms Terms.
     * @param int   $max_depth Max depth (0 = unlimited).
     * @return array
     */
    private static function build_flat_links($terms, $max_depth)
    {
        $blocks = array();
        foreach ($terms as $term) {
            $depth = count(get_ancestors($term->term_id, $term->taxonomy));
            if (0 !== $max_depth && $depth >= $max_depth) {
                continue;
            }
            $blocks[] = self::build_link_block($term);
        }

        return $blocks;
    }

    /**
     * Build hierarchical navigation links.
     *
     * @param string $taxonomy Taxonomy.
     * @param array  $terms Terms.
     * @param int    $max_depth Max depth (0 = unlimited).
     * @return array
     */
    private static function build_hierarchical_links($taxonomy, $terms, $max_depth)
    {
        $map = array();
        foreach ($terms as $term) {
            $parent_id = (int) $term->parent;
            if (! isset($map[$parent_id])) {
                $map[$parent_id] = array();
            }
            $map[$parent_id][] = $term;
        }

        return self::build_links_from_map($taxonomy, $map, 0, 0, $max_depth);
    }

    /**
     * Build link blocks from parent map.
     *
     * @param string $taxonomy Taxonomy.
     * @param array  $map Parent map.
     * @param int    $parent Parent id.
     * @param int    $level Current level.
     * @param int    $max_depth Max depth (0 = unlimited).
     * @return array
     */
    private static function build_links_from_map($taxonomy, $map, $parent, $level, $max_depth)
    {
        if (empty($map[$parent])) {
            return array();
        }

        $blocks = array();
        foreach ($map[$parent] as $term) {
            if (0 !== $max_depth && $level >= $max_depth) {
                continue;
            }
            $children = self::build_links_from_map($taxonomy, $map, $term->term_id, $level + 1, $max_depth);
            $block    = self::build_link_block($term);
            if (! empty($children)) {
                $block['innerBlocks']  = $children;
                $block['innerContent'] = array_fill(0, count($children) + 1, '');
            }
            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Build a navigation-link block for a term.
     *
     * @param \WP_Term $term Term object.
     * @return array
     */
    private static function build_link_block($term)
    {
        $url = get_term_link($term);
        if (is_wp_error($url)) {
            $url = '';
        }

        return array(
            'blockName'    => 'core/navigation-link',
            'attrs'        => array(
                'label' => $term->name,
                'url'   => $url,
                'kind'  => 'taxonomy',
                'type'  => $term->taxonomy,
                'id'    => $term->term_id,
            ),
            'innerBlocks'  => array(),
            'innerHTML'    => '',
            'innerContent' => array(),
        );
    }

    /**
     * Build cache key for attributes.
     *
     * @param array $attrs Attributes.
     * @return string
     */
}
