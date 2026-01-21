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
     * @return array
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

        return $links;
    }
}
