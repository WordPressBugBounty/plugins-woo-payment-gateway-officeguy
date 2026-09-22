<?php
if (!defined('ABSPATH'))
    exit;

class OfficeGuyBlocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    protected $name = 'officeguy';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_officeguy_settings', array());
    }

    public function is_active()
    {
        return $this->get_setting('enabled') == 'yes' && in_array($this->get_setting('pci', 'no'), array('redirect', 'no', 'yes'), true);
    }

    public function get_payment_method_script_handles()
    {
        $Dependencies = array('wc-blocks-registry', 'wc-blocks-components', 'wc-settings', 'wp-element', 'wp-html-entities');
        if ($this->get_setting('pci', 'no') == 'no')
        {
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- SUMIT updates its tokenization script independently of the plugin.
            wp_register_script('officeguypayments', ($this->get_setting('environment') == 'dev' ? 'http://dev.' : 'https://app.') . 'sumit.co.il/scripts/payments.js', array('jquery'), null, false);
            $Dependencies[] = 'officeguypayments';
        }
        wp_register_script('officeguy-blocks', PLUGIN_DIR . 'includes/js/officeguy-blocks.js', $Dependencies, '4.0.1', true);
        return array('officeguy-blocks');
    }

    public function get_payment_method_data()
    {
        return array(
            'title' => $this->get_setting('title', 'SUMIT'),
            'description' => wp_kses_post($this->get_setting('description')),
            'redirectMessage' => __('You will be redirected to SUMIT to complete your payment.', 'woo-payment-gateway-officeguy'),
            'testingNotice' => $this->get_setting('testing') == 'yes' ? __('Warning! SUMIT plugin is set to Testing mode. Testing mode doesn\'t process credit card transactions and doesn\'t issue invoices/receipts.', 'woo-payment-gateway-officeguy') : '',
            'available' => $this->is_active() && OfficeGuyPayment::IsCurrencySupported(),
            'supports' => array('products'),
            'mode' => $this->get_setting('pci', 'no'),
            'companyId' => absint($this->get_setting('companyid')),
            'publicKey' => $this->get_setting('pci', 'no') == 'no' ? $this->get_setting('publickey') : '',
            'environment' => $this->get_setting('environment', 'www'),
            'language' => get_locale(),
            'citizenId' => $this->get_setting('citizenid', 'required'),
            'cvv' => $this->get_setting('cvv', 'required'),
            'maximumPayments' => max(1, absint($this->get_setting('maxpayments', 1))),
            'minimumForPayments' => (float) $this->get_setting('minamountforpayments', 0),
            'minimumPerPayment' => (float) $this->get_setting('minamountperpayment', 0),
            'currencyDecimals' => wc_get_price_decimals(),
            'labels' => array(
                'cardNumber' => __('Credit Card number', 'woo-payment-gateway-officeguy'),
                'month' => __('Month', 'woo-payment-gateway-officeguy'),
                'year' => __('Year', 'woo-payment-gateway-officeguy'),
                'citizenId' => __('Israeli Citizen ID', 'woo-payment-gateway-officeguy'),
                'cvv' => __('Security code (CVV)', 'woo-payment-gateway-officeguy'),
                'payments' => __('Payments', 'woo-payment-gateway-officeguy'),
                'invalidCard' => __('Card number is invalid.', 'woo-payment-gateway-officeguy'),
                'invalidExpiry' => __('Card expiration date is invalid.', 'woo-payment-gateway-officeguy'),
                'requiredId' => __('Citizen ID is required.', 'woo-payment-gateway-officeguy'),
                'invalidCvv' => __('Card security code is invalid (only digits are allowed).', 'woo-payment-gateway-officeguy'),
                'error' => __('Payment could not be prepared. Please try again.', 'woo-payment-gateway-officeguy')
            )
        );
    }
}
