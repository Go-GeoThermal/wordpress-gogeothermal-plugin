<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// include api call custom class for authenticated requests
require_once GGT_SINAPPSUS_PLUGIN_PATH . '/utils/class-api-connector.php';

// Load credit payment gateway
function ggt_load_credit_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/class-credit-payment.php';
    }
}
add_action('plugins_loaded', 'ggt_load_credit_gateway', 11);

// Add credit payment option to checkout
function ggt_add_credit_payment_option() {
    if (!is_checkout()) return;
    
    // Check if user has credit limit
    $user_id = get_current_user_id();
    $credit_limit = get_user_meta($user_id, 'CreditLimit', true);
    if (empty($credit_limit)) {
        $credit_limit = get_user_meta($user_id, 'creditLimit', true);
    }
    if (empty($credit_limit)) {
        $credit_limit = get_user_meta($user_id, 'credit_limit', true);
    }
    
    if (empty($credit_limit)) return;
    
    // Add very simple payment option field at checkout
    add_action('woocommerce_after_order_notes', function($checkout) use ($credit_limit) {
        echo '<div style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #ddd;">';
        echo '<h3>Payment Method</h3>';
        
        echo '<div style="margin: 10px 0;">';
        echo '<label>';
        echo '<input type="checkbox" name="pay_with_credit" value="yes" checked> ';
        echo '<strong>Pay with Credit</strong>';
        echo '</label>';
        echo '<p style="margin: 5px 0 0 25px; color: #666;">Use your available credit of ' . wc_price($credit_limit) . ' to complete this purchase.</p>';
        echo '</div>';
        
        // Add hidden field for payment method
        echo '<input type="hidden" name="payment_method" value="geo_credit">';
        
        echo '</div>';
    });
    
    // Process the order with the credit payment method
    add_action('woocommerce_checkout_order_processed', function($order_id) {
        if (isset($_POST['pay_with_credit']) && $_POST['pay_with_credit'] == 'yes') {
            $order = wc_get_order($order_id);
            $order->set_payment_method('geo_credit');
            $order->save();
            
            // Debug info
            error_log('Order #' . $order_id . ' set to use credit payment method');
        }
    });
}
add_action('template_redirect', 'ggt_add_credit_payment_option');