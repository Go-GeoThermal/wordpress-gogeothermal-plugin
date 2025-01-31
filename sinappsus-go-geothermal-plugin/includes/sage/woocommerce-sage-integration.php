<?php 


class WooCommerceSageIntegration
{

    public function __construct()
    {

        // Register activation hook
        // register_activation_hook(__FILE__, [$this, 'plugin_activate']);
        // Add action hooks for WooCommerce integration
        // add_action('woocommerce_thankyou', [$this, 'convert_order_to_quote'], 10, 1);
        // add_action('user_register', [$this, 'register_user_in_sage'], 10, 1);
        // add_action('admin_menu', [$this, 'register_admin_menu']);
        // add_filter('woocommerce_get_price_html', [$this, 'get_product_price_from_sage'], 10, 2);
        // // Additional actions and filters
        // add_action('woocommerce_new_order', [$this, 'sync_new_order_to_sage'], 10, 1);
        // add_action('woocommerce_product_save', [$this, 'sync_product_to_sage'], 10, 1);
    }


    

}

new WooCommerceSageIntegration();
