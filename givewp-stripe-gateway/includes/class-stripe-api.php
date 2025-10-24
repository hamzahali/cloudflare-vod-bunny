<?php
/**
 * Stripe API Class
 *
 * Handles all Stripe API interactions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GiveWP_Stripe_API {

    /**
     * API base URL
     */
    const API_BASE = 'https://api.stripe.com/v1/';

    /**
     * Get API key based on mode
     */
    public static function get_api_key() {
        $mode = give_is_test_mode() ? 'test' : 'live';
        return give_get_option("givewp_stripe_gateway_{$mode}_secret_key");
    }

    /**
     * Get publishable key based on mode
     */
    public static function get_publishable_key() {
        $mode = give_is_test_mode() ? 'test' : 'live';
        return give_get_option("givewp_stripe_gateway_{$mode}_publishable_key");
    }

    /**
     * Make API request to Stripe
     */
    private static function request($endpoint, $method = 'POST', $data = array()) {
        $api_key = self::get_api_key();

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Stripe API key is not set.', 'givewp-stripe-gateway'));
        }

        $url = self::API_BASE . $endpoint;

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 30,
        );

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown Stripe error', 'givewp-stripe-gateway');
            return new WP_Error('stripe_error', $error_message, $data);
        }

        return $data;
    }

    /**
     * Create a PaymentIntent
     */
    public static function create_payment_intent($amount, $currency, $metadata = array(), $description = '') {
        $data = array(
            'amount'      => $amount, // Amount in cents
            'currency'    => strtolower($currency),
            'description' => $description,
            'metadata'    => $metadata,
            'automatic_payment_methods' => array(
                'enabled' => 'true',
            ),
        );

        return self::request('payment_intents', 'POST', $data);
    }

    /**
     * Confirm a PaymentIntent
     */
    public static function confirm_payment_intent($payment_intent_id, $payment_method = null) {
        $data = array();

        if ($payment_method) {
            $data['payment_method'] = $payment_method;
        }

        return self::request('payment_intents/' . $payment_intent_id . '/confirm', 'POST', $data);
    }

    /**
     * Retrieve a PaymentIntent
     */
    public static function retrieve_payment_intent($payment_intent_id) {
        return self::request('payment_intents/' . $payment_intent_id, 'GET');
    }

    /**
     * Create a charge (legacy method, but still supported)
     */
    public static function create_charge($amount, $currency, $source, $metadata = array(), $description = '') {
        $data = array(
            'amount'      => $amount,
            'currency'    => strtolower($currency),
            'source'      => $source,
            'description' => $description,
            'metadata'    => $metadata,
        );

        return self::request('charges', 'POST', $data);
    }

    /**
     * Create a customer
     */
    public static function create_customer($email, $name = '', $metadata = array()) {
        $data = array(
            'email'    => $email,
            'name'     => $name,
            'metadata' => $metadata,
        );

        return self::request('customers', 'POST', $data);
    }

    /**
     * Create a payment method
     */
    public static function create_payment_method($type, $card_data) {
        $data = array(
            'type' => $type,
            'card' => $card_data,
        );

        return self::request('payment_methods', 'POST', $data);
    }

    /**
     * Attach payment method to customer
     */
    public static function attach_payment_method($payment_method_id, $customer_id) {
        $data = array(
            'customer' => $customer_id,
        );

        return self::request('payment_methods/' . $payment_method_id . '/attach', 'POST', $data);
    }

    /**
     * Create a refund
     */
    public static function create_refund($charge_id, $amount = null, $reason = '') {
        $data = array(
            'charge' => $charge_id,
        );

        if ($amount) {
            $data['amount'] = $amount;
        }

        if ($reason) {
            $data['reason'] = $reason;
        }

        return self::request('refunds', 'POST', $data);
    }

    /**
     * Retrieve a charge
     */
    public static function retrieve_charge($charge_id) {
        return self::request('charges/' . $charge_id, 'GET');
    }

    /**
     * Construct webhook event from request
     */
    public static function construct_webhook_event($payload, $signature) {
        $webhook_secret = give_get_option('givewp_stripe_gateway_webhook_secret');

        if (empty($webhook_secret)) {
            return new WP_Error('no_webhook_secret', __('Webhook secret is not set.', 'givewp-stripe-gateway'));
        }

        // Simple signature verification
        $timestamp = time();
        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);

        // Note: This is a simplified version. For production, use Stripe's official library
        // or implement the full webhook signature verification process

        return json_decode($payload, true);
    }
}
