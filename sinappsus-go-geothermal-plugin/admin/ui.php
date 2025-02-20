<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the const.php file to get the environments array
require_once __DIR__ . '/../const.php';
ini_set('max_execution_time', 0);
 ini_set('memory_limit', '3048M');

class Sinappsus_GGT_Admin_UI
{
    private $api_url;

    public function __construct()
    {
        // global $environments;
        $this->api_url = 'https://api.gogeothermal.co.uk/api'; //$environments['production']['api_url'];

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'sinappsus_ggt_wp_plugin']);
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'Go Geothermal Settings',
            'Go Geothermal',
            'manage_options',
            'sinappsus-ggt-settings',
            [$this, 'display_settings_page'],
            'dashicons-admin-generic',
            6
        );
    }

    public function display_settings_page()
    {
?>
        <div class="wrap">
            <h1>Go Geothermal Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ggt_sinappsus_settings_group');
                do_settings_sections('ggt_sinappsus_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Email</th>
                        <td><input type="text" name="ggt_sinappsus_email" value="<?php echo esc_attr(get_option('ggt_sinappsus_email')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Password</th>
                        <td><input type="password" name="ggt_sinappsus_password" value="<?php echo esc_attr(get_option('ggt_sinappsus_password')); ?>" /></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" id="authenticate-button" class="button button-primary">Authenticate</button>
                    <button type="button" id="validate-button" class="button">Validate</button>
                    <button type="button" id="renew-button" class="button">Renew Token</button>
                    <?php submit_button(); ?>
                </p>
                <p id="timer"></p>
                <p id="message"></p>
            </form>
            <div id="additional-actions" style="display: none;">
                <button type="button" id="clear-products-button" class="button button-secondary">Clear All Products</button>
                <button type="button" id="sync-products-button" class="button button-secondary">Sync All Products</button>
            </div>
            <div id="user-actions" >
                <button type="button" id="sync-users-button" class="button button-secondary">Sync All Users</button>
                <button type="button" id="delete-users-button" class="button button-secondary">Delete All Users</button>
            </div>

        </div>
        <script type="text/javascript">
           document.addEventListener('DOMContentLoaded', function() {
    function getToken() {
        return new Promise((resolve, reject) => {
            jQuery.post(ajaxurl, {
                action: 'get_token'
            }, function(response) {
                if (response.success) {
                    resolve(response.data.token);
                } else {
                    reject('Token not found');
                }
            });
        });
    }

    document.getElementById('authenticate-button').addEventListener('click', function() {
        var email = document.querySelector('input[name="ggt_sinappsus_email"]').value;
        var password = document.querySelector('input[name="ggt_sinappsus_password"]').value;

        fetch('<?php echo $this->api_url; ?>/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email: email, password: password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.access_token) {
                // Store the token in the options database
                jQuery.post(ajaxurl, {
                    action: 'store_token',
                    token: data.access_token
                }, function(response) {
                    if (response.success) {
                        document.getElementById('message').innerText = 'Authentication successful!';
                        document.getElementById('additional-actions').style.display = 'block';
                        document.getElementById('user-actions').style.display = 'block';
                    } else {
                        document.getElementById('message').innerText = 'Failed to store token!';
                    }
                });
            } else {
                document.getElementById('message').innerText = 'Authentication failed!';
            }
        })
        .catch(error => {
            document.getElementById('message').innerText = 'An error occurred: ' + error.message;
        });
    });

    document.getElementById('validate-button').addEventListener('click', function() {
        getToken().then(token => {
            fetch('<?php echo $this->api_url; ?>/validate-token', {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.expires_in) {
                    document.getElementById('message').innerText = 'Token is valid! Expires in: ' + data.expires_in + ' seconds';
                } else {
                    document.getElementById('message').innerText = 'Token is invalid!';
                }
            })
            .catch(error => {
                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
            });
        }).catch(error => {
            document.getElementById('message').innerText = 'An error occurred: ' + error;
        });
    });

    document.getElementById('renew-button').addEventListener('click', function() {
        getToken().then(token => {
            fetch('<?php echo $this->api_url; ?>/renew-token', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.token) {
                    // Store the new token in the options database
                    jQuery.post(ajaxurl, {
                        action: 'store_token',
                        token: data.token
                    }, function(response) {
                        if (response.success) {
                            document.getElementById('message').innerText = 'Token renewed successfully!';
                        } else {
                            document.getElementById('message').innerText = 'Failed to store renewed token!';
                        }
                    });
                } else {
                    document.getElementById('message').innerText = 'Failed to renew token!';
                }
            })
            .catch(error => {
                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
            });
        }).catch(error => {
            document.getElementById('message').innerText = 'An error occurred: ' + error;
        });
    });

    document.getElementById('sync-users-button').addEventListener('click', function() {
        getToken().then(token => {
            fetch('<?php echo $this->api_url; ?>/customers', {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data && Array.isArray(data)) {
                    data.forEach(user_data => {
                        if (user_data.email) {
                            jQuery.post(ajaxurl, {
                                action: 'sync_user',
                                user_data: user_data
                            }, function(response) {
                                if (!response.success) {
                                    document.getElementById('message').innerText = 'Failed to sync user: ' + user_data.email;
                                }
                            }).fail(function(error) {
                                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
                            });
                        }
                    });
                    document.getElementById('message').innerText = 'Users synchronized successfully!';
                } else {
                    document.getElementById('message').innerText = 'Invalid response from API.';
                }
            })
            .catch(error => {
                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
            });
        }).catch(error => {
            document.getElementById('message').innerText = 'An error occurred: ' + error;
        });
    });

    document.getElementById('delete-users-button').addEventListener('click', function() {
        if (confirm('Are you sure you want to delete all users?')) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=delete_all_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('message').innerText = 'All users deleted successfully!';
                } else {
                    document.getElementById('message').innerText = 'Failed to delete users!';
                }
            })
            .catch(error => {
                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
            });
        }
    });
});
        </script>
<?php
    }

    public function sinappsus_ggt_wp_plugin($links)
    {
        $settings_url = add_query_arg(
            array(
                'page' => 'sinappsus-ggt-settings',
                'tab' => 'integration',
                'section' => 'sinappsus_ggt_wp_plugin',
            ),
            admin_url('admin.php')
        );

        $plugin_links = array(
            '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'sinappsus-ggt-wp-plugin') . '</a>',
            '<a href="#">' . __('Support', 'sinappsus-ggt-wp-plugin') . '</a>',
            '<a href="#">' . __('Docs', 'sinappsus-ggt-wp-plugin') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }
}

add_action('admin_init', 'ggt_sinappsus_register_settings');

function ggt_sinappsus_register_settings()
{
    register_setting('ggt_sinappsus_settings_group', 'ggt_sinappsus_email');
    register_setting('ggt_sinappsus_settings_group', 'ggt_sinappsus_password');
}

add_action('wp_ajax_clear_all_products', 'clear_all_products');
add_action('wp_ajax_get_all_products', 'get_all_products');
add_action('wp_ajax_update_product', 'update_product');
add_action('wp_ajax_create_product', 'create_product');
add_action('wp_ajax_sync_user', 'sync_user');
add_action('wp_ajax_delete_all_users', 'delete_all_users');

function clear_all_products()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'any'
    );

    $products = get_posts($args);

    foreach ($products as $product) {
        wp_delete_post($product->ID, true);
    }

    wp_send_json_success();
}

function get_all_products()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_key' => '_stockCode'
    );

    $products = get_posts($args);
    $result = array();

    foreach ($products as $product) {
        $product_obj = wc_get_product($product->ID);
        $result[] = array(
            'id' => $product->ID,
            'stockCode' => $product_obj->get_meta('_stockCode')
        );
    }

    wp_send_json_success($result);
}

function update_product()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $product_id = intval($_POST['product_id']);
    $product_data = $_POST['product_data'];

    $product = wc_get_product($product_id);
    if ($product) {
        $product->set_name($product_data['title']);
        $product->set_regular_price($product_data['salesPrice']);
        $product->set_description($product_data['description']);
        $product->update_meta_data('_stockCode', $product_data['stockCode']);
        $product->set_stock($product_data['qtyInStock']);
        $product->set_manage_stock(true);
        $product->set_backorders('yes');

        // Update other meta data
        foreach ($product_data as $key => $value) {
            if ($key !== 'title' && $key !== 'salesPrice' && $key !== 'description' && $key !== 'stockCode') {
                $product->update_meta_data($key, $value);
            }
        }

        $product->save();
        wp_send_json_success();
    } else {
        wp_send_json_error('Product not found', 404);
    }
}

function create_product()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $product_data = $_POST['product_data'];

    $product = new WC_Product();
    $product->set_name($product_data['title']);
    $product->set_regular_price($product_data['salesPrice']);
    $product->set_description($product_data['description']);
    $product->set_stock($product_data['qtyInStock']);
    $product->set_manage_stock(true);
    $product->set_backorders('yes');

    $product->update_meta_data('_stockCode', $product_data['stockCode']);

    // Update other meta data
    foreach ($product_data as $key => $value) {
        if ($key !== 'title' && $key !== 'salesPrice' && $key !== 'description' && $key !== 'stockCode') {
            $product->update_meta_data($key, $value);
        }
    }

    $product->save();
    wp_send_json_success();
}

function sync_user() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $user_data = $_POST['user_data'];

    if (!isset($user_data['email'])) {
        wp_send_json_error('User email is required.');
    }

    $user_id = username_exists($user_data['email']);
    if (!$user_id) {
        $user_id = wp_create_user($user_data['email'], wp_generate_password(), $user_data['email']);
    }

    if (is_wp_error($user_id)) {
        wp_send_json_error('Failed to create user: ' . $user_data['email']);
    }

    // Update user meta data
    foreach ($user_data as $key => $value) {
        update_user_meta($user_id, $key, $value);
    }

     // Update WooCommerce billing and shipping address fields
     update_user_meta($user_id, 'billing_address_1', sanitize_text_field($user_data['address1']));
     update_user_meta($user_id, 'billing_address_2', sanitize_text_field($user_data['address2']));
     update_user_meta($user_id, 'billing_city', sanitize_text_field($user_data['address3']));
     update_user_meta($user_id, 'billing_state', sanitize_text_field($user_data['address4']));
     update_user_meta($user_id, 'billing_postcode', sanitize_text_field($user_data['address5']));
     update_user_meta($user_id, 'billing_country', sanitize_text_field($user_data['countryCode']));
     update_user_meta($user_id, 'billing_phone', sanitize_text_field($user_data['telephone']));
     update_user_meta($user_id, 'billing_email', sanitize_text_field($user_data['email']));
 
     update_user_meta($user_id, 'shipping_address_1', sanitize_text_field($user_data['deliveryAddress1']));
     update_user_meta($user_id, 'shipping_address_2', sanitize_text_field($user_data['deliveryAddress2']));
     update_user_meta($user_id, 'shipping_city', sanitize_text_field($user_data['deliveryAddress3']));
     update_user_meta($user_id, 'shipping_state', sanitize_text_field($user_data['deliveryAddress4']));
     update_user_meta($user_id, 'shipping_postcode', sanitize_text_field($user_data['deliveryAddress5']));
     update_user_meta($user_id, 'shipping_country', sanitize_text_field($user_data['countryCode']));
     update_user_meta($user_id, 'shipping_phone', sanitize_text_field($user_data['telephone']));
 

    wp_send_json_success();
}

function delete_all_users()
{
    $users = get_users();

    foreach ($users as $user) {
        wp_delete_user($user->ID);
    }

    wp_send_json_success();
}

new Sinappsus_GGT_Admin_UI();

// THE FUNCTIONALITY TO SHOW THE META DATA ON THE PRODUCT

add_action('woocommerce_product_options_general_product_data', 'add_custom_fields_to_product');

function add_custom_fields_to_product()
{
    global $post;

    $custom_fields = [
        'link_to_product' => 'Link to Product',
        'content' => 'Content',
        'product_grid_content' => 'Product Grid Content',
        'image_url' => 'Image URL',
        'sku' => 'SKU',
        'gtin' => 'GTIN',
        'ean' => 'EAN',
        'own' => 'Own',
        'brand' => 'Brand',
        'category' => 'Category',
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
        '_stockCode' => 'Stock Code',
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
    ];

    echo '<div class="options_group">';
    foreach ($custom_fields as $key => $label) {
        woocommerce_wp_text_input([
            'id' => $key,
            'label' => __($label, 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the ' . $label, 'woocommerce'),
            'value' => get_post_meta($post->ID, $key, true)
        ]);
    }
    echo '</div>';
}

add_action('woocommerce_process_product_meta', 'save_custom_fields_to_product');

function save_custom_fields_to_product($post_id)
{
    $custom_fields = [
        'link_to_product',
        'content',
        'product_grid_content',
        'image_url',
        'sku',
        'gtin',
        'ean',
        'own',
        'brand',
        'category',
        'output',
        'energy_rating',
        'scop',
        'phase',
        'itemType',
        'nominalCode',
        'unitOfSale',
        'deptNumber',
        'custom1',
        'custom2',
        'custom3',
        'deletedFlag',
        'inactiveFlag',
        'salesPrice',
        'unitWeight',
        'taxCode',
        'qtyAllocated',
        'qtyInStock',
        'qtyOnOrder',
        'stockTakeDate',
        'stockCat',
        '_stockCode',
        'averageCostPrice',
        'location',
        'purchaseNominalCode',
        'lastPurchasePrice',
        'commodityCode',
        'barcode',
        'webDetails',
        'webDescription',
        'supplierPartNumber',
        'recordCreateDate',
        'recordModifyDate',
        'supplierRef',
        'webCategoryA',
        'webCategoryB',
        'webCategoryC',
        'instrastatCommCode',
        'reorderLevel',
        'reorderQty',
        'webPublish',
        'webSpecialOffer',
        'webImage',
        'assemblyLevel',
        'lastCostPrice',
        'lastDiscPurchasePrice',
        'countryCodeOfOrigin',
        'discALevel1Rate',
        'discALevel2Rate',
        'discALevel3Rate',
        'discALevel4Rate',
        'discALevel5Rate',
        'discALevel6Rate',
        'discALevel7Rate',
        'discALevel8Rate',
        'discALevel9Rate',
        'discALevel10Rate',
        'discALevel1Qty',
        'discALevel2Qty',
        'discALevel3Qty',
        'discALevel4Qty',
        'discALevel5Qty',
        'discALevel6Qty',
        'discALevel7Qty',
        'discALevel8Qty',
        'discALevel9Qty',
        'discALevel10Qty',
        'component1Code',
        'component2Code',
        'component3Code',
        'component4Code',
        'component5Code',
        'component6Code',
        'component7Code',
        'component8Code',
        'component9Code',
        'component10Code',
        'component1Qty',
        'component2Qty',
        'component3Qty',
        'component4Qty',
        'component5Qty',
        'component6Qty',
        'component7Qty',
        'component8Qty',
        'component9Qty',
        'component10Qty',
        'lastDateSynched',
    ];

    foreach ($custom_fields as $key) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
}
// END OF THE FUNCTIONALITY TO SHOW THE META DATA ON THE PRODUCT


// USER PROFILES AND REGISTER

add_action('show_user_profile', 'show_custom_user_profile_fields');
add_action('edit_user_profile', 'show_custom_user_profile_fields');

function show_custom_user_profile_fields($user)
{
    $custom_fields = [
        'accountRef' => 'Account Reference',
        'address1' => 'Address 1',
        'address2' => 'Address 2',
        'address3' => 'Address 3',
        'address4' => 'Address 4',
        'address5' => 'Address 5',
        'countryCode' => 'Country Code',
        'contactName' => 'Contact Name',
        'telephone' => 'Telephone',
        'deliveryName' => 'Delivery Name',
        'deliveryAddress1' => 'Delivery Address 1',
        'deliveryAddress2' => 'Delivery Address 2',
        'deliveryAddress3' => 'Delivery Address 3',
        'deliveryAddress4' => 'Delivery Address 4',
        'deliveryAddress5' => 'Delivery Address 5',
        'email2' => 'Email 2',
        'email3' => 'Email 3',
        'email4' => 'Email 4',
        'email5' => 'Email 5',
        'email6' => 'Email 6',
        'eoriNumber' => 'EORI Number',
        'defNomCode' => 'Default Nominal Code',
        'defNomCodeUseDefault' => 'Use Default Nominal Code',
        'defTaxCode' => 'Default Tax Code',
        'defTaxCodeUseDefault' => 'Use Default Tax Code',
        'terms' => 'Terms',
        'termsAgreed' => 'Terms Agreed',
        'turnoverYtd' => 'Turnover YTD',
        'currency' => 'Currency',
        'bankAccountName' => 'Bank Account Name',
        'bankSortCode' => 'Bank Sort Code',
        'bankAccountNumber' => 'Bank Account Number',
        'bacsRef' => 'BACS Reference',
        'iban' => 'IBAN',
        'bicSwift' => 'BIC/SWIFT',
        'rollNumber' => 'Roll Number',
        'additionalRef1' => 'Additional Reference 1',
        'additionalRef2' => 'Additional Reference 2',
        'additionalRef3' => 'Additional Reference 3',
        'paymentType' => 'Payment Type',
        'sendInvoicesElectronically' => 'Send Invoices Electronically',
        'sendLettersElectronically' => 'Send Letters Electronically',
        'analysis1' => 'Analysis 1',
        'analysis2' => 'Analysis 2',
        'analysis3' => 'Analysis 3',
        'analysis4' => 'Analysis 4',
        'analysis5' => 'Analysis 5',
        'analysis6' => 'Analysis 6',
        'deptNumber' => 'Department Number',
        'paymentDueDays' => 'Payment Due Days',
        'paymentDueFrom' => 'Payment Due From',
        'accountStatus' => 'Account Status',
        'inactiveAccount' => 'Inactive Account',
        'onHold' => 'On Hold',
        'creditLimit' => 'Credit Limit',
        'balance' => 'Balance',
        'vatNumber' => 'VAT Number',
        'memo' => 'Memo',
        'discountRate' => 'Discount Rate',
        'discountType' => 'Discount Type',
        'www' => 'Website',
        'priceListRef' => 'Price List Reference',
        'tradeContact' => 'Trade Contact',
        'telephone2' => 'Telephone 2',
        'fax' => 'Fax',
        'lastDateSynched' => 'Last Date Synched',
    ];

    echo '<h3>Custom User Profile Fields</h3>';
    echo '<table class="form-table">';
    foreach ($custom_fields as $key => $label) {
        echo '<tr>';
        echo '<th><label for="' . $key . '">' . $label . '</label></th>';
        echo '<td><input type="text" name="' . $key . '" id="' . $key . '" value="' . esc_attr(get_user_meta($user->ID, $key, true)) . '" class="regular-text" /></td>';
        echo '</tr>';
    }
    echo '</table>';
}

add_action('personal_options_update', 'save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'save_custom_user_profile_fields');

function save_custom_user_profile_fields($user_id)
{
    $custom_fields = [
        'accountRef',
        'address1',
        'address2',
        'address3',
        'address4',
        'address5',
        'countryCode',
        'contactName',
        'telephone',
        'deliveryName',
        'deliveryAddress1',
        'deliveryAddress2',
        'deliveryAddress3',
        'deliveryAddress4',
        'deliveryAddress5',
        'email2',
        'email3',
        'email4',
        'email5',
        'email6',
        'eoriNumber',
        'defNomCode',
        'defNomCodeUseDefault',
        'defTaxCode',
        'defTaxCodeUseDefault',
        'terms',
        'termsAgreed',
        'turnoverYtd',
        'currency',
        'bankAccountName',
        'bankSortCode',
        'bankAccountNumber',
        'bacsRef',
        'iban',
        'bicSwift',
        'rollNumber',
        'additionalRef1',
        'additionalRef2',
        'additionalRef3',
        'paymentType',
        'sendInvoicesElectronically',
        'sendLettersElectronically',
        'analysis1',
        'analysis2',
        'analysis3',
        'analysis4',
        'analysis5',
        'analysis6',
        'deptNumber',
        'paymentDueDays',
        'paymentDueFrom',
        'accountStatus',
        'inactiveAccount',
        'onHold',
        'creditLimit',
        'balance',
        'vatNumber',
        'memo',
        'discountRate',
        'discountType',
        'www',
        'priceListRef',
        'tradeContact',
        'telephone2',
        'fax',
        'lastDateSynched',
    ];

    foreach ($custom_fields as $key) {
        if (isset($_POST[$key])) {
            update_user_meta($user_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
}


// update the register form

add_action('register_form', 'add_custom_registration_fields');

function add_custom_registration_fields()
{
    $custom_fields = [
        'accountRef',
        'name',
        'address1',
        'address2',
        'address3',
        'address4',
        'address5',
        'countryCode',
        'contactName',
        'telephone',
        'email2',
        'eoriNumber',
        'www',
        'telephone2',
    ];

    foreach ($custom_fields as $key => $label) {
        echo '<p>';
        echo '<label for="' . $key . '">' . $label . '<br />';
        echo '<input type="text" name="' . $key . '" id="' . $key . '" class="input" value="' . esc_attr(wp_unslash($_POST[$key] ?? '')) . '" size="25" /></label>';
        echo '</p>';
    }
}

add_action('user_register', 'save_custom_registration_fields');

function save_custom_registration_fields($user_id)
{
    $custom_fields = [
        'accountRef',
        'name',
        'address1',
        'address2',
        'address3',
        'address4',
        'address5',
        'countryCode',
        'contactName',
        'telephone',
        'email2',
        'eoriNumber',
        'www',
        'telephone2',
    ];

    $user_data = [];
    foreach ($custom_fields as $key) {
        if (isset($_POST[$key])) {
            update_user_meta($user_id, $key, sanitize_text_field($_POST[$key]));
            $user_data[$key] = sanitize_text_field($_POST[$key]);
        }
    }

      // Update WooCommerce billing and shipping address fields
      update_user_meta($user_id, 'billing_address_1', sanitize_text_field($_POST['address1']));
      update_user_meta($user_id, 'billing_address_2', sanitize_text_field($_POST['address2']));
      update_user_meta($user_id, 'billing_city', sanitize_text_field($_POST['address3']));
      update_user_meta($user_id, 'billing_state', sanitize_text_field($_POST['address4']));
      update_user_meta($user_id, 'billing_postcode', sanitize_text_field($_POST['address5']));
      update_user_meta($user_id, 'billing_country', sanitize_text_field($_POST['countryCode']));
      update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['telephone']));
      update_user_meta($user_id, 'billing_email', sanitize_text_field($_POST['email']));
  
      update_user_meta($user_id, 'shipping_address_1', sanitize_text_field($_POST['address1']));
      update_user_meta($user_id, 'shipping_address_2', sanitize_text_field($_POST['address2']));
      update_user_meta($user_id, 'shipping_city', sanitize_text_field($_POST['address3']));
      update_user_meta($user_id, 'shipping_state', sanitize_text_field($_POST['address4']));
      update_user_meta($user_id, 'shipping_postcode', sanitize_text_field($_POST['deliveryAddress5']));
      update_user_meta($user_id, 'shipping_country', sanitize_text_field($_POST['countryCode']));
      update_user_meta($user_id, 'shipping_phone', sanitize_text_field($_POST['telephone']));

    // Send user data to the API
    wp_remote_post('https://api.gogeothermal.co.uk/api/customers', [
        'headers' => [
            'Authorization' => 'Bearer ' . get_option('sinappsus_gogeo_codex'),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($user_data)
    ]);
}
// END USER PROFILES AND REGISTER


// AUTHENTICATION TO API
add_action('wp_ajax_store_token', 'store_token');
function store_token()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $token = sanitize_text_field($_POST['token']);
    $encrypted_token = openssl_encrypt($token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
    update_option('sinappsus_gogeo_codex', $encrypted_token);
    wp_send_json_success();
}

function get_token()
{
    $encrypted_token = get_option('sinappsus_gogeo_codex');
    if ($encrypted_token) {
        return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
    }
    return false;
}

add_action('wp_ajax_get_token', 'get_token_ajax');
function get_token_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $token = get_token();
    if ($token) {
        wp_send_json_success(['token' => $token]);
    } else {
        wp_send_json_error('Token not found');
    }
}
// END AUTHENTICATION TO API


add_filter('wp_mail', 'disabling_emails', 10, 1);
function disabling_emails($args)
{
    unset($args['to']);
    return $args;
}
