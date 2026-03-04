<?php
/**
 * Add Account Ref column to Users table
 * Makes Account Ref visible and searchable in the admin users list
 */

defined('ABSPATH') || exit;

/**
 * Add Account Ref column to users list
 */
function ggt_add_user_columns($columns) {
    // Insert Account Ref column after the username for better visibility
    $new_columns = array();
    
    // If username column exists, insert after it
    if (isset($columns['username'])) {
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'username') {
                $new_columns['accountRef'] = __('Account Ref', 'sinappsus-ggt-wp-plugin');
            }
        }
    } else {
        // Fallback: prepend or append
        $new_columns['accountRef'] = __('Account Ref', 'sinappsus-ggt-wp-plugin');
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
        }
    }
    
    return $new_columns;
}
add_filter('manage_users_columns', 'ggt_add_user_columns');

/**
 * Populate Account Ref column
 */
function ggt_populate_user_column($value, $column_name, $user_id) {
    if ($column_name === 'accountRef') {
        // Try getting from standard mapping first
        $account_ref = get_user_meta($user_id, 'accountRef', true);
        
        if ($account_ref) {
            return '<strong>' . esc_html($account_ref) . '</strong>';
        } else {
            return '<span style="color: #999;">—</span>';
        }
    }
    return $value;
}
add_filter('manage_users_custom_column', 'ggt_populate_user_column', 10, 3);

/**
 * Make Account Ref column sortable
 */
function ggt_sortable_user_columns($columns) {
    $columns['accountRef'] = 'accountRef';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'ggt_sortable_user_columns');

/**
 * Handle Account Ref column sorting
 * (Searching is handled via pre_user_query below)
 */
function ggt_user_params_orderby($query) {
    if (!is_admin()) return;
    
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users') return;

    if ($query->get('orderby') == 'accountRef') {
        $query->set('meta_key', 'accountRef');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_users', 'ggt_user_params_orderby');

/**
 * Enable Searching by Account Ref
 */
function ggt_user_search_by_account_ref($query) {
    global $wpdb;

    if (!is_admin()) return;

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users') return;

    // Check if there is a search term
    if (!isset($query->query_vars['search']) || empty($query->query_vars['search'])) {
        return;
    }

    $term = trim($query->query_vars['search']);
    // Remove leading/trailing * usually added by WP
    $term = trim($term, '*');
    
    if (empty($term)) return;
    
    // We must modify query_from and query_where directly on the object because
    // WP doesn't provide a clean "OR META" search via standard args.

    // 1. Join the usermeta table for accountRef
    // We use a unique alias 'ggt_ur_am' to avoid collisions
    $query->query_from .= " LEFT JOIN {$wpdb->usermeta} AS ggt_ur_am ON ({$wpdb->users}.ID = ggt_ur_am.user_id AND ggt_ur_am.meta_key = 'accountRef')";

    // 2. Add OR condition to the WHERE clause
    // The standard search creates a clause like: AND (user_login LIKE ... OR user_email ...)
    // We want to append our condition to that group.
    
    $search_string = '%' . $wpdb->esc_like($term) . '%';
    
    // HACK: We look for the closing parenthesis of the main search group and inject our OR
    // WP's search clause usually ends with `)` so we replace the last `)` with ` OR ...condition... )`
    
    // Check if query_where has the search clause.
    // Usually it's in the format: AND ( ... )
    
    if (preg_match('/\)\s*$/', $query->query_where)) {
         $query->query_where = preg_replace(
            '/\)\s*$/',
            " OR (ggt_ur_am.meta_value LIKE '{$search_string}') )",
            $query->query_where,
            1 // limit to 1 replacement
        );
    }
}
add_action('pre_user_query', 'ggt_user_search_by_account_ref');
