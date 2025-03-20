<?php 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
$environments = array();
$environments["staging"] = array(
    "api_url" => "https://api-staging.gogeothermal.co.uk/api",
);

$environments["production"] = array(
    "api_url" => "https://api.gogeothermal.co.uk/api",
);

// Define a constant for backwards compatibility
if (!defined('GGT_SINAPPSUS_API_URL')) {
    $selected_env = get_option('ggt_sinappsus_environment', 'production');
    define('GGT_SINAPPSUS_API_URL', $environments[$selected_env]['api_url']);
}