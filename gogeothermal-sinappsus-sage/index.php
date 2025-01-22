
        <?php
/*
Plugin Name: WooCommerce Sage 50cloud Integration
Description: A custom WordPress plugin to integrate WooCommerce with Sage 50cloud via the HyperAccounts REST API.
Version: 1.0.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WooCommerceSageIntegration
{
    private $api_base_url = 'update to read from db';
    private $auth_token = 'update to read from db';

    public function __construct()
    {


        // Register activation hook
        register_activation_hook(__FILE__, [$this, 'plugin_activate']);
        // Add action hooks for WooCommerce integration
        add_action('woocommerce_thankyou', [$this, 'convert_order_to_quote'], 10, 1);
        add_action('user_register', [$this, 'register_user_in_sage'], 10, 1);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_filter('woocommerce_get_price_html', [$this, 'get_product_price_from_sage'], 10, 2);
        // Additional actions and filters
        add_action('woocommerce_new_order', [$this, 'sync_new_order_to_sage'], 10, 1);
        add_action('woocommerce_product_save', [$this, 'sync_product_to_sage'], 10, 1);
    }

    public function plugin_activate()
    {
        // Actions to take on plugin activation
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'Sage Integration Admin',
            'Sage Integration',
            'manage_options',
            'sage-integration',
            [$this, 'admin_menu_page'],
            'dashicons-admin-settings',
            90
        );
    }

    public function admin_menu_page()
    {
        // Display the admin page with options to override and approve users/orders
        echo '<div class="wrap"><h1>Sage Integration Admin</h1>';
        $this->display_users_section();
        $this->display_products_section();
        $this->display_quotes_section();
        $this->display_company_settings_section();
        $this->display_exchange_rates_section();
        echo '</div>';
    }

    private function display_users_section()
    {
        echo '<h2>Users</h2>';
        $users = $this->get_from_sage('/api/customer');
        if ($users && isset($users['data'])) {
            echo '<table class="widefat fixed">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Telephone</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($users['data'] as $user) {
                echo '<tr>
                        <td>' . $user['id'] . '</td>
                        <td>' . $user['name'] . '</td>
                        <td>' . $user['email'] . '</td>
                        <td>' . $user['telephone'] . '</td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No users found in Sage.</p>';
        }
    }

    private function display_products_section()
    {
        echo '<h2>Products</h2>';
        $products = $this->get_from_sage('/api/product');
        if ($products && isset($products['data'])) {
            echo '<table class="widefat fixed">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Sync to WooCommerce</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($products['data'] as $product) {
                echo '<tr>
                        <td>' . $product['id'] . '</td>
                        <td>' . $product['name'] . '</td>
                        <td>' . $product['stockCode'] . '</td>
                        <td>' . $product['salesPrice'] . '</td>
                        <td><a href="' . admin_url('admin.php?page=sage-integration&action=sync_product&product_id=' . $product['id']) . '">Sync to WooCommerce</a></td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No products found in Sage.</p>';
        }
    }

    private function display_quotes_section()
    {
        echo '<h2>Quotes</h2>';
        $quotes = $this->get_from_sage('/api/salesOrder');
        if ($quotes && isset($quotes['data'])) {
            echo '<table class="widefat fixed">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Ref</th>
                            <th>Order Date</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($quotes['data'] as $quote) {
                echo '<tr>
                        <td>' . $quote['id'] . '</td>
                        <td>' . $quote['customerRef'] . '</td>
                        <td>' . $quote['orderDate'] . '</td>
                        <td>' . $quote['total'] . '</td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No quotes found in Sage.</p>';
        }
    }

    private function display_company_settings_section()
    {
        echo '<h2>Company Settings</h2>';
        $company_settings = $this->read_company_settings();
        if ($company_settings) {
            echo '<table class="widefat fixed">
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($company_settings as $key => $value) {
                echo '<tr>
                        <td>' . $key . '</td>
                        <td>' . $value . '</td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No company settings found in Sage.</p>';
        }
    }

    private function display_exchange_rates_section()
    {
        echo '<h2>Exchange Rates</h2>';
        $exchange_rates = $this->read_exchange_rates();
        if ($exchange_rates && isset($exchange_rates['data'])) {
            echo '<table class="widefat fixed">
                    <thead>
                        <tr>
                            <th>Currency</th>
                            <th>Rate</th>
                            <th>Update Rate</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($exchange_rates['data'] as $rate) {
                echo '<tr>
                        <td>' . $rate['currencyCode'] . '</td>
                        <td>' . $rate['exchangeRate'] . '</td>
                        <td><a href="' . admin_url('admin.php?page=sage-integration&action=update_exchange_rate&currency_code=' . $rate['currencyCode']) . '">Update</a></td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No exchange rates found in Sage.</p>';
        }
    }

    public function convert_order_to_quote($order_id)
    {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        $quote_data = $this->prepare_quote_data($order);

        // Call Sage API to create the quote
        $this->post_to_sage('/api/salesOrder', $quote_data);
    }

    public function register_user_in_sage($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) return;

        // Prepare customer data
        $customer_data = [
            'name' => $user->display_name,
            'email' => $user->user_email,
            'telephone' => get_user_meta($user_id, 'billing_phone', true),
            'address' => get_user_meta($user_id, 'billing_address_1', true),
        ];

        // Register the user in Sage if approved by an admin
        if (get_user_meta($user_id, 'is_approved', true) == 1) {
            $this->post_to_sage('/api/customer', $customer_data);
        }
    }

    public function get_product_price_from_sage($price, $product)
    {
        $stock_code = $product->get_sku();
        $response = $this->get_from_sage('/api/product/' . $stock_code);

        if ($response && isset($response['salesPrice'])) {
            $price = wc_price($response['salesPrice']);
        }

        return $price;
    }

    public function sync_new_order_to_sage($order_id)
    {
        $order = wc_get_order($order_id);
        $order_data = $this->prepare_order_data($order);

        // Sync new order to Sage
        $this->post_to_sage('/api/salesOrder', $order_data);
    }

    public function sync_product_to_sage($product_id)
    {
        $product = wc_get_product($product_id);
        $product_data = [
            'stockCode' => $product->get_sku(),
            'name' => $product->get_name(),
            'salesPrice' => $product->get_price(),
            'description' => $product->get_description(),
        ];

        // Sync product to Sage
        $this->post_to_sage('/api/product', $product_data);
    }

    public function read_company_settings()
    {
        // Fetch company settings from Sage
        $response = $this->get_from_sage('/api/company');
        return $response;
    }

    public function read_exchange_rates()
    {
        // Fetch exchange rates from Sage
        $response = $this->get_from_sage('/api/currency');
        return $response;
    }

    public function update_exchange_rate($currency_data)
    {
        // Update exchange rate in Sage
        $this->patch_to_sage('/api/currency', $currency_data);
    }

    private function prepare_quote_data($order)
    {
        // Prepare order data in the format required by Sage
        return [
            'customerRef' => $order->get_user_id(),
            'orderDate' => date('Y-m-d'),
            'items' => $this->prepare_order_items($order),
            'billingAddress' => $order->get_billing_address_1(),
            'shippingAddress' => $order->get_shipping_address_1(),
        ];
    }

    private function prepare_order_items($order)
    {
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = [
                'stockCode' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'name' => $product->get_name(),
            ];
        }
        return $items;
    }

    private function post_to_sage($endpoint, $data)
    {
        $url = $this->api_base_url . $endpoint;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->auth_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'method' => 'POST',
        ];

        $response = wp_remote_post($url, $args);
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function patch_to_sage($endpoint, $data)
    {
        $url = $this->api_base_url . $endpoint;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->auth_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'method' => 'PATCH',
        ];

        $response = wp_remote_request($url, $args);
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function get_from_sage($endpoint)
    {
        $url = $this->api_base_url . $endpoint;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->auth_token,
                'Content-Type' => 'application/json',
            ],
            'method' => 'GET',
        ];

        $response = wp_remote_get($url, $args);
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}

new WooCommerceSageIntegration();
