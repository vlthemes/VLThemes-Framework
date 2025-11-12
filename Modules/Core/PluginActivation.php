<?php

/**
 * Plugin Activation Module
 *
 * Handles required and recommended plugin management via TGM Plugin Activation.
 * Provides a centralized system for themes to declare plugin dependencies.
 *
 * @package VLThemesFramework
 * @subpackage Core
 * @since 1.0.0
 */

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class PluginActivation extends BaseModule
{

	/**
	 * Initialize the module.
	 *
	 * Loads TGM Plugin Activation library and registers hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init()
	{
		// Load TGM Plugin Activation class
		$this->load_tgmpa();

		// Register plugins on tgmpa_register hook
		add_action('tgmpa_register', array($this, 'register_plugins'));

		// Admin notices for critical plugins
		add_action('admin_notices', array($this, 'critical_plugin_notice'));
	}

	/**
	 * Load TGM Plugin Activation library.
	 *
	 * Includes the TGMPA class file if not already loaded.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_tgmpa()
	{
		$tgmpa_path = VLT_FRAMEWORK_PATH . 'includes/lib/class-tgm-plugin-activation.php';

		if (file_exists($tgmpa_path) && ! class_exists('TGM_Plugin_Activation')) {
			require_once $tgmpa_path;
		}
	}

	/**
	 * Register required and recommended plugins.
	 *
	 * Filters allow themes to:
	 * 1. Add their own plugins via 'vlt_framework_tgmpa_plugins'
	 * 2. Modify default plugin source via 'vlt_framework_plugins_source'
	 * 3. Customize TGMPA config via 'vlt_framework_tgmpa_config'
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_plugins()
	{
		// Get default plugin source URL
		$default_source = apply_filters(
			'vlt_framework_plugins_source',
			'https://vlthemes.me/plugins/'
		);
		$default_source = trailingslashit(esc_url_raw($default_source));

		// Allow themes to register their plugins
		$plugins = apply_filters('vlt_framework_tgmpa_plugins', array(), $default_source);

		// TGMPA configuration
		$config = array(
			'id'           => $this->get_config('tgmpa_id', '@@textdomain'),
			'default_path' => $default_source,
			'menu'         => 'tgmpa-install-plugins',
			'has_notices'  => true,
			'dismissable'  => true,
			'is_automatic' => false,
			'message'      => '',
		);

		// Allow themes to customize config
		$config = apply_filters('vlt_framework_tgmpa_config', $config);

		// Register plugins with TGMPA
		if (function_exists('tgmpa')) {
			tgmpa($plugins, $config);
		}
	}

	/**
	 * Display admin notice for critical plugins.
	 *
	 * Shows a dismissible notice if critical plugins are not active.
	 * Themes can specify critical plugins via 'vlt_framework_critical_plugins' filter.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function critical_plugin_notice()
	{
		// Only show to users who can activate plugins
		if (! is_admin() || ! current_user_can('activate_plugins')) {
			return;
		}

		// Get critical plugins from config
		$critical_plugins = apply_filters('vlt_framework_critical_plugins', array());

		if (empty($critical_plugins)) {
			return;
		}

		// Check which critical plugins are missing
		$missing_plugins = array();
		foreach ($critical_plugins as $plugin) {
			// Check by class name
			if (isset($plugin['class']) && ! class_exists($plugin['class'])) {
				$missing_plugins[] = $plugin['name'];
			}
			// Check by function name
			elseif (isset($plugin['function']) && ! function_exists($plugin['function'])) {
				$missing_plugins[] = $plugin['name'];
			}
		}

		// Display notice if any critical plugins are missing
		if (! empty($missing_plugins)) {
			$plugin_list = implode(', ', array_map(function ($name) {
				return '<strong>' . esc_html($name) . '</strong>';
			}, $missing_plugins));

			$message = sprintf(
				/* translators: %s: list of plugin names */
				_n(
					'Please activate %s before you continue working with this theme.',
					'Please activate the following plugins before you continue working with this theme: %s',
					count($missing_plugins),
					'@@textdomain'
				),
				$plugin_list
			);

			echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
		}
	}
}
