<?php
/**
 * Stripe Gateway Class
 *
 * Handles payment processing through Stripe
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GiveWP_Stripe_Gateway_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('give_gateway_stripe_custom', array($this, 'process_payment'));
        add_action('give_stripe_custom_cc_form', array($this, 'credit_card_form'));
        add_filter('give_enabled_payment_gateways', array($this, 'check_gateway_requirements'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Check if gateway requirements are met
     */
    public function check_gateway_requirements($gateways) {
        // Don't hide gateway in admin - always show it so users can configure it
        if (is_admin()) {
            return $gateways;
        }

        // Check for SSL on frontend
        if (!is_ssl() && !give_is_test_mode()) {
            unset($gateways['stripe_custom']);
            return $gateways;
        }

        // Check for API keys on frontend
        $test_secret_key = give_get_option('givewp_stripe_gateway_test_secret_key');
        $live_secret_key = give_get_option('givewp_stripe_gateway_live_secret_key');

        if (give_is_test_mode() && empty($test_secret_key)) {
            unset($gateways['stripe_custom']);
        } elseif (!give_is_test_mode() && empty($live_secret_key)) {
            unset($gateways['stripe_custom']);
        }

        return $gateways;
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Only show on GiveWP settings pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'give') === false) {
            return;
        }

        // Check if gateway is enabled
        $enabled_gateways = give_get_enabled_payment_gateways();
        if (!isset($enabled_gateways['stripe_custom'])) {
            return;
        }

        $errors = array();

        // Check for API keys
        $test_secret_key = give_get_option('givewp_stripe_gateway_test_secret_key');
        $test_pub_key = give_get_option('givewp_stripe_gateway_test_publishable_key');
        $live_secret_key = give_get_option('givewp_stripe_gateway_live_secret_key');
        $live_pub_key = give_get_option('givewp_stripe_gateway_live_publishable_key');

        if (give_is_test_mode()) {
            if (empty($test_secret_key) || empty($test_pub_key)) {
                $errors[] = sprintf(
                    __('Stripe (Custom) is in test mode but API keys are missing. Please <a href="%s">configure your test API keys</a>.', 'givewp-stripe-gateway'),
                    admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=stripe-custom')
                );
            }
        } else {
            if (empty($live_secret_key) || empty($live_pub_key)) {
                $errors[] = sprintf(
                    __('Stripe (Custom) is in live mode but API keys are missing. Please <a href="%s">configure your live API keys</a>.', 'givewp-stripe-gateway'),
                    admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=stripe-custom')
                );
            }
            if (!is_ssl()) {
                $errors[] = __('Stripe (Custom) requires SSL to be enabled in live mode. Please enable SSL on your site.', 'givewp-stripe-gateway');
            }
        }

        // Display errors
        foreach ($errors as $error) {
            echo '<div class="notice notice-error"><p>' . $error . '</p></div>';
        }
    }

    /**
     * SSL notice
     */
    public function ssl_notice() {
        echo '<div class="error"><p>';
        echo __('Stripe requires SSL to be enabled in live mode. Please enable SSL on your site.', 'givewp-stripe-gateway');
        echo '</p></div>';
    }

    /**
     * Output credit card form
     */
    public function credit_card_form($form_id) {
        $publishable_key = GiveWP_Stripe_API::get_publishable_key();

        if (empty($publishable_key)) {
            echo '<div class="give-notice give-notice-error">';
            echo __('Stripe is not configured properly. Please contact the site administrator.', 'givewp-stripe-gateway');
            echo '</div>';
            return;
        }

        ?>
        <fieldset id="give_cc_fields" class="give-do-validate">
            <legend><?php _e('Credit Card Info', 'givewp-stripe-gateway'); ?></legend>

            <div id="give-stripe-card-element" style="margin-bottom: 15px;">
                <!-- Stripe Card Element will be inserted here -->
            </div>

            <div id="give-stripe-card-errors" role="alert" style="color: #dc3232; margin-bottom: 15px;"></div>

            <input type="hidden" name="give_stripe_payment_method" id="give-stripe-payment-method" value="" />
        </fieldset>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
        (function($) {
            var stripe = Stripe('<?php echo esc_js($publishable_key); ?>');
            var elements = stripe.elements();

            var style = {
                base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            };

            var cardElement = elements.create('card', {style: style});
            cardElement.mount('#give-stripe-card-element');

            cardElement.on('change', function(event) {
                var displayError = document.getElementById('give-stripe-card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });

            // Handle form submission
            $('body').on('submit', 'form.give-form', function(e) {
                var $form = $(this);
                var selectedGateway = $form.find('input[name="give-gateway"]:checked').val();

                if (selectedGateway !== 'stripe_custom') {
                    return true;
                }

                var paymentMethod = $('#give-stripe-payment-method').val();

                if (!paymentMethod) {
                    e.preventDefault();

                    stripe.createPaymentMethod({
                        type: 'card',
                        card: cardElement,
                        billing_details: {
                            name: $form.find('input[name="give_first"]').val() + ' ' + $form.find('input[name="give_last"]').val(),
                            email: $form.find('input[name="give_email"]').val()
                        }
                    }).then(function(result) {
                        if (result.error) {
                            var errorElement = document.getElementById('give-stripe-card-errors');
                            errorElement.textContent = result.error.message;
                        } else {
                            $('#give-stripe-payment-method').val(result.paymentMethod.id);
                            $form.off('submit').submit();
                        }
                    });

                    return false;
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Process payment
     */
    public function process_payment($purchase_data) {
        // Validate nonce
        give_validate_nonce($purchase_data['gateway_nonce'], 'give-gateway');

        // Collect payment data
        $payment_data = array(
            'price'           => $purchase_data['price'],
            'give_form_title' => $purchase_data['post_data']['give-form-title'],
            'give_form_id'    => intval($purchase_data['post_data']['give-form-id']),
            'give_price_id'   => isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : '',
            'date'            => $purchase_data['date'],
            'user_email'      => $purchase_data['user_email'],
            'purchase_key'    => $purchase_data['purchase_key'],
            'currency'        => give_get_currency($purchase_data['post_data']['give-form-id']),
            'user_info'       => $purchase_data['user_info'],
            'status'          => 'pending',
            'gateway'         => 'stripe_custom',
        );

        // Record the pending payment
        $payment_id = give_insert_payment($payment_data);

        if (!$payment_id) {
            give_record_gateway_error(
                __('Payment Error', 'givewp-stripe-gateway'),
                __('Payment creation failed before sending to Stripe.', 'givewp-stripe-gateway'),
                $payment_id
            );
            give_send_back_to_checkout('?payment-mode=stripe_custom');
            return;
        }

        // Get payment method from form
        $payment_method_id = isset($purchase_data['post_data']['give_stripe_payment_method'])
            ? sanitize_text_field($purchase_data['post_data']['give_stripe_payment_method'])
            : '';

        if (empty($payment_method_id)) {
            give_update_payment_status($payment_id, 'failed');
            give_record_gateway_error(
                __('Payment Error', 'givewp-stripe-gateway'),
                __('Payment method is missing.', 'givewp-stripe-gateway'),
                $payment_id
            );
            give_send_back_to_checkout('?payment-mode=stripe_custom');
            return;
        }

        // Calculate amount in cents
        $amount = round($purchase_data['price'] * 100);

        // Create metadata
        $metadata = array(
            'donation_id'   => $payment_id,
            'form_id'       => $payment_data['give_form_id'],
            'form_title'    => $payment_data['give_form_title'],
            'donor_email'   => $payment_data['user_email'],
            'donor_name'    => $payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'],
        );

        // Create payment intent
        $payment_intent = GiveWP_Stripe_API::create_payment_intent(
            $amount,
            $payment_data['currency'],
            $metadata,
            sprintf(__('Donation for %s', 'givewp-stripe-gateway'), $payment_data['give_form_title'])
        );

        if (is_wp_error($payment_intent)) {
            give_update_payment_status($payment_id, 'failed');
            give_record_gateway_error(
                __('Stripe Payment Error', 'givewp-stripe-gateway'),
                $payment_intent->get_error_message(),
                $payment_id
            );
            give_send_back_to_checkout('?payment-mode=stripe_custom');
            return;
        }

        // Store payment intent ID
        give_update_payment_meta($payment_id, '_give_stripe_payment_intent_id', $payment_intent['id']);

        // Confirm payment intent with payment method
        $confirmed = GiveWP_Stripe_API::confirm_payment_intent(
            $payment_intent['id'],
            $payment_method_id
        );

        if (is_wp_error($confirmed)) {
            give_update_payment_status($payment_id, 'failed');
            give_record_gateway_error(
                __('Stripe Payment Error', 'givewp-stripe-gateway'),
                $confirmed->get_error_message(),
                $payment_id
            );
            give_send_back_to_checkout('?payment-mode=stripe_custom');
            return;
        }

        // Check payment status
        if ($confirmed['status'] === 'succeeded') {
            // Store charge ID
            if (isset($confirmed['charges']['data'][0]['id'])) {
                give_update_payment_meta($payment_id, '_give_stripe_charge_id', $confirmed['charges']['data'][0]['id']);
            }

            // Update payment status
            give_update_payment_status($payment_id, 'publish');
            give_set_payment_transaction_id($payment_id, $confirmed['id']);

            // Send to success page
            give_send_to_success_page();
        } elseif ($confirmed['status'] === 'requires_action') {
            // Handle 3D Secure or other authentication
            give_update_payment_meta($payment_id, '_give_stripe_requires_action', true);
            give_update_payment_meta($payment_id, '_give_stripe_client_secret', $confirmed['client_secret']);

            // In a real implementation, you would redirect to a page that handles the authentication
            // For now, we'll mark it as pending
            give_update_payment_status($payment_id, 'pending');
            give_send_to_success_page();
        } else {
            give_update_payment_status($payment_id, 'failed');
            give_record_gateway_error(
                __('Stripe Payment Error', 'givewp-stripe-gateway'),
                sprintf(__('Payment status: %s', 'givewp-stripe-gateway'), $confirmed['status']),
                $payment_id
            );
            give_send_back_to_checkout('?payment-mode=stripe_custom');
        }
    }
}

// Initialize the gateway
new GiveWP_Stripe_Gateway_Handler();
