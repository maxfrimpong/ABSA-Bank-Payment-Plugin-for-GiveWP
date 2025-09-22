<?php
/**
 * Plugin Name: Absa Pay Gateway
 * Plugin URI: https://yourwebsite.com/absa-pay-gateway
 * Description: Accept payments via Absa Pay in your WooCommerce store.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: absa-pay-gateway
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * 
 * WC requires at least: 5.0
 * WC tested up to: 6.0
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('ABSA_PAY_GATEWAY_VERSION', '1.0.0');
define('ABSA_PAY_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ABSA_PAY_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error notice"><p>' . esc_html__('Absa Pay Gateway requires WooCommerce to be installed and active.', 'absa-pay-gateway') . '</p></div>';
    });
    return;
}

// Load the gateway class
add_action('plugins_loaded', 'init_absa_pay_gateway');
function init_absa_pay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once ABSA_PAY_GATEWAY_PLUGIN_DIR . 'includes/class-absa-pay-gateway.php';
    require_once ABSA_PAY_GATEWAY_PLUGIN_DIR . 'includes/absa-pay-api-handler.php';

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_absa_pay_gateway');
    function add_absa_pay_gateway($gateways) {
        $gateways[] = 'WC_Absa_Pay_Gateway';
        return $gateways;
    }
}

// Add settings link
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'absa_pay_gateway_plugin_links');
function absa_pay_gateway_plugin_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=absa_pay') . '">' . __('Settings', 'absa-pay-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Load text domain
add_action('init', 'absa_pay_gateway_load_textdomain');
function absa_pay_gateway_load_textdomain() {
    load_plugin_textdomain('absa-pay-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}