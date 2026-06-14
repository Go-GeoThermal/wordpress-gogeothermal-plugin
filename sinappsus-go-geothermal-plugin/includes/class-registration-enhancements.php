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
if (!function_exists('ggt_get_registration_company_link_email')) {
    /**
     * Get the submitted company email when the registrant asks to join an account.
     */
    function ggt_get_registration_company_link_email() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- registration nonce is handled by WordPress/WooCommerce
        $linked_to_company = !empty($_POST['ggt_linked_to_company']);

        if (!$linked_to_company) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- registration nonce is handled by WordPress/WooCommerce
        return isset($_POST['ggt_company_email']) ? sanitize_email(wp_unslash($_POST['ggt_company_email'])) : '';
    }
}

if (!function_exists('ggt_attempt_registration_company_link')) {
    /**
     * Link a newly registered WP user to an existing middleware account user.
     */
    function ggt_attempt_registration_company_link($user_id, $company_email = '', $registration_data = array()) {
        $company_email = sanitize_email($company_email);

        if (empty($company_email)) {
            return array('attempted' => false, 'linked' => false, 'account_ref' => null);
        }

        $existing_attempt = get_user_meta($user_id, '_ggt_company_link_attempted', true);
        if ($existing_attempt) {
            $account_ref = get_user_meta($user_id, 'accountRef', true);
            return array(
                'attempted'   => true,
                'linked'      => !empty($account_ref),
                'account_ref' => !empty($account_ref) ? $account_ref : null,
            );
        }

        update_user_meta($user_id, '_ggt_company_link_attempted', 1);

        $user = get_userdata($user_id);
        if (!$user || !function_exists('ggt_sinappsus_connect_to_api')) {
            return array('attempted' => true, 'linked' => false, 'account_ref' => null);
        }

        $name = '';
        foreach (array('name', 'contactName', 'customerName') as $name_key) {
            if (!empty($registration_data[$name_key])) {
                $name = sanitize_text_field($registration_data[$name_key]);
                break;
            }
        }

        if (empty($name)) {
            $name = trim(
                ($user->first_name ? $user->first_name . ' ' : '') .
                ($user->last_name  ? $user->last_name  : '')
            );
        }

        if (empty($name)) {
            $name = $user->display_name ?: '';
        }

        $payload = array(
            'email'             => $user->user_email,
            'name'              => $name,
            'company_email'     => $company_email,
            'wordpress_user_id' => $user_id,
        );

        $response = ggt_sinappsus_connect_to_api('account-users/register-company-link', $payload, 'POST');

        if (!empty($response['account_ref'])) {
            $account_ref = sanitize_text_field($response['account_ref']);

            update_user_meta($user_id, 'accountRef', $account_ref);
            update_user_meta($user_id, 'ggt_is_sub_user', 1);
            update_user_meta($user_id, 'ggt_needs_review', 1);

            if (function_exists('ggt_apply_parent_account_ref_fields_to_user')) {
                ggt_apply_parent_account_ref_fields_to_user($user_id, $account_ref);
            }

            return array(
                'attempted'   => true,
                'linked'      => true,
                'account_ref' => $account_ref,
            );
        }

        return array('attempted' => true, 'linked' => false, 'account_ref' => null);
    }
}

if (!function_exists('ggt_validate_registration_company_email_exists')) {
    /**
     * Validate whether a submitted company email maps to an existing trade account.
     */
    function ggt_validate_registration_company_email_exists($company_email) {
        $company_email = sanitize_email($company_email);

        if (empty($company_email) || !is_email($company_email)) {
            return array('exists' => false, 'error' => 'invalid_email');
        }

        if (!function_exists('ggt_sinappsus_connect_to_api')) {
            return array('exists' => false, 'error' => 'api_unavailable');
        }

        $response = ggt_sinappsus_connect_to_api(
            'account-users/validate-company-email',
            array('company_email' => $company_email),
            'POST'
        );

        if (isset($response['error'])) {
            return array('exists' => false, 'error' => 'api_error');
        }

        return array(
            'exists' => !empty($response['exists']),
            'error'  => null,
        );
    }
}

if (!function_exists('ggt_registration_company_link_rejected_message')) {
    /**
     * Get the shared rejection message for unavailable company associations.
     */
    function ggt_registration_company_link_rejected_message() {
        return __('This company has not approved you for association. Please contact your organization admin to verify your information or continue to create a new company account.', 'sinappsus-ggt');
    }
}

class GGT_Registration_Enhancements {

    public function __construct() {
        add_action('woocommerce_register_form', [$this, 'add_company_link_fields'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_nopriv_ggt_validate_registration_company_email', [$this, 'ajax_validate_company_email']);
        add_action('wp_ajax_ggt_validate_registration_company_email', [$this, 'ajax_validate_company_email']);
        add_filter('woocommerce_registration_errors', [$this, 'validate_company_link_registration'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'handle_company_link'], 10, 3);
    }

    /**
     * Enqueue registration company-link validation assets on public account pages.
     */
    public function enqueue_assets() {
        if (is_user_logged_in()) {
            return;
        }

        if (function_exists('is_account_page') && !is_account_page()) {
            return;
        }

        $script_path = GGT_SINAPPSUS_PLUGIN_PATH . '/assets/js/registration-company-link.js';
        $style_path = GGT_SINAPPSUS_PLUGIN_PATH . '/assets/css/registration-company-link.css';

        wp_enqueue_script(
            'ggt-registration-company-link',
            GGT_SINAPPSUS_PLUGIN_URL . '/assets/js/registration-company-link.js',
            array(),
            file_exists($script_path) ? filemtime($script_path) : GGT_SINAPPSUS_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'ggt-registration-company-link',
            GGT_SINAPPSUS_PLUGIN_URL . '/assets/css/registration-company-link.css',
            array(),
            file_exists($style_path) ? filemtime($style_path) : GGT_SINAPPSUS_PLUGIN_VERSION
        );

        wp_localize_script('ggt-registration-company-link', 'ggtRegistrationCompanyLink', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ggt_registration_company_link'),
            'rejectedMessage' => ggt_registration_company_link_rejected_message(),
            'validationErrorMessage' => __('We could not verify this company account right now. Please continue to create a new company account or try again later.', 'sinappsus-ggt'),
        ));
    }

    /**
     * Validate a company email from the public registration form via AJAX.
     */
    public function ajax_validate_company_email() {
        if (!check_ajax_referer('ggt_registration_company_link', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'sinappsus-ggt')), 403);
        }

        $company_email = isset($_POST['company_email'])
            ? sanitize_email(wp_unslash($_POST['company_email']))
            : '';

        $validation = ggt_validate_registration_company_email_exists($company_email);

        if (!empty($validation['error']) && $validation['error'] !== 'invalid_email') {
            wp_send_json_error(array(
                'exists' => false,
                'message' => __('We could not verify this company account right now. Please continue to create a new company account or try again later.', 'sinappsus-ggt'),
            ), 503);
        }

        wp_send_json_success(array(
            'exists' => !empty($validation['exists']),
            'message' => !empty($validation['exists']) ? '' : ggt_registration_company_link_rejected_message(),
        ));
    }

    /**
     * Block company-linked registration when the submitted company email is not approved.
     */
    public function validate_company_link_registration($errors, $username, $email) {
        $company_email = ggt_get_registration_company_link_email();

        if (empty($company_email)) {
            return $errors;
        }

        $validation = ggt_validate_registration_company_email_exists($company_email);

        if (empty($validation['exists'])) {
            $errors->add('ggt_company_link_not_approved', ggt_registration_company_link_rejected_message());
        }

        return $errors;
    }

    /**
     * Output the company-link fields on the registration form.
     */
    public function add_company_link_fields() {
        ?>
        <div class="ggt-company-link-section form-row form-row-wide">
            <label class="ggt-company-link-label" for="ggt_linked_to_company">
                <input type="checkbox" class="ggt-company-link-checkbox"
                       name="ggt_linked_to_company" id="ggt_linked_to_company" value="1" />
                <span class="ggt-company-link-text"><?php esc_html_e('I am registering as part of a company account', 'sinappsus-ggt'); ?></span>
            </label>
        </div>
        <p class="ggt-company-email-row form-row form-row-wide" id="ggt_company_email_row" hidden>
            <label for="ggt_company_email">
                <?php esc_html_e('Company primary email address', 'sinappsus-ggt'); ?>
            </label>
            <input type="email" class="woocommerce-Input woocommerce-Input--text input-text"
                   name="ggt_company_email" id="ggt_company_email" value="" autocomplete="off" />
                 <span id="ggt_company_email_validation" class="ggt-company-email-validation" role="alert" aria-live="polite" hidden></span>
        </p>
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

        ggt_attempt_registration_company_link($customer_id, $company_email, array('name' => $name ?: ''));
    }
}

new GGT_Registration_Enhancements();
