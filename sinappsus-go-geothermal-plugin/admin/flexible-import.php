<?php
/**
 * Flexible Product Import - Field Mapping
 * 
 * Allows users to map API product fields to WooCommerce fields/meta before importing
 */

if (!defined('ABSPATH')) {
    exit;
}

// Raise execution time and memory limits for long-running import operations.
if (!function_exists('ggt_raise_import_limits')) {
    function ggt_raise_import_limits($context = 'import') {
        // Allow overrides via filters
        $time_limit = apply_filters('ggt_import_time_limit', 0, $context); // 0 = unlimited
        $memory_limit = apply_filters('ggt_import_memory_limit', '1024M', $context);
    // Default HTTP timeout increased to 300s (5 minutes) to accommodate long imports
    $http_timeout = apply_filters('ggt_import_http_timeout', 300, $context); // seconds

        // Best-effort increases (silence if disallowed)
        if (function_exists('set_time_limit')) {
            @set_time_limit(intval($time_limit));
        }
        @ini_set('memory_limit', $memory_limit);

        return intval($http_timeout);
    }
}

// AJAX handlers
add_action('wp_ajax_ggt_get_available_fields', 'ggt_get_available_fields');
add_action('wp_ajax_ggt_save_field_mapping', 'ggt_save_field_mapping');
add_action('wp_ajax_ggt_get_field_mapping', 'ggt_get_field_mapping');
add_action('wp_ajax_ggt_reset_field_mapping', 'ggt_reset_field_mapping');
add_action('wp_ajax_ggt_preview_import', 'ggt_preview_import');
add_action('wp_ajax_ggt_analyze_import', 'ggt_analyze_import');
add_action('wp_ajax_ggt_execute_flexible_import', 'ggt_execute_flexible_import');
add_action('wp_ajax_ggt_process_related_products', 'ggt_process_related_products');

/**
 * Get all available WooCommerce fields that can be mapped to
 */
function ggt_get_available_fields() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $fields = array(
        // Core WooCommerce fields
        array('value' => 'title', 'label' => 'Product Title', 'group' => 'Core'),
        array('value' => 'description', 'label' => 'Description', 'group' => 'Core'),
        array('value' => 'short_description', 'label' => 'Short Description', 'group' => 'Core'),
        array('value' => 'sku', 'label' => 'SKU', 'group' => 'Core'),
        array('value' => 'regular_price', 'label' => 'Regular Price', 'group' => 'Core'),
        array('value' => 'sale_price', 'label' => 'Sale Price', 'group' => 'Core'),
        array('value' => 'stock_quantity', 'label' => 'Stock Quantity', 'group' => 'Core'),
        array('value' => 'manage_stock', 'label' => 'Manage Stock', 'group' => 'Core'),
        array('value' => 'backorders', 'label' => 'Backorders', 'group' => 'Core'),
        array('value' => 'category', 'label' => 'Category', 'group' => 'Core'),
        array('value' => 'weight', 'label' => 'Weight', 'group' => 'Core'),
        array('value' => 'shipping_class', 'label' => 'Shipping Class', 'group' => 'Core'),
        array('value' => 'image_url', 'label' => 'Featured Image URL', 'group' => 'Core'),
        
        // Meta fields (custom fields from API)
        array('value' => '_stockCode', 'label' => 'Stock Code', 'group' => 'Meta'),
        array('value' => 'link_to_product', 'label' => 'Link to Product', 'group' => 'Meta'),
        array('value' => 'content', 'label' => 'Content', 'group' => 'Meta'),
        array('value' => 'product_grid_content', 'label' => 'Product Grid Content', 'group' => 'Meta'),
        array('value' => 'gtin', 'label' => 'GTIN', 'group' => 'Meta'),
        array('value' => 'ean', 'label' => 'EAN', 'group' => 'Meta'),
        array('value' => 'own', 'label' => 'Own', 'group' => 'Meta'),
        array('value' => 'brand', 'label' => 'Brand', 'group' => 'Meta'),
        array('value' => 'output', 'label' => 'Output', 'group' => 'Meta'),
        array('value' => 'energy_rating', 'label' => 'Energy Rating', 'group' => 'Meta'),
        array('value' => 'scop', 'label' => 'SCOP', 'group' => 'Meta'),
        array('value' => 'phase', 'label' => 'Phase', 'group' => 'Meta'),
        array('value' => 'itemType', 'label' => 'Item Type', 'group' => 'Meta'),
        array('value' => 'nominalCode', 'label' => 'Nominal Code', 'group' => 'Meta'),
        array('value' => 'unitOfSale', 'label' => 'Unit of Sale', 'group' => 'Meta'),
        array('value' => 'deptNumber', 'label' => 'Dept Number', 'group' => 'Meta'),
        array('value' => 'custom1', 'label' => 'Custom 1', 'group' => 'Meta'),
        array('value' => 'custom2', 'label' => 'Custom 2', 'group' => 'Meta'),
        array('value' => 'custom3', 'label' => 'Custom 3', 'group' => 'Meta'),
        array('value' => 'deletedFlag', 'label' => 'Deleted Flag', 'group' => 'Meta'),
        array('value' => 'inactiveFlag', 'label' => 'Inactive Flag', 'group' => 'Meta'),
        array('value' => 'salesPrice', 'label' => 'Sales Price', 'group' => 'Meta'),
        array('value' => 'unitWeight', 'label' => 'Unit Weight', 'group' => 'Meta'),
        array('value' => 'taxCode', 'label' => 'Tax Code', 'group' => 'Meta'),
        array('value' => 'qtyAllocated', 'label' => 'Qty Allocated', 'group' => 'Meta'),
        array('value' => 'qtyInStock', 'label' => 'Qty In Stock', 'group' => 'Meta'),
        array('value' => 'qtyOnOrder', 'label' => 'Qty On Order', 'group' => 'Meta'),
        array('value' => 'stockTakeDate', 'label' => 'Stock Take Date', 'group' => 'Meta'),
        array('value' => 'stockCat', 'label' => 'Stock Cat', 'group' => 'Meta'),
        array('value' => 'averageCostPrice', 'label' => 'Average Cost Price', 'group' => 'Meta'),
        array('value' => 'location', 'label' => 'Location', 'group' => 'Meta'),
        array('value' => 'purchaseNominalCode', 'label' => 'Purchase Nominal Code', 'group' => 'Meta'),
        array('value' => 'lastPurchasePrice', 'label' => 'Last Purchase Price', 'group' => 'Meta'),
        array('value' => 'commodityCode', 'label' => 'Commodity Code', 'group' => 'Meta'),
        array('value' => 'barcode', 'label' => 'Barcode', 'group' => 'Meta'),
        array('value' => 'webDetails', 'label' => 'Web Details', 'group' => 'Meta'),
        array('value' => 'webDescription', 'label' => 'Web Description', 'group' => 'Meta'),
        array('value' => 'supplierPartNumber', 'label' => 'Supplier Part Number', 'group' => 'Meta'),
        array('value' => 'recordCreateDate', 'label' => 'Record Create Date', 'group' => 'Meta'),
        array('value' => 'recordModifyDate', 'label' => 'Record Modify Date', 'group' => 'Meta'),
        array('value' => 'supplierRef', 'label' => 'Supplier Ref', 'group' => 'Meta'),
        array('value' => 'webCategoryA', 'label' => 'Web Category A', 'group' => 'Meta'),
        array('value' => 'webCategoryB', 'label' => 'Web Category B', 'group' => 'Meta'),
        array('value' => 'webCategoryC', 'label' => 'Web Category C', 'group' => 'Meta'),
        array('value' => 'instrastatCommCode', 'label' => 'Instrastat Comm Code', 'group' => 'Meta'),
        array('value' => 'reorderLevel', 'label' => 'Reorder Level', 'group' => 'Meta'),
        array('value' => 'reorderQty', 'label' => 'Reorder Qty', 'group' => 'Meta'),
        array('value' => 'webPublish', 'label' => 'Web Publish', 'group' => 'Meta'),
        array('value' => 'webSpecialOffer', 'label' => 'Web Special Offer', 'group' => 'Meta'),
        array('value' => 'webImage', 'label' => 'Web Image', 'group' => 'Meta'),
        array('value' => 'assemblyLevel', 'label' => 'Assembly Level', 'group' => 'Meta'),
        array('value' => 'lastCostPrice', 'label' => 'Last Cost Price', 'group' => 'Meta'),
        array('value' => 'lastDiscPurchasePrice', 'label' => 'Last Disc Purchase Price', 'group' => 'Meta'),
        array('value' => 'countryCodeOfOrigin', 'label' => 'Country Code Of Origin', 'group' => 'Meta'),
    );

    // Add ACF fields if ACF is active
    if (is_acf_pro_active() && function_exists('acf_get_field_groups')) {
        $field_groups = acf_get_field_groups();
        foreach ($field_groups as $group) {
            $group_fields = acf_get_fields($group['key']);
            if ($group_fields) {
                foreach ($group_fields as $field) {
                    $fields[] = array(
                        'value' => 'acf_' . $field['name'],
                        'label' => $field['label'] . ' (ACF)',
                        'group' => 'ACF - ' . $group['title']
                    );
                }
            }
        }
    }

    wp_send_json_success($fields);
}

/**
 * Save field mapping configuration
 */
function ggt_save_field_mapping() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    // Check if data was sent as JSON strings
    $mapping = array();
    $enabled_fields = array();
    
    if (isset($_POST['mapping'])) {
        if (is_string($_POST['mapping'])) {
            // Decode JSON string
            $mapping = json_decode(stripslashes($_POST['mapping']), true);
        } else if (is_array($_POST['mapping'])) {
            $mapping = $_POST['mapping'];
        }
    }
    
    if (isset($_POST['enabled_fields'])) {
        if (is_string($_POST['enabled_fields'])) {
            // Decode JSON string
            $enabled_fields = json_decode(stripslashes($_POST['enabled_fields']), true);
        } else if (is_array($_POST['enabled_fields'])) {
            $enabled_fields = $_POST['enabled_fields'];
        }
    }
    
    // Log what we received for debugging
    error_log('GGT Save Mapping - Raw POST: ' . print_r($_POST, true));
    error_log('GGT Save Mapping - Mapping: ' . print_r($mapping, true));
    error_log('GGT Save Mapping - Enabled: ' . print_r($enabled_fields, true));
    
    // Ensure we have arrays
    if (!is_array($mapping)) {
        $mapping = array();
    }
    if (!is_array($enabled_fields)) {
        $enabled_fields = array();
    }
    
    update_option('ggt_product_field_mapping', $mapping);
    update_option('ggt_product_field_mapping_enabled', $enabled_fields);
    
    // Return what we saved
    wp_send_json_success(array(
        'message' => 'Field mapping saved successfully',
        'saved_mapping' => $mapping,
        'saved_enabled' => $enabled_fields,
        'received_post' => $_POST
    ));
}

/**
 * Get saved field mapping
 */
function ggt_get_field_mapping() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $mapping = get_option('ggt_product_field_mapping', array());
    $enabled_fields = get_option('ggt_product_field_mapping_enabled', array());
    
    // Ensure empty arrays are returned as objects to JavaScript
    if (empty($mapping)) {
        $mapping = new stdClass();
    }
    if (empty($enabled_fields)) {
        $enabled_fields = new stdClass();
    }
    
    wp_send_json_success(array(
        'mapping' => $mapping,
        'enabled_fields' => $enabled_fields
    ));
}

/**
 * Reset field mapping to defaults
 */
function ggt_reset_field_mapping() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    delete_option('ggt_product_field_mapping');
    delete_option('ggt_product_field_mapping_enabled');
    wp_send_json_success(array('message' => 'Field mapping reset successfully'));
}

/**
 * Preview products from API (first 10)
 */
function ggt_preview_import() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    // Increase limits to avoid preview timeouts on slow servers
    $http_timeout = ggt_raise_import_limits('preview');

    $token = ggt_get_decrypted_token();
    if (!$token) {
        wp_send_json_error('No authentication token found');
    }

    // Get API URL safely
    $api_url = ggt_get_api_base_url();
    if (empty($api_url)) {
        wp_send_json_error('A valid URL was not provided.');
        return;
    }

    $selected_env = get_option('ggt_sinappsus_environment', 'production');
    
    // Debug: log what we're calling
    error_log('Preview Import - Environment: ' . $selected_env);
    error_log('Preview Import - API URL: ' . $api_url);
    error_log('Preview Import - Token exists: ' . ($token ? 'yes' : 'no'));
    error_log('Preview Import - Token (first 20 chars): ' . ($token ? substr($token, 0, 20) . '...' : 'none'));
    error_log('Preview Import - Full URL: ' . $api_url . '/products?limit=10');

    $response = wp_remote_get($api_url . '/products?limit=10', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => max(30, $http_timeout)
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('API request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check for API error responses
    if (isset($data['error']) && $data['error'] === true) {
        $error_message = isset($data['message']) ? $data['message'] : 'Unknown API error';
        wp_send_json_error('API error: ' . $error_message);
    }

    if (!is_array($data) || empty($data)) {
        wp_send_json_error('No products returned from API');
    }

    // Get available fields from first product
    $available_fields = array();
    if (!empty($data[0])) {
        $available_fields = array_keys($data[0]);
    }

    wp_send_json_success(array(
        'products' => array_slice($data, 0, 5), // Only send first 5 for preview
        'total' => count($data),
        'available_fields' => $available_fields
    ));
}

/**
 * Analyze import with current mapping
 */
function ggt_analyze_import() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    // Increase limits for analysis which fetches all products
    $http_timeout = ggt_raise_import_limits('analyze');

    $token = ggt_get_decrypted_token();
    if (!$token) {
        wp_send_json_error('No authentication token found');
    }

    $mapping = get_option('ggt_product_field_mapping', array());
    $enabled_fields = get_option('ggt_product_field_mapping_enabled', array());
    
    if (empty($mapping)) {
        wp_send_json_error('No field mapping configured. Please map at least one field in Step 2.');
    }
    
    // Check if at least one field is enabled
    $has_enabled = false;
    foreach ($mapping as $api_field => $wc_field) {
        if (!isset($enabled_fields[$api_field]) || $enabled_fields[$api_field]) {
            $has_enabled = true;
            break;
        }
    }
    
    if (!$has_enabled) {
        wp_send_json_error('All fields are disabled. Please enable at least one field to import.');
    }

    global $environments;
    $selected_env = get_option('ggt_sinappsus_environment', 'production');
    $api_url = $environments[$selected_env]['api_url'];

    // Fetch all products for analysis
    $response = wp_remote_get($api_url . '/products', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => max(60, $http_timeout)
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('API request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);

    if (!is_array($products)) {
        wp_send_json_error('Invalid response from API');
    }

    // Analyze products
    $total_products = count($products);
    $existing_count = 0;
    $new_count = 0;
    $warnings = array();
    $missing_stock_codes = 0;

    // Get all existing products for comparison
    $existing_products = array();
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    );
    $existing_ids = get_posts($args);
    
    foreach ($existing_ids as $id) {
        $product = wc_get_product($id);
        if ($product) {
            $stock_code = $product->get_meta('_stockCode');
            if ($stock_code) {
                $existing_products[$stock_code] = $id;
            }
        }
    }

    foreach ($products as $product_data) {
        // Check if stockCode is mapped and exists
        $stock_code = null;
        foreach ($mapping as $api_field => $wc_field) {
            if ($wc_field === '_stockCode' && isset($product_data[$api_field])) {
                $stock_code = $product_data[$api_field];
                break;
            }
        }

        if (!$stock_code) {
            $missing_stock_codes++;
            continue;
        }

        if (isset($existing_products[$stock_code])) {
            $existing_count++;
        } else {
            // Check if inactive
            $is_inactive = false;
            foreach ($mapping as $api_field => $wc_field) {
                if ($wc_field === 'inactiveFlag' && isset($product_data[$api_field])) {
                    $is_inactive = ($product_data[$api_field] === true || $product_data[$api_field] === "1");
                    break;
                }
            }
            
            if (!$is_inactive) {
                $new_count++;
            }
        }
    }

    if ($missing_stock_codes > 0) {
        $warnings[] = "$missing_stock_codes products missing stock code mapping";
    }

    if (!isset($mapping) || empty($mapping)) {
        $warnings[] = "No field mapping configured";
    }

    wp_send_json_success(array(
        'total' => $total_products,
        'existing' => $existing_count,
        'new' => $new_count,
        'warnings' => $warnings
    ));
}

/**
 * Execute flexible import with user-defined mappings
 */
function ggt_execute_flexible_import() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!get_option('ggt_plugin_enabled', 1)) {
        wp_send_json_error('Plugin is disabled', 403);
    }

    // Lift resource limits for the full import
    $http_timeout = ggt_raise_import_limits('execute');

    // Track progress and add a shutdown handler to return a helpful error if PHP fatals
    $import_started_at = microtime(true);
    $progress = (object) [
        'processed' => 0,
        'updated' => 0,
        'created' => 0,
        'skipped' => 0
    ];
    // Will be updated as we go; used for last message if we crash
    $last_step = 'initializing';

    // Shutdown handler for fatal errors (memory/time). Emits a JSON error if possible.
    register_shutdown_function(function () use ($import_started_at, &$progress, &$last_step) {
        $err = error_get_last();
        if (!$err) return;
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) return;

        $duration = round((microtime(true) - $import_started_at) * 1000);
        $peak_mem_mb = round(memory_get_peak_usage(true) / 1048576, 2);

        // Heuristics to classify the cause
        $message = isset($err['message']) ? (string) $err['message'] : '';
        $cause = 'Server resource limit or fatal error';
        if (stripos($message, 'Allowed memory size') !== false) {
            $cause = 'Memory limit exceeded';
        } elseif (stripos($message, 'Maximum execution time') !== false) {
            $cause = 'Execution time limit exceeded';
        }

        // Try to send JSON if headers aren't sent yet
        if (!headers_sent()) {
            status_header(500);
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode([
                'success' => false,
                'message' => 'Import failed: ' . $cause . '.',
                'hint' => 'We raised time and memory limits for this request. If this persists, try smaller batches or raise the server limits.',
                'meta' => [
                    'processed' => $progress->processed,
                    'updated' => $progress->updated,
                    'created' => $progress->created,
                    'skipped' => $progress->skipped,
                    'last_step' => $last_step,
                    'duration_ms' => $duration,
                    'peak_memory_mb' => $peak_mem_mb,
                ]
            ]);
            // Ensure we stop here
            die();
        }
    });

    $token = ggt_get_decrypted_token();
    if (!$token) {
        wp_send_json_error('No authentication token found');
    }

    $mapping = get_option('ggt_product_field_mapping', array());
    
    if (empty($mapping)) {
        wp_send_json_error('No field mapping configured');
    }

    global $environments;
    $selected_env = get_option('ggt_sinappsus_environment', 'production');
    $api_url = $environments[$selected_env]['api_url'];

    // Fetch all products
    $last_step = 'fetching products from API';
    $response = wp_remote_get($api_url . '/products', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => max(60, $http_timeout)
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('API request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);

    if (!is_array($products)) {
        wp_send_json_error('Invalid response from API');
    }

    // Get all existing products
    $existing_products = array();
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    );
    $existing_ids = get_posts($args);
    
    foreach ($existing_ids as $id) {
        $product = wc_get_product($id);
        if ($product) {
            $stock_code = $product->get_meta('_stockCode');
            $sku = $product->get_sku();
            if ($stock_code) {
                $existing_products[$stock_code] = $id;
            } elseif ($sku) {
                $existing_products[$sku] = $id;
            }
        }
    }

    $updated = 0;
    $created = 0;
    $skipped = 0;
    $errors = array();

    foreach ($products as $product_data) {
        try {
            $last_step = 'processing product';
            // Apply field mapping
            $mapped_data = ggt_apply_field_mapping($product_data, $mapping);
            
            // Get stock code
            $stock_code = isset($mapped_data['_stockCode']) ? $mapped_data['_stockCode'] : null;
            
            if (!$stock_code) {
                $skipped++;
                $progress->skipped = $skipped;
                $progress->processed++;
                continue;
            }

            // Check if product exists
            $product_id = isset($existing_products[$stock_code]) ? $existing_products[$stock_code] : null;
            
            if ($product_id) {
                // Update existing (pass original product_data for ACF relationships)
                ggt_update_product_with_mapping_and_acf($product_id, $product_data, $mapped_data);
                $updated++;
                $progress->updated = $updated;
            } else {
                // Create new (if not inactive)
                if (empty($mapped_data['inactiveFlag']) || ($mapped_data['inactiveFlag'] !== true && $mapped_data['inactiveFlag'] !== "1")) {
                    // Pass original product_data for ACF relationships
                    $new_id = ggt_create_product_with_mapping_and_acf($product_data, $mapped_data);
                    if ($new_id) {
                        $created++;
                        $existing_products[$stock_code] = $new_id;
                        $progress->created = $created;
                    }
                } else {
                    $skipped++;
                    $progress->skipped = $skipped;
                }
            }
            $progress->processed++;
        } catch (Exception $e) {
            $errors[] = 'Error processing product: ' . $e->getMessage();
            $progress->processed++;
        }
    }

    // Persist last import summary for dashboard
    update_option('ggt_last_product_import', array(
        'updated' => $updated,
        'created' => $created,
        'skipped' => $skipped,
        'timestamp' => time(),
    ));

    $duration_ms = round((microtime(true) - $import_started_at) * 1000);
    $peak_memory_mb = round(memory_get_peak_usage(true) / 1048576, 2);

    wp_send_json_success(array(
        'updated' => $updated,
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'meta' => array(
            'processed' => $progress->processed,
            'duration_ms' => $duration_ms,
            'peak_memory_mb' => $peak_memory_mb
        )
    ));
}

/**
 * Apply field mapping to product data
 */
function ggt_apply_field_mapping($product_data, $mapping) {
    $mapped = array();
    $enabled_fields = get_option('ggt_product_field_mapping_enabled', array());
    
    foreach ($mapping as $api_field => $wc_field) {
        // Only map if field is enabled
        if (isset($enabled_fields[$api_field]) && $enabled_fields[$api_field] && isset($product_data[$api_field])) {
            $mapped[$wc_field] = $product_data[$api_field];
        }
    }
    
    return $mapped;
}

/**
 * Create product with mapped data
 */
function ggt_create_product_with_mapping($mapped_data) {
    $product = new WC_Product();
    
    // Set core WooCommerce fields
    if (isset($mapped_data['title'])) {
        $product->set_name($mapped_data['title']);
    } elseif (isset($mapped_data['description'])) {
        $product->set_name($mapped_data['description']);
    }
    
    if (isset($mapped_data['description'])) {
        $product->set_description($mapped_data['description']);
    }
    
    if (isset($mapped_data['short_description'])) {
        $product->set_short_description($mapped_data['short_description']);
    }
    
    if (isset($mapped_data['sku'])) {
        $product->set_sku($mapped_data['sku']);
    }
    
    if (isset($mapped_data['regular_price'])) {
        $product->set_regular_price($mapped_data['regular_price']);
    }
    
    if (isset($mapped_data['sale_price'])) {
        $product->set_sale_price($mapped_data['sale_price']);
    }
    
    if (isset($mapped_data['stock_quantity'])) {
        $product->set_stock_quantity($mapped_data['stock_quantity']);
        $product->set_manage_stock(true);
    } elseif (isset($mapped_data['qtyInStock'])) {
        $product->set_stock_quantity($mapped_data['qtyInStock']);
        $product->set_manage_stock(true);
    }
    
    if (isset($mapped_data['backorders'])) {
        $product->set_backorders($mapped_data['backorders']);
    } else {
        $product->set_backorders('yes');
    }
    
    if (isset($mapped_data['weight'])) {
        $product->set_weight($mapped_data['weight']);
    }
    
    // Handle category
    if (isset($mapped_data['category'])) {
        $category_id = find_or_create_product_category($mapped_data['category']);
        if ($category_id) {
            $product->set_category_ids(array($category_id));
        }
    }
    
    // Handle shipping class
    if (isset($mapped_data['shipping_class'])) {
        $shipping_class_id = ggt_find_or_create_shipping_class($mapped_data['shipping_class']);
        if ($shipping_class_id) {
            $product->set_shipping_class_id($shipping_class_id);
        }
    }
    
    // Save product
    $product_id = $product->save();
    
    if ($product_id) {
        // Set all meta data
        foreach ($mapped_data as $key => $value) {
            // Skip core fields already handled (these are WC field names after mapping)
            if (!in_array($key, ['title', 'description', 'short_description', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'backorders', 'weight', 'category', 'shipping_class', 'image_url', 'manage_stock'])) {
                // Handle ACF fields (prefixed with acf_)
                if (strpos($key, 'acf_') === 0 && function_exists('update_field')) {
                    $field_name = substr($key, 4);
                    update_field($field_name, $value, $product_id);
                } else {
                    update_post_meta($product_id, $key, $value);
                }
            }
        }
        
        // Handle featured image (image_url should be mapped from API's image_path field which contains relative path)
        if (isset($mapped_data['image_url']) && !empty($mapped_data['image_url'])) {
            ggt_log("Product {$product_id}: Found image_url in mapped data: {$mapped_data['image_url']}", 'IMPORT');
            set_product_featured_image_from_url($product_id, $mapped_data['image_url']);
        } else {
            ggt_log("Product {$product_id}: No image_url in mapped data", 'IMPORT');
        }
    }
    
    return $product_id;
}

/**
 * Create product with mapped data and handle ACF relationships
 * This wrapper function keeps the original product data for ACF processing
 */
function ggt_create_product_with_mapping_and_acf($product_data, $mapped_data) {
    $stock_code = isset($mapped_data['_stockCode']) ? $mapped_data['_stockCode'] : 'unknown';
    ggt_log("Creating new product with stock code: {$stock_code}", 'IMPORT');
    
    $product_id = ggt_create_product_with_mapping($mapped_data);
    
    if ($product_id) {
        ggt_log("Created product ID {$product_id} (stock code: {$stock_code})", 'IMPORT');
        
        // Log if we have RelatedProducts in original data
        if (isset($product_data['RelatedProducts'])) {
            ggt_log("Product {$product_id}: Has RelatedProducts in API data: {$product_data['RelatedProducts']}", 'IMPORT');
        }
        
        // Handle ACF relationships using original API data
        relate_products_via_acf($product_id, $product_data);
    } else {
        ggt_log("FAILED to create product with stock code: {$stock_code}", 'IMPORT');
    }
    
    return $product_id;
}

/**
 * Update product with mapped data
 */
function ggt_update_product_with_mapping($product_id, $mapped_data) {
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return false;
    }
    
    // Update core WooCommerce fields
    if (isset($mapped_data['title'])) {
        $product->set_name($mapped_data['title']);
    } elseif (isset($mapped_data['description'])) {
        $product->set_name($mapped_data['description']);
    }
    
    if (isset($mapped_data['description'])) {
        $product->set_description($mapped_data['description']);
    }
    
    if (isset($mapped_data['short_description'])) {
        $product->set_short_description($mapped_data['short_description']);
    }
    
    if (isset($mapped_data['regular_price'])) {
        $product->set_regular_price($mapped_data['regular_price']);
    } elseif (isset($mapped_data['salesPrice'])) {
        $product->set_regular_price($mapped_data['salesPrice']);
    }
    
    if (isset($mapped_data['sale_price'])) {
        $product->set_sale_price($mapped_data['sale_price']);
    }
    
    if (isset($mapped_data['stock_quantity'])) {
        $product->set_stock_quantity($mapped_data['stock_quantity']);
        $product->set_manage_stock(true);
    } elseif (isset($mapped_data['qtyInStock'])) {
        $product->set_stock_quantity($mapped_data['qtyInStock']);
        $product->set_manage_stock(true);
    }
    
    if (isset($mapped_data['backorders'])) {
        $product->set_backorders($mapped_data['backorders']);
    } else {
        $product->set_backorders('yes');
    }
    
    if (isset($mapped_data['weight'])) {
        $product->set_weight($mapped_data['weight']);
    }
    
    // Handle category
    if (isset($mapped_data['category'])) {
        $category_id = find_or_create_product_category($mapped_data['category']);
        if ($category_id) {
            $product->set_category_ids(array($category_id));
        }
    }
    
    // Handle shipping class
    if (isset($mapped_data['shipping_class'])) {
        $shipping_class_id = ggt_find_or_create_shipping_class($mapped_data['shipping_class']);
        if ($shipping_class_id) {
            $product->set_shipping_class_id($shipping_class_id);
        }
    }
    
    // Save product
    $product->save();
    
    // Update all meta data
    foreach ($mapped_data as $key => $value) {
        // Skip core fields already handled (these are WC field names after mapping)
        if (!in_array($key, ['title', 'description', 'short_description', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'backorders', 'weight', 'category', 'shipping_class', 'image_url', 'manage_stock'])) {
            // Handle ACF fields (prefixed with acf_)
            if (strpos($key, 'acf_') === 0 && function_exists('update_field')) {
                $field_name = substr($key, 4);
                update_field($field_name, $value, $product_id);
            } else {
                update_post_meta($product_id, $key, $value);
            }
        }
    }
    // Handle featured image (image_url should be mapped from API's image_path field which contains relative path)
    if (isset($mapped_data['image_url']) && !empty($mapped_data['image_url'])) {
        ggt_log("Product {$product_id}: Found image_url in mapped data: {$mapped_data['image_url']}", 'IMPORT');
        set_product_featured_image_from_url($product_id, $mapped_data['image_url']);
    } else {
        ggt_log("Product {$product_id}: No image_url in mapped data", 'IMPORT');
    }
    
    return true;
}

/**
 * Update product with mapped data and handle ACF relationships
 * This wrapper function keeps the original product data for ACF processing
 */
function ggt_update_product_with_mapping_and_acf($product_id, $product_data, $mapped_data) {
    $stock_code = isset($mapped_data['_stockCode']) ? $mapped_data['_stockCode'] : 'unknown';
    ggt_log("Updating product ID {$product_id} (stock code: {$stock_code})", 'IMPORT');
    
    // Log if we have RelatedProducts in original data
    if (isset($product_data['RelatedProducts'])) {
        ggt_log("Product {$product_id}: Has RelatedProducts in API data: {$product_data['RelatedProducts']}", 'IMPORT');
    }
    
    $result = ggt_update_product_with_mapping($product_id, $mapped_data);
    
    if ($result) {
        ggt_log("Updated product ID {$product_id} successfully", 'IMPORT');
        // Handle ACF relationships using original API data
        relate_products_via_acf($product_id, $product_data);
    } else {
        ggt_log("FAILED to update product ID {$product_id}", 'IMPORT');
    }
    
    return $result;
}

/**
 * Process RelatedProducts field after import is complete
 * This function runs after all products are imported to resolve stock codes to product IDs
 */
function ggt_process_related_products() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    // Get the ACF field name for related products
    $related_field = get_option('ggt_import_acf_related_field');
    
    if (empty($related_field)) {
        wp_send_json_error('Related products ACF field not configured');
    }

    // Get all products
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    );
    $product_ids = get_posts($args);
    
    $processed = 0;
    $updated = 0;
    $errors = array();

    foreach ($product_ids as $product_id) {
        $processed++;
        
        // Check if this product has a RelatedProduct meta field (from import)
        $related_product_raw = get_post_meta($product_id, 'RelatedProduct', true);
        
        if (empty($related_product_raw)) {
            continue;
        }

        // Parse the pipe-delimited stock codes
        $stock_codes = array();
        $raw = $related_product_raw;
        
        // Split by pipe or comma
        $parts = preg_split('/[,|\\|]+/', $raw);
        
        foreach ($parts as $part) {
            $code = trim($part);
            if (!empty($code)) {
                $stock_codes[] = $code;
            }
        }

        if (empty($stock_codes)) {
            continue;
        }

        // Resolve stock codes to product IDs
        $related_product_ids = array();
        foreach ($stock_codes as $stock_code) {
            $related_id = ggt_stockcode_to_product_id($stock_code);
            if ($related_id) {
                $related_product_ids[] = $related_id;
            } else {
                $errors[] = "Product ID $product_id: Could not find product with stock code '$stock_code'";
            }
        }

        if (!empty($related_product_ids)) {
            // Get existing related products from ACF field
            $existing_related = array();
            if (function_exists('get_field')) {
                $existing = get_field($related_field, $product_id);
                if (is_array($existing)) {
                    foreach ($existing as $e) {
                        if (is_object($e) && isset($e->ID)) {
                            $existing_related[] = intval($e->ID);
                        } elseif (is_numeric($e)) {
                            $existing_related[] = intval($e);
                        }
                    }
                }
            } else {
                $existing = get_post_meta($product_id, $related_field, true);
                if (!empty($existing)) {
                    if (is_string($existing)) {
                        $maybe = maybe_unserialize($existing);
                        if (is_array($maybe)) $existing = $maybe;
                    }
                    if (is_array($existing)) {
                        foreach ($existing as $e) {
                            if (is_numeric($e)) $existing_related[] = intval($e);
                        }
                    }
                }
            }

            // Merge and dedupe
            $merged = array_values(array_unique(array_merge($existing_related, $related_product_ids)));

            // Update the ACF field
            if (function_exists('update_field')) {
                update_field($related_field, $merged, $product_id);
                $updated++;
            } else {
                update_post_meta($product_id, $related_field, maybe_serialize($merged));
                $updated++;
            }
        }
    }

    wp_send_json_success(array(
        'processed' => $processed,
        'updated' => $updated,
        'errors' => $errors
    ));
}

/**
 * Helper function to resolve stock code to product ID
 */
function ggt_stockcode_to_product_id($stock_code) {
    if (empty($stock_code)) {
        return 0;
    }

    $args = array(
        'post_type' => 'product',
        'meta_key' => '_stockCode',
        'meta_value' => $stock_code,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'post_status' => 'any'
    );

    $products = get_posts($args);
    
    return !empty($products) ? intval($products[0]) : 0;
}

/**
 * Find or create WooCommerce shipping class by name
 */
function ggt_find_or_create_shipping_class($class_name) {
    if (empty($class_name)) {
        return 0;
    }
    
    // Try to find existing shipping class
    $term = get_term_by('name', $class_name, 'product_shipping_class');
    
    if ($term) {
        return $term->term_id;
    }
    
    // Create new shipping class
    $result = wp_insert_term($class_name, 'product_shipping_class');
    
    if (is_wp_error($result)) {
        return 0;
    }
    
    return $result['term_id'];
}
