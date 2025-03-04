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




// Load payment gateway
function ggt_load_credit_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        wc_get_logger()->info('Loading credit payment gateway', ['source' => 'geo-credit']);
        require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/class-credit-payment.php';
    }
}
add_action('plugins_loaded', 'ggt_load_credit_gateway', 11);