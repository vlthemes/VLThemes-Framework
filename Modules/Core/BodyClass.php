<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Body Class Module
 *
 * Adds custom body classes for various theme conditions
 * Handles mobile detection, header/footer types, and theme versioning
 */
class BodyClass extends BaseModule
{

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'body_class';

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
		// Add custom body classes
		add_filter('body_class', [$this, 'add_body_classes'], 10);

		// Add theme version classes
		add_filter('body_class', [$this, 'add_theme_version_classes'], 11);
	}

	/**
	 * Add custom body classes
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes.
	 */
	public function add_body_classes($classes)
	{
		// Mobile detection
		if (! wp_is_mobile()) {
			$classes[] = 'no-mobile';
		} else {
			$classes[] = 'is-mobile';
		}

		// Allow themes to add custom classes via filter
		$classes = apply_filters('vlt_framework_body_class', $classes);

		return $classes;
	}

	/**
	 * Add theme version classes
	 *
	 * Adds classes for theme name and version to body tag
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes.
	 */
	public function add_theme_version_classes($classes)
	{
		$current_theme = wp_get_theme();
		$theme_prefix = 'vlt';

		// Check if theme exists
		if (! $current_theme->exists()) {
			return $classes;
		}

		// Is child theme activated?
		if ($current_theme->parent()) {
			$child_version = $current_theme->get('Version');
			if (! empty($child_version)) {
				$classes[] = $theme_prefix . '-child-theme-version-' . $this->sanitize_version($child_version);
			}
			$current_theme = $current_theme->parent();
		}

		// Add parent theme version and name
		if ($current_theme->exists()) {
			$theme_version = $current_theme->get('Version');
			$theme_name = $current_theme->get('Name');

			if (! empty($theme_version)) {
				$classes[] = $theme_prefix . '-theme-version-' . $this->sanitize_version($theme_version);
			}

			if (! empty($theme_name)) {
				$classes[] = $theme_prefix . '-theme-' . sanitize_html_class(strtolower($theme_name));
			}
		}

		return $classes;
	}

	/**
	 * Sanitize version string for use in CSS class
	 *
	 * @param string $version Version string.
	 * @return string Sanitized version string.
	 */
	private function sanitize_version($version)
	{
		// Remove any non-alphanumeric characters except dots and hyphens
		$version = preg_replace('/[^a-zA-Z0-9.-]/', '', $version);
		// Replace dots with hyphens for valid CSS class names
		$version = str_replace('.', '-', $version);
		return sanitize_html_class($version);
	}
}
