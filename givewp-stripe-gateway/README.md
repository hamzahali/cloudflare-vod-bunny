# GiveWP Stripe Payment Gateway

A custom Stripe payment gateway plugin for GiveWP that integrates directly with the Stripe API to process donations securely.

## Description

This plugin adds a custom Stripe payment gateway to GiveWP, allowing you to accept credit card donations through Stripe's API. It includes features like:

- Direct integration with Stripe API v1
- Support for both test and live modes
- Secure payment processing using Stripe Elements
- Webhook support for asynchronous payment notifications
- Support for refunds, disputes, and failed payments
- Detailed payment logging and error handling
- SSL requirement for live payments
- Statement descriptor customization

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- GiveWP plugin (latest version recommended)
- SSL certificate (required for live payments)
- Stripe account with API keys

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `givewp-stripe-gateway` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Donations > Settings > Payment Gateways > Stripe (Custom)** to configure

### Via WordPress Admin

1. Go to **Plugins > Add New**
2. Upload the plugin zip file
3. Click **Install Now** and then **Activate**
4. Navigate to **Donations > Settings > Payment Gateways > Stripe (Custom)** to configure

## Configuration

### 1. Get Your Stripe API Keys

1. Log in to your [Stripe Dashboard](https://dashboard.stripe.com/)
2. Navigate to **Developers > API Keys**
3. Copy your **Publishable key** and **Secret key** for both test and live modes

### 2. Configure Plugin Settings

1. In WordPress, go to **Donations > Settings > Payment Gateways**
2. Click on the **Stripe (Custom)** tab
3. Enter your Stripe API keys:
   - **Test Publishable Key** (starts with `pk_test_`)
   - **Test Secret Key** (starts with `sk_test_`)
   - **Live Publishable Key** (starts with `pk_live_`)
   - **Live Secret Key** (starts with `sk_live_`)

### 3. Enable the Gateway

1. Go to **Donations > Settings > Payment Gateways > Gateways**
2. Enable **Stripe (Custom)** gateway
3. Save settings

### 4. Set Up Webhooks

Webhooks ensure that your site receives real-time updates about payment events from Stripe.

1. Copy the webhook URL shown in the plugin settings (format: `https://yoursite.com/givewp-stripe-webhook`)
2. Go to your [Stripe Dashboard > Developers > Webhooks](https://dashboard.stripe.com/webhooks)
3. Click **Add endpoint**
4. Paste your webhook URL
5. Select the following events to listen to:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.succeeded`
   - `charge.failed`
   - `charge.refunded`
   - `charge.dispute.created`
6. Click **Add endpoint**
7. Copy the **Signing secret** (starts with `whsec_`)
8. Paste it in the **Webhook Secret** field in your plugin settings
9. Save settings

## Usage

### For Site Administrators

1. Create or edit a donation form in GiveWP
2. Donors will see **Credit Card (Stripe)** as a payment option
3. The Stripe card element will appear on the donation form
4. Donations are processed securely through Stripe

### For Donors

1. Select an amount and fill out the donation form
2. Choose **Credit Card (Stripe)** as payment method
3. Enter credit card details in the secure Stripe form
4. Click **Donate Now**
5. Receive confirmation of donation

## Features

### Security

- PCI compliance through Stripe Elements (card data never touches your server)
- SSL required for live mode
- Webhook signature verification
- Secure API key storage

### Payment Processing

- Real-time payment processing
- Support for one-time donations
- Automatic currency conversion
- Failed payment handling
- 3D Secure authentication support

### Admin Features

- Test mode for development
- Detailed payment notes and logs
- Webhook event logging
- Custom statement descriptors
- Refund support through Stripe dashboard
- Dispute notifications

## Testing

### Test Mode

1. Enable **Test Mode** in plugin settings
2. Use test API keys
3. Use [Stripe test card numbers](https://stripe.com/docs/testing):
   - Success: `4242 4242 4242 4242`
   - Decline: `4000 0000 0000 0002`
   - 3D Secure: `4000 0027 6000 3184`
4. Use any future expiry date and any 3-digit CVC

### Going Live

1. Ensure SSL is enabled on your site
2. Enter live API keys
3. Set up webhook with live endpoint
4. Disable test mode
5. Test with a small real donation
6. Monitor the first few transactions

## Troubleshooting

### Gateway Not Appearing

- Ensure GiveWP is installed and activated
- Check that SSL is enabled (required for live mode)
- Verify API keys are entered correctly
- Clear WordPress cache

### Payment Failing

- Check Stripe dashboard for error details
- Verify API keys match the mode (test/live)
- Ensure webhook is configured correctly
- Check browser console for JavaScript errors

### Webhook Issues

- Verify webhook URL is accessible
- Check that signing secret is correct
- Enable webhook logging in settings
- Review logs at `wp-content/givewp-stripe-webhooks.log`

### Common Error Messages

- **"Stripe is not configured properly"** - API keys are missing or invalid
- **"Payment method is missing"** - JavaScript error, check console
- **"Stripe requires SSL"** - Enable SSL certificate on your site

## File Structure

```
givewp-stripe-gateway/
├── givewp-stripe-gateway.php          # Main plugin file
├── includes/
│   ├── class-stripe-api.php           # Stripe API integration
│   ├── class-stripe-gateway.php       # Payment gateway handler
│   ├── class-stripe-webhook.php       # Webhook event handler
│   └── admin/
│       └── class-stripe-settings.php  # Admin settings
└── README.md                          # This file
```

## Hooks and Filters

Developers can extend the plugin using WordPress hooks:

### Actions

- `givewp_stripe_before_payment_process` - Before payment processing starts
- `givewp_stripe_after_payment_process` - After payment processing completes
- `givewp_stripe_webhook_event` - When webhook event is received

### Filters

- `givewp_stripe_payment_intent_args` - Modify PaymentIntent creation arguments
- `givewp_stripe_metadata` - Modify metadata sent to Stripe
- `givewp_stripe_statement_descriptor` - Customize statement descriptor

## Changelog

### 1.0.0 - 2025-01-24

- Initial release
- Stripe API v1 integration
- Payment processing with Stripe Elements
- Webhook support
- Test and live modes
- Admin settings panel

## Support

For issues, questions, or contributions:

- GitHub Issues: [https://github.com/hamzahali/givewp-stripe-gateway/issues](https://github.com/hamzahali/givewp-stripe-gateway/issues)
- GiveWP Documentation: [https://givewp.com/documentation/](https://givewp.com/documentation/)
- Stripe Documentation: [https://stripe.com/docs](https://stripe.com/docs)

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built for GiveWP by hamzahali
- Uses Stripe API for payment processing
- Stripe Elements for secure card collection

## Disclaimer

This plugin is provided as-is without warranty. Always test thoroughly in a staging environment before using in production. Ensure you comply with all relevant payment processing regulations in your jurisdiction.
