<?php
if (!defined('ABSPATH'))
    exit;

class OfficeGuyStock
{
    public static function UpdateStock()
    {
        OfficeGuyStock::InternalUpdateStock(false);
    }

    public static function InternalUpdateStock($ForceSync)
    {
        $Gateway = GetOfficeGuyGateway();
        if (!isset($Gateway))
        {
            wp_clear_scheduled_hook('officeguy_cron');
            return;
        }

        if (!$ForceSync)
        {
            $LastSyncDate = $Gateway->stock_sync_last;
            if (isset($LastSyncDate) && (time() - $LastSyncDate) < 60 * 60)
            {
                OfficeGuyAPI::WriteToLog('Stock: Skipping sync (LastSyncDate: ' . $LastSyncDate . ', Now: ' . time() . ')', 'debug');
                return;
            }
        }

        $URL = OfficeGuyAPI::GetURL('/stock/stock/list/', $Gateway->get_option('environment'));

        $Request = array();
        $Request['Credentials'] = OfficeGuyPayment::GetCredentials($Gateway);

        $Response = OfficeGuyAPI::PostRaw($Request, '/stock/stock/list/', $Gateway->get_option('environment'), false);
        if (is_wp_error($Response))
        {
            $Gateway->settings['stock_sync_freq'] = 'none';
            $Gateway->update_option('stock_sync_freq', $Gateway->settings['stock_sync_freq']);
            wp_clear_scheduled_hook('officeguy_cron');
            OfficeGuyAPI::WriteToLog(__('Problem connecting to server at ', 'woo-payment-gateway-officeguy') . $URL . ' (' . $Response->get_error_message() . ')', 'error');
            return null;
        }

        $Body = wp_remote_retrieve_body($Response);
        $Body = json_decode($Body, true);

        $StockData = $Body['Data']['Stock'];
        $Gateway->stock_sync_last = time();
        $Gateway->update_option('stock_sync_last', $Gateway->stock_sync_last);

        if (!isset($StockData))
            return;

        foreach ($StockData as $StockItem)
        {
            $ExternalIdentifier = $StockItem['ExternalIdentifier'];
            if (empty($ExternalIdentifier))
            {
                if (!isset($StockItem['Name']) || !is_string($StockItem['Name']) || trim($StockItem['Name']) === '')
                    continue;

                $Products = get_posts(array('title' => $StockItem['Name'], 'post_type' => 'product', 'post_status' => get_post_stati(), 'numberposts' => 1));
                if (empty($Products))
                    $Products = get_posts(array('title' => $StockItem['Name'], 'post_type' => 'product_variation', 'post_status' => get_post_stati(), 'numberposts' => 1));
                if (!empty($Products))
                    $ExternalIdentifier = $Products[0]->ID;
            }

            wc_update_product_stock($ExternalIdentifier, $StockItem['Stock']);
            OfficeGuyAPI::WriteToLog('Stock: Updated ' . $ExternalIdentifier . ': ' . $StockItem['Stock'], 'debug');
        }
    }

    public static function UpdateStockOnCheckout()
    {
        $Gateway = GetOfficeGuyGateway();
        $Flag = $Gateway->settings['checkout_stock_sync'];

        if (isset($Flag) && $Flag == 'yes')
            OfficeGuyStock::InternalUpdateStock(false);
    }

    public static function CreateSchedules($Gateway)
    {
        if (empty($Gateway->settings['stock_sync_freq']))
            $Frequency = '';
        else
            $Frequency = $Gateway->settings['stock_sync_freq'];
        $CronInterval = '';

        if ($Frequency === '12')
            $CronInterval = 'twelve_hours';
        elseif ($Frequency === '24')
            $CronInterval = 'daily';
        else
        {
            wp_clear_scheduled_hook('officeguy_cron');
            return;
        }
        if (wp_get_schedule('officeguy_cron') !== $CronInterval)
        {
            wp_clear_scheduled_hook('officeguy_cron');
            wp_schedule_event(time(), $CronInterval, 'officeguy_cron');
        }
    }

    public static function SetupCronIntervals($schedules)
    {
        $schedules['twelve_hours'] = array(
            'interval' => 43200,
            'display' => __('Every 12 hours', 'woo-payment-gateway-officeguy')
        );
        return $schedules;
    }

    public static function RegisterDashboardWidget()
    {
        if (!current_user_can('manage_woocommerce'))
            return;

        wp_add_dashboard_widget('officeguy_dashboard_widget', 'SUMIT', 'OfficeGuyStock::RenderDashboardWidget');

        if (isset($_POST['synchronize_officeguy']) && isset($_POST['officeguy_synchronize']) && is_string($_POST['officeguy_synchronize']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['officeguy_synchronize'])), 'officeguy_nonce'))
            OfficeGuyStock::InternalUpdateStock(true);
    }

    public static function RenderDashboardWidget()
    { ?>
        <form method="post">
            <?php wp_nonce_field('officeguy_nonce', 'officeguy_synchronize'); ?>
            <h3><?php esc_html_e('Manual Stock Synchronization', 'woo-payment-gateway-officeguy'); ?></h3>
            <p><?php esc_html_e('Click the button below to manually sync stock now', 'woo-payment-gateway-officeguy'); ?></p>

            <input type="submit" name="synchronize_officeguy" class="button button-primary" value="<?php esc_attr_e('Synchronize Stock', 'woo-payment-gateway-officeguy'); ?>">
        </form>
        <?php
    }

    /**
     * Alert the user if synchronization process
     * end with success or not
     */
    public static function DashboardSyncMessage()
    {
        if (!current_user_can('manage_woocommerce'))
            return;

        if (isset($_POST['synchronize_officeguy']) && isset($_POST['officeguy_synchronize']) && is_string($_POST['officeguy_synchronize']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['officeguy_synchronize'])), 'officeguy_nonce'))
        { ?>
            <div class="notice-success notice">
                <p><?php esc_html_e('Stock synced successfully!', 'woo-payment-gateway-officeguy'); ?></p>
            </div>
        <?php
        }
        elseif (isset($_POST['synchronize_officeguy']))
        { ?>
            <div class="error notice">
                <p><?php esc_html_e('Something went wrong.', 'woo-payment-gateway-officeguy'); ?></p>
            </div>
<?php
        }
    }
}

add_filter('cron_schedules', 'OfficeGuyStock::SetupCronIntervals');
add_action('officeguy_cron', 'OfficeGuyStock::UpdateStock');
add_action('woocommerce_proceed_to_checkout', 'OfficeGuyStock::UpdateStockOnCheckout');
add_action('wp_dashboard_setup', 'OfficeGuyStock::RegisterDashboardWidget');
add_action('admin_notices', 'OfficeGuyStock::DashboardSyncMessage');

?>
