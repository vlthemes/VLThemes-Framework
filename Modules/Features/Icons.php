<?php

namespace VLT\Framework\Modules\Features;

use VLT\Framework\BaseModule;
use VLT\Framework\Modules\Utils\Sanitize;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Icons Module
 */
class Icons extends BaseModule
{

	private static $icons_cache = null;

	/**
	 * Register module
	 */
	public function register()
	{
	}

	/**
	 * Get icon SVG markup
	 */
	public static function get($icon, $class = '')
	{
		$icons = self::get_icons();

		if (!isset($icons[$icon])) {
			return '';
		}

		$class = self::sanitize_class($class);
		$svg = $icons[$icon];

		if (!empty($class)) {
			if (strpos($svg, 'class="') !== false) {
				$svg = preg_replace('/class="([^"]*)"/', 'class="$1 ' . esc_attr($class) . '"', $svg);
			} else {
				$svg = preg_replace('/<svg/', '<svg class="' . esc_attr($class) . '"', $svg, 1);
			}
		}

		return $svg;
	}

	/**
	 * Check if icon exists
	 */
	public static function exists($icon)
	{
		$icons = self::get_icons();
		return isset($icons[$icon]);
	}

	/**
	 * Get all available icons
	 */
	public static function get_all()
	{
		return self::get_icons();
	}

	/**
	 * Get icon names
	 */
	public static function get_names()
	{
		$icons = self::get_icons();
		return array_keys($icons);
	}

	/**
	 * Get all icons from filter with caching
	 */
	private static function get_icons()
	{
		if (null !== self::$icons_cache) {
			return self::$icons_cache;
		}

		self::$icons_cache = apply_filters('vlt_framework_icons', array());

		return self::$icons_cache;
	}

	/**
	 * Clear icons cache
	 */
	public static function clear_cache()
	{
		self::$icons_cache = null;
	}

	/**
	 * Sanitize CSS class
	 */
	private static function sanitize_class($class)
	{
		return Sanitize::sanitize_class($class);
	}
}
