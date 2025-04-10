<?php
/**
 * Order Progress Button Test
 */

// Bootstrap WordPress
require_once(dirname(dirname(dirname(__DIR__))) . '/wp-load.php');

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Admin access required');
}

// Get an order to test with
$test_orders = wc_get_orders(array(
    'limit' => 1,
    'status' => array('processing', 'completed', 'on-hold')
));

$test_order = !empty($test_orders) ? reset($test_orders) : null;
$order_number = $test_order ? $test_order->get_order_number() : '12345';
$order_id = $test_order ? $test_order->get_id() : '1';

// Load the WC styles
wp_enqueue_style('woocommerce-general');

// Include jQuery
wp_enqueue_script('jquery');

// Include our scripts
wp_enqueue_style('ggt-order-progress-css', plugins_url('assets/css/order-progress.css', __FILE__));
wp_enqueue_script('ggt-order-progress-js', plugins_url('assets/js/order-progress.js', __FILE__), array('jquery'), null, true);

wp_localize_script('ggt-order-progress-js', 'ggt_order_progress', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('ggt_order_progress_nonce'),
    'loading_text' => __('Loading order progress...'),
    'error_text' => __('Error loading order progress.'),
    'no_progress_text' => __('No progress updates available for this order.'),
    'debug_mode' => true
));

// Get our order progress class
require_once __DIR__ . '/includes/class-order-progress.php';
$order_progress = new GGT_Order_Progress();

// Output the header
wp_head();
?>

<div class="wrap" style="padding: 20px; max-width: 800px; margin: 0 auto;">
    <h1>Order Progress Button Test</h1>
    
    <p>This page allows you to test the Order Progress button functionality.</p>
    
    <div style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; background: #f8f8f8;">
        <h2>Test Button</h2>
        
        <p><strong>Order Number:</strong> <?php echo esc_html($order_number); ?></p>
        <p><strong>Order ID:</strong> <?php echo esc_html($order_id); ?></p>
        
        <p>
            <!-- Test button with attributes -->
            <a href="#" class="button ggt-order-progress-button" 
               data-order-number="<?php echo esc_attr($order_number); ?>" 
               data-order-id="<?php echo esc_attr($order_id); ?>"
               onclick="ggtShowOrderProgress('<?php echo esc_js($order_number); ?>', '<?php echo esc_js($order_id); ?>'); return false;">
                Test Order Progress Button
            </a>
        </p>
        
        <p>
            <!-- Direct function call button -->
            <button type="button" class="button" onclick="window.ggtShowOrderProgress('<?php echo esc_js($order_number); ?>', '<?php echo esc_js($order_id); ?>');">
                Direct Function Call
            </button>
        </p>
    </div>
    
    <div style="margin: 20px 0;">
        <h2>Debug Information</h2>
        <pre id="debug-info" style="background: #f0f0f0; padding: 10px; max-height: 300px; overflow: auto;"></pre>
    </div>
    
    <script>
    // Debug helper
    function addDebug(message) {
        var debugEl = document.getElementById('debug-info');
        var time = new Date().toLocaleTimeString();
        debugEl.textContent = time + ': ' + message + '\n' + debugEl.textContent;
    }
    
    // Document ready
    jQuery(document).ready(function($) {
        addDebug('Page loaded. jQuery version: ' + $.fn.jquery);
        
        // Log button details
        var $button = $('.ggt-order-progress-button');
        addDebug('Found buttons: ' + $button.length);
        
        if ($button.length) {
            addDebug('Button details: ' + 
                     '\nClass: ' + $button.attr('class') + 
                     '\nOrder number: ' + $button.data('order-number') + 
                     '\nOrder ID: ' + $button.data('order-id'));
        }
        
        // Log modal details
        if ($('#ggt-progress-modal').length) {
            addDebug('Modal found in DOM');
        } else {
            addDebug('Modal NOT found in DOM');
        }
        
        // Add additional click handler
        $button.on('click', function(e) {
            addDebug('Button clicked via jQuery handler');
        });
    });
    </script>
</div>

<?php
// Ensure our modal and scripts are added
$order_progress->add_progress_modal_template();
$order_progress->add_inline_script();

// Output the footer
wp_footer();
?>
