<?php

if (!defined('ABSPATH')) {
    exit;
}

class Dekapost_API {
    private $api_url = 'https://services.dekapost.ir/clubapi';
    private $token = '';
    private $token_expiry = 0;

    public function __construct() {
        $this->load_token();
    }

    private function load_token() {
        $settings = get_option('dekapost_shipping_settings');
        if (!empty($settings['api_token']) && !empty($settings['api_token_expiry'])) {
            $this->token = $settings['api_token'];
            $this->token_expiry = $settings['api_token_expiry'];
        }
    }

    private function save_token($token, $expiry) {
        $settings = get_option('dekapost_shipping_settings');
        $settings['api_token'] = $token;
        $settings['api_token_expiry'] = $expiry;
        update_option('dekapost_shipping_settings', $settings);
        
        $this->token = $token;
        $this->token_expiry = $expiry;
    }

    private function is_token_valid() {
        return !empty($this->token) && $this->token_expiry > time();
    }

    private function get_token() {
        if (!$this->is_token_valid()) {
            $settings = get_option('dekapost_shipping_settings');
            if (empty($settings['api_username']) || empty($settings['api_password'])) {
                return false;
            }

            $response = wp_remote_post($this->api_url . '/token', array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode(array(
                    'username' => $settings['api_username'],
                    'password' => $settings['api_password']
                ))
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body)) {
                // Decode JWT to get expiry
                $token_parts = explode('.', $body);
                if (count($token_parts) === 3) {
                    $payload = json_decode(base64_decode($token_parts[1]), true);
                    if (isset($payload['exp'])) {
                        $this->save_token($body, $payload['exp']);
                        return $body;
                    }
                }
            }
            return false;
        }
        return $this->token;
    }

    private function make_request($endpoint, $method = 'GET', $data = null) {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            )
        );

        if ($data !== null) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->api_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }

    public function get_states() {
        return $this->make_request('/states');
    }

    public function get_contract_properties() {
        return $this->make_request('/contracts');
    }

    public function calculate_price($parcels_data) {
        return $this->make_request('/calculate-price', 'POST', $parcels_data);
    }

    public function save_parcels($parcels_data) {
        return $this->make_request('/save-parcels', 'POST', $parcels_data);
    }
} 