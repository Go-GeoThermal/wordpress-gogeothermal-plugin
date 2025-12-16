<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Credit Payment Blocks integration
 */
class WC_Geo_Credit_Blocks_Support extends AbstractPaymentMethodType {
    
    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'geo_credit';

    public function get_supported_features() {
        return ['products', 'refunds'];
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_geo_credit_settings', []);
    
        if (!isset($this->settings['enabled'])) {
            $this->settings['enabled'] = 'yes'; // Default to enabled if not set
        }
    
        $is_active = ($this->settings['enabled'] === 'yes');
    
        wc_get_logger()->info('Geo Credit Blocks initialize called. Enabled: ' . ($is_active ? 'true' : 'false'), ['source' => 'geo-credit-blocks']);
    
        if (!$is_active) {
            wc_get_logger()->info('Geo Credit Blocks NOT active, skipping registration.', ['source' => 'geo-credit-blocks']);
            return;
        }
    
        wc_get_logger()->info('Geo Credit Blocks is active. Proceeding with registration.', ['source' => 'geo-credit-blocks']);
    }
    
    

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        $this->settings = get_option('woocommerce_geo_credit_settings', []);
    
        if (!isset($this->settings['enabled'])) {
            $this->settings['enabled'] = 'yes'; // Default to enabled if option is missing
        }
    
        $is_active = ($this->settings['enabled'] === 'yes');
    
        wc_get_logger()->info('Geo Credit Blocks is_active: ' . ($is_active ? 'true' : 'false'), ['source' => 'geo-credit-blocks']);
    
        return $is_active;
    }
    

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */


     public function get_payment_method_script_handles() {

        wp_register_script(
            'wc-geo-credit-payment-method',
            plugins_url('sinappsus-go-geothermal-plugin/assets/js/geo-credit-payment-method.js', dirname(__FILE__, 3)), // Correct path
            ['wp-element', 'wp-components', 'wc-blocks-registry'],
            '1.0.0',
            true
        );
        
    
        return ['wc-geo-credit-payment-method'];
    }
    


    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $credit_limit = 0;
        $available_credit = 0;
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            // Try different meta keys where credit limit might be stored
            $credit_limit = get_user_meta($user_id, 'CreditLimit', true);
            if (empty($credit_limit)) {
                $credit_limit = get_user_meta($user_id, 'creditLimit', true);
            }
            if (empty($credit_limit)) {
                $credit_limit = get_user_meta($user_id, 'credit_limit', true);
            }
            
            if (!$credit_limit) {
                $credit_limit = 0;
            }

            // Get current balance
            $balance = get_user_meta($user_id, 'Balance', true);
            if (empty($balance)) {
                $balance = get_user_meta($user_id, 'balance', true);
            }
            // Default balance to 0 if not set
            if (empty($balance)) {
                $balance = 0;
            }

            // Calculate available credit
            $available_credit = floatval($credit_limit) - floatval($balance);
        }
        
        wc_get_logger()->info('Geo Credit Blocks data prepared. Credit limit: ' . $credit_limit . ', Available: ' . $available_credit, ['source' => 'geo-credit-blocks']);
        
        return [
            'title'       => 'Credit Payment',
            'description' => 'Use your available credit to checkout. Your available credit is ' . strip_tags(wc_price($available_credit)),
            'supports'    => [
                'products',
            ],
            'credit_limit' => $available_credit,
        ];
    }
}