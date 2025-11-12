<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Setup Module
 *
 * Handles theme setup, content width, image sizes, and theme support features
 */
class Setup extends BaseModule
{

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'setup';

	/**
	 * Module version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Text domain for theme
	 *
	 * @var string
	 */
	private $text_domain = '@@textdomain';

	/**
	 * Register module
	 */
	public function register()
	{
		// Set text domain from config or filter
		$this->text_domain = $this->get_config('text_domain', '@@textdomain');
		$this->text_domain = apply_filters('vlt_framework_text_domain', $this->text_domain);

		add_action('after_setup_theme', [$this, 'theme_setup']);
		add_action('after_setup_theme', [$this, 'content_width'], 0);
	}

	/**
	 * Theme setup
	 */
	public function theme_setup()
	{
		// Theme text domain path
		$theme_domain_path = apply_filters(
			'vlt_framework_theme_domain_path',
			get_template_directory() . '/languages'
		);

		// Load theme text domain
		load_theme_textdomain($this->text_domain, $theme_domain_path);

		// Add theme support
		$this->add_theme_support();

		// Register image sizes
		$this->register_image_sizes();

		do_action('vlt_framework_after_theme_setup');
	}

	/**
	 * Add theme support features from config
	 */
	private function add_theme_support()
	{
		$supports = $this->get_config('theme_support', []);

		foreach ($supports as $feature => $args) {
			if (is_numeric($feature)) {
				add_theme_support($args);
			} else {
				add_theme_support($feature, $args);
			}
		}
	}

	/**
	 * Register custom image sizes from config
	 */
	private function register_image_sizes()
	{
		$sizes = $this->get_config('image_sizes', []);

		foreach ($sizes as $name => $size) {
			add_image_size(
				$name,
				$size[0] ?? 0,
				$size[1] ?? 0,
				$size[2] ?? false
			);
		}
	}

	/**
	 * Set content width
	 */
	public function content_width()
	{
		$GLOBALS['content_width'] = apply_filters('vlt_framework_content_width', 1300);
	}
}
