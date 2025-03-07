<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Geo_Credit_Gateway extends WC_Payment_Gateway {
    protected $logger;

    public function __construct() {
        $this->id = 'geo_credit';
        $this->title = __('Go Geothermal Credit Payment', 'woocommerce');
        $this->description = __('Use your available credit to checkout.', 'woocommerce');
        $this->has_fields = false;
        $this->method_title = __('Go Geothermal Credit Payment', 'woocommerce');
        $this->method_description = __('Allows Go geothermal credit approved users to pay with credit if their credit limit is sufficient.', 'woocommerce');
        $this->logger = wc_get_logger();

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

        // Get the desired delivery date
        $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);

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
            $response_body = wp_remote_retrieve_body($response);

            // Log the response for debugging
            $this->logger->info('API RESPONSE CODE: ' . $response_code);
            $this->logger->info('API RESPONSE BODY: ' . $response_body);

            if ($response_code == 200) {
                // Mark order as completed
                $order->payment_complete();
                $order->update_status('completed', __('Paid using store credit.', 'woocommerce'));

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                );
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
        $endpoint = 'https://api.gogeothermal.co.uk/api/sales-orders/wp-new-order';

        $user_id = $order->get_user_id();
        $user_meta = get_user_meta($user_id);

        $order_data = array(
            'order_id'    => $order->get_id(),
            'user_id'     => $user_id,
            'total'       => $order->get_total(),
            'currency'    => get_woocommerce_currency(),
            'billing'     => $order->get_address('billing'),
            'shipping'    => $order->get_address('shipping'),
            'user_meta'   => $user_meta, // Include user meta data
            'items'       => array(),
            'delivery_date' => $delivery_date, // Include the delivery date
        );

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $stock_code = get_post_meta($product->get_id(), '_stockCode', true); // Fetch the stock code

            $order_data['items'][] = array(
                'product_id' => $product->get_id(),
                'name'       => $product->get_name(),
                'quantity'   => $item->get_quantity(),
                'price'      => $item->get_total(),
                'stock_code' => $stock_code, // Include the stock code
            );
        }

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

        // Log the response for debugging
        $this->logger->info('API RESPONSE: ' . print_r($response, true));

        return $response;
    }

    private function get_token()
    {
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