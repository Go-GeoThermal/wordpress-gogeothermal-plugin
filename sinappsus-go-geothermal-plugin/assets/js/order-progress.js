/**
 * Order Progress Timeline functionality
 */
(function($) {
    'use strict';
    
    // Debug logger function
    function logDebug(message, data) {
        if (typeof ggt_order_progress !== 'undefined' && ggt_order_progress.debug_mode) {
            if (data) {
                console.log('[GGT Debug]', message, data);
            } else {
                console.log('[GGT Debug]', message);
            }
        }
    }
    
    // Log when the script loads
    logDebug('Order Progress JS loaded');
    
    // Initialize the module when document is ready
    $(document).ready(function() {
        logDebug('Document ready, initializing Order Progress');
        initOrderProgressButtons();
    });
    
    // Set up the Order Progress buttons
    function initOrderProgressButtons() {
        logDebug('Initializing Order Progress buttons');
        
        // Check if we have the necessary global variables
        if (typeof ggt_order_progress === 'undefined') {
            console.error('[GGT Error] Required ggt_order_progress object is missing');
            return;
        }
        
        // Use more specific selectors to target only Order Progress buttons
        // Look for specific elements with the right class or data attribute
        const $buttons = $('.woocommerce-orders-table .order_progress, ' + 
                         'a[href="#view-order-progress"], ' + 
                         '.woocommerce-button[data-order-number]');
        
        logDebug('Found ' + $buttons.length + ' Order Progress buttons');
        
        // Remove any previous click handlers first to prevent duplicates
        $buttons.off('click.ggt-order-progress');
        
        // Attach click handler directly to buttons with a namespace
        $buttons.on('click.ggt-order-progress', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            logDebug('Order Progress button clicked');
            
            const orderNumber = $(this).data('order-number');
            const orderId = $(this).data('order-id');
            
            if (!orderNumber && !orderId) {
                logDebug('Order number not found, trying to get from parent row');
                const $row = $(this).closest('tr');
                if ($row.length) {
                    const $orderCell = $row.find('.woocommerce-orders-table__cell-order-number a');
                    if ($orderCell.length) {
                        const orderText = $orderCell.text().trim();
                        if (orderText) {
                            logDebug('Found order number from row: ' + orderText);
                            showProgressModal(ggt_order_progress.loading_text || 'Loading order progress...', orderText);
                            fetchOrderProgress(orderText);
                            return false;
                        }
                    }
                }
                console.error('[GGT Error] Order number not found for progress button');
                return false;
            }
            
            logDebug('Processing order', {number: orderNumber, id: orderId});
            
            // Show loading state
            showProgressModal(ggt_order_progress.loading_text || 'Loading order progress...', orderNumber || orderId);
            
            // Remove existing timeline if any
            $('#ggt-progress-timeline').remove();
            
            // Fetch progress data
            fetchOrderProgress(orderNumber || orderId);
            
            return false;
        });
        
        // For delegated events, use much more specific selector
        $(document).off('click.ggt-order-progress');
        $(document).on('click.ggt-order-progress', 
            '.woocommerce-orders-table .order_progress, ' +
            '.woocommerce-orders-table .ggt-order-progress-button, ' +
            '.woocommerce-orders-table a[href="#view-order-progress"]', 
            function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                logDebug('Delegated Order Progress button clicked');
                
                const $button = $(this);
                const orderNumber = $button.data('order-number');
                const orderId = $button.data('order-id');
                
                if (!orderNumber && !orderId) {
                    const $row = $button.closest('tr');
                    if ($row.length) {
                        const $orderCell = $row.find('.woocommerce-orders-table__cell-order-number a');
                        if ($orderCell.length) {
                            const orderText = $orderCell.text().trim();
                            if (orderText) {
                                showProgressModal(ggt_order_progress.loading_text || 'Loading order progress...', orderText);
                                fetchOrderProgress(orderText);
                                return false;
                            }
                        }
                    }
                    return false;
                }
                
                showProgressModal(ggt_order_progress.loading_text || 'Loading order progress...', orderNumber || orderId);
                fetchOrderProgress(orderNumber || orderId);
                return false;
            }
        );
        
        // Close modal when clicking the close button or outside the modal
        $(document).on('click', '#ggt-progress-modal-close', function() {
            closeProgressModal();
        });
        
        $(document).on('click', '#ggt-progress-modal', function(e) {
            if ($(e.target).is('#ggt-progress-modal')) {
                closeProgressModal();
            }
        });
        
        // Also close with ESC key
        $(document).keyup(function(e) {
            if (e.key === "Escape") {
                closeProgressModal();
            }
        });
    }
    
    // Fetch order progress data via AJAX
    function fetchOrderProgress(orderNumber) {
        logDebug('Fetching order progress for', orderNumber);
        
        $.ajax({
            url: ggt_order_progress.ajax_url,
            method: 'POST',
            data: {
                action: 'ggt_get_order_progress',
                order_number: orderNumber,
                nonce: ggt_order_progress.nonce
            },
            success: function(response) {
                logDebug('AJAX response received', response);
                
                if (response.success && response.data && response.data.events) {
                    displayOrderTimeline(response.data.events, orderNumber);
                } else {
                    showProgressError(response.data?.message || ggt_order_progress.no_progress_text);
                }
            },
            error: function(xhr, status, error) {
                console.error('[GGT Error] Error fetching order progress:', error);
                logDebug('AJAX error details', {xhr: xhr, status: status});
                showProgressError(ggt_order_progress.error_text);
            }
        });
    }
    
    // Show the progress modal with content
    function showProgressModal(content, orderNumber) {
        logDebug('Showing progress modal', {content: content, orderNumber: orderNumber});
        
        // Set content and order number
        $('#ggt-progress-content').html(content);
        $('#ggt-modal-order-number').text(orderNumber || '');
        
        // Show modal
        $('#ggt-progress-modal').show();
        
        // Add body class to prevent scrolling
        $('body').addClass('ggt-modal-open');
        
        // Check if the modal is visible in the DOM
        setTimeout(function() {
            if ($('#ggt-progress-modal').is(':visible')) {
                logDebug('Modal is visible in the DOM');
            } else {
                logDebug('Modal is NOT visible in the DOM');
            }
        }, 100);
    }
    
    // Close the progress modal
    function closeProgressModal() {
        logDebug('Closing progress modal');
        $('#ggt-progress-modal').hide();
        $('body').removeClass('ggt-modal-open');
    }
    
    // Show error message in the modal
    function showProgressError(message) {
        logDebug('Showing error message', message);
        const errorHtml = `
            <div class="ggt-error-message">
                <p>${message}</p>
            </div>
        `;
        $('#ggt-progress-content').html(errorHtml);
    }
    
    // Display the order timeline with events
    function displayOrderTimeline(events, orderNumber) {
        logDebug('Displaying timeline', {events: events, orderNumber: orderNumber});
        
        // Sort events by date if multiple events
        if (events.length > 1) {
            events.sort((a, b) => {
                return new Date(a.created_at) - new Date(b.created_at);
            });
        }
        
        let timelineHtml = '';
        
        if (events.length === 0) {
            timelineHtml = '<p class="ggt-no-events">' + ggt_order_progress.no_progress_text + '</p>';
        } else {
            timelineHtml = `
                <div class="ggt-order-header">
                    <p class="ggt-timeline-intro">Your order has the following progress updates:</p>
                </div>
                <div id="ggt-progress-timeline" class="ggt-timeline">`;
                
            events.forEach(event => {
                const statusClass = getStatusClass(event.status);
                const statusLabel = formatStatus(event.status);
                
                timelineHtml += `
                    <div class="ggt-timeline-item ${statusClass}">
                        <div class="ggt-timeline-marker"></div>
                        <div class="ggt-timeline-content">
                            <h4>${statusLabel}</h4>
                            <time>${event.formattedDate}</time>
                            <div class="ggt-timeline-details">`;
                
                // Add courier info if available
                if (event.courier) {
                    timelineHtml += `<span class="ggt-timeline-chip">Courier: ${event.courier}</span>`;
                }
                
                // Add tracking reference if available
                if (event.courierReference) {
                    timelineHtml += `<span class="ggt-timeline-chip">Tracking: ${event.courierReference}</span>`;
                }
                
                // Add batch info if available
                if (event.batch) {
                    timelineHtml += `<span class="ggt-timeline-chip">Batch: ${event.batch}</span>`;
                }
                
                timelineHtml += `
                            </div>
                        </div>
                    </div>`;
            });
            
            timelineHtml += '</div>';
        }
        
        $('#ggt-progress-content').html(timelineHtml);
    }
    
    // Format status text for display
    function formatStatus(status) {
        if (!status) return 'Unknown';
        
        // Convert snake_case or kebab-case to Title Case
        return status
            .replace(/_/g, ' ')
            .replace(/-/g, ' ')
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    }
    
    // Get CSS class for the status
    function getStatusClass(status) {
        if (!status) return 'ggt-status-unknown';
        
        // Map status to CSS classes
        const statusMapping = {
            'created': 'ggt-status-created',
            'processing': 'ggt-status-processing',
            'packed': 'ggt-status-packed',
            'ready_for_shipping': 'ggt-status-ready-shipping',
            'ready-for-shipping': 'ggt-status-ready-shipping',
            'shipped': 'ggt-status-shipped',
            'delivered': 'ggt-status-delivered',
            'completed': 'ggt-status-completed',
            'cancelled': 'ggt-status-cancelled',
            'on_hold': 'ggt-status-hold',
            'on-hold': 'ggt-status-hold',
            'returned': 'ggt-status-returned',
            'refunded': 'ggt-status-refunded',
            'failed': 'ggt-status-failed'
        };
        
        // Convert to lowercase for case-insensitive matching
        const lowercaseStatus = status.toLowerCase();
        
        // Return the class or unknown as fallback
        return statusMapping[lowercaseStatus] || 'ggt-status-unknown';
    }
    
    // Add a small test function to verify functionality
    window.testGGTOrderProgress = function() {
        logDebug('Running test function');
        showProgressModal('This is a test modal. The JS is working!', 'TEST-123');
        
        // Create some mock data for testing
        const mockEvents = [
            {
                status: 'processing',
                created_at: '2023-10-01T12:00:00',
                formattedDate: 'October 1, 2023, 12:00 pm',
                courier: 'Test Courier'
            },
            {
                status: 'shipped',
                created_at: '2023-10-03T09:30:00',
                formattedDate: 'October 3, 2023, 9:30 am',
                courier: 'Test Courier',
                courierReference: 'TRACK-12345'
            }
        ];
        
        // Display the mock timeline after a short delay
        setTimeout(() => {
            displayOrderTimeline(mockEvents, 'TEST-123');
        }, 2000);
        
        return 'Test initiated';
    };
    
})(jQuery);
