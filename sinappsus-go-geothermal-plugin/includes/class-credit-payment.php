<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Geo_Credit_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'geo_credit';
        $this->title = __('Go Geothermal Credit Payment', 'woocommerce');
        $this->description = __('Use your available credit to checkout.', 'woocommerce');
        $this->has_fields = false;
        $this->method_title = __('Go Geothermal Credit Payment', 'woocommerce');
        $this->method_description = __('Allows Go geothermal credit approved users to pay with credit if their credit limit is sufficient.', 'woocommerce');

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Add support for blocks checkout
        $this->add_blocks_support();
    }

    // Add support for WooCommerce Blocks
    private function add_blocks_support() {
        // Check if WC Blocks is active and of a compatible version
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/blocks/class-wc-geo-credit-blocks.php';
            // $this->logger->info('WooCommerce Blocks support classes loaded: ' . GGT_SINAPPSUS_PLUGIN_PATH . '/includes/blocks/class-wc-geo-credit-blocks.php');
        }
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Credit Payment', 'woocommerce'),
                'default' => 'yes'
            ),
            'debug' => array(
                'title' => __('Debug Mode', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable debug mode', 'woocommerce'),
                'default' => 'yes',
                'description' => __('Show debug info on checkout page', 'woocommerce'),
            )
        );
    }

    public function is_available() {
        // First, check if the gateway is enabled
        if ($this->enabled != 'yes') {
            return false;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $cart_total = WC()->cart->total;

        // Try different meta keys where credit limit might be stored
        $credit_limit = get_user_meta($user_id, 'CreditLimit', true);
        if (empty($credit_limit)) {
            $credit_limit = get_user_meta($user_id, 'creditLimit', true);
        }
        if (empty($credit_limit)) {
            $credit_limit = get_user_meta($user_id, 'credit_limit', true);
        }

        if (empty($credit_limit) || floatval($credit_limit) < floatval($cart_total)) {
            return false;
        }

        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        // Store payment provider meta for consistency
        $order->update_meta_data('ggt_payment_method', $order->get_payment_method());
        $order->update_meta_data('ggt_payment_method_title', $order->get_payment_method_title());
        $order->save();

        // Comprehensive check for delivery date from multiple sources
        $delivery_date = null;
        $meta_keys_to_try = ['ggt_delivery_date', '_delivery_date', '_ggt_delivery_date', 'delivery_date'];
        
        foreach ($meta_keys_to_try as $key) {
            $meta_value = get_post_meta($order_id, $key, true);
            if (!empty($meta_value)) {
                $delivery_date = $meta_value;
                break;
            }
        }
        
        // Check POST data if no date found in meta
        if (empty($delivery_date)) {
            if (!empty($_POST['ggt_delivery_date'])) {
                $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
            } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
                $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
            } else {
                // Last attempt - check any POST field with delivery_date in the name
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'delivery_date') !== false && !empty($value)) {
                        $delivery_date = sanitize_text_field($value);
                        break;
                    }
                }
            }
            
            // If found in POST, save it to all possible meta keys
            if (!empty($delivery_date)) {
                foreach ($meta_keys_to_try as $key) {
                    update_post_meta($order_id, $key, $delivery_date);
                }
            }
        }
        
        // Also check session as last resort
        if (empty($delivery_date) && WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
            $delivery_date = WC()->session->get('ggt_validated_delivery_date');
            foreach ($meta_keys_to_try as $key) {
                update_post_meta($order_id, $key, $delivery_date);
            }
        }

        // Try different meta keys where credit limit might be stored
        $credit_limit = get_user_meta($user_id, 'CreditLimit', true);
        if (empty($credit_limit)) {
            $credit_limit = get_user_meta($user_id, 'creditLimit', true);
        }
        if (empty($credit_limit)) {
            $credit_limit = get_user_meta($user_id, 'credit_limit', true);
        }

        if (!empty($credit_limit) && floatval($credit_limit) >= floatval($order->get_total())) {
            // Figure out which key to use for updating
            $credit_key = 'creditLimit'; // Default
            if (get_user_meta($user_id, 'CreditLimit', true) !== '') {
                $credit_key = 'CreditLimit';
            } else if (get_user_meta($user_id, 'credit_limit', true) !== '') {
                $credit_key = 'credit_limit';
            }

            // Deduct the order total from user's credit
            update_user_meta($user_id, $credit_key, floatval($credit_limit) - floatval($order->get_total()));

            // Send order details to external API
            $response = $this->send_order_to_api($order, $delivery_date);

            if (is_wp_error($response)) {
                wc_add_notice(__('Something went wrong. Please contact your account manager.', 'woocommerce'), 'error');
                return array('result' => 'fail');
            }

            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code == 200) {
                // Mark order as completed
                $order->payment_complete();
                $order->update_status('completed', __('Paid using store credit.', 'woocommerce'));
                // Mark sent to avoid duplicate sends by global hooks
                $order->update_meta_data('ggt_sales_order_sent', 1);
                $order->update_meta_data('ggt_sales_order_sent_at', current_time('mysql'));
                $order->save();

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } elseif ($response_code == 401) {
                // Check for specific "User is not approved" message
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (isset($data['message']) && strpos($data['message'], 'User is not approved') !== false) {
                    // Handle the specific error: flag order, notify admin
                    if (function_exists('ggt_handle_account_not_found_error')) {
                        ggt_handle_account_not_found_error($order);
                    }
                    
                    // Treat as success for the user flow (transaction logged), but order is on-hold
                    // We empty cart and redirect to thank you page where the notice will be shown
                    WC()->cart->empty_cart();
                    
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    wc_add_notice(__('Something went wrong. Please contact your account manager.', 'woocommerce'), 'error');
                    return array('result' => 'fail');
                }
            } else {
                wc_add_notice(__('Something went wrong. Please contact your account manager.', 'woocommerce'), 'error');
                return array('result' => 'fail');
            }
        } else {
            wc_add_notice(__('Insufficient credit balance.', 'woocommerce'), 'error');
            return array('result' => 'fail');
        }
    }

    private function send_order_to_api($order, $delivery_date) {
        $api_key = $this->get_token();
        $api_base_url = ggt_get_api_base_url();
        $endpoint = $api_base_url . '/sales-orders/wp-new-order';

        $user_id = $order->get_user_id();
        $user_meta = get_user_meta($user_id);
        
        // EVEN MORE aggressive delivery date checking
        $all_possible_sources = [
            // Meta keys checked in order of priority
            'get_meta' => [
                'ggt_delivery_date',
                'delivery_date', 
                '_delivery_date',
                '_ggt_delivery_date',
                'ggt_delivery_date_selected'
            ],
            
            // POST fields checked in order of priority
            'post' => [
                'ggt_delivery_date', 
                'ggt_delivery_date_hidden', 
                '_delivery_date_backup'
            ],
            
            // Session keys checked in order of priority
            'session' => [
                'ggt_validated_delivery_date'
            ],
            
            // Try any other meta keys that might contain "delivery" and "date"
            'search_meta' => true
        ];
        
        if (empty($delivery_date)) {
            // Check meta keys
            foreach ($all_possible_sources['get_meta'] as $key) {
                $value = $order->get_meta($key);
                if (!empty($value)) {
                    $delivery_date = $value;
                    break;
                }
            }
            
            // Check POST data
            if (empty($delivery_date)) {
                foreach ($all_possible_sources['post'] as $key) {
                    if (!empty($_POST[$key])) {
                        $delivery_date = sanitize_text_field($_POST[$key]);
                        break;
                    }
                }
            }
            
            // Check session
            if (empty($delivery_date) && WC()->session) {
                foreach ($all_possible_sources['session'] as $key) {
                    $value = WC()->session->get($key);
                    if (!empty($value)) {
                        $delivery_date = $value;
                        break;
                    }
                }
            }
            
            // Search any meta keys containing both "delivery" and "date"
            if (empty($delivery_date) && $all_possible_sources['search_meta']) {
                $order_meta = get_post_meta($order->get_id());
                foreach ($order_meta as $key => $values) {
                    if (stripos($key, 'delivery') !== false && stripos($key, 'date') !== false) {
                        $delivery_date = is_array($values) ? reset($values) : $values;
                        break;
                    }
                }
            }
        }
        
        // If still no date, use today + 3 days as fallback
        if (empty($delivery_date)) {
            $delivery_date = date('Y-m-d', strtotime('+3 days'));
            
            // Save this fallback date to the order
            $order->update_meta_data('ggt_delivery_date', $delivery_date);
            $order->update_meta_data('delivery_date', $delivery_date);
            $order->update_meta_data('ggt_delivery_date_fallback', 'true');
            $order->save();
        }

        $formatted_delivery_date = $delivery_date ? date('Y-m-d', strtotime($delivery_date)) : null;
        
        // Look for delivery address information
        $delivery_address = null;
        $delivery_address_json = $order->get_meta('ggt_delivery_info');
        if (!empty($delivery_address_json)) {
            $delivery_address_data = json_decode(stripslashes($delivery_address_json), true);
            if ($delivery_address_data && isset($delivery_address_data['original'])) {
                $delivery_address = $delivery_address_data['original'];
            }
        }
        
        $order_data = array(
            'woocommerce_order_id' => $order->get_id(),
            'user_id'        => $user_id,
            'total'          => $order->get_total(),
            'currency'       => get_woocommerce_currency(),
            'billing'        => $order->get_address('billing'),
            'shipping'       => $order->get_address('shipping'),
            'user_meta'      => $user_meta,
            'items'          => array(),
            'world_pay'     =>  $order->get_payment_method(),
            'payment_provider' => $order->get_payment_method(),
            'payment_provider_title' => $order->get_payment_method_title(),
            'delivery_date'  => $formatted_delivery_date,
            'delivery_address' => $delivery_address // Add delivery address data
        );
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $stock_code = get_post_meta($product->get_id(), '_stockCode', true); // Fetch the stock code
            
            // Calculate unit price (total item price divided by quantity)
            $quantity = $item->get_quantity();
            $total_price = $item->get_total();
            $unit_price = $quantity > 0 ? ($total_price / $quantity) : 0;

            $order_data['items'][] = array(
                'product_id' => $product->get_id(),
                'name'       => $product->get_name(),
                'quantity'   => $quantity,
                'unitPrice'  => round($unit_price, 2), // Unit price per item
                'price'      => $total_price, // Total price for all items of this type
                'stock_code' => $stock_code, // Include the stock code
            );
        }

        // Log the API request
        ggt_log_api_interaction('Credit payment API request', 'info', [
            'endpoint' => $endpoint,
            'method' => 'POST', 
            'payload' => $order_data
        ]);

        $response = wp_remote_post($endpoint, array(
            'method'    => 'POST',
            'timeout'   => 300,
            'headers'   => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body'      => json_encode($order_data),
        ));

        // Log the response
        if (is_wp_error($response)) {
            ggt_log_api_interaction('Credit payment API response error', 'error', [
                'endpoint' => $endpoint,
                'error' => $response->get_error_message()
            ]);
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $body_data = json_decode($body, true);
            
            ggt_log_api_interaction('Credit payment API response', 'info', [
                'endpoint' => $endpoint,
                'status' => $code,
                'response' => $body_data ?: $body
            ]);
        }

        return $response;
    }

    private function get_token()
    {
        if (function_exists('ggt_get_decrypted_token')) {
            return ggt_get_decrypted_token();
        }
        $encrypted_token = get_option('sinappsus_gogeo_codex');
        if ($encrypted_token) {
            return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
        }
        return false;
    }
}

// Register the gateway with WooCommerce
function add_geo_credit_gateway($methods) {
    $methods[] = 'WC_Geo_Credit_Gateway';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_geo_credit_gateway');

// Initialize the gateway
new WC_Geo_Credit_Gateway();