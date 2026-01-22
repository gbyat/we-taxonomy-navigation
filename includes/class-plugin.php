<?php

namespace Webentwicklerin\TaxonomyNavigation;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Plugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    private function __construct()
    {
        $this->includes();
        $this->hooks();
    }

    /**
     * Load required files.
     *
     * @return void
     */
    private function includes()
    {
        require_once PLUGIN_PATH . 'includes/class-taxonomy-list-block.php';
        require_once PLUGIN_PATH . 'includes/class-navigation-renderer.php';
        require_once PLUGIN_PATH . 'includes/class-taxonomy-navigation-block.php';
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function hooks()
    {
        add_action('init', array($this, 'register_blocks'), 10);
        add_action('init', array($this, 'load_textdomain'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Clear term cache when terms are updated
        add_action('created_term', array($this, 'clear_term_cache'), 10, 3);
        add_action('edited_term', array($this, 'clear_term_cache'), 10, 3);
        add_action('delete_term', array($this, 'clear_term_cache'), 10, 3);
    }

    /**
     * Register blocks.
     *
     * @return void
     */
    public function register_blocks()
    {
        Taxonomy_List_Block::register();
        Taxonomy_Navigation_Block::register();
    }

    /**
     * Load plugin translations.
     *
     * @return void
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('we-taxonomy-navigation', false, dirname(plugin_basename(PLUGIN_FILE)) . '/languages');
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes()
    {
        register_rest_route(
            'we-taxonomy-navigation/v1',
            '/terms',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_taxonomy_terms'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            )
        );
    }

    /**
     * Get taxonomy terms via REST API.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_taxonomy_terms($request)
    {
        $taxonomy = $request->get_param('taxonomy');
        if (empty($taxonomy) || ! taxonomy_exists($taxonomy)) {
            $taxonomy = 'category';
        }

        $attributes = array(
            'taxonomy'      => $taxonomy,
            'includeEmpty'  => $request->get_param('includeEmpty') === '1',
            'orderBy'       => $request->get_param('orderBy') ?: 'name',
            'order'         => $request->get_param('order') ?: 'ASC',
            'showHierarchy' => $request->get_param('showHierarchy') === '1',
            'maxDepth'      => absint($request->get_param('maxDepth')),
        );

        $links = Navigation_Renderer::build_links_for_attributes($taxonomy, $attributes);

        // Create response with no-cache headers
        $response = new \WP_REST_Response($links);
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    /**
     * Clear term cache when terms are modified.
     *
     * @param int    $term_id Term ID.
     * @param int    $tt_id Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * @return void
     */
    public function clear_term_cache($term_id, $tt_id, $taxonomy)
    {
        error_log('WE Taxonomy Nav: Clearing cache for term ' . $term_id . ' in taxonomy ' . $taxonomy);

        // Clear all term caches for this taxonomy
        wp_cache_delete('last_changed', 'terms');
        clean_term_cache($term_id, $taxonomy);

        // Clear all get_terms caches
        wp_cache_flush_group('terms');

        // Force update option to bust any persistent caches
        update_option('we_taxonomy_nav_cache_buster', time());

        // Clear all WordPress caches to force page regeneration
        wp_cache_flush();

        // Clear any object cache
        if (function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
        }

        // Find all posts/pages using taxonomy navigation blocks and mark them for update
        $this->update_posts_with_taxonomy_blocks();

        error_log('WE Taxonomy Nav: Cache cleared successfully');
    }

    /**
     * Update all posts/pages that use taxonomy navigation blocks.
     * This forces WordPress to regenerate the block HTML with fresh taxonomy data.
     *
     * @return void
     */
    private function update_posts_with_taxonomy_blocks()
    {
        global $wpdb;

        // Find all posts/pages/templates containing taxonomy navigation blocks
        $posts = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_content LIKE '%wp:we-taxonomy-navigation/taxonomy-navigation%' 
             AND (post_status = 'publish' OR post_type IN ('wp_template', 'wp_template_part'))"
        );

        if (empty($posts)) {
            return;
        }

        error_log('WE Taxonomy Nav: Updating ' . count($posts) . ' posts with taxonomy blocks');

        foreach ($posts as $post) {
            $post_obj = get_post($post->ID);

            // For templates/template-parts, regenerate and save content with fresh taxonomy links
            if ($post_obj && in_array($post_obj->post_type, array('wp_template', 'wp_template_part'))) {
                // Parse blocks
                $blocks = parse_blocks($post_obj->post_content);

                error_log('WE Taxonomy Nav: Parsed ' . count($blocks) . ' top-level blocks from template: ' . $post_obj->post_name);
                $this->debug_block_structure($blocks, 0);

                // Regenerate taxonomy navigation blocks with fresh data
                $updated = $this->regenerate_taxonomy_blocks($blocks);

                if ($updated) {
                    error_log('WE Taxonomy Nav: ✅ Blocks were updated, serializing and saving...');
                    // Serialize blocks back to content
                    $new_content = serialize_blocks($blocks);

                    // Update post content and modified time
                    $wpdb->update(
                        $wpdb->posts,
                        array(
                            'post_content' => $new_content,
                            'post_modified' => current_time('mysql'),
                            'post_modified_gmt' => current_time('mysql', 1)
                        ),
                        array('ID' => $post->ID),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );

                    error_log('WE Taxonomy Nav: ✅ Regenerated and saved template: ' . $post_obj->post_name);

                    // AGGRESSIVE cache clearing for template parts
                    $template_part_name = str_replace('wp_template_part//', '', $post_obj->post_name);

                    // Clear all possible template part caches
                    wp_cache_delete('wp_template_part_' . $template_part_name, 'themes');
                    wp_cache_delete('wp_template_part_' . $post_obj->post_name, 'themes');
                    wp_cache_delete($post_obj->post_name, 'theme_template_parts');
                    wp_cache_delete($template_part_name, 'theme_template_parts');
                    wp_cache_delete('template_part_' . $post_obj->ID, 'posts');
                    wp_cache_delete('template_part_' . $template_part_name, 'posts');

                    // Clear transients that might cache template parts
                    delete_transient('wp_template_part_' . $template_part_name);
                    delete_transient('wp_template_part_' . $post_obj->post_name);

                    // Clear block rendering cache
                    wp_cache_delete('block_' . $post_obj->ID, 'blocks');
                    wp_cache_delete('blocks_' . $post_obj->ID, 'blocks');

                    // Force WordPress to reload the template part
                    clean_post_cache($post_obj->ID);

                    error_log('WE Taxonomy Nav: Cleared all caches for template part: ' . $template_part_name);
                } else {
                    error_log('WE Taxonomy Nav: ❌ No blocks were updated (block not found?)');
                }
            } else {
                // For regular posts/pages, just update modified time
                $wpdb->update(
                    $wpdb->posts,
                    array('post_modified' => current_time('mysql'), 'post_modified_gmt' => current_time('mysql', 1)),
                    array('ID' => $post->ID),
                    array('%s', '%s'),
                    array('%d')
                );
            }

            // Clear post cache
            clean_post_cache($post->ID);
        }

        // Clear global template cache
        wp_cache_delete('get_block_templates', 'themes');
        wp_cache_delete('get_block_template_parts', 'themes');

        // If object cache is active, flush it completely
        if (wp_using_ext_object_cache()) {
            error_log('WE Taxonomy Nav: Object cache detected, flushing...');
            wp_cache_flush();
        }

        // Force update option to bust any persistent caches
        update_option('we_taxonomy_nav_template_cache_buster', time());

        error_log('WE Taxonomy Nav: All caches cleared, template cache buster updated');
    }

    /**
     * Debug: Log block structure recursively.
     *
     * @param array $blocks Block array.
     * @param int   $depth Current depth.
     * @return void
     */
    private function debug_block_structure($blocks, $depth = 0)
    {
        $indent = str_repeat('  ', $depth);
        foreach ($blocks as $block) {
            $block_name = $block['blockName'] ?? '(no name)';
            error_log($indent . '- Block: ' . $block_name);

            if (isset($block['innerBlocks']) && is_array($block['innerBlocks']) && !empty($block['innerBlocks'])) {
                $this->debug_block_structure($block['innerBlocks'], $depth + 1);
            }
        }
    }

    /**
     * Recursively regenerate taxonomy navigation blocks with fresh data.
     *
     * @param array &$blocks Block array (passed by reference).
     * @return bool Whether any blocks were updated.
     */
    private function regenerate_taxonomy_blocks(&$blocks)
    {
        $updated = false;

        foreach ($blocks as &$block) {
            $block_name = $block['blockName'] ?? '(no name)';

            // Check if this is a taxonomy navigation block
            if (isset($block['blockName']) && $block['blockName'] === 'we-taxonomy-navigation/taxonomy-navigation') {
                error_log('WE Taxonomy Nav: ✅ Found taxonomy navigation block, regenerating...');

                // Get block attributes
                $attrs = $block['attrs'] ?? array();
                $taxonomy = $attrs['taxonomy'] ?? 'category';

                // Get fresh taxonomy links
                $fresh_links = Navigation_Renderer::build_links_for_attributes($taxonomy, $attrs);

                // Find the inner core/navigation block
                if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    foreach ($block['innerBlocks'] as &$inner_block) {
                        if (isset($inner_block['blockName']) && $inner_block['blockName'] === 'core/navigation') {
                            error_log('WE Taxonomy Nav: Found inner navigation block, updating links...');

                            // Get existing blocks, keep non-taxonomy ones
                            $existing = $inner_block['innerBlocks'] ?? array();
                            $user_blocks = array();

                            foreach ($existing as $existing_block) {
                                $class_name = $existing_block['attrs']['className'] ?? '';
                                if (strpos($class_name, 'taxonomy-generated-') === false) {
                                    $user_blocks[] = $existing_block;
                                }
                            }

                            // Combine: fresh taxonomy links + user blocks
                            $inner_block['innerBlocks'] = array_merge($fresh_links, $user_blocks);

                            error_log('WE Taxonomy Nav: Updated with ' . count($fresh_links) . ' taxonomy links + ' . count($user_blocks) . ' user blocks');
                            $updated = true;
                        }
                    }
                }
            }

            // Recursively process nested blocks
            if (isset($block['innerBlocks']) && is_array($block['innerBlocks']) && !empty($block['innerBlocks'])) {
                if ($this->regenerate_taxonomy_blocks($block['innerBlocks'])) {
                    $updated = true;
                }
            }
        }

        return $updated;
    }
}
