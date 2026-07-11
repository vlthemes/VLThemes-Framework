<?php

/**
 * Framework default configuration
 * Can be overridden via 'vlt_fw_config' filter
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'content_width' => 1170,

	'assets' => [
		'google_fonts'     => true,
		'preload_fonts'    => true,
		'jquery_in_footer' => true
	],

	'nav_menus' => [
		'primary-menu' => 'Primary Menu'
	],

	'image_sizes' => [],

	'sidebars' => [],

	'customizer' => [
		'config_id'   => 'vlt_customize',
		'capability'  => 'edit_theme_options',
		'option_type' => 'theme_mod'
	],

	'google_fonts' => []
];
