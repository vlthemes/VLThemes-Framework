<?php

/**
 * Icons Module
 *
 * Manages SVG icons through filter-based system.
 * Icons are added via 'vlt_framework_icons' filter from themes or plugins.
 *
 * @package VLT\Framework
 * @subpackage Utils
 * @since 1.0.0
 */

namespace VLT\Framework\Modules\Features;

use VLT\Framework\BaseModule;
use VLT\Framework\Modules\Utils\Sanitize;

if (!defined('ABSPATH')) {
	exit;
}

class Icons extends BaseModule
{

	/**
	 * Icons cache
	 *
	 * @var array|null
	 */
	private static $icons_cache = null;

	/**
	 * Register module
	 */
	public function register()
	{
		// No functions to register - using global functions.php
	}

	/**
	 * Get icon SVG markup
	 *
	 * Returns SVG markup for the requested icon with optional CSS class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $icon  Icon name.
	 * @param string $class Additional CSS class (will be added to existing or new class attribute).
	 * @return string Icon SVG markup or empty string if not found.
	 */
	public static function get($icon, $class = '')
	{
		// Get icons from cache or filter
		$icons = self::get_icons();

		// Check if icon exists
		if (!isset($icons[$icon])) {
			return '';
		}

		// Sanitize class
		$class = self::sanitize_class($class);

		// Get icon SVG
		$svg = $icons[$icon];

		// Add class to SVG if provided
		if (!empty($class)) {
			// Check if SVG already has class attribute
			if (strpos($svg, 'class="') !== false) {
				// Add to existing class
				$svg = preg_replace('/class="([^"]*)"/', 'class="$1 ' . esc_attr($class) . '"', $svg);
			} else {
				// Add new class attribute to SVG tag
				$svg = preg_replace('/<svg/', '<svg class="' . esc_attr($class) . '"', $svg, 1);
			}
		}

		return $svg;
	}

	/**
	 * Check if icon exists
	 *
	 * @since 1.0.0
	 *
	 * @param string $icon Icon name.
	 * @return bool True if icon exists, false otherwise.
	 */
	public static function exists($icon)
	{
		$icons = self::get_icons();
		return isset($icons[$icon]);
	}

	/**
	 * Get all available icons
	 *
	 * @since 1.0.0
	 * @return array Array of all icons with their SVG markup.
	 */
	public static function get_all()
	{
		return self::get_icons();
	}

	/**
	 * Get icon names
	 *
	 * @since 1.0.0
	 * @return array Array of icon names.
	 */
	public static function get_names()
	{
		$icons = self::get_icons();
		return array_keys($icons);
	}

	/**
	 * Get all icons from filter with caching
	 *
	 * Icons are loaded once and cached for performance.
	 *
	 * @since 1.0.0
	 * @return array Array of icons with SVG markup.
	 */
	private static function get_icons()
	{
		if (null !== self::$icons_cache) {
			return self::$icons_cache;
		}

		/**
		 * Filter: vlt_framework_icons
		 *
		 * Add custom icons from themes or plugins.
		 *
		 * @since 1.0.0
		 *
		 * @param array $icons Array of icons with SVG markup
		 *                     Format: [ 'icon-name' => '<svg>...</svg>' ]
		 */
		self::$icons_cache = apply_filters('vlt_framework_icons', array());

		return self::$icons_cache;
	}

	/**
	 * Clear icons cache
	 *
	 * Useful when icons are dynamically added after initial load.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_cache()
	{
		self::$icons_cache = null;
	}

	/**
	 * Sanitize CSS class
	 *
	 * Wrapper for Sanitize module
	 *
	 * @since 1.0.0
	 *
	 * @param string $class CSS class.
	 * @return string Sanitized CSS class.
	 */
	private static function sanitize_class($class)
	{
		return Sanitize::sanitize_class($class);
	}
}
