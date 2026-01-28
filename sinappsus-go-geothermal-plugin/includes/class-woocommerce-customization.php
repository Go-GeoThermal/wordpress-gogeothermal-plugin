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

// Fix validation and saving of delivery date
add_action('woocommerce_checkout_process', 'ggt_validate_delivery_date');
function ggt_validate_delivery_date() {
    // Debug logging
    $debug_post = $_POST;
    // Remove sensitive data
    unset($debug_post['payment_method'], $debug_post['woocommerce_checkout_place_order'], $debug_post['_wpnonce']);
    error_log('GGT Validation: Starting validation. POST keys: ' . implode(', ', array_keys($debug_post)));
    if (isset($_POST['ggt_delivery_date'])) {
        error_log('GGT Validation: ggt_delivery_date value: "' . $_POST['ggt_delivery_date'] . '"');
    } else {
        error_log('GGT Validation: ggt_delivery_date is NOT set');
    }

    // Check for the delivery date in multiple possible form fields
    $delivery_date = null;
    
    // Prioritize the hidden field which is ISO formatted by JS
    if (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
        error_log("GGT Validation: Using hidden field value: '$delivery_date'");
    } elseif (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
        error_log("GGT Validation: Using main field value: '$delivery_date'");
        
        // Handle UK date format (dd/mm/yyyy) -> convert to d-m-y for strtotime
        if (strpos($delivery_date, '/') !== false) {
            $delivery_date = str_replace('/', '-', $delivery_date);
            error_log("GGT Validation: Converted UK format to: '$delivery_date'");
        }
    }
    
    /* 
    // Disable loose fallback loop as it might be picking up irrelevant fields
    if (empty($delivery_date)) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'delivery_date') !== false && !empty($value)) {
                error_log("GGT Validation: Found fallback field '$key' with value '$value'");
                $delivery_date = sanitize_text_field($value);
                break;
            }
        }
    }
    */
    
    if (!empty($delivery_date)) {
        error_log("GGT Validation: Final delivery_date to validate: '$delivery_date'");
    } else {
        error_log("GGT Validation: No delivery date found. Adding error notice.");
    }
    
    // If no date is found in any field, show error
    if (empty($delivery_date) || $delivery_date === 'undefined' || $delivery_date === 'null') {
        wc_add_notice(__('Please select a delivery date.', 'woocommerce'), 'error');
        return;
    }
    
    $date_timestamp = strtotime($delivery_date);
    $today = strtotime('today');
    
    // Check if it's a valid date
    if (!$date_timestamp) {
        wc_add_notice(__('The delivery date is not valid.', 'woocommerce'), 'error');
        return;
    }
    
    // Calculate minimum allowed date based on cutoff time
    $min_days = function_exists('ggt_calculate_min_delivery_offset') ? ggt_calculate_min_delivery_offset() : 1;
    
    // Ensure the date is valid based on our cutoff rules
    if ($date_timestamp < strtotime("+$min_days days", $today)) {
        wc_add_notice(__('Please select a valid delivery date from the calendar.', 'woocommerce'), 'error');
        return;
    }
    
    // Check if it's a weekend
    $day_of_week = date('N', $date_timestamp);
    if ($day_of_week >= 6) { // 6 = Saturday, 7 = Sunday
        wc_add_notice(__('Deliveries are not available on weekends.', 'woocommerce'), 'error');
        return;
    }
    
    // Store validated date in session for later use
    if (WC()->session) {
        WC()->session->set('ggt_validated_delivery_date', $delivery_date);
    }
}

// Add modern validation hook as a backup
add_action('woocommerce_after_checkout_validation', 'ggt_validate_delivery_date_modern', 10, 2);
function ggt_validate_delivery_date_modern($data, $errors) {
    error_log('GGT Validation Modern: Starting validation.');
    
    $delivery_date = null;
    
    // Prioritize the hidden field which is ISO formatted by JS
    if (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
        
        // Handle UK date format (dd/mm/yyyy) -> convert to d-m-y for strtotime
        if (strpos($delivery_date, '/') !== false) {
            $delivery_date = str_replace('/', '-', $delivery_date);
        }
    }
    
    /*
    if (empty($delivery_date)) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'delivery_date') !== false && !empty($value)) {
                $delivery_date = sanitize_text_field($value);
                break;
            }
        }
    }
    */
    
    if (empty($delivery_date) || $delivery_date === 'undefined' || $delivery_date === 'null') {
        $errors->add('delivery_date_required', __('Please select a delivery date.', 'woocommerce'));
    }
}

// Remove all previous hooks for saving delivery date to prevent conflicts
remove_action('woocommerce_checkout_update_order_meta', 'ggt_save_delivery_date_field');

// Improved saving of delivery date with comprehensive checks
add_action('woocommerce_checkout_update_order_meta', 'ggt_improved_save_delivery_date', 20);
function ggt_improved_save_delivery_date($order_id) {
    $delivery_date = null;
    
    // Check multiple sources for the delivery date
    // Prioritize the hidden field which is ISO formatted by JS
    if (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
        
        // Handle UK date format (dd/mm/yyyy) -> convert to d-m-y for strtotime/saving
        if (strpos($delivery_date, '/') !== false) {
            $delivery_date = str_replace('/', '-', $delivery_date);
        }
    } elseif (WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
    }
    
    /*
    else {
        // Last attempt - check any POST field with delivery_date in the name
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'delivery_date') !== false && !empty($value)) {
                $delivery_date = sanitize_text_field($value);
                break;
            }
        }
    }
    */
    
    if (!empty($delivery_date)) {
        // Save using multiple meta keys for compatibility
        update_post_meta($order_id, 'ggt_delivery_date', $delivery_date);
        update_post_meta($order_id, '_delivery_date', $delivery_date);
        update_post_meta($order_id, '_ggt_delivery_date', $delivery_date);
        update_post_meta($order_id, 'delivery_date', $delivery_date);
        
        // Also add it directly to the order object
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_meta_data('ggt_delivery_date', $delivery_date);
            $order->update_meta_data('_delivery_date', $delivery_date);
            $order->update_meta_data('delivery_date', $delivery_date);
            $order->save();
            
            // Force the order to include this field in all API responses
            add_post_meta($order_id, '_delivery_date_included', 'yes', true);
        }
        
        // Clear the session data
        if (WC()->session) {
            WC()->session->__unset('ggt_validated_delivery_date');
        }
    }
}

// Ensure we capture the delivery date at order creation time with highest priority
add_action('woocommerce_checkout_create_order', 'ggt_capture_delivery_date_early', 5, 2);
function ggt_capture_delivery_date_early($order, $data) {
    $delivery_date = null;
    
    // Check all possible sources for the date
    // Prioritize the hidden field which is ISO formatted by JS
    if (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
        
        // Handle UK date format (dd/mm/yyyy) -> convert to d-m-y
        if (strpos($delivery_date, '/') !== false) {
            $delivery_date = str_replace('/', '-', $delivery_date);
        }
    }
}

// Also hook into direct order creation
add_action('woocommerce_checkout_create_order', 'ggt_add_delivery_date_to_order', 20, 2);
function ggt_add_delivery_date_to_order($order, $data) {
    // Get delivery date from various possible sources
    $delivery_date = null;
    
    // Prioritize the hidden field which is ISO formatted by JS
    if (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
        
        // Handle UK date format (dd/mm/yyyy) -> convert to d-m-y
        if (strpos($delivery_date, '/') !== false) {
            $delivery_date = str_replace('/', '-', $delivery_date);
        }
    } elseif (WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
    }
    
    if (!empty($delivery_date)) {
        // Save the delivery date to the order object directly
        $order->update_meta_data('ggt_delivery_date', $delivery_date);
        $order->update_meta_data('_delivery_date', $delivery_date);
    }
}

add_action('woocommerce_checkout_create_order', 'ggt_store_selected_delivery_data', 12, 2);
function ggt_store_selected_delivery_data($order, $data) {
    // Store delivery information (selected address)
    if (!empty($_POST['ggt_delivery_info'])) {
        $delivery_info = sanitize_text_field($_POST['ggt_delivery_info']);
        
        // Store the raw JSON data
        $order->update_meta_data('ggt_delivery_info', $delivery_info);
        
        // Also parse and store individual fields for easier access
        $address_data = json_decode(stripslashes($delivery_info), true);
        if ($address_data && isset($address_data['mapped'])) {
            foreach ($address_data['mapped'] as $key => $value) {
                $order->update_meta_data('ggt_shipping_' . $key, sanitize_text_field($value));
            }
            
            // Also save the original API address data
            if (isset($address_data['original'])) {
                $order->update_meta_data('ggt_delivery_address_original', json_encode($address_data['original']));
                
                // Save individual original fields too
                foreach ($address_data['original'] as $key => $value) {
                    $order->update_meta_data('ggt_delivery_orig_' . $key, sanitize_text_field($value));
                }
            }
        }
    }
    
    // Store delivery date
    if (!empty($_POST['ggt_delivery_date'])) {
        $order->update_meta_data('ggt_delivery_date_selected', sanitize_text_field($_POST['ggt_delivery_date']));
    } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
        $order->update_meta_data('ggt_delivery_date_selected', sanitize_text_field($_POST['ggt_delivery_date_hidden']));
    }
}

// Register the delivery date as a custom order field to ensure it shows in admin and REST API
add_filter('woocommerce_api_order_response', 'ggt_add_delivery_date_to_api_response', 10, 2);
function ggt_add_delivery_date_to_api_response($order_data, $order) {
    $order_id = $order->get_id();
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    
    // Fallback checks
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, 'ggt_delivery_date_selected', true);
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, '_delivery_date', true);
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, 'delivery_date', true);
    
    if (!empty($delivery_date)) {
        $order_data['delivery_date'] = $delivery_date;
    }
    
    return $order_data;
}

// Display the delivery date in admin order page (enhanced)
add_action('woocommerce_admin_order_data_after_billing_address', 'ggt_display_delivery_date_in_admin');
function ggt_display_delivery_date_in_admin($order) {
    $order_id = $order->get_id();
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    
    // Fallback checks for other meta keys
    if (empty($delivery_date)) {
        $delivery_date = get_post_meta($order_id, 'ggt_delivery_date_selected', true);
    }
    if (empty($delivery_date)) {
        $delivery_date = get_post_meta($order_id, '_delivery_date', true);
    }
    if (empty($delivery_date)) {
        $delivery_date = get_post_meta($order_id, 'delivery_date', true);
    }

    if ($delivery_date) {
        $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
        echo '<div class="order_data_column" style="clear:both; margin-top:15px; padding:10px; background:#f8f8f8; border:1px solid #e5e5e5;">';
        echo '<h4>' . __('Delivery Details', 'woocommerce') . '</h4>';
        echo '<p><strong>' . __('Delivery Date') . ':</strong> ' . $formatted_date . ' <span style="color:#888;">(' . $delivery_date . ')</span></p>';
        echo '</div>';
    } else {
        echo '<div class="order_data_column" style="clear:both; margin-top:15px; padding:10px; background:#fff8e5; border:1px solid #f0e4d0;">';
        echo '<h4>' . __('Delivery Details', 'woocommerce') . '</h4>';
        echo '<p><strong>' . __('Delivery Date') . ':</strong> <span style="color:#b32d2e;">Not specified</span></p>';
        echo '</div>';
    }
}

// Display delivery date on customer order page and emails (enhanced)
add_action('woocommerce_order_details_after_order_table', 'ggt_display_delivery_date_on_order_details');
add_action('woocommerce_email_order_meta', 'ggt_display_delivery_date_on_order_details');
function ggt_display_delivery_date_on_order_details($order) {
    $order_id = $order->get_id();
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    
    // Fallback checks
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, 'ggt_delivery_date_selected', true);
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, '_delivery_date', true);
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, 'delivery_date', true);

    if ($delivery_date) {
        $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
        echo '<div class="ggt-delivery-date-display">';
        echo '<h2>' . __('Delivery Date', 'woocommerce') . '</h2>';
        echo '<p><strong>' . $formatted_date . '</strong></p>';
        echo '</div>';
    }
}

// Also display in order details meta section
add_action('woocommerce_order_details_before_order_table', 'ggt_display_delivery_date_before_order_table');
function ggt_display_delivery_date_before_order_table($order) {
    // Display prominently at the top of the order details
    $order_id = $order->get_id();
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    
    // Fallback checks
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, 'ggt_delivery_date_selected', true);
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, '_delivery_date', true);
    if (empty($delivery_date)) $delivery_date = get_post_meta($order_id, 'delivery_date', true);

    if ($delivery_date) {
        $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
        echo '<div class="ggt-delivery-summary">';
        echo '<p>' . __('Your order is scheduled for delivery on', 'woocommerce') . ' <strong>' . $formatted_date . '</strong></p>';
        echo '</div>';
    }
}

// Add delivery date to order emails
add_filter('woocommerce_email_order_meta_fields', 'ggt_add_delivery_date_to_emails', 10, 3);
function ggt_add_delivery_date_to_emails($fields, $sent_to_admin, $order) {
    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
    if ($delivery_date) {
        $fields['ggt_delivery_date'] = array(
            'label' => __('Delivery Date', 'woocommerce'),
            'value' => date_i18n(get_option('date_format'), strtotime($delivery_date)),
        );
    }
    return $fields;
}

// Send order to API on order creation with better logging
add_action('woocommerce_checkout_order_processed', 'ggt_send_order_to_api', 10, 1);
function ggt_send_order_to_api($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) { return; }
    // Early store payment method meta for reliability
    $order->update_meta_data('ggt_payment_method', $order->get_payment_method());
    $order->update_meta_data('ggt_payment_method_title', $order->get_payment_method_title());
    $order->save();

    // Only attempt immediate send for on-site gateways (credit or others). External gateways may complete later.
    $payment_method = $order->get_payment_method();
    $is_external_gateway = apply_filters('ggt_is_external_gateway', in_array($payment_method, ['worldpay', 'paypal', 'stripe']) );

    // Always queue attempt; if external, the later hooks will retry and mark sent.
    ggt_attempt_sales_order_send($order, 'checkout_order_processed');
}

// Central function to send order to API endpoint with proper logging
function ggt_send_order_to_api_endpoint($order, $delivery_date) {
    $api_key = ggt_get_decrypted_token();
    $api_base_url = ggt_get_api_base_url();
    $endpoint = $api_base_url . '/sales-orders/wp-new-order';
    $payment_method = $order->get_payment_method();
    $payment_method_title = $order->get_payment_method_title();

    // Idempotency: do not send twice
    if ($order->get_meta('ggt_sales_order_sent')) {
        return new WP_Error('already_sent', 'Sales order already sent');
    }

    // If no token yet, queue for retry and abort
    if (empty($api_key)) {
        ggt_queue_pending_sales_order($order->get_id(), 'missing_token');
        return new WP_Error('missing_token', 'API token missing; queued for retry');
    }
    
    $user_id = $order->get_user_id();
    $user_meta = get_user_meta($user_id);
    
    // If delivery date wasn't passed as a parameter or is empty, try all possible meta keys
    if (empty($delivery_date)) {
        $meta_keys_to_try = [
            'ggt_delivery_date',
            '_delivery_date',
            '_ggt_delivery_date',
            'delivery_date'
        ];
        
        foreach ($meta_keys_to_try as $key) {
            $meta_value = get_post_meta($order->get_id(), $key, true);
            if (!empty($meta_value)) {
                $delivery_date = $meta_value;
                break;
            }
        }
    }
    
    // Also check the session as a last resort
    if (empty($delivery_date) && WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
    }
    
    // Format delivery date to ensure consistent API format
    $formatted_delivery_date = $delivery_date ? date('Y-m-d', strtotime($delivery_date)) : null;
    
    // Get selected delivery address data
    $delivery_address = null;
    $delivery_address_json = $order->get_meta('ggt_delivery_info');
    if (!empty($delivery_address_json)) {
        $delivery_address_data = json_decode(stripslashes($delivery_address_json), true);
        if ($delivery_address_data && isset($delivery_address_data['original'])) {
            $delivery_address = $delivery_address_data['original'];
        }
    }
    
    // Calculate shipping gross total (Net + Tax) with fallback
    // User requested "shipping total with its vat" to be passed as carrNet
    $carrNet = floatval($order->get_shipping_total()) + floatval($order->get_shipping_tax());
    
    if (empty($carrNet) || floatval($carrNet) == 0) {
        $shipping_total = 0;
        foreach ($order->get_shipping_methods() as $shipping_item) {
            $shipping_total += floatval($shipping_item->get_total()) + floatval($shipping_item->get_total_tax());
        }
        $carrNet = $shipping_total;
    }

    $order_data = [
        'woocommerce_order_id' => $order->get_id(),
        'user_id'              => $user_id,
        'total'                => $order->get_total(),
        'currency'             => get_woocommerce_currency(),
        'billing'              => $order->get_address('billing'),
        'shipping'             => $order->get_address('shipping'),
        'user_meta'            => $user_meta,
        'items'                => [],
        // Backwards compatibility field name remained as world_pay (legacy consumers)
        'world_pay'            => $payment_method,
        // New explicit payment provider fields
        'payment_provider'     => $payment_method,
        'payment_provider_title' => $payment_method_title,
        'delivery_date'        => $formatted_delivery_date,
        'delivery_address'     => $delivery_address,
        'customer_note'        => $order->get_customer_note(),
        'carrNet'              => $carrNet
    ];

    // =============================
    // TEMP: Verbose payload logging for QA (remove after verification)
    // This logs the full payload being sent to the API to help validate mapping and content.
    // =============================
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info('TEMP PAYLOAD LOG: sales order payload', [
            'source' => 'ggt-payload-debug',
            'order_id' => $order->get_id(),
            'endpoint' => $endpoint,
            'payment_provider' => $payment_method,
            'shipping_debug' => [
                'get_shipping_total' => $order->get_shipping_total(),
                'calculated_carrNet' => $carrNet
            ],
            'payload' => $order_data,
        ]);
    } else {
        error_log('[GGT TEMP PAYLOAD] ' . json_encode($order_data));
    }
    
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) continue;
        
        $stock_code = get_post_meta($product->get_id(), '_stockCode', true);

        $quantity = $item->get_quantity();
            $total_price = $item->get_total();
            $unit_price = $quantity > 0 ? ($total_price / $quantity) : 0;
        
        $order_data['items'][] = [
            'product_id' => $product->get_id(),
            'name'       => $item->get_name(),
            'quantity'   => $item->get_quantity(),
            'unitPrice'  => round($unit_price, 2), // Unit price per item
            'price'      => $item->get_total(),
            'stock_code' => $stock_code,
        ];
    }
    
    // Log the API request
    if (function_exists('ggt_log_api_interaction')) {
        ggt_log_api_interaction('Request to sales-orders API', 'info', [
            'endpoint' => $endpoint,
            'method' => 'POST',
            'payload' => $order_data
        ]);
    }

    $response = wp_remote_post($endpoint, [
        'method'    => 'POST',
        'timeout'   => 300,
        'headers'   => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body'      => json_encode($order_data),
    ]);
    
    // Log the response
    if (function_exists('ggt_log_api_interaction')) {
        if (is_wp_error($response)) {
            ggt_log_api_interaction('API Response Error', 'error', [
                'endpoint' => $endpoint,
                'error' => $response->get_error_message()
            ]);
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $body_data = json_decode($body, true);
            
            ggt_log_api_interaction('API Response', 'info', [
                'endpoint' => $endpoint,
                'status' => $code,
                'response' => $body_data ?: $body
            ]);
        }
    }
    
    // Mark sent if successful 200 response
    if (!is_wp_error($response)) {
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            $order->update_meta_data('ggt_sales_order_sent', 1);
            $order->update_meta_data('ggt_sales_order_sent_at', current_time('mysql'));
            $order->save();
            // Remove from queue if exists
            ggt_dequeue_pending_sales_order($order->get_id());
        } elseif ($code == 401) {
            // Check for specific "User is not approved" message
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['message']) && strpos($data['message'], 'User is not approved') !== false) {
                ggt_handle_account_not_found_error($order);
                // We don't queue for retry because it requires manual intervention
                ggt_dequeue_pending_sales_order($order->get_id());
            } else {
                ggt_queue_pending_sales_order($order->get_id(), 'http_' . $code);
            }
        } else {
            ggt_queue_pending_sales_order($order->get_id(), 'http_' . $code);
        }
    } else {
        ggt_queue_pending_sales_order($order->get_id(), 'wp_error');
    }

    return $response;
}

// function ggt_get_decrypted_token() {
//     $encrypted_token = get_option('sinappsus_gogeo_codex');
//     if ($encrypted_token) {
//         return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
//     }
//     return false;
// }

function ggt_update_order_status_in_api($order, $status) {
    $api_key = ggt_get_decrypted_token();
    $api_base_url = ggt_get_api_base_url();
    $endpoint = $api_base_url . '/sales-orders/wp-update-order-status';

    $order_data = array(
        'order_id' => $order->get_id(),
        'transaction_status' => $status,
    );

    // Log the API request
    ggt_log_api_interaction('Update order status request', 'info', [
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
        ggt_log_api_interaction('Update status API response error', 'error', [
            'endpoint' => $endpoint,
            'error' => $response->get_error_message()
        ]);
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $body_data = json_decode($body, true);
        
        ggt_log_api_interaction('Update status API response', 'info', [
            'endpoint' => $endpoint,
            'status' => $code,
            'response' => $body_data ?: $body
        ]);
    }

    return $response;
}

// Update transaction status on payment complete
add_action('woocommerce_payment_complete', 'ggt_update_transaction_status', 10, 1);
function ggt_update_transaction_status($order_id) {
    $order = wc_get_order($order_id);
    ggt_update_order_status_in_api($order, 'paid');
    // Attempt send if not yet sent (external gateways often finalize here)
    ggt_attempt_sales_order_send($order, 'payment_complete');
}

// Update transaction status on payment failure
add_action('woocommerce_order_status_failed', 'ggt_update_transaction_status_failed', 10, 1);
function ggt_update_transaction_status_failed($order_id) {
    $order = wc_get_order($order_id);
    ggt_update_order_status_in_api($order, 'failed');
}

// Attempt send when order status transitions to processing or completed and not yet sent
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status, $order){
    if (in_array($new_status, ['processing','completed','on-hold'])) {
        ggt_attempt_sales_order_send($order, 'status_changed_' . $new_status);
    }
}, 10, 4);

/**
 * Wrapper to attempt sending sales order payload if not sent.
 */
function ggt_attempt_sales_order_send($order, $trigger){
    if (!$order || $order->get_meta('ggt_sales_order_sent')) { return; }
    // Let the Credit gateway handle its own send to avoid duplicates
    if ($order->get_payment_method() === 'geo_credit') { return; }
    // Avoid duplicate rapid attempts within 5 seconds
    $last_attempt = $order->get_meta('ggt_sales_order_last_attempt');
    if ($last_attempt && (time() - intval($last_attempt)) < 5) { return; }
    $order->update_meta_data('ggt_sales_order_last_attempt', time());
    $order->save();

    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
    $response = ggt_send_order_to_api_endpoint($order, $delivery_date);

    if (is_wp_error($response)) {
        ggt_log_api_interaction('Sales order send failed, queued', 'error', [
            'order_id' => $order->get_id(),
            'trigger' => $trigger,
            'error_code' => $response->get_error_code(),
            'error_message' => $response->get_error_message()
        ]);
    } else {
        $code = wp_remote_retrieve_response_code($response);
        ggt_log_api_interaction('Sales order send attempt', 'info', [
            'order_id' => $order->get_id(),
            'trigger' => $trigger,
            'status' => $code
        ]);
    }
}

// Queue management helpers
function ggt_queue_pending_sales_order($order_id, $reason){
    $pending = get_option('ggt_pending_sales_orders', []);
    if (!is_array($pending)) { $pending = []; }
    $pending[$order_id] = [
        'reason' => $reason,
        'last_attempt' => current_time('mysql'),
        'attempts' => isset($pending[$order_id]['attempts']) ? $pending[$order_id]['attempts'] + 1 : 1,
    ];
    update_option('ggt_pending_sales_orders', $pending, false);
}

function ggt_dequeue_pending_sales_order($order_id){
    $pending = get_option('ggt_pending_sales_orders', []);
    if (isset($pending[$order_id])) {
        unset($pending[$order_id]);
        update_option('ggt_pending_sales_orders', $pending, false);
    }
}

// Retry pending sales orders on init (lightweight)
add_action('init', function(){
    $pending = get_option('ggt_pending_sales_orders', []);
    if (empty($pending) || !is_array($pending)) { return; }
    foreach ($pending as $order_id => $meta){
        $order = wc_get_order($order_id);
        if (!$order) { ggt_dequeue_pending_sales_order($order_id); continue; }
        if ($order->get_meta('ggt_sales_order_sent')) { ggt_dequeue_pending_sales_order($order_id); continue; }
        // Exponential backoff: skip if attempts >5 and last attempt <1 hour ago
        $attempts = isset($meta['attempts']) ? intval($meta['attempts']) : 0;
        $last_attempt_time = strtotime($meta['last_attempt']);
        $now = time();
        $min_delay = min( (pow(2, $attempts) * 60), 6*3600 ); // cap at 6h
        if (($now - $last_attempt_time) < $min_delay) { continue; }
        ggt_attempt_sales_order_send($order, 'retry_attempt');
    }
});

// Load payment gateway
function ggt_load_credit_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        require_once GGT_SINAPPSUS_PLUGIN_PATH . '/includes/class-credit-payment.php';
    }
}
add_action('plugins_loaded', 'ggt_load_credit_gateway', 11);

add_action('woocommerce_checkout_create_order', 'ggt_save_shipping_fields_to_meta', 15, 2);
function ggt_save_shipping_fields_to_meta($order, $data) {
    $fields = array(
        'shipping_first_name',
        'shipping_last_name',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
    );
    foreach ($fields as $field) {
        $key = 'ggt_' . $field;
        if (!empty($_POST[$key])) {
            $order->update_meta_data($key, sanitize_text_field($_POST[$key]));
        }
    }
}

// AJAX handler to store delivery date in session
add_action('wp_ajax_ggt_store_delivery_date', 'ggt_ajax_store_delivery_date');
add_action('wp_ajax_nopriv_ggt_store_delivery_date', 'ggt_ajax_store_delivery_date');

function ggt_ajax_store_delivery_date() {
    check_ajax_referer('ggt_checkout_nonce', 'nonce');
    
    if (!empty($_POST['delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['delivery_date']);
        WC()->session->set('ggt_validated_delivery_date', $delivery_date);
        
        wp_send_json_success(['saved' => true]);
    } else {
        wp_send_json_error(['saved' => false]);
    }
    
    wp_die();
}

// Add redundant check on checkout process to catch the backup value
add_action('woocommerce_checkout_process', 'ggt_redundant_delivery_date_check', 5);
function ggt_redundant_delivery_date_check() {
    if (empty($_POST['ggt_delivery_date']) && empty($_POST['ggt_delivery_date_hidden']) && !empty($_POST['_delivery_date_backup'])) {
        // Found backup date, copy it to proper fields
        $_POST['ggt_delivery_date_hidden'] = sanitize_text_field($_POST['_delivery_date_backup']);
    }
}

// Extra safety for capturing delivery date
add_action('woocommerce_checkout_create_order', 'ggt_emergency_delivery_date_capture', 1, 2);
function ggt_emergency_delivery_date_capture($order, $data) {
    $delivery_date = null;
    
    // Check all possible sources in descending priority
    if (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
    } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (!empty($_POST['_delivery_date_backup'])) {
        $delivery_date = sanitize_text_field($_POST['_delivery_date_backup']);
    } elseif (WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
    }
    
    if (!empty($delivery_date)) {
        // Save it immediately so it doesn't get lost
        $order->update_meta_data('ggt_delivery_date', $delivery_date);
        $order->update_meta_data('delivery_date', $delivery_date);
    }
}

// AJAX handler to update user shipping address in database
add_action('wp_ajax_ggt_update_user_shipping_address', 'ggt_ajax_update_user_shipping_address');
add_action('wp_ajax_nopriv_ggt_update_user_shipping_address', 'ggt_ajax_update_user_shipping_address');

function ggt_ajax_update_user_shipping_address() {
    check_ajax_referer('ggt_checkout_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $user_id = get_current_user_id();
    $shipping_address = $_POST['shipping_address'] ?? [];
    $delivery_info = $_POST['delivery_info'] ?? '';
    
    if (empty($shipping_address)) {
        wp_send_json_error('No shipping address provided');
        return;
    }
    
    // Update user meta with shipping address fields, including new ones
    $allowed_fields = [
        'shipping_first_name',
        'shipping_last_name', 
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'shipping_phone',
        'shipping_email'
    ];
    
    foreach ($shipping_address as $field => $value) {
        if (in_array($field, $allowed_fields)) {
            update_user_meta($user_id, $field, sanitize_text_field($value));
        }
    }
    
    // Also store the delivery info for order processing
    if (!empty($delivery_info)) {
        update_user_meta($user_id, 'ggt_selected_delivery_info', $delivery_info);
    }
    
    // Store timestamp of when address was updated
    update_user_meta($user_id, 'ggt_shipping_address_updated', current_time('mysql'));
    
    wp_send_json_success([
        'message' => 'Shipping address updated successfully',
        'updated_fields' => array_keys($shipping_address)
    ]);
}

// Helper to handle account not found scenarios
function ggt_handle_account_not_found_error($order) {
    if (!$order) return;

    // 1. Flag the order
    $order->update_status('on-hold', __('API Error: User account not found or not active in Sage. Flagged for attention.', 'sinappsus-ggt-wp-plugin'));
    $order->add_order_note(__('Transaction logged but account not yet activated. Admin notified.', 'sinappsus-ggt-wp-plugin'));
    $order->update_meta_data('_ggt_account_not_found', 'true');
    $order->save();

    // 2. Notify Admin
    $admin_email = get_option('ggt_account_not_found_email');
    if ($admin_email && is_email($admin_email)) {
        $subject = sprintf('Action Required: Order #%s - Account Not Found', $order->get_order_number());
        $message = sprintf(
            "An order was placed but the user account was not found or not active in Sage.\n\n" .
            "Order ID: %s\n" .
            "User Email: %s\n" .
            "User Name: %s %s\n" .
            "Total: %s\n\n" .
            "Please check the user account in Sage and WooCommerce.",
            $order->get_order_number(),
            $order->get_billing_email(),
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            $order->get_formatted_order_total()
        );
        
        // Ensure email is sent as plain text
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }
}

// Display notice on Thank You page if account was not found
add_action('woocommerce_thankyou', 'ggt_display_account_not_found_notice');
function ggt_display_account_not_found_notice($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    if ($order->get_meta('_ggt_account_not_found') === 'true') {
        echo '<div class="woocommerce-message notice notice-warning notice-alt" role="alert">';
        echo '<h4 style="margin-top:0;">' . __('Attention Required', 'sinappsus-ggt-wp-plugin') . '</h4>';
        echo '<p>' . __('Your transaction has been logged, but your account is not yet fully activated. Someone from Go Geothermal will be in touch with you shortly to finalize your order.', 'sinappsus-ggt-wp-plugin') . '</p>';
        echo '</div>';
    }
}