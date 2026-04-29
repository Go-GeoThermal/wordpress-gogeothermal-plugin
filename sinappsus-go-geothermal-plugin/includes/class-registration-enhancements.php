<?php
if (!defined('ABSPATH')) exit;

/**
 * GGT Registration Enhancements
 *
 * Adds an optional "I am part of a company account" checkbox to the WooCommerce
 * registration form. When checked, the registrant provides the primary email of
 * the company account. On successful registration the new user is silently linked
 * to that account via the Go Geothermal API and flagged for staff review.
 */
class GGT_Registration_Enhancements {

    public function __construct() {
        add_action('woocommerce_register_form', [$this, 'add_company_link_fields']);
        add_action('woocommerce_created_customer', [$this, 'handle_company_link'], 10, 3);
    }

    /**
     * Output the company-link fields on the registration form.
     */
    public function add_company_link_fields() {
        ?>
        <p class="form-row form-row-wide">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                       name="ggt_linked_to_company" id="ggt_linked_to_company" value="1" />
                <span><?php esc_html_e('I am registering as part of a company account', 'sinappsus-ggt'); ?></span>
            </label>
        </p>
        <p class="form-row form-row-wide" id="ggt_company_email_row" style="display:none;">
            <label for="ggt_company_email">
                <?php esc_html_e('Company primary email address', 'sinappsus-ggt'); ?>
            </label>
            <input type="email" class="woocommerce-Input woocommerce-Input--text input-text"
                   name="ggt_company_email" id="ggt_company_email" value="" autocomplete="off" />
        </p>
        <script>
        (function () {
            var checkbox = document.getElementById('ggt_linked_to_company');
            var row = document.getElementById('ggt_company_email_row');
            if (checkbox && row) {
                checkbox.addEventListener('change', function () {
                    row.style.display = this.checked ? 'block' : 'none';
                });
            }
        }());
        </script>
        <?php
    }

    /**
     * After a new customer is created, silently attempt to link them to a
     * company account if the company email field was submitted.
     *
     * @param int   $customer_id         WP user ID of the newly created customer.
     * @param array $new_customer_data   Data passed to wp_insert_user().
     * @param bool  $password_generated  Whether a password was auto-generated.
     */
    public function handle_company_link($customer_id, $new_customer_data, $password_generated) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce before this hook fires
        $company_email = isset($_POST['ggt_company_email']) ? sanitize_email(wp_unslash($_POST['ggt_company_email'])) : '';

        if (empty($company_email)) {
            return;
        }

        $user = get_userdata($customer_id);
        if (!$user) {
            return;
        }

        $name = trim(
            ($user->first_name ? $user->first_name . ' ' : '') .
            ($user->last_name  ? $user->last_name  : '')
        );

        $payload = [
            'email'             => $user->user_email,
            'name'              => $name ?: '',
            'company_email'     => $company_email,
            'wordpress_user_id' => $customer_id,
        ];

        // Silent: outcome must never be revealed to the registering user.
        if (!function_exists('ggt_sinappsus_connect_to_api')) {
            return;
        }

        $response = ggt_sinappsus_connect_to_api('account-users/register-company-link', $payload, 'POST');

        if (!empty($response['account_ref'])) {
            update_user_meta($customer_id, 'accountRef', $response['account_ref']);
        }
    }
}

new GGT_Registration_Enhancements();
