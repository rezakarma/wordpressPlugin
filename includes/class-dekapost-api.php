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

            $body = wp_remote_retrieve_body($response);
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
            return array(
                'status' => false,
                'message' => 'Authentication failed. Please check your credentials.'
            );
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Referer' => 'https://services.dekapost.ir/'
            )
        );

        if ($data !== null) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->api_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return array(
                'status' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }

    public function get_cities() {
        return $this->make_request('/GetCityForContract');
    }

    public function get_contracts($city_id) {
        if (!$this->is_token_valid()) {
            return array(
                'status' => false,
                'message' => 'Not logged in'
            );
        }

        return $this->make_request('/contracts/' . $city_id);
    }

    public function calculate_price($parcels_data) {
        return $this->make_request('/GetPriceWithList', 'POST', $parcels_data);
    }

    public function save_parcels($parcels_data) {
        return $this->make_request('/api/Parcels/SaveParcels', 'POST', $parcels_data);
    }

    public function process_excel_data($excel_data) {
        $parcels = array();
        
        foreach ($excel_data as $row) {
            $parcel = array(
                'companyID' => 0,
                'serviceID' => 4,
                'serviceType' => 2,
                'contractID' => intval($row['contractID'] ?? 0),
                'parcelTypeID' => intval($row['parcelTypeID'] ?? 0),
                'sourcePostNodeID' => 0,
                'userID' => 0,
                'destCityID' => strval($row['destCityID'] ?? ''),
                'serialNo' => strval($row['serialNo'] ?? ''),
                'senderFirstName' => strval($row['senderFirstName'] ?? ''),
                'senderLastName' => strval($row['senderLastName'] ?? ''),
                'senderAddress' => strval($row['senderAddress'] ?? ''),
                'senderNID' => strval($row['senderNID'] ?? ''),
                'senderPhone' => strval($row['senderPhone'] ?? ''),
                'senderMobile' => strval($row['senderMobile'] ?? ''),
                'senderPostalCode' => strval($row['senderPostalCode'] ?? ''),
                'senderStreet' => strval($row['senderStreet'] ?? ''),
                'senderZone' => strval($row['senderZone'] ?? ''),
                'receiverStreet' => strval($row['receiverStreet'] ?? ''),
                'receiverZone' => strval($row['receiverZone'] ?? ''),
                'receiverFirstName' => strval($row['receiverFirstName'] ?? ''),
                'receiverLastName' => strval($row['receiverLastName'] ?? ''),
                'receiverAddress' => strval($row['receiverAddress'] ?? ''),
                'receiverNID' => strval($row['receiverNID'] ?? ''),
                'receiverPhone' => strval($row['receiverPhone'] ?? ''),
                'receiverMobile' => strval($row['receiverMobile'] ?? ''),
                'receiverPostalCode' => strval($row['receiverPostalCode'] ?? ''),
                'weight' => intval($row['weight'] ?? 0),
                'length' => intval($row['boxLength'] ?? 0),
                'width' => intval($row['boxWidth'] ?? 0),
                'height' => intval($row['boxHeight'] ?? 0),
                'contentAmount' => strval($row['contentAmount'] ?? ''),
                'tlsID' => 1,
                'lstSideServices' => array(
                    array('sideServiceID' => intval($row['sideServiceID'] ?? 0))
                ),
                'boxID' => intval($row['boxID'] ?? 0),
                'outsizeFlag' => 0,
                'contents' => strval($row['contents'] ?? ''),
                'paymentDate' => '',
                'sourceID' => 1,
                'sendPlaceFlag' => intval($row['sendPlaceFlag'] ?? 0),
                'paymentTypeID' => 1,
                'customerHasBox' => intval($row['customerHasBox'] ?? 0),
                'needPacking' => intval($row['needPacking'] ?? 0),
                'appID' => 1,
                'lat' => '35.707401',
                'lon' => '51.369683',
                'characterType' => 1,
                'sameSenderReceiver' => 0,
                'clubUserID' => 0,
                'sourceCityID' => intval($row['sourceCityID'] ?? 0),
                'receiverCustomerCode' => '',
                'senderLockerID' => '',
                'receiverLockerID' => '',
                'suggestedDateTime' => '',
                'parcelID' => 0
            );
            
            $parcels[] = $parcel;
        }
        
        return $parcels;
    }
} 