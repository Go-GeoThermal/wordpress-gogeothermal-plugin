(function($) {
    'use strict';
    
    // Flag to track initialization status
    var isInitialized = false;
    var datePickerAttempts = 0;
    var maxAttempts = 5;
    var customPricingComplete = false;
    var lastAjaxRequest = 0;
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            initCheckoutEnhancements();
            
            // Try once more after a delay
            setTimeout(function() {
                // Only run this delayed initialization if the datepicker isn't found
                if (!$('#ggt_delivery_date').length) {
                    createDeliveryDateField();
                }
            }, 1500);
        }
    });
    
    // More selective AJAX handler - only for specific WooCommerce events
    $(document.body).on('updated_checkout', function() {
        if (!$('#ggt_delivery_date').length) {
            createDeliveryDateField();
        }
        initDeliveryDatePicker();
    });
    
    // Prevent too frequent Ajax reinitializations
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Only process if it's a WooCommerce checkout update and not too frequent
        if (settings.url && settings.url.indexOf('wc-ajax=update_order_review') > -1) {
            var now = new Date().getTime();
            if (now - lastAjaxRequest > 2000) { // At least 2 seconds between reinits
                lastAjaxRequest = now;
                if ($('body').hasClass('woocommerce-checkout')) {
                    // Only attempt date picker initialization, not everything
                    if (!$('#ggt_delivery_date').length) {
                        createDeliveryDateField();
                    } else {
                        initDeliveryDatePicker();
                    }
                }
            }
        }
    });
    
    function initCheckoutEnhancements() {
        if (isInitialized) return;
        
        console.log('🔄 [GGT] Initializing checkout enhancements...');
        
        // Set the initialization flag
        isInitialized = true;
        
        fetchCustomerPricing();
        setupDeliveryAddressSelector();
        
        // Create the delivery date field if it doesn't exist
        if (!$('#ggt_delivery_date').length) {
            createDeliveryDateField();
        } else {
            initDeliveryDatePicker();
        }
    }
    
    // Create the delivery date field directly in JS if PHP hooks failed
    function createDeliveryDateField() {
        // Only try a limited number of times
        if (datePickerAttempts >= maxAttempts) return;
        datePickerAttempts++;
        
        console.log('🔄 [GGT] Attempting to create delivery date field... (attempt ' + datePickerAttempts + ')');
        
        // Try to find appropriate placement targets
        const $orderReview = $('#order_review');
        const $paymentMethods = $('#payment');
        const $additionalFields = $('.woocommerce-additional-fields');
        const $orderNotes = $('.woocommerce-additional-fields__field-wrapper');
        
        // Create our date field HTML
        const dateFieldHtml = `
            <div id="ggt_delivery_date_field" class="ggt-delivery-date-wrapper">
                <h3>Desired Delivery Date</h3>
                <p class="form-row form-row-wide">
                    Please select your preferred delivery date. Note: Deliveries are not available on weekends, UK public holidays, or within 2 business days from today.
                </p>
                <p class="form-row form-row-wide">
                    <label for="ggt_delivery_date">Select your desired delivery date</label>
                    <input type="text" class="input-text form-row-wide ggt-delivery-date-field" name="ggt_delivery_date" id="ggt_delivery_date" placeholder="Click to select a date" autocomplete="off" readonly="readonly" data-is-datefield="true" required>
                </p>
            </div>
        `;
        
        // Try different insertion points based on available elements
        if ($additionalFields.length) {
            // Add before additional fields if they exist
            $additionalFields.before(dateFieldHtml);
        } else if ($paymentMethods.length) {
            // Add before payment methods if the additional fields don't exist
            $paymentMethods.before(dateFieldHtml);
        } else if ($orderReview.length) {
            // Fallback to adding at the top of order review
            $orderReview.prepend(dateFieldHtml);
        } else {
            // Last resort - try to add it to the checkout form
            $('form.checkout').append(dateFieldHtml);
        }
        
        // If we added the field, initialize the datepicker
        if ($('#ggt_delivery_date').length) {
            console.log('✅ [GGT] Successfully created delivery date field');
            initDeliveryDatePicker();
        } else {
            console.log('❌ [GGT] Failed to create delivery date field');
        }
    }
    
    // Initialize delivery date picker if jQuery UI is available
    function initDeliveryDatePicker() {
        // Check if jQuery UI is available
        if (typeof $.fn.datepicker !== 'function') {
            console.error('❌ [GGT] jQuery UI Datepicker not available');
            return;
        }
        
        // Look for the field with multiple possible selectors
        const $dateField = $('#ggt_delivery_date, [name="ggt_delivery_date"]').first();
        
        if (!$dateField.length) {
            // Don't log this every time to reduce spam
            return;
        }
        
        // Don't initialize twice
        if ($dateField.hasClass('hasDatepicker')) {
            return;
        }
        
        console.log('🔄 [GGT] Setting up delivery date picker...');
        
        // UK public holidays
        const ukHolidays = getUKHolidays();
        
        try {
            // Initialize the date picker
            $dateField.datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: '+2d', // Minimum 2 days from today
                maxDate: '+6m', // Maximum 6 months ahead
                beforeShowDay: function(date) {
                    // Check if it's a weekend
                    const day = date.getDay();
                    if (day === 0 || day === 6) { // Sunday or Saturday
                        return [false, '', 'No deliveries on weekends'];
                    }
                    
                    // Check if it's a UK public holiday
                    const dateString = $.datepicker.formatDate('yy-mm-dd', date);
                    if (ukHolidays.includes(dateString)) {
                        return [false, 'uk-holiday', 'No deliveries on UK public holidays'];
                    }
                    
                    // Valid delivery date
                    return [true, '', ''];
                }
            });
            
            // Add click handler to show datepicker
            $dateField.off('click.ggtDate').on('click.ggtDate', function() {
                $(this).datepicker('show');
            });
            
            console.log('✅ [GGT] Delivery date picker initialized successfully');
        } catch (e) {
            console.error('❌ [GGT] Error initializing datepicker:', e);
        }
    }
    
    // UK Holidays list
    function getUKHolidays() {
        return [
            // 2023 UK Holidays
            '2023-01-02', // New Year's Day (observed)
            '2023-04-07', // Good Friday
            '2023-04-10', // Easter Monday
            '2023-05-01', // Early May Bank Holiday
            '2023-05-29', // Spring Bank Holiday
            '2023-08-28', // Summer Bank Holiday
            '2023-12-25', // Christmas Day
            '2023-12-26', // Boxing Day
            
            // 2024 UK Holidays
            '2024-01-01', // New Year's Day
            '2024-03-29', // Good Friday
            '2024-04-01', // Easter Monday
            '2024-05-06', // Early May Bank Holiday
            '2024-05-27', // Spring Bank Holiday
            '2024-08-26', // Summer Bank Holiday
            '2024-12-25', // Christmas Day
            '2024-12-26', // Boxing Day
        ];
    }
    
    function fetchCustomerPricing() {
        if (customPricingComplete) return;
        
        if (!ggt_checkout_data || !ggt_checkout_data.account_ref) {
            console.log('⚠️ [GGT] No customer account reference found.');
            customPricingComplete = true;
            return;
        }
        
        console.log('🔄 [GGT] Fetching custom pricing for customer...');
        
        $.ajax({
            url: ggt_checkout_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ggt_fetch_customer_pricing',
                nonce: ggt_checkout_data.nonce,
                account_ref: ggt_checkout_data.account_ref
            },
            success: function(response) {
                customPricingComplete = true;
                if (response.success && response.data && response.data.prices) {
                    updateCartPrices(response.data.prices);
                } else {
                    console.log('⚠️ [GGT] No custom pricing found or error occurred:', response);
                }
            },
            error: function(xhr, status, error) {
                customPricingComplete = true;
                console.error('❌ [GGT] Error fetching pricing data:', error);
            }
        });
    }
    
    function updateCartPrices(prices) {
        console.log('🔄 [GGT] Updating cart with custom prices...');
        let priceUpdated = false;
        
        $.each(prices, function(index, priceItem) {
            if (!priceItem.stockCode || priceItem.storedPrice <= 0) {
                return; // Skip invalid items
            }
            
            // Find cart items with matching stock code and update if needed
            $('.cart_item').each(function() {
                const $item = $(this);
                const stockCode = $item.data('stock-code');
                
                if (stockCode === priceItem.stockCode) {
                    // Update the price - note: we'll need to trigger cart update via AJAX
                    priceUpdated = true;
                    console.log(`✅ [GGT] Updating price for ${stockCode} to ${priceItem.storedPrice}`);
                }
            });
        });
        
        if (priceUpdated) {
            // Update cart totals
            $.ajax({
                url: ggt_checkout_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'ggt_update_cart_prices',
                    nonce: ggt_checkout_data.nonce,
                    prices: prices
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the checkout to show updated prices
                        $('body').trigger('update_checkout');
                    }
                }
            });
        }
    }
    
    function setupDeliveryAddressSelector() {
        if (!ggt_checkout_data || !ggt_checkout_data.account_ref) {
            console.log('⚠️ [GGT] No customer account reference found for delivery addresses.');
            return;
        }
        
        console.log('🔄 [GGT] Setting up delivery address selector...');
        
        // Try multiple selectors for shipping address section
        const $shippingForm = $('.woocommerce-shipping-fields, #shipping_address, [id*="shipping"]').first();
        
        if ($shippingForm.length && !$('#ggt-show-delivery-addresses').length) {
            $shippingForm.prepend(
                '<div class="ggt-delivery-address-selector">' +
                '<button type="button" id="ggt-show-delivery-addresses" class="button alt">Select from saved delivery addresses</button>' +
                '<div id="ggt-delivery-addresses-modal" style="display:none;"><div class="modal-content"><span class="close">&times;</span>' +
                '<h3>Select a delivery address</h3><div id="ggt-address-list"></div></div></div>' +
                '</div>'
            );
            
            // Add event listener for the button
            $('#ggt-show-delivery-addresses').off('click.ggtAddr').on('click.ggtAddr', function(e) {
                e.preventDefault();
                console.log('🔄 [GGT] Fetching delivery addresses...');
                fetchDeliveryAddresses();
            });
            
            // Close modal when clicking the X or outside the modal
            $(document).off('click.ggtModalClose').on('click.ggtModalClose', '#ggt-delivery-addresses-modal .close', function() {
                $('#ggt-delivery-addresses-modal').hide();
            });
            
            $(window).off('click.ggtModalOutside').on('click.ggtModalOutside', function(e) {
                if ($(e.target).is('#ggt-delivery-addresses-modal')) {
                    $('#ggt-delivery-addresses-modal').hide();
                }
            });
            
            console.log('✅ [GGT] Delivery address selector initialized');
        } else if (!$shippingForm.length) {
            console.log('⚠️ [GGT] Shipping form not found - DOM structure may be different than expected');
        } else {
            console.log('ℹ️ [GGT] Address selector already exists');
        }
    }
    
    function fetchDeliveryAddresses() {
        $.ajax({
            url: ggt_checkout_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ggt_fetch_delivery_addresses',
                nonce: ggt_checkout_data.nonce,
                account_ref: ggt_checkout_data.account_ref
            },
            success: function(response) {
                console.log('🔄 [GGT] Delivery addresses response:', response);
                if (response.success && response.data.addresses) {
                    displayDeliveryAddresses(response.data.addresses);
                } else {
                    alert('No delivery addresses found for your account.');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ [GGT] Error fetching delivery addresses:', error);
                alert('Error fetching delivery addresses. Please try again.');
            }
        });
    }
    
    function displayDeliveryAddresses(addresses) {
        const $addressList = $('#ggt-address-list');
        $addressList.empty();
        
        if (addresses.length === 0) {
            $addressList.html('<p>No saved delivery addresses found.</p>');
            $('#ggt-delivery-addresses-modal').show();
            return;
        }
        
        let addressHtml = '<ul class="ggt-address-list">';
        addresses.forEach(function(address, index) {
            addressHtml += '<li class="ggt-address-item" data-index="' + index + '">';
            addressHtml += '<strong>' + (address.addressName || 'Address ' + (index + 1)) + '</strong><br>';
            addressHtml += address.addressLine1 + '<br>';
            if (address.addressLine2) addressHtml += address.addressLine2 + '<br>';
            if (address.addressLine3) addressHtml += address.addressLine3 + '<br>';
            if (address.addressLine4) addressHtml += address.addressLine4 + '<br>';
            addressHtml += address.postCode + '<br>';
            addressHtml += '<button type="button" class="select-address button" data-index="' + index + '">Select</button>';
            addressHtml += '</li>';
        });
        addressHtml += '</ul>';
        
        $addressList.html(addressHtml);
        $('#ggt-delivery-addresses-modal').show();
        
        // Handle address selection
        $('.select-address').on('click', function() {
            const index = $(this).data('index');
            selectDeliveryAddress(addresses[index]);
            $('#ggt-delivery-addresses-modal').hide();
        });
    }
    
    function selectDeliveryAddress(address) {
        // Update shipping address fields
        $('#shipping_first_name').val($('#billing_first_name').val());
        $('#shipping_last_name').val($('#billing_last_name').val());
        $('#shipping_company').val(address.addressName || '');
        $('#shipping_address_1').val(address.addressLine1 || '');
        $('#shipping_address_2').val(address.addressLine2 || '');
        $('#shipping_city').val(address.addressLine3 || '');
        $('#shipping_state').val(address.addressLine4 || '');
        $('#shipping_postcode').val(address.postCode || '');
        
        // Trigger update
        $('body').trigger('update_checkout');
    }
})(jQuery);
