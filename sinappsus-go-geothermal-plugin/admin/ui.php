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
    private $environments;

    public function __construct()
    {
        global $environments;
        $this->environments = $environments;

        // Get selected environment or default to production
        $selected_env = get_option('ggt_sinappsus_environment', 'production');
        $this->api_url = $this->environments[$selected_env]['api_url'];

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
        $token_exists = get_token() ? true : false;
        $selected_env = get_option('ggt_sinappsus_environment', 'production');
?>
        <div class="wrap">
            <h1>Go Geothermal Settings</h1>
            <form method="post" action="options.php" style="display: <?php echo $token_exists ? 'none' : 'block'; ?>">
                <?php
                settings_fields('ggt_sinappsus_settings_group');
                do_settings_sections('ggt_sinappsus_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Environment</th>
                        <td>
                            <select name="ggt_sinappsus_environment">
                                <option value="production" <?php selected($selected_env, 'production'); ?>>Production</option>
                                <option value="staging" <?php selected($selected_env, 'staging'); ?>>Staging/Testing</option>
                            </select>
                            <p class="description">Select the API environment to connect to.</p>
                        </td>
                    </tr>
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
            <div id="additional-actions" style="display: <?php echo $token_exists ? 'block' : 'none'; ?>">
                <h2>Product Actions</h2>
                <div class="action-item">
                    <button type="button" id="clear-products-button" class="button button-secondary">Clear All Products</button>
                    <p class="description">This will remove all products from the database.</p>
                </div>
                <div class="action-item">
                    <button type="button" id="sync-products-button" class="button button-secondary">Sync All Products</button>
                    <p class="description">This will synchronize all products with the Sage system.</p>
                </div>
             
                <!-- Progress container for sync process -->
                <div id="sync-progress-container" style="display:none; margin-top: 15px;">
                    <div class="sync-status-message"></div>
                    <div class="progress-bar-container" style="height: 20px; background-color: #f0f0f0; border-radius: 3px; margin: 10px 0; overflow: hidden;">
                        <div class="progress-bar" style="width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;"></div>
                    </div>
                    <div class="sync-details">
                        <span class="sync-count">0</span> / <span class="sync-total">0</span> products processed
                        (<span class="sync-success-count">0</span> updated, <span class="sync-skip-count">0</span> skipped)
                    </div>
                </div>
            </div>
            <div id="user-actions" style="display: <?php echo $token_exists ? 'block' : 'none'; ?>">
                <h2>User Actions</h2>
                <div class="action-item">
                    <button type="button" id="sync-users-button" class="button button-secondary">Sync All Users</button>
                    <p class="description">This will synchronize all users with the Sage system.</p>
                </div>
                <!-- Progress container for user sync process -->
                <div id="user-sync-progress-container" style="display:none; margin-top: 15px;">
                    <div class="user-sync-status-message"></div>
                    <div class="progress-bar-container" style="height: 20px; background-color: #f0f0f0; border-radius: 3px; margin: 10px 0; overflow: hidden;">
                        <div class="user-progress-bar" style="width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;"></div>
                    </div>
                    <div class="user-sync-details">
                        <span class="user-sync-count">0</span> / <span class="user-sync-total">0</span> users processed
                        (<span class="user-sync-success-count">0</span> updated, <span class="user-sync-error-count">0</span> failed)
                    </div>
                </div>
                <div class="action-item">
                    <button type="button" id="delete-users-button" class="button button-secondary">Delete All Users</button>
                    <p class="description">This will remove all users from the database.</p>
                </div>
                <div class="action-item">
                    <button type="button" id="reset-token-button" class="button">Reset Token</button>
                    <p class="description">This will reset the current token and show the login form again.</p>
                </div>

            </div>

            <div class="action-item" style="display: <?php echo $token_exists ? 'block' : 'none'; ?>">
                <h3>Registration Fields</h3>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('ggt_sinappsus_settings_group');
                    do_settings_sections('ggt_sinappsus_settings_group');
                    ?>
                    <label for="ggt_enable_additional_registration_fields">
                        <input type="checkbox" id="ggt_enable_additional_registration_fields" name="ggt_enable_additional_registration_fields" value="1" <?php checked(1, get_option('ggt_enable_additional_registration_fields'), true); ?> />
                        Enable Additional Registration Fields
                    </label>
                    <p class="description">Enable or disable additional fields on the registration form.</p>
                    <?php submit_button(); ?>
                </form>
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
                    var environment = document.querySelector('select[name="ggt_sinappsus_environment"]').value;

                    // First store the environment
                    jQuery.post(ajaxurl, {
                        action: 'store_environment',
                        environment: environment
                    }, function(response) {
                        if (response.success) {
                            // Now authenticate with the API
                            var apiUrl = environment === 'production' ?
                                '<?php echo $this->environments["production"]["api_url"]; ?>' :
                                '<?php echo $this->environments["staging"]["api_url"]; ?>';

                            fetch(apiUrl + '/login', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        email: email,
                                        password: password
                                    })
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
                        }
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
                    // Reset counters and show progress container
                    let successCount = 0;
                    let errorCount = 0;
                    
                    document.getElementById('message').innerText = 'Initializing user synchronization...';
                    document.getElementById('user-sync-progress-container').style.display = 'block';
                    document.querySelector('.user-sync-status-message').innerText = 'Connecting to API...';
                    document.querySelector('.user-progress-bar').style.width = '0%';
                    document.querySelector('.user-sync-count').innerText = '0';
                    document.querySelector('.user-sync-success-count').innerText = '0';
                    document.querySelector('.user-sync-error-count').innerText = '0';

                    getToken().then(token => {
                        document.querySelector('.user-sync-status-message').innerText = 'Fetching users from API...';
                        
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
                                    let processed = 0;
                                    let total = data.length;
                                    document.querySelector('.user-sync-status-message').innerText = 'Starting synchronization of ' + total + ' users...';
                                    document.querySelector('.user-sync-total').innerText = total;
                                    
                                    // Process each user sequentially to avoid overwhelming the server
                                    function processNextUser(index) {
                                        if (index >= data.length) {
                                            document.querySelector('.user-sync-status-message').innerText = 'Synchronization complete!';
                                            document.getElementById('message').innerText = 'Users synchronized successfully! Updated: ' + 
                                                successCount + ', Failed: ' + errorCount;
                                            
                                            // Hide the progress container after 20 seconds
                                            setTimeout(function() {
                                                document.getElementById('user-sync-progress-container').style.display = 'none';
                                            }, 20000);
                                            return;
                                        }

                                        let user_data = data[index];
                                        if (!user_data.email) {
                                            processed++;
                                            errorCount++;
                                            document.querySelector('.user-sync-error-count').innerText = errorCount;
                                            document.querySelector('.user-sync-status-message').innerText = 'Skipping user with no email...';
                                            updateProgress(processed, total);
                                            processNextUser(index + 1);
                                            return;
                                        }

                                        document.querySelector('.user-sync-status-message').innerText = 'Processing user: ' + user_data.email;
                                        
                                        jQuery.post(ajaxurl, {
                                            action: 'sync_user',
                                            user_data: user_data
                                        }, function(response) {
                                            processed++;
                                            if (response.success) {
                                                successCount++;
                                                document.querySelector('.user-sync-success-count').innerText = successCount;
                                            } else {
                                                errorCount++;
                                                document.querySelector('.user-sync-error-count').innerText = errorCount;
                                                document.querySelector('.user-sync-status-message').innerText = 'Error with user: ' + user_data.email;
                                            }
                                            updateProgress(processed, total);
                                            processNextUser(index + 1);
                                        }).fail(function(error) {
                                            processed++;
                                            errorCount++;
                                            document.querySelector('.user-sync-error-count').innerText = errorCount;
                                            document.querySelector('.user-sync-status-message').innerText = 'Error with user: ' + user_data.email;
                                            updateProgress(processed, total);
                                            processNextUser(index + 1);
                                        });
                                    }

                                    // Helper function to update progress
                                    function updateProgress(current, total) {
                                        const percent = Math.round((current / total) * 100);
                                        document.querySelector('.user-progress-bar').style.width = percent + '%';
                                        document.querySelector('.user-sync-count').innerText = current;
                                        document.getElementById('message').innerText = 'Processed: ' + current + ' of ' + total + 
                                            ' (' + percent + '%)';
                                    }

                                    // Start processing users
                                    processNextUser(0);
                                } else {
                                    document.querySelector('.user-sync-status-message').innerText = 'Error: Invalid response from API';
                                    document.getElementById('message').innerText = 'Invalid response from API.';
                                }
                            })
                            .catch(error => {
                                document.querySelector('.user-sync-status-message').innerText = 'Error: ' + error.message;
                                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
                            });
                    }).catch(error => {
                        document.querySelector('.user-sync-status-message').innerText = 'Authentication error';
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

                document.getElementById('clear-products-button').addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete all products?')) {
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: 'action=clear_all_products'
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('message').innerText = 'All products deleted successfully!';
                                } else {
                                    document.getElementById('message').innerText = 'Failed to delete products!';
                                }
                            })
                            .catch(error => {
                                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
                            });
                    }
                });

                document.getElementById('sync-products-button').addEventListener('click', function() {
                    // Reset counters and show progress container
                    let successCount = 0;
                    let skipCount = 0;
                    
                    document.getElementById('message').innerText = 'Initializing product synchronization...';
                    document.getElementById('sync-progress-container').style.display = 'block';
                    document.querySelector('.sync-status-message').innerText = 'Connecting to API...';
                    document.querySelector('.progress-bar').style.width = '0%';
                    document.querySelector('.sync-count').innerText = '0';
                    document.querySelector('.sync-success-count').innerText = '0';
                    document.querySelector('.sync-skip-count').innerText = '0';

                    getToken().then(token => {
                        document.querySelector('.sync-status-message').innerText = 'Fetching products from API...';
                        
                        fetch('<?php echo $this->api_url; ?>/products', {
                                method: 'GET',
                                headers: {
                                    'Authorization': 'Bearer ' + token,
                                    'Content-Type': 'application/json'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data && Array.isArray(data)) {
                                    // First, get all existing products
                                    document.querySelector('.sync-status-message').innerText = 'Analyzing existing products...';
                                    
                                    jQuery.post(ajaxurl, {
                                        action: 'get_all_products'
                                    }).done(function(existingProducts) {
                                        let existingByStockCode = {};
                                        let existingBySku = {};
                                        if (existingProducts.success && Array.isArray(existingProducts.data)) {
                                            // Create lookup maps for faster checking - one for stockCode and one for SKU
                                            existingProducts.data.forEach(product => {
                                                if (product.stockCode) {
                                                    existingByStockCode[product.stockCode] = product.id;
                                                }
                                                if (product.sku) {
                                                    existingBySku[product.sku] = product.id;
                                                }
                                            });
                                        }

                                        let processed = 0;
                                        let total = data.length;
                                        document.querySelector('.sync-status-message').innerText = 'Starting synchronization of ' + total + ' products...';
                                        document.querySelector('.sync-total').innerText = total;

                                        // Process each product sequentially to avoid overwhelming the server
                                        function processNextProduct(index) {
                                            if (index >= data.length) {
                                                document.querySelector('.sync-status-message').innerText = 'Synchronization complete!';
                                                document.getElementById('message').innerText = 'Products synchronized successfully! Updated: ' + 
                                                    successCount + ', Skipped: ' + skipCount;
                                                
                                                // Hide the progress container after 20 seconds
                                                setTimeout(function() {
                                                    document.getElementById('sync-progress-container').style.display = 'none';
                                                }, 20000);
                                                return;
                                            }

                                            let product_data = data[index];
                                            if (!product_data.stockCode) {
                                                processed++;
                                                skipCount++;
                                                document.querySelector('.sync-skip-count').innerText = skipCount;
                                                document.querySelector('.sync-status-message').innerText = 'Skipping product with no stock code...';
                                                updateProgress(processed, total);
                                                processNextProduct(index + 1);
                                                return;
                                            }

                                            // Check if the product exists by stockCode or SKU
                                            let existingProductId = existingByStockCode[product_data.stockCode] ||
                                                (product_data.sku ? existingBySku[product_data.sku] : null);

                                            if (existingProductId) {
                                                // Update existing product
                                                document.querySelector('.sync-status-message').innerText = 'Updating: ' + (product_data.title || product_data.stockCode);
                                                jQuery.post(ajaxurl, {
                                                    action: 'update_product',
                                                    product_id: existingProductId,
                                                    product_data: product_data
                                                }).always(function() {
                                                    processed++;
                                                    successCount++;
                                                    document.querySelector('.sync-success-count').innerText = successCount;
                                                    updateProgress(processed, total);
                                                    processNextProduct(index + 1);
                                                });
                                            } else if (product_data.inactiveFlag !== true && product_data.inactiveFlag !== "1") {
                                                // Only create new product if inactiveFlag is not true or "1"
                                                document.querySelector('.sync-status-message').innerText = 'Creating: ' + (product_data.title || product_data.stockCode);
                                                jQuery.post(ajaxurl, {
                                                    action: 'create_product',
                                                    product_data: product_data
                                                }).always(function() {
                                                    processed++;
                                                    successCount++;
                                                    document.querySelector('.sync-success-count').innerText = successCount;
                                                    updateProgress(processed, total);
                                                    processNextProduct(index + 1);
                                                });
                                            } else {
                                                // Skip this product
                                                document.querySelector('.sync-status-message').innerText = 'Skipping inactive product: ' + (product_data.title || product_data.stockCode);
                                                processed++;
                                                skipCount++;
                                                document.querySelector('.sync-skip-count').innerText = skipCount;
                                                updateProgress(processed, total);
                                                processNextProduct(index + 1);
                                            }
                                        }

                                        // Helper function to update progress
                                        function updateProgress(current, total) {
                                            const percent = Math.round((current / total) * 100);
                                            document.querySelector('.progress-bar').style.width = percent + '%';
                                            document.querySelector('.sync-count').innerText = current;
                                            document.getElementById('message').innerText = 'Processed: ' + current + ' of ' + total + 
                                                ' (' + percent + '%)';
                                        }

                                        // Start processing products
                                        processNextProduct(0);
                                    }).fail(function(error) {
                                        document.querySelector('.sync-status-message').innerText = 'Error: Failed to get existing products';
                                        document.getElementById('message').innerText = 'Failed to get existing products: ' + error.message;
                                    });
                                } else {
                                    document.querySelector('.sync-status-message').innerText = 'Error: Invalid response from API';
                                    document.getElementById('message').innerText = 'Invalid response from API.';
                                }
                            })
                            .catch(error => {
                                document.querySelector('.sync-status-message').innerText = 'Error: ' + error.message;
                                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
                            });
                    }).catch(error => {
                        document.querySelector('.sync-status-message').innerText = 'Authentication error';
                        document.getElementById('message').innerText = 'An error occurred: ' + error;
                    });
                });

              

                // Add this in the document.addEventListener('DOMContentLoaded') function
                document.getElementById('reset-token-button').addEventListener('click', function() {
                    if (confirm('Are you sure you want to reset the token? You will need to authenticate again.')) {
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: 'action=reset_token'
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('message').innerText = 'Token reset successfully!';
                                    // Hide action sections and show login form
                                    document.getElementById('additional-actions').style.display = 'none';
                                    document.getElementById('user-actions').style.display = 'none';
                                    document.querySelector('form').style.display = 'block';
                                    document.querySelector('.action-item[style*="block"]').style.display = 'none';
                                } else {
                                    document.getElementById('message').innerText = 'Failed to reset token!';
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
    register_setting('ggt_sinappsus_settings_group', 'ggt_enable_additional_registration_fields');
    register_setting('ggt_sinappsus_settings_group', 'ggt_sinappsus_environment');
}

add_action('wp_ajax_clear_all_products', 'clear_all_products');
add_action('wp_ajax_get_all_products', 'get_all_products');
add_action('wp_ajax_update_product', 'update_product');
add_action('wp_ajax_create_product', 'create_product');
add_action('wp_ajax_sync_user', 'sync_user');
add_action('wp_ajax_delete_all_users', 'delete_all_users');
add_action('wp_ajax_reset_token', 'reset_token');

function reset_token()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    delete_option('sinappsus_gogeo_codex');
    wp_send_json_success();
}



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
    $count = count($products);

    if ($count === 0) {
        wp_send_json_success(['message' => 'No products found to delete']);
        return;
    }

    $deleted = 0;
    $errors = [];

    foreach ($products as $product) {
        $result = wp_delete_post($product->ID, true);
        if ($result) {
            $deleted++;
        } else {
            $errors[] = "Failed to delete product ID: {$product->ID}";
        }
    }

    wp_send_json_success([
        'deleted' => $deleted,
        'total' => $count,
        'errors' => $errors
    ]);
}


function get_all_products()
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
    $result = array();

    foreach ($products as $product) {
        $product_obj = wc_get_product($product->ID);
        $stock_code = $product_obj->get_meta('_stockCode');
        $sku = $product_obj->get_sku();

        $result[] = array(
            'id' => $product->ID,
            'stockCode' => $stock_code,
            'sku' => $sku
        );
    }

    wp_send_json_success($result);
}

function create_product()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $product_data = $_POST['product_data'];

    $product = new WC_Product();
    $product->set_name($product_data['description']);
    $product->set_regular_price($product_data['salesPrice']);
    
    // Set description based on webDescription if available, otherwise use description
    $description = !empty($product_data['webDescription']) ? $product_data['webDescription'] : $product_data['description'];
    $product->set_description($description);
    
    $product->set_stock($product_data['qtyInStock']);
    $product->set_manage_stock(true);
    $product->set_backorders('yes');
    
    // Set the stockCode as meta data
    $product->update_meta_data('_stockCode', $product_data['stockCode']);
    
    // If no SKU is provided, use stockCode as the SKU
    if (empty($product_data['sku'])) {
        $product->set_sku($product_data['stockCode']);
    } else {
        $product->set_sku($product_data['sku']);
    }

    // Handle category assignment
    if (!empty($product_data['category'])) {
        $category_id = find_or_create_product_category($product_data['category']);
        if ($category_id) {
            $product->set_category_ids(array($category_id));
        }
    }

    // Update other meta data (including parent_product_id for grouping)
    foreach ($product_data as $key => $value) {
        if (!in_array($key, ['sku', 'salesPrice', 'description', 'stockCode', 'category', 'image_url', 'webDescription'])) {
            $product->update_meta_data($key, $value);
        }
    }

    $product->save();
    
    // Handle product grouping after product is saved
    handle_product_grouping($product->get_id(), $product_data);
    
    // Set featured image after product is saved (needs product ID)
    if (!empty($product_data['image_url'])) {
        set_product_featured_image_from_url($product->get_id(), $product_data['image_url']);
    }
    
    wp_send_json_success();
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
        $product->set_name($product_data['description']);
        $product->set_regular_price($product_data['salesPrice']);
        
        // Set description based on webDescription if available, otherwise use description
        $description = !empty($product_data['webDescription']) ? $product_data['webDescription'] : $product_data['description'];
        $product->set_description($description);
        
        $product->update_meta_data('_stockCode', $product_data['stockCode']);
        $product->set_stock($product_data['qtyInStock']);
        $product->set_manage_stock(true);
        $product->set_backorders('yes');

        // Handle category assignment
        if (!empty($product_data['category'])) {
            $category_id = find_or_create_product_category($product_data['category']);
            if ($category_id) {
                $product->set_category_ids(array($category_id));
            }
        }

        // Update other meta data (including parent_product_id for grouping)
        foreach ($product_data as $key => $value) {
            if (!in_array($key, ['sku', 'salesPrice', 'description', 'stockCode', 'category', 'image_url', 'webDescription'])) {
                $product->update_meta_data($key, $value);
            }
        }

        $product->save();
        
        // Handle product grouping after product is saved
        handle_product_grouping($product->get_id(), $product_data);
        
        // Set featured image after product is saved
        if (!empty($product_data['image_url'])) {
            set_product_featured_image_from_url($product->get_id(), $product_data['image_url']);
        }
        
        wp_send_json_success();
    } else {
        wp_send_json_error('Product not found', 404);
    }
}

function sync_user()
{
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
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    // Get all users with role 'subscriber' or 'customer'
    $args = array(
        'role__in' => array('subscriber', 'customer'),
        'fields' => 'ID'
    );

    $users = get_users($args);
    $count = count($users);

    if ($count === 0) {
        wp_send_json_success(['message' => 'No users found to delete']);
        return;
    }

    $deleted = 0;
    $errors = [];

    foreach ($users as $user_id) {
        // Skip admin users as a safeguard
        if (user_can($user_id, 'manage_options')) {
            continue;
        }

        $result = wp_delete_user($user_id);
        if ($result) {
            $deleted++;
        } else {
            $errors[] = "Failed to delete user ID: $user_id";
        }
    }

    wp_send_json_success([
        'deleted' => $deleted,
        'total' => $count,
        'errors' => $errors
    ]);
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
        'parent_product_id' => 'Parent Product ID',
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
        'parent_product_id',
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
    if (!get_option('ggt_enable_additional_registration_fields')) {
        return;
    }

    $custom_fields = [
        'name' => 'Name',
        'address1' => 'Address 1',
        'address2' => 'Address 2',
        'address3' => 'Address 3',
        'address4' => 'Address 4',
        'address5' => 'Address 5',
        'countryCode' => 'Country Code',
        'contactName' => 'Contact Name',
        'telephone' => 'Telephone',
        'email2' => 'Email 2',
        'eoriNumber' => 'EORI Number',
        'www' => 'Website',
        'telephone2' => 'Telephone 2',
    ];


    foreach ($custom_fields as $key => $label) {
        echo '<p><label for="' . $key . '">' . $label . '</label><br><input type="text" name="' . $key . '" id="' . $key . '" class="input" value="' . esc_attr(wp_unslash($_POST[$key] ?? '')) . '" size="25" /></p>';
    }
}

add_action('user_register', 'save_custom_registration_fields');

function save_custom_registration_fields($user_id)
{
    $custom_fields = [
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
    
    // Ensure primary email is included in API data
    if (isset($_POST['email'])) {
        $user_data['email'] = sanitize_email($_POST['email']);
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
    update_user_meta($user_id, 'shipping_postcode', sanitize_text_field($_POST['address5']));
    update_user_meta($user_id, 'shipping_country', sanitize_text_field($_POST['countryCode']));
    update_user_meta($user_id, 'shipping_phone', sanitize_text_field($_POST['telephone']));

    // Send user data to the API using centralized function
    ggt_sinappsus_connect_to_api('customers', $user_data, 'POST');
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

// Use our centralized function instead of duplicating logic
function get_token()
{
    return ggt_get_decrypted_token();
}

add_action('wp_ajax_get_token', 'get_token_ajax');
function get_token_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $token = ggt_get_decrypted_token();
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

// Add new AJAX handler for the environment
add_action('wp_ajax_store_environment', 'store_environment');
function store_environment()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    $environment = sanitize_text_field($_POST['environment']);
    if ($environment !== 'production' && $environment !== 'staging') {
        $environment = 'production'; // Default to production if invalid
    }

    update_option('ggt_sinappsus_environment', $environment);
    wp_send_json_success();
}

// WooCommerce Registration Form Integration - reuse existing functions
add_action('woocommerce_register_form', 'add_custom_registration_fields');
add_action('woocommerce_created_customer', 'save_custom_registration_fields');

// Helper function to find or create product category
function find_or_create_product_category($category_name) {
    if (empty($category_name)) {
        return null;
    }
    
    // Search for existing categories with wildcard matching
    $existing_categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'name__like' => $category_name,
        'hide_empty' => false,
    ));
    
    // If found, return the first match
    if (!empty($existing_categories)) {
        return $existing_categories[0]->term_id;
    }
    
    // Create new category if not found
    $new_category = wp_insert_term(
        $category_name,
        'product_cat'
    );
    
    if (is_wp_error($new_category)) {
        return null;
    }
    
    return $new_category['term_id'];
}

// Helper function to set featured image from URL
function set_product_featured_image_from_url($product_id, $image_url) {
    if (empty($image_url)) {
        return false;
    }
    
    // Check if the image already exists in media library
    $attachment_id = attachment_url_to_postid($image_url);
    
    if (!$attachment_id) {
        // Download and import the image
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($image_url, $product_id, null, 'id');
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
    }
    
    // Set as featured image
    return set_post_thumbnail($product_id, $attachment_id);
}

// WooCommerce Grouped Product Functions
function find_or_create_grouped_product($parent_product_id) {
    if (empty($parent_product_id)) {
        return null;
    }
    
    // Search for existing grouped product with this parent_product_id in meta
    $existing_groups = get_posts(array(
        'post_type' => 'product',
        'meta_query' => array(
            array(
                'key' => '_parent_product_id',
                'value' => $parent_product_id,
                'compare' => '='
            )
        ),
        'post_status' => 'any',
        'posts_per_page' => 1
    ));
    
    if (!empty($existing_groups)) {
        $group_product = wc_get_product($existing_groups[0]->ID);
        // Ensure it's actually a grouped product
        if ($group_product && $group_product->is_type('grouped')) {
            return $existing_groups[0]->ID;
        }
    }
    
    // Create new grouped product
    $grouped_product = new WC_Product_Grouped();
    $grouped_product->set_name('Group: ' . $parent_product_id);
    $grouped_product->set_status('publish');
    $grouped_product->set_catalog_visibility('visible');
    
    // Save the grouped product
    $group_id = $grouped_product->save();
    
    if ($group_id) {
        // Store the parent_product_id as meta for future reference
        update_post_meta($group_id, '_parent_product_id', $parent_product_id);
        return $group_id;
    }
    
    error_log('GGT Plugin: Failed to create grouped product for parent_product_id: ' . $parent_product_id);
    return null;
}

function add_product_to_group($product_id, $group_id) {
    if (!$product_id || !$group_id) {
        return false;
    }
    
    $grouped_product = wc_get_product($group_id);
    if (!$grouped_product || !$grouped_product->is_type('grouped')) {
        error_log('GGT Plugin: Group product not found or not grouped type. Group ID: ' . $group_id);
        return false;
    }
    
    // Get current children
    $current_children = $grouped_product->get_children();
    
    // Add this product if not already in the group
    if (!in_array($product_id, $current_children)) {
        $current_children[] = $product_id;
        $grouped_product->set_children($current_children);
        $result = $grouped_product->save();
        
        if (!$result) {
            error_log('GGT Plugin: Failed to save grouped product children for group ID: ' . $group_id);
            return false;
        }
        
        // Also store the group relationship in the child product's meta
        update_post_meta($product_id, '_grouped_parent_id', $group_id);
    }
    
    return true;
}

function handle_product_grouping($product_id, $product_data) {
    if (empty($product_data['parent_product_id'])) {
        return;
    }
    
    $parent_product_id = $product_data['parent_product_id'];
    
    // Find or create the grouped product
    $grouped_product_id = find_or_create_grouped_product($parent_product_id);
    
    if ($grouped_product_id) {
        // Add this product to the group
        $result = add_product_to_group($product_id, $grouped_product_id);
        if (!$result) {
            error_log('GGT Plugin: Failed to add product ' . $product_id . ' to group ' . $grouped_product_id);
        }
    }
}


