<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class admin_menu_page
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'Authentication',
            'manage_options',
            [$this, 'admin_menu_page'],
            'dashicons-admin-settings',
            90
        );
    }

    public function admin_menu_page()
    {
        // Display the admin page with options to override and approve users/orders
        echo '<div class="wrap"><h1>Sage Integration Admin</h1>';
        echo '</div>';
    }
}
