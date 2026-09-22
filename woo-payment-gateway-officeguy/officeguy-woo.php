<?php

/**
 * Plugin Name: SUMIT Payment Gateway for WooCommerce
 * Plugin URI: https://help.sumit.co.il/he/articles/5830000
 * Description: Accept all major credit cards directly on your WooCommerce site in a seamless and secure checkout environment using SUMIT credit card clearing and invoicing.
 * Version: 4.0.1
 * Author: SUMIT
 * Author URI: https://www.sumit.co.il
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-payment-gateway-officeguy
 * Domain Path: /languages

 * @package WordPress
 * @author SUMIT
 * @since 1.0.1
 */

/*
 * Copyright (c) SUMIT.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see https://www.gnu.org/licenses/.
 */

if (!defined('ABSPATH'))
    exit;

define('PLUGIN_DIR', plugin_dir_url(__FILE__));

/**
 * Load plugin textdomain.
 */
function officeguy_load_textdomain()
{
    load_plugin_textdomain('woo-payment-gateway-officeguy', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'officeguy_load_textdomain');

if (!function_exists('is_woocommerce_activated'))
{
    require_once dirname(__FILE__) . '/includes/OfficeGuyAPI.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyStock.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyTokens.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyPluginSetup.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyPayment.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyRequestHelpers.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuySubscriptions.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyCartFlow.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuySettings.php';
    require_once dirname(__FILE__) . '/includes/officeguy_woocommerce_gateway.php';
    require_once dirname(__FILE__) . '/includes/officeguybit_woocommerce_gateway.php';
    require_once dirname(__FILE__) . '/templates/single-product/officeguy-price.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyDokanMarketplace.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyWCFMMarketplace.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyWCVendorsMarketplace.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyMultiVendor.php';
    require_once dirname(__FILE__) . '/includes/OfficeGuyDonation.php';

    OfficeGuyPluginSetup::Init(__FILE__);
}

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
add_action('woocommerce_blocks_loaded', function() {
    if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'))
        return;

    require_once dirname(__FILE__) . '/includes/OfficeGuyBlocks.php';
    add_action('woocommerce_blocks_payment_method_type_registration', function($Registry) {
        $Registry->register(new OfficeGuyBlocks());
    });
});
