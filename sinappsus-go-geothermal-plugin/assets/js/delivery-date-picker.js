(function($) {
    'use strict';
    
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            setTimeout(initDeliveryDatePicker, 500);
            
            // Add form submission event handler
            $('form.checkout').on('checkout_place_order submit', function() {
                const deliveryDate = $('#ggt_delivery_date').val();
                
                if (!deliveryDate) {
                    alert('Please select a delivery date before placing your order.');
                    $('#ggt_delivery_date').focus();
                    return false;
                }
                
                // Add hidden field as backup
                if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                    $('form.checkout').append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + deliveryDate + '">');
                } else {
                    $('input[name="ggt_delivery_date_hidden"]').val(deliveryDate);
                }
                
                return true;
            });
        }
    });
    
    function initDeliveryDatePicker() {
        if (!$('#ggt_delivery_date').length) return;
        if ($('#ggt_delivery_date').hasClass('hasDatepicker')) return;
        
        // Simple static list of UK holidays
        const ukHolidays = [
            // 2023 UK Holidays
            '2023-01-02', '2023-04-07', '2023-04-10', '2023-05-01', '2023-05-29', 
            '2023-08-28', '2023-12-25', '2023-12-26',
            
            // 2024 UK Holidays
            '2024-01-01', '2024-03-29', '2024-04-01', '2024-05-06', '2024-05-27', 
            '2024-08-26', '2024-12-25', '2024-12-26',
        ];
        
        $('#ggt_delivery_date').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: '+2d',
            maxDate: '+6m',
            beforeShowDay: function(date) {
                // Check if it's a weekend
                const day = date.getDay();
                if (day === 0 || day === 6) {
                    return [false, '', 'No deliveries on weekends'];
                }
                
                // Check if it's a UK public holiday
                const dateString = $.datepicker.formatDate('yy-mm-dd', date);
                if ($.inArray(dateString, ukHolidays) !== -1) {
                    return [false, 'uk-holiday', 'No deliveries on UK public holidays'];
                }
                
                return [true, '', ''];
            },
            onSelect: function(dateText) {
                $(this).trigger('change');
                
                // Update hidden field
                if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                    $('form.checkout').append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + dateText + '">');
                } else {
                    $('input[name="ggt_delivery_date_hidden"]').val(dateText);
                }
            }
        });
    }
})(jQuery);
