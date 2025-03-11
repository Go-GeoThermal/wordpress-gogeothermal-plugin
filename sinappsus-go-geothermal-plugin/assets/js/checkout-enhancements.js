(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            initCheckoutEnhancements();
        }
    });
    
    function initCheckoutEnhancements() {
        console.log('🔄 [GGT] Initializing checkout enhancements...');
        fetchCustomerPricing();
        setupDeliveryAddressSelector();
    }
    
    function fetchCustomerPricing() {
        if (!ggt_checkout_data || !ggt_checkout_data.account_ref) {
            console.log('⚠️ [GGT] No customer account reference found.');
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
                if (response.success && response.data.prices) {
                    updateCartPrices(response.data.prices);
                } else {
                    console.log('⚠️ [GGT] No custom pricing found or error occurred:', response);
                }
            },
            error: function(xhr, status, error) {
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
            return;
        }
        
        // Add the delivery address selection button
        const $shippingForm = $('.woocommerce-shipping-fields');
        if ($shippingForm.length) {
            $shippingForm.prepend(
                '<div class="ggt-delivery-address-selector">' +
                '<button type="button" id="ggt-show-delivery-addresses" class="button alt">Select from saved delivery addresses</button>' +
                '<div id="ggt-delivery-addresses-modal" style="display:none;"><div class="modal-content"><span class="close">&times;</span>' +
                '<h3>Select a delivery address</h3><div id="ggt-address-list"></div></div></div>' +
                '</div>'
            );
            
            $('#ggt-show-delivery-addresses').on('click', function(e) {
                e.preventDefault();
                fetchDeliveryAddresses();
            });
            
            // Close modal when clicking the X or outside the modal
            $(document).on('click', '#ggt-delivery-addresses-modal .close', function() {
                $('#ggt-delivery-addresses-modal').hide();
            });
            
            $(window).on('click', function(e) {
                if ($(e.target).is('#ggt-delivery-addresses-modal')) {
                    $('#ggt-delivery-addresses-modal').hide();
                }
            });
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
                if (response.success && response.data.addresses) {
                    displayDeliveryAddresses(response.data.addresses);
                } else {
                    alert('No delivery addresses found for your account.');
                }
            },
            error: function() {
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
