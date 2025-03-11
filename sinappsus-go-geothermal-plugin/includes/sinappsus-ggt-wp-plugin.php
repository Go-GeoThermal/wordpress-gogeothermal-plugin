<?php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// include api call custom class for authenticated requests
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/utils/class-api-connector.php';

// include go geothermal api middleware tools
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/sage/woocommerce-sage-integration.php';

// Make sure the blocks directory exists
if (!file_exists(GGT_SINAPPSUS_PLUGIN_PATH . '/includes/blocks')) {
    mkdir(GGT_SINAPPSUS_PLUGIN_PATH . '/includes/blocks', 0755, true);
}

// Add WooCommerce Blocks support
add_action('woocommerce_blocks_loaded', function() {
    if (class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/blocks/class-wc-geo-credit-blocks.php';

        add_action('woocommerce_blocks_payment_method_type_registration', function($registry) {
            error_log('Registering geo_credit manually in WooCommerce Blocks');
            $registry->register(new WC_Geo_Credit_Blocks_Support());
        });

        error_log('WooCommerce Blocks API loaded successfully');
    } else {
        error_log('WooCommerce Blocks API NOT available');
    }
});

// Include checkout enhancements
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/class-checkout-enhancements.php';

// Include woocommerce customization for delivery date and auto passing additional payment methods
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/class-woocommerce-customization.php';

// Make sure the enhanced checkout CSS is loaded one way or another
add_action('wp_enqueue_scripts', 'ggt_ensure_checkout_styles', 999);
function ggt_ensure_checkout_styles() {
    if (is_checkout() && !wp_style_is('ggt-checkout-enhancements-css', 'enqueued')) {
        wp_enqueue_style(
            'ggt-checkout-enhancements-css',
            plugins_url('sinappsus-go-geothermal-plugin/assets/css/checkout-enhancements.css', dirname(__FILE__, 2)),
            array(),
            filemtime(dirname(__FILE__, 2) . '/assets/css/checkout-enhancements.css')
        );
    }
}

// Don't load the separate delivery date picker script as it's now integrated in checkout-enhancements.js
// If there's any custom code in delivery-date-picker.js that's not in checkout-enhancements.js,
// it should be integrated there instead.

// Remove the separate enqueue function as it's now included in the class-checkout-enhancements.php file
// This prevents duplicate CSS loading
// do not delete the following line if it exists:
// add_action('wp_enqueue_scripts', 'ggt_enqueue_checkout_styles');