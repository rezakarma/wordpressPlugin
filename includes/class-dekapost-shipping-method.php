<?php

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WC_Shipping_Method')) {
    return;
}

class Dekapost_Shipping_Method extends WC_Shipping_Method {
    private $api;

    public function __construct($instance_id = 0) {
        $this->id = 'dekapost_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Dekapost Shipping', 'dekapost-shipping');
        $this->method_description = __('Shipping method for Dekapost delivery service', 'dekapost-shipping');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
        $this->api = new Dekapost_API();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Method Title', 'dekapost-shipping'),
                'type' => 'text',
                'default' => __('Dekapost Shipping', 'dekapost-shipping'),
            ),
            'api_username' => array(
                'title' => __('API Username', 'dekapost-shipping'),
                'type' => 'text',
                'default' => '',
            ),
            'api_password' => array(
                'title' => __('API Password', 'dekapost-shipping'),
                'type' => 'password',
                'default' => '',
            ),
        );
    }

    public function calculate_shipping($package = array()) {
        // Get the destination city
        $destination_city = $package['destination']['city'];
        
        // Get the cart items
        $items = WC()->cart->get_cart();
        
        // Prepare parcels data
        $parcels_data = array(
            'isContract' => true,
            '_GetPriceWithList_IPList' => array()
        );

        foreach ($items as $item) {
            $product = $item['data'];
            $weight = $product->get_weight() ? $product->get_weight() * 1000 : 1000; // Convert to grams
            
            $parcels_data['_GetPriceWithList_IPList'][] = array(
                'appTypeID' => 3,
                'uniqueID' => 0,
                'serviceID' => 4,
                'weight' => $weight,
                'parcelTypeID' => 2,
                'sourceCityID' => 473, // Default source city
                'destCityID' => $this->get_city_id($destination_city),
                'sideServicesIDs' => array(
                    array('sideServiceID' => 0)
                ),
                'tlS_ID' => 1,
                'contentAmount' => $product->get_price(),
                'outsizeFlag' => 0,
                'boxID' => 1,
                'customerHasBox' => 0,
                'needPacking' => 1,
                'boxLength' => 0,
                'boxWidth' => 0,
                'boxHeight' => 0,
                'quantity' => $item['quantity'],
                'sendPlaceFlag' => 0,
                'payTypeID' => 1,
                'contractID' => 1,
                'sameSenderReceiver' => 0
            );
        }

        // Calculate shipping cost
        $response = $this->api->calculate_price($parcels_data);
        
        if ($response && isset($response['data'][0]['totalAmount'])) {
            $cost = $response['data'][0]['totalAmount'] / 10; // Convert to main currency unit
            
            $this->add_rate(array(
                'id' => $this->id,
                'label' => $this->title,
                'cost' => $cost,
                'package' => $package,
            ));
        }
    }

    private function get_city_id($city_name) {
        // This should be implemented to map city names to IDs
        // For now, returning a default value
        return 473;
    }
} 