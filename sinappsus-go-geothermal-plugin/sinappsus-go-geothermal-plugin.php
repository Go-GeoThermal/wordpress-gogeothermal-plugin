<?php
/*
 * Plugin Name: Sinappsus GoGeothermal Official Plugin
 * Description: A custom WordPress plugin to integrate WooCommerce with The Go Geothermal API.
 * Plugin URI: https://gogeothermal.co.uk
 * Author URI: https://sinappsus.agency
 * Version: 0.0.5
 * Author: Sinappsus
 * Requires at least: 5.0
 * Tested up to: 6.0
*/

defined('ABSPATH') || exit;

define('GGT_SINAPPSUS_PLUGIN_VERSION', '0.0.5');
define('GGT_SINAPPSUS_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('GGT_SINAPPSUS_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

// Plugin update checker
require_once __DIR__ . '/plugin-update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://raw.githubusercontent.com/sinappsus-agency/sas-ena-wp/refs/heads/master/wordpress-gogeothermal-plugin/info.json',  
    __FILE__, 
    'ena-sinappsus-plugin'
);

// Check Plugin is activated or activate
function ggt_sinappsus_plugin()
{
    require_once(plugin_basename('includes/sinappsus-ggt-wp-plugin.php'));
    load_plugin_textdomain('sinappsus-ggt-wp-plugin', false, trailingslashit(dirname(plugin_basename(__FILE__))));
}

add_action('plugins_loaded', 'ggt_sinappsus_plugin', 0);

// Load The Go Geothermal Admin UI
require_once __DIR__ . '/admin/ui.php';