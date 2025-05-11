<?php

if (!defined('ABSPATH')) {
    exit;
}

class Dekapost_API {
    private $api_base_url = 'https://services.dekapost.ir/clubapi';
    private $token = null;
    private $token_expiry = null;

    public function __construct() {
        $settings = get_option('dekapost_shipping_settings');
        $this->token = $settings['api_token'] ?? null;
        $this->token_expiry = $settings['api_token_expiry'] ?? null;
    }

    /**
     * Authenticate with the API and get token
     */
    public function authenticate($username, $password) {
        $response = wp_remote_post($this->api_base_url . '/token', array(
            'body' => json_encode(array(
                'username' => $username,
                'password' => $password
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['token'])) {
            $this->token = $body['token'];
            $this->token_expiry = time() + (24 * 60 * 60); // Token expires in 24 hours
            
            // Update settings
            $settings = get_option('dekapost_shipping_settings');
            $settings['api_token'] = $this->token;
            $settings['api_token_expiry'] = $this->token_expiry;
            update_option('dekapost_shipping_settings', $settings);
            
            return true;
        }

        return false;
    }

    /**
     * Get contract properties nodes
     */
    public function get_contract_properties() {
        if (!$this->is_token_valid()) {
            return false;
        }

        $response = wp_remote_post($this->api_base_url . '/api/GetContractPropertiesNodes', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get states (cities) list
     */
    public function get_states() {
        if (!$this->is_token_valid()) {
            return false;
        }

        $response = wp_remote_get($this->api_base_url . '/api/ParcelPrice/GetStates', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Calculate price for parcels
     */
    public function calculate_price($parcels_data) {
        if (!$this->is_token_valid()) {
            return false;
        }

        $response = wp_remote_post($this->api_base_url . '/GetPriceWithList', array(
            'body' => json_encode($parcels_data),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Save parcels
     */
    public function save_parcels($parcels_data) {
        if (!$this->is_token_valid()) {
            return false;
        }

        $response = wp_remote_post($this->api_base_url . '/api/Parcels/SaveParcels', array(
            'body' => json_encode($parcels_data),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Check if token is valid
     */
    private function is_token_valid() {
        if (!$this->token || !$this->token_expiry) {
            return false;
        }

        if (time() >= $this->token_expiry) {
            return false;
        }

        return true;
    }
} 