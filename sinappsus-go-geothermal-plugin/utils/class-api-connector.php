<?php 

// Connect to the API
function ggt_sinappsus_connect_to_api($endpoint, $data = array(), $method = 'GET') {
    error_log('ggt_sinappsus_connect_to_api() called');
    $account_key = get_option('ggt_sinappsus_jwt_token');
    $url = GGT_SINAPPSUS_API_URL . $endpoint;

    // Default args
    $args = array(
        'method'  => $method,
        'headers' => array(
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $account_key,
        ),
        // We'll set 'body' below depending on method
    );

    // If it's GET, pass $data as an array so WP creates a query string.
    // For POST/PATCH/PUT, encode it as JSON.
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
