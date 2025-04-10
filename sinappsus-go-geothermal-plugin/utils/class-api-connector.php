<?php 

// Connect to the API
function ggt_sinappsus_connect_to_api($endpoint, $data = array(), $method = 'GET') {
    // Get the base URL from the selected environment
    $url = ggt_get_api_base_url() . '/' . ltrim($endpoint, '/');
    
    $args = array(
        'method'     => $method,
        'timeout'    => 30,
        'headers'    => array(
            'Authorization' => 'Bearer ' . ggt_get_decrypted_token(),
        ),
    );
    
    if (strtoupper($method) === 'GET') {
        $args['body'] = $data; 
    } else {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body']                     = json_encode($data);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        error_log('API call failed for ' . $url . ': ' . $response->get_error_message());
        return array('error' => $response->get_error_message());
    }

    return json_decode(wp_remote_retrieve_body($response), true);
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
    
    // Log success
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info('Successfully fetched order progress', [
            'source' => 'ggt-api',
            'order_number' => $order_number,
            'events_count' => is_array($response) ? count($response) : 'not an array'
        ]);
    }
    
    // If empty response or invalid format
    if (empty($response) || !is_array($response)) {
        return array('events' => []);
    }
    
    // Format the response similar to the Vue.js frontend
    $formatted_events = [];
    foreach ($response as $event) {
        $formatted_events[] = [
            'id' => $event['id'] ?? '',
            'status' => $event['transactionStatus'] ?? 'unknown',
            'formattedDate' => isset($event['created_at']) ? date('d M Y H:i', strtotime($event['created_at'])) : '',
            'courier' => $event['courier'] ?? '',
            'courierReference' => $event['courierReference'] ?? '',
            'batch' => $event['batch'] ?? '',
            'meta' => $event['meta'] ?? null,
            'created_at' => $event['created_at'] ?? '',
        ];
    }
    
    return array('events' => $formatted_events);
}

/**
 * Helper function to decrypt API token
 * @return string|bool Decrypted token or false if not found
 */
function ggt_get_decrypted_token() {
    $encrypted_token = get_option('sinappsus_gogeo_codex');
    if ($encrypted_token) {
        return openssl_decrypt($encrypted_token, 'aes-256-cbc', AUTH_KEY, 0, AUTH_SALT);
    }
    return false;
}

/**
 * Helper function to log API interactions
 * 
 * @param string $message The log message
 * @param string $level The log level (info, error, etc)
 * @param array $context Additional context data
 */
function ggt_log_api_interaction($message, $level = 'info', $context = []) {
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->log($level, $message, array_merge(['source' => 'ggt-api'], $context));
    } else {
        error_log("GGT API [{$level}]: {$message} - " . json_encode($context));
    }
}
