(function($) {
    'use strict';
    
    // Define variables to track state
    let pricingFetched = false;
    let isInitialized = false;
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            // Add a delay to allow other scripts to complete
            setTimeout(initializeCheckout, 1000);
        }
    });
    
    // Use a backup initialization with a longer delay
    setTimeout(function() {
        if ($('body').hasClass('woocommerce-checkout') && !isInitialized) {
            console.log('🔄 [GGT] Running late initialization (backup)');
            initializeCheckout();
        }
    }, 2500);
    
    // Listen for checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('🔄 [GGT] Checkout updated, reinitializing date field');
        if (!$('#ggt_delivery_date').length) {
            createDeliveryDateField();
        }
        initDeliveryDatePicker();
    });
    
    function initializeCheckout() {
        if (isInitialized) return;
        isInitialized = true;
        
        console.log('🔄 [GGT] Initializing checkout enhancements...');
        
        // Check which target elements are available using more robust selectors
        const paymentSection = $('.woocommerce-checkout-payment, #payment, [id*="payment"]').first();
        const orderReview = $('.woocommerce-checkout-review-order, #order_review').first();
        const checkoutForm = $('form.checkout, .woocommerce-checkout, form[name="checkout"]').first();
        
        console.log('🔍 [GGT] Payment div exists:', paymentSection.length > 0);
        console.log('🔍 [GGT] Order review exists:', orderReview.length > 0);
        console.log('🔍 [GGT] Checkout form exists:', checkoutForm.length > 0);
        
        // Run primary functions
        fetchCustomerPricing();
        setupDeliveryAddressSelector();
        createDeliveryDateField();
    }
    
    function createDeliveryDateField() {
        // Don't create if it already exists
        if ($('#ggt_delivery_date').length) {
            console.log('✅ [GGT] Delivery date field already exists');
            initDeliveryDatePicker();
            return;
        }
        
        console.log('🔄 [GGT] Creating delivery date field...');
        
        // Simple HTML for the date field
        var dateFieldHtml = `
            <div id="ggt_delivery_date_field" class="form-row form-row-wide" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #f8f8f8;">
                <h3>Desired Delivery Date</h3>
                <p>Please select your preferred delivery date. Note: Deliveries are not available on weekends, UK public holidays, or within 2 business days from today.</p>
                <label for="ggt_delivery_date">Select your desired delivery date <abbr class="required" title="required">*</abbr></label>
                <input type="text" class="input-text" name="ggt_delivery_date" id="ggt_delivery_date" placeholder="Click to select a date" required readonly>
            </div>
        `;
        
        // Use more broad selectors to find insertion points
        var inserted = false;
        
        // Expanded list of potential insertion points
        var targets = [
            $('.woocommerce-checkout-payment'),
            $('#payment'),
            $('[id*="payment"]').first(),
            $('.woocommerce-checkout-review-order'),
            $('#order_review'),
            $('[id*="order_review"]').first(),
            $('.woocommerce-billing-details'),
            $('.woocommerce-billing-fields'),
            $('[id*="billing"]').first(),
            $('.place-order'),
            $('#place_order').parent(),
            $('.woocommerce-checkout'),
            $('form.checkout'),
            $('form[name="checkout"]')
        ];
        
        // Try each target for insertion
        $.each(targets, function(i, $target) {
            if (!inserted && $target && $target.length) {
                console.log('🔄 [GGT] Trying insertion at target #' + i);
                
                if (i <= 5) {
                    // For payment and review order sections, insert before
                    $target.before(dateFieldHtml);
                } else {
                    // For other targets like billing, insert after
                    $target.after(dateFieldHtml);
                }
                
                inserted = $('#ggt_delivery_date').length > 0;
                if (inserted) {
                    console.log('✅ [GGT] Successfully inserted date field at target #' + i);
                    return false; // Break out of loop
                }
            }
        });
        
        // If still not inserted, try direct DOM insertion
        if (!inserted) {
            console.log('🔄 [GGT] Trying direct DOM insertion');
            
            var checkoutForm = document.querySelector('form.checkout, .woocommerce-checkout, form[name="checkout"]');
            if (checkoutForm) {
                var divContainer = document.createElement('div');
                divContainer.innerHTML = dateFieldHtml;
                checkoutForm.appendChild(divContainer);
                
                inserted = $('#ggt_delivery_date').length > 0;
                if (inserted) console.log('✅ [GGT] Added date field using direct DOM insertion');
            }
        }
        
        // If we successfully added the field, initialize the datepicker
        if (inserted) {
            initDeliveryDatePicker();
        } else {
            console.log('❌ [GGT] All insertion methods failed');
        }
    }
    
    function fetchUKHolidays(callback) {
        console.log('🔄 [GGT] Fetching UK holidays...');
        
        $.ajax({
            url: 'https://www.gov.uk/bank-holidays.json',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response && response['england-and-wales'] && response['england-and-wales'].events) {
                    var holidayDates = response['england-and-wales'].events.map(function(event) {
                        return event.date; // Format is already YYYY-MM-DD
                    });
                    console.log('✅ [GGT] Successfully fetched ' + holidayDates.length + ' UK holidays');
                    callback(holidayDates);
                } else {
                    console.error('❌ [GGT] Invalid holiday data format received');
                    callback([]);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ [GGT] Error fetching UK holidays:', error);
                callback([]);
            }
        });
    }
    
    function initDeliveryDatePicker() {
        var $dateField = $('#ggt_delivery_date');
        
        if (!$dateField.length) {
            return;
        }
        
        // Don't re-initialize
        if ($dateField.hasClass('hasDatepicker')) {
            return;
        }
        
        console.log('🔄 [GGT] Setting up delivery date picker...');
        
        try {
            // Fallback holidays in case API fails
            var fallbackUKHolidays = [
                // 2023 UK Holidays
                '2023-01-02', '2023-04-07', '2023-04-10', '2023-05-01', '2023-05-29', 
                '2023-08-28', '2023-12-25', '2023-12-26',
                // 2024 UK Holidays - fixed year typos
                '2024-01-01', '2024-03-29', '2024-04-01', '2024-05-06', '2024-05-27', 
                '2024-08-26', '2024-12-25', '2024-12-26'
            ];
            
            // Attempt to fetch holidays from API
            fetchUKHolidays(function(ukHolidays) {
                // If API returned no holidays, use fallback
                if (!ukHolidays || !ukHolidays.length) {
                    ukHolidays = fallbackUKHolidays;
                    console.log('ℹ️ [GGT] Using fallback holiday dates');
                }
                
                // Initialize datepicker with holidays
                initDatepickerWithHolidays($dateField, ukHolidays);
            });
        } catch (e) {
            console.error('❌ [GGT] Error during holiday fetching:', e);
            // Use fallback on error
            initDatepickerWithHolidays($dateField, fallbackUKHolidays);
        }
    }
    
    function initDatepickerWithHolidays($dateField, ukHolidays) {
        $dateField.datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: '+2d',
            maxDate: '+6m',
            beforeShowDay: function(date) {
                // Check if it's a weekend
                var day = date.getDay();
                if (day === 0 || day === 6) {
                    return [false, '', 'No deliveries on weekends'];
                }
                
                // Check if it's a UK public holiday
                var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                if ($.inArray(dateString, ukHolidays) !== -1) {
                    return [false, 'uk-holiday', 'No deliveries on UK public holidays'];
                }
                
                return [true, '', ''];
            },
            onSelect: function(dateText) {
                console.log('✅ [GGT] Delivery date selected:', dateText);
                
                // Store in multiple places to ensure it gets captured
                // 1. Regular field value
                $(this).val(dateText).trigger('change');
                
                // 2. Hidden field for form submission
                if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                    $('form.checkout').append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + dateText + '">');
                } else {
                    $('input[name="ggt_delivery_date_hidden"]').val(dateText);
                }
                
                // 3. LocalStorage for persistent backup
                try {
                    localStorage.setItem('ggt_delivery_date', dateText);
                    console.log('✅ [GGT] Date saved to localStorage');
                } catch (e) {
                    console.log('⚠️ [GGT] Could not save to localStorage:', e);
                }
                
                // 4. Send via AJAX to store in session
                $.ajax({
                    url: ggt_checkout_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ggt_store_delivery_date',
                        nonce: ggt_checkout_data.nonce,
                        delivery_date: dateText
                    },
                    success: function() {
                        console.log('✅ [GGT] Date saved to server session');
                    }
                });
            }
        });
        
        console.log('✅ [GGT] Date picker initialized with ' + ukHolidays.length + ' holidays');
    }
    
    function fetchCustomerPricing() {
        if (pricingFetched) return;
        if (!ggt_checkout_data || !ggt_checkout_data.account_ref) {
            console.log('⚠️ [GGT] No customer account reference found.');
            pricingFetched = true;
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
                pricingFetched = true;
                console.log('🔄 [GGT] Pricing response:', response);
                // Check multiple possible paths to find pricing data
                let prices = null;
                
                if (response.success && response.data) {
                    if (response.data.response && response.data.response.prices) {
                        prices = response.data.response.prices;
                        console.log('✅ [GGT] Found prices in response.data.response.prices');
                    } else if (response.data.results && response.data.results.prices) {
                        prices = response.data.results.prices;
                        console.log('✅ [GGT] Found prices in response.data.results.prices');
                    } else if (response.data.prices) {
                        prices = response.data.prices;
                        console.log('✅ [GGT] Found prices in response.data.prices');
                    } else {
                        console.log('⚠️ [GGT] No prices found in response structure');
                    }
                    
                    if (prices) {
                        console.log('✅ [GGT] Found ' + prices.length + ' custom prices');
                        updateCartPrices(prices);
                    } else {
                        console.log('⚠️ [GGT] No custom pricing found in response structure:', response.data);
                    }
                } else {
                    console.log('⚠️ [GGT] No custom pricing found or error occurred:', response);
                }
            },
            error: function(xhr, status, error) {
                pricingFetched = true;
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
                // The results array directly contains the addresses, not results.addresses
                if (response.success && response.data && response.data.results) {
                    console.log('✅ [GGT] Found ' + response.data.results.length + ' delivery addresses');
                    displayDeliveryAddresses(response.data.results);
                } else {
                    console.error('❌ [GGT] Could not find delivery addresses in response:', response);
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
        console.log('[GGT] Selecting delivery address:', address);
        
        // Map the address fields correctly from API response to user meta fields
        const mappedAddress = {
            shipping_first_name: $('#billing_first_name').val() || '',
            shipping_last_name: $('#billing_last_name').val() || '',
            shipping_company: address.addressName || '',
            shipping_address_1: address.addressLine1 || '',
            shipping_address_2: address.addressLine2 || '',
            shipping_city: address.addressLine3 || address.town || '',
            shipping_state: address.addressLine4 || address.county || '',
            shipping_postcode: address.postCode || '',
            shipping_country: address.countryCode || 'GB'
        };
        
        console.log('[GGT] Updating user shipping address in database:', mappedAddress);
        
        // Show loading message
        if ($('.woocommerce-message').length === 0) {
            $('.woocommerce-checkout').prepend(
                '<div class="woocommerce-message" role="alert">' +
                'Updating shipping address...' +
                '</div>'
            );
        }
        
        // Send AJAX request to update user's shipping address in database
        $.ajax({
            url: ggt_checkout_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ggt_update_user_shipping_address',
                nonce: ggt_checkout_data.nonce,
                shipping_address: mappedAddress,
                delivery_info: JSON.stringify({
                    mapped: mappedAddress,
                    original: address
                })
            },
            success: function(response) {
                console.log('[GGT] Address update response:', response);
                
                if (response.success) {
                    // Update the message
                    $('.woocommerce-message').text('Shipping address updated! Refreshing checkout...');
                    
                    // Wait a moment then refresh the page
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $('.woocommerce-message').removeClass('woocommerce-message').addClass('woocommerce-error');
                    $('.woocommerce-error').text('Failed to update shipping address. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('[GGT] Error updating shipping address:', error);
                $('.woocommerce-message').removeClass('woocommerce-message').addClass('woocommerce-error');
                $('.woocommerce-error').text('Error updating shipping address. Please try again.');
            }
        });
        
        // Store complete address info for order processing
        storeDeliveryInfo(JSON.stringify({
            mapped: mappedAddress,
            original: address
        }));
    }
    
    function updateShippingFields(address) {
        // This function is no longer needed since we're updating the database directly
        // Keeping it for compatibility but it won't be called
        console.log('[GGT] updateShippingFields called but not used - updating database instead');
    }
    
    // Add this at the end of the file to ensure form submission captures the delivery date
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            // Check for previously stored date in localStorage
            try {
                const storedDate = localStorage.getItem('ggt_delivery_date');
                if (storedDate && $('#ggt_delivery_date').length) {
                    $('#ggt_delivery_date').val(storedDate);
                    console.log('✅ [GGT] Restored date from localStorage:', storedDate);
                    
                    // Also ensure hidden field has the value
                    if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                        $('form.checkout').append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + storedDate + '">');
                    } else {
                        $('input[name="ggt_delivery_date_hidden"]').val(storedDate);
                    }
                }
            } catch (e) {
                console.log('⚠️ [GGT] Could not access localStorage:', e);
            }
            
            // Make absolutely sure date is submitted with checkout
            $('form.checkout').on('submit checkout_place_order', function() {
                const date = $('#ggt_delivery_date').val() || localStorage.getItem('ggt_delivery_date');
                if (date) {
                    console.log('✅ [GGT] Form submission - adding date:', date);
                    
                    // Create or update hidden field
                    if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                        $(this).append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + date + '">');
                    } else {
                        $('input[name="ggt_delivery_date_hidden"]').val(date);
                    }
                    
                    // Add a second backup field with different name
                    if (!$('input[name="_delivery_date_backup"]').length) {
                        $(this).append('<input type="hidden" name="_delivery_date_backup" value="' + date + '">');
                    } else {
                        $('input[name="_delivery_date_backup"]').val(date);
                    }
                }
            });
        }
    });

    function storeShippingFields() {
        const fields = [
            'shipping_first_name',
            'shipping_last_name',
            'shipping_company',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country'
        ];
        fields.forEach(function(field) {
            const val = $('#' + field).val() || '';
            const name = 'ggt_' + field;
            if (!$('input[name="' + name + '"]').length) {
                $('form.checkout').append('<input type="hidden" name="' + name + '" value="' + val + '">');
            } else {
                $('input[name="' + name + '"]').val(val);
            }
        });
    }

    $(document).on('click', '#place_order, form.checkout input[type="submit"]', function() {
        storeShippingFields();
    });

    $(document).on('click', '.select-shipping-address', function() {
        // Suppose we detect user-chosen address details here
        const selectedAddress = {
            first_name: 'Alice',
            last_name: 'Smith',
            // ...other address fields...
        };

        // Write them into hidden fields
        Object.entries(selectedAddress).forEach(([key, val]) => {
            const hiddenFieldName = 'ggt_shipping_' + key;
            if (!$('input[name="' + hiddenFieldName + '"]').length) {
                $('form.checkout').append(
                    '<input type="hidden" name="' + hiddenFieldName + '" value="' + val + '">'
                );
            } else {
                $('input[name="' + hiddenFieldName + '"]').val(val);
            }
        });
    });
})(jQuery);
