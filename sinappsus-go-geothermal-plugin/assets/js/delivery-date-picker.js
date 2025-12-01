(function($) {
    'use strict';
    
    let ukHolidays = [];
    
    $(document).ready(function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            // Fetch UK holidays first, then initialize date picker
            fetchUKHolidays().then(function() {
                setTimeout(initDeliveryDatePicker, 500);
            });
            
            // Add form submission event handler
            $('form.checkout').on('checkout_place_order submit', function() {
                const deliveryDate = $('#ggt_delivery_date').val();
                
                if (!deliveryDate) {
                    alert('Please select a delivery date before placing your order.');
                    $('#ggt_delivery_date').focus();
                    return false;
                }
                
                // Convert UK format to ISO format for backend
                const dateParts = deliveryDate.split('/');
                const isoDate = dateParts[2] + '-' + dateParts[1].padStart(2, '0') + '-' + dateParts[0].padStart(2, '0');
                
                // Add hidden field as backup
                if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                    $('form.checkout').append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + isoDate + '">');
                } else {
                    $('input[name="ggt_delivery_date_hidden"]').val(isoDate);
                }
                
                return true;
            });
        }
    });
    
    function fetchUKHolidays() {
        return new Promise(function(resolve) {
            $.ajax({
                url: 'https://www.gov.uk/bank-holidays.json',
                method: 'GET',
                dataType: 'json',
                timeout: 5000,
                success: function(data) {
                    ukHolidays = [];
                    
                    // Extract holidays for England and Wales, Scotland, and Northern Ireland
                    if (data['england-and-wales'] && data['england-and-wales'].events) {
                        data['england-and-wales'].events.forEach(function(holiday) {
                            ukHolidays.push(holiday.date);
                        });
                    }
                    
                    // Optionally include Scottish holidays
                    if (data['scotland'] && data['scotland'].events) {
                        data['scotland'].events.forEach(function(holiday) {
                            if (ukHolidays.indexOf(holiday.date) === -1) {
                                ukHolidays.push(holiday.date);
                            }
                        });
                    }
                    
                    // Optionally include Northern Ireland holidays
                    if (data['northern-ireland'] && data['northern-ireland'].events) {
                        data['northern-ireland'].events.forEach(function(holiday) {
                            if (ukHolidays.indexOf(holiday.date) === -1) {
                                ukHolidays.push(holiday.date);
                            }
                        });
                    }
                    
                    console.log('UK holidays loaded:', ukHolidays.length + ' holidays found');
                    resolve();
                },
                error: function(xhr, status, error) {
                    console.warn('Failed to fetch UK holidays from gov.uk API:', error);
                    console.warn('Using fallback holiday list');
                    
                    // Fallback to static list for current and next year
                    const currentYear = new Date().getFullYear();
                    ukHolidays = [
                        // Current year fallback
                        currentYear + '-01-01', // New Year's Day
                        currentYear + '-12-25', // Christmas Day
                        currentYear + '-12-26', // Boxing Day
                        
                        // Next year fallback
                        (currentYear + 1) + '-01-01',
                        (currentYear + 1) + '-12-25',
                        (currentYear + 1) + '-12-26'
                    ];
                    
                    resolve();
                }
            });
        });
    }
    
    function initDeliveryDatePicker() {
        if (!$('#ggt_delivery_date').length) return;
        if ($('#ggt_delivery_date').hasClass('hasDatepicker')) return;
        
        // Get min date offset from server, default to +1d if not set
        let minDateOffset = '+1d';
        if (typeof ggt_vars !== 'undefined' && ggt_vars.min_date_offset) {
            minDateOffset = '+' + ggt_vars.min_date_offset + 'd';
        }

        $('#ggt_delivery_date').datepicker({
            dateFormat: 'dd/mm/yy', // UK date format
            minDate: minDateOffset,
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
                
                // Convert UK format to ISO format for backend storage
                const dateParts = dateText.split('/');
                const isoDate = dateParts[2] + '-' + dateParts[1].padStart(2, '0') + '-' + dateParts[0].padStart(2, '0');
                
                // Update hidden field with ISO format
                if (!$('input[name="ggt_delivery_date_hidden"]').length) {
                    $('form.checkout').append('<input type="hidden" name="ggt_delivery_date_hidden" value="' + isoDate + '">');
                } else {
                    $('input[name="ggt_delivery_date_hidden"]').val(isoDate);
                }
            }
        });
    }
})(jQuery);
