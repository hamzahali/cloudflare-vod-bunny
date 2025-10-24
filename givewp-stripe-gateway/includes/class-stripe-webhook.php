<?php
/**
 * Stripe Webhook Handler
 *
 * Handles incoming webhook events from Stripe
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GiveWP_Stripe_Webhook {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_webhook_endpoint'));
        add_action('parse_request', array($this, 'handle_webhook'));
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule(
            '^givewp-stripe-webhook/?$',
            'index.php?givewp_stripe_webhook=1',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'givewp_stripe_webhook';
            return $vars;
        });
    }

    /**
     * Get webhook URL
     */
    public static function get_webhook_url() {
        return home_url('givewp-stripe-webhook');
    }

    /**
     * Handle webhook request
     */
    public function handle_webhook($wp) {
        if (!isset($wp->query_vars['givewp_stripe_webhook']) || $wp->query_vars['givewp_stripe_webhook'] !== '1') {
            return;
        }

        // Get the request body
        $payload = @file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        if (empty($payload)) {
            $this->send_response(400, array('error' => 'No payload received'));
            return;
        }

        // Decode the event
        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->send_response(400, array('error' => 'Invalid JSON payload'));
            return;
        }

        // Log the event
        $this->log_webhook_event($event);

        // Process the event based on type
        $processed = $this->process_webhook_event($event);

        if ($processed) {
            $this->send_response(200, array('success' => true));
        } else {
            $this->send_response(400, array('error' => 'Event processing failed'));
        }

        exit;
    }

    /**
     * Process webhook event
     */
    private function process_webhook_event($event) {
        if (!isset($event['type']) || !isset($event['data']['object'])) {
            return false;
        }

        $event_type = $event['type'];
        $event_data = $event['data']['object'];

        switch ($event_type) {
            case 'payment_intent.succeeded':
                return $this->handle_payment_intent_succeeded($event_data);

            case 'payment_intent.payment_failed':
                return $this->handle_payment_intent_failed($event_data);

            case 'charge.refunded':
                return $this->handle_charge_refunded($event_data);

            case 'charge.succeeded':
                return $this->handle_charge_succeeded($event_data);

            case 'charge.failed':
                return $this->handle_charge_failed($event_data);

            case 'charge.dispute.created':
                return $this->handle_dispute_created($event_data);

            default:
                // Unhandled event type, but we'll return true to acknowledge receipt
                return true;
        }
    }

    /**
     * Handle payment intent succeeded
     */
    private function handle_payment_intent_succeeded($payment_intent) {
        $payment_id = $this->get_payment_id_from_intent($payment_intent['id']);

        if (!$payment_id) {
            return false;
        }

        $current_status = give_get_payment_status($payment_id);

        if ($current_status === 'publish') {
            return true; // Already completed
        }

        // Update payment status
        give_update_payment_status($payment_id, 'publish');

        // Store charge ID if available
        if (isset($payment_intent['charges']['data'][0]['id'])) {
            give_update_payment_meta($payment_id, '_give_stripe_charge_id', $payment_intent['charges']['data'][0]['id']);
        }

        give_set_payment_transaction_id($payment_id, $payment_intent['id']);

        return true;
    }

    /**
     * Handle payment intent failed
     */
    private function handle_payment_intent_failed($payment_intent) {
        $payment_id = $this->get_payment_id_from_intent($payment_intent['id']);

        if (!$payment_id) {
            return false;
        }

        give_update_payment_status($payment_id, 'failed');

        $error_message = isset($payment_intent['last_payment_error']['message'])
            ? $payment_intent['last_payment_error']['message']
            : __('Payment failed', 'givewp-stripe-gateway');

        give_insert_payment_note($payment_id, sprintf(__('Payment failed: %s', 'givewp-stripe-gateway'), $error_message));

        return true;
    }

    /**
     * Handle charge refunded
     */
    private function handle_charge_refunded($charge) {
        $payment_id = $this->get_payment_id_from_charge($charge['id']);

        if (!$payment_id) {
            return false;
        }

        if ($charge['refunded'] && $charge['amount_refunded'] === $charge['amount']) {
            // Full refund
            give_update_payment_status($payment_id, 'refunded');
            give_insert_payment_note($payment_id, __('Payment fully refunded via Stripe webhook.', 'givewp-stripe-gateway'));
        } else {
            // Partial refund
            $refund_amount = $charge['amount_refunded'] / 100;
            give_insert_payment_note(
                $payment_id,
                sprintf(__('Partial refund of %s via Stripe webhook.', 'givewp-stripe-gateway'), give_currency_filter(give_format_amount($refund_amount)))
            );
        }

        return true;
    }

    /**
     * Handle charge succeeded
     */
    private function handle_charge_succeeded($charge) {
        $payment_id = $this->get_payment_id_from_charge($charge['id']);

        if (!$payment_id) {
            // Try to get payment ID from metadata
            if (isset($charge['metadata']['donation_id'])) {
                $payment_id = intval($charge['metadata']['donation_id']);
            }
        }

        if (!$payment_id) {
            return false;
        }

        $current_status = give_get_payment_status($payment_id);

        if ($current_status === 'publish') {
            return true; // Already completed
        }

        give_update_payment_status($payment_id, 'publish');
        give_update_payment_meta($payment_id, '_give_stripe_charge_id', $charge['id']);

        return true;
    }

    /**
     * Handle charge failed
     */
    private function handle_charge_failed($charge) {
        $payment_id = $this->get_payment_id_from_charge($charge['id']);

        if (!$payment_id) {
            if (isset($charge['metadata']['donation_id'])) {
                $payment_id = intval($charge['metadata']['donation_id']);
            }
        }

        if (!$payment_id) {
            return false;
        }

        give_update_payment_status($payment_id, 'failed');

        $error_message = isset($charge['failure_message'])
            ? $charge['failure_message']
            : __('Charge failed', 'givewp-stripe-gateway');

        give_insert_payment_note($payment_id, sprintf(__('Charge failed: %s', 'givewp-stripe-gateway'), $error_message));

        return true;
    }

    /**
     * Handle dispute created
     */
    private function handle_dispute_created($dispute) {
        $charge_id = $dispute['charge'];
        $payment_id = $this->get_payment_id_from_charge($charge_id);

        if (!$payment_id) {
            return false;
        }

        give_insert_payment_note(
            $payment_id,
            sprintf(
                __('A dispute was filed for this payment. Reason: %s. Amount: %s', 'givewp-stripe-gateway'),
                $dispute['reason'],
                give_currency_filter(give_format_amount($dispute['amount'] / 100))
            )
        );

        return true;
    }

    /**
     * Get payment ID from payment intent ID
     */
    private function get_payment_id_from_intent($intent_id) {
        global $wpdb;

        $payment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_give_stripe_payment_intent_id' AND meta_value = %s LIMIT 1",
                $intent_id
            )
        );

        return $payment_id ? intval($payment_id) : false;
    }

    /**
     * Get payment ID from charge ID
     */
    private function get_payment_id_from_charge($charge_id) {
        global $wpdb;

        $payment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_give_stripe_charge_id' AND meta_value = %s LIMIT 1",
                $charge_id
            )
        );

        return $payment_id ? intval($payment_id) : false;
    }

    /**
     * Log webhook event
     */
    private function log_webhook_event($event) {
        if (give_get_option('givewp_stripe_gateway_webhook_logging', false)) {
            $log_file = WP_CONTENT_DIR . '/givewp-stripe-webhooks.log';
            $log_entry = date('Y-m-d H:i:s') . ' - ' . $event['type'] . ' - ' . json_encode($event) . "\n";
            error_log($log_entry, 3, $log_file);
        }
    }

    /**
     * Send JSON response
     */
    private function send_response($status_code, $data) {
        status_header($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

// Initialize webhook handler
new GiveWP_Stripe_Webhook();
