<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the const.php file to get the environments array
require_once __DIR__ . '/../const.php';
ini_set('max_execution_time', 0);
ini_set('memory_limit', '3048M');

/**
 * Write to plugin debug log file
 */
function ggt_log($message, $context = '') {
    $log_dir = GGT_SINAPPSUS_PLUGIN_PATH . '/logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $context_str = $context ? " [{$context}]" : '';
    $log_line = "[{$timestamp}]{$context_str} {$message}\n";
    
    file_put_contents($log_file, $log_line, FILE_APPEND);
}

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
        
        // Validate environment key exists, fallback to production if invalid/empty
        if (empty($selected_env) || !isset($this->environments[$selected_env])) {
            $selected_env = 'production';
        }
        
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
        
        add_submenu_page(
            'sinappsus-ggt-settings',
            'Debug Logs',
            'Debug Logs',
            'manage_options',
            'sinappsus-ggt-logs',
            [$this, 'display_logs_page']
        );
    }

    public function display_settings_page()
    {
        $token_exists = get_token() ? true : false;
        $selected_env = get_option('ggt_sinappsus_environment', 'production');
        $plugin_enabled = (bool) get_option('ggt_plugin_enabled', 1);
        $last_product_import = get_option('ggt_last_product_import');
        $last_user_sync = get_option('ggt_last_user_sync');
?>
        <div class="wrap">
            <h1>Go Geothermal Settings</h1>
            
            <!-- Tabs Navigation -->
            <h2 class="nav-tab-wrapper" id="ggt-tabs-nav">
                <a href="#dashboard" class="nav-tab nav-tab-active">Dashboard</a>
                <a href="#auth" class="nav-tab">Authentication</a>
                <a href="#products" class="nav-tab">Products</a>
                <a href="#users" class="nav-tab">Users</a>
                <a href="#settings" class="nav-tab">Settings</a>
            </h2>

            <!-- Tabs Content -->
            <div id="ggt-tab-dashboard" class="ggt-tab-panel" style="display:block;">
                <h2>Dashboard</h2>
                <form method="post" action="options.php" style="margin-bottom:20px;">
                    <?php settings_fields('ggt_sinappsus_dashboard_group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Plugin Enabled</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ggt_plugin_enabled" value="1" <?php checked(1, $plugin_enabled, true); ?> />
                                    Enable Go Geothermal plugin features
                                </label>
                                <p class="description">Toggle to temporarily disable this plugin's frontend/admin behaviors without uninstalling.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Dashboard Settings'); ?>
                </form>
                <div class="card" style="max-width:800px;">
                    <h3>Last Product Import</h3>
                    <?php if (!empty($last_product_import) && is_array($last_product_import)): ?>
                        <p>Created: <strong><?php echo intval($last_product_import['created'] ?? 0); ?></strong>, Updated: <strong><?php echo intval($last_product_import['updated'] ?? 0); ?></strong>, Skipped: <strong><?php echo intval($last_product_import['skipped'] ?? 0); ?></strong></p>
                        <p>When: <em><?php echo isset($last_product_import['timestamp']) ? human_time_diff(intval($last_product_import['timestamp']), current_time('timestamp')) . ' ago' : 'N/A'; ?></em></p>
                    <?php else: ?>
                        <p>No product import has been recorded yet.</p>
                    <?php endif; ?>
                </div>
                <div class="card" style="max-width:800px; margin-top:15px;">
                    <h3>Last User Sync</h3>
                    <?php if (!empty($last_user_sync) && is_array($last_user_sync)): ?>
                        <p>Processed: <strong><?php echo intval($last_user_sync['total'] ?? 0); ?></strong>, Updated: <strong><?php echo intval($last_user_sync['updated'] ?? 0); ?></strong>, Failed: <strong><?php echo intval($last_user_sync['failed'] ?? 0); ?></strong></p>
                        <p>When: <em><?php echo isset($last_user_sync['timestamp']) ? human_time_diff(intval($last_user_sync['timestamp']), current_time('timestamp')) . ' ago' : 'N/A'; ?></em></p>
                    <?php else: ?>
                        <p>No user sync has been recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="ggt-tab-auth" class="ggt-tab-panel" style="display:none;">
                <h2>Authentication</h2>
                <p><?php echo $token_exists ? '<span style="color:green;">Token present</span>' : '<span style="color:#cc0000;">Not authenticated</span>'; ?></p>
                <form method="post" action="options.php">
                    <?php settings_fields('ggt_sinappsus_auth_group'); ?>
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
                        <?php submit_button('Save Auth Settings', 'secondary', 'submit', false); ?>
                    </p>
                    <p id="timer"></p>
                    <p id="message"></p>
                </form>
                <div class="action-item">
                    <button type="button" id="reset-token-button" class="button">Reset Token</button>
                    <p class="description">This will reset the current token and show the login form again.</p>
                </div>
            </div>

            <div id="ggt-tab-products" class="ggt-tab-panel" style="display:none;">
                <h2>Product Actions</h2>
                <?php if (!$plugin_enabled): ?>
                    <div class="notice notice-warning"><p>The plugin is currently disabled. Enable it on the Dashboard tab to unlock product actions.</p></div>
                <?php endif; ?>
                <?php if (!$token_exists): ?>
                    <div class="notice notice-error"><p>You're not authenticated. Go to the Authentication tab to sign in and obtain a token.</p></div>
                <?php endif; ?>
                <div class="action-item">
                    <button type="button" id="clear-products-button" class="button button-secondary" <?php disabled(!$token_exists || !$plugin_enabled); ?>>Clear All Products</button>
                    <p class="description">This will remove all products from the database.</p>
                </div>
                <div class="action-item">
                    <button type="button" id="configure-import-button" class="button button-primary" <?php disabled(!$token_exists || !$plugin_enabled); ?>>Configure Field Mapping</button>
                    <p class="description">Map API fields to WooCommerce product fields before importing.</p>
                </div>
                <div class="action-item" style="display: none;">
                    <button type="button" id="sync-products-button" class="button" <?php disabled(!$token_exists || !$plugin_enabled); ?>>Sync All Products</button>
                    <p class="description">Fetch products from API, updating existing by Stock Code or SKU and creating new ones if active.</p>
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

                <div class="card" style="margin-top:20px; max-width:1000px;">
                    <h3>Current Product Field Mapping</h3>
                    <?php 
                        $prod_mapping = get_option('ggt_product_field_mapping', array());
                        $prod_enabled = get_option('ggt_product_field_mapping_enabled', array());
                    ?>
                    <?php if (empty($prod_mapping)): ?>
                        <p>No product field mapping configured yet.</p>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="wp-list-table widefat fixed striped" style="min-width:600px;">
                                <thead>
                                    <tr>
                                        <th>API Field</th>
                                        <th>Mapped To (WooCommerce)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prod_mapping as $api_field => $wc_field): 
                                        $is_enabled = !isset($prod_enabled[$api_field]) || (bool)$prod_enabled[$api_field];
                                    ?>
                                        <tr>
                                            <td><code><?php echo esc_html($api_field); ?></code></td>
                                            <td><?php echo esc_html($wc_field); ?></td>
                                            <td><?php echo $is_enabled ? '<span style="color:green;">Enabled</span>' : '<span style="color:#666;">Disabled</span>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="ggt-tab-users" class="ggt-tab-panel" style="display:none;">
                <h2>User Actions</h2>
                <?php if (!$plugin_enabled): ?>
                    <div class="notice notice-warning"><p>The plugin is currently disabled. Enable it on the Dashboard tab to unlock user actions.</p></div>
                <?php endif; ?>
                <?php if (!$token_exists): ?>
                    <div class="notice notice-error"><p>You're not authenticated. Go to the Authentication tab to sign in and obtain a token.</p></div>
                <?php endif; ?>
                <div class="action-item">
                    <button type="button" id="configure-user-mapping-button" class="button button-primary" <?php disabled(!$token_exists || !$plugin_enabled); ?>>Configure User Field Mapping</button>
                    <p class="description">Map API account fields to WordPress/WooCommerce and control which fields show on registration.</p>
                </div>
                <div class="action-item">
                    <button type="button" id="sync-users-button" class="button button-secondary" <?php disabled(!$token_exists || !$plugin_enabled); ?>>Sync All Users</button>
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
                    <button type="button" id="delete-users-button" class="button button-secondary" <?php disabled(!$token_exists || !$plugin_enabled); ?>>Delete All Users</button>
                    <p class="description">This will remove all users from the database.</p>
                </div>

                <div class="card" style="margin-top:20px; max-width:1000px;">
                    <h3>Current User/Account Field Mapping</h3>
                    <?php 
                        $usr_mapping = get_option('ggt_user_field_mapping', array());
                        if (is_object($usr_mapping)) { $usr_mapping = (array)$usr_mapping; }
                        $usr_enabled = get_option('ggt_user_field_mapping_enabled', array());
                    ?>
                    <?php if (empty($usr_mapping)): ?>
                        <p>No user/account field mapping configured yet.</p>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="wp-list-table widefat fixed striped" style="min-width:600px;">
                                <thead>
                                    <tr>
                                        <th>API Field</th>
                                        <th>Mapped To (User Meta / Field)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usr_mapping as $api_field => $target): 
                                        $is_enabled = !isset($usr_enabled[$api_field]) || (bool)$usr_enabled[$api_field];
                                    ?>
                                        <tr>
                                            <td><code><?php echo esc_html($api_field); ?></code></td>
                                            <td><?php echo esc_html(is_array($target) ? json_encode($target) : $target); ?></td>
                                            <td><?php echo $is_enabled ? '<span style="color:green;">Enabled</span>' : '<span style="color:#666;">Disabled</span>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="ggt-tab-settings" class="ggt-tab-panel" style="display:none;">
                <h2>Registration & Import Settings</h2>
                <form method="post" action="options.php">
                    <?php
                    // One settings form handles both sections to avoid clearing values from the other
                    settings_fields('ggt_sinappsus_settings_group');
                    do_settings_sections('ggt_sinappsus_settings_group');
                    ?>

                    <!-- Registration Fields -->
                    <h3>Registration Fields</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Additional Registration Fields</th>
                            <td>
                                <label for="ggt_enable_additional_registration_fields">
                                    <input type="checkbox" id="ggt_enable_additional_registration_fields" name="ggt_enable_additional_registration_fields" value="1" <?php checked(1, get_option('ggt_enable_additional_registration_fields'), true); ?> />
                                    Enable Additional Registration Fields
                                </label>
                                <p class="description">Enable or disable additional fields on the registration form.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Two-Column Registration Layout</th>
                            <td>
                                <label for="ggt_registration_two_columns">
                                    <input type="checkbox" id="ggt_registration_two_columns" name="ggt_registration_two_columns" value="1" <?php checked(1, get_option('ggt_registration_two_columns'), true); ?> />
                                    Split additional registration fields into two columns
                                </label>
                                <p class="description">When enabled, the extra registration fields will render in two responsive columns.</p>
                            </td>
                        </tr>
                    </table>

                    <!-- Import Settings -->
                    <h3>Import Settings</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Account Not Found Notification Email</th>
                            <td>
                                <input type="email" name="ggt_account_not_found_email" value="<?php echo esc_attr(get_option('ggt_account_not_found_email')); ?>" class="regular-text" />
                                <p class="description">Email address to notify when an order is placed but the user account is not found/active in Sage.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Enable ACF Relate</th>
                            <td>
                                <label for="ggt_import_enable_acf_relate">
                                    <input type="checkbox" id="ggt_import_enable_acf_relate" name="ggt_import_enable_acf_relate" value="1" <?php checked(1, get_option('ggt_import_enable_acf_relate'), true); ?> />
                                    Enable ACF relating during import
                                </label>
                                <p class="description">When enabled, the importer will set the ACF fields for related products and required flag.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">ACF "Is Required" Field</th>
                            <td>
                                <?php
                                $acf_fields = array();
                                if (function_exists('acf_get_field_groups')) {
                                    $field_groups = acf_get_field_groups(array('post_type' => 'product'));
                                    foreach ($field_groups as $group) {
                                        $fields = acf_get_fields($group['key']);
                                        if ($fields) {
                                            foreach ($fields as $field) {
                                                if ($field['type'] === 'true_false') {
                                                    $acf_fields[$field['name']] = $field['label'] . ' (' . $field['name'] . ')';
                                                }
                                            }
                                        }
                                    }
                                }
                                $current_required = get_option('ggt_import_acf_required_field');
                                ?>
                                <select name="ggt_import_acf_required_field" style="width: 300px;">
                                    <option value="">-- Select Field --</option>
                                    <?php foreach ($acf_fields as $name => $label): ?>
                                        <option value="<?php echo esc_attr($name); ?>" <?php selected($current_required, $name); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the ACF true/false field for "Is Required".</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">ACF "Related Products" Field</th>
                            <td>
                                <?php
                                $acf_relationship_fields = array();
                                if (function_exists('acf_get_field_groups')) {
                                    $field_groups = acf_get_field_groups(array('post_type' => 'product'));
                                    foreach ($field_groups as $group) {
                                        $fields = acf_get_fields($group['key']);
                                        if ($fields) {
                                            foreach ($fields as $field) {
                                                if ($field['type'] === 'relationship') {
                                                    $acf_relationship_fields[$field['name']] = $field['label'] . ' (' . $field['name'] . ')';
                                                }
                                            }
                                        }
                                    }
                                }
                                $current_related = get_option('ggt_import_acf_related_field');
                                ?>
                                <select name="ggt_import_acf_related_field" style="width: 300px;">
                                    <option value="">-- Select Field --</option>
                                    <?php foreach ($acf_relationship_fields as $name => $label): ?>
                                        <option value="<?php echo esc_attr($name); ?>" <?php selected($current_related, $name); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the ACF relationship field for "Related Products".</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Replace Existing Featured Image</th>
                            <td>
                                <label for="ggt_replace_existing_image">
                                    <input type="checkbox" id="ggt_replace_existing_image" name="ggt_replace_existing_image" value="1" <?php checked(1, get_option('ggt_replace_existing_image'), true); ?> />
                                    Always replace existing featured image during import/sync
                                </label>
                                <p class="description">When unchecked, products that already have a featured image keep it even if the mapped image URL changes. New products always get an image if provided.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

        </div>

        <!-- Flexible Import Modal -->
        <div id="flexible-import-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:999999;">
            <div style="position:relative; width:90%; max-width:1200px; margin:50px auto; background:#fff; border-radius:8px; max-height:90vh; overflow-y:auto;">
                <div style="padding:20px; border-bottom:1px solid #ddd;">
                    <h2 style="margin:0;">Configure Product Field Mapping</h2>
                    <button id="close-modal" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
                </div>
                
                <div id="modal-content" style="padding:20px;">
                    <!-- Step 1: Preview -->
                    <div id="step-preview" class="import-step">
                        <h3>Step 1: Preview API Data</h3>
                        <p>Loading sample products from API...</p>
                        <button type="button" id="load-preview" class="button button-primary">Load Preview</button>
                        <div id="preview-results" style="margin-top:15px;"></div>
                    </div>

                    <!-- Step 2: Field Mapping -->
                    <div id="step-mapping" class="import-step" style="display:none;">
                        <h3>Step 2: Map Fields</h3>
                        <p>Map API fields (left) to WooCommerce fields (right)</p>
                        
                        <div style="margin-bottom:15px;">
                            <button type="button" id="auto-map-fields" class="button">Auto-Map Common Fields</button>
                            <button type="button" id="clear-mapping" class="button">Clear All Mappings</button>
                            <button type="button" id="save-mapping" class="button button-primary">Save Mapping</button>
                            <span style="margin-left:20px;">
                                <button type="button" id="enable-all-fields" class="button">Enable All</button>
                                <button type="button" id="disable-all-fields" class="button">Disable All</button>
                            </span>
                        </div>
                        
                        <div id="field-mapping-container" style="border:1px solid #ddd; padding:15px; background:#f9f9f9; max-height:400px; overflow-y:auto;">
                            <!-- Dynamic mapping rows will be inserted here -->
                        </div>
                        
                        <div style="margin-top:15px;">
                            <button type="button" id="next-to-analysis" class="button button-primary">Next: Analyze Import</button>
                        </div>
                    </div>

                    <!-- Step 3: Analysis -->
                    <div id="step-analysis" class="import-step" style="display:none;">
                        <h3>Step 3: Analyze Import</h3>
                        <p>Review what will be imported...</p>
                        <button type="button" id="back-to-mapping" class="button">← Back to Mapping</button>
                        <button type="button" id="run-analysis" class="button button-primary">Run Analysis</button>
                        <div id="analysis-results" style="margin-top:15px;"></div>
                        
                        <div style="margin-top:15px; display:none;" id="execute-section">
                            <button type="button" id="execute-import" class="button button-primary">Execute Import</button>
                        </div>
                    </div>

                    <!-- Step 4: Results -->
                    <div id="step-results" class="import-step" style="display:none;">
                        <h3>Import Complete</h3>
                        <div id="import-results"></div>
                        <button type="button" id="close-results" class="button">Close</button>
                    </div>
                </div>
            </div>
        </div>

                <!-- User Mapping Modal -->
                <div id="user-mapping-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:999999;">
                    <div style="position:relative; width:90%; max-width:1000px; margin:50px auto; background:#fff; border-radius:8px; max-height:90vh; overflow-y:auto;">
                        <div style="padding:20px; border-bottom:1px solid #ddd;">
                            <h2 style="margin:0;">Configure User/Account Field Mapping</h2>
                            <button id="close-user-mapping-modal" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
                        </div>
                        <div style="padding:20px;">
                            <p>Map API user/account fields (left) to WordPress/WooCommerce targets (right). Use the checkbox to enable which fields are active and visible on registration.</p>
                            <div style="margin-bottom:15px;">
                                <button type="button" id="user-enable-all" class="button">Enable All</button>
                                <button type="button" id="user-disable-all" class="button">Disable All</button>
                                <button type="button" id="user-save-mapping" class="button button-primary" style="float:right;">Save Mapping</button>
                            </div>
                            <div id="user-field-mapping-container" style="border:1px solid #ddd; padding:15px; background:#f9f9f9; max-height:500px; overflow-y:auto;"></div>
                        </div>
                    </div>
                </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // --- Simple tab navigation ---
                (function(){
                    const nav = document.getElementById('ggt-tabs-nav');
                    if (!nav) return;
                    const tabs = nav.querySelectorAll('.nav-tab');
                    const panels = {
                        '#dashboard': document.getElementById('ggt-tab-dashboard'),
                        '#auth': document.getElementById('ggt-tab-auth'),
                        '#products': document.getElementById('ggt-tab-products'),
                        '#users': document.getElementById('ggt-tab-users'),
                        '#settings': document.getElementById('ggt-tab-settings')
                    };
                    function activate(hash){
                        const target = panels[hash] ? hash : '#dashboard';
                        Object.keys(panels).forEach(h => { if(panels[h]) panels[h].style.display = (h===target)?'block':'none'; });
                        tabs.forEach(t => { t.classList.toggle('nav-tab-active', t.getAttribute('href')===target); });
                        if (history && history.replaceState) history.replaceState(null, '', target);
                    }
                    tabs.forEach(t => t.addEventListener('click', function(e){ e.preventDefault(); activate(this.getAttribute('href')); }));
                    activate(location.hash || '#dashboard');
                })();

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
                                                // Switch to Products tab after authentication
                                                const tab = document.querySelector('#ggt-tabs-nav a[href="#products"]');
                                                if (tab) tab.click();
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
                                            // Store summary for dashboard
                                            jQuery.post(ajaxurl, {
                                                action: 'ggt_store_last_user_sync',
                                                updated: successCount,
                                                failed: errorCount,
                                                total: total
                                            });
                                            
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
                                                // Store summary for dashboard
                                                jQuery.post(ajaxurl, {
                                                    action: 'ggt_store_last_product_sync',
                                                    updated: successCount,
                                                    skipped: skipCount,
                                                    total: total
                                                });
                                                
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
                                    // Navigate to Authentication tab
                                    const tab = document.querySelector('#ggt-tabs-nav a[href="#auth"]');
                                    if (tab) tab.click();
                                } else {
                                    document.getElementById('message').innerText = 'Failed to reset token!';
                                }
                            })
                            .catch(error => {
                                document.getElementById('message').innerText = 'An error occurred: ' + error.message;
                            });
                    }
                });

                // Flexible Import Modal Functionality
                let availableFields = [];
                let availableAPIFields = [];
                let currentMapping = {};
                let enabledFields = {};
                // User mapping state
                let userAvailableTargets = [];
                let userAvailableApiFields = [];
                let userCurrentMapping = {};
                let userEnabledFields = {};
                let userCustomLabels = {};

                document.getElementById('configure-import-button').addEventListener('click', function() {
                    document.getElementById('flexible-import-modal').style.display = 'block';
                    document.getElementById('step-preview').style.display = 'block';
                    document.getElementById('step-mapping').style.display = 'none';
                    document.getElementById('step-analysis').style.display = 'none';
                    document.getElementById('step-results').style.display = 'none';
                });

                document.getElementById('close-modal').addEventListener('click', function() {
                    document.getElementById('flexible-import-modal').style.display = 'none';
                });

                // Open User Mapping modal and load data
                const openUserMapping = function() {
                    document.getElementById('user-mapping-modal').style.display = 'block';
                    Promise.all([
                        jQuery.post(ajaxurl, { action: 'ggt_users_get_api_fields' }),
                        jQuery.post(ajaxurl, { action: 'ggt_users_get_available_fields' }),
                        jQuery.post(ajaxurl, { action: 'ggt_users_get_field_mapping' })
                    ]).then(([apiRes, targetsRes, mapRes]) => {
                        if (apiRes.success) {
                            userAvailableApiFields = apiRes.data.map(item => item.key);
                        }
                        if (targetsRes.success) {
                            userAvailableTargets = targetsRes.data;
                        }
                        if (mapRes.success && mapRes.data) {
                            userCurrentMapping = mapRes.data.mapping || {};
                            userEnabledFields = mapRes.data.enabled_fields || {};
                            userCustomLabels = mapRes.data.custom_labels || {};
                            if (Array.isArray(userCurrentMapping)) userCurrentMapping = {};
                            if (Array.isArray(userEnabledFields)) userEnabledFields = {};
                            if (Array.isArray(userCustomLabels)) userCustomLabels = {};
                        }
                        renderUserFieldMapping();
                    });
                };

                const configureUserBtn = document.getElementById('configure-user-mapping-button');
                if (configureUserBtn) {
                    configureUserBtn.addEventListener('click', openUserMapping);
                }
                const closeUserModalBtn = document.getElementById('close-user-mapping-modal');
                if (closeUserModalBtn) {
                    closeUserModalBtn.addEventListener('click', function(){
                        document.getElementById('user-mapping-modal').style.display = 'none';
                    });
                }

                function renderUserFieldMapping() {
                    let html = '<table class="wp-list-table widefat fixed"><thead><tr>' +
                               '<th style="width:10%;">Enable</th>' +
                               '<th style="width:30%;">API Field</th>' +
                               '<th style="width:30%;">Target Field</th>' +
                               '<th style="width:20%;">Custom Label</th>' +
                               '<th style="width:10%;">Action</th>' +
                               '</tr></thead><tbody>';

                    userAvailableApiFields.forEach(apiField => {
                        const mappedTo = userCurrentMapping[apiField] || '';
                        const isEnabled = userEnabledFields[apiField] !== false; // default true
                        const customLabel = userCustomLabels[apiField] || '';
                        html += '<tr>' +
                                '<td style="text-align:center;"><input type="checkbox" class="user-field-enabled" data-api-field="' + apiField + '" ' + (isEnabled ? 'checked' : '') + '></td>' +
                                '<td><strong>' + apiField + '</strong></td>' +
                                '<td><select class="user-field-mapping" data-api-field="' + apiField + '" style="width:100%">' +
                                '<option value="">-- Not Mapped --</option>';
                        let currentGroup = '';
                        userAvailableTargets.forEach(field => {
                            if (field.group !== currentGroup) {
                                if (currentGroup) html += '</optgroup>';
                                html += '<optgroup label="' + field.group + '">';
                                currentGroup = field.group;
                            }
                            const selected = (mappedTo === field.value) ? 'selected' : '';
                            html += '<option value="' + field.value + '" ' + selected + '>' + field.label + '</option>';
                        });
                        html += '</optgroup></select></td>' +
                                '<td><input type="text" class="user-field-label" data-api-field="' + apiField + '" value="' + customLabel + '" placeholder="Default Label" style="width:100%"></td>' +
                                '<td><button type="button" class="button user-clear-map" data-api-field="' + apiField + '">Clear</button></td>' +
                                '</tr>';
                    });

                    html += '</tbody></table>';
                    const container = document.getElementById('user-field-mapping-container');
                    if (container) container.innerHTML = html;

                    document.querySelectorAll('.user-field-enabled').forEach(cb => {
                        cb.addEventListener('change', function() {
                            const k = this.getAttribute('data-api-field');
                            userEnabledFields[k] = this.checked;
                        });
                    });
                    document.querySelectorAll('.user-field-mapping').forEach(sel => {
                        sel.addEventListener('change', function() {
                            const k = this.getAttribute('data-api-field');
                            const v = this.value;
                            if (v) {
                                userCurrentMapping[k] = v;
                                if (!userEnabledFields.hasOwnProperty(k)) userEnabledFields[k] = true;
                            } else {
                                delete userCurrentMapping[k];
                            }
                        });
                    });
                    document.querySelectorAll('.user-field-label').forEach(inp => {
                        inp.addEventListener('change', function() {
                            const k = this.getAttribute('data-api-field');
                            userCustomLabels[k] = this.value;
                        });
                    });
                    document.querySelectorAll('.user-clear-map').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const k = this.getAttribute('data-api-field');
                            delete userCurrentMapping[k];
                            delete userCustomLabels[k];
                            renderUserFieldMapping();
                        });
                    });
                }

                const userEnableAllBtn = document.getElementById('user-enable-all');
                if (userEnableAllBtn) userEnableAllBtn.addEventListener('click', function(){
                    userAvailableApiFields.forEach(k => userEnabledFields[k] = true);
                    renderUserFieldMapping();
                });
                const userDisableAllBtn = document.getElementById('user-disable-all');
                if (userDisableAllBtn) userDisableAllBtn.addEventListener('click', function(){
                    userAvailableApiFields.forEach(k => userEnabledFields[k] = false);
                    renderUserFieldMapping();
                });
                const userSaveBtn = document.getElementById('user-save-mapping');
                if (userSaveBtn) userSaveBtn.addEventListener('click', function(){
                    jQuery.post(ajaxurl, {
                        action: 'ggt_users_save_field_mapping',
                        mapping: JSON.stringify(userCurrentMapping),
                        enabled_fields: JSON.stringify(userEnabledFields),
                        custom_labels: JSON.stringify(userCustomLabels)
                    }).done(function(res){
                        if (res.success) {
                            alert('User mapping saved.');
                        } else {
                            alert('Error saving user mapping: ' + (res.data || 'Unknown error'));
                        }
                    });
                });

                document.getElementById('load-preview').addEventListener('click', function() {
                    document.getElementById('preview-results').innerHTML = '<p>Loading...</p>';
                    
                    Promise.all([
                        jQuery.post(ajaxurl, { action: 'ggt_preview_import' }),
                        jQuery.post(ajaxurl, { action: 'ggt_get_available_fields' }),
                        jQuery.post(ajaxurl, { action: 'ggt_get_field_mapping' })
                    ]).then(([previewRes, fieldsRes, mappingRes]) => {
                        if (previewRes.success && fieldsRes.success) {
                            availableFields = fieldsRes.data;
                            availableAPIFields = previewRes.data.available_fields;
                            
                            if (mappingRes.success && mappingRes.data) {
                                // Ensure we get an object, not an array
                                if (mappingRes.data.mapping) {
                                    currentMapping = mappingRes.data.mapping;
                                } else {
                                    currentMapping = mappingRes.data;
                                }
                                
                                // Force currentMapping to be an object if it's an array
                                if (Array.isArray(currentMapping)) {
                                    currentMapping = {};
                                }
                                
                                if (mappingRes.data.enabled_fields) {
                                    enabledFields = mappingRes.data.enabled_fields;
                                } else {
                                    // Default all to enabled
                                    enabledFields = {};
                                    Object.keys(currentMapping).forEach(key => {
                                        enabledFields[key] = true;
                                    });
                                }
                                
                                // Force enabledFields to be an object if it's an array
                                if (Array.isArray(enabledFields)) {
                                    enabledFields = {};
                                }
                            }

                            let html = '<div style="border:1px solid #ddd; padding:10px; background:#fff;">';
                            // Pivoted preview: rows are fields; columns are first 3 samples
                            const samples = (previewRes.data.products || []).slice(0, 3);
                            const sampleCount = samples.length;
                            html += '<h4>Field values for first ' + sampleCount + ' product(s)</h4>';
                            html += '<div style="overflow-x:auto; max-width:100%;">';
                            html += '<table class="wp-list-table widefat fixed striped" style="min-width:720px;">';
                            html += '<thead><tr>';
                            html += '<th style="width:280px;">Field</th>';
                            for (let i = 0; i < sampleCount; i++) {
                                html += '<th>Sample ' + (i + 1) + '</th>';
                            }
                            html += '</tr></thead><tbody>';

                            availableAPIFields.forEach(field => {
                                html += '<tr>';
                                html += '<td><code>' + field + '</code></td>';
                                for (let i = 0; i < sampleCount; i++) {
                                    const product = samples[i] || {};
                                    let value = product[field];
                                    if (value === undefined || value === null) value = '';
                                    if (typeof value === 'object') value = JSON.stringify(value);
                                    const safeTitle = String(value).replace(/\"/g, '&quot;');
                                    html += '<td style="max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="' + safeTitle + '">' + String(value) + '</td>';
                                }
                                html += '</tr>';
                            });

                            html += '</tbody></table>';
                            html += '</div>';
                            html += '</div>';
                            html += '<p style="margin-top:15px;"><button type="button" id="proceed-to-mapping" class="button button-primary">Next: Configure Field Mapping</button></p>';
                            
                            document.getElementById('preview-results').innerHTML = html;
                            
                            document.getElementById('proceed-to-mapping').addEventListener('click', function() {
                                showMappingStep();
                            });
                        } else {
                            document.getElementById('preview-results').innerHTML = '<p style="color:red;">Error loading preview</p>';
                        }
                    });
                });

                function showMappingStep() {
                    document.getElementById('step-preview').style.display = 'none';
                    document.getElementById('step-mapping').style.display = 'block';
                    
                    renderFieldMapping();
                }

                function renderFieldMapping() {
                    let html = '<table class="wp-list-table widefat fixed"><thead><tr>';
                    html += '<th style="width:5%;">Enable</th>';
                    html += '<th style="width:35%;">API Field</th>';
                    html += '<th style="width:40%;">WooCommerce Field</th>';
                    html += '<th style="width:20%;">Action</th>';
                    html += '</tr></thead><tbody>';
                    
                    availableAPIFields.forEach(apiField => {
                        let mappedTo = currentMapping[apiField] || '';
                        let isEnabled = enabledFields[apiField] !== false;
                        
                        html += '<tr>';
                        html += '<td style="text-align:center;"><input type="checkbox" class="field-enabled-checkbox" data-api-field="' + apiField + '" ' + (isEnabled ? 'checked' : '') + '></td>';
                        html += '<td><strong>' + apiField + '</strong></td>';
                        html += '<td><select class="field-mapping-select" data-api-field="' + apiField + '" style="width:100%;">';
                        html += '<option value="">-- Not Mapped --</option>';
                        
                        let currentGroup = '';
                        availableFields.forEach(field => {
                            if (field.group !== currentGroup) {
                                if (currentGroup) html += '</optgroup>';
                                html += '<optgroup label="' + field.group + '">';
                                currentGroup = field.group;
                            }
                            let selected = (mappedTo === field.value) ? 'selected' : '';
                            html += '<option value="' + field.value + '" ' + selected + '>' + field.label + '</option>';
                        });
                        
                        html += '</optgroup></select></td>';
                        html += '<td><button type="button" class="button clear-field-map" data-api-field="' + apiField + '">Clear</button></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    document.getElementById('field-mapping-container').innerHTML = html;
                    
                    // Attach event listeners
                    document.querySelectorAll('.field-enabled-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            let apiField = this.getAttribute('data-api-field');
                            enabledFields[apiField] = this.checked;
                        });
                    });
                    
                    document.querySelectorAll('.field-mapping-select').forEach(select => {
                        select.addEventListener('change', function() {
                            let apiField = this.getAttribute('data-api-field');
                            let wcField = this.value;
                            if (wcField) {
                                currentMapping[apiField] = wcField;
                                // Auto-enable when mapping is set
                                if (!enabledFields.hasOwnProperty(apiField)) {
                                    enabledFields[apiField] = true;
                                }
                            } else {
                                delete currentMapping[apiField];
                            }
                        });
                    });
                    
                    document.querySelectorAll('.clear-field-map').forEach(btn => {
                        btn.addEventListener('click', function() {
                            let apiField = this.getAttribute('data-api-field');
                            delete currentMapping[apiField];
                            renderFieldMapping();
                        });
                    });
                }

                document.getElementById('auto-map-fields').addEventListener('click', function() {
                    // Auto-map common field names
                    const autoMappings = {
                        'stockCode': '_stockCode',
                        'description': 'description',
                        'salesPrice': 'regular_price',
                        'qtyInStock': 'stock_quantity',
                        'sku': 'sku',
                        'category': 'category',
                        'webDescription': 'short_description',
                        'image_path': 'image_url',
                        'unitWeight': 'weight'
                    };
                    
                    Object.keys(autoMappings).forEach(apiField => {
                        if (availableAPIFields.includes(apiField)) {
                            currentMapping[apiField] = autoMappings[apiField];
                        }
                    });
                    
                    renderFieldMapping();
                    alert('Common fields have been auto-mapped. Review and adjust as needed.');
                });

                document.getElementById('clear-mapping').addEventListener('click', function() {
                    if (confirm('Clear all field mappings?')) {
                        currentMapping = {};
                        renderFieldMapping();
                    }
                });

                document.getElementById('enable-all-fields').addEventListener('click', function() {
                    availableAPIFields.forEach(apiField => {
                        enabledFields[apiField] = true;
                    });
                    renderFieldMapping();
                });

                document.getElementById('disable-all-fields').addEventListener('click', function() {
                    availableAPIFields.forEach(apiField => {
                        enabledFields[apiField] = false;
                    });
                    renderFieldMapping();
                });

                document.getElementById('save-mapping').addEventListener('click', function() {
                    jQuery.post(ajaxurl, {
                        action: 'ggt_save_field_mapping',
                        mapping: JSON.stringify(currentMapping),
                        enabled_fields: JSON.stringify(enabledFields)
                    }).done(function(response) {
                        if (response.success) {
                            alert('Field mapping saved successfully!');
                        } else {
                            alert('Error saving mapping: ' + (response.data || 'Unknown error'));
                        }
                    });
                });

                document.getElementById('next-to-analysis').addEventListener('click', function() {
                    if (Object.keys(currentMapping).length === 0) {
                        alert('Please map at least one field before proceeding.');
                        return;
                    }
                    
                    console.log('Saving mapping:', currentMapping);
                    console.log('Enabled fields:', enabledFields);
                    console.log('Mapping count:', Object.keys(currentMapping).length);
                    
                    // Show saving indicator
                    let originalText = this.textContent;
                    this.textContent = 'Saving...';
                    this.disabled = true;
                    
                    // Save mapping first - send as JSON strings
                    jQuery.post(ajaxurl, {
                        action: 'ggt_save_field_mapping',
                        mapping: JSON.stringify(currentMapping),
                        enabled_fields: JSON.stringify(enabledFields)
                    }).done(function(response) {
                        console.log('Save response:', response);
                        if (response.success) {
                            console.log('Saved mapping:', response.data.saved_mapping);
                            console.log('Saved enabled:', response.data.saved_enabled);
                            console.log('Received POST:', response.data.received_post);
                            document.getElementById('step-mapping').style.display = 'none';
                            document.getElementById('step-analysis').style.display = 'block';
                        } else {
                            alert('Error saving mapping: ' + (response.data || 'Unknown error'));
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('Save failed:', status, error);
                        console.error('Response:', xhr.responseText);
                        alert('Failed to save mapping. Please check console for details.');
                    }).always(function() {
                        document.getElementById('next-to-analysis').textContent = originalText;
                        document.getElementById('next-to-analysis').disabled = false;
                    });
                });

                document.getElementById('back-to-mapping').addEventListener('click', function() {
                    document.getElementById('step-analysis').style.display = 'none';
                    document.getElementById('step-mapping').style.display = 'block';
                });

                document.getElementById('run-analysis').addEventListener('click', function() {
                    document.getElementById('analysis-results').innerHTML = '<p>Analyzing...</p>';
                    
                    jQuery.post(ajaxurl, {
                        action: 'ggt_analyze_import'
                    }).done(function(response) {
                        if (response.success) {
                            let html = '<div style="border:1px solid #ddd; padding:15px; background:#fff;">';
                            html += '<h4>Analysis Results</h4>';
                            html += '<p><strong>Total Products:</strong> ' + response.data.total + '</p>';
                            html += '<p><strong>Existing (will update):</strong> ' + response.data.existing + '</p>';
                            html += '<p><strong>New (will create):</strong> ' + response.data.new + '</p>';
                            
                            if (response.data.warnings.length > 0) {
                                html += '<div style="background:#fff3cd; border:1px solid #ffc107; padding:10px; margin-top:10px;">';
                                html += '<strong>Warnings:</strong><ul>';
                                response.data.warnings.forEach(warning => {
                                    html += '<li>' + warning + '</li>';
                                });
                                html += '</ul></div>';
                            }
                            
                            html += '</div>';
                            document.getElementById('analysis-results').innerHTML = html;
                            document.getElementById('execute-section').style.display = 'block';
                        } else {
                            document.getElementById('analysis-results').innerHTML = '<p style="color:red;">Error: ' + (response.data || 'Unknown error') + '</p>';
                        }
                    });
                });

                document.getElementById('execute-import').addEventListener('click', function() {
                    if (!confirm('Execute import? This will create/update products based on your field mapping.')) {
                        return;
                    }
                    
                    document.getElementById('analysis-results').innerHTML = '<p>Importing products... This may take a few minutes.</p>';
                    this.disabled = true;
                    
                    jQuery.post(ajaxurl, {
                        action: 'ggt_execute_flexible_import'
                    }).done(function(response) {
                        if (response.success) {
                            // Main import complete, now process related products
                            document.getElementById('analysis-results').innerHTML = '<p>Processing related products...</p>';
                            
                            jQuery.post(ajaxurl, {
                                action: 'ggt_process_related_products'
                            }).done(function(relatedRes) {
                                document.getElementById('step-analysis').style.display = 'none';
                                document.getElementById('step-results').style.display = 'block';
                                
                                let html = '<div style="border:1px solid #4caf50; padding:15px; background:#e8f5e9;">';
                                html += '<h4 style="color:#4caf50;">✓ Import Complete!</h4>';
                                html += '<p><strong>Created:</strong> ' + response.data.created + '</p>';
                                html += '<p><strong>Updated:</strong> ' + response.data.updated + '</p>';
                                html += '<p><strong>Skipped:</strong> ' + response.data.skipped + '</p>';
                                
                                if (relatedRes.success && relatedRes.data) {
                                    html += '<p><strong>Related Products Processed:</strong> ' + relatedRes.data.processed + '</p>';
                                    html += '<p><strong>Related Products Updated:</strong> ' + relatedRes.data.updated + '</p>';
                                    
                                    if (relatedRes.data.errors && relatedRes.data.errors.length > 0) {
                                        html += '<div style="background:#fff3cd; border:1px solid #ffc107; padding:10px; margin-top:10px;">';
                                        html += '<strong>Related Products Warnings:</strong><ul>';
                                        relatedRes.data.errors.slice(0, 5).forEach(error => {
                                            html += '<li>' + error + '</li>';
                                        });
                                        if (relatedRes.data.errors.length > 5) {
                                            html += '<li>... and ' + (relatedRes.data.errors.length - 5) + ' more warnings</li>';
                                        }
                                        html += '</ul></div>';
                                    }
                                }
                                
                                if (response.data.errors.length > 0) {
                                    html += '<div style="background:#fff3cd; border:1px solid #ffc107; padding:10px; margin-top:10px;">';
                                    html += '<strong>Import Errors:</strong><ul>';
                                    response.data.errors.slice(0, 10).forEach(error => {
                                        html += '<li>' + error + '</li>';
                                    });
                                    if (response.data.errors.length > 10) {
                                        html += '<li>... and ' + (response.data.errors.length - 10) + ' more errors</li>';
                                    }
                                    html += '</ul></div>';
                                }
                                
                                html += '</div>';
                                document.getElementById('import-results').innerHTML = html;
                            }).fail(function() {
                                // Show main import results even if related products processing fails
                                document.getElementById('step-analysis').style.display = 'none';
                                document.getElementById('step-results').style.display = 'block';
                                
                                let html = '<div style="border:1px solid #4caf50; padding:15px; background:#e8f5e9;">';
                                html += '<h4 style="color:#4caf50;">✓ Import Complete!</h4>';
                                html += '<p><strong>Created:</strong> ' + response.data.created + '</p>';
                                html += '<p><strong>Updated:</strong> ' + response.data.updated + '</p>';
                                html += '<p><strong>Skipped:</strong> ' + response.data.skipped + '</p>';
                                html += '<p style="color:orange;"><strong>Warning:</strong> Related products processing failed</p>';
                                html += '</div>';
                                document.getElementById('import-results').innerHTML = html;
                            });
                        } else {
                            document.getElementById('step-analysis').style.display = 'none';
                            document.getElementById('step-results').style.display = 'block';
                            document.getElementById('import-results').innerHTML = '<p style="color:red;">Import failed: ' + (response.data || 'Unknown error') + '</p>';
                        }
                    }).fail(function() {
                        document.getElementById('step-analysis').style.display = 'none';
                        document.getElementById('step-results').style.display = 'block';
                        document.getElementById('import-results').innerHTML = '<p style="color:red;">Import request failed</p>';
                    });
                });

                document.getElementById('close-results').addEventListener('click', function() {
                    document.getElementById('flexible-import-modal').style.display = 'none';
                    location.reload(); // Refresh to show new products
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
    
    public function display_logs_page()
    {
        $log_file = GGT_SINAPPSUS_PLUGIN_PATH . '/logs/debug.log';
        $log_content = '';
        $log_size = 0;
        
        // Handle clear logs action
        if (isset($_POST['clear_logs']) && check_admin_referer('ggt_clear_logs')) {
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
                echo '<div class="notice notice-success"><p>Logs cleared successfully.</p></div>';
            }
        }
        
        // Read log file
        if (file_exists($log_file)) {
            $log_size = filesize($log_file);
            $log_content = file_get_contents($log_file);
        }
        
        ?>
        <div class="wrap">
            <h1>Debug Logs</h1>
            
            <div style="margin: 20px 0;">
                <p><strong>Log File:</strong> <?php echo esc_html($log_file); ?></p>
                <p><strong>Size:</strong> <?php echo esc_html(size_format($log_size)); ?></p>
                
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('ggt_clear_logs'); ?>
                    <button type="submit" name="clear_logs" class="button button-secondary" onclick="return confirm('Are you sure you want to clear all logs?');">Clear Logs</button>
                </form>
                
                <button type="button" class="button" onclick="location.reload();">Refresh</button>
            </div>
            
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px;">
                <?php if (!empty($log_content)): ?>
                    <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($log_content); ?></pre>
                <?php else: ?>
                    <p style="color: #888;">No logs yet. Logs will appear here when the import runs.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

add_action('admin_init', 'ggt_sinappsus_register_settings');



function ggt_sinappsus_register_settings()
{
    // Settings tab (registration & import)
    register_setting('ggt_sinappsus_settings_group', 'ggt_enable_additional_registration_fields');
    register_setting('ggt_sinappsus_settings_group', 'ggt_registration_two_columns');
    register_setting('ggt_sinappsus_settings_group', 'ggt_import_enable_acf_relate');
    register_setting('ggt_sinappsus_settings_group', 'ggt_import_acf_required_field');
    register_setting('ggt_sinappsus_settings_group', 'ggt_import_acf_related_field');
    register_setting('ggt_sinappsus_settings_group', 'ggt_replace_existing_image');
    register_setting('ggt_sinappsus_settings_group', 'ggt_account_not_found_email');

    // Dashboard tab
    register_setting('ggt_sinappsus_dashboard_group', 'ggt_plugin_enabled');

    // Authentication tab
    register_setting('ggt_sinappsus_auth_group', 'ggt_sinappsus_environment');
    register_setting('ggt_sinappsus_auth_group', 'ggt_sinappsus_email');
    register_setting('ggt_sinappsus_auth_group', 'ggt_sinappsus_password');
}

add_action('wp_ajax_clear_all_products', 'clear_all_products');
add_action('wp_ajax_get_all_products', 'get_all_products');
add_action('wp_ajax_update_product', 'update_product');
add_action('wp_ajax_create_product', 'create_product');
add_action('wp_ajax_sync_user', 'sync_user');
add_action('wp_ajax_delete_all_users', 'delete_all_users');
add_action('wp_ajax_reset_token', 'reset_token');
// Store last sync/import summaries
add_action('wp_ajax_ggt_store_last_user_sync', 'ggt_store_last_user_sync');
add_action('wp_ajax_ggt_store_last_product_sync', 'ggt_store_last_product_sync');
// Dismiss admin auth notice
add_action('wp_ajax_ggt_dismiss_auth_notice', 'ggt_dismiss_auth_notice');

function reset_token()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }

    delete_option('sinappsus_gogeo_codex');
    wp_send_json_success();
}

function ggt_store_last_user_sync() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    $updated = isset($_POST['updated']) ? intval($_POST['updated']) : 0;
    $failed = isset($_POST['failed']) ? intval($_POST['failed']) : 0;
    $total = isset($_POST['total']) ? intval($_POST['total']) : ($updated + $failed);
    update_option('ggt_last_user_sync', array(
        'updated' => $updated,
        'failed' => $failed,
        'total' => $total,
        'timestamp' => time(),
    ));
    wp_send_json_success();
}

function ggt_store_last_product_sync() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    $updated = isset($_POST['updated']) ? intval($_POST['updated']) : 0;
    $skipped = isset($_POST['skipped']) ? intval($_POST['skipped']) : 0;
    $total = isset($_POST['total']) ? intval($_POST['total']) : ($updated + $skipped);
    update_option('ggt_last_product_import', array(
        'created' => 0, // unknown for sync flow
        'updated' => $updated,
        'skipped' => $skipped,
        'timestamp' => time(),
    ));
    wp_send_json_success();
}

function ggt_dismiss_auth_notice() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    delete_option('ggt_auth_required');
    wp_send_json_success();
}



function clear_all_products()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!get_option('ggt_plugin_enabled', 1)) {
        wp_send_json_error('Plugin is disabled', 403);
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
    if (!get_option('ggt_plugin_enabled', 1)) {
        wp_send_json_error('Plugin is disabled', 403);
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

    // Update other meta data (including isRequired for grouping)
    foreach ($product_data as $key => $value) {
        if (!in_array($key, ['sku', 'salesPrice', 'description', 'stockCode', 'category', 'image_path', 'webDescription'])) {
            $product->update_meta_data($key, $value);
        }
    }

    $product->save();
    
    // Optionally relate products via ACF after product is saved
    relate_products_via_acf($product->get_id(), $product_data);
    
    // Set featured image after product is saved (needs product ID)
    if (!empty($product_data['image_path'])) {
        set_product_featured_image_from_url($product->get_id(), $product_data['image_path']);
    }
    
    wp_send_json_success();
}

function update_product()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!get_option('ggt_plugin_enabled', 1)) {
        wp_send_json_error('Plugin is disabled', 403);
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

        // Update other meta data (including isRequired for grouping)
        foreach ($product_data as $key => $value) {
            if (!in_array($key, ['sku', 'salesPrice', 'description', 'stockCode', 'category', 'image_path', 'webDescription'])) {
                $product->update_meta_data($key, $value);
            }
        }

        $product->save();
        
    // Optionally relate products via ACF after product is saved
    relate_products_via_acf($product->get_id(), $product_data);
        
        // Set featured image after product is saved (respect replace setting)
        if (!empty($product_data['image_path'])) {
            $replace = (int) get_option('ggt_replace_existing_image', 0);
            $has_existing = has_post_thumbnail($product_id);
            if ($has_existing && !$replace) {
                ggt_log("Product {$product_id}: Skipped featured image replacement (option disabled)", 'IMPORT');
            } else {
                set_product_featured_image_from_url($product->get_id(), $product_data['image_path']);
            }
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
    if (!get_option('ggt_plugin_enabled', 1)) {
        wp_send_json_error('Plugin is disabled', 403);
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

    // Apply mapping to user data (map-only semantics) and persist via helper
    if (function_exists('ggt_apply_user_field_mapping')) {
        $mapped = ggt_apply_user_field_mapping($user_data);
        if (function_exists('ggt_update_user_targets')) {
            ggt_update_user_targets($user_id, $mapped);
        } else {
            foreach ($mapped as $targetKey => $val) {
                update_user_meta($user_id, $targetKey, $val);
            }
        }
    }


    wp_send_json_success();
}

function delete_all_users()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!get_option('ggt_plugin_enabled', 1)) {
        wp_send_json_error('Plugin is disabled', 403);
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

// OLD FUNCTION REMOVED - Custom fields now display in "GoGeothermal Data" tab
// See includes/product-tab.php for the new implementation

// OLD SAVE FUNCTION REMOVED - Custom fields now saved via "GoGeothermal Data" tab
// See includes/product-tab.php for the new implementation
// END OF THE FUNCTIONALITY TO SHOW THE META DATA ON THE PRODUCT


// USER PROFILES AND REGISTER

add_action('show_user_profile', 'show_custom_user_profile_fields');
add_action('edit_user_profile', 'show_custom_user_profile_fields');

function show_custom_user_profile_fields($user)
{
    // Build profile UI from mapped fields only (show everything imported)
    $mapping = get_option('ggt_user_field_mapping', array());
    if (is_object($mapping)) { $mapping = (array)$mapping; }
    $catalog = function_exists('ggt_get_registration_fields_catalog') ? ggt_get_registration_fields_catalog() : array();

    // Compute targets to show (dedup by target), ignore enabled flags here
    $targets = array();
    foreach ($mapping as $apiKey => $targetKey) {
        if (empty($targetKey)) continue;
        if (!isset($targets[$targetKey])) {
            $label = isset($catalog[$apiKey]) ? $catalog[$apiKey] : $apiKey;
            $targets[$targetKey] = $label;
        }
    }

    if (empty($targets)) return;

    echo '<h3>Account Fields</h3>';
    echo '<table class="form-table">';
    foreach ($targets as $targetKey => $label) {
        // Determine current value for core vs meta keys
        $current = '';
        switch ($targetKey) {
            case 'user_email':
                $current = $user->user_email; break;
            case 'first_name':
                $current = get_user_meta($user->ID, 'first_name', true); break;
            case 'last_name':
                $current = get_user_meta($user->ID, 'last_name', true); break;
            case 'display_name':
                $current = $user->display_name; break;
            case 'nickname':
                $current = get_user_meta($user->ID, 'nickname', true); break;
            default:
                $current = get_user_meta($user->ID, $targetKey, true); break;
        }
        echo '<tr>';
        echo '<th><label for="' . esc_attr($targetKey) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="text" name="ggt_profile[' . esc_attr($targetKey) . ']" id="' . esc_attr($targetKey) . '" value="' . esc_attr($current) . '" class="regular-text" /></td>';
        echo '</tr>';
    }
    echo '</table>';
}

add_action('personal_options_update', 'save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'save_custom_user_profile_fields');

function save_custom_user_profile_fields($user_id)
{
    if (!isset($_POST['ggt_profile']) || !is_array($_POST['ggt_profile'])) return;
    $updates = array();
    foreach ($_POST['ggt_profile'] as $targetKey => $val) {
        $updates[$targetKey] = sanitize_text_field($val);
    }
    if (function_exists('ggt_update_user_targets')) {
        ggt_update_user_targets($user_id, $updates);
    } else {
        foreach ($updates as $k => $v) update_user_meta($user_id, $k, $v);
    }
}


// update the register form

add_action('register_form', 'add_custom_registration_fields');

function add_custom_registration_fields()
{
    // Respect master enable/disable toggle
    if (!get_option('ggt_plugin_enabled', 1)) {
        return;
    }
    if (!get_option('ggt_enable_additional_registration_fields')) {
        return;
    }
    // Pull from mapping catalog and enabled mask
    $catalog = function_exists('ggt_get_registration_fields_catalog') ? ggt_get_registration_fields_catalog() : [];
    $enabled = get_option('ggt_user_field_mapping_enabled', []);
    if (is_object($enabled)) { $enabled = (array)$enabled; }

    $two_cols = (bool) get_option('ggt_registration_two_columns');
    if ($two_cols) {
        // Force a 2-column grid and collapse to 1 column on small screens with a tiny inline style block
        echo '<style type="text/css">.ggt-registration-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px} @media (max-width:640px){.ggt-registration-grid{grid-template-columns:1fr}}</style>';
        echo '<div class="ggt-registration-grid">';
    }

    foreach ($catalog as $key => $label) {
        if (is_array($enabled) && array_key_exists($key, $enabled) && !$enabled[$key]) {
            continue;
        }
        echo '<p class="ggt-reg-field" style="margin:0 0 12px 0;">'
            . '<label for="' . esc_attr($key) . '">' . esc_html($label) . '</label><br>'
            . '<input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" class="input" value="' . esc_attr(wp_unslash($_POST[$key] ?? '')) . '" size="25" />'
            . '</p>';
    }

    if ($two_cols) {
        echo '</div>';
    }
}

add_action('user_register', 'save_custom_registration_fields');

function save_custom_registration_fields($user_id)
{
    if (!get_option('ggt_plugin_enabled', 1)) return;
    $catalog = function_exists('ggt_get_registration_fields_catalog') ? ggt_get_registration_fields_catalog() : [];
    $enabled = get_option('ggt_user_field_mapping_enabled', []);
    if (is_object($enabled)) { $enabled = (array)$enabled; }

    $user_data = [];
    foreach ($catalog as $key => $label) {
        if (is_array($enabled) && array_key_exists($key, $enabled) && !$enabled[$key]) {
            continue;
        }
        if (isset($_POST[$key])) {
            $val = sanitize_text_field($_POST[$key]);
            $user_data[$key] = $val;
        }
    }
    
    // Ensure primary email is included in API data
    if (isset($_POST['email'])) {
        $user_data['email'] = sanitize_email($_POST['email']);
    }

    // Apply user mapping to additionally set target fields
    if (function_exists('ggt_apply_user_field_mapping')) {
        $mapped = ggt_apply_user_field_mapping($user_data);
        if (function_exists('ggt_update_user_targets')) {
            ggt_update_user_targets($user_id, $mapped);
        } else {
            foreach ($mapped as $targetKey => $val) {
                update_user_meta($user_id, $targetKey, $val);
            }
        }
    }

    // Send user data to the API using centralized function
    ggt_sinappsus_connect_to_api('customers', $user_data, 'POST');
}
// END USER PROFILES AND REGISTER

// Admin notice when authentication is required
add_action('admin_notices', 'ggt_admin_auth_notice');
function ggt_admin_auth_notice() {
    if (!current_user_can('manage_options')) return;
    if (!get_option('ggt_auth_required')) return;
    $screen = get_current_screen();
    $link = admin_url('admin.php?page=sinappsus-ggt-settings#auth');
    echo '<div class="notice notice-error is-dismissible ggt-auth-notice"><p><strong>Go Geothermal:</strong> API calls are failing due to missing/expired authentication. Please <a href="' . esc_url($link) . '">re-authenticate on the Authentication tab</a>.</p></div>';
    // Add a small inline script to dismiss server-side
    echo '<script>document.addEventListener("click",function(e){if(e.target && e.target.closest(".ggt-auth-notice .notice-dismiss")){jQuery.post(ajaxurl,{action:"ggt_dismiss_auth_notice"});}});</script>';
}


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
function set_product_featured_image_from_url($product_id, $image_path) {
    if (empty($image_path)) {
        ggt_log("Empty image path for product {$product_id}", 'IMAGE');
        return false;
    }
    
    ggt_log("Starting image import for product {$product_id}, path: {$image_path}", 'IMAGE');
    
    // If image_path is relative, construct full URL
    if (!filter_var($image_path, FILTER_VALIDATE_URL)) {
        $api_base_url = ggt_get_api_base_url();

        // Derive host by stripping trailing /api from base (eg: https://api-staging.gogeothermal.uk)
        $api_host = rtrim($api_base_url, '/');
        $api_host = preg_replace('#/api$#', '', $api_host);

        // Normalize incoming relative path
        // - Our DB stores: products/product_....ext
        // - Legacy values may be: product-images/....ext
        $relative = ltrim($image_path, '/');
        if (strpos($relative, 'product-images/') === 0) {
            $relative = 'products/' . basename($relative); // map old folder to new and flatten to filename
        } elseif (basename($relative) === $relative) {
            // It's just a filename -> assume products/
            $relative = 'products/' . $relative;
        }

        // Build direct public storage URL served by Laravel storage symlink
        // Example: https://api-staging.gogeothermal.uk/storage/products/product_STOCKCODE_123.png
        $image_path = rtrim($api_host, '/') . '/storage/' . $relative;
        ggt_log("Constructed storage URL: {$image_path}", 'IMAGE');
    }
    
    // Check if the image already exists in media library
    $attachment_id = attachment_url_to_postid($image_path);
    
    if (!$attachment_id) {
        // Download and import the image
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        ggt_log("Downloading image from: {$image_path}", 'IMAGE');
        
        // First check if the URL is accessible
        $response = wp_remote_head($image_path);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
            ggt_log("ERROR: Image not accessible: {$error_msg}", 'IMAGE');
            return false;
        }
        
        $attachment_id = media_sideload_image($image_path, $product_id, null, 'id');
        
        if (is_wp_error($attachment_id)) {
            ggt_log("ERROR downloading image: " . $attachment_id->get_error_message(), 'IMAGE');
            return false;
        }
        
        ggt_log("Image imported successfully, attachment ID: {$attachment_id}", 'IMAGE');
    } else {
        ggt_log("Image already exists in library, attachment ID: {$attachment_id}", 'IMAGE');
    }
    
    // Set as featured image
    $result = set_post_thumbnail($product_id, $attachment_id);
    ggt_log("Set featured image result for product {$product_id}: " . ($result ? 'SUCCESS' : 'FAILED') . " (attachment {$attachment_id})", 'IMAGE');
    return $result;
}

// WooCommerce Grouped Product Functions
// function find_or_create_grouped_product($parent_product_id) {
//     if (empty($parent_product_id)) {
//         return null;
//     }
    
//     // Search for existing grouped product with this parent_product_id in meta
//     $existing_groups = get_posts(array(
//         'post_type' => 'product',
//         'meta_query' => array(
//             array(
//                 'key' => '_parent_product_id',
//                 'value' => $parent_product_id,
//                 'compare' => '='
//             )
//         ),
//         'post_status' => 'any',
//         'posts_per_page' => 1
//     ));
    
//     if (!empty($existing_groups)) {
//         $group_product = wc_get_product($existing_groups[0]->ID);
//         // Ensure it's actually a grouped product
//         if ($group_product && $group_product->is_type('grouped')) {
//             return $existing_groups[0]->ID;
//         }
//     }
    
//     // Create new grouped product
//     $grouped_product = new WC_Product_Grouped();
//     $grouped_product->set_name('Group: ' . $parent_product_id);
//     $grouped_product->set_status('publish');
//     $grouped_product->set_catalog_visibility('visible');
    
//     // Save the grouped product
//     $group_id = $grouped_product->save();
    
//     if ($group_id) {
//         // Store the parent_product_id as meta for future reference
//         update_post_meta($group_id, '_parent_product_id', $parent_product_id);
//         return $group_id;
//     }
    
//     error_log('GGT Plugin: Failed to create grouped product for parent_product_id: ' . $parent_product_id);
//     return null;
// }

// function add_product_to_group($product_id, $group_id) {
//     if (!$product_id || !$group_id) {
//         return false;
//     }
    
//     $grouped_product = wc_get_product($group_id);
//     if (!$grouped_product || !$grouped_product->is_type('grouped')) {
//         error_log('GGT Plugin: Group product not found or not grouped type. Group ID: ' . $group_id);
//         return false;
//     }
    
//     // Get current children
//     $current_children = $grouped_product->get_children();
    
//     // Add this product if not already in the group
//     if (!in_array($product_id, $current_children)) {
//         $current_children[] = $product_id;
//         $grouped_product->set_children($current_children);
//         $result = $grouped_product->save();
        
//         if (!$result) {
//             error_log('GGT Plugin: Failed to save grouped product children for group ID: ' . $group_id);
//             return false;
//         }
        
//         // Also store the group relationship in the child product's meta
//         update_post_meta($product_id, '_grouped_parent_id', $group_id);
//     }
    
//     return true;
// }

// function handle_product_grouping($product_id, $product_data) {
//     if (empty($product_data['parent_product_id'])) {
//         return;
//     }
    
//     $parent_product_id = $product_data['parent_product_id'];
    
/**
 * Resolve a stockCode to a product ID.
 * Searches meta key `_stockCode` for a match.
 */
function stockcode_to_product_id($stockCode) {
    if (empty($stockCode)) return 0;
    $posts = get_posts(array(
        'post_type' => 'product',
        'meta_key' => '_stockCode',
        'meta_value' => $stockCode,
        'fields' => 'ids',
        'posts_per_page' => 1,
    ));
    return !empty($posts) ? intval($posts[0]) : 0;
}

/**
 * Return true if Advanced Custom Fields (Pro) plugin is installed and active.
 */
function is_acf_pro_active() {
    // Try plugin path check first
    if (!function_exists('is_plugin_active')) {
        if (file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
    }

    // Check common plugin folder names (pro and free)
    if (function_exists('is_plugin_active')) {
        if (is_plugin_active('advanced-custom-fields-pro/acf.php') || is_plugin_active('advanced-custom-fields/acf.php')) {
            return true;
        }
    }

    // As a fallback, check for core ACF functions (update_field etc.)
    if (function_exists('update_field') || function_exists('acf_get_field')) {
        return true;
    }

    return false;
}

/**
 * Relate products via ACF fields after an import/update.
 * Expects the imported product data to include a `RelatedProduct` key (pipe or comma separated stock codes)
 * and optionally an `IsRequired` flag. Uses plugin options to determine ACF field names/keys.
 */
function relate_products_via_acf($product_id, $product_data) {
    ggt_log("Starting ACF processing for product {$product_id}", 'ACF');
    
    // Only run if ACF Pro is installed and active
    if (!is_acf_pro_active()) {
        ggt_log("ACF Pro not active for product {$product_id}", 'ACF');
        return;
    }

    // Check if enabled in options
    $acf_enabled = get_option('ggt_import_enable_acf_relate');
    ggt_log("ACF relate option value: " . var_export($acf_enabled, true), 'ACF');
    
    if (!$acf_enabled) {
        ggt_log("ACF relate not enabled in options for product {$product_id}. Go to Admin → Go Geothermal → Settings and check 'Enable ACF relating during import'", 'ACF');
        return;
    }

    $related_field = get_option('ggt_import_acf_related_field');
    $required_field = get_option('ggt_import_acf_required_field');

    ggt_log("Product {$product_id}: ACF field names - related_field='{$related_field}', required_field='{$required_field}'", 'ACF');

    // If nothing configured, bail
    if (empty($related_field) && empty($required_field)) {
        ggt_log("No ACF fields configured for product {$product_id}", 'ACF');
        return;
    }
    
    // Log what data we received
    $has_related = isset($product_data['RelatedProducts']) || isset($product_data['RelatedProduct']);
    $has_required = isset($product_data['IsRequired']);
    ggt_log("Product {$product_id} data: has RelatedProducts=" . ($has_related ? 'yes' : 'no') . ", has IsRequired=" . ($has_required ? 'yes' : 'no'), 'ACF');

    // Handle related products: parse incoming codes and merge with existing related IDs, avoiding duplicates
    $incoming_related_ids = array();
    // Check for both RelatedProducts (from API) and RelatedProduct (legacy)
    $related_raw = !empty($product_data['RelatedProducts']) ? $product_data['RelatedProducts'] : 
                   (!empty($product_data['RelatedProduct']) ? $product_data['RelatedProduct'] : '');
    
    if (!empty($related_raw)) {
        ggt_log("Product {$product_id}: RelatedProducts raw value: {$related_raw}", 'ACF');
        // Normalize delimiters (comma or pipe)
        $parts = preg_split('/[,|\\|\|]+/', $related_raw);
        ggt_log("Product {$product_id}: Split into " . count($parts) . " parts", 'ACF');
        
        foreach ($parts as $p) {
            $code = trim($p);
            if (empty($code)) continue;
            $id = stockcode_to_product_id($code);
            if ($id) {
                $incoming_related_ids[] = $id;
                ggt_log("Product {$product_id}: Found stock code '{$code}' -> product ID {$id}", 'ACF');
            } else {
                ggt_log("Product {$product_id}: Could NOT find product with stock code '{$code}'", 'ACF');
            }
        }
    } else {
        ggt_log("Product {$product_id}: No RelatedProducts data found in product_data", 'ACF');
    }

    if (!empty($related_field) && !empty($incoming_related_ids)) {
        ggt_log("Product {$product_id}: Found " . count($incoming_related_ids) . " related product IDs: " . implode(', ', $incoming_related_ids), 'ACF');
        // Get existing related IDs (ACF or postmeta)
        $existing_related = array();
        if (function_exists('get_field')) {
            $existing = get_field($related_field, $product_id);
            ggt_log("Product {$product_id}: get_field('{$related_field}') returned: " . (is_array($existing) ? 'array with ' . count($existing) . ' items' : gettype($existing)), 'ACF');
            if (is_array($existing)) {
                // ACF relationship may return array of post objects or IDs
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
        $merged = array_values(array_unique(array_merge($existing_related, $incoming_related_ids)));

        ggt_log("Product {$product_id}: Merged related products: " . implode(', ', $merged), 'ACF');

        // Save merged list via ACF if available, otherwise postmeta
        if (function_exists('update_field')) {
            ggt_log("Product {$product_id}: About to update_field('{$related_field}') with " . count($merged) . " IDs: " . implode(',', $merged), 'ACF');
            $result = update_field($related_field, $merged, $product_id);
            ggt_log("Product {$product_id}: Updated ACF field '{$related_field}' - " . ($result ? 'SUCCESS' : 'FAILED'), 'ACF');
            
            // Verify it was saved
            $verify = get_field($related_field, $product_id);
            ggt_log("Product {$product_id}: Verification get_field('{$related_field}') returned: " . (is_array($verify) ? 'array with ' . count($verify) . ' items' : gettype($verify)), 'ACF');
        } else {
            update_post_meta($product_id, $related_field, maybe_serialize($merged));
            ggt_log("Product {$product_id}: Updated postmeta '{$related_field}'", 'ACF');
        }
    } elseif (!empty($related_field)) {
        ggt_log("Product {$product_id}: No related products to save (related_raw was: " . (!empty($related_raw) ? $related_raw : 'empty') . ")", 'ACF');
    }

    // Handle required flag (truthy values in product_data)
    if (!empty($required_field)) {
        $is_required = false;
        if (isset($product_data['IsRequired'])) {
            $val = $product_data['IsRequired'];
            $is_required = in_array(strtolower((string)$val), ['1', 'true', 'yes', 'y'], true);
        }

        if (function_exists('update_field')) {
            update_field($required_field, $is_required ? 1 : 0, $product_id);
        } else {
            update_post_meta($product_id, $required_field, $is_required ? '1' : '0');
        }
    }
}

