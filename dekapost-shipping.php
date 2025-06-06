<?php
/**
 * Plugin Name: Dekapost Shipping
 * Plugin URI: https://dekapost.ir
 * Description: Dekapost shipping management system
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: dekapost-shipping
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DEKAPOST_SHIPPING_VERSION', '1.0.0');
define('DEKAPOST_SHIPPING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEKAPOST_SHIPPING_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once DEKAPOST_SHIPPING_PLUGIN_DIR . 'includes/class-dekapost-api.php';
require_once DEKAPOST_SHIPPING_PLUGIN_DIR . 'includes/class-dekapost-admin.php';

// Initialize the plugin
function dekapost_shipping_init() {
    // Initialize the admin class
    new Dekapost_Admin();
}
add_action('plugins_loaded', 'dekapost_shipping_init');

// Activation hook
register_activation_hook(__FILE__, 'dekapost_shipping_activate');
function dekapost_shipping_activate() {
    // Create necessary database tables and options
    add_option('dekapost_shipping_settings', array(
        'api_username' => '',
        'api_password' => '',
        'api_token' => '',
        'api_token_expiry' => ''
    ));
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'dekapost_shipping_deactivate');
function dekapost_shipping_deactivate() {
    // Cleanup if necessary
} 