<?php

/**
 * Plugin Name: WE Taxonomy Navigation
 * Description: Provides taxonomy list blocks and a navigation variation that auto-populates terms.
 * Version: 0.1.0
 * Author: webentwicklerin, Gabriele Laesser
 * Author URI: https://webentwicklerin.at
 * Text Domain: we-taxonomy-navigation
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace Webentwicklerin\TaxonomyNavigation;

if (! defined('ABSPATH')) {
    exit;
}

const PLUGIN_VERSION = '0.1.0';
const PLUGIN_PATH    = __DIR__ . '/';
const PLUGIN_FILE    = __FILE__;

require_once PLUGIN_PATH . 'includes/class-plugin.php';

Plugin::get_instance();
