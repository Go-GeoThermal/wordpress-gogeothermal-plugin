<?php
/**
 * Fix for Divi/WooCommerce checkout payment methods display
 */

if (!defined('ABSPATH')) {
    exit;
}

// Special fix for Divi checkout
function ggt_divi_payment_methods_fix() {
    if (!is_checkout()) return;
    
    // Only run if we detect Divi
    if (!class_exists('ET_Builder_Plugin') && !function_exists('et_setup_theme')) {
        wc_get_logger()->info('Divi not detected, skipping payment fix', ['source' => 'checkout-fix']);
        return;
    }
    
    wc_get_logger()->info('Divi detected, applying payment method fix', ['source' => 'checkout-fix']);
    
    // Add our payment method UI to Divi checkout
    add_action('woocommerce_review_order_before_submit', 'ggt_inject_payment_methods_ui', 5);
    
    // Add required CSS for our payment methods display
    add_action('wp_head', function() {
        ?>
        <style>
        .ggt-payment-methods {
            margin: 30px 0 20px;
            padding: 20px;
            background: #f7f7f7;
            border: 1px solid #d3d3d3;
            border-radius: 3px;
        }
        .ggt-payment-methods h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            color: #333;
        }
        .ggt-payment-methods ul {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .ggt-payment-methods li {
            margin-bottom: 15px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .ggt-payment-methods li.active {
            border-color: #7f54b3;
            box-shadow: 0 0 5px rgba(127,84,179,0.3);
        }
        .ggt-payment-methods label {
            font-weight: bold;
            cursor: pointer;
            display: inline-block;
            margin-left: 5px;
        }
        .ggt-payment-methods .payment-description {
            margin-top: 8px;
            margin-left: 25px;
            font-size: 14px;
            color: #666;
        }
        </style>
        <?php
    });
    
    // Add JavaScript to make the payment methods work
    add_action('wp_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Initialize payment methods
            function initPaymentMethods() {
                console.log('Initializing custom payment methods');
                
                // Set up click handler for payment method selection
                $('.ggt-payment-method-input').on('change', function() {
                    const selectedMethod = $(this).val();
                    console.log('Selected payment method:', selectedMethod);
                    
                    // Update active class
                    $('.ggt-payment-methods li').removeClass('active');
                    $(this).closest('li').addClass('active');
                    
                    // Update hidden WooCommerce payment method field
                    $('#ggt-real-payment-method').val(selectedMethod);
                    
                    // Trigger update checkout to ensure WooCommerce recognizes the change
                    $(document.body).trigger('update_checkout');
                });
                
                // Select first payment method by default
                $('.ggt-payment-method-input').first().prop('checked', true).trigger('change');
                
                // Handle form submission
                $('form.checkout').on('checkout_place_order', function() {
                    const selectedMethod = $('#ggt-real-payment-method').val();
                    console.log('Submitting order with payment method:', selectedMethod);
                    
                    // Set the real WooCommerce payment method field
                    if (!$('input[name="payment_method"]').length) {
                        $('form.checkout').append('<input type="hidden" name="payment_method" value="' + selectedMethod + '">');
                    } else {
                        $('input[name="payment_method"]').val(selectedMethod);
                    }
                    
                    return true;
                });
            }
            
            // Initialize on page load and on updated_checkout event
            initPaymentMethods();
            $(document.body).on('updated_checkout', initPaymentMethods);
        });
        </script>
        <?php
    });
}
add_action('template_redirect', 'ggt_divi_payment_methods_fix');

// Function to inject our custom payment methods UI
function ggt_inject_payment_methods_ui() {
    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    
    if (empty($available_gateways)) {
        return;
    }
    
    echo '<div class="ggt-payment-methods">';
    echo '<h3>Payment Methods</h3>';
    echo '<ul>';
    
    foreach ($available_gateways as $gateway) {
        echo '<li class="payment-method-' . esc_attr($gateway->id) . '">';
        echo '<input type="radio" class="ggt-payment-method-input" id="ggt-payment-' . esc_attr($gateway->id) . '" name="ggt_payment_method" value="' . esc_attr($gateway->id) . '">';
        echo '<label for="ggt-payment-' . esc_attr($gateway->id) . '">' . esc_html($gateway->get_title()) . '</label>';
        
        if ($gateway->get_description()) {
            echo '<div class="payment-description">' . wp_kses_post($gateway->get_description()) . '</div>';
        }
        
        echo '</li>';
    }
    
    echo '</ul>';
    
    // Hidden field to store the selected payment method
    echo '<input type="hidden" id="ggt-real-payment-method" name="ggt_real_payment_method" value="">';
    
    // Add debug info if needed
    if (isset($_GET['debug_payment'])) {
        echo '<div style="margin-top:15px; padding:10px; background:#ffe; border:1px solid #ddd;">';
        echo '<strong>Available payment methods:</strong> ' . implode(', ', array_keys($available_gateways));
        echo '</div>';
    }
    
    echo '</div>';
}

// Handle payment method selection and validation
function ggt_handle_payment_method_selection() {
    if (!is_ajax() && isset($_POST['ggt_real_payment_method'])) {
        // Set the real payment method from our custom field
        $_POST['payment_method'] = sanitize_text_field($_POST['ggt_real_payment_method']);
    }
}
add_action('woocommerce_checkout_process', 'ggt_handle_payment_method_selection');