<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Assets Module
 */
class Assets extends BaseModule
{

	protected $name = 'assets';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		add_action('wp_enqueue_scripts', [$this, 'register_assets'], 1);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);

		if ($this->get_config('assets.jquery_in_footer', true)) {
			add_action('wp_default_scripts', [$this, 'move_jquery_to_footer']);
		}

		if ($this->get_config('assets.preload_fonts', true)) {
			add_filter('wp_resource_hints', [$this, 'resource_hints'], 10, 2);
			add_action('wp_head', [$this, 'preload_google_fonts'], 1);
		}

		add_action('wp_enqueue_scripts', [$this, 'frontend_hooks'], 5);
	}

	/**
	 * Register framework assets
	 */
	public function register_assets()
	{
		do_action('vlt_framework_register_assets');
	}

	/**
	 * Frontend asset hooks
	 */
	public function frontend_hooks()
	{
		if (is_singular() && comments_open()) {
			wp_enqueue_script('comment-reply');
		}

		wp_enqueue_script('imagesloaded');

		do_action('vlt_framework_enqueue_scripts');
		do_action('vlt_framework_enqueue_styles');
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts()
	{
		do_action('vlt_framework_admin_enqueue_scripts');
	}

	/**
	 * Enqueue admin styles
	 */
	public function enqueue_admin_styles()
	{
		do_action('vlt_framework_admin_enqueue_styles');
	}

	/**
	 * Get Google Fonts URL
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
