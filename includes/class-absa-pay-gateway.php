<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Absa_Pay_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'absa_pay';
        $this->icon = apply_filters('absa_pay_gateway_icon', '');
        $this->has_fields = false;
        $this->method_title = __('Absa Pay', 'absa-pay-gateway');
        $this->method_description = __('Accept payments via Absa Pay. Customers will be redirected to Absa Pay to complete their payment.', 'absa-pay-gateway');
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->api_key = $this->get_option('api_key');
        $this->api_secret = $this->get_option('api_secret');
        $this->redirect_page_id = $this->get_option('redirect_page_id');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_absa_pay_gateway', array($this, 'check_absa_pay_response'));
        add_action('woocommerce_receipt_absa_pay', array($this, 'receipt_page'));
        
        // Check if the gateway is enabled
        if (!$this->is_valid_for_use()) {
            $this->enabled = 'no';
        }
    }
    
    /**
     * Check if this gateway is enabled and available in the user's country
     */
    public function is_valid_for_use() {
        // Add any currency or country restrictions here
        return in_array(get_woocommerce_currency(), array('ZAR', 'USD', 'EUR', 'GBP'));
    }
    
    /**
     * Admin Panel Options
     */
    public function admin_options() {
        if ($this->is_valid_for_use()) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e('Gateway disabled', 'absa-pay-gateway'); ?></strong>: 
                    <?php esc_html_e('Absa Pay does not support your store currency.', 'absa-pay-gateway'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'absa-pay-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Absa Pay', 'absa-pay-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'absa-pay-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'absa-pay-gateway'),
                'default'     => __('Absa Pay', 'absa-pay-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'absa-pay-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'absa-pay-gateway'),
                'default'     => __('Pay securely via Absa Pay.', 'absa-pay-gateway'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'absa-pay-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable Test Mode', 'absa-pay-gateway'),
                'default'     => 'yes',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'absa-pay-gateway'),
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'absa-pay-gateway'),
                'type'        => 'text',
                'description' => __('Get your Merchant ID from your Absa Pay account.', 'absa-pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_key' => array(
                'title'       => __('API Key', 'absa-pay-gateway'),
                'type'        => 'text',
                'description' => __('Get your API Key from your Absa Pay account.', 'absa-pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_secret' => array(
                'title'       => __('API Secret', 'absa-pay-gateway'),
                'type'        => 'password',
                'description' => __('Get your API Secret from your Absa Pay account.', 'absa-pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'redirect_page_id' => array(
                'title'       => __('Return Page', 'absa-pay-gateway'),
                'type'        => 'select',
                'options'     => $this->get_pages('Select Page'),
                'description' => __('URL of success page', 'absa-pay-gateway'),
                'desc_tip'    => true,
            )
        );
    }
    
    /**
     * Get all WordPress pages
     */
    public function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) {
            $page_list[] = $title;
        }
        foreach ($wp_pages as $page) {
            $prefix = '';
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_post($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
    
    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }
    
    /**
     * Receipt page
     */
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        
        // Generate payment request
        $absa_pay_args = $this->generate_absa_pay_args($order);
        
        // Submit to Absa Pay
        $this->submit_absa_pay_request($absa_pay_args);
    }
    
    /**
     * Generate Absa Pay payment arguments
     */
    public function generate_absa_pay_args($order) {
        $order_id = $order->get_id();
        $redirect_url = $this->get_return_url($order);
        $notify_url = WC()->api_request_url('WC_Absa_Pay_Gateway');
        
        // Format amount to match Absa Pay requirements
        $amount = number_format($order->get_total(), 2, '.', '');
        
        $args = array(
            'merchant_id'    => $this->merchant_id,
            'amount'         => $amount,
            'currency'       => $order->get_currency(),
            'order_id'       => $order_id,
            'reference'      => 'WC-' . $order_id,
            'customer_email' => $order->get_billing_email(),
            'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'return_url'     => $redirect_url,
            'callback_url'   => $notify_url,
            'timestamp'      => time(),
        );
        
        // Generate signature
        $args['signature'] = $this->generate_signature($args);
        
        return $args;
    }
    
    /**
     * Generate signature for request
     */
    public function generate_signature($params) {
        // Sort parameters alphabetically
        ksort($params);
        
        // Create string to sign
        $string_to_sign = '';
        foreach ($params as $key => $value) {
            $string_to_sign .= $key . $value;
        }
        
        // Add API secret to the string
        $string_to_sign .= $this->api_secret;
        
        // Generate hash
        return hash('sha256', $string_to_sign);
    }
    
    /**
     * Submit payment request to Absa Pay
     */
    public function submit_absa_pay_request($args) {
        $absa_pay_url = $this->testmode ? 'https://test.absa.co.za/api/payment/initiate' : 'https://api.absa.co.za/api/payment/initiate';
        
        echo '<p>' . __('Thank you for your order, please click the button below to pay with Absa Pay.', 'absa-pay-gateway') . '</p>';
        
        echo '<form action="' . esc_url($absa_pay_url) . '" method="post" id="absa_pay_payment_form">';
        foreach ($args as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
        }
        echo '<input type="submit" class="button alt" value="' . __('Pay via Absa Pay', 'absa-pay-gateway') . '" />';
        echo '<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'absa-pay-gateway') . '</a>';
        echo '</form>';
        
        // Auto-submit form in production
        if (!$this->testmode) {
            wc_enqueue_js('
                jQuery("#absa_pay_payment_form").submit();
            ');
        }
    }
    
    /**
     * Check for valid Absa Pay response
     */
    public function check_absa_pay_response() {
        if (isset($_REQUEST['order_id'])) {
            $order_id = intval($_REQUEST['order_id']);
            $order = wc_get_order($order_id);
            
            // Verify the signature
            $received_signature = isset($_REQUEST['signature']) ? $_REQUEST['signature'] : '';
            $expected_signature = $this->generate_signature($_REQUEST);
            
            if ($received_signature === $expected_signature) {
                // Signature is valid, process the response
                $status = isset($_REQUEST['status']) ? strtolower($_REQUEST['status']) : '';
                
                if ($status === 'success') {
                    // Payment was successful
                    $order->payment_complete();
                    $order->add_order_note(__('Absa Pay payment completed successfully.', 'absa-pay-gateway'));
                    
                    // Empty cart
                    WC()->cart->empty_cart();
                    
                    // Redirect to thank you page
                    wp_redirect($this->get_return_url($order));
                    exit;
                } else {
                    // Payment failed
                    $order->update_status('failed', __('Payment failed via Absa Pay.', 'absa-pay-gateway'));
                    
                    // Add error notice
                    wc_add_notice(__('Payment failed. Please try again.', 'absa-pay-gateway'), 'error');
                    
                    // Redirect to checkout page
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            } else {
                // Invalid signature
                $order->update_status('failed', __('Absa Pay payment verification failed: invalid signature.', 'absa-pay-gateway'));
                
                // Add error notice
                wc_add_notice(__('Payment verification failed. Please contact support.', 'absa-pay-gateway'), 'error');
                
                // Redirect to checkout page
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        }
        
        // If we got here, something went wrong
        wp_redirect(wc_get_checkout_url());
        exit;
    }
}