<?php 
// Use only two hooks to minimize conflicts - one early in the process, one right before payment
add_action('woocommerce_before_checkout_form', 'ggt_add_delivery_date_field', 20);
add_action('woocommerce_review_order_before_payment', 'ggt_add_delivery_date_field', 10);

function ggt_add_delivery_date_field($checkout) {
    // Prevent duplicate fields
    if (did_action('ggt_delivery_date_added')) {
        return;
    }
    
    echo '<div id="ggt_delivery_date_field" class="form-row form-row-wide">';
    echo '<h3>' . __('Desired Delivery Date') . '</h3>';
    echo '<p>' . __('Please select your preferred delivery date. Note: Deliveries are not available on weekends, UK public holidays, or within 2 business days from today.') . '</p>';
    echo '<label for="ggt_delivery_date">' . __('Select your desired delivery date') . ' <abbr class="required" title="required">*</abbr></label>';
    echo '<input type="text" class="input-text" name="ggt_delivery_date" id="ggt_delivery_date" placeholder="' . __('Click to select a date') . '" required readonly>';
    echo '</div>';
    
    // Mark that we've added the field
    do_action('ggt_delivery_date_added');
}

// Add a guaranteed fallback method that will definitely work
add_action('wp_footer', 'ggt_delivery_date_field_footer_fallback');
function ggt_delivery_date_field_footer_fallback() {
    if (!is_checkout()) return;
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Wait a bit to allow other scripts to complete
        setTimeout(function() {
            // Only add if the field doesn't exist
            if ($('#ggt_delivery_date').length === 0) {
                console.log('🔄 [GGT] Adding delivery date via footer fallback');
                
                var dateField = `
                    <div id="ggt_delivery_date_field" class="form-row form-row-wide" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f8f8f8;">
                        <h3>Desired Delivery Date</h3>
                        <p>Please select your preferred delivery date. Note: Deliveries are not available on weekends, UK public holidays, or within 2 business days from today.</p>
                        <label for="ggt_delivery_date">Select your desired delivery date <abbr class="required" title="required">*</abbr></label>
                        <input type="text" class="input-text" name="ggt_delivery_date" id="ggt_delivery_date" placeholder="Click to select a date" required readonly>
                    </div>
                `;
                
                // Try several potential insertion points
                var insertionPoints = [
                    '#payment',
                    '.woocommerce-checkout-payment',
                    '.woocommerce-checkout-review-order',
                    '#order_review',
                    '.place-order',
                    '#place_order',
                    '.woocommerce-billing-fields',
                    '.woocommerce-shipping-fields'
                ];
                
                var inserted = false;
                $.each(insertionPoints, function(i, selector) {
                    if (!inserted && $(selector).length) {
                        console.log('🔍 [GGT] Found insertion point: ' + selector);
                        
                        if (selector === '#place_order') {
                            $(selector).parent().before(dateField);
                        } else {
                            $(selector).before(dateField);
                        }
                        
                        inserted = $('#ggt_delivery_date').length > 0;
                        if (inserted) return false;
                    }
                });
                
                // Fallback to just adding it to the end of the checkout form
                if (!inserted && $('form.checkout').length) {
                    $('form.checkout').append(dateField);
                    inserted = $('#ggt_delivery_date').length > 0;
                }
                
                // Initialize the datepicker if possible
                if (inserted && typeof $.fn.datepicker === 'function') {
                    var ukHolidays = [
                        // 2023 UK Holidays
                        '2023-01-02', '2023-04-07', '2023-04-10', '2023-05-01', '2023-05-29', 
                        '2023-08-28', '2023-12-25', '2023-12-26',
                        // 2024 UK Holidays
                        '2024-01-01', '2024-03-29', '2024-04-01', '2024-05-06', '2024-05-27', 
                        '2024-08-26', '2024-12-25', '2024-12-26'
                    ];
                    
                    $('#ggt_delivery_date').datepicker({
                        dateFormat: 'yy-mm-dd',
                        minDate: '+2d',
                        maxDate: '+6m',
                        beforeShowDay: function(date) {
                            var day = date.getDay();
                            if (day === 0 || day === 6) {
                                return [false, '', 'No deliveries on weekends'];
                            }
                            
                            var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                            if ($.inArray(dateString, ukHolidays) !== -1) {
                                return [false, 'uk-holiday', 'No deliveries on UK public holidays'];
                            }
                            
                            return [true, '', ''];
                        }
                    });
                    
                    $('#ggt_delivery_date').on('click', function() {
                        $(this).datepicker('show');
                    });
                    
                    console.log('✅ [GGT] Delivery date field created and initialized via footer fallback');
                }
            }
        }, 1500); // Give more time for the page to finish rendering
    });
    </script>
    <?php
}

// Validate delivery date
add_action('woocommerce_checkout_process', 'ggt_validate_delivery_date');
function ggt_validate_delivery_date() {
    if (empty($_POST['ggt_delivery_date'])) {
        wc_add_notice(__('Please select a delivery date.'), 'error');
        return;
    }
    
    $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
    $date_timestamp = strtotime($delivery_date);
    $today = strtotime('today');
    
    // Check if it's a valid date
    if (!$date_timestamp) {
        wc_add_notice(__('The delivery date is not valid.'), 'error');
        return;
    }
    
    // Ensure the date is at least 2 days in the future
    if ($date_timestamp < strtotime('+2 days', $today)) {
        wc_add_notice(__('The delivery date must be at least 2 business days from today.'), 'error');
        return;
    }
    
    // Check if it's a weekend
    $day_of_week = date('N', $date_timestamp);
    if ($day_of_week >= 6) { // 6 = Saturday, 7 = Sunday
        wc_add_notice(__('Deliveries are not available on weekends.'), 'error');
        return;
    }
    
    // UK public holidays check would be here if we had a programmatic way to check
}

// Save the custom field value to order meta data
add_action('woocommerce_checkout_update_order_meta', 'ggt_save_delivery_date_field');
function ggt_save_delivery_date_field($order_id) {
    if (!empty($_POST['ggt_delivery_date'])) {
        update_post_meta($order_id, 'ggt_delivery_date', sanitize_text_field($_POST['ggt_delivery_date']));
    }
}

// Display the delivery date in admin order page
add_action('woocommerce_admin_order_data_after_billing_address', 'ggt_display_delivery_date_in_admin');
function ggt_display_delivery_date_in_admin($order) {
    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
    if ($delivery_date) {
        echo '<p><strong>' . __('Delivery Date') . ':</strong> ' . date_i18n(get_option('date_format'), strtotime($delivery_date)) . '</p>';
    }
}

// Send order to API on order creation
add_action('woocommerce_checkout_order_processed', 'ggt_send_order_to_api', 10, 1);
function ggt_send_order_to_api($order_id) {
    $order = wc_get_order($order_id);
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    $response = ggt_send_order_to_api_endpoint($order, $delivery_date);

    if (is_wp_error($response)) {
        wc_get_logger()->error('Failed to send order to API: ' . $response->get_error_message(), ['source' => 'geo-credit']);
    }
}

// Update transaction status on payment complete
add_action('woocommerce_payment_complete', 'ggt_update_transaction_status', 10, 1);
function ggt_update_transaction_status($order_id) {
    $order = wc_get_order($order_id);
    $response = ggt_update_order_status_in_api($order, 'paid');

    if (is_wp_error($response)) {
        wc_get_logger()->error('Failed to update transaction status to paid: ' . $response->get_error_message(), ['source' => 'geo-credit']);
    }
}

// Update transaction status on payment failure
add_action('woocommerce_order_status_failed', 'ggt_update_transaction_status_failed', 10, 1);
function ggt_update_transaction_status_failed($order_id) {
    $order = wc_get_order($order_id);
    $response = ggt_update_order_status_in_api($order, 'failed');

    if (is_wp_error($response)) {
        wc_get_logger()->error('Failed to update transaction status to failed: ' . $response->get_error_message(), ['source' => 'geo-credit']);
    }
}

function ggt_send_order_to_api_endpoint($order, $delivery_date) {
    $api_key = ggt_get_decrypted_token();
    $endpoint = 'https://api.gogeothermal.co.uk/api/sales-orders/wp-new-order';

    // Exclude credit payment method
    if ($order->get_payment_method() === 'geo_credit') {
        return;
    }

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

    return $response;
}

function ggt_get_decrypted_token() {
    $encrypted_token = get_option('sinappsus_gogeo_codex');
    if ($encrypted_token) {
        return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
    }
    return false;
}

function ggt_update_order_status_in_api($order, $status) {
    $api_key = get_option('sinappsus_gogeo_codex');
    $endpoint = 'https://api.gogeothermal.co.uk/api/sales-orders/wp-update-order-status';

    $order_data = array(
        'order_id' => $order->get_id(),
        'transaction_status' => $status,
    );

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

    return $response;
}

// Load payment gateway
function ggt_load_credit_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        wc_get_logger()->info('Loading credit payment gateway', ['source' => 'geo-credit']);
        require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/class-credit-payment.php';
    }
}
add_action('plugins_loaded', 'ggt_load_credit_gateway', 11);