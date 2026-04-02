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
        
        // Register AJAX handlers (kept for backwards compatibility; pricing is now handled globally by GGT_Customer_Pricing)
        add_action('wp_ajax_ggt_fetch_customer_pricing', array($this, 'ajax_fetch_customer_pricing'));
        
        add_action('wp_ajax_ggt_update_cart_prices', array($this, 'ajax_update_cart_prices'));
        
        add_action('wp_ajax_ggt_fetch_delivery_addresses', array($this, 'ajax_fetch_delivery_addresses'));
        
        // AJAX handler to refresh pricing cache
        add_action('wp_ajax_ggt_refresh_customer_pricing_cache', array($this, 'ajax_refresh_pricing_cache'));
        
        // Add stock code data attribute to cart items  
        add_filter('woocommerce_cart_item_class', array($this, 'add_stock_code_to_cart_item'), 10, 3);
        add_action('woocommerce_cart_item_name', array($this, 'add_stock_code_data_attribute'), 10, 3);
        
        // Custom pricing is now applied globally by GGT_Customer_Pricing::apply_prices_to_cart
        // We keep the order item meta saver for traceability
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
                GGT_SINAPPSUS_PLUGIN_URL . '/assets/css/jquery-ui.css',
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
            $has_custom_pricing = false;
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $account_ref = get_user_meta($user_id, 'accountRef', true);
                if (!$account_ref) {
                    $account_ref = get_user_meta($user_id, 'account_ref', true);
                }
                // Check if user has custom pricing (already loaded by GGT_Customer_Pricing)
                if (class_exists('GGT_Customer_Pricing')) {
                    $has_custom_pricing = GGT_Customer_Pricing::customer_has_custom_pricing();
                }
            }
            
            // Get cart stock codes for JavaScript
            $cart_stock_codes = $this->get_cart_stock_codes();
            
            // Localize script with data needed for AJAX calls
            wp_localize_script('ggt-checkout-enhancements', 'ggt_checkout_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ggt_checkout_nonce'),
                'account_ref' => $account_ref,
                'cart_stock_codes' => $cart_stock_codes,
                'has_custom_pricing' => $has_custom_pricing, // Tells JS that prices are already applied server-side
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
    
    /**
     * AJAX: Fetch customer pricing.
     * Now returns data from the globally-cached GGT_Customer_Pricing class
     * instead of making a separate API call. Kept for backwards compatibility.
     */
    public function ajax_fetch_customer_pricing() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        $account_ref = isset($_POST['account_ref']) ? sanitize_text_field($_POST['account_ref']) : '';
        
        if (empty($account_ref)) {
            wp_send_json_error(array('message' => 'Account reference is required'));
            return;
        }
        
        // Use the globally-cached price map from GGT_Customer_Pricing
        // This avoids a duplicate API call since prices are already loaded
        if (class_exists('GGT_Customer_Pricing')) {
            $price_map = GGT_Customer_Pricing::get_price_map();
            $mapped_prices = array();
            foreach ($price_map as $stock_code => $discounted_price) {
                $mapped_prices[] = array(
                    'stockCode' => $stock_code,
                    'storedPrice' => $discounted_price,
                );
            }
            wp_send_json_success(array(
                'prices' => $mapped_prices,
                'already_applied' => true, // Signal to JS that prices are already applied server-side
            ));
            return;
        }
        
        // Fallback: direct API call (should not normally reach here)
        $endpoint = 'customers/' . urlencode($account_ref) . '/custom-pricing';
        $response = ggt_sinappsus_connect_to_api($endpoint);
        
        if (isset($response['error'])) {
            wp_send_json_error(array('message' => $response['error']));
            return;
        }

        $mapped_prices = array();
        
        if (isset($response['pricing']) && is_array($response['pricing'])) {
            foreach ($response['pricing'] as $item) {
                if (isset($item['stockCode']) && isset($item['discountedPrice'])) {
                    $mapped_prices[] = array(
                        'stockCode' => $item['stockCode'],
                        'storedPrice' => $item['discountedPrice'],
                        'originalPrice' => isset($item['originalPrice']) ? $item['originalPrice'] : 0,
                        'discountPercentage' => isset($item['discountPercentage']) ? $item['discountPercentage'] : 0
                    );
                }
            }
        }

        wp_send_json_success(array('prices' => $mapped_prices));
    }
    
    /**
     * Cart pricing is now handled globally by GGT_Customer_Pricing::apply_prices_to_cart().
     * This method is kept as a no-op safety net — if somehow it fires, it defers to the new class.
     * The old session-based approach is removed.
     */
    public function apply_custom_pricing_to_cart($cart) {
        // Pricing is now applied globally by GGT_Customer_Pricing.
        // This hook registration has been removed from __construct.
        // If called directly, do nothing.
        return;
    }
    
    public function save_custom_price_to_order_item($item, $cart_item_key, $values, $order) {
        // Save custom price metadata to order items for traceability.
        // Check both the old session-based keys and the new GGT_Customer_Pricing keys.
        $stock_code = isset($values['ggt_stock_code']) ? $values['ggt_stock_code'] : null;
        $custom_price = isset($values['ggt_custom_price']) ? $values['ggt_custom_price'] : null;
        
        // Fallback: if not set from cart item data, check via GGT_Customer_Pricing
        if (!$stock_code && class_exists('GGT_Customer_Pricing')) {
            $product_id = $values['product_id'] ?? ($values['data'] ? $values['data']->get_id() : 0);
            $stock_code = get_post_meta($product_id, '_stockCode', true);
            $custom_price = GGT_Customer_Pricing::get_custom_price($product_id);
        }
        
        if ($stock_code && null !== $custom_price) {
            $item->add_meta_data('_ggt_custom_price', $custom_price);
            $item->add_meta_data('_ggt_stock_code', $stock_code);
            
            // Get the original WC price (bypassing our filters)
            $original_price = get_post_meta($values['data']->get_id(), '_regular_price', true);
            if ($original_price) {
                $item->add_meta_data('_ggt_original_price', $original_price);
            }
            
            $this->logger->info(
                sprintf('Saved custom price %f to order item for stock code %s', 
                    $custom_price, 
                    $stock_code
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
    
    /**
     * AJAX: Update cart prices.
     * With global pricing now active, this is mostly a no-op — cart prices are already correct.
     * Kept for backwards compatibility. Will still recalculate totals if called.
     */
    public function ajax_update_cart_prices() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        // Prices are now applied globally by GGT_Customer_Pricing.
        // Simply recalculate totals to ensure everything is in sync.
        if (WC()->cart) {
            WC()->cart->calculate_totals();
        }
        
        wp_send_json_success(array(
            'updated' => true,
            'cart_total' => WC()->cart ? WC()->cart->get_cart_total() : 0,
            'note' => 'Prices are applied globally by server-side pricing engine.'
        ));
    }
    
    /**
     * AJAX: Force refresh the customer pricing cache.
     */
    public function ajax_refresh_pricing_cache() {
        check_ajax_referer('ggt_checkout_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }
        
        if (class_exists('GGT_Customer_Pricing')) {
            GGT_Customer_Pricing::clear_cache();
            $prices = GGT_Customer_Pricing::get_price_map();
            wp_send_json_success(array(
                'message' => 'Pricing cache refreshed',
                'count' => count($prices)
            ));
        } else {
            wp_send_json_error(array('message' => 'Customer pricing module not available'));
        }
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
