<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GGT_Checkout_Enhancements {
    private $logger;
    
    public function __construct() {
        $this->logger = wc_get_logger();
        
        // Enqueue scripts earlier to ensure they're loaded before other scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 5);
        
        // Register AJAX handlers
        add_action('wp_ajax_ggt_fetch_customer_pricing', array($this, 'ajax_fetch_customer_pricing'));
        add_action('wp_ajax_nopriv_ggt_fetch_customer_pricing', array($this, 'ajax_fetch_customer_pricing'));
        
        add_action('wp_ajax_ggt_update_cart_prices', array($this, 'ajax_update_cart_prices'));
        add_action('wp_ajax_nopriv_ggt_update_cart_prices', array($this, 'ajax_update_cart_prices'));
        
        add_action('wp_ajax_ggt_fetch_delivery_addresses', array($this, 'ajax_fetch_delivery_addresses'));
        add_action('wp_ajax_nopriv_ggt_fetch_delivery_addresses', array($this, 'ajax_fetch_delivery_addresses'));
        
        // Add stock code data attribute to cart items  
        add_filter('woocommerce_cart_item_class', array($this, 'add_stock_code_to_cart_item'), 10, 3);
        add_action('woocommerce_cart_item_name', array($this, 'add_stock_code_data_attribute'), 10, 3);
        
        // Ensure custom prices persist during checkout and order creation
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_custom_pricing_to_cart'), 10, 1);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_custom_price_to_order_item'), 10, 4);
        add_action('woocommerce_checkout_order_processed', array($this, 'log_order_totals'), 10, 1);
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            // Force load jQuery and jQuery UI with earliest priority
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            
            // jQuery UI styling with a direct protocol-relative URL
            wp_enqueue_style(
                'jquery-ui-style',
                '//code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                array(),
                '1.13.2'
            );
            
            // Use a timestamp to break cache
            $version = time();
            
            // Load checkout enhancements script last to ensure it can access UI elements
            wp_enqueue_script(
                'ggt-checkout-enhancements',
                plugins_url('sinappsus-go-geothermal-plugin/assets/js/checkout-enhancements.js', dirname(__FILE__, 2)),
                array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'),
                $version,
                true // Load in footer
            );
            
            // Load CSS for the checkout enhancements
            wp_enqueue_style(
                'ggt-checkout-enhancements-css',
                plugins_url('sinappsus-go-geothermal-plugin/assets/css/checkout-enhancements.css', dirname(__FILE__, 2)),
                array('jquery-ui-style'),
                $version
            );
            
            // Add a debug script to check jQuery UI
            wp_add_inline_script('jquery-ui-datepicker', '
                console.log("[GGT DEBUG] jQuery UI Datepicker loaded: " + (typeof jQuery.fn.datepicker === "function"));
            ', 'after');
            
            // Get customer account reference
            $account_ref = '';
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $account_ref = get_user_meta($user_id, 'accountRef', true);
                if (!$account_ref) {
                    $account_ref = get_user_meta($user_id, 'account_ref', true);
                }
            }
            
            // Get cart stock codes for JavaScript
            $cart_stock_codes = $this->get_cart_stock_codes();
            
            // Localize script with data needed for AJAX calls
            wp_localize_script('ggt-checkout-enhancements', 'ggt_checkout_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ggt_checkout_nonce'),
                'account_ref' => $account_ref,
                'cart_stock_codes' => $cart_stock_codes
            ));
        }
    }
    
    public function get_cart_stock_codes() {
        $cart_stock_codes = array();
        
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $stock_code = get_post_meta($product_id, '_stockCode', true);
                
                if ($stock_code) {
                    $cart_stock_codes[] = array(
                        'cart_item_key' => $cart_item_key,
                        'product_id' => $product_id,
                        'stock_code' => $stock_code,
                        'quantity' => $cart_item['quantity']
                    );
                }
            }
        }
        
        return $cart_stock_codes;
    }
    
    public function add_stock_code_to_cart_item($class, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $stock_code = get_post_meta($product->get_id(), '_stockCode', true);
        
        if ($stock_code) {
            $class .= ' stock-code-' . sanitize_html_class($stock_code);
        }
        
        return $class;
    }
    
    public function add_stock_code_data_attribute($product_name, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $stock_code = get_post_meta($product->get_id(), '_stockCode', true);
        
        if ($stock_code) {
            // Add data attribute to the product name element
            $product_name = '<span data-stock-code="' . esc_attr($stock_code) . '" data-cart-key="' . esc_attr($cart_item_key) . '">' . $product_name . '</span>';
        }
        
        return $product_name;
    }
    
    public function ajax_fetch_customer_pricing() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        $account_ref = isset($_POST['account_ref']) ? sanitize_text_field($_POST['account_ref']) : '';
        
        if (empty($account_ref)) {
            wp_send_json_error(array('message' => 'Account reference is required'));
            return;
        }
        
        // Use the centralized API connector
        $endpoint = 'customers/' . urlencode($account_ref) . '/pricing';
        $response = ggt_sinappsus_connect_to_api($endpoint);
        
        if (isset($response['error'])) {
            wp_send_json_error(array('message' => $response['error']));
            return;
        }
        
        wp_send_json_success($response);
    }
    
    public function apply_custom_pricing_to_cart($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Get custom pricing from session/user meta if available
        $account_ref = '';
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $account_ref = get_user_meta($user_id, 'accountRef', true);
            if (!$account_ref) {
                $account_ref = get_user_meta($user_id, 'account_ref', true);
            }
        }
        
        if (!$account_ref) {
            return;
        }
        
        // Check if we have custom pricing stored in session
        $custom_pricing = WC()->session->get('ggt_custom_pricing');
        if (!$custom_pricing) {
            return;
        }
        
        $this->logger->info('Applying custom pricing to cart during calculation', array('source' => 'ggt-checkout'));
        
        // Create price map from custom pricing
        $price_map = array();
        foreach ($custom_pricing as $price_item) {
            if (!empty($price_item['stockCode']) && isset($price_item['storedPrice'])) {
                $price_map[$price_item['stockCode']] = $price_item['storedPrice'];
            }
        }
        
        // Apply custom prices to cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $stock_code = get_post_meta($product_id, '_stockCode', true);
            
            if ($stock_code && isset($price_map[$stock_code])) {
                $custom_price = $price_map[$stock_code];
                $cart_item['data']->set_price($custom_price);
                
                // Store the custom price in cart item data for later reference
                $cart_item['ggt_custom_price'] = $custom_price;
                $cart_item['ggt_stock_code'] = $stock_code;
                
                $this->logger->info(
                    sprintf('Applied custom price %f to product %s (stock: %s)', 
                        $custom_price, 
                        $product_id, 
                        $stock_code
                    ), 
                    array('source' => 'ggt-checkout')
                );
            }
        }
    }
    
    public function save_custom_price_to_order_item($item, $cart_item_key, $values, $order) {
        // If this cart item had a custom price, save it to the order item
        if (isset($values['ggt_custom_price']) && isset($values['ggt_stock_code'])) {
            $item->add_meta_data('_ggt_custom_price', $values['ggt_custom_price']);
            $item->add_meta_data('_ggt_stock_code', $values['ggt_stock_code']);
            $item->add_meta_data('_ggt_original_price', $values['data']->get_regular_price());
            
            $this->logger->info(
                sprintf('Saved custom price %f to order item for stock code %s', 
                    $values['ggt_custom_price'], 
                    $values['ggt_stock_code']
                ), 
                array('source' => 'ggt-checkout')
            );
        }
    }
    
    public function log_order_totals($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->logger->info(
                sprintf('Order %d created with total: %f', 
                    $order_id, 
                    $order->get_total()
                ), 
                array('source' => 'ggt-checkout')
            );
            
            // Log each item's price for debugging
            foreach ($order->get_items() as $item) {
                $custom_price = $item->get_meta('_ggt_custom_price');
                $stock_code = $item->get_meta('_ggt_stock_code');
                
                if ($custom_price && $stock_code) {
                    $this->logger->info(
                        sprintf('Order item %s (stock: %s) has custom price: %f', 
                            $item->get_name(), 
                            $stock_code, 
                            $custom_price
                        ), 
                        array('source' => 'ggt-checkout')
                    );
                }
            }
        }
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
        
        // Store custom pricing in session for persistence through order creation
        WC()->session->set('ggt_custom_pricing', $prices);
        
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
                    
                    // Store custom pricing data in cart item for order creation
                    WC()->cart->cart_contents[$cart_item_key]['ggt_custom_price'] = $custom_price;
                    WC()->cart->cart_contents[$cart_item_key]['ggt_stock_code'] = $stock_code;
                    
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
        
        // Use the centralized API connector
        $endpoint = 'customers/' . urlencode($account_ref) . '/addresses';
        $response = ggt_sinappsus_connect_to_api($endpoint);
        
        if (isset($response['error'])) {
            wp_send_json_error(array('message' => $response['error']));
            return;
        }
        
        wp_send_json_success($response);
    }
}

// Initialize the class
new GGT_Checkout_Enhancements();
