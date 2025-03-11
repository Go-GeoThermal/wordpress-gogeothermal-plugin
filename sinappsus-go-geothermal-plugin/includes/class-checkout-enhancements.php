<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GGT_Checkout_Enhancements {
    private $logger;
    
    public function __construct() {
        $this->logger = wc_get_logger();
        
        // Enqueue scripts for checkout
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
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
            wp_enqueue_script(
                'ggt-checkout-enhancements',
                plugins_url('sinappsus-go-geothermal-plugin/assets/js/checkout-enhancements.js', dirname(__FILE__, 2)),
                array('jquery'),
                '1.0.0',
                true
            );
            
            // Add some custom CSS for the delivery address modal
            wp_add_inline_style('woocommerce-inline', '
                #ggt-delivery-addresses-modal {
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0,0,0,0.4);
                }
                #ggt-delivery-addresses-modal .modal-content {
                    background-color: #fefefe;
                    margin: 15% auto;
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%;
                    max-width: 600px;
                }
                #ggt-delivery-addresses-modal .close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                .ggt-address-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }
                .ggt-address-item {
                    padding: 10px;
                    margin-bottom: 10px;
                    border: 1px solid #ddd;
                }
                .ggt-delivery-address-selector {
                    margin-bottom: 20px;
                }
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
