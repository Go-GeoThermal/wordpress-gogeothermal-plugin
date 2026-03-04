<?php
/**
 * GGT Customer Pricing
 * 
 * Applies customer-specific price list pricing globally across the entire WooCommerce site.
 * When a logged-in user has an accountRef and custom pricing from the API,
 * their discounted prices replace the default WooCommerce prices on all pages:
 * shop, single product, cart, checkout, etc.
 * 
 * Pricing is cached per-customer using WordPress transients to minimize API calls.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GGT_Customer_Pricing {

    /** @var array|null Cached price map for the current request: stockCode => discountedPrice */
    private static $price_map = null;

    /** @var bool Whether we already attempted to load prices this request */
    private static $loaded = false;

    /** @var bool Guard against recursive price filter calls */
    private static $applying = false;

    /** Cache duration in seconds (1 hour) */
    const CACHE_TTL = 3600;

    /**
     * Boot all hooks
     */
    public static function init() {
        // ── Product price filters (simple products) ──
        add_filter('woocommerce_product_get_price',         [__CLASS__, 'filter_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price',  [__CLASS__, 'filter_price'], 99, 2);
        add_filter('woocommerce_product_get_sale_price',     [__CLASS__, 'filter_sale_price'], 99, 2);

        // ── Product price filters (variations) ──
        add_filter('woocommerce_product_variation_get_price',         [__CLASS__, 'filter_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price',  [__CLASS__, 'filter_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_sale_price',     [__CLASS__, 'filter_sale_price'], 99, 2);

        // ── Variable product price range ──
        add_filter('woocommerce_variation_prices_price',         [__CLASS__, 'filter_variation_price'], 99, 3);
        add_filter('woocommerce_variation_prices_regular_price', [__CLASS__, 'filter_variation_price'], 99, 3);
        add_filter('woocommerce_variation_prices_sale_price',    [__CLASS__, 'filter_variation_sale_price'], 99, 3);

        // ── Ensure cart uses custom prices during calculation ──
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'apply_prices_to_cart'], 10, 1);

        // ── Bust variation price hash so WC doesn't serve stale cached ranges ──
        add_filter('woocommerce_get_variation_prices_hash', [__CLASS__, 'variation_prices_hash'], 99, 3);

        // ── Price HTML: show clean price (no strikethrough "was/now") ──
        add_filter('woocommerce_get_price_html', [__CLASS__, 'filter_price_html'], 99, 2);

        // ── Admin AJAX to force-refresh cache ──
        add_action('wp_ajax_ggt_refresh_customer_pricing_cache', [__CLASS__, 'ajax_refresh_cache']);

        // ── Clear cache on login (fresh session) ──
        add_action('wp_login', [__CLASS__, 'clear_cache_on_login'], 10, 2);
    }

    // ─────────────────────────────────────────────
    //  Price Map Loading
    // ─────────────────────────────────────────────

    /**
     * Get the price map for the current logged-in user.
     * Returns an associative array: stockCode => discountedPrice
     * Returns empty array if user has no custom pricing.
     *
     * @return array
     */
    public static function get_price_map() {
        // Return cached result for this request
        if (self::$loaded) {
            return self::$price_map ?: [];
        }
        self::$loaded = true;
        self::$price_map = [];

        // Must be logged in
        if (!is_user_logged_in()) {
            return self::$price_map;
        }

        $user_id = get_current_user_id();
        $account_ref = get_user_meta($user_id, 'accountRef', true);
        if (!$account_ref) {
            $account_ref = get_user_meta($user_id, 'account_ref', true);
        }
        if (!$account_ref) {
            return self::$price_map;
        }

        // Try transient cache first
        $transient_key = 'ggt_cust_pricing_' . md5($account_ref);
        $cached = get_transient($transient_key);

        if (false !== $cached && is_array($cached)) {
            self::$price_map = $cached;
            return self::$price_map;
        }

        // Fetch from API
        if (!function_exists('ggt_sinappsus_connect_to_api')) {
            return self::$price_map;
        }

        $endpoint = 'customers/' . urlencode($account_ref) . '/custom-pricing';
        $response = ggt_sinappsus_connect_to_api($endpoint);

        if (isset($response['error'])) {
            self::log('Failed to fetch custom pricing for ' . $account_ref . ': ' . $response['error']);
            return self::$price_map;
        }

        if (isset($response['pricing']) && is_array($response['pricing'])) {
            foreach ($response['pricing'] as $item) {
                if (!empty($item['stockCode']) && isset($item['discountedPrice'])) {
                    self::$price_map[$item['stockCode']] = (float) $item['discountedPrice'];
                }
            }

            // Cache the result
            set_transient($transient_key, self::$price_map, self::CACHE_TTL);

            self::log('Loaded ' . count(self::$price_map) . ' custom prices for customer ' . $account_ref);
        }

        return self::$price_map;
    }

    /**
     * Look up the custom price for a WooCommerce product.
     *
     * @param int|\WC_Product $product  Product ID or WC_Product object
     * @return float|null  Custom price or null if no custom pricing for this product
     */
    public static function get_custom_price($product) {
        $price_map = self::get_price_map();
        if (empty($price_map)) {
            return null;
        }

        $product_id = ($product instanceof \WC_Product) ? $product->get_id() : (int) $product;
        $stock_code = get_post_meta($product_id, '_stockCode', true);

        if ($stock_code && isset($price_map[$stock_code])) {
            return $price_map[$stock_code];
        }

        return null;
    }

    // ─────────────────────────────────────────────
    //  WooCommerce Price Filters
    // ─────────────────────────────────────────────

    /**
     * Filter product price and regular price.
     */
    public static function filter_price($price, $product) {
        if (self::$applying) {
            return $price;
        }

        // Don't modify admin-side prices (product edit screens)
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }

        $custom_price = self::get_custom_price($product);
        if (null !== $custom_price) {
            return $custom_price;
        }

        return $price;
    }

    /**
     * Filter sale price — return empty string so WC doesn't show "sale" badge
     * when we're overriding the price with a custom list price.
     */
    public static function filter_sale_price($price, $product) {
        if (self::$applying) {
            return $price;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }

        $custom_price = self::get_custom_price($product);
        if (null !== $custom_price) {
            // Return empty sale price to avoid strikethrough display
            return '';
        }

        return $price;
    }

    /**
     * Filter variation prices (used to build the "From: £X" range).
     */
    public static function filter_variation_price($price, $variation, $product) {
        if (self::$applying) {
            return $price;
        }

        $custom_price = self::get_custom_price($variation);
        if (null !== $custom_price) {
            return $custom_price;
        }

        return $price;
    }

    /**
     * Filter variation sale prices.
     */
    public static function filter_variation_sale_price($price, $variation, $product) {
        if (self::$applying) {
            return $price;
        }

        $custom_price = self::get_custom_price($variation);
        if (null !== $custom_price) {
            return '';
        }

        return $price;
    }

    /**
     * Modify the price HTML so customers with custom pricing see a clean
     * single price instead of a strikethrough "was/now" display.
     */
    public static function filter_price_html($price_html, $product) {
        if (self::$applying) {
            return $price_html;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }

        $custom_price = self::get_custom_price($product);
        if (null !== $custom_price) {
            // Return a clean price display with no strikethrough
            return wc_price($custom_price);
        }

        return $price_html;
    }

    // ─────────────────────────────────────────────
    //  Cart Integration
    // ─────────────────────────────────────────────

    /**
     * Ensure custom prices are applied to cart items during total calculation.
     * This is the authoritative server-side price setter.
     */
    public static function apply_prices_to_cart($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $price_map = self::get_price_map();
        if (empty($price_map)) {
            return;
        }

        // Use the applying flag to prevent recursive filter calls
        self::$applying = true;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $stock_code = get_post_meta($product_id, '_stockCode', true);

            if ($stock_code && isset($price_map[$stock_code])) {
                $custom_price = $price_map[$stock_code];
                $cart_item['data']->set_price($custom_price);

                // Store metadata for order creation
                WC()->cart->cart_contents[$cart_item_key]['ggt_custom_price'] = $custom_price;
                WC()->cart->cart_contents[$cart_item_key]['ggt_stock_code']   = $stock_code;
            }
        }

        self::$applying = false;
    }

    // ─────────────────────────────────────────────
    //  Variation Price Hash
    // ─────────────────────────────────────────────

    /**
     * Append a user-specific component to the variation price hash so
     * WooCommerce doesn't serve cached price ranges from other users.
     */
    public static function variation_prices_hash($hash, $product, $for_display) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $account_ref = get_user_meta($user_id, 'accountRef', true);
            if (!$account_ref) {
                $account_ref = get_user_meta($user_id, 'account_ref', true);
            }
            if ($account_ref) {
                $hash[] = 'ggt_' . md5($account_ref);
            }
        }
        return $hash;
    }

    // ─────────────────────────────────────────────
    //  Cache Management
    // ─────────────────────────────────────────────

    /**
     * Clear pricing cache for a specific account_ref or the current user.
     *
     * @param string|null $account_ref  Specific account ref, or null for current user
     */
    public static function clear_cache($account_ref = null) {
        if (!$account_ref && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $account_ref = get_user_meta($user_id, 'accountRef', true);
            if (!$account_ref) {
                $account_ref = get_user_meta($user_id, 'account_ref', true);
            }
        }

        if ($account_ref) {
            $transient_key = 'ggt_cust_pricing_' . md5($account_ref);
            delete_transient($transient_key);

            // Reset in-memory cache
            self::$price_map = null;
            self::$loaded = false;

            self::log('Cleared pricing cache for customer ' . $account_ref);
        }
    }

    /**
     * Clear cache when user logs in so they get fresh pricing.
     */
    public static function clear_cache_on_login($user_login, $user) {
        $account_ref = get_user_meta($user->ID, 'accountRef', true);
        if (!$account_ref) {
            $account_ref = get_user_meta($user->ID, 'account_ref', true);
        }
        if ($account_ref) {
            $transient_key = 'ggt_cust_pricing_' . md5($account_ref);
            delete_transient($transient_key);
        }
    }

    /**
     * AJAX handler to force-refresh the pricing cache for the current user.
     */
    public static function ajax_refresh_cache() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
            return;
        }

        self::clear_cache();

        // Force reload
        self::$loaded = false;
        self::$price_map = null;
        $prices = self::get_price_map();

        wp_send_json_success([
            'message' => 'Pricing cache refreshed',
            'count'   => count($prices)
        ]);
    }

    // ─────────────────────────────────────────────
    //  Utility: check if customer has custom pricing
    // ─────────────────────────────────────────────

    /**
     * Check whether the current user has any custom pricing.
     *
     * @return bool
     */
    public static function customer_has_custom_pricing() {
        return !empty(self::get_price_map());
    }

    // ─────────────────────────────────────────────
    //  Logging
    // ─────────────────────────────────────────────

    private static function log($message) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info($message, ['source' => 'ggt-customer-pricing']);
        } else {
            error_log('[GGT Customer Pricing] ' . $message);
        }
    }
}

// Boot the class
GGT_Customer_Pricing::init();
