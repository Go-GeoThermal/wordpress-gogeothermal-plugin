<?php
/**
 * Order Progress functionality for the Go Geothermal Plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GGT_Order_Progress {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add button to the orders table
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_order_progress_button'), 10, 2);
        
        // Register AJAX handlers
        add_action('wp_ajax_ggt_get_order_progress', array($this, 'ajax_get_order_progress'));
        
        // Enqueue scripts and styles - higher priority to ensure loading
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 20);
        
        // Add debug action - accessible via admin-ajax.php
        add_action('wp_ajax_ggt_test_order_progress', array($this, 'ajax_test_order_progress'));
    }
    
    /**
     * Add an Order Progress button to the My Orders actions
     *
     * @param array $actions Array of actions
     * @param WC_Order $order Order object
     * @return array Modified actions
     */
    public function add_order_progress_button($actions, $order) {
        // Only add the button for completed or processing orders
        if ($order->is_paid() && in_array($order->get_status(), array('completed', 'processing', 'shipped', 'on-hold'))) {
            // Get order number - either from order metadata or order ID
            $order_number = $order->get_meta('_order_number');
            if (empty($order_number)) {
                $order_number = $order->get_order_number();
            }
            
            // Instead of using "javascript:void(0)" which doesn't work with all themes,
            // we'll use "#" but add a custom identifier to help us target it
            $actions['order_progress'] = array(
                'url'  => '#view-order-progress',
                'name' => __('Order Progress', 'sinappsus-ggt-wp-plugin'),
                'action' => 'order_progress', 
                'data-order-number' => $order_number,
                'data-order-id' => $order->get_id()
            );
        }
        
        return $actions;
    }
    
    /**
     * Test endpoint for debugging the order progress functionality
     */
    public function ajax_test_order_progress() {
        wp_send_json_success([
            'message' => 'Order progress AJAX endpoint is working',
            'time' => current_time('mysql'),
            'post_data' => $_POST
        ]);
    }
    
    /**
     * AJAX handler for getting order progress
     */
    public function ajax_get_order_progress() {
        // Log request for debugging
        error_log('Order progress AJAX request received: ' . json_encode($_POST));
        
        // Check for nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ggt_order_progress_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        // Check if user can view this order
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to view order progress.'));
        }
        
        // Get order number from request
        $order_number = isset($_POST['order_number']) ? sanitize_text_field($_POST['order_number']) : '';
        
        if (empty($order_number)) {
            wp_send_json_error(array('message' => 'Order number is required.'));
        }
        
        // Include the API connector if needed
        if (!function_exists('ggt_fetch_order_progress')) {
            // For testing purposes, return mock data if the function doesn't exist
            $mock_data = [
                'events' => [
                    [
                        'status' => 'processing',
                        'created_at' => date('Y-m-d H:i:s'),
                        'formattedDate' => date('F j, Y, g:i a'),
                        'courier' => 'Mock Courier',
                        'courierReference' => 'TRACK123456',
                    ],
                    [
                        'status' => 'shipped',
                        'created_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                        'formattedDate' => date('F j, Y, g:i a', strtotime('+1 day')),
                        'courier' => 'Mock Courier',
                        'courierReference' => 'TRACK123456',
                        'batch' => 'BATCH001'
                    ]
                ]
            ];
            
            wp_send_json_success($mock_data);
            return;
        }
        
        // Fetch order progress
        $progress_data = ggt_fetch_order_progress($order_number);
        
        // Check for errors in response
        if (isset($progress_data['error'])) {
            wp_send_json_error(array(
                'message' => 'Error retrieving order progress: ' . $progress_data['error'],
                'order_number' => $order_number
            ));
        }
        
        // Return the progress data
        wp_send_json_success($progress_data);
    }
    
    /**
     * Enqueue CSS and JavaScript for the order progress functionality
     */
    public function enqueue_assets() {
        // Only load on account page
        if (!is_account_page()) {
            return;
        }
        
        // Debug message in console
        add_action('wp_footer', function() {
            ?>
            <script>
            console.log("[GGT Debug] Order Progress scripts loading on account page");
            </script>
            <?php
        }, 5);
        
        // Enqueue the CSS with versioning to break cache
        wp_enqueue_style(
            'ggt-order-progress-css',
            plugins_url('assets/css/order-progress.css', dirname(__FILE__)),
            array(),
            time() // Use current time as version to prevent caching
        );
        
        // Enqueue jQuery explicitly first
        wp_enqueue_script('jquery');
        
        // Enqueue JavaScript with versioning to break cache
        wp_enqueue_script(
            'ggt-order-progress-js',
            plugins_url('assets/js/order-progress.js', dirname(__FILE__)),
            array('jquery'),
            time(), // Use current time as version to prevent caching
            true
        );
        
        // Add localized script variables
        wp_localize_script('ggt-order-progress-js', 'ggt_order_progress', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ggt_order_progress_nonce'),
            'loading_text' => __('Loading order progress...', 'sinappsus-ggt-wp-plugin'),
            'error_text' => __('Error loading order progress.', 'sinappsus-ggt-wp-plugin'),
            'no_progress_text' => __('No progress updates available for this order.', 'sinappsus-ggt-wp-plugin'),
            'debug_mode' => true // Enable debug mode
        ));
        
        // Add a critical inline script to the head to catch buttons before page loads
        add_action('wp_head', array($this, 'add_early_script'), 5);
        
        // Add inline script for direct button handler
        add_action('wp_footer', array($this, 'add_inline_script'), 20);
        
        // Add the modal template to the footer
        add_action('wp_footer', array($this, 'add_progress_modal_template'));
    }
    
    /**
     * Add early script to catch WooCommerce order table interactions
     */
    public function add_early_script() {
        ?>
        <script type="text/javascript">
        // Early script to catch and modify links as soon as they appear in DOM
        function ggtInitEarlyButtonHandler() {
            if (typeof jQuery === 'function') {
                console.log('[GGT Debug] Setting up early button handlers');
                
                // Use more specific selector to only target Order Progress buttons
                jQuery(document).off('click', 'a[href*="view-order-progress"]');
                jQuery(document).on('click', '.woocommerce-orders-table a[href*="view-order-progress"]', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $link = jQuery(this);
                    var orderNumber = $link.data('order-number') || $link.attr('data-order-number');
                    var orderId = $link.data('order-id') || $link.attr('data-order-id');
                    
                    console.log('[GGT Debug] Order Progress link intercepted:', orderNumber);
                    
                    // Try to get order info from parent row if not on the button
                    if (!orderNumber) {
                        var $row = $link.closest('tr');
                        var $orderNumberElement = $row.find('.woocommerce-orders-table__cell-order-number a');
                        if ($orderNumberElement.length) {
                            // Try getting from the text content first
                            orderNumber = $orderNumberElement.text().trim();
                            console.log('[GGT Debug] Found order number from row text:', orderNumber);
                            
                            // Also try extracting from URL as a backup
                            var orderUrl = $orderNumberElement.attr('href');
                            if (orderUrl) {
                                var orderMatch = orderUrl.match(/\/(\d+)\/?$/);
                                if (orderMatch && orderMatch[1]) {
                                    orderId = orderMatch[1];
                                    // Only update order number if we didn't already find it
                                    if (!orderNumber) orderNumber = orderId;
                                    console.log('[GGT Debug] Found order ID from URL:', orderId);
                                }
                            }
                        }
                    }
                    
                    // Log what we found for debugging
                    console.log('[GGT Debug] Order data to be used:', {
                        orderNumber: orderNumber,
                        orderId: orderId
                    });
                    
                    if (orderNumber || orderId) {
                        if (typeof window.ggtShowOrderProgress === 'function') {
                            // Always pass at least one value, preferring order number
                            window.ggtShowOrderProgress(orderNumber || orderId, orderId);
                        } else {
                            console.log('[GGT Debug] ggtShowOrderProgress function not available yet');
                            // Store info to call later when function is available
                            window.ggtPendingOrderProgress = {
                                orderNumber: orderNumber || orderId,
                                orderId: orderId
                            };
                        }
                    } else {
                        console.error('[GGT Debug] Could not determine order number or ID');
                    }
                    
                    return false;
                });
                
                // Target action buttons specifically with more specific selector
                jQuery(document).off('click', '.order-actions .button.order_progress');
                jQuery(document).on('click', '.woocommerce-orders-table .order-actions .button.order_progress', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('[GGT Debug] Order actions button clicked');
                    
                    // ...existing code...
                });
            }
        }
        
        // Run early initialization
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ggtInitEarlyButtonHandler);
        } else {
            ggtInitEarlyButtonHandler();
        }
        </script>
        <?php
    }
    
    /**
     * Add inline script for direct button click handling
     */
    public function add_inline_script() {
        ?>
        <script type="text/javascript">
        // Global function to show order progress
        window.ggtShowOrderProgress = function(orderNumber, orderId) {
            console.log('[GGT Debug] Direct click handler called with:', orderNumber, orderId);
            
            // Ensure we have a valid value for order number
            if (!orderNumber && orderId) {
                orderNumber = orderId;
                console.log('[GGT Debug] Using order ID as order number:', orderNumber);
            }
            
            // Validate order number format - remove any non-numeric characters if needed
            if (orderNumber && typeof orderNumber === 'string') {
                // If it contains letters, leave as is, otherwise make sure it's just numbers
                if (!/[a-zA-Z]/.test(orderNumber) && /\D/.test(orderNumber)) {
                    orderNumber = orderNumber.replace(/\D/g, '');
                    console.log('[GGT Debug] Cleaned order number:', orderNumber);
                }
            }
            
            if (!orderNumber) {
                console.error('[GGT Error] No valid order number or ID provided');
                alert('Cannot show order progress: No order number found.');
                return false;
            }
            
            if (typeof jQuery === 'function') {
                try {
                    // Check if modal exists and create it if not
                    if (jQuery('#ggt-progress-modal').length === 0) {
                        console.log('[GGT Debug] Creating modal element dynamically');
                        jQuery('body').append(`
                            <div id="ggt-progress-modal" class="ggt-modal" style="display:none;">
                                <div class="ggt-modal-content">
                                    <div class="ggt-modal-header">
                                        <h2>Order Progress: <span id="ggt-modal-order-number"></span></h2>
                                        <span id="ggt-progress-modal-close" class="ggt-modal-close">&times;</span>
                                    </div>
                                    <div class="ggt-modal-body">
                                        <div id="ggt-progress-content">
                                            <div class="ggt-loading">Loading progress information...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);
                    }
                    
                    // Show loading state
                    jQuery('#ggt-progress-content').html('<div class="ggt-loading">Loading order progress...</div>');
                    jQuery('#ggt-modal-order-number').text(orderNumber || '');
                    
                    // Force styles to ensure visibility
                    jQuery('#ggt-progress-modal').attr('style', 
                        'display: block !important; ' +
                        'opacity: 1 !important; ' + 
                        'visibility: visible !important; ' +
                        'z-index: 999999 !important;'
                    );
                    jQuery('body').addClass('ggt-modal-open');
                    
                    console.log('[GGT Debug] Modal display state:', jQuery('#ggt-progress-modal').css('display'));
                    
                    // Ensure we have the AJAX URL and nonce
                    var ajaxUrl = typeof ggt_order_progress !== 'undefined' ? 
                        ggt_order_progress.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
                    var nonce = typeof ggt_order_progress !== 'undefined' ? 
                        ggt_order_progress.nonce : '<?php echo wp_create_nonce('ggt_order_progress_nonce'); ?>';
                    
                    // Log the exact AJAX request parameters for debugging
                    var requestParams = {
                        action: 'ggt_get_order_progress',
                        order_number: orderNumber,
                        nonce: nonce
                    };
                    console.log('[GGT Debug] AJAX request parameters:', requestParams);
                    
                    // Trigger the AJAX request
                    jQuery.ajax({
                        url: ajaxUrl,
                        method: 'POST',
                        data: requestParams,
                        success: function(response) {
                            console.log('[GGT Debug] Order progress response:', response);
                            
                            if (response.success && response.data && response.data.events) {
                                // Process and display the timeline
                                let eventsHtml = '<div class="ggt-order-header">' +
                                    '<p class="ggt-timeline-intro">Your order has the following progress updates:</p>' +
                                    '</div>' +
                                    '<div id="ggt-progress-timeline" class="ggt-timeline">';
                                    
                                response.data.events.forEach(function(event) {
                                    eventsHtml += '<div class="ggt-timeline-item">' +
                                        '<div class="ggt-timeline-marker"></div>' +
                                        '<div class="ggt-timeline-content">' +
                                        '<h4>' + (event.status || 'Update') + '</h4>' +
                                        '<time>' + (event.formattedDate || '') + '</time>' +
                                        '<div class="ggt-timeline-details">';
                                        
                                    if (event.courier) {
                                        eventsHtml += '<span class="ggt-timeline-chip">Courier: ' + event.courier + '</span>';
                                    }
                                    
                                    if (event.courierReference) {
                                        eventsHtml += '<span class="ggt-timeline-chip">Tracking: ' + event.courierReference + '</span>';
                                    }
                                    
                                    if (event.batch) {
                                        eventsHtml += '<span class="ggt-timeline-chip">Batch: ' + event.batch + '</span>';
                                    }
                                    
                                    eventsHtml += '</div></div></div>';
                                });
                                
                                eventsHtml += '</div>';
                                jQuery('#ggt-progress-content').html(eventsHtml);
                                
                            } else {
                                jQuery('#ggt-progress-content').html('<div class="ggt-error-message">' + 
                                    '<p>' + (response.data?.message || 'No progress data available') + '</p></div>');
                                
                                // Log error details for debugging
                                console.error('[GGT Error] API response error:', response);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('[GGT Debug] AJAX Error:', error, xhr.responseText);
                            jQuery('#ggt-progress-content').html('<div class="ggt-error-message">' + 
                                '<p>Error loading order progress. Please try again.</p>' + 
                                '<p>Technical details: ' + status + ' - ' + error + '</p></div>');
                            
                            try {
                                var responseJson = JSON.parse(xhr.responseText);
                                console.error('[GGT Error] Parsed response:', responseJson);
                            } catch (e) {
                                // Just log the raw text if parsing fails
                            }
                        }
                    });
                    
                    // Set up close button handlers
                    jQuery('#ggt-progress-modal-close').on('click', function() {
                        jQuery('#ggt-progress-modal').hide();
                        jQuery('body').removeClass('ggt-modal-open');
                    });
                    
                    jQuery('#ggt-progress-modal').on('click', function(e) {
                        if (jQuery(e.target).is('#ggt-progress-modal')) {
                            jQuery('#ggt-progress-modal').hide();
                            jQuery('body').removeClass('ggt-modal-open');
                        }
                    });
                    
                } catch(e) {
                    console.error('[GGT Error] Exception in order progress handler:', e);
                    alert('Error displaying order progress: ' + e.message);
                }
            } else {
                console.error('[GGT Error] jQuery not loaded');
                alert('Unable to load order progress: jQuery is not available. Please refresh the page and try again.');
            }
            
            return false;
        }
        
        // Execute any pending order progress requests
        jQuery(document).ready(function($) {
            console.log('[GGT Debug] Document ready: Setting up modal close handlers');
            
            // Use more specific selectors for button detection
            console.log('[GGT Debug] Looking for buttons with selectors:', 
                '.woocommerce-orders-table a[href*="view-order-progress"]:', $('.woocommerce-orders-table a[href*="view-order-progress"]').length,
                '.woocommerce-orders-table .order-actions .button.order_progress:', $('.woocommerce-orders-table .order-actions .button.order_progress').length
            );
            
            // Look for potential order progress buttons with more specific scope
            $('.woocommerce-orders-table .button, .woocommerce-orders-table .woocommerce-button').each(function() {
                var $btn = $(this);
                var text = $btn.text().trim();
                if (text.indexOf('Progress') !== -1 || text.indexOf('progress') !== -1) {
                    console.log('[GGT Debug] Found potential order progress button:', {
                        text: text,
                        html: $btn.prop('outerHTML'),
                        data: {
                            orderNumber: $btn.data('order-number'),
                            orderId: $btn.data('order-id')
                        }
                    });
                    
                    // Attach our handler to these buttons too
                    $btn.addClass('ggt-order-progress-button');
                    $btn.off('click').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var orderNumber = $btn.data('order-number') || '';
                        var orderId = $btn.data('order-id') || '';
                        if (!orderNumber && !orderId) {
                            // Try to extract from closest row
                            var $row = $btn.closest('tr');
                            if ($row.length) {
                                var $orderCell = $row.find('.woocommerce-orders-table__cell-order-number a');
                                if ($orderCell.length) {
                                    orderNumber = $orderCell.text().trim();
                                }
                            }
                        }
                        window.ggtShowOrderProgress(orderNumber, orderId);
                        return false;
                    });
                }
            });
            
            // Identify order progress buttons with more specific selectors
            $('.woocommerce-orders-table a[href*="view-order-progress"], .woocommerce-orders-table .order-actions .button.order_progress').each(function() {
                console.log('[GGT Debug] Found order progress button:', this);
                $(this).addClass('ggt-order-progress-button');
            });
            
            // Process any pending order progress requests
            if (window.ggtPendingOrderProgress) {
                console.log('[GGT Debug] Processing pending order progress request');
                window.ggtShowOrderProgress(
                    window.ggtPendingOrderProgress.orderNumber,
                    window.ggtPendingOrderProgress.orderId
                );
                window.ggtPendingOrderProgress = null;
            }
            
            // Add global click handlers with more specific selectors
            $(document).off('click.ggt-order-progress');
            $(document).on('click.ggt-order-progress', 
                '.woocommerce-orders-table .ggt-order-progress-button, ' + 
                '.woocommerce-orders-table a[href*="view-order-progress"], ' + 
                '.woocommerce-orders-table .order-actions .button.order_progress', 
                function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $button = $(this);
                    var orderNumber = $button.data('order-number') || $button.attr('data-order-number');
                    var orderId = $button.data('order-id') || $button.attr('data-order-id');
                    
                    console.log('[GGT Debug] Button click handler fired:', orderNumber);
                    
                    window.ggtShowOrderProgress(orderNumber, orderId);
                    return false;
                }
            );
        });
        </script>
        <?php
    }
    
    /**
     * Add the modal template to the footer with extra styles for visibility
     */
    public function add_progress_modal_template() {
        if (!is_account_page()) {
            return;
        }
        
        ?>
        <style>
        /* Essential modal styles - Force these to ensure visibility */
        .ggt-modal {
            display: none;
            position: fixed !important;
            z-index: 999999 !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: 100% !important;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6) !important;
            padding: 0 !important;
            margin: 0 !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .ggt-modal-content {
            position: relative !important;
            background-color: #fff !important;
            margin: 10vh auto !important;
            padding: 20px !important;
            border-radius: 5px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
            width: 90% !important;
            max-width: 700px !important;
        }
        .ggt-modal-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding-bottom: 15px !important;
            border-bottom: 1px solid #eee !important;
        }
        .ggt-modal-close {
            color: #aaa !important;
            float: right !important;
            font-size: 28px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            line-height: 1 !important;
        }
        .ggt-modal-close:hover {
            color: #333 !important;
            text-decoration: none !important;
        }
        .ggt-loading {
            text-align: center !important;
            padding: 20px !important;
        }
        .ggt-loading:after {
            content: "";
            display: inline-block;
            width: 40px;
            height: 40px;
            margin: 8px 0 0 8px;
            border-radius: 50%;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            animation: ggt-spin 1s linear infinite;
        }
        @keyframes ggt-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        body.ggt-modal-open {
            overflow: hidden !important;
        }
        </style>
        <div id="ggt-progress-modal" class="ggt-modal" style="display:none !important;">
            <div class="ggt-modal-content">
                <div class="ggt-modal-header">
                    <h2><?php _e('Order Progress', 'sinappsus-ggt-wp-plugin'); ?>: <span id="ggt-modal-order-number"></span></h2>
                    <span id="ggt-progress-modal-close" class="ggt-modal-close">&times;</span>
                </div>
                <div class="ggt-modal-body">
                    <div id="ggt-progress-content">
                        <div class="ggt-loading"><?php _e('Loading progress information...', 'sinappsus-ggt-wp-plugin'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the class
new GGT_Order_Progress();
