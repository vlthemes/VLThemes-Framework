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
	public function init()
	{
		$this->load_tgmpa();
		add_action('tgmpa_register', array($this, 'register_plugins'));
		add_action('admin_notices', array($this, 'critical_plugin_notice'));
	}

	/**
	 * Load TGM Plugin Activation library
	 */
	private function load_tgmpa()
	{
		$tgmpa_path = VLT_FRAMEWORK_PATH . 'includes/lib/class-tgm-plugin-activation.php';

		if (file_exists($tgmpa_path) && ! class_exists('TGM_Plugin_Activation')) {
			require_once $tgmpa_path;
		}
	}

	/**
	 * Register required and recommended plugins
	 */
	public function register_plugins()
	{
		$default_source = apply_filters(
			'vlt_framework_plugins_source',
			'https://vlthemes.me/plugins/'
		);
		$default_source = trailingslashit(esc_url_raw($default_source));

		$plugins = apply_filters('vlt_framework_tgmpa_plugins', array(), $default_source);

		$text_domain = $this->get_config('text_domain', 'vlt-framework');
		$config = array(
			'id'           => $this->get_config('tgmpa_id', $text_domain),
			'default_path' => $default_source,
			'menu'         => 'tgmpa-install-plugins',
			'has_notices'  => true,
			'dismissable'  => true,
			'is_automatic' => false,
			'message'      => '',
		);

		$config = apply_filters('vlt_framework_tgmpa_config', $config);

		if (function_exists('tgmpa')) {
			tgmpa($plugins, $config);
		}
	}

	/**
	 * Display admin notice for critical plugins
	 */
	public function critical_plugin_notice()
	{
		if (! is_admin() || ! current_user_can('activate_plugins')) {
			return;
		}

		$critical_plugins = apply_filters('vlt_framework_critical_plugins', array());

		if (empty($critical_plugins)) {
			return;
		}

		$missing_plugins = array();
		foreach ($critical_plugins as $plugin) {
			if (isset($plugin['class']) && ! class_exists($plugin['class'])) {
				$missing_plugins[] = $plugin['name'];
			}
			elseif (isset($plugin['function']) && ! function_exists($plugin['function'])) {
				$missing_plugins[] = $plugin['name'];
			}
		}

		if (! empty($missing_plugins)) {
			$plugin_list = implode(', ', array_map(function ($name) {
				return '<strong>' . esc_html($name) . '</strong>';
			}, $missing_plugins));

			$text_domain = $this->get_config('text_domain', 'vlt-framework');
			$message = sprintf(
				_n(
					'Please activate %s before you continue working with this theme.',
					'Please activate the following plugins before you continue working with this theme: %s',
					count($missing_plugins),
					$text_domain
				),
				$plugin_list
			);

			echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
		}
	}
}
