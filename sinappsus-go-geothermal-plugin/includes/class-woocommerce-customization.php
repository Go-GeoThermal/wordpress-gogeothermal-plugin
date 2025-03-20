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
    // Check for the delivery date in multiple possible form fields
    $delivery_date = null;
    
    if (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
    } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    }
    
    // If still no date found, check other possible field names
    if (empty($delivery_date)) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'delivery_date') !== false && !empty($value)) {
                $delivery_date = sanitize_text_field($value);
                break;
            }
        }
    }
    
    // If no date is found in any field, show error
    if (empty($delivery_date)) {
        wc_add_notice(__('Please select a delivery date.'), 'error');
        wc_get_logger()->error('No delivery date provided during checkout validation', ['source' => 'ggt-delivery']);
        return;
    }
    
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
    
    // Store validated date in session for later use
    WC()->session->set('ggt_validated_delivery_date', $delivery_date);
    
    wc_get_logger()->info(
        sprintf('Delivery date validated successfully: %s', $delivery_date),
        ['source' => 'ggt-delivery']
    );
}

// Remove all previous hooks for saving delivery date to prevent conflicts
remove_action('woocommerce_checkout_update_order_meta', 'ggt_save_delivery_date_field');

// Improved saving of delivery date with comprehensive checks
add_action('woocommerce_checkout_update_order_meta', 'ggt_improved_save_delivery_date', 20);
function ggt_improved_save_delivery_date($order_id) {
    $delivery_date = null;
    
    // Check multiple sources for the delivery date
    if (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
    } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
    } else {
        // Last attempt - check any POST field with delivery_date in the name
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'delivery_date') !== false && !empty($value)) {
                $delivery_date = sanitize_text_field($value);
                break;
            }
        }
    }
    
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
        
        // Add debugging data to confirm date was saved
        wc_get_logger()->debug(
            sprintf('Delivery date saved. Verification: Meta now contains: %s', 
                get_post_meta($order_id, 'ggt_delivery_date', true)
            ),
            ['source' => 'ggt-delivery']
        );
    } else {
        wc_get_logger()->error(
            sprintf('Failed to find delivery date for order #%d in any expected location', $order_id),
            ['source' => 'ggt-delivery']
        );
    }
}

// Ensure we capture the delivery date at order creation time with highest priority
add_action('woocommerce_checkout_create_order', 'ggt_capture_delivery_date_early', 5, 2);
function ggt_capture_delivery_date_early($order, $data) {
    $delivery_date = null;
    
    // Check all possible sources for the date
    if (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
    } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    }
    
    // Log what we found
    if (!empty($delivery_date)) {
        wc_get_logger()->info(
            sprintf('Early capture: Found delivery date "%s" for order #%d', $delivery_date, $order->get_id()), 
            ['source' => 'ggt-delivery']
        );
        
        // Save it to order meta
        $order->update_meta_data('ggt_delivery_date', $delivery_date);
        $order->update_meta_data('delivery_date', $delivery_date); // Also add non-prefixed version
    } else {
        wc_get_logger()->warning(
            sprintf('Early capture: No delivery date found for order #%d', $order->get_id()),
            ['source' => 'ggt-delivery']
        );
    }
}

// Also hook into direct order creation
add_action('woocommerce_checkout_create_order', 'ggt_add_delivery_date_to_order', 20, 2);
function ggt_add_delivery_date_to_order($order, $data) {
    // Get delivery date from various possible sources
    $delivery_date = null;
    
    if (!empty($_POST['ggt_delivery_date'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date']);
    } elseif (!empty($_POST['ggt_delivery_date_hidden'])) {
        $delivery_date = sanitize_text_field($_POST['ggt_delivery_date_hidden']);
    } elseif (WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
    }
    
    if (!empty($delivery_date)) {
        // Save the delivery date to the order object directly
        $order->update_meta_data('ggt_delivery_date', $delivery_date);
        $order->update_meta_data('_delivery_date', $delivery_date);
        
        wc_get_logger()->info(
            sprintf('Added delivery date "%s" directly to order object #%d', $delivery_date, $order->get_id()),
            ['source' => 'ggt-delivery']
        );
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
            
            wc_get_logger()->info(
                sprintf('Saved selected delivery address for order #%d', $order->get_id()),
                ['source' => 'ggt-delivery']
            );
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
    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
    
    if (!empty($delivery_date)) {
        $order_data['delivery_date'] = $delivery_date;
    }
    
    return $order_data;
}

// Display the delivery date in admin order page (enhanced)
add_action('woocommerce_admin_order_data_after_billing_address', 'ggt_display_delivery_date_in_admin');
function ggt_display_delivery_date_in_admin($order) {
    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
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
    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
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
    $delivery_date = get_post_meta($order->get_id(), 'ggt_delivery_date', true);
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
    $delivery_date = get_post_meta($order_id, 'ggt_delivery_date', true);
    
    // Log that we're sending the order with delivery date
    wc_get_logger()->info(
        sprintf('Sending order #%d to API with delivery date: %s', $order_id, $delivery_date ?: 'not set'), 
        ['source' => 'ggt-api']
    );
    
    $response = ggt_send_order_to_api_endpoint($order, $delivery_date);

    if (is_wp_error($response)) {
        wc_get_logger()->error(
            sprintf('Failed to send order #%d to API: %s', $order_id, $response->get_error_message()), 
            ['source' => 'ggt-api']
        );
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        wc_get_logger()->info(
            sprintf('API response for order #%d: Code %d, Body: %s', $order_id, $response_code, $response_body), 
            ['source' => 'ggt-api']
        );
    }
}

// Add debug logging to the API function - FIXED: Removed duplicate function
function ggt_send_order_to_api_endpoint($order, $delivery_date) {
    // Add more aggressive debugging at the start
    wc_get_logger()->debug('STARTING API SEND - Initial delivery date: ' . ($delivery_date ?: 'EMPTY'), ['source' => 'ggt-api']);
    
    $api_key = ggt_get_decrypted_token();
    $api_base_url = ggt_get_api_base_url();
    $endpoint = $api_base_url . '/sales-orders/wp-new-order';
    
    // Exclude credit payment method - skip sending
    if ($order->get_payment_method() === 'geo_credit') {
        wc_get_logger()->info('Skipping API call for geo_credit payment method', ['source' => 'ggt-api']);
        return;
    }
    
    $user_id = $order->get_user_id();
    $user_meta = get_user_meta($user_id);
    
    // Dump order meta for debugging
    $order_meta = get_post_meta($order->get_id());
    $date_meta = array();
    foreach ($order_meta as $key => $value) {
        if (strpos($key, 'date') !== false || strpos($key, 'delivery') !== false) {
            $date_meta[$key] = $value[0];
        }
    }
    
    wc_get_logger()->debug('Order date meta: ' . json_encode($date_meta), ['source' => 'ggt-api']);
    
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
                wc_get_logger()->info(
                    sprintf('Found delivery date in meta key %s: %s', $key, $delivery_date),
                    ['source' => 'ggt-api']
                );
                break;
            }
        }
    }
    
    // Also check the session as a last resort
    if (empty($delivery_date) && WC()->session && WC()->session->get('ggt_validated_delivery_date')) {
        $delivery_date = WC()->session->get('ggt_validated_delivery_date');
        wc_get_logger()->info(
            sprintf('Found delivery date in session: %s', $delivery_date),
            ['source' => 'ggt-api']
        );
    }
    
    // Right before preparing the API payload, make one final check for the date
    if (empty($delivery_date)) {
        // Last desperate attempt - check the order meta directly
        $delivery_date = $order->get_meta('ggt_delivery_date');
        wc_get_logger()->debug('Last resort check for date - found: ' . ($delivery_date ?: 'STILL EMPTY'), ['source' => 'ggt-api']);
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
            wc_get_logger()->info(
                'Found delivery address data in order meta',
                ['source' => 'ggt-api']
            );
        }
    }
    
    // Log what we're sending to the API
    wc_get_logger()->info(
        sprintf('Sending order #%d to API with delivery date: %s (original: %s)',
            $order->get_id(),
            $formatted_delivery_date ?: 'null',
            $delivery_date ?: 'null'
        ),
        ['source' => 'ggt-api']
    );
    
    $order_data = [
        'order_id'         => $order->get_id(),
        'user_id'          => $user_id,
        'total'            => $order->get_total(),
        'currency'         => get_woocommerce_currency(),
        'billing'          => $order->get_address('billing'),
        'shipping'         => $order->get_address('shipping'),
        'user_meta'        => $user_meta,
        'items'            => [],
        'delivery_date'    => $formatted_delivery_date,
        'delivery_date_raw' => $delivery_date, // For debugging
        'delivery_address' => $delivery_address // Add delivery address to the API payload
    ];
    
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) continue;
        
        $stock_code = get_post_meta($product->get_id(), '_stockCode', true);
        
        $order_data['items'][] = [
            'product_id' => $product->get_id(),
            'name'       => $item->get_name(),
            'quantity'   => $item->get_quantity(),
            'price'      => $item->get_total(),
            'stock_code' => $stock_code,
        ];
    }
    
    wc_get_logger()->debug(
        'Order data being sent to API: ' . print_r($order_data, true),
        ['source' => 'ggt-api']
    );

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
    
    // Log response
    if (is_wp_error($response)) {
        wc_get_logger()->error(
            sprintf('API error for order #%d: %s', $order->get_id(), $response->get_error_message()),
            ['source' => 'ggt-api']
        );
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        wc_get_logger()->info(
            sprintf('API response for order #%d: Code %d with body %s', $order->get_id(), $code, $body),
            ['source' => 'ggt-api']
        );
    }
    
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
    $api_base_url = ggt_get_api_base_url();
    $endpoint = $api_base_url . '/sales-orders/wp-update-order-status';

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

// Load payment gateway
function ggt_load_credit_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        wc_get_logger()->info('Loading credit payment gateway', ['source' => 'geo-credit']);
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
        
        wc_get_logger()->info(
            sprintf('AJAX: Stored delivery date in session: %s', $delivery_date),
            ['source' => 'ggt-delivery']
        );
        
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
        wc_get_logger()->info(
            sprintf('Using delivery date from backup field: %s', $_POST['_delivery_date_backup']),
            ['source' => 'ggt-delivery']
        );
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
        
        wc_get_logger()->info(
            sprintf('EMERGENCY: Set delivery date %s on order %s', $delivery_date, $order->get_id()),
            ['source' => 'ggt-delivery']
        );
    } else {
        wc_get_logger()->warning(
            sprintf('EMERGENCY: Could not find delivery date for order %s', $order->get_id()),
            ['source' => 'ggt-delivery']
        );
    }
}