<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Body Class Module
 */
class BodyClass extends BaseModule
{
	protected $name    = 'body_class';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register(): void
	{
		add_filter('body_class', [ $this, 'add_body_classes' ], 10);
		add_filter('body_class', [ $this, 'add_theme_version_classes' ], 11);
	}

	/**
	 * Add custom body classes
	 */
	public function add_body_classes($classes)
	{
		if (! wp_is_mobile()) {
			$classes[] = 'no-mobile';
		} else {
			$classes[] = 'is-mobile';
		}

		$classes = apply_filters('vlt_fw_body_class', $classes);

		return $classes;
	}

	/**
	 * Add theme version classes
	 */
	public function add_theme_version_classes($classes)
	{
		$current_theme = wp_get_theme();
		$theme_prefix  = 'vlt';

		if (! $current_theme->exists()) {
			return $classes;
		}

		if ($current_theme->parent()) {
			$child_version = $current_theme->get('Version');

			if (! empty($child_version)) {
				$classes[] = $theme_prefix . '-child-theme-version-' . $this->sanitize_version($child_version);
			}
			$current_theme = $current_theme->parent();
		}

		if ($current_theme->exists()) {
			$theme_version = $current_theme->get('Version');
			$theme_name    = $current_theme->get('Name');

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
	 * Sanitize version string for CSS class
	 */
	private function sanitize_version($version)
	{
		$version = preg_replace('/[^a-zA-Z0-9.-]/', '', $version);
		$version = str_replace('.', '-', $version);

		return sanitize_html_class($version);
	}
}
