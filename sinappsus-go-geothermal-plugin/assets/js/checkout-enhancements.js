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
            }
        });
        
        $dateField.on('click', function() {
            $(this).datepicker('show');
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
