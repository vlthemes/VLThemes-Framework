<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

class Sidebars extends BaseModule
{

	protected $name = 'sidebars';

	/**
	 * Register module
	 */
	public function register()
	{
		add_action('widgets_init', [$this, 'register_sidebars']);
	}

	/**
	 * Register all sidebars
	 */
	public function register_sidebars()
	{
		// Register default sidebars from config
		$this->register_default_sidebars();

		// Register Shop Sidebar if WooCommerce is active
		if (class_exists('WooCommerce')) {
			$this->register_shop_sidebar();
		}

		// Allow themes to register custom sidebars
		$this->register_custom_sidebars();
	}

	/**
	 * Register default sidebars from config
	 */
	private function register_default_sidebars()
	{
		$sidebars = $this->get_config('sidebars', []);

		if (empty($sidebars) || !is_array($sidebars)) {
			return;
		}

		foreach ($sidebars as $sidebar) {
			$this->register_single_sidebar($sidebar);
		}
	}

	/**
	 * Register shop sidebar for WooCommerce
	 */
	private function register_shop_sidebar()
	{
		$this->register_single_sidebar([
			'name' => __('Shop Sidebar', '@@textdomain'),
			'id' => 'shop-sidebar',
			'description' => __('Sidebar for WooCommerce shop pages.', '@@textdomain'),
		]);
	}

	/**
	 * Register custom sidebars from theme filter
	 */
	private function register_custom_sidebars()
	{
		$custom_sidebars = apply_filters('vlt_framework_custom_sidebars', []);

		if (empty($custom_sidebars) || !is_array($custom_sidebars)) {
			return;
		}

		foreach ($custom_sidebars as $sidebar) {
			$this->register_single_sidebar($sidebar);
		}
	}

	/**
	 * Register single sidebar
	 */
	private function register_single_sidebar($sidebar)
	{
		if (empty($sidebar['id']) || empty($sidebar['name'])) {
			return;
		}

		$defaults = [
			'name' => '',
			'id' => '',
			'description' => '',
			'before_widget' => '<div id="%1$s" class="vlt-widget %2$s">',
			'after_widget' => '</div>',
			'before_title' => '<h5 class="vlt-widget__title">',
			'after_title' => '</h5>',
		];

		$args = wp_parse_args($sidebar, $defaults);

		register_sidebar($args);
	}
}
