<?php
/**
 * Plugin Name: GiveWP Stripe Payment Gateway
 * Plugin URI: https://github.com/hamzahali/givewp-stripe-gateway
 * Description: Custom Stripe payment gateway for GiveWP using Stripe API
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/hamzahali
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: givewp-stripe-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('GIVEWP_STRIPE_VERSION', '1.0.0');
define('GIVEWP_STRIPE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIVEWP_STRIPE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIVEWP_STRIPE_PLUGIN_FILE', __FILE__);

/**
 * Main GiveWP Stripe Gateway Class
 */
class GiveWP_Stripe_Gateway {

    /**
     * Instance of this class
     */
    private static $instance;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 999);

        // Activation hook
        register_activation_hook(GIVEWP_STRIPE_PLUGIN_FILE, array($this, 'activate'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules for webhook endpoint
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if GiveWP is active
        if (!class_exists('Give')) {
            add_action('admin_notices', array($this, 'give_missing_notice'));
            return;
        }

        // Load plugin files
        $this->includes();

        // Register gateway - use priority 10 to ensure proper loading
        add_filter('give_payment_gateways', array($this, 'register_gateway'), 10);

        // Load text domain
        load_plugin_textdomain('givewp-stripe-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once GIVEWP_STRIPE_PLUGIN_DIR . 'includes/class-stripe-api.php';
        require_once GIVEWP_STRIPE_PLUGIN_DIR . 'includes/class-stripe-gateway.php';
        require_once GIVEWP_STRIPE_PLUGIN_DIR . 'includes/class-stripe-webhook.php';
        require_once GIVEWP_STRIPE_PLUGIN_DIR . 'includes/admin/class-stripe-settings.php';
    }

    /**
     * Register payment gateway
     */
    public function register_gateway($gateways) {
        $gateways['stripe_custom'] = array(
            'admin_label'    => __('Stripe (Custom)', 'givewp-stripe-gateway'),
            'checkout_label' => __('Credit Card (Stripe)', 'givewp-stripe-gateway'),
        );
        return $gateways;
    }

    /**
     * Notice when GiveWP is not active
     */
    public function give_missing_notice() {
        echo '<div class="error"><p>';
        echo __('GiveWP Stripe Gateway requires GiveWP plugin to be installed and activated.', 'givewp-stripe-gateway');
        echo '</p></div>';
    }
}

/**
 * Initialize the plugin
 */
function givewp_stripe_gateway() {
    return GiveWP_Stripe_Gateway::get_instance();
}

// Start the plugin
givewp_stripe_gateway();
