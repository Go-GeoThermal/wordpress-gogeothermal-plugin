<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Sinappsus_GGT_Admin_UI
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function sinappsus_ggt_wp_plugin($links)
    {
        $settings_url = add_query_arg(
            array(
                'page' => 'sinappsus-ggt-settings',
                'tab' => 'integration',
                'section' => 'sinappsus_ggt_wp_plugin',
            ),
            admin_url('admin.php')
        );
    
        $plugin_links = array(
            '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'sinappsus-ggt-wp-plugin') . '</a>',
            '<a href="#">' . __('Support', 'sinappsus-ggt-wp-plugin') . '</a>',
            '<a href="#">' . __('Docs', 'sinappsus-ggt-wp-plugin') . '</a>',
        );
    
        return array_merge($plugin_links, $links);
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sinappsus_ggt_wp_plugin');
    
}
