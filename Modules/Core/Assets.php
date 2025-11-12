<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Assets Module
 *
 * Handles enqueuing scripts and styles for admin and frontend
 * Manages Google Fonts, jQuery positioning, and asset loading hooks
 */
class Assets extends BaseModule
{

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'assets';

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

		// Register all framework assets (don't enqueue yet)
		add_action('wp_enqueue_scripts', [$this, 'register_assets'], 1);

		// Admin assets (only framework admin files)
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);

		// jQuery in footer option
		if ($this->get_config('assets.jquery_in_footer', true)) {
			add_action('wp_default_scripts', [$this, 'move_jquery_to_footer']);
		}

		// Google fonts preload/preconnect
		if ($this->get_config('assets.preload_fonts', true)) {
			add_filter('wp_resource_hints', [$this, 'resource_hints'], 10, 2);
			add_action('wp_head', [$this, 'preload_google_fonts'], 1);
		}

		// Hooks for themes to use
		add_action('wp_enqueue_scripts', [$this, 'frontend_hooks'], 5);
	}

	/**
	 * Register all framework assets
	 *
	 * Registers scripts and styles but doesn't enqueue them
	 * Other modules can enqueue these as dependencies
	 */
	public function register_assets()
	{
		// Allow themes/plugins to register additional assets
		do_action('vlt_framework_register_assets');
	}

	/**
	 * Frontend asset hooks
	 *
	 * Provides hooks for themes to enqueue their own scripts and styles
	 */
	public function frontend_hooks()
	{
		// Comment reply script for singular posts/pages with comments
		if (is_singular() && comments_open()) {
			wp_enqueue_script('comment-reply');
		}

		// Imagesloaded library
		wp_enqueue_script('imagesloaded');

		// Hook for theme to enqueue scripts
		do_action('vlt_framework_enqueue_scripts');

		// Hook for theme to enqueue styles
		do_action('vlt_framework_enqueue_styles');
	}

	/**
	 * Enqueue admin scripts
	 *
	 * Only loads framework admin.min.js file
	 */
	public function enqueue_admin_scripts()
	{
		// Hook for additional admin scripts
		do_action('vlt_framework_admin_enqueue_scripts');
	}

	/**
	 * Enqueue admin styles
	 *
	 * Only loads framework admin.min.css file
	 */
	public function enqueue_admin_styles()
	{
		// Hook for additional admin styles
		do_action('vlt_framework_admin_enqueue_styles');
	}

	/**
	 * Get Google Fonts URL
	 *
	 * Generates Google Fonts API URL from array of font families
	 *
	 * @param array $fonts Array of font families (e.g. ['Roboto:wght@400;700', 'Open Sans']).
	 * @return string Google Fonts URL or empty string.
	 */
	public function get_google_fonts_url($fonts = [])
	{
		if (empty($fonts)) {
			$fonts = apply_filters('vlt_framework_google_fonts', []);
		}

		if (empty($fonts)) {
			return '';
		}

		$families = array_map(function ($font) {
			$parts = explode(':', $font, 2);
			$font_name = $parts[0];
			$font_weights = isset($parts[1]) ? ':' . $parts[1] : '';
			return urlencode($font_name) . $font_weights;
		}, $fonts);

		return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
	}

	/**
	 * Add resource hints for Google Fonts
	 *
	 * Adds preconnect hints to improve font loading performance
	 *
	 * @param array  $hints Array of resource hints.
	 * @param string $relation_type Type of resource hint.
	 * @return array Modified hints array.
	 */
	public function resource_hints($hints, $relation_type)
	{
		if ('preconnect' === $relation_type) {
			$hints[] = ['href' => 'https://fonts.googleapis.com'];
			$hints[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'];
		}
		return $hints;
	}

	/**
	 * Preload Google Fonts
	 *
	 * Adds preload link tag to improve font loading performance
	 */
	public function preload_google_fonts()
	{
		$fonts_url = $this->get_google_fonts_url();
		if ($fonts_url) {
			printf(
				'<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n",
				esc_url($fonts_url)
			);
		}
	}

	/**
	 * Move jQuery to footer
	 *
	 * Improves page load performance by deferring jQuery execution
	 *
	 * @param WP_Scripts $wp_scripts WordPress scripts object.
	 */
	public function move_jquery_to_footer($wp_scripts)
	{
		if (is_admin()) {
			return;
		}

		$wp_scripts->add_data('jquery', 'group', 1);
		$wp_scripts->add_data('jquery-core', 'group', 1);
		$wp_scripts->add_data('jquery-migrate', 'group', 1);
	}
}
