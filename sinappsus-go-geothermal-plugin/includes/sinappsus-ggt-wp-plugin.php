<?php 

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// include api call custom class for authenticated requests
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/utils/class-api-connector.php';

// include go geothermal api middleware tools
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/sage/woocommerce-sage-integration.php';


