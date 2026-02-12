# Sinappsus Go Geothermal Plugin Documentation

## 1. Overview
The **Sinappsus Go Geothermal Plugin** is a custom WordPress/WooCommerce extension designed to integrate the Go Geothermal e-commerce site with their internal systems (likely Sage/Hyper via a custom API). 

Its primary functions are:
*   **Product & User Synchronization**: Tools to map and import data from external CSVs/APIs.
*   **Checkout Enhancements**: Custom delivery date selection, dynamic pricing fetching, and address handling.
*   **Credit Payment Gateway**: A "Pay on Account" feature checking user credit limits.
*   **Order Tracking**: Visual order progress for customers.

## 2. Project Structure

### Root Directory
*   `sinappsus-go-geothermal-plugin.php`: **Main Entry Point**. Checks for WooCommerce dependencies and loads the core plugin logic.
*   `const.php`: Defines global constants including API URLs for Staging/Production environments.
*   `plugin-update/`: Handles auto-updates from GitHub.

### Core Logic (`includes/`)
*   `sinappsus-ggt-wp-plugin.php`: **Plugin Initializer**. Loads all sub-modules, hooks into WooCommerce, and initializes settings.
*   `class-checkout-enhancements.php`: **Critical**. Modifies the checkout flow. Handles fetching customer specific pricing via AJAX, updating cart prices, and processing delivery addresses.
*   `class-credit-payment.php`: **Payment Gateway**. Implements `WC_Geo_Credit_Gateway`. Checks `CreditLimit` vs `Balance` user meta to authorize "pay later" orders.
*   `class-order-progress.php`: Adds a UI button and logic to track order status (Processing, Shipped, outputting visual progress bars).
*   `class-woocommerce-customization.php`: Adds specific field customizations, notably the **Delivery Date Picker** with holidays logic.
*   `woocommerce-sage-integration.php`: *(Currently Empty)* Appears to be a placeholder for direct Sage integration.
*   `cron-sync.php`: Likely handles background synchronization tasks.

### Admin Tools (`admin/`)
*   `flexible-import.php`: **Product Importer**. Provides a UI to map columns from an imported CSV/dataset to WooCommerce product fields (meta, attributes, prices).
*   `user-mapping.php`: **User Importer**. Similar to product import, but for mapping customer account data from external systems to WordPress User Meta.
*   `ui.php`: General admin UI settings.

### Utilities (`utils/`)
*   `class-api-connector.php`: Handles secure connections to the Go Geothermal API (`ggt_sinappsus_connect_to_api`), managing encrypted tokens (`sinappsus_gogeo_codex`).

## 3. Business Logic & Connections

### 3.1 External API Connection
*   **Auth**: The plugin stores an encrypted token in the WP Option `sinappsus_gogeo_codex`.
*   **Connectivity**: `utils/class-api-connector.php` decrypts this token and uses it as a Bearer token for requests.
*   **Endpoints**: Base URLs are defined in `const.php` (toggled between Staging/Production).

### 3.2 Product & User Import Loop
1.  **Admin** uploads data or triggers a sync.
2.  **Mapping**: The "Flexible Import" system (`admin/flexible-import.php`) allows admins to map external keys (e.g., `stockCode`, `nominalCode`) to WP fields.
3.  **Execution**: Data is processed in batches (Ajax) to avoid timeouts. `ggt_execute_flexible_import`.

### 3.3 Checkout Flow Customizations
1.  **Pricing**: When a user goes to checkout or views cart, `class-checkout-enhancements.php` may communicate with the API to get "Customer Specific Pricing" based on their account ID.
2.  **Delivery Date**: `class-woocommerce-customization.php` injects a DatePicker.
    *   **Logic**: Blocks weekends. Enforces a 2-day lead time (`minDate: +2d`).
    *   **Holidays**: Checks against a hardcoded list of UK holidays (needs maintenance).
3.  **Payment**: If the user selects "Credit Payment":
    *   `class-credit-payment.php` checks User Meta `CreditLimit` and `Balance`.
    *   Condition: `Available Credit >= Cart Total`.

### 3.4 Order Progress
*   Adds an "Order Progress" button to the My Account > Orders list.
*   Fetches status steps via AJAX to display a visual progress bar to the end customer.

---

## 4. Analysis & Maintenance To-Do List

The following items have been identified as redundant, outdated, or in need of optimization.

| Priority | Item | Location | Action | Rationale |
| :--- | :--- | :--- | :--- | :--- |
| **HIGH** | Hardcoded Holidays | `includes/class-woocommerce-customization.php` | **Update/Refactor** | The file contains hardcoded arrays for 2023/2024 holidays. These will expire. **Fix:** Move to a setting field or fetch dynamically from an API. |
| **HIGH** | Empty Class | `includes/sage/woocommerce-sage-integration.php` | **Remove** | The class `WooCommerceSageIntegration` is empty and commented out. If it's not used, delete it to avoid confusion. |
| **MED** | Old Debug Files | `admin/test_debug.php`, `includes/oldFunc.txt`, `test-progress.php` | **Delete** | These look like development artifacts that should not be in production code. |
| **MED** | Direct Script Injection | `includes/class-woocommerce-customization.php` | **Refactor** | JS/CSS is being echoed directly in PHP functions (`ggt_delivery_date_field_footer_fallback`). **Fix:** Move to separate `.js`/`.css` files and use `wp_enqueue_script` / `wp_localize_script`. |
| **MED** | External CDN Dependency | `includes/class-checkout-enhancements.php` | **Bundle/Replace** | Loading jQuery UI CSS from `code.jquery.com`. **Fix:** Enqueue WordPress core's bundled `jquery-ui-core` styles or bundle the CSS locally to ensure GDPR compliance and reliability. |
| **LOW** | Debug Constants | `sinappsus-ggt-wp-plugin.php` | **Clean** | Commented out `define('WP_DEBUG', ...)` lines should be removed. Debugging is controlled via `wp-config.php`. |
| **LOW** | Code Duplication | `class-credit-payment.php` | **Consolidate** | It checks multiple meta keys for credit limit (`CreditLimit`, `creditLimit`, `credit_limit`). **Fix:** Standardize on one key during the import process to simplify runtime checks. |
