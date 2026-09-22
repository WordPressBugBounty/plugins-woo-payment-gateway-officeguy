<?php
if (!defined('ABSPATH'))
    exit;

class OfficeGuyWCVendorsMarketplace
{
    public static function Init()
    {
        if (!OfficeGuyWCVendorsMarketplace::PluginIsActive())
            return;

        add_action('show_user_profile', 'OfficeGuyWCVendorsMarketplace::OfficeGuyUserAPIKeyFields');
        add_action('edit_user_profile', 'OfficeGuyWCVendorsMarketplace::OfficeGuyUserAPIKeyFields');
        // add_action( 'user_new_form', 'OfficeGuyWCVendorsMarketplace::OfficeGuyUserAPIKeyFields' );

        add_action('personal_options_update', 'OfficeGuyWCVendorsMarketplace::SaveOfficeGuyUserAPIKeyFields');
        add_action('edit_user_profile_update', 'OfficeGuyWCVendorsMarketplace::SaveOfficeGuyUserAPIKeyFields');
    }

    public static function OfficeGuyUserAPIKeyFields($User)
    {
        $OfficeGuyValidCredentialsMsg = get_the_author_meta('OfficeGuyValidCredentials', $User->ID);
        if ($OfficeGuyValidCredentialsMsg != null)
        {
?>
            <div class="error">
                <p><?php echo esc_html('SUMIT error: ' . $OfficeGuyValidCredentialsMsg); ?></p>
            </div>
        <?php
        }

        ?>
        <h3>SUMIT API</h3>

        <table class="form-table">
            <tr>
                <th><label for="officeguycompanyid">Company ID</label></th>
                <td>
                    <input type="text" name="officeguycompanyid" id="officeguycompanyid" value="<?php echo esc_attr(get_the_author_meta('OfficeGuyCompanyID', $User->ID)); ?>" class="regular-text" /><br />
                    <span class="description">SUMIT Company ID for vender</span>
                </td>
            </tr>
            <tr>
                <th><label for="officeguyapikey">API Private Key</label></th>
                <td>
                    <input type="text" name="officeguyapikey" id="officeguyapikey" value="<?php echo esc_attr(get_the_author_meta('OfficeGuyAPIKey', $User->ID)); ?>" class="regular-text" /><br />
                    <span class="description">SUMIT API Key for vender</span>
                </td>
            </tr>
        </table>
<?php
    }

    public static function SaveOfficeGuyUserAPIKeyFields($UserID)
    {
        if (empty($_POST['_wpnonce']) || !is_string($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-user_' . $UserID))
            return;

        if (!current_user_can('edit_user', $UserID))
            return false;

        if (!isset($_POST['officeguycompanyid'], $_POST['officeguyapikey']) || !is_string($_POST['officeguycompanyid']) || !is_string($_POST['officeguyapikey']))
            return;

        $CompanyID = sanitize_text_field(wp_unslash($_POST['officeguycompanyid']));
        $APIKey = sanitize_text_field(wp_unslash($_POST['officeguyapikey']));
        update_user_meta($UserID, 'OfficeGuyCompanyID', $CompanyID);
        update_user_meta($UserID, 'OfficeGuyAPIKey', $APIKey);

        if (!empty($CompanyID) && !empty($APIKey))
        {
            $Response = OfficeGuyAPI::CheckCredentials($CompanyID, $APIKey);
            update_user_meta($UserID, 'OfficeGuyValidCredentials', $Response);
        }
        else
            delete_user_meta($UserID, 'OfficeGuyValidCredentials');
    }

    public static function GetProductVendorCredentials()
    {
        $ProductCredentials = array();
        $ProductIDs = OfficeGuySubscriptions::GetCartProductIDs();
        foreach ($ProductIDs as $ProductID)
        {
            $VendorID = get_post_field('post_author', $ProductID);
            $ProductCredentials[$ProductID]['OfficeGuyCompanyID'] = get_the_author_meta('OfficeGuyCompanyID', $VendorID);
            $ProductCredentials[$ProductID]['OfficeGuyAPIKey'] = get_the_author_meta('OfficeGuyAPIKey', $VendorID);
        }
        return $ProductCredentials;
    }

    public static function PluginIsActive()
    {
        return is_plugin_active('wc-vendors/class-wc-vendors.php') 
            || is_plugin_active('wc-vendors-pro/class-wc-vendors-pro.php');
    }

    public static function VendorsInCartCount()
    {
        $ProductCredentials = OfficeGuyWCVendorsMarketplace::GetProductVendorCredentials();
        $CompanyIDs = array();

        foreach ($ProductCredentials as $ProductCredential)
        {
            $CompanyID = $ProductCredential['OfficeGuyCompanyID'];
            if (is_numeric($CompanyID))
                $CompanyIDs[] = $CompanyID;
        }

        $CompanyIDs = array_unique($CompanyIDs);
        return count($CompanyIDs);
    }
}

add_action('admin_init', 'OfficeGuyWCVendorsMarketplace::Init');
?>
