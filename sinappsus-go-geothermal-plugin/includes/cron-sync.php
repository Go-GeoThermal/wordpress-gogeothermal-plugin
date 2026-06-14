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

        // Always sync account sub-users (they don't need field mapping — fixed fields)
        ggt_run_account_sub_users_sync_cron();
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

/**
 * Resolve the best parent WP user for an accountRef.
 */
function ggt_get_primary_account_ref_user_id($account_ref, $exclude_user_id = 0) {
    if (empty($account_ref)) {
        return 0;
    }

    $user_ids = get_users(array(
        'fields'     => 'ids',
        'meta_key'   => 'accountRef',
        'meta_value' => $account_ref,
        'number'     => -1,
    ));

    if (empty($user_ids)) {
        return 0;
    }

    $fallback_user_id = 0;

    foreach ($user_ids as $candidate_user_id) {
        $candidate_user_id = intval($candidate_user_id);

        if ($candidate_user_id === intval($exclude_user_id)) {
            continue;
        }

        if (!$fallback_user_id) {
            $fallback_user_id = $candidate_user_id;
        }

        if ((int) get_user_meta($candidate_user_id, 'ggt_is_sub_user', true) !== 1) {
            return $candidate_user_id;
        }
    }

    return $fallback_user_id;
}

/**
 * Copy mapped account-level targets from the primary accountRef user to a sub-user.
 */
function ggt_apply_parent_account_ref_fields_to_user($target_user_id, $account_ref) {
    if (empty($target_user_id) || empty($account_ref) || !function_exists('ggt_update_user_targets')) {
        return false;
    }

    $parent_user_id = ggt_get_primary_account_ref_user_id($account_ref, $target_user_id);
    if (empty($parent_user_id)) {
        return false;
    }

    $mapping = get_option('ggt_user_field_mapping', array());
    if (is_object($mapping)) {
        $mapping = (array) $mapping;
    }

    if (!isset($mapping['creditLimit'])) {
        $mapping['creditLimit'] = 'creditLimit';
    }
    if (!isset($mapping['accountRef'])) {
        $mapping['accountRef'] = 'accountRef';
    }
    if (!isset($mapping['balance'])) {
        $mapping['balance'] = 'balance';
    }

    $excluded_core_targets = array('user_email', 'first_name', 'last_name', 'display_name', 'nickname');
    $mapped_targets = array();

    foreach ((array) $mapping as $source_key => $target_key) {
        if (empty($target_key) || in_array($target_key, $excluded_core_targets, true)) {
            continue;
        }

        if (stripos($target_key, 'email') !== false) {
            continue;
        }

        if (array_key_exists($target_key, $mapped_targets)) {
            continue;
        }

        $mapped_targets[$target_key] = get_user_meta($parent_user_id, $target_key, true);
    }

    if (empty($mapped_targets)) {
        return false;
    }

    ggt_update_user_targets($target_user_id, $mapped_targets);

    return true;
}

function ggt_run_account_sub_users_sync_cron() {
    if (function_exists('ggt_log')) ggt_log("Starting Account Sub-User Sync", 'CRON');

    @ini_set('memory_limit', '1024M');
    @set_time_limit(0);

    // Fetch all sub-users from API
    $response = ggt_sinappsus_connect_to_api('account-users');
    if (isset($response['error']) || !isset($response['data']) || !is_array($response['data'])) {
        if (function_exists('ggt_log')) ggt_log("Account Sub-User Sync Failed: API Error or invalid response", 'CRON');
        return;
    }

    $sub_users = $response['data'];
    $count = count($sub_users);
    if (function_exists('ggt_log')) ggt_log("Fetched {$count} account sub-users from API", 'CRON');

    $created = 0;
    $updated = 0;
    $failed  = 0;
    $skipped = 0;

    foreach ($sub_users as $sub_user) {
        if (empty($sub_user['email'])) {
            $skipped++;
            continue;
        }

        $email       = sanitize_email($sub_user['email']);
        $account_ref = isset($sub_user['account_ref']) ? sanitize_text_field($sub_user['account_ref']) : '';
        $name        = isset($sub_user['name'])        ? sanitize_text_field($sub_user['name'])        : '';
        $sub_user_id = isset($sub_user['id'])          ? intval($sub_user['id'])                       : 0;

        // Resolve WP user — prefer stored wordpress_user_id, fall back to email lookup
        $wp_user_id = null;
        if (!empty($sub_user['wordpress_user_id'])) {
            $wp_user = get_user_by('ID', intval($sub_user['wordpress_user_id']));
            if ($wp_user) {
                $wp_user_id = $wp_user->ID;
            }
        }

        if (!$wp_user_id) {
            $wp_user_id = email_exists($email);
        }

        $is_new = false;

        if (!$wp_user_id) {
            // Create new WP user
            $display_name = !empty($name) ? $name : $email;
            $wp_user_id = wp_insert_user(array(
                'user_login'   => $email,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password(24),
                'display_name' => $display_name,
                'role'         => 'customer',
            ));

            if (is_wp_error($wp_user_id)) {
                $failed++;
                if (function_exists('ggt_log')) {
                    ggt_log("Sub-User Sync FAILED to create {$email}: " . $wp_user_id->get_error_message(), 'CRON');
                }
                continue;
            }

            $is_new = true;
            $created++;
            if (function_exists('ggt_log')) {
                ggt_log("Sub-User Sync: Created WP user {$wp_user_id} for {$email} (accountRef: {$account_ref})", 'CRON');
            }
        } else {
            $updated++;
            if (function_exists('ggt_log')) {
                ggt_log("Sub-User Sync: Matched existing WP user {$wp_user_id} for {$email} (accountRef: {$account_ref})", 'CRON');
            }
        }

        // Persist accountRef and sub-user meta on the WP user
        if (!empty($account_ref)) {
            update_user_meta($wp_user_id, 'accountRef', $account_ref);
            ggt_apply_parent_account_ref_fields_to_user($wp_user_id, $account_ref);
        }
        update_user_meta($wp_user_id, 'ggt_needs_review', !empty($sub_user['needs_review']) ? 1 : 0);
        update_user_meta($wp_user_id, 'ggt_is_sub_user', 1);

        // Ensure role is 'customer' (existing users may have been registered as subscribers)
        $wp_user_obj = get_user_by('ID', $wp_user_id);
        if ($wp_user_obj && !in_array('customer', (array) $wp_user_obj->roles, true)) {
            $wp_user_obj->set_role('customer');
        }

        // Write wordpress_user_id back to the API so future lookups are faster
        if ($is_new && $sub_user_id > 0) {
            ggt_sinappsus_connect_to_api(
                "account-users/{$sub_user_id}",
                array('wordpress_user_id' => $wp_user_id),
                'PUT'
            );
        }
    }

    if (function_exists('ggt_log')) {
        ggt_log("Account Sub-User Sync Complete: Created {$created}, Updated {$updated}, Failed {$failed}, Skipped {$skipped}", 'CRON');
    }

    update_option('ggt_last_sub_user_sync', array(
        'created'   => $created,
        'updated'   => $updated,
        'failed'    => $failed,
        'total'     => $count,
        'timestamp' => time(),
    ));
}
