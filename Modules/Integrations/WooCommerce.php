<?php

namespace VLT\Framework\Modules\Integrations;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * WooCommerce Integration Module
 */
class WooCommerce extends BaseModule
{

	protected $name = 'woocommerce';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		// Only proceed if WooCommerce is active
		if (!class_exists('WooCommerce')) {
			return;
		}

		add_action('after_setup_theme', [$this, 'setup_woocommerce_support'], 10);
		add_action('after_setup_theme', [$this, 'apply_image_sizes'], 99);
	}

	/**
	 * Initialize module
	 */
	public function init() {}

	/**
	 * Setup WooCommerce theme support
	 */
	public function setup_woocommerce_support()
	{
		$config = $this->get_config('woocommerce', []);
		$supports = $config['support'] ?? ['woocommerce'];

		if (empty($supports)) {
			return;
		}

		foreach ($supports as $feature) {
			add_theme_support($feature);
		}
	}

	/**
	 * Apply WooCommerce image sizes from theme config
	 */
	public function apply_image_sizes()
	{
		$config = $this->get_config('woocommerce', []);

		if (empty($config)) {
			return;
		}

		// Update WooCommerce thumbnail image width
		if (isset($config['thumbnail_image_width'])) {
			update_option('woocommerce_thumbnail_image_width', absint($config['thumbnail_image_width']));
		}

		// Update WooCommerce single product image width
		if (isset($config['single_image_width'])) {
			update_option('woocommerce_single_image_width', absint($config['single_image_width']));
		}

		// Update WooCommerce gallery thumbnail size
		if (isset($config['gallery_thumbnail_image_width'])) {
			$size = absint($config['gallery_thumbnail_image_width']);
			update_option('woocommerce_thumbnail_cropping', 'custom');
			update_option('woocommerce_thumbnail_cropping_custom_width', $size);
			update_option('woocommerce_thumbnail_cropping_custom_height', $size);
		}
	}
}
