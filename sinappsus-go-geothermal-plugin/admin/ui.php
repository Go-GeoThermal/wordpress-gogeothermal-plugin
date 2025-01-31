<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the const.php file to get the environments array
require_once __DIR__ . '/../const.php';

class Sinappsus_GGT_Admin_UI
{
    private $api_url;

    public function __construct()
    {
        global $environments;
        $this->api_url = $environments['production']['api_url'];

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
                        <th scope="row">Username</th>
                        <td><input type="text" name="ggt_sinappsus_username" value="<?php echo esc_attr(get_option('ggt_sinappsus_username')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Password</th>
                        <td><input type="password" name="ggt_sinappsus_password" value="<?php echo esc_attr(get_option('ggt_sinappsus_password')); ?>" /></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" id="authenticate-button" class="button button-primary">Authenticate</button>
                    <button type="button" id="validate-button" class="button">Validate</button>
                    <?php submit_button(); ?>
                </p>
                <p id="timer"></p>
                <p id="message"></p>
            </form>
        </div>
        <script type="text/javascript">
            document.getElementById('authenticate-button').addEventListener('click', function() {
                var username = document.querySelector('input[name="ggt_sinappsus_username"]').value;
                var password = document.querySelector('input[name="ggt_sinappsus_password"]').value;

                fetch('<?php echo $this->api_url; ?>/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ username: username, password: password })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.token) {
                        localStorage.setItem('jwt_token', data.token);
                        document.getElementById('message').innerText = 'Authentication successful!';
                    } else {
                        document.getElementById('message').innerText = 'Authentication failed!';
                    }
                })
                .catch(error => {
                    document.getElementById('message').innerText = 'An error occurred: ' + error.message;
                });
            });

            document.getElementById('validate-button').addEventListener('click', function() {
                var token = localStorage.getItem('jwt_token');

                fetch('<?php echo $this->api_url; ?>/validate', {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        document.getElementById('message').innerText = 'Token is valid!';
                    } else {
                        document.getElementById('message').innerText = 'Token is invalid!';
                    }
                })
                .catch(error => {
                    document.getElementById('message').innerText = 'An error occurred: ' + error.message;
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

function ggt_sinappsus_register_settings() {
    register_setting('ggt_sinappsus_settings_group', 'ggt_sinappsus_username');
    register_setting('ggt_sinappsus_settings_group', 'ggt_sinappsus_password');
}

new Sinappsus_GGT_Admin_UI();