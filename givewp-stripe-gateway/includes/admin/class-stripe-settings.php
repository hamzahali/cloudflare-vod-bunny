<?php
/**
 * Stripe Settings
 *
 * Handles admin settings for Stripe gateway
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GiveWP_Stripe_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('give_settings_gateways', array($this, 'add_settings'), 10, 1);
        add_filter('give_get_sections_gateways', array($this, 'add_section'), 10, 1);
    }

    /**
     * Add settings section
     */
    public function add_section($sections) {
        $sections['stripe-custom'] = __('Stripe (Custom)', 'givewp-stripe-gateway');
        return $sections;
    }

    /**
     * Add settings fields
     */
    public function add_settings($settings) {
        $current_section = give_get_current_setting_section();

        if ('stripe-custom' !== $current_section) {
            return $settings;
        }

        $stripe_settings = array(
            array(
                'id'   => 'give_title_stripe_custom',
                'type' => 'title',
            ),
            array(
                'name' => __('Stripe Custom Gateway Settings', 'givewp-stripe-gateway'),
                'desc' => __('Configure the Stripe payment gateway settings below.', 'givewp-stripe-gateway'),
                'id'   => 'give_stripe_custom_description',
                'type' => 'give_description',
            ),
            array(
                'name' => __('Test Mode', 'givewp-stripe-gateway'),
                'desc' => __('Enable test mode to use Stripe test API keys. Remember to disable this when you are ready to accept live payments.', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_test_mode',
                'type' => 'radio_inline',
                'default' => 'enabled',
                'options' => array(
                    'enabled'  => __('Enabled', 'givewp-stripe-gateway'),
                    'disabled' => __('Disabled', 'givewp-stripe-gateway'),
                ),
            ),
            array(
                'id'   => 'give_title_stripe_custom_test_keys',
                'type' => 'sectionheader',
                'name' => __('Test API Keys', 'givewp-stripe-gateway'),
            ),
            array(
                'name' => __('Test Publishable Key', 'givewp-stripe-gateway'),
                'desc' => sprintf(
                    __('Enter your test publishable key. You can find your API keys in your <a href="%s" target="_blank">Stripe account settings</a>.', 'givewp-stripe-gateway'),
                    'https://dashboard.stripe.com/test/apikeys'
                ),
                'id'   => 'givewp_stripe_gateway_test_publishable_key',
                'type' => 'text',
                'default' => '',
                'attributes' => array(
                    'placeholder' => 'pk_test_...',
                ),
            ),
            array(
                'name' => __('Test Secret Key', 'givewp-stripe-gateway'),
                'desc' => __('Enter your test secret key.', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_test_secret_key',
                'type' => 'text',
                'default' => '',
                'attributes' => array(
                    'placeholder' => 'sk_test_...',
                ),
            ),
            array(
                'id'   => 'give_title_stripe_custom_live_keys',
                'type' => 'sectionheader',
                'name' => __('Live API Keys', 'givewp-stripe-gateway'),
            ),
            array(
                'name' => __('Live Publishable Key', 'givewp-stripe-gateway'),
                'desc' => sprintf(
                    __('Enter your live publishable key. You can find your API keys in your <a href="%s" target="_blank">Stripe account settings</a>.', 'givewp-stripe-gateway'),
                    'https://dashboard.stripe.com/apikeys'
                ),
                'id'   => 'givewp_stripe_gateway_live_publishable_key',
                'type' => 'text',
                'default' => '',
                'attributes' => array(
                    'placeholder' => 'pk_live_...',
                ),
            ),
            array(
                'name' => __('Live Secret Key', 'givewp-stripe-gateway'),
                'desc' => __('Enter your live secret key.', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_live_secret_key',
                'type' => 'text',
                'default' => '',
                'attributes' => array(
                    'placeholder' => 'sk_live_...',
                ),
            ),
            array(
                'id'   => 'give_title_stripe_custom_webhook',
                'type' => 'sectionheader',
                'name' => __('Webhook Settings', 'givewp-stripe-gateway'),
            ),
            array(
                'name' => __('Webhook URL', 'givewp-stripe-gateway'),
                'desc' => sprintf(
                    __('Add this webhook URL to your <a href="%s" target="_blank">Stripe webhooks settings</a>. Select all payment-related events.', 'givewp-stripe-gateway'),
                    'https://dashboard.stripe.com/webhooks'
                ),
                'id'   => 'givewp_stripe_gateway_webhook_url',
                'type' => 'give_description',
                'default' => '',
                'value' => '<code>' . GiveWP_Stripe_Webhook::get_webhook_url() . '</code>',
            ),
            array(
                'name' => __('Webhook Secret', 'givewp-stripe-gateway'),
                'desc' => __('Enter your webhook signing secret from Stripe. This is used to verify webhook authenticity.', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_webhook_secret',
                'type' => 'text',
                'default' => '',
                'attributes' => array(
                    'placeholder' => 'whsec_...',
                ),
            ),
            array(
                'name' => __('Enable Webhook Logging', 'givewp-stripe-gateway'),
                'desc' => __('Enable logging of webhook events for debugging purposes. Logs will be saved to wp-content/givewp-stripe-webhooks.log', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_webhook_logging',
                'type' => 'radio_inline',
                'default' => 'disabled',
                'options' => array(
                    'enabled'  => __('Enabled', 'givewp-stripe-gateway'),
                    'disabled' => __('Disabled', 'givewp-stripe-gateway'),
                ),
            ),
            array(
                'id'   => 'give_title_stripe_custom_advanced',
                'type' => 'sectionheader',
                'name' => __('Advanced Settings', 'givewp-stripe-gateway'),
            ),
            array(
                'name' => __('Statement Descriptor', 'givewp-stripe-gateway'),
                'desc' => __('Text that appears on customer credit card statements. Max 22 characters. Leave blank to use Stripe account default.', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_statement_descriptor',
                'type' => 'text',
                'default' => '',
                'attributes' => array(
                    'maxlength' => 22,
                ),
            ),
            array(
                'name' => __('Collect Billing Address', 'givewp-stripe-gateway'),
                'desc' => __('Enable to collect billing address on the donation form.', 'givewp-stripe-gateway'),
                'id'   => 'givewp_stripe_gateway_collect_billing',
                'type' => 'radio_inline',
                'default' => 'disabled',
                'options' => array(
                    'enabled'  => __('Enabled', 'givewp-stripe-gateway'),
                    'disabled' => __('Disabled', 'givewp-stripe-gateway'),
                ),
            ),
            array(
                'id'   => 'give_title_stripe_custom_end',
                'type' => 'sectionend',
            ),
        );

        return array_merge($settings, $stripe_settings);
    }
}

// Initialize settings
new GiveWP_Stripe_Settings();
