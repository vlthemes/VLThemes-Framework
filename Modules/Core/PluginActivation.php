<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Plugin Activation Module
 */
class PluginActivation extends BaseModule
{
	/**
	 * Initialize module
	 */
	public function init(): void
	{
		$this->load_tgmpa();
		add_action('tgmpa_register', [ $this, 'register_plugins' ]);
		add_action('admin_notices', [ $this, 'critical_plugin_notice' ]);
	}

	/**
	 * Load TGM Plugin Activation library
	 */
	private function load_tgmpa(): void
	{
		$tgmpa_path = VLT_FW_PATH . 'includes/lib/class-tgm-plugin-activation.php';

		if (file_exists($tgmpa_path) && ! class_exists('TGM_Plugin_Activation')) {
			require_once $tgmpa_path;
		}
	}

	/**
	 * Register required and recommended plugins
	 */
	public function register_plugins(): void
	{
		$default_source = apply_filters(
			'vlt_fw_plugins_source',
			'https://vlthemes.me/plugins/',
		);
		$default_source = trailingslashit(esc_url_raw($default_source));

		$plugins = apply_filters('vlt_fw_tgmpa_plugins', [], $default_source);

		if (function_exists('tgmpa')) {
			tgmpa($plugins);
		}
	}

	/**
	 * Display admin notice for critical plugins
	 */
	public function critical_plugin_notice(): void
	{
		if (! is_admin() || ! current_user_can('activate_plugins')) {
			return;
		}

		$critical_plugins = apply_filters('vlt_fw_critical_plugins', []);

		if (empty($critical_plugins)) {
			return;
		}

		$missing_plugins = [];
		foreach ($critical_plugins as $plugin) {
			if (isset($plugin['class']) && ! class_exists($plugin['class'])) {
				$missing_plugins[] = $plugin['name'];
			} elseif (isset($plugin['function']) && ! function_exists($plugin['function'])) {
				$missing_plugins[] = $plugin['name'];
			}
		}

		if (! empty($missing_plugins)) {
			$plugin_list = implode(
				', ',
				array_map(
					function ($name) {
						return '<strong>' . esc_html($name) . '</strong>';
					},
					$missing_plugins,
				),
			);

			$message = sprintf(
				_n(
					'Please activate %s before you continue working with this theme.',
					'Please activate the following plugins before you continue working with this theme: %s',
					count($missing_plugins),
					'@@textdomain',
				),
				$plugin_list,
			);

			echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
		}
	}
}
