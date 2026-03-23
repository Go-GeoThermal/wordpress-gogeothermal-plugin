jQuery(document).ready(function($) {
    // Wait a bit to allow other scripts to complete
    setTimeout(function() {
        // Only add if the field doesn't exist
        if ($('#ggt_delivery_date').length === 0) {
            console.log('🔄 [GGT] Adding delivery date via footer fallback');
            
            var dateField = `
                <div id="ggt_delivery_date_field" class="form-row form-row-wide" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f8f8f8;">
                    <h3>Desired Despatched Date</h3>
                    <p>Please select your preferred despatched date. Note: Deliveries are not available on weekends, UK public holidays, or within 2 business days from today.</p>
                    <label for="ggt_delivery_date">Select your desired despatched date <abbr class="required" title="required">*</abbr></label>
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
                var holidays = ggt_checkout_params.holidays || [];
                
                $('#ggt_delivery_date').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: '+2d',
                    maxDate: '+6m',
                    beforeShowDay: function(date) {
                        var day = date.getDay();
                        if (day === 0 || day === 6) {
                            return [false, '', 'No deliveries on weekends'];
                        }
                        
                        var string = jQuery.datepicker.formatDate('yy-mm-dd', date);
                        if (holidays.indexOf(string) != -1) {
                            return [false, '', 'Holiday'];
                        }
                        
                        return [true, '', ''];
                    }
                });
            }
        }
    }, 1000);
});
