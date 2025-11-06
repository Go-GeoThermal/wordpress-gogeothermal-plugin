<?php
/**
 * Custom Product Tab for GoGeothermal Mapped Fields
 * 
 * Adds a custom tab to WooCommerce product edit page
 * to display only the mapped custom fields from our plugin
 */

defined('ABSPATH') || exit;

/**
 * Add custom tab to product data tabs
 */
function ggt_add_product_data_tab($tabs) {
    $tabs['ggt_mapped_fields'] = array(
        'label'    => __('GoGeothermal Data', 'sinappsus-ggt-wp-plugin'),
        'target'   => 'ggt_mapped_fields_data',
        'class'    => array('show_if_simple', 'show_if_variable'),
        'priority' => 75,
    );
    return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'ggt_add_product_data_tab');

/**
 * Get available field labels from flexible import
 */
function ggt_get_field_labels() {
    return array(
        // Core fields
        'title' => 'Product Title',
        'description' => 'Description',
        'short_description' => 'Short Description',
        'sku' => 'SKU',
        'regular_price' => 'Regular Price',
        'sale_price' => 'Sale Price',
        'stock_quantity' => 'Stock Quantity',
        'manage_stock' => 'Manage Stock',
        'backorders' => 'Backorders',
        'category' => 'Category',
        'weight' => 'Weight',
        'shipping_class' => 'Shipping Class',
        'image_url' => 'Image URL',
        'image_path' => 'Image Path',
        
        // Meta fields
        '_stockCode' => 'Stock Code',
        'link_to_product' => 'Link to Product',
        'content' => 'Content',
        'product_grid_content' => 'Product Grid Content',
        'gtin' => 'GTIN',
        'ean' => 'EAN',
        'own' => 'Own',
        'brand' => 'Brand',
        'output' => 'Output',
        'energy_rating' => 'Energy Rating',
        'scop' => 'SCOP',
        'phase' => 'Phase',
        'itemType' => 'Item Type',
        'nominalCode' => 'Nominal Code',
        'unitOfSale' => 'Unit of Sale',
        'deptNumber' => 'Dept Number',
        'custom1' => 'Custom 1',
        'custom2' => 'Custom 2',
        'custom3' => 'Custom 3',
        'deletedFlag' => 'Deleted Flag',
        'inactiveFlag' => 'Inactive Flag',
        'salesPrice' => 'Sales Price',
        'unitWeight' => 'Unit Weight',
        'taxCode' => 'Tax Code',
        'qtyAllocated' => 'Qty Allocated',
        'qtyInStock' => 'Qty In Stock',
        'qtyOnOrder' => 'Qty On Order',
        'stockTakeDate' => 'Stock Take Date',
        'stockCat' => 'Stock Cat',
        'averageCostPrice' => 'Average Cost Price',
        'location' => 'Location',
        'purchaseNominalCode' => 'Purchase Nominal Code',
        'lastPurchasePrice' => 'Last Purchase Price',
        'commodityCode' => 'Commodity Code',
        'barcode' => 'Barcode',
        'webDetails' => 'Web Details',
        'webDescription' => 'Web Description',
        'supplierPartNumber' => 'Supplier Part Number',
        'recordCreateDate' => 'Record Create Date',
        'recordModifyDate' => 'Record Modify Date',
        'supplierRef' => 'Supplier Ref',
        'webCategoryA' => 'Web Category A',
        'webCategoryB' => 'Web Category B',
        'webCategoryC' => 'Web Category C',
        'instrastatCommCode' => 'Instrastat Comm Code',
        'reorderLevel' => 'Reorder Level',
        'reorderQty' => 'Reorder Qty',
        'webPublish' => 'Web Publish',
        'webSpecialOffer' => 'Web Special Offer',
        'webImage' => 'Web Image',
        'assemblyLevel' => 'Assembly Level',
        'lastCostPrice' => 'Last Cost Price',
        'lastDiscPurchasePrice' => 'Last Disc Purchase Price',
        'countryCodeOfOrigin' => 'Country Code Of Origin',
        'discALevel1Rate' => 'Disc A Level 1 Rate',
        'discALevel2Rate' => 'Disc A Level 2 Rate',
        'discALevel3Rate' => 'Disc A Level 3 Rate',
        'discALevel4Rate' => 'Disc A Level 4 Rate',
        'discALevel5Rate' => 'Disc A Level 5 Rate',
        'discALevel6Rate' => 'Disc A Level 6 Rate',
        'discALevel7Rate' => 'Disc A Level 7 Rate',
        'discALevel8Rate' => 'Disc A Level 8 Rate',
        'discALevel9Rate' => 'Disc A Level 9 Rate',
        'discALevel10Rate' => 'Disc A Level 10 Rate',
        'discALevel1Qty' => 'Disc A Level 1 Qty',
        'discALevel2Qty' => 'Disc A Level 2 Qty',
        'discALevel3Qty' => 'Disc A Level 3 Qty',
        'discALevel4Qty' => 'Disc A Level 4 Qty',
        'discALevel5Qty' => 'Disc A Level 5 Qty',
        'discALevel6Qty' => 'Disc A Level 6 Qty',
        'discALevel7Qty' => 'Disc A Level 7 Qty',
        'discALevel8Qty' => 'Disc A Level 8 Qty',
        'discALevel9Qty' => 'Disc A Level 9 Qty',
        'discALevel10Qty' => 'Disc A Level 10 Qty',
        'component1Code' => 'Component 1 Code',
        'component2Code' => 'Component 2 Code',
        'component3Code' => 'Component 3 Code',
        'component4Code' => 'Component 4 Code',
        'component5Code' => 'Component 5 Code',
        'component6Code' => 'Component 6 Code',
        'component7Code' => 'Component 7 Code',
        'component8Code' => 'Component 8 Code',
        'component9Code' => 'Component 9 Code',
        'component10Code' => 'Component 10 Code',
        'component1Qty' => 'Component 1 Qty',
        'component2Qty' => 'Component 2 Qty',
        'component3Qty' => 'Component 3 Qty',
        'component4Qty' => 'Component 4 Qty',
        'component5Qty' => 'Component 5 Qty',
        'component6Qty' => 'Component 6 Qty',
        'component7Qty' => 'Component 7 Qty',
        'component8Qty' => 'Component 8 Qty',
        'component9Qty' => 'Component 9 Qty',
        'component10Qty' => 'Component 10 Qty',
        'lastDateSynched' => 'Last Date Synched',
        'isRequired' => 'Is Required',
    );
}

/**
 * Add content to custom tab
 */
function ggt_add_product_data_panel() {
    global $post;
    
    // Get the saved field mapping
    $mapping = get_option('ggt_product_field_mapping', array());
    $enabled_fields = get_option('ggt_product_field_mapping_enabled', array());
    $field_labels = ggt_get_field_labels();
    
    if (empty($mapping)) {
        echo '<div id="ggt_mapped_fields_data" class="panel woocommerce_options_panel">';
        echo '<p style="padding: 12px;">' . __('No field mapping configured. Please configure field mapping in the Flexible Import settings.', 'sinappsus-ggt-wp-plugin') . '</p>';
        echo '</div>';
        return;
    }
    
    // Filter to get only enabled custom (meta) fields
    $custom_fields = array();
    
    // List of core WooCommerce fields that should NOT appear in this tab
    $core_fields = array(
        'title', 'name', 'description', 'short_description', 'sku', 
        'regular_price', 'sale_price', 'price', 'stock_quantity', 
        'backorders', 'weight', 'length', 'width', 'height',
        'category', 'categories', 'tags', 'shipping_class',
        'image_url', 'image_path', 'featured_image', 'manage_stock'
    );
    
    foreach ($mapping as $api_field => $wc_field) {
        // Check if field is enabled (could be boolean true or string "1")
        $is_enabled = isset($enabled_fields[$api_field]) && 
                      ($enabled_fields[$api_field] === true || $enabled_fields[$api_field] === 1 || $enabled_fields[$api_field] === "1");
        
        // Only include if field is enabled and NEITHER the API field nor WC field is a core field
        // This prevents showing fields like salesPrice->regular_price, qtyInStock->stock_quantity, etc.
        if ($is_enabled && 
            !in_array($api_field, $core_fields) && 
            !in_array($wc_field, $core_fields)) {
            $custom_fields[$api_field] = $wc_field;
        }
    }
    
    ?>
    <div id="ggt_mapped_fields_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php if (empty($custom_fields)): ?>
                <p style="padding: 12px;">
                    <?php _e('No custom fields are currently mapped and enabled.', 'sinappsus-ggt-wp-plugin'); ?>
                </p>
            <?php else: ?>
                <p style="padding: 12px 12px 0; color: #646970; font-style: italic;">
                    <?php _e('These fields are automatically populated from the GoGeothermal API during product import.', 'sinappsus-ggt-wp-plugin'); ?>
                </p>
                <?php foreach ($custom_fields as $api_field => $wc_field): 
                    $value = get_post_meta($post->ID, $wc_field, true);
                    // Use the proper label from field_labels, fall back to formatted api_field
                    $label = isset($field_labels[$api_field]) ? $field_labels[$api_field] : ucwords(str_replace('_', ' ', $api_field));
                ?>
                    <p class="form-field">
                        <label for="<?php echo esc_attr($wc_field); ?>">
                            <?php echo esc_html($label); ?>
                        </label>
                        <input 
                            type="text" 
                            id="<?php echo esc_attr($wc_field); ?>" 
                            name="<?php echo esc_attr($wc_field); ?>" 
                            value="<?php echo esc_attr($value); ?>" 
                            class="short"
                            style="width: 50%;"
                        />
                        <span class="description">
                            <?php printf(__('Meta key: %s', 'sinappsus-ggt-wp-plugin'), '<code>' . esc_html($wc_field) . '</code>'); ?>
                        </span>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
add_action('woocommerce_product_data_panels', 'ggt_add_product_data_panel');

/**
 * Save custom tab fields
 */
function ggt_save_product_data_fields($post_id) {
    // Get the saved field mapping
    $mapping = get_option('ggt_product_field_mapping', array());
    $enabled_fields = get_option('ggt_product_field_mapping_enabled', array());
    
    if (empty($mapping)) {
        return;
    }
    
    // List of core WooCommerce fields that should NOT be saved here
    $core_fields = array(
        'title', 'name', 'description', 'short_description', 'sku', 
        'regular_price', 'sale_price', 'price', 'stock_quantity', 
        'backorders', 'weight', 'length', 'width', 'height',
        'category', 'categories', 'tags', 'shipping_class',
        'image_url', 'image_path', 'featured_image', 'manage_stock'
    );
    
    foreach ($mapping as $api_field => $wc_field) {
        // Check if field is enabled (could be boolean true or string "1")
        $is_enabled = isset($enabled_fields[$api_field]) && 
                      ($enabled_fields[$api_field] === true || $enabled_fields[$api_field] === 1 || $enabled_fields[$api_field] === "1");
        
        // Only save if field is enabled and NEITHER the API field nor WC field is a core field
        if ($is_enabled && 
            !in_array($api_field, $core_fields) && 
            !in_array($wc_field, $core_fields)) {
            if (isset($_POST[$wc_field])) {
                update_post_meta($post_id, $wc_field, sanitize_text_field($_POST[$wc_field]));
            }
        }
    }
}
add_action('woocommerce_process_product_meta', 'ggt_save_product_data_fields');

/**
 * Add custom styling for the tab
 */
function ggt_admin_product_tab_style() {
    global $post_type;
    if ('product' === $post_type) {
        ?>
        <style>
            #woocommerce-product-data ul.wc-tabs li.ggt_mapped_fields_options a:before {
                content: '\f540'; /* Dashicon for database/api */
            }
            #ggt_mapped_fields_data .form-field label {
                font-weight: 600;
            }
            #ggt_mapped_fields_data .description code {
                background: #f0f0f1;
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 12px;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'ggt_admin_product_tab_style');
