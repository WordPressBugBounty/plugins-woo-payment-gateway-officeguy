<?php
if (!defined('ABSPATH'))
    exit;

class OfficeGuyPluginSetup
{
	public static function Init($File)
	{
		register_activation_hook($File, 'OfficeGuyPluginSetup::ActivateHook');
		add_action('admin_init', 'OfficeGuyPluginSetup::ActivationRedirect');
		add_filter('plugin_action_links_' . plugin_basename($File), 'OfficeGuyPluginSetup::ActionLinks', 10, 4);
	}

	public static function ActivateHook()
	{
		add_option('officeguy_plugin_do_activation_redirect', true);
	}

	public static function ActivationRedirect()
	{
		if (!get_option('officeguy_plugin_do_activation_redirect', false))
			return;

		delete_option('officeguy_plugin_do_activation_redirect');
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core display flag suppresses the redirect after bulk activation.
		if (!isset($_GET['activate-multi']))
		{
			wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=officeguy'));
			exit;
		}
	}

	public static function ActionLinks($actions, $plugin_file, $plugin_data, $context)
	{
		$new = array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=officeguy')),
				esc_html__('Settings', 'woo-payment-gateway-officeguy')
			)
		);

		return array_merge($new, $actions);
	}
}
