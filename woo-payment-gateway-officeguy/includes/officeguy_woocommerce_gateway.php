<?php
if (!defined('ABSPATH'))
    exit;

/**
 * The WC_OfficeGuy Class
 *
 * @since 1.0
 */
function officeguy_woocommerce_gateway()
{
    if (!class_exists("WC_Payment_Gateway_CC"))
        return;

    class WC_OfficeGuy extends WC_Payment_Gateway_CC
    {
        function __construct()
        {
            $this->id = 'officeguy';
            $this->init_settings();
            $this->method_title = 'SUMIT';
            $this->method_description = __('Receive payment using SUMIT credit card processing.', 'woo-payment-gateway-officeguy');
            $this->icon = PLUGIN_DIR . 'includes/images/cards.png';
            $this->has_fields = true;
            if (!empty($this->settings['title']))
                $this->title = $this->settings['title'];
            OfficeGuySettings::InitFormFields($this);
            OfficeGuySettings::InitDefaultSettings($this);

            if ($this->settings['support_tokens'] == 'yes')
                $this->supports = array('products', 'refunds', 'add_payment_method', 'tokenization', 'subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'subscription_payment_method_change', 'subscription_payment_method_change_customer', 'subscription_payment_method_change_admin', 'multiple_subscriptions');
            else
                $this->supports = array('products', 'refunds');

            add_action('woocommerce_receipt_officeguy', array($this, 'ReceiptPage'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'AddScripts'));
            add_action('woocommerce_payment_complete', 'OfficeGuyPayment::CreateDocumentOnPaymentComplete', 10, 1);
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'ProcessSubscriptionPayment'), 10, 2);
            add_action('woocommerce_subscription_failing_payment_method_updated_' . $this->id, array($this, 'ProcessSubscriptionPaymentMethodUpdate'));
            add_action('woocommerce_api_wc_officeguy', array($this, 'ProcessRedirectResponse'));
            
            if (!OfficeGuyPayment::IsCurrencySupported())
                $this->enabled = 'no';
            OfficeGuyStock::CreateSchedules($this);
        }

        function admin_options()
        { ?>
            <h3><?php echo esc_html__('SUMIT Payments', 'woo-payment-gateway-officeguy') ?></h3>
            <p>
                <?php echo wp_kses_post(__('The SUMIT Payments Gateway is a simple and powerful checkout solution.<br />The plugin adds credit card fields to the checkout page, and processes the credit card transaction on SUMIT.<br />In order to avoid PCI certification requirement, credit card details are tokenized on the end user browser using a JavaScript library, and never transmitted through your servers.<br />Please follow the <a target="_blank" href="https://help.sumit.co.il/he/articles/5830000">installation instructions</a> to complete the plugin setup.', 'woo-payment-gateway-officeguy')); ?>
            </p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
        }

        function payment_fields()
        {
            if ($this->settings['description'])
            { ?>
                <p><?php echo wp_kses_post($this->settings['description']); ?></p>
            <?php
            }

            if (is_checkout() && $this->settings['support_tokens'] == 'yes' && is_user_logged_in())
            {
                $this->tokenization_script();
                $this->saved_payment_methods();
            }

            $OrderValue = 0;
            if (is_checkout())
            {
                if (is_wc_endpoint_url('order-pay'))
                {
                    $OrderID = get_query_var('order-pay');
                    $Order = wc_get_order($OrderID);
                    $OrderValue = round($Order->get_total());
                }
                else
                    $OrderValue = round(WC()->cart->cart_contents_total + WC()->cart->tax_total + WC()->cart->shipping_total + WC()->cart->fee_total);
            }

            /**
             * Implement SUMIT JS payment
             * (check weather the PCI option set to 'no' which means no-pci & no redirect)
             */
            if ($this->settings['pci'] == "no")
            { ?>
                <script>
                    var OG_Settings = <?php echo wp_json_encode(array(
                        'CompanyID' => absint($this->settings['companyid']),
                        'APIPublicKey' => $this->settings['publickey'],
                        'Environment' => $this->settings['environment'],
                        'ResponseLanguage' => get_locale(),
                    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                    jQuery(function() {
                        og_bind_form_events();
                    });
                </script>
            <?php
            }

            if (empty($this->settings['companyid']) || (empty($this->settings['publickey']) && $this->settings['pci'] == "no") || empty($this->settings['privatekey']))
            { ?>
                <div class="woocommerce-error">
                    <?php echo esc_html__('Warning! SUMIT plugin setup isn\'t complete. Please configure the required API keys.', 'woo-payment-gateway-officeguy') ?>
                </div>
            <?php
            }
            else if ($this->settings['testing'] == 'yes')
            { ?>
                <div class="woocommerce-error">
                    <?php echo esc_html__('Warning! SUMIT plugin is set to Testing mode. Testing mode doesn\'t process credit card transactions and doesn\'t issue invoices/receipts.', 'woo-payment-gateway-officeguy') ?>
                </div>
            <?php
            }

            /**
             * Remove payments count option
             * (if there's subscription products & the payment input method doesn't set to 'redirect')
             */
            if ($this->settings['pci'] != "redirect")
            { ?>
                <script>
                    jQuery(function() {
                        OfficeGuy.Payments.InitEditors();
                    });
                </script>
            <?php
            }

            $MaximumPayments = OfficeGuyPayment::GetMaximumPayments($this, $OrderValue);
            if ($MaximumPayments != '1' && (OfficeGuySubscriptions::CartContainsOfficeGuySubscription() || OfficeGuySubscriptions::CartContainsWooCommerceSubscriptionWithoutTrial()))
                $MaximumPayments = '1';

            if ($this->settings['pci'] == "yes" || $this->settings['pci'] == "no")
            { ?>
                <fieldset id="wc-officeguy-cc-form" class="wc-credit-card-form wc-payment-form og-payment-form">
                    <!-- Show input boxes for new data -->
                    <div class="woocommerce-error og-errors"></div>

                    <!-- Credit card number -->
                    <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-first') ?>">
                        <label for="og-ccnum" class="og-label-tel">
                            <?php echo esc_html__('Credit Card number', 'woo-payment-gateway-officeguy') ?> <span class="required">*</span>
                        </label>
                        <input type="tel" class="input-text og-cc-cardnumber" id="og-ccnum" name="og-ccnum" data-og="cardnumber" maxlength="20" autocomplete="off" required="required" data-og-message="<?php echo esc_attr__('Card number is required.', 'woo-payment-gateway-officeguy') ?>" aria-label="<?php esc_attr_e('Credit Card number', 'woo-payment-gateway-officeguy') ?>" />
                    </p>
                    <?php
                    if ($this->settings['citizenid'] != 'no')
                    { ?>
                        <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-last') ?>">
                            <label for="og-citizenid" title="<?php esc_attr_e('Citizen ID or Passport number for tourists', 'woo-payment-gateway-officeguy') ?>" class="og-label-tel">
                                <?php esc_html_e('Israeli Citizen ID', 'woo-payment-gateway-officeguy') ?> [?]
                                <?php if ($this->settings['citizenid'] == 'required')
                                { ?>
                                    <span class="required">*</span>
                                <?php } ?>
                            </label>
                            <input type="tel" class="input-text og-cc-citizenid" id="og-citizenid" name="og-citizenid" maxlength="20" data-og="citizenid" autocomplete="off" aria-label="<?php esc_attr_e('Israeli Citizen ID', 'woo-payment-gateway-officeguy') ?>" <?php echo $this->settings['citizenid'] == 'required' ? 'required="required" data-og-message="' . esc_attr__('Israeli Citizen ID is required.', 'woo-payment-gateway-officeguy') . '" ' : '' ?> />
                        </p>
                    <?php
                    } ?>

                    <div class="og-clear"></div>

                    <!-- Credit card expiration -->
                    <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-first') ?>">
                        <label for="og-expmonth">
                            <?php echo esc_html__('Expiration date', 'woo-payment-gateway-officeguy') ?> <span class="required">*</span>
                        </label>
                        <span class="og-expiration">
                            <select name="og-expmonth" id="og-expmonth" class="woocommerce-select og-cc-month" data-og="expirationmonth" required="required" aria-label="<?php echo esc_attr__('Expiration date', 'woo-payment-gateway-officeguy') . ' (' . esc_attr__('Month', 'woo-payment-gateway-officeguy') . ')' ?>">
                                <option value=""><?php esc_html_e('Month', 'woo-payment-gateway-officeguy') ?></option>
                                <?php
                                for ($i = 1; $i <= 12; $i++)
                                {
                                    printf('<option value="%u">%s</option>', absint($i), absint($i));
                                }
                                ?>
                            </select>
                            <select name="og-expyear" id="og-expyear" class="woocommerce-select og-cc-year" data-og="expirationyear" required="required" data-og-message="<?php echo esc_attr__('Card expiration date is required.', 'woo-payment-gateway-officeguy') ?>" aria-label="<?php echo esc_attr__('Expiration date', 'woo-payment-gateway-officeguy') . ' (' . esc_attr__('Year', 'woo-payment-gateway-officeguy') . ')' ?>">
                                <option value=""><?php esc_html_e('Year', 'woo-payment-gateway-officeguy') ?></option>
                                <?php for ($i = gmdate('y'); $i <= gmdate('y') + 15; $i++)
                                {
                                    printf('<option value="20%u">' . ($this->settings['fourdigitsyear'] == 'yes' ? '20' : '') . '%u</option>', absint($i), absint($i));
                                }
                                ?>
                            </select>
                        </span>
                    </p>

                    <?php
                    // CVV
                    if ($this->settings['cvv'] != 'no')
                    { ?>
                        <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-last') ?>">
                            <label for="og-cvv" title="<?php esc_attr_e('3 or 4 digits usually found on the signature strip', 'woo-payment-gateway-officeguy') ?>" class="og-label-tel">
                                <?php esc_html_e('Security code (CVV)', 'woo-payment-gateway-officeguy') ?> [?]
                                <?php if ($this->settings['cvv'] == 'required')
                                { ?>
                                    <span class="required">*</span>
                                <?php } ?>
                            </label>
                            <input type="tel" class="input-text og-cc-cvv" id="og-cvv" name="og-cvv" maxlength="4" data-og="cvv" autocomplete="off" aria-label="<?php esc_attr_e('Security code (CVV)', 'woo-payment-gateway-officeguy') ?>" <?php echo $this->settings['cvv'] == 'required' ? 'required="required" data-og-message="' . esc_attr__('Card security code is required.', 'woo-payment-gateway-officeguy') . '" ' : '' ?> />
                        </p>
                    <?php
                    }
                    ?>

                    <?php
                    if ($MaximumPayments > 1)
                    { ?>
                        <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-first') ?>">
                            <label for="og-paymentscount" title="<?php esc_attr_e('Credit card payments count', 'woo-payment-gateway-officeguy') ?>">
                                <?php esc_html_e('Payments', 'woo-payment-gateway-officeguy') ?> [?]
                            </label>
                            <select class="woocommerce-select" id="og-paymentscount" name="og-paymentscount">
                                <?php
                                for ($i = 1; $i <= $MaximumPayments; $i++)
                                {
                                    printf('<option value="%u">%u</option>', absint($i), absint($i));
                                }
                                ?>
                            </select>
                        </p>
                    <?php
                    }

                    if ($this->settings['support_tokens'] == 'yes' && is_checkout() && is_user_logged_in() && !OfficeGuyPayment::ForceTokenStorage($this))
                    { ?>
                        <div class="og-clear"></div>
                        <p class="form-row">
                            <?php
                            $this->payment_fields_save_payment_method_checkbox();
                            ?>
                        </p>
                        <div class="og-clear"></div>
                    <?php
                    }
                    ?>
                </fieldset>

                <?php
                if ($this->settings['support_tokens'] == 'yes' && is_checkout() && is_user_logged_in())
                { ?>
                    <fieldset class="og-token-form">
                        <?php if ($this->settings['cvv'] != 'no')
                        { ?>
                            <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-first') ?>">
                                <label for="og-cvv" title="<?php esc_attr_e('3 or 4 digits usually found on the signature strip', 'woo-payment-gateway-officeguy') ?>">
                                    <?php esc_html_e('Security code (CVV)', 'woo-payment-gateway-officeguy') ?> [?]
                                    <?php if ($this->settings['cvv'] == 'required')
                                    { ?>
                                        <span class="required">*</span>
                                    <?php } ?>
                                </label>
                                <input type="tel" class="input-text og-cc-cvv" id="og-cvv" name="og-cvv" maxlength="4" autocomplete="off" <?php echo $this->settings['cvv'] == 'required' ? 'required="required" data-og-message="' . esc_attr__('Card security code is required.', 'woo-payment-gateway-officeguy') . '" ' : '' ?> />
                            </p>
                        <?php 
			}

                        if ($MaximumPayments > 1)
                        { ?>
                            <p class="form-row<?php echo ($this->settings['singlecolumnlayout'] == 'yes' ? '' : ' form-row-last') ?>">
                                <label for="og-paymentscount" title="<?php esc_attr_e('Credit card payments count', 'woo-payment-gateway-officeguy') ?>">
                                    <?php esc_html_e('Payments', 'woo-payment-gateway-officeguy') ?> [?]
                                </label>
                                <select class="woocommerce-select" id="og-paymentscount" name="og-paymentscount">
                                    <?php for ($i = 1; $i <= $MaximumPayments; $i++)
                                    {
                                        printf('<option value="%u">%u</option>', absint($i), absint($i));
                                    } ?>
                                </select>
                            </p>
                        <?php
                        } ?>
                    </fieldset>
            <?php
                }
            }
        }

        function payment_fields_save_payment_method_checkbox()
        { ?>
            <p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                <input id="wc-<?php echo esc_attr($this->id) ?>-new-payment-method" name="wc-<?php echo esc_attr($this->id) ?>-new-payment-method" type="checkbox" checked="checked" style="width:auto;" />
                <label style="display:inline;" for="wc-<?php echo esc_attr($this->id) ?>-new-payment-method"><?php echo esc_html__('Securely save my details to my account.', 'woo-payment-gateway-officeguy') ?></label>
            </p>
<?php
        }

        function process_payment($OrderID)
        {
            $Order = wc_get_order($OrderID);
            $Result = OfficeGuyPayment::ProcessOrder($this, $Order, false);
            return is_array($Result) ? $Result : array('result' => 'failure');
        }

        function process_refund($OrderID, $Amount = null, $Reason = '')
        {
            $Order = wc_get_order($OrderID);
            return OfficeGuyPayment::ProcessOrderRefund($this, $Order, $Amount, $Reason);
        }

        function ProcessSubscriptionPayment($Amount, $Order)
        {
            if ($Amount == 0)
            {
                $Order->payment_complete();
                return;
            }

            OfficeGuyPayment::ProcessOrder($this, $Order, true);
        }

        function ProcessSubscriptionPaymentMethodUpdate()
        {
            OfficeGuyAPI::WriteToLog('ProcessSubscriptionPaymentMethodUpdate', 'debug');
        }

        function add_payment_method()
        {
            OfficeGuyAPI::WriteToLog('add_payment_method', 'debug');
            return OfficeGuyTokens::ProcessToken($this);
        }

        function validate_fields()
        {
            return OfficeGuyPayment::ValidateOrderFields($this);
        }

        function ReceiptPage($Order)
        {
            echo '<p>' . esc_html__('Thank you for your order.', 'woo-payment-gateway-officeguy') . '</p>';
        }

        public function process_admin_options()
        {
            parent::process_admin_options();
            OfficeGuySettings::InitDefaultSettings($this);
            if (!empty($this->settings['companyid']) && !empty($this->settings['publickey']) && !empty($this->settings['privatekey']))
            {
                $CredentialsMessage = OfficeGuyAPI::CheckCredentials($this->settings['companyid'], $this->settings['privatekey']);
                if ($CredentialsMessage != null)
                    WC_Admin_Settings::add_error('SUMIT - Invalid Private Key: ' . $CredentialsMessage);
                else 
                {
                    $CredentialsMessage = OfficeGuyAPI::CheckPublicCredentials($this->settings['companyid'], $this->settings['publickey']);
                    if ($CredentialsMessage != null)
                        WC_Admin_Settings::add_error('SUMIT - Invalid Public Key: ' . $CredentialsMessage);
                }
            }
        }

        public function ProcessRedirectResponse()
        {
            header('HTTP/1.1 200 OK');
            header('User-Agent: SUMIT');

            $OrderID = OfficeGuyRequestHelpers::Get('OG-OrderID');
            $Order = wc_get_order($OrderID);
            if (!$Order || ($Order->get_payment_method() != 'officeguy' && $Order->get_payment_method() != 'officeguybit'))
                return;
            if ($Order->get_status() != "pending")
                return;
    
            $Gateway = GetOfficeGuyGateway();
            if ($Gateway->settings['pci'] != 'redirect' && $Order->get_payment_method() != 'officeguybit')
                return;
            $OGPaymentID = OfficeGuyRequestHelpers::Get('OG-PaymentID');
            if (empty($OGPaymentID))
                return;
    
            $Request = array();
            $Request['Credentials'] = OfficeGuyPayment::GetCredentials($Gateway);
            $Request['PaymentID'] = $OGPaymentID;
            $Response = OfficeGuyAPI::Post($Request, '/billing/payments/get/', $Gateway->settings['environment'], false);
            if ($Response == null)
                return;
    
            if (!OfficeGuyPayment::ValidatePayment($Order, $Response, $OGPaymentID))
                return;
            $OGDocumentID = $Response['Data']['Payment']['DocumentID'];
    
            $ResponsePayment = $Response['Data']['Payment'];
            if ($ResponsePayment['ValidPayment'] != true)
            {
                $Order->add_order_note(__('Payment failed', 'woo-payment-gateway-officeguy') . ' - ' . $ResponsePayment['StatusDescription']);
                wc_add_notice(__('Payment failed', 'woo-payment-gateway-officeguy') . ' - ' . $ResponsePayment['StatusDescription'], $notice_type = 'error');
                $Order->update_status('failed');
            }
            else
            {
                $ResponsePaymentMethod = $ResponsePayment['PaymentMethod'];
                /* translators: 1: payment authorization number, 2: last digits of the credit card, 3: SUMIT payment ID, 4: SUMIT document ID, 5: SUMIT customer ID. */
                $Remark = __('SUMIT payment completed. Auth Number: %1$s. Last digits: %2$s. Payment ID: %3$s. Document ID: %4$s. Customer ID: %5$s.', 'woo-payment-gateway-officeguy');
                $Remark = sprintf($Remark, $ResponsePayment['AuthNumber'], $ResponsePaymentMethod['CreditCard_LastDigits'], $ResponsePayment['ID'], $OGDocumentID, $ResponsePayment['CustomerID']);
                $Order->add_order_note($Remark);
                $Order->update_meta_data('OfficeGuyDocumentID', $OGDocumentID);
                $Order->update_meta_data('OfficeGuyCustomerID', $ResponsePayment['CustomerID']);
                $Order->save_meta_data();
                $Order->payment_complete((string)$ResponsePayment['ID']);
    
                if ($Gateway->settings['createorderdocument'] == 'yes')
                {
                    $OrderCustomer = array(
                        'ID' => $ResponsePayment['CustomerID']
                    );
                    OfficeGuyPayment::CreateOrderDocument($Gateway, $Order, $OrderCustomer, $OGDocumentID);
                }

                wp_safe_redirect($this->get_return_url($Order));
                exit;
            }
        }
        
        function AddScripts()
        {
            if ($this->settings['pci'] != "redirect")
            {
                wp_enqueue_script('jquery');
                // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- SUMIT hosts and updates this script independently; no plugin version is appended.
                wp_enqueue_script('officeguypayments', ($this->settings['environment'] == "dev" ? "http://dev." : "https://app.") . 'sumit.co.il/scripts/payments.js', array('jquery'), null, false);
            }
            wp_enqueue_style('officeguy-og-css', PLUGIN_DIR . 'includes/css/front.css', array(), '4.0.1');
            wp_enqueue_script('officeguy-front', PLUGIN_DIR . 'includes/js/officeguy.js', array('jquery'), '4.0.1', false);
        }

        public static function AddPaymentGateway($Methods)
        {
            $Methods[] = 'WC_OfficeGuy';
            return $Methods;
        }

        public static function SubscriptionPaymentMethodDisplay($Text, $Order)
        {
            $Order = new WC_Order($Order->get_id());
            $Tokens = $Order->get_payment_tokens();
            if (count($Tokens) == 0)
                return $Text;

            $Token = WC_Payment_Tokens::get($Tokens[count($Tokens) - 1]);
            if (!empty($Token))
                $Text .= ' (' . $Token->get_last4() . ')';
            return $Text;
        }
    }
}
add_action('plugins_loaded', 'officeguy_woocommerce_gateway', 0);

function GetOfficeGuyGateway()
{
    $Gateway = WC()->payment_gateways->payment_gateways()['officeguy'];
    if (!isset($Gateway))
    {
        if (!class_exists('WC_OfficeGuy'))
            return null;
        $Gateway = new WC_OfficeGuy();
    }
    return $Gateway;
}

add_filter('woocommerce_payment_gateways', 'WC_OfficeGuy::AddPaymentGateway');
add_filter('woocommerce_subscription_payment_method_to_display', 'WC_OfficeGuy::SubscriptionPaymentMethodDisplay', 10, 2);
