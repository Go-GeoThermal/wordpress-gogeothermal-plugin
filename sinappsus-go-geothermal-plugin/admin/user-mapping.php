<?php
/**
 * Flexible Accounts/User Mapping
 * 
 * Mirror of product field mapping, but for customer/accounts data used in
 * sync all users and registration forms. Provides:
 * - Available API fields list (source)
 * - Save/get mapping (api_field -> target wp meta field)
 * - Enabled fields toggle used for show/hide and processing
 * - Helper to apply mapping+enabled mask to incoming user payloads
 */

if (!defined('ABSPATH')) {
    exit;
}

// AJAX handlers
add_action('wp_ajax_ggt_users_get_available_fields', 'ggt_users_get_available_fields');
add_action('wp_ajax_ggt_users_get_api_fields', 'ggt_users_get_api_fields');
add_action('wp_ajax_ggt_users_save_field_mapping', 'ggt_users_save_field_mapping');
add_action('wp_ajax_ggt_users_get_field_mapping', 'ggt_users_get_field_mapping');
add_action('wp_ajax_ggt_users_reset_field_mapping', 'ggt_users_reset_field_mapping');

/**
 * Return supported API user/account fields the site understands
 * Keys match expected API payload keys and registration field names.
 */
function ggt_get_supported_user_api_fields_with_labels() {
    // Labels match exactly what is shown in the middleware Vue UI (TradeAccountsManagement.vue).
    // Fields commented out are NOT present as form fields in that Vue component.
    return array(
        // Identity/Core
        'accountRef'                => 'Account Ref',
        'name'                      => 'Company Name',
        'customerName'              => 'Customer Name',
        'contactName'               => 'Contact Name',
        'email'                     => 'Account Email',
        'email2'                    => 'Site Contact Email 01',
        'email3'                    => 'Site Contact Email 02',
        'email4'                    => 'Site Contact Email 03',
        'email5'                    => 'Site Contact Email 04',
        'email6'                    => 'Accounts Contact Number',
        // 'telephone'              => 'Telephone',    // Not in Vue form (Vue uses telephoneNumber)
        'telephoneNumber'           => 'Telephone',
        'telephone2'                => 'Telephone 2',
        'fax'                       => 'Fax',
        // 'www'                    => 'Website',      // Not in Vue form (Vue uses webAddress)
        'webAddress'                => 'Website',
        'tradeContact'              => 'Trade Contact',

        // Billing/Address
        'address1'                  => 'Address Line 1',
        'address2'                  => 'Address Line 2',
        'address3'                  => 'Address Line 3',
        'address4'                  => 'Address Line 4',
        'address5'                  => 'Postcode',
        'countryCode'               => 'Country Code of Origin',

        // Delivery/Shipping (del* keys match Vue form fields; delivery* keys are not in the Vue form)
        // 'deliveryName'           => 'Delivery Name',      // Not in Vue form (Vue uses delName)
        // 'deliveryAddress1'       => 'Delivery Address 1', // Not in Vue form (Vue uses delAddress1)
        // 'deliveryAddress2'       => 'Delivery Address 2', // Not in Vue form
        // 'deliveryAddress3'       => 'Delivery Address 3', // Not in Vue form
        // 'deliveryAddress4'       => 'Delivery Address 4', // Not in Vue form
        // 'deliveryAddress5'       => 'Delivery Address 5', // Not in Vue form
        'delName'                   => 'Delivery Name',
        'delAddress1'               => 'Delivery Address 1',
        'delAddress2'               => 'Delivery Address 2',
        'delAddress3'               => 'Delivery Address 3',
        'delAddress4'               => 'Delivery Address 4',
        'delAddress5'               => 'Delivery Address 5',

        // Finance/Tax/Bank
        'balance'                   => 'Balance',
        'currency'                  => 'Currency',
        'creditLimit'               => 'Credit Limit',
        'vatRegNumber'              => 'VAT Number',
        // 'vatNumber'              => 'VAT Number', // Not in Vue form (Vue uses vatRegNumber)
        'eoriNumber'                => 'EORI Number',
        'bacsRef'                   => 'BACS Reference',
        'iban'                      => 'IBAN',
        'bicSwift'                  => 'BIC/SWIFT',
        'rollNumber'                => 'Roll Number',
        'paymentType'               => 'Payment Type',
        'discountRate'              => 'Discount Rate',
        'discountType'              => 'Discount Type',

        // Defaults / Codes
        'defTaxCode'                => 'Default Tax Code',
        'defNomCode'                => 'Default Nominal Code',

        // Terms / Status / Dates
        'terms'                     => 'Trading Terms Text',
        'termsAgreed'               => 'Terms Agreed',
        'accountOnHold'             => 'Account On Hold',
        'accountStatusText'         => 'Account Status',
        'averagePayDays'            => 'Average Pay Days',
        'turnoverYtd'               => 'Turnover YTD',
        // 'lastPaymentDate'        => 'Last Payment Date',  // Not in Vue form
        // 'recordCreateDate'       => 'Record Create Date', // Not in Vue form
        // 'recordModifyDate'       => 'Record Modify Date', // Not in Vue form
        // 'lastDateSynched'        => 'Last Date Synched',  // Not in Vue form
        'inactiveAccount'           => 'Inactive Account',
        'settleDueDays'             => 'Settle Due Days',
        'paymentDueDays'            => 'Payment Due from',
        'paymentDueFrom'            => 'Payment Due From',
        'creditPosition'            => 'Credit Position',

        // Misc/Analysis
        'analysis1'                 => 'Cust. Type',
        'analysis2'                 => 'Resp. Rep',
        'analysis3'                 => 'Region',
        'analysis4'                 => 'Analysis 4',
        'analysis5'                 => 'Analysis 5',
        'analysis6'                 => 'Analysis 6',
        'deptNumber'                => 'Department Number',
        'priceListRef'              => 'Price List Reference',
        'companyRegistrationNumber' => 'Company Registration Number',
        // 'sendInvoicesElectronically' => 'Send Invoices Electronically', // No dedicated form field in Vue (value derived from sendLettersElectronically)
        'sendLettersElectronically' => 'Send invoices electronically or by mail',
        'memo'                      => 'Memo',
        'additionalRef1'            => 'Additional Ref 1',
        'additionalRef2'            => 'Additional Ref 2',
        'additionalRef3'            => 'Additional Ref 3',

        // Bank details (panel exists in Vue but is hidden via display:none)
        'bankAccountName'           => 'Bank Account Name',
        'bankSortCode'              => 'Bank Sort Code',
        'bankAccountNumber'         => 'Bank Account Number',
    );
}

/**
 * Expose API fields (keys + labels) for the admin UI
 */
function ggt_users_get_api_fields() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    $fields = ggt_get_supported_user_api_fields_with_labels();
    $out = array();
    foreach ($fields as $key => $label) {
        $out[] = array('key' => $key, 'label' => $label);
    }
    wp_send_json_success($out);
}

/**
 * Available TARGET fields on WordPress side (for mapping)
 * Typically WP user meta or WooCommerce billing/shipping keys.
 */
function ggt_users_get_available_fields() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $targets = array(
        array('value' => 'user_email', 'label' => 'User Email', 'group' => 'Core'),
        array('value' => 'first_name', 'label' => 'First Name', 'group' => 'Core'),
        array('value' => 'last_name', 'label' => 'Last Name', 'group' => 'Core'),
        array('value' => 'display_name', 'label' => 'Display Name', 'group' => 'Core'),
        array('value' => 'nickname', 'label' => 'Nickname', 'group' => 'Core'),
    );

    // Add Meta targets dynamically for every supported API field
    $api_fields = ggt_get_supported_user_api_fields_with_labels();
    foreach ($api_fields as $key => $label) {
        $targets[] = array('value' => $key, 'label' => $label . ' (meta)', 'group' => 'Meta');
    }

    // Woo billing_*
    $targets[] = array('value' => 'billing_first_name', 'label' => 'Billing First Name', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_last_name', 'label' => 'Billing Last Name', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_address_1', 'label' => 'Billing Address 1', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_address_2', 'label' => 'Billing Address 2', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_city', 'label' => 'Billing City', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_state', 'label' => 'Billing State/County', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_postcode', 'label' => 'Billing Postcode', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_country', 'label' => 'Billing Country', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_phone', 'label' => 'Billing Phone', 'group' => 'Billing');
    $targets[] = array('value' => 'billing_email', 'label' => 'Billing Email', 'group' => 'Billing');

    // Woo shipping_*
    $targets[] = array('value' => 'shipping_address_1', 'label' => 'Shipping Address 1', 'group' => 'Shipping');
    $targets[] = array('value' => 'shipping_address_2', 'label' => 'Shipping Address 2', 'group' => 'Shipping');
    $targets[] = array('value' => 'shipping_city', 'label' => 'Shipping City', 'group' => 'Shipping');
    $targets[] = array('value' => 'shipping_state', 'label' => 'Shipping State/County', 'group' => 'Shipping');
    $targets[] = array('value' => 'shipping_postcode', 'label' => 'Shipping Postcode', 'group' => 'Shipping');
    $targets[] = array('value' => 'shipping_country', 'label' => 'Shipping Country', 'group' => 'Shipping');
    $targets[] = array('value' => 'shipping_phone', 'label' => 'Shipping Phone', 'group' => 'Shipping');


    wp_send_json_success($targets);
}

/** Save user field mapping and enabled flags */
function ggt_users_save_field_mapping() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $mapping = array();
    $enabled = array();
    $custom_labels = array();

    if (isset($_POST['mapping'])) {
        $mapping = is_string($_POST['mapping']) ? json_decode(stripslashes($_POST['mapping']), true) : $_POST['mapping'];
    }
    if (isset($_POST['enabled_fields'])) {
        $enabled = is_string($_POST['enabled_fields']) ? json_decode(stripslashes($_POST['enabled_fields']), true) : $_POST['enabled_fields'];
    }
    if (isset($_POST['custom_labels'])) {
        $custom_labels = is_string($_POST['custom_labels']) ? json_decode(stripslashes($_POST['custom_labels']), true) : $_POST['custom_labels'];
    }

    if (!is_array($mapping)) $mapping = array();
    if (!is_array($enabled)) $enabled = array();
    if (!is_array($custom_labels)) $custom_labels = array();

    update_option('ggt_user_field_mapping', $mapping);
    update_option('ggt_user_field_mapping_enabled', $enabled);
    update_option('ggt_user_field_labels', $custom_labels);

    wp_send_json_success(array(
        'message' => 'User field mapping saved',
        'mapping' => $mapping,
        'enabled_fields' => $enabled,
        'custom_labels' => $custom_labels,
    ));
}

/** Get saved user mapping */
function ggt_users_get_field_mapping() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $mapping = get_option('ggt_user_field_mapping', array());
    $enabled = get_option('ggt_user_field_mapping_enabled', array());
    $custom_labels = get_option('ggt_user_field_labels', array());

    // Normalize to arrays for merging defaults
    if (is_object($mapping)) { $mapping = (array)$mapping; }
    if (is_object($enabled)) { $enabled = (array)$enabled; }
    if (is_object($custom_labels)) { $custom_labels = (array)$custom_labels; }

    // Seed vital defaults: ensure accountRef and creditLimit are always mapped
    $changed = false;
    if (!isset($mapping['accountRef'])) {
        $mapping['accountRef'] = 'accountRef';
        $changed = true;
    }
    if (!isset($mapping['creditLimit'])) {
        $mapping['creditLimit'] = 'creditLimit';
        $changed = true;
    }
    if (!isset($mapping['balance'])) {
        $mapping['balance'] = 'balance';
        $changed = true;
    }
    // Set both disabled by default for registration visibility (still imported)
    if (!isset($enabled['accountRef'])) { $enabled['accountRef'] = false; $changed = true; }
    if (!isset($enabled['creditLimit'])) { $enabled['creditLimit'] = false; $changed = true; }
    if (!isset($enabled['balance'])) { $enabled['balance'] = false; $changed = true; }

    if ($changed) {
        update_option('ggt_user_field_mapping', $mapping);
        update_option('ggt_user_field_mapping_enabled', $enabled);
    }

    if (empty($mapping)) $mapping = new stdClass();
    if (empty($enabled)) $enabled = new stdClass();
    if (empty($custom_labels)) $custom_labels = new stdClass();

    wp_send_json_success(array(
        'mapping' => $mapping,
        'enabled_fields' => $enabled,
        'custom_labels' => $custom_labels,
    ));
}

/** Reset mapping */
function ggt_users_reset_field_mapping() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    delete_option('ggt_user_field_mapping');
    delete_option('ggt_user_field_mapping_enabled');
    delete_option('ggt_user_field_labels');
    wp_send_json_success(array('message' => 'User mapping reset'));
}

/**
 * Apply user field mapping and enabled mask to an incoming payload.
 * - $data: raw array from API/UI (keys are API field names)
 * - Returns array keyed by TARGET meta keys/core keys.
 */
function ggt_apply_user_field_mapping($data) {
    // Map-only semantics: import/write only explicitly mapped fields
    $mapping = get_option('ggt_user_field_mapping', array());
    if (is_object($mapping)) { $mapping = (array)$mapping; }

    // Force vital default mappings if they are missing. 
    // This fixes the issue where creditLimit isn't updated if the mapping settings haven't been explicitly saved.
    if (!isset($mapping['creditLimit'])) {
        $mapping['creditLimit'] = 'creditLimit';
    }
    // Handle potential PascalCase from older API versions or manual entry
    if (!isset($mapping['CreditLimit'])) {
        $mapping['CreditLimit'] = 'creditLimit';
    }
    if (!isset($mapping['accountRef'])) {
        $mapping['accountRef'] = 'accountRef';
    }
    if (!isset($mapping['balance'])) {
        $mapping['balance'] = 'balance';
    }

    $out = array();
    foreach ((array)$data as $key => $value) {
        if (isset($mapping[$key]) && !empty($mapping[$key])) {
            $target = $mapping[$key];
            $out[$target] = $value;
        }
    }
    return $out;
}

/**
 * Persist mapped targets to a user, handling core fields vs meta (incl. Woo fields).
 */
function ggt_update_user_targets($user_id, $mapped_targets) {
    if (empty($user_id) || empty($mapped_targets) || !is_array($mapped_targets)) return;

    $core_keys = array('user_email','first_name','last_name','display_name','nickname');
    $core_update = array('ID' => $user_id);

    foreach ($mapped_targets as $targetKey => $val) {
        if (in_array($targetKey, $core_keys, true)) {
            $core_update[$targetKey] = sanitize_text_field($val);
        } else {
            update_user_meta($user_id, $targetKey, sanitize_text_field($val));
        }
    }

    if (count($core_update) > 1) {
        wp_update_user($core_update);
    }
}

/**
 * Return list of registration fields (API field keys => Labels)
 */
function ggt_get_registration_fields_catalog() {
    $defaults = ggt_get_supported_user_api_fields_with_labels();
    $custom = get_option('ggt_user_field_labels', array());
    if (is_object($custom)) { $custom = (array)$custom; }
    
    foreach ($custom as $key => $label) {
        if (!empty($label) && isset($defaults[$key])) {
            $defaults[$key] = $label;
        }
    }
    return $defaults;
}
