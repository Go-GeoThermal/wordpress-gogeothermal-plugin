(function($) {
    'use strict';
    
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            initDeliveryDatePicker();
        }
    });
    
    function initDeliveryDatePicker() {
        console.log('🔄 [GGT] Initializing delivery date picker...');
        
        if (!$('#ggt_delivery_date').length) {
            console.log('⚠️ [GGT] Delivery date field not found');
            return;
        }
        
        // UK public holidays for the current and next year
        const ukHolidays = getUKHolidays();
        
        // Initialize the date picker
        $('#ggt_delivery_date').datepicker({
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
    }
    
    function getUKHolidays() {
        // List of UK public holidays for current and next year
        // This is a static list that should be updated annually
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
    
})(jQuery);
