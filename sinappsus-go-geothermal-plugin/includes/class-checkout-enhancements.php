<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GGT_Checkout_Enhancements {
    private $logger;
    
    public function __construct() {
        $this->logger = wc_get_logger();
        
        // Enqueue scripts for checkout with higher priority to ensure it loads early
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
        
        // Register AJAX handlers
        add_action('wp_ajax_ggt_fetch_customer_pricing', array($this, 'ajax_fetch_customer_pricing'));
        add_action('wp_ajax_nopriv_ggt_fetch_customer_pricing', array($this, 'ajax_fetch_customer_pricing'));
        
        add_action('wp_ajax_ggt_update_cart_prices', array($this, 'ajax_update_cart_prices'));
        add_action('wp_ajax_nopriv_ggt_update_cart_prices', array($this, 'ajax_update_cart_prices'));
        
        add_action('wp_ajax_ggt_fetch_delivery_addresses', array($this, 'ajax_fetch_delivery_addresses'));
        add_action('wp_ajax_nopriv_ggt_fetch_delivery_addresses', array($this, 'ajax_fetch_delivery_addresses'));
        
        // Add stock code data attribute to cart items
        add_filter('woocommerce_cart_item_class', array($this, 'add_stock_code_to_cart_item'), 10, 3);
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            // Make sure jQuery and jQuery UI are loaded with specified versions
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            
            // jQuery UI styling - make sure it loads with a proper handle
            wp_enqueue_style(
                'jquery-ui-for-ggt',  // Changed handle to avoid conflicts
                '//code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                array(),
                '1.13.2'
            );
            
            // Deregister delivery date picker if it exists to prevent conflicts
            if (wp_script_is('ggt-delivery-date-picker', 'registered')) {
                wp_deregister_script('ggt-delivery-date-picker');
            }
            
            // Add version timestamp to bust cache
            $js_version = filemtime(dirname(__FILE__, 2) . '/assets/js/checkout-enhancements.js');
            $css_version = filemtime(dirname(__FILE__, 2) . '/assets/css/checkout-enhancements.css');
            
            // Load our custom checkout enhancements script with proper dependencies
            wp_enqueue_script(
                'ggt-checkout-enhancements',
                plugins_url('sinappsus-go-geothermal-plugin/assets/js/checkout-enhancements.js', dirname(__FILE__, 2)),
                array('jquery', 'jquery-ui-datepicker'),
                $js_version,
                true
            );
            
            // Load the custom CSS
            wp_enqueue_style(
                'ggt-checkout-enhancements-css',
                plugins_url('sinappsus-go-geothermal-plugin/assets/css/checkout-enhancements.css', dirname(__FILE__, 2)),
                array('jquery-ui-for-ggt'),  // Make sure our CSS loads after jQuery UI CSS
                $css_version
            );
            
            // Debug jQuery UI availability
            wp_add_inline_script('ggt-checkout-enhancements', '
                console.log("jQuery UI Status Check:");
                console.log("- jQuery version: " + jQuery.fn.jquery);
                console.log("- jQuery UI Datepicker available: " + (typeof jQuery.fn.datepicker === "function"));
                console.log("- Datepicker field exists: " + (jQuery("#ggt_delivery_date").length > 0));
            ');
            
            // Get the currently logged-in user's account reference
            $account_ref = '';
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $account_ref = get_user_meta($user_id, 'accountRef', true);
                if (!$account_ref) {
                    $account_ref = get_user_meta($user_id, 'account_ref', true); // Check alternative key
                }
            }
            
            // Log information for debugging
            $this->logger->info('Enqueuing checkout enhancements script with account ref: ' . $account_ref, 
                array('source' => 'ggt-checkout')
            );
            
            // Localize script with data needed for AJAX calls
            wp_localize_script('ggt-checkout-enhancements', 'ggt_checkout_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ggt_checkout_nonce'),
                'account_ref' => $account_ref
            ));
        }
    }
    
    public function add_stock_code_to_cart_item($class, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $stock_code = get_post_meta($product->get_id(), '_stockCode', true);
        
        if ($stock_code) {
            $class .= ' stock-code-' . sanitize_html_class($stock_code);
            $class .= '" data-stock-code="' . esc_attr($stock_code);
        }
        
        return $class;
    }
    
    public function ajax_fetch_customer_pricing() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        $account_ref = isset($_POST['account_ref']) ? sanitize_text_field($_POST['account_ref']) : '';
        
        if (empty($account_ref)) {
            wp_send_json_error(array('message' => 'Account reference is required'));
            return;
        }
        
        // Get API token
        $token = $this->get_api_token();
        if (!$token) {
            wp_send_json_error(array('message' => 'API token not available'));
            return;
        }
        
        // Make API request to get custom pricing
        $response = wp_remote_get(
            'https://api.gogeothermal.co.uk/api/customers/' . urlencode($account_ref) . '/pricing',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            $this->logger->error('Customer pricing API error: ' . $response->get_error_message(), array('source' => 'ggt-checkout'));
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON in customer pricing response', array('source' => 'ggt-checkout'));
            wp_send_json_error(array('message' => 'Invalid response from API'));
            return;
        }
        
        wp_send_json_success($data);
    }
    
    public function ajax_update_cart_prices() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        $prices = isset($_POST['prices']) ? $_POST['prices'] : array();
        
        if (empty($prices) || !is_array($prices)) {
            wp_send_json_error(array('message' => 'No valid prices provided'));
            return;
        }
        
        // Create a more accessible array of prices indexed by stock code
        $price_map = array();
        foreach ($prices as $price) {
            if (!empty($price['stockCode']) && isset($price['storedPrice'])) {
                $price_map[$price['stockCode']] = $price['storedPrice'];
            }
        }
        
        // Update cart item prices if they match a stock code with custom pricing
        $cart = WC()->cart;
        $cart_updated = false;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $stock_code = get_post_meta($product_id, '_stockCode', true);
            
            if ($stock_code && isset($price_map[$stock_code])) {
                $custom_price = $price_map[$stock_code];
                
                // Only update if the price is different
                $current_price = $cart_item['data']->get_price();
                if ($current_price != $custom_price) {
                    $cart_item['data']->set_price($custom_price);
                    $this->logger->info(
                        sprintf('Updated price for product %s (SKU: %s) from %f to %f', 
                            $product_id, 
                            $stock_code,
                            $current_price,
                            $custom_price
                        ), 
                        array('source' => 'ggt-checkout')
                    );
                    $cart_updated = true;
                }
            }
        }
        
        if ($cart_updated) {
            $cart->calculate_totals();
        }
        
        wp_send_json_success(array(
            'updated' => $cart_updated,
            'cart_total' => $cart->get_cart_total()
        ));
    }
    
    public function ajax_fetch_delivery_addresses() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        $account_ref = isset($_POST['account_ref']) ? sanitize_text_field($_POST['account_ref']) : '';
        
        if (empty($account_ref)) {
            wp_send_json_error(array('message' => 'Account reference is required'));
            return;
        }
        
        // Get API token
        $token = $this->get_api_token();
        if (!$token) {
            wp_send_json_error(array('message' => 'API token not available'));
            return;
        }
        
        // Make API request to get delivery addresses
        $response = wp_remote_get(
            'https://api.gogeothermal.co.uk/api/customers/' . urlencode($account_ref) . '/delivery-address',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            $this->logger->error('Delivery address API error: ' . $response->get_error_message(), array('source' => 'ggt-checkout'));
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON in delivery address response', array('source' => 'ggt-checkout'));
            wp_send_json_error(array('message' => 'Invalid response from API'));
            return;
        }
        
        wp_send_json_success($data);
    }
    
    private function get_api_token() {
        $encrypted_token = get_option('sinappsus_gogeo_codex');
        if ($encrypted_token) {
            return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
        }
        return false;
    }
}

// Initialize the class
new GGT_Checkout_Enhancements();
