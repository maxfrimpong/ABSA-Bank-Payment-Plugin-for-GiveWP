<?php
if (!defined('ABSPATH')) {
    exit;
}

class Absa_Pay_API_Handler {
    
    private $gateway;
    
    public function __construct($gateway) {
        $this->gateway = $gateway;
    }
    
    /**
     * Make API request to Absa Pay
     */
    public function make_api_request($endpoint, $params = array()) {
        $url = $this->gateway->testmode ? 
            'https://test.absa.co.za/api/' . $endpoint : 
            'https://api.absa.co.za/api/' . $endpoint;
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->gateway->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ),
            'body' => json_encode($params),
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'success' => wp_remote_retrieve_response_code($response) === 200,
            'data'    => $response_body
        );
    }
    
    /**
     * Verify payment status with Absa Pay
     */
    public function verify_payment($transaction_id) {
        $params = array(
            'merchant_id' => $this->gateway->merchant_id,
            'transaction_id' => $transaction_id,
            'timestamp' => time()
        );
        
        // Generate signature
        $params['signature'] = $this->gateway->generate_signature($params);
        
        return $this->make_api_request('payment/verify', $params);
    }
}