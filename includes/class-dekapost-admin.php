<?php

if (!defined('ABSPATH')) {
    exit;
}

class Dekapost_Admin {
    private $api;

    public function __construct() {
        $this->api = new Dekapost_API();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dekapost_upload_excel', array($this, 'handle_excel_upload'));
        add_action('wp_ajax_dekapost_save_parcels', array($this, 'handle_save_parcels'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Dekapost Shipping', 'dekapost-shipping'),
            __('Dekapost Shipping', 'dekapost-shipping'),
            'manage_options',
            'dekapost-shipping',
            array($this, 'render_admin_page'),
            'dashicons-cart',
            56
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_dekapost-shipping' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'dekapost-admin-style',
            DEKAPOST_SHIPPING_PLUGIN_URL . 'css/admin.css',
            array(),
            DEKAPOST_SHIPPING_VERSION
        );

        wp_enqueue_script(
            'dekapost-admin-script',
            DEKAPOST_SHIPPING_PLUGIN_URL . 'js/admin.js',
            array('jquery'),
            DEKAPOST_SHIPPING_VERSION,
            true
        );

        wp_localize_script('dekapost-admin-script', 'dekapostShipping', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dekapost-shipping-nonce')
        ));
    }

    public function render_admin_page() {
        // Check if user is logged in and has admin privileges
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get settings
        $settings = get_option('dekapost_shipping_settings');
        
        // Get states
        $states = $this->api->get_states();
        
        // Get contract properties
        $contracts = $this->api->get_contract_properties();
        
        // Include admin template
        include DEKAPOST_SHIPPING_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function handle_excel_upload() {
        check_ajax_referer('dekapost-shipping-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!isset($_FILES['excel_file'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['excel_file'];
        
        // Process Excel file and prepare data for API
        // This is a placeholder - you'll need to implement Excel processing
        $parcels_data = array(
            'isContract' => true,
            '_GetPriceWithList_IPList' => array()
        );

        // Calculate price
        $response = $this->api->calculate_price($parcels_data);
        
        if ($response) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error('Failed to calculate price');
        }
    }

    public function handle_save_parcels() {
        check_ajax_referer('dekapost-shipping-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $parcels_data = json_decode(stripslashes($_POST['parcels_data']), true);
        
        if (!$parcels_data) {
            wp_send_json_error('Invalid parcels data');
        }

        $response = $this->api->save_parcels($parcels_data);
        
        if ($response) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error('Failed to save parcels');
        }
    }
} 