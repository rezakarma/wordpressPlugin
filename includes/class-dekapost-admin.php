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
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting(
            'dekapost_shipping_settings',
            'dekapost_shipping_settings',
            array($this, 'validate_settings')
        );
    }

    public function validate_settings($input) {
        $validated = array();
        
        // Validate username
        $validated['api_username'] = sanitize_text_field($input['api_username']);
        
        // Validate password
        $validated['api_password'] = sanitize_text_field($input['api_password']);
        
        // If credentials changed, clear token
        $current_settings = get_option('dekapost_shipping_settings');
        if ($current_settings['api_username'] !== $validated['api_username'] || 
            $current_settings['api_password'] !== $validated['api_password']) {
            $validated['api_token'] = '';
            $validated['api_token_expiry'] = '';
        } else {
            $validated['api_token'] = $current_settings['api_token'];
            $validated['api_token_expiry'] = $current_settings['api_token_expiry'];
        }
        
        return $validated;
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
        
        // Get cities
        $cities = $this->api->get_cities();
        
        // Get contract properties
        $contracts = $this->api->get_contracts(0); // Default to 0 for initial load
        
        // Include admin template
        include DEKAPOST_SHIPPING_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function handle_excel_upload() {
        check_ajax_referer('dekapost-shipping-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Unauthorized access'
            ));
        }

        if (!isset($_FILES['excel_file'])) {
            wp_send_json_error(array(
                'message' => 'No file uploaded'
            ));
        }

        $file = $_FILES['excel_file'];
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['ext'], array('xlsx', 'xls'))) {
            wp_send_json_error(array(
                'message' => 'Invalid file type. Please upload an Excel file (.xlsx or .xls)'
            ));
        }

        // Load PhpSpreadsheet
        require_once DEKAPOST_SHIPPING_PLUGIN_DIR . 'vendor/autoload.php';

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Get header row
            $headers = array_shift($data);

            // Define the expected column order
            $expected_columns = array(
                'sourceCity',
                'sourceCityID',
                'destCity',
                'destCityID',
                'stateName',
                'parcelType',
                'parcelTypeID',
                'boxID',
                'boxLength',
                'boxWidth',
                'boxHeight',
                'lstSideServices',
                'sideServiceID',
                'contents',
                'contentAmount',
                'weight',
                'customerHasBox',
                'needPacking',
                'sendPlaceFlag',
                'receiverFirstName',
                'receiverLastName',
                'receiverNID',
                'receiverMobile',
                'receiverPhone',
                'receiverZone',
                'receiverStreet',
                'receiverAddress',
                'receiverPostalCode',
                'senderFirstName',
                'senderLastName',
                'senderNID',
                'senderMobile',
                'senderPhone',
                'senderZone',
                'senderStreet',
                'senderAddress',
                'senderPostalCode',
                'serialNo'
            );

            // Process data
            $parcels_data = array();
            foreach ($data as $row) {
                $parcel = array();
                foreach ($expected_columns as $index => $column) {
                    $parcel[$column] = $row[$index] ?? '';
                }
                $parcels_data[] = $parcel;
            }

            // Process data for API
            $processed_data = $this->api->process_excel_data($parcels_data);

            // Calculate price
            $price_data = array(
                'isContract' => true,
                '_GetPriceWithList_IPList' => array_map(function($parcel) {
                    return array(
                        'appTypeID' => 3,
                        'uniqueID' => 0,
                        'serviceID' => 4,
                        'weight' => intval($parcel['weight']),
                        'parcelTypeID' => intval($parcel['parcelTypeID']),
                        'sourceCityID' => intval($parcel['sourceCityID']),
                        'destCityID' => intval($parcel['destCityID']),
                        'sideServicesIDs' => array(
                            array('sideServiceID' => intval($parcel['sideServiceID']))
                        ),
                        'tlS_ID' => 1,
                        'contentAmount' => $parcel['contentAmount'],
                        'outsizeFlag' => 0,
                        'boxID' => intval($parcel['boxID']),
                        'customerHasBox' => intval($parcel['customerHasBox']),
                        'needPacking' => intval($parcel['needPacking']),
                        'boxLength' => intval($parcel['boxLength']),
                        'boxWidth' => intval($parcel['boxWidth']),
                        'boxHeight' => intval($parcel['boxHeight']),
                        'quantity' => 1,
                        'sendPlaceFlag' => intval($parcel['sendPlaceFlag']),
                        'payTypeID' => 1,
                        'contractID' => intval($parcel['contractID']),
                        'sameSenderReceiver' => 0
                    );
                }, $processed_data)
            );

            $response = $this->api->calculate_price($price_data);
            
            if ($response['status']) {
                wp_send_json_success(array(
                    'message' => $response['message'],
                    'data' => $response['data'],
                    'parcels' => $processed_data
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $response['message'] ?? 'Failed to calculate price'
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error processing Excel file: ' . $e->getMessage()
            ));
        }
    }

    public function handle_save_parcels() {
        check_ajax_referer('dekapost-shipping-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Unauthorized access'
            ));
        }

        $parcels_data = json_decode(stripslashes($_POST['parcels_data']), true);
        
        if (!$parcels_data) {
            wp_send_json_error(array(
                'message' => 'Invalid parcels data'
            ));
        }

        $response = $this->api->save_parcels($parcels_data);
        
        if ($response['status']) {
            wp_send_json_success(array(
                'message' => $response['message'],
                'data' => $response['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $response['message'] ?? 'Failed to save parcels'
            ));
        }
    }
} 