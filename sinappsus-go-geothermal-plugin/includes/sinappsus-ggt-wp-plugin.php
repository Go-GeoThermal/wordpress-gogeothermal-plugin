<?php
// define('WP_DEBUG', true);
// define('WP_DEBUG_LOG', true);
// define('WP_DEBUG_DISPLAY', false);
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

// Ensure delivery date picker script loads even if we still have it as separate file
add_action('wp_enqueue_scripts', 'ggt_enqueue_delivery_date_script', 100);
function ggt_enqueue_delivery_date_script() {
    if (!is_checkout()) return;
    
    if (file_exists(GGT_SINAPPSUS_PLUGIN_PATH . '/assets/js/delivery-date-picker.js')) {
        wp_enqueue_script(
            'ggt-delivery-date-picker',
            plugins_url('sinappsus-go-geothermal-plugin/assets/js/delivery-date-picker.js', dirname(__FILE__, 2)),
            array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'),
            filemtime(GGT_SINAPPSUS_PLUGIN_PATH . '/assets/js/delivery-date-picker.js'),
            true
        );
    }
}

// Add debugging for delivery date and address features
add_action('wp_footer', 'ggt_debug_checkout_data', 100);
function ggt_debug_checkout_data() {
    if (!is_checkout() || !WP_DEBUG) return;
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Check if our date picker is working
        console.log('🔍 [GGT DEBUG] jQuery UI Datepicker available:', typeof $.fn.datepicker === 'function');
        console.log('🔍 [GGT DEBUG] Delivery date field exists:', $('#ggt_delivery_date').length > 0);
        
        // Get all shipping fields
        const shippingFields = $('input, select').filter(function() { 
            return this.id && (this.id.includes('shipping') || this.name && this.name.includes('shipping')); 
        });
        console.log('🔍 [GGT DEBUG] All shipping fields found:', shippingFields.map(function() { 
            return { id: this.id, name: this.name }; 
        }).get());
        
        // Monitor form submission
        $('form.checkout').on('submit', function(e) {
            console.log('🔍 [GGT DEBUG] Form submitted with delivery date:', $('#ggt_delivery_date').val());
            
            // Add delivery date as hidden field one more time to be extra sure
            if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                $(this).append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + $('#ggt_delivery_date').val() + '">');
            } else {
                $('input[name="ggt_delivery_date_hidden"]').val($('#ggt_delivery_date').val());
            }
        });
    });
    </script>
    <?php
}

// Add enhanced debugging for delivery date and address issues - SIMPLIFIED to avoid breaking things
add_action('wp_footer', 'ggt_enhanced_debug', 999);
function ggt_enhanced_debug() {
    if (!is_checkout() || !WP_DEBUG) return;
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('🔍 [GGT DEBUG] Running enhanced debug script');
        
        // Log all shipping fields that actually exist in the DOM
        const shippingFields = $('input, select, textarea').filter(function() {
            return (this.id && this.id.toLowerCase().includes('shipping')) || 
                   (this.name && this.name.toLowerCase().includes('shipping'));
        });
        
        console.log('🔍 [GGT DEBUG] Shipping fields found:', shippingFields.length);
        
        // Debug the shipping checkbox behavior
        const $shipCheckbox = $('#ship-to-different-address-checkbox');
        if ($shipCheckbox.length) {
            console.log('🔍 [GGT DEBUG] Ship-to-different checkbox found, checked:', $shipCheckbox.is(':checked'));
        } else {
            console.log('🔍 [GGT DEBUG] Ship-to-different checkbox NOT found');
        }
    });
    </script>
    <?php
}

// Debugging for our order API integration
add_action('woocommerce_checkout_order_processed', 'ggt_log_order_meta', 5, 3);
function ggt_log_order_meta($order_id, $posted_data, $order) {
    wc_get_logger()->debug(
        sprintf('Order #%d processed - checking delivery date meta', $order_id),
        ['source' => 'ggt-debug']
    );
    
    // Check what delivery date data we have
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    $delivery_date_alt = get_post_meta($order_id, '_delivery_date', true);
    $delivery_date_order = $order->get_meta('ggt_delivery_date');
    
    wc_get_logger()->debug(
        sprintf('Order #%d delivery dates: Meta: %s, Alt: %s, Order: %s, POST: %s',
            $order_id,
            $delivery_date ?: 'not set',
            $delivery_date_alt ?: 'not set',
            $delivery_date_order ?: 'not set',
            !empty($_POST['ggt_delivery_date']) ? $_POST['ggt_delivery_date'] : 'not set'
        ),
        ['source' => 'ggt-debug']
    );
    
    // Log all checkout POST data to see what's being submitted
    if (!empty($_POST)) {
        $relevant_post = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'date') !== false || strpos($key, 'delivery') !== false) {
                $relevant_post[$key] = $value;
            }
        }
        
        if (!empty($relevant_post)) {
            wc_get_logger()->debug(
                sprintf('Order #%d POST data related to delivery date: %s',
                    $order_id, 
                    json_encode($relevant_post)
                ),
                ['source' => 'ggt-debug']
            );
        } else {
            wc_get_logger()->debug(
                sprintf('Order #%d - No relevant delivery date in POST data', $order_id),
                ['source' => 'ggt-debug']
            );
        }
    }
    
    // Emergency save if needed
    if (empty($delivery_date) && !empty($_POST['ggt_delivery_date'])) {
        update_post_meta($order_id, 'ggt_delivery_date', sanitize_text_field($_POST['ggt_delivery_date']));
        $order->update_meta_data('ggt_delivery_date', sanitize_text_field($_POST['ggt_delivery_date']));
        $order->save();
        
        wc_get_logger()->debug(
            sprintf('Order #%d - Emergency save of delivery date from POST data', $order_id),
            ['source' => 'ggt-debug']
        );
    }
    
    // Check for delivery address data
    $delivery_info = get_post_meta($order_id, 'ggt_delivery_info', true);
    
    if (!empty($delivery_info)) {
        wc_get_logger()->debug(
            sprintf('Order #%d has delivery address info: %s',
                $order_id, 
                substr($delivery_info, 0, 100) . '...' // First 100 chars to avoid huge logs
            ),
            ['source' => 'ggt-debug']
        );
    } else {
        wc_get_logger()->debug(
            sprintf('Order #%d - No delivery address information found', $order_id),
            ['source' => 'ggt-debug']
        );
    }
}

/**
 * Get the base API URL based on the selected environment
 * @return string The base API URL
 */
function ggt_get_api_base_url() {
    global $environments;
    $selected_env = get_option('ggt_sinappsus_environment', 'production');
    
    if (isset($environments[$selected_env]) && isset($environments[$selected_env]['api_url'])) {
        return $environments[$selected_env]['api_url'];
    }
    
    // Default to production if environment not found
    return "https://api.gogeothermal.co.uk/api";
}