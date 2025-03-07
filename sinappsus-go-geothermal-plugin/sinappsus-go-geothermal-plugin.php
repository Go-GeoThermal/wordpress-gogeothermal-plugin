<?php
/*
 * Plugin Name: Sinappsus GoGeothermal Official Plugin
 * Description: A custom WordPress plugin to integrate WooCommerce with The Go Geothermal API.
 * Plugin URI: https://gogeothermal.co.uk
 * Author URI: https://sinappsus.agency
 * Version: 0.0.7
 * Author: Sinappsus
 * Requires at least: 5.0
 * Tested up to: 6.0
*/

defined('ABSPATH') || exit;

define('GGT_SINAPPSUS_PLUGIN_VERSION', '0.0.7');
define('GGT_SINAPPSUS_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('GGT_SINAPPSUS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GGT_SINAPPSUS_API_URL', 'https://api.gogeothermal.co.uk/api');

// Plugin update checker
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/plugin-update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://raw.githubusercontent.com/Go-GeoThermal/wordpress-gogeothermal-plugin/refs/heads/master/sinappsus-go-geothermal-plugin/info.json',  
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

// Add settings link on plugin page
function ggt_sinappsus_plugin_action_links($links)
{
    $settings_link = '<a href="admin.php?page=sinappsus-ggt-settings">' . __('Settings', 'sinappsus-ggt-wp-plugin') . '</a>';
    $payment_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=geo_credit">' . __('Payment Configuration', 'sinappsus-ggt-wp-plugin') . '</a>';
    array_unshift($links, $settings_link, $payment_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ggt_sinappsus_plugin_action_links');

// Load The Go Geothermal Admin UI
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/admin/ui.php';