<?php

namespace Webentwicklerin\TaxonomyNavigation;

if (! defined('ABSPATH')) {
    exit;
}

final class Taxonomy_List_Block
{
    /**
     * Register block type.
     *
     * @return void
     */
    public static function register()
    {
        register_block_type_from_metadata(
            PLUGIN_PATH . 'blocks/taxonomy-list',
            array(
                'render_callback' => array(__CLASS__, 'render'),
            )
        );
    }

    /**
     * Render callback.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function render($attributes)
    {
        $attributes = wp_parse_args(
            $attributes,
            array(
                'taxonomy'       => 'category',
                'displayStyle'   => 'ul',
                'orderBy'        => 'name',
                'order'          => 'ASC',
                'includeEmpty'   => false,
                'showCount'      => false,
                'showHierarchy'  => true,
                'maxDepth'       => 0,
                'dropdownTitle'  => '',
                'dropdownLink'   => '',
                'showDropdownButton' => true,
                'enableFilter'   => false,
            )
        );

        if (empty($attributes['taxonomy']) || ! taxonomy_exists($attributes['taxonomy'])) {
            return '';
        }

        $taxonomy = $attributes['taxonomy'];
        $max_depth = absint($attributes['maxDepth']);

        $args = array(
            'taxonomy'   => $taxonomy,
            'orderby'    => sanitize_key($attributes['orderBy']),
            'order'      => 'DESC' === strtoupper($attributes['order']) ? 'DESC' : 'ASC',
            'hide_empty' => empty($attributes['includeEmpty']),
        );

        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $wrapper_attributes = get_block_wrapper_attributes(
            array(
                'data-taxonomy' => esc_attr($taxonomy),
            )
        );

        switch ($attributes['displayStyle']) {
            case 'dropdown':
                return self::render_dropdown($terms, $taxonomy, $attributes, $wrapper_attributes);
            case 'filterable':
                return self::render_filterable_list($terms, $taxonomy, $attributes, $wrapper_attributes);
            case 'ol':
            case 'ul':
            default:
                return self::render_list($terms, $taxonomy, $attributes, $wrapper_attributes);
        }
    }

    /**
     * Render list template.
     *
     * @param array  $terms Terms.
     * @param string $taxonomy Taxonomy.
     * @param array  $attributes Attributes.
     * @param string $wrapper_attributes Wrapper attributes.
     * @return string
     */
    private static function render_list($terms, $taxonomy, $attributes, $wrapper_attributes)
    {
        $tag        = 'ol' === $attributes['displayStyle'] ? 'ol' : 'ul';
        $max_depth  = absint($attributes['maxDepth']);
        $hierarchy  = ! empty($attributes['showHierarchy']) && is_taxonomy_hierarchical($taxonomy);
        $show_count = ! empty($attributes['showCount']);
        $include_empty = ! empty($attributes['includeEmpty']);
        $order_by = sanitize_key($attributes['orderBy']);
        $order    = 'DESC' === strtoupper($attributes['order']) ? 'DESC' : 'ASC';
        $show_button = isset($attributes['showDropdownButton']) ? (bool) $attributes['showDropdownButton'] : true;
        $show_button = isset($attributes['showDropdownButton']) ? (bool) $attributes['showDropdownButton'] : true;

        $output = $hierarchy
            ? self::render_terms_hierarchical_list($taxonomy, $show_count, $tag, $max_depth, $include_empty, $order_by, $order)
            : self::render_terms_flat_list($terms, $show_count, $tag, $max_depth);

        return sprintf(
            '<div %s>%s</div>',
            $wrapper_attributes,
            $output
        );
    }

    /**
     * Render filterable list template.
     *
     * @param array  $terms Terms.
     * @param string $taxonomy Taxonomy.
     * @param array  $attributes Attributes.
     * @param string $wrapper_attributes Wrapper attributes.
     * @return string
     */
    private static function render_filterable_list($terms, $taxonomy, $attributes, $wrapper_attributes)
    {
        $tag        = 'ul';
        $max_depth  = absint($attributes['maxDepth']);
        $hierarchy  = ! empty($attributes['showHierarchy']) && is_taxonomy_hierarchical($taxonomy);
        $show_count = ! empty($attributes['showCount']);
        $include_empty = ! empty($attributes['includeEmpty']);
        $order_by = sanitize_key($attributes['orderBy']);
        $order    = 'DESC' === strtoupper($attributes['order']) ? 'DESC' : 'ASC';

        $list = $hierarchy
            ? self::render_terms_hierarchical_list($taxonomy, $show_count, $tag, $max_depth, $include_empty, $order_by, $order)
            : self::render_terms_flat_list($terms, $show_count, $tag, $max_depth);

        $filter_label = esc_html__('Filter terms', 'we-taxonomy-navigation');
        $placeholder  = esc_attr__('Type to filter terms...', 'we-taxonomy-navigation');
        $filter_input = '';
        if (! empty($attributes['enableFilter'])) {
            $filter_input = sprintf(
                '<label class="screen-reader-text">%1$s</label><input type="text" class="we-taxonomy-filter" placeholder="%2$s" />',
                $filter_label,
                $placeholder
            );
        }

        return sprintf(
            '<div %1$s class="we-taxonomy-filterable">
				%2$s
				<div class="we-taxonomy-list">%3$s</div>
			</div>',
            $wrapper_attributes,
            $filter_input,
            $list
        );
    }

    /**
     * Render dropdown template.
     *
     * @param array  $terms Terms.
     * @param string $taxonomy Taxonomy.
     * @param array  $attributes Attributes.
     * @param string $wrapper_attributes Wrapper attributes.
     * @return string
     */
    private static function render_dropdown($terms, $taxonomy, $attributes, $wrapper_attributes)
    {
        $show_count = ! empty($attributes['showCount']);
        $hierarchy  = ! empty($attributes['showHierarchy']) && is_taxonomy_hierarchical($taxonomy);
        $max_depth  = absint($attributes['maxDepth']);
        $title      = $attributes['dropdownTitle'] ? $attributes['dropdownTitle'] : __('Select a term', 'we-taxonomy-navigation');
        $link       = $attributes['dropdownLink'];
        $include_empty = ! empty($attributes['includeEmpty']);
        $order_by = sanitize_key($attributes['orderBy']);
        $order    = 'DESC' === strtoupper($attributes['order']) ? 'DESC' : 'ASC';
        $show_button = isset($attributes['showDropdownButton']) ? (bool) $attributes['showDropdownButton'] : true;

        $options = $hierarchy
            ? self::render_terms_hierarchical_dropdown($taxonomy, $show_count, $max_depth, $include_empty, $order_by, $order)
            : self::render_terms_flat_dropdown($terms, $show_count, $max_depth);

        $first_option = $link
            ? sprintf('<option value="%s">-- %s --</option>', esc_url($link), esc_html($title))
            : sprintf('<option value="" aria-hidden="true">-- %s --</option>', esc_html($title));

        $button = '';
        if ($show_button) {
            $button = sprintf(
                '<button type="button" class="we-taxonomy-select-button">%s</button>',
                esc_html__('Show selection', 'we-taxonomy-navigation')
            );
        }

        $auto_attr = $show_button ? '0' : '1';

        return sprintf(
            '<div %1$s class="we-taxonomy-dropdown">
				<label class="screen-reader-text">%2$s</label>
				<select class="we-taxonomy-select" data-auto-navigate="%6$s" aria-label="%2$s">%3$s%4$s</select>
				%5$s
			</div>',
            $wrapper_attributes,
            esc_html($title),
            $first_option,
            $options,
            $button,
            esc_attr($auto_attr)
        );
    }

    /**
     * Render hierarchical list via wp_list_categories.
     *
     * @param string $taxonomy Taxonomy.
     * @param bool   $show_count Show count.
     * @param string $tag Tag.
     * @param int    $max_depth Max depth (0 = unlimited).
     * @return string
     */
    private static function render_terms_hierarchical_list($taxonomy, $show_count, $tag, $max_depth, $include_empty, $order_by, $order)
    {
        $args = array(
            'taxonomy'     => $taxonomy,
            'title_li'     => '',
            'show_count'   => $show_count ? 1 : 0,
            'hide_empty'   => $include_empty ? 0 : 1,
            'echo'         => 0,
            'depth'        => 0 === $max_depth ? 0 : $max_depth,
            'style'        => 'list',
            'orderby'      => $order_by,
            'order'        => $order,
        );

        $list = wp_list_categories($args);
        if (empty($list)) {
            return '';
        }

        return sprintf('<%1$s>%2$s</%1$s>', esc_html($tag), $list);
    }

    /**
     * Render flat list.
     *
     * @param array $terms Terms.
     * @param bool  $show_count Show count.
     * @param string $tag Tag.
     * @param int   $max_depth Max depth (0 = unlimited).
     * @return string
     */
    private static function render_terms_flat_list($terms, $show_count, $tag, $max_depth)
    {
        $output = '<' . $tag . '>';
        foreach ($terms as $term) {
            $depth = count(get_ancestors($term->term_id, $term->taxonomy));
            if (0 !== $max_depth && $depth >= $max_depth) {
                continue;
            }
            $count = $show_count ? sprintf(' (%d)', absint($term->count)) : '';
            $output .= sprintf(
                '<li><a href="%s">%s%s</a></li>',
                esc_url(get_term_link($term)),
                esc_html($term->name),
                esc_html($count)
            );
        }
        $output .= '</' . $tag . '>';

        return $output;
    }

    /**
     * Render flat dropdown options.
     *
     * @param array $terms Terms.
     * @param bool  $show_count Show count.
     * @param int   $max_depth Max depth (0 = unlimited).
     * @return string
     */
    private static function render_terms_flat_dropdown($terms, $show_count, $max_depth)
    {
        $output = '';
        foreach ($terms as $term) {
            $depth = count(get_ancestors($term->term_id, $term->taxonomy));
            if (0 !== $max_depth && $depth >= $max_depth) {
                continue;
            }
            $count = $show_count ? sprintf(' (%d)', absint($term->count)) : '';
            $output .= sprintf(
                '<option value="%s">%s%s</option>',
                esc_url(get_term_link($term)),
                esc_html($term->name),
                esc_html($count)
            );
        }

        return $output;
    }

    /**
     * Render hierarchical dropdown options.
     *
     * @param string $taxonomy Taxonomy.
     * @param bool   $show_count Show count.
     * @param int    $max_depth Max depth (0 = unlimited).
     * @param int    $parent Parent term id.
     * @param int    $level Current level.
     * @return string
     */
    private static function render_terms_hierarchical_dropdown($taxonomy, $show_count, $max_depth, $include_empty, $order_by, $order, $parent = 0, $level = 0)
    {
        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => $include_empty ? 0 : 1,
            'parent'     => $parent,
            'orderby'    => $order_by,
            'order'      => $order,
        );

        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $output = '';
        foreach ($terms as $term) {
            if (0 !== $max_depth && $level >= $max_depth) {
                continue;
            }
            $count  = $show_count ? sprintf(' (%d)', absint($term->count)) : '';
            $indent = str_repeat('&#8212; ', $level);
            $output .= sprintf(
                '<option value="%s">%s%s%s</option>',
                esc_url(get_term_link($term)),
                $indent,
                esc_html($term->name),
                esc_html($count)
            );
            $output .= self::render_terms_hierarchical_dropdown($taxonomy, $show_count, $max_depth, $include_empty, $order_by, $order, $term->term_id, $level + 1);
        }

        return $output;
    }
}
