<?php 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
$environments = array();
$environments["sandbox"]     =    array(
    "api_url"    =>    "https://api.gogeothermal.co.uk/api",
);

$environments["production"] =    array(
    "api_url"    =>    "https://api.gogeothermal.co.uk/api",
);