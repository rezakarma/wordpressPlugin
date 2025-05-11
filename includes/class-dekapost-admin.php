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
        add_action('wp_ajax_dekapost_get_contracts', array($this, 'handle_get_contracts'));
        
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
        
        // Get cities from API
        $cities_response = $this->api->get_cities();
        
        if ($cities_response['status'] && isset($cities_response['data']['addressData'])) {
            // Store cities in WordPress options
            update_option('dekapost_cities', $cities_response['data']['addressData']);
            $cities = $cities_response['data']['addressData'];
        } else {
            $cities = array();
            if (!$cities_response['status']) {
                add_settings_error(
                    'dekapost_shipping',
                    'cities_error',
                    $cities_response['message'] ?? 'Failed to load cities. Please check your API credentials.',
                    'error'
                );
            }
        }
        
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
        
        // Debug file information
        error_log('File upload details: ' . print_r($file, true));
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['ext'], array('xlsx', 'xls'))) {
            wp_send_json_error(array(
                'message' => 'Invalid file type. Please upload an Excel file (.xlsx or .xls)'
            ));
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'File upload error: ';
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_message .= 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message .= 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message .= 'The uploaded file was only partially uploaded';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message .= 'No file was uploaded';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message .= 'Missing a temporary folder';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message .= 'Failed to write file to disk';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message .= 'A PHP extension stopped the file upload';
                    break;
                default:
                    $error_message .= 'Unknown upload error';
            }
            wp_send_json_error(array('message' => $error_message));
        }

        // Load PhpSpreadsheet
        require_once DEKAPOST_SHIPPING_PLUGIN_DIR . 'vendor/autoload.php';

        try {
            error_log('Attempting to load Excel file: ' . $file['tmp_name']);
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            error_log('Excel data loaded. Number of rows: ' . count($data));

            // Skip header row and process data
            array_shift($data);
            
            // Process data based on column order
            $parcels_data = array();
            foreach ($data as $index => $row) {
                if (count($row) >= 6) { // Ensure we have enough columns
                    $parcel = array(
                        'sourceCity' => $row[0] ?? '',
                        'sourceCityID' => $row[1] ?? '',
                        'destCity' => $row[2] ?? '',
                        'destCityID' => $row[3] ?? '',
                        'stateName' => $row[4] ?? '',
                        'parcelType' => $row[5] ?? '',
                        'parcelTypeID' => $row[6] ?? '',
                        'boxID' => $row[7] ?? '',
                        'boxLength' => $row[8] ?? '',
                        'boxWidth' => $row[9] ?? '',
                        'boxHeight' => $row[10] ?? '',
                        'lstSideServices' => $row[11] ?? '',
                        'sideServiceID' => $row[12] ?? '',
                        'contents' => $row[13] ?? '',
                        'contentAmount' => $row[14] ?? '',
                        'weight' => $row[15] ?? '',
                        'customerHasBox' => $row[16] ?? '',
                        'needPacking' => $row[17] ?? '',
                        'sendPlaceFlag' => $row[18] ?? '',
                        'receiverFirstName' => $row[19] ?? '',
                        'receiverLastName' => $row[20] ?? '',
                        'receiverNID' => $row[21] ?? '',
                        'receiverMobile' => $row[22] ?? '',
                        'receiverPhone' => $row[23] ?? '',
                        'receiverZone' => $row[24] ?? '',
                        'receiverStreet' => $row[25] ?? '',
                        'receiverAddress' => $row[26] ?? '',
                        'receiverPostalCode' => $row[27] ?? '',
                        'senderFirstName' => $row[28] ?? '',
                        'senderLastName' => $row[29] ?? '',
                        'senderNID' => $row[30] ?? '',
                        'senderMobile' => $row[31] ?? '',
                        'senderPhone' => $row[32] ?? '',
                        'senderZone' => $row[33] ?? '',
                        'senderStreet' => $row[34] ?? '',
                        'senderAddress' => $row[35] ?? '',
                        'senderPostalCode' => $row[36] ?? '',
                        'serialNo' => $row[37] ?? ''
                    );
                    $parcels_data[] = $parcel;
                } else {
                    error_log('Row ' . ($index + 2) . ' skipped: insufficient columns');
                }
            }

            error_log('Processed ' . count($parcels_data) . ' parcels');

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
            error_log('Excel processing error: ' . $e->getMessage());
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

    public function handle_get_contracts() {
        check_ajax_referer('dekapost-shipping-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Unauthorized access'
            ));
        }

        $city_id = isset($_POST['city_id']) ? intval($_POST['city_id']) : 0;
        
        if (empty($city_id)) {
            wp_send_json_error(array(
                'message' => 'City ID is required'
            ));
        }

        error_log('AJAX Request - Getting contracts for city ID: ' . $city_id);

        $response = $this->api->get_contracts($city_id);
        
        error_log('API Response for contracts: ' . print_r($response, true));
        
        if ($response['status']) {
            // Ensure we're sending the data in the correct format
            wp_send_json_success(array(
                'contracts' => $response['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $response['message'] ?? 'Failed to get contracts'
            ));
        }
    }
} 