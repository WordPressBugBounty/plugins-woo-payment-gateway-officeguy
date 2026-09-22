<?php
if (!defined('ABSPATH'))
    exit;

class OfficeGuyRequestHelpers
{
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Request accessor; payment callbacks authenticate against SUMIT at the call site.
    public static function Get($Name)
    {
        if (isset($_GET[$Name]) && is_string($_GET[$Name]))
            return wp_unslash($_GET[$Name]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve opaque payment values; callers must validate or sanitize for their specific use.
        return null;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Request accessor; WooCommerce verifies form nonces, and SUMIT callbacks verify payment through the API.
    public static function Post($Name)
    {
        if (isset($_POST[$Name]) && is_string($_POST[$Name]))
            return wp_unslash($_POST[$Name]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve opaque payment values; callers must validate or sanitize for their specific use.
        return null;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing
}
