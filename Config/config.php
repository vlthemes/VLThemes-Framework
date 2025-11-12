<?php
/**
 * Framework default configuration
 * Can be overridden via 'vlt_framework_config' filter
 */

if (!defined('ABSPATH')) {
	exit;
}

return [
	'text_domain' => 'vlt-framework',
	'content_width' => 1170,

	'assets' => [
		'google_fonts' => true,
		'preload_fonts' => true,
		'jquery_in_footer' => true,
	],

	'theme_support' => [
		'title-tag',
		'post-thumbnails',
		'automatic-feed-links',
		'html5' => ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption'],
		'post-formats' => ['gallery', 'video', 'audio', 'quote', 'link'],
	],

	'nav_menus' => [
		'primary-menu' => 'Primary Menu',
	],

	'image_sizes' => [],

	'sidebars' => [],

	'customizer' => [
		'config_id' => 'vlt_customize',
		'capability' => 'edit_theme_options',
		'option_type' => 'theme_mod',
	],

	'google_fonts' => [],
];
