<?php
/**
 * Modern settings adapter for the Square settings page.
 *
 * @package WooCommerce\Square\Admin
 */

namespace WooCommerce\Square\Admin;

use Automattic\WooCommerce\Admin\Settings\LegacySettingsPageAdapter;

defined( 'ABSPATH' ) || exit;

/**
 * Adapts Square_Modern_Settings_Page into the ModernSettingsPageInterface contract.
 *
 * - get_save_adapter() returns 'none' so the SDK never attempts form_post saves;
 *   our existing REST controllers own persistence.
 * - get_script_handles() returns our settings script so it loads before the SDK
 *   mounts, giving registerSettingsExtension time to run.
 *
 * @since x.x.x
 */
final class Square_Modern_Settings_Adapter extends LegacySettingsPageAdapter {

	/**
	 * Returns 'none' — form_post is incompatible with Square's serialized
	 * option store (wc_square_settings, woocommerce_square_*_settings blobs).
	 *
	 * @since x.x.x
	 * @param string $section Section slug.
	 * @return string
	 */
	public function get_save_adapter( string $section ): string {
		return 'none';
	}

	/**
	 * Returns the script handle that must load before the SDK mounts.
	 *
	 * The handle is registered in Admin.php via wp_enqueue_script().
	 * It calls registerSettingsExtension() at module level, so components
	 * are available when settings-embed.js resolves them.
	 *
	 * @since x.x.x
	 * @param string $section Section slug.
	 * @return string[]
	 */
	public function get_script_handles( string $section ): array {
		return array( 'woocommerce-square-settings-js' );
	}
}
