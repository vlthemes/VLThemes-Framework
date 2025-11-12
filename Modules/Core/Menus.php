<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Menus Module
 *
 * Handles navigation menu registration and display
 * Menus can be added via config or 'vlt_framework_custom_menus' filter
 */
class Menus extends BaseModule
{

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'menus';

	/**
	 * Module version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		add_action('after_setup_theme', [$this, 'register_menus'], 10);
	}

	/**
	 * Register all menus
	 */
	public function register_menus()
	{
		// Register default menus from config
		$this->register_default_menus();

		// Register custom menus from theme
		$this->register_custom_menus();
	}

	/**
	 * Register default menus from config
	 */
	private function register_default_menus()
	{
		$menus = $this->get_config('nav_menus', []);

		if (empty($menus) || ! is_array($menus)) {
			return;
		}

		register_nav_menus($menus);
	}

	/**
	 * Register custom menus from theme filter
	 */
	private function register_custom_menus()
	{
		/**
		 * Filter: vlt_framework_custom_menus
		 *
		 * Add custom menu locations from theme
		 *
		 * @param array $menus Array of menu locations
		 *                     Format: [ 'location-slug' => 'Location Name' ]
		 */
		$custom_menus = apply_filters('vlt_framework_custom_menus', []);

		if (empty($custom_menus) || ! is_array($custom_menus)) {
			return;
		}

		register_nav_menus($custom_menus);
	}

	/**
	 * Check if menu location has menu assigned
	 *
	 * @param string $location Menu location.
	 * @return bool
	 */
	public static function has_menu($location)
	{
		return has_nav_menu($location);
	}

	/**
	 * Get menu by location
	 *
	 * @param string $location Menu location.
	 * @return object|false Menu object or false if not found.
	 */
	public static function get_menu($location)
	{
		$locations = get_nav_menu_locations();

		if (! isset($locations[$location])) {
			return false;
		}

		return wp_get_nav_menu_object($locations[$location]);
	}

	/**
	 * Display navigation menu by location
	 *
	 * Displays menu with default styling and fallback to admin message
	 *
	 * @param string $location Menu location slug.
	 * @param array  $args     Optional. wp_nav_menu arguments array.
	 * @return void|false False if menu doesn't exist, void otherwise.
	 */
	public static function display_menu($location, $args = [])
	{
		// Always set theme_location to ensure WordPress theme check compliance
		$defaults = [
			'theme_location'  => $location,
			'container'       => 'nav',
			'container_class' => 'vlt-menu-' . $location,
			'menu_class'      => 'vlt-menu',
			'fallback_cb'     => [__CLASS__, 'fallback'],
		];

		$args = wp_parse_args($args, $defaults);

		// Ensure theme_location is always set (WordPress theme check requirement)
		if (empty($args['theme_location'])) {
			$args['theme_location'] = $location;
		}

		wp_nav_menu($args);
	}

	/**
	 * Fallback callback when menu is not assigned
	 *
	 * Shows helpful message to administrators with link to menu settings
	 * Regular users see nothing (graceful degradation)
	 *
	 * @return void
	 */
	public static function fallback()
	{
		// Only show message to administrators
		if (! current_user_can('administrator')) {
			return;
		}

		$menu_link = '<a href="' . esc_url(admin_url('nav-menus.php')) . '" target="_blank">' . esc_html__('Appearance > Menus', '@@textdomain') . '</a>';
		$message = sprintf(esc_html__('Please register navigation from %s', '@@textdomain'), $menu_link);

		echo '<p class="vlt-no-menu-message">' . wp_kses_post($message) . '</p>';
	}

	/**
	 * Get navigation breakpoint for responsive menu
	 *
	 * Defines the screen size breakpoint at which the mobile menu activates.
	 * Filterable to allow customization via themes or plugins.
	 *
	 * Default breakpoint: 'xl' (1200px in Bootstrap grid)
	 * Available breakpoints: 'xs', 'sm', 'md', 'lg', 'xl'
	 *
	 * @return string Breakpoint size identifier.
	 */
	public static function get_nav_breakpoint()
	{
		$breakpoint = apply_filters('vlt_framework_nav_breakpoint', 'xl');

		// Validate breakpoint
		$valid_breakpoints = ['xs', 'sm', 'md', 'lg', 'xl'];
		if (! in_array($breakpoint, $valid_breakpoints, true)) {
			$breakpoint = 'xl';
		}

		return $breakpoint;
	}
}
