<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if (! defined('ABSPATH')) {
	exit;
}

class Actions extends BaseModule
{

	protected $name = 'actions';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		add_action('wp_body_open', [$this, 'wp_body_open']);
		add_action('wp_body_open', [$this, 'skip_link']);

		do_action('vlt_framework_actions_init');
	}

	public function wp_body_open()
	{
		return do_action('vlt_framework_action_body_open');
	}

	public function skip_link()
	{
		$text_domain = $this->get_config('text_domain', 'vlt-framework');
		echo '<a class="skip-link screen-reader-text" href="#content">' . esc_html__('Skip to content', $text_domain) . '</a>';
	}
}
