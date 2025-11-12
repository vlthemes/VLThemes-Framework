<?php

/**
 * VLT Framework Configuration
 *
 * This configuration can be overridden by themes via 'vlt_framework_config' filter
 *
 * @package VLT_Framework
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

return [

	// ========================================
	// TEXT DOMAIN & LOCALIZATION
	// ========================================
	'text_domain' => '@@textdomain',

	// ========================================
	// ASSETS SETTINGS
	// ========================================
	'assets' => [
		'google_fonts' => true,
		'preload_fonts' => true,
		'jquery_in_footer' => true,
	],

	// ========================================
	// CONTENT WIDTH
	// ========================================
	'content_width' => 1170,

	// ========================================
	// THEME SUPPORT
	// ========================================
	'theme_support' => [
		'title-tag',
		'post-thumbnails',
		'automatic-feed-links',
		'html5' => ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption'],
		'post-formats' => ['gallery', 'video', 'audio', 'quote', 'link'],
	],

	// ========================================
	// NAVIGATION MENUS
	// ========================================
	'nav_menus' => [
		'primary-menu' => 'Primary Menu',
	],

	// ========================================
	// IMAGE SIZES
	// ========================================
	'image_sizes' => [
		// Format: 'name' => [width, height, crop]
	],

	// ========================================
	// SIDEBARS (Default sidebars)
	// ========================================
	'sidebars' => [
		// Format:
		// [
		// 	'name' => 'Blog Sidebar',
		// 	'id' => 'blog-sidebar',
		// 	'description' => 'Sidebar for blog pages.',
		// ],
		// Shop sidebar добавляется автоматически в модуле Sidebars если WooCommerce активен
	],

	// ========================================
	// CUSTOMIZER SETTINGS
	// ========================================
	'customizer' => [
		'config_id' => 'vlt_customize',
		'capability' => 'edit_theme_options',
		'option_type' => 'theme_mod',
	],

	// ========================================
	// GOOGLE FONTS
	// ========================================
	'google_fonts' => [
		// Format:
		// 'Onest:wght@100..900',
	],

];
