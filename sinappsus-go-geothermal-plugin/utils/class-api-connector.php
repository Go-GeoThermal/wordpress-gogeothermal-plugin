<?php 

/**
 * Get the decrypted API token
 * @return string|bool The decrypted token or false if not available
 */
if (!function_exists('ggt_get_decrypted_token')) {
    function ggt_get_decrypted_token() {
        $encrypted_token = get_option('sinappsus_gogeo_codex');
        if ($encrypted_token) {
            return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
        }
        return false;
    }
}

/**
 * Log API interaction for debugging and monitoring
 * 
 * @param string $message The message to log
 * @param string $level The log level (info, error, warning)
 * @param array $context Additional context information
 */
if (!function_exists('ggt_log_api_interaction')) {
    function ggt_log_api_interaction($message, $level = 'info', $context = []) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->$level($message, array_merge(['source' => 'ggt-api'], $context));
        } else {
            error_log('[GGT API] ' . $message . ' - ' . json_encode($context));
        }
    }
}

/**
 * Connect to the Go Geothermal API
 * 
 * @param string $endpoint API endpoint (without base URL)
 * @param array $data Data to send with the request
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param array $additional_headers Additional headers to include
 * @return array|WP_Error Response data as array or WP_Error on failure
 */
if (!function_exists('ggt_sinappsus_connect_to_api')) {
    function ggt_sinappsus_connect_to_api($endpoint, $data = array(), $method = 'GET', $additional_headers = []) {
        // Get the base URL from the selected environment
        $url = ggt_get_api_base_url() . '/' . ltrim($endpoint, '/');
        
        // Get authentication token
        $token = ggt_get_decrypted_token();
        if (!$token) {
            ggt_log_api_interaction('API authentication token not available', 'error', ['endpoint' => $endpoint]);
            // Flag admin notice to prompt re-authentication
            update_option('ggt_auth_required', 1);
            update_option('ggt_last_auth_error_at', time());
            return ['error' => 'Authentication token not available'];
        }
        
        // Setup request arguments
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], $additional_headers);
        
        $args = array(
            'method'     => $method,
            'timeout'    => 30,
            'headers'    => $headers,
        );
        
        // Process data based on HTTP method
        if (strtoupper($method) === 'GET') {
            // For GET requests, add data as query parameters
            if (!empty($data)) {
                $url = add_query_arg($data, $url);
            }
        } else {
            // For other methods, encode data as JSON in the body
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = json_encode($data);
        }

        // Log the API request
        ggt_log_api_interaction('API request', 'info', [
            'endpoint' => $endpoint,
            'url' => $url,
            'method' => $method,
            'data_size' => !empty($data) ? count($data) : 0
        ]);

        // Execute the request
        $response = wp_remote_request($url, $args);

        // Handle response
        if (is_wp_error($response)) {
            ggt_log_api_interaction('API request failed', 'error', [
                'endpoint' => $endpoint,
                'error' => $response->get_error_message()
            ]);
            return ['error' => $response->get_error_message()];
        }

        // Get HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Try to decode JSON response
        $decoded_body = json_decode($body, true);
        
        // Log the response
        ggt_log_api_interaction('API response received', 'info', [
            'endpoint' => $endpoint,
            'status_code' => $status_code,
            'success' => ($status_code >= 200 && $status_code < 300)
        ]);

        // Handle error status codes, but treat 404 as a valid response for some endpoints
        if ($status_code < 200 || $status_code >= 300) {
            // For 404 responses, check if we have a valid JSON response with a message
            if ($status_code === 404 && $decoded_body && isset($decoded_body['message'])) {
                ggt_log_api_interaction('API 404 response with message', 'info', [
                    'endpoint' => $endpoint,
                    'status_code' => $status_code,
                    'message' => $decoded_body['message']
                ]);
                
                // Return the response as-is, but add status code for reference
                $response_data = $decoded_body;
                $response_data['status_code'] = $status_code;
                return $response_data;
            }
            // If unauthorized, flag admin to re-authenticate
            if ($status_code === 401) {
                update_option('ggt_auth_required', 1);
                update_option('ggt_last_auth_error_at', time());
            }
            
            ggt_log_api_interaction('API error response', 'error', [
                'endpoint' => $endpoint,
                'status_code' => $status_code,
                'response' => $decoded_body ? json_encode($decoded_body) : $body
            ]);
            
            return [
                'error' => 'API returned error status: ' . $status_code,
                'status_code' => $status_code,
                'response' => $decoded_body ? $decoded_body : $body
            ];
        }

        // Clear auth-required flag on successful authenticated response
        if (get_option('ggt_auth_required')) {
            delete_option('ggt_auth_required');
        }
        return $decoded_body ? $decoded_body : $body;
    }
}

/**
 * Get the base API URL based on the selected environment
 * @return string The base API URL
 */
if (!function_exists('ggt_get_api_base_url')) {
    function ggt_get_api_base_url() {
        global $environments;
        $selected_env = get_option('ggt_sinappsus_environment', 'production');
        
        if (isset($environments[$selected_env]) && isset($environments[$selected_env]['api_url'])) {
            return $environments[$selected_env]['api_url'];
        }
        
        // Default to production if environment not found
        return $environments['production']['api_url'];
    }
}

/**
 * Fetches order progress timeline from the API
 * 
 * @param string $order_number The order number to fetch progress for
 * @return array The order progress data or error information
 */
if (!function_exists('ggt_fetch_order_progress')) {
    function ggt_fetch_order_progress($order_number) {
        // Validate input
        if (empty($order_number)) {
            return array('error' => 'Order number is required');
        }
        
        $endpoint = 'integrations/connx/search/by-order/' . urlencode($order_number);
        
        // Log the API request attempt
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info('Fetching order progress', [
                'source' => 'ggt-api',
                'order_number' => $order_number,
                'endpoint' => $endpoint
            ]);
        }
        
        // Use existing API connection function
        $response = ggt_sinappsus_connect_to_api($endpoint);
        
        // Process the response
        if (isset($response['error'])) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Order progress API error', [
                    'source' => 'ggt-api',
                    'order_number' => $order_number,
                    'error' => $response['error']
                ]);
            }
            return $response;
        }
        
        // Check if it's a 404 with a message (valid "not found" response)
        if (isset($response['status_code']) && $response['status_code'] === 404 && isset($response['message'])) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info('Order progress - no transactions found', [
                    'source' => 'ggt-api',
                    'order_number' => $order_number,
                    'message' => $response['message']
                ]);
            }
            // Return the response as-is - it's a valid "not found" response
            return $response;
        }
        
        return $response;
    }
}
