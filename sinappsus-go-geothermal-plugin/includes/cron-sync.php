<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add custom cron interval
add_filter('cron_schedules', 'ggt_add_cron_interval');
function ggt_add_cron_interval($schedules) {
    $schedules['twodays'] = array(
        'interval' => 2 * 24 * 60 * 60, // 2 days in seconds
        'display'  => esc_html__('Every Two Days'),
    );
    return $schedules;
}

// Hook for scheduling
add_action('init', 'ggt_schedule_cron_events');

function ggt_schedule_cron_events() {
    if (!wp_next_scheduled('ggt_daily_sync_event')) {
        wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'ggt_daily_sync_event');
    }
    
    if (!wp_next_scheduled('ggt_log_cleanup_event')) {
        wp_schedule_event(strtotime('tomorrow midnight'), 'twodays', 'ggt_log_cleanup_event');
    }
}

// Hook for execution
add_action('ggt_daily_sync_event', 'ggt_execute_daily_sync');
add_action('ggt_log_cleanup_event', 'ggt_execute_log_cleanup');

function ggt_execute_log_cleanup() {
    // Check if cleanup is enabled
    if (!get_option('ggt_log_cleanup_enabled', 1)) {
        return;
    }

    $log_file = GGT_SINAPPSUS_PLUGIN_PATH . 'logs/debug.log';
    
    if (file_exists($log_file)) {
        // Delete the file completely as requested
        @unlink($log_file);
        
        if (function_exists('ggt_log')) {
            ggt_log("Logs cleaned up (deleted) by scheduled task.", 'CRON');
        }
    }
}

function ggt_execute_daily_sync() {
    // Check if plugin is enabled
    if (!get_option('ggt_plugin_enabled', 1)) {
        if (function_exists('ggt_log')) ggt_log("Daily Sync: Plugin disabled, skipping.", 'CRON');
        return;
    }

    // Auto Sync Products
    if (get_option('ggt_auto_sync_products', 0)) {
        // Check if mapping is configured
        $mapping = get_option('ggt_product_field_mapping', array());
        if (!empty($mapping)) {
             ggt_run_product_sync_cron();
        } else {
             if (function_exists('ggt_log')) ggt_log("Daily Sync: Product mapping empty, skipping products.", 'CRON');
        }
    }

    // Auto Sync Users
    if (get_option('ggt_auto_sync_users', 0)) {
        // Check if mapping is configured
        $mapping = get_option('ggt_user_field_mapping', array());
        if (!empty($mapping)) {
             ggt_run_user_sync_cron();
        } else {
             if (function_exists('ggt_log')) ggt_log("Daily Sync: User mapping empty, skipping users.", 'CRON');
        }
    }
}

function ggt_run_product_sync_cron() {
    if (function_exists('ggt_log')) ggt_log("Starting Daily Product Sync", 'CRON');
    
    // increase limits for cron
    @ini_set('memory_limit', '1024M');
    @set_time_limit(0);

    // 1. Fetch from API
    $response = ggt_sinappsus_connect_to_api('products');
    if (isset($response['error']) || !is_array($response)) {
        if (function_exists('ggt_log')) ggt_log("Product Sync Failed: API Error or invalid response", 'CRON');
        return;
    }

    $api_products = $response;
    $count = count($api_products);
    if (function_exists('ggt_log')) ggt_log("Fetched {$count} products from API", 'CRON');

    // 2. Get existing products
    if (!function_exists('ggt_core_get_all_products')) {
        if (function_exists('ggt_log')) ggt_log("Product Sync Failed: ggt_core_get_all_products not found", 'CRON');
        return;
    }

    $existing_products_raw = ggt_core_get_all_products();
    $existingByStockCode = [];
    $existingBySku = [];

    foreach ($existing_products_raw as $p) {
        if (!empty($p['stockCode'])) $existingByStockCode[$p['stockCode']] = $p['id'];
        if (!empty($p['sku'])) $existingBySku[$p['sku']] = $p['id'];
    }

    $updated = 0;
    $created = 0;
    $skipped = 0;

    // 3. Loop and sync
    foreach ($api_products as $product_data) {
        // Validation same as JS
        if (empty($product_data['stockCode'])) {
            $skipped++;
            continue;
        }

        $existingId = $existingByStockCode[$product_data['stockCode']] ?? 
                      ($product_data['sku'] ? ($existingBySku[$product_data['sku']] ?? null) : null);

        if ($existingId) {
            $res = ggt_core_update_product($existingId, $product_data);
            if (!is_wp_error($res)) $updated++;
        } else {
             // Check inactive flag
             if (empty($product_data['inactiveFlag']) || $product_data['inactiveFlag'] === false || $product_data['inactiveFlag'] === "0" || $product_data['inactiveFlag'] === 0) {
                $res = ggt_core_create_product($product_data);
                if (!is_wp_error($res)) $created++;
             } else {
                 $skipped++;
             }
        }
    }

    if (function_exists('ggt_log')) ggt_log("Daily Product Sync Complete: Created {$created}, Updated {$updated}, Skipped {$skipped}", 'CRON');
    
    // Update last sync timestamp option
    update_option('ggt_last_product_import', array(
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'timestamp' => time(),
    ));
}

function ggt_run_user_sync_cron() {
    if (function_exists('ggt_log')) ggt_log("Starting Daily User Sync", 'CRON');
    
    // increase limits for cron
    @ini_set('memory_limit', '1024M');
    @set_time_limit(0);

    // 1. Fetch from API
    $response = ggt_sinappsus_connect_to_api('customers');
    if (isset($response['error']) || !is_array($response)) {
        if (function_exists('ggt_log')) ggt_log("User Sync Failed: API Error or invalid response", 'CRON');
        return;
    }

    $api_users = $response;
    $count = count($api_users);
    if (function_exists('ggt_log')) ggt_log("Fetched {$count} users from API", 'CRON');

    if (!function_exists('ggt_core_sync_user')) {
        if (function_exists('ggt_log')) ggt_log("User Sync Failed: ggt_core_sync_user not found", 'CRON');
        return;
    }

    $updated = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($api_users as $user_data) {
        if (empty($user_data['email'])) {
            $skipped++;
            if (function_exists('ggt_log')) {
                // Log why it was skipped. Try to find some identifier.
                $ref = isset($user_data['accountRef']) ? $user_data['accountRef'] : (isset($user_data['companyName']) ? $user_data['companyName'] : 'Unknown');
                ggt_log("User Sync Skipped: Missing email address. Ref: " . $ref, 'CRON');
            }
            continue;
        }

        $res = ggt_core_sync_user($user_data);
        if (is_wp_error($res)) {
            $failed++;
             if (function_exists('ggt_log')) ggt_log("User Sync Error for {$user_data['email']}: " . $res->get_error_message(), 'CRON');
        } else {
            $updated++;
        }
    }

    if (function_exists('ggt_log')) ggt_log("Daily User Sync Complete: Updated {$updated}, Failed {$failed}, Skipped {$skipped}", 'CRON');

    // Update last sync timestamp option
    update_option('ggt_last_user_sync', array(
        'updated' => $updated,
        'failed' => $failed,
        'total' => $count,
        'timestamp' => time(),
    ));
}
