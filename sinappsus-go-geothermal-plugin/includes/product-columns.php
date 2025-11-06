<?php
/**
 * Add Stock Code column to Products table
 * Makes Stock Code visible and searchable in the admin products list
 */

defined('ABSPATH') || exit;

/**
 * Add Stock Code column to products list
 */
function ggt_add_product_columns($columns) {
    // Insert stock code column after the product name
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'name') {
            $new_columns['stock_code'] = __('Stock Code', 'sinappsus-ggt-wp-plugin');
        }
    }
    return $new_columns;
}
add_filter('manage_edit-product_columns', 'ggt_add_product_columns');

/**
 * Populate Stock Code column
 */
function ggt_populate_product_column($column, $post_id) {
    if ($column === 'stock_code') {
        $stock_code = get_post_meta($post_id, '_stockCode', true);
        if ($stock_code) {
            echo '<strong>' . esc_html($stock_code) . '</strong>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
add_action('manage_product_posts_custom_column', 'ggt_populate_product_column', 10, 2);

/**
 * Make Stock Code column sortable
 */
function ggt_sortable_product_columns($columns) {
    $columns['stock_code'] = 'stock_code';
    return $columns;
}
add_filter('manage_edit-product_sortable_columns', 'ggt_sortable_product_columns');

/**
 * Handle Stock Code column sorting
 */
function ggt_product_column_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ('stock_code' === $orderby) {
        $query->set('meta_key', '_stockCode');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'ggt_product_column_orderby');

/**
 * Add Stock Code to product search
 */
function ggt_search_by_stock_code($search, $query) {
    global $wpdb;

    // Only modify search for product post type in admin
    if (!is_admin() || !$query->is_search() || empty($query->query_vars['s']) || $query->query_vars['post_type'] !== 'product') {
        return $search;
    }

    $search_term = $wpdb->esc_like($query->query_vars['s']);
    
    // Add stock code meta to search
    $search .= " OR (";
    $search .= "{$wpdb->posts}.ID IN (
        SELECT DISTINCT post_id FROM {$wpdb->postmeta}
        WHERE meta_key = '_stockCode'
        AND meta_value LIKE '%{$search_term}%'
    )";
    $search .= ")";

    return $search;
}
add_filter('posts_search', 'ggt_search_by_stock_code', 10, 2);

/**
 * Add Stock Code filter to products list
 */
function ggt_add_stock_code_filter() {
    global $typenow;

    if ($typenow === 'product') {
        $stock_code = isset($_GET['stock_code_search']) ? sanitize_text_field($_GET['stock_code_search']) : '';
        ?>
        <input 
            type="text" 
            name="stock_code_search" 
            placeholder="<?php esc_attr_e('Search by Stock Code', 'sinappsus-ggt-wp-plugin'); ?>" 
            value="<?php echo esc_attr($stock_code); ?>" 
            style="width: 200px;"
        />
        <?php
    }
}
add_action('restrict_manage_posts', 'ggt_add_stock_code_filter');

/**
 * Filter products by Stock Code search
 */
function ggt_filter_by_stock_code($query) {
    global $pagenow, $typenow;

    if ($pagenow === 'edit.php' && $typenow === 'product' && isset($_GET['stock_code_search']) && !empty($_GET['stock_code_search'])) {
        $stock_code = sanitize_text_field($_GET['stock_code_search']);
        
        $meta_query = $query->get('meta_query') ?: array();
        $meta_query[] = array(
            'key' => '_stockCode',
            'value' => $stock_code,
            'compare' => 'LIKE'
        );
        
        $query->set('meta_query', $meta_query);
    }
}
add_filter('pre_get_posts', 'ggt_filter_by_stock_code');

/**
 * Add styling for stock code column
 */
function ggt_product_column_styles() {
    global $pagenow, $typenow;
    
    if ($pagenow === 'edit.php' && $typenow === 'product') {
        ?>
        <style>
            .column-stock_code {
                width: 120px;
            }
            .column-stock_code strong {
                color: #2271b1;
                font-family: monospace;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'ggt_product_column_styles');
