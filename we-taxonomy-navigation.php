<?php

declare(strict_types=1);

namespace Webentwicklerin\TaxonomyNavigation {
    /**
     * Plugin Name: WE Taxonomy Navigation
     * Plugin URI: https://github.com/gbyat/we-taxonomy-navigation
     * Description: Provides taxonomy list blocks and a navigation variation that auto-populates terms.
     * Version: 0.1.4
     * Requires at least: 6.0
     * Tested up to: 6.4
     * Author: webentwicklerin, Gabriele Laesser
     * Author URI: https://webentwicklerin.at
     * License: GPL v2 or later
     * Text Domain: we-taxonomy-navigation
     * Domain Path: /languages
     * Requires PHP: 7.4
     */

    // Prevent direct access
    if (!defined('ABSPATH')) {
        exit;
    }

    // Plugin constants (defined in global scope despite namespace)
    define('WE_TAXONOMY_NAV_VERSION', '0.1.4');
    define('WE_TAXONOMY_NAV_PLUGIN_FILE', __FILE__);
    define('WE_TAXONOMY_NAV_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('WE_TAXONOMY_NAV_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('WE_TAXONOMY_NAV_GITHUB_REPO', 'gbyat/we-taxonomy-navigation');

    require_once WE_TAXONOMY_NAV_PLUGIN_DIR . 'includes/class-plugin.php';

    Plugin::get_instance();
}

// GitHub Update System (in global namespace, after plugin initialization)
namespace {
    class WE_Taxonomy_Nav_GitHub_Updater
    {
        private $file;
        private $plugin;
        private $basename;
        private $active;
        private $github_response;
        private $access_token;
        private $plugin_headers;

        public function __construct($file)
        {
            add_action('admin_init', array($this, 'set_plugin_properties'));
            add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
            add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
            add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
            add_action('upgrader_process_complete', array($this, 'purge'), 10, 2);
            add_action('admin_init', array($this, 'get_github_response'));

            $this->file = $file;
            $this->basename = plugin_basename($this->file);
            $this->active = is_plugin_active($this->basename);
        }

        public function set_plugin_properties()
        {
            $this->plugin = get_plugin_data($this->file);
            $this->plugin_headers = array(
                'Name' => $this->plugin['Name'],
                'Version' => $this->plugin['Version'],
                'TextDomain' => $this->plugin['TextDomain'],
            );
        }

        public function get_github_response()
        {
            $this->access_token = get_option('we_taxonomy_nav_github_token');
            if (!$this->access_token) {
                return;
            }

            $args = array(
                'headers' => array(
                    'Authorization' => 'token ' . $this->access_token,
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            );

            $response = wp_remote_get('https://api.github.com/repos/' . WE_TAXONOMY_NAV_GITHUB_REPO . '/releases/latest', $args);
            if (is_wp_error($response)) {
                return;
            }

            $this->github_response = json_decode(wp_remote_retrieve_body($response));
        }

        public function modify_transient($transient)
        {
            if (!$this->github_response || !$this->active) {
                return $transient;
            }

            $current_version = $this->plugin['Version'];
            $new_version = ltrim($this->github_response->tag_name, 'v');

            if (version_compare($current_version, $new_version, '>=')) {
                return $transient;
            }

            $plugin_data = array(
                'slug' => $this->basename,
                'new_version' => $new_version,
                'url' => $this->plugin['PluginURI'],
                'package' => $this->github_response->zipball_url,
            );

            $transient->response[$this->basename] = (object) $plugin_data;
            return $transient;
        }

        public function plugin_popup($result, $action, $args)
        {
            if ($action !== 'plugin_information') {
                return $result;
            }

            if (!isset($args->slug) || $args->slug !== $this->basename) {
                return $result;
            }

            if (!$this->github_response) {
                return $result;
            }

            $changelog = '';
            $changelog_file = WE_TAXONOMY_NAV_PLUGIN_DIR . 'CHANGELOG.md';
            if (file_exists($changelog_file)) {
                $changelog_content = file_get_contents($changelog_file);
                if ($changelog_content) {
                    $changelog = $this->format_changelog_for_popup($changelog_content);
                }
            }

            if (empty($changelog)) {
                $changelog = $this->github_response->body ?: esc_html__('No changelog available.', 'we-taxonomy-navigation');
            }

            $description = $this->plugin['Description'];
            $readme_file = WE_TAXONOMY_NAV_PLUGIN_DIR . 'README.md';
            if (file_exists($readme_file)) {
                $readme_content = file_get_contents($readme_file);
                if ($readme_content) {
                    $description = $this->format_readme_for_popup($readme_content);
                }
            }

            $plugin_data = array(
                'name' => $this->plugin['Name'],
                'slug' => $this->basename,
                'version' => $this->github_response->tag_name,
                'author' => $this->plugin['AuthorName'],
                'author_profile' => $this->plugin['AuthorURI'],
                'last_updated' => $this->github_response->published_at,
                'homepage' => $this->plugin['PluginURI'],
                'short_description' => $this->plugin['Description'],
                'sections' => array(
                    'description' => $description,
                    'changelog' => $changelog,
                    'installation' => $this->get_installation_instructions(),
                ),
                'download_link' => $this->github_response->zipball_url,
                'requires' => '6.0',
                'tested' => '6.4',
                'requires_php' => '7.4',
            );

            return (object) $plugin_data;
        }

        private function format_changelog_for_popup($changelog_content)
        {
            $changelog = $changelog_content;
            $changelog = preg_replace('/^### (.*)$/m', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/^## (.*)$/m', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/^# (.*)$/m', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/^- (.*)$/m', '<li>$1</li>', $changelog);
            $changelog = preg_replace('/^\* (.*)$/m', '<li>$1</li>', $changelog);
            $changelog = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog);
            $changelog = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/`(.*?)`/', '<code>$1</code>', $changelog);
            $changelog = preg_replace('/.*changed files.*\n?/i', '', $changelog);
            $changelog = preg_replace('/\n{3,}/', "\n\n", $changelog);
            $changelog = preg_replace('/(<\/ul>)\n/', "$1\n\n", $changelog);
            $changelog = nl2br($changelog);
            return $changelog;
        }

        private function format_readme_for_popup($readme_content)
        {
            $readme = $readme_content;
            $readme = preg_replace('/^### (.*)$/m', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/^## (.*)$/m', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/^# (.*)$/m', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/^- (.*)$/m', '<li>$1</li>', $readme);
            $readme = preg_replace('/^\* (.*)$/m', '<li>$1</li>', $readme);
            $readme = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $readme);
            $readme = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/`(.*?)`/', '<code>$1</code>', $readme);
            $readme = preg_replace('/\n{3,}/', "\n\n", $readme);
            $readme = nl2br($readme);
            return $readme;
        }

        private function get_installation_instructions()
        {
            return '<strong>' . esc_html__('Installation', 'we-taxonomy-navigation') . '</strong><br><br>
        <ol>
            <li>' . esc_html__('Upload the plugin files to the /wp-content/plugins/we-taxonomy-navigation directory, or install the plugin through the WordPress plugins screen directly.', 'we-taxonomy-navigation') . '</li>
            <li>' . esc_html__('Activate the plugin through the \'Plugins\' screen in WordPress.', 'we-taxonomy-navigation') . '</li>
            <li>' . esc_html__('Add the Taxonomy Navigation or Taxonomy List blocks to your pages, posts, or templates.', 'we-taxonomy-navigation') . '</li>
        </ol>';
        }

        public function after_install($response, $hook_extra, $result)
        {
            global $wp_filesystem;
            $install_directory = plugin_dir_path($this->file);
            $wp_filesystem->move($result['destination'], $install_directory);
            $result['destination'] = $install_directory;

            $this->set_plugin_properties();

            if ($this->active) {
                $activate = activate_plugin($this->basename);
            }

            return $result;
        }

        public function purge()
        {
            if ($this->active) {
                delete_transient('we_taxonomy_nav_github_updater_' . $this->basename);
            }
        }
    }

    // Initialize GitHub Updater
    new WE_Taxonomy_Nav_GitHub_Updater(__FILE__);
}
