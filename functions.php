<?php

/**
 * VLThemes Framework - Global Helper Functions
 *
 * Simple wrappers around framework static methods for convenience.
 * These functions are loaded FIRST before any modules initialize.
 *
 * @package VLT Framework
 *
 * @version 1.0.0
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get theme mod value with ACF override support
 *
 * Universal theme option getter with ACF integration.
 * Order: ACF (post/options page) → Kirki/theme_mod → stored field default → null
 * Archives/search/404 pages always return global values.
 *
 * Wrapper for Kirki::get_theme_mod() static method
 *
 * @param string      $key      Setting key
 * @param bool        $use_acf  Try ACF first? (default: true)
 * @param int|null    $post_id  Post ID for ACF (default: current post)
 * @param string|null $acf_name ACF field name if different from $key
 *
 * @return mixed Theme mod value or null if not found
 */
if ( !function_exists( 'vlt_fw_get_theme_mod' ) ) {
	function vlt_fw_get_theme_mod( $key, $use_acf = true, $post_id = null, $acf_name = null ) {
		if ( class_exists( 'VLT\Framework\Modules\Integrations\Kirki' ) ) {
			return VLT\Framework\Modules\Integrations\Kirki::get_theme_mod( $key, $use_acf, $post_id, $acf_name );
		}

		return get_theme_mod( $key, null );
	}
}

/**
 * Get framework instance
 *
 * @return VLT\Framework\Framework|null
 */
if ( !function_exists( 'vlt_fw' ) ) {
	function vlt_fw() {
		if ( class_exists( 'VLT\Framework\Framework' ) ) {
			return VLT\Framework\Framework::instance();
		}

		return null;
	}
}

/**
 * Get specific framework module
 *
 * @param string $module_name Module name
 *
 * @return object|null
 */
if ( !function_exists( 'vlt_fw_get_module' ) ) {
	function vlt_fw_get_module( $module_name ) {
		$framework = vlt_framework();

		return $framework ? $framework->get_module( $module_name ) : null;
	}
}

/**
 * Check if framework module is loaded
 *
 * @param string $module_name Module name
 *
 * @return bool
 */
if ( !function_exists( 'vlt_fw_has_module' ) ) {
	function vlt_fw_has_module( $module_name ) {
		$framework = vlt_framework();

		return $framework ? $framework->has_module( $module_name ) : false;
	}
}

/**
 * Get framework configuration value
 *
 * @param string $key     Configuration key (dot notation)
 * @param mixed  $default Default value
 *
 * @return mixed
 */
if ( !function_exists( 'vlt_get_config' ) ) {
	function vlt_fw_get_config( $key, $default = null ) {
		$framework = vlt_framework();

		return $framework ? $framework->get_config( $key, $default ) : $default;
	}
}

/**
 * Generate HSL CSS variables from color
 *
 * Converts hex color to HSL format and generates CSS custom properties.
 * Output example: --color: 220, 80%, 50%; --color-h: 220; --color-s: 80%; --color-l: 50%;
 *
 * Wrapper for Kirki::get_hsl_variables() static method
 *
 * @param string $var_name CSS variable name (without --)
 * @param string $color    Color value (hex format: #fff or #ffffff)
 *
 * @return string CSS variables string or empty string on failure
 */
if ( !function_exists( 'vlt_fw_get_hsl_variables' ) ) {
	function vlt_fw_get_hsl_variables( $var_name, $color ) {
		if ( class_exists( 'VLT\Framework\Modules\Integrations\Kirki' ) ) {
			return VLT\Framework\Modules\Integrations\Kirki::get_hsl_variables( $var_name, $color );
		}

		return '';
	}
}

/**
 * Get attachment image with lazy loading support
 *
 * Enhanced wrapper for wp_get_attachment_image() with framework Image helper.
 * Supports custom image sizes and automatic lazy loading.
 *
 * Wrapper for Image::get_attachment_image() static method
 *
 * @param int   $image_id Image attachment ID
 * @param array $args     Optional. Arguments array with keys:
 *                        - size (string|array): Image size key or [w, h, crop]. Default 'full'.
 *                        - class (string): Additional CSS classes. Default ''.
 *                        - image_key (string): Image key for custom dimensions. Default ''.
 *                        - settings (array): Settings array with custom dimensions. Default [].
 *                        - lazy_load (bool): Enable lazy loading. Default true.
 *
 * @return string|false HTML img element or false on failure
 */
if ( !function_exists( 'vlt_fw_get_attachment_image' ) ) {
	function vlt_fw_get_attachment_image( $image_id, $args = [] ) {
		if ( function_exists( 'vlt_toolkit_get_attachment_image' ) ) {
			return vlt_toolkit_get_attachment_image( $image_id, $args );
		}

		// Fallback: basic implementation via wp_get_attachment_image
		if ( empty( $image_id ) ) {
			return false;
		}

		$defaults = [
			'size'      => 'full',
			'class'     => '',
			'lazy_load' => true,
		];

		$args = wp_parse_args( $args, $defaults );

		$attrs = [];

		if ( $args['lazy_load'] ) {
			$attrs['loading'] = 'lazy';
		}

		if ( !empty( $args['class'] ) ) {
			$attrs['class'] = trim( $args['class'] );
		}

		return wp_get_attachment_image( $image_id, $args['size'], false, $attrs );
	}
}

/**
 * Get attachment image source URL
 *
 * Returns image URL with support for custom sizes and responsive images.
 *
 * Wrapper for Image::get_attachment_image_src() static method
 *
 * @param int   $image_id Image attachment ID
 * @param array $args     Optional. Arguments array with keys:
 *                        - size (string|array): Image size key or [w, h, crop]. Default 'full'.
 *                        - image_key (string): Image key for custom dimensions. Default ''.
 *                        - settings (array): Settings array with custom dimensions. Default [].
 *
 * @return string|false Image URL or false if not found
 */
if ( !function_exists( 'vlt_fw_get_attachment_image_src' ) ) {
	function vlt_fw_get_attachment_image_src( $image_id, $args = [] ) {
		if ( function_exists( 'vlt_toolkit_get_attachment_image_src' ) ) {
			return vlt_toolkit_get_attachment_image_src( $image_id, $args );
		}

		// Fallback: basic implementation
		if ( empty( $image_id ) ) {
			return false;
		}

		$defaults = [
			'size' => 'full',
		];

		$args = wp_parse_args( $args, $defaults );

		$image_src = wp_get_attachment_image_src( $image_id, $args['size'] );

		if ( !$image_src ) {
			return false;
		}

		return $image_src[0];
	}
}

/**
 * Convert string to boolean
 *
 * Intelligently converts various string representations to boolean.
 * Handles: 'true', 'false', 'yes', 'no', '1', '0', 'on', 'off', etc.
 *
 * Wrapper for Sanitize::string_to_bool() static method
 *
 * @param mixed $value Value to convert (string, int, bool)
 *
 * @return bool Boolean value
 */
if ( !function_exists( 'vlt_fw_string_to_bool' ) ) {
	function vlt_fw_string_to_bool( $value ) {
		if ( class_exists( 'VLT\Framework\Modules\Utils\Sanitize' ) ) {
			return VLT\Framework\Modules\Utils\Sanitize::string_to_bool( $value );
		}

		return (bool) $value;
	}
}

/**
 * Sanitize CSS class name(s)
 *
 * Sanitizes one or more CSS class names, removing invalid characters.
 * Supports space-separated multiple classes.
 *
 * Wrapper for Sanitize::sanitize_class() static method
 *
 * @param string $class CSS class name(s), space-separated
 *
 * @return string Sanitized CSS class name(s)
 */
if ( !function_exists( 'vlt_fw_sanitize_class' ) ) {
	function vlt_fw_sanitize_class( $class ) {
		if ( class_exists( 'VLT\Framework\Modules\Utils\Sanitize' ) ) {
			return VLT\Framework\Modules\Utils\Sanitize::sanitize_class( $class );
		}

		return sanitize_html_class( $class );
	}
}

/**
 * Sanitize inline CSS style attribute
 *
 * Removes potentially dangerous CSS properties and scripts.
 * Validates CSS syntax and removes malicious code.
 *
 * Wrapper for Sanitize::style() static method
 *
 * @param string $style Inline CSS style string
 *
 * @return string Sanitized CSS style string
 */
if ( !function_exists( 'vlt_fw_sanitize_style' ) ) {
	function vlt_fw_sanitize_style( $style ) {
		if ( class_exists( 'VLT\Framework\Modules\Utils\Sanitize' ) ) {
			return VLT\Framework\Modules\Utils\Sanitize::style( $style );
		}

		return wp_strip_all_tags( $style );
	}
}

/**
 * Fire wp_body_open action with framework hooks
 *
 * WordPress 5.2+ has wp_body_open() function for hooking after <body> tag.
 * This function provides backward compatibility and framework-specific action.
 *
 * Usage in templates: Call immediately after opening <body> tag
 *
 * @return void
 */
if ( !function_exists( 'vlt_fw_body_open' ) ) {
	function vlt_fw_body_open() {
		if ( function_exists( 'wp_body_open' ) ) {
			wp_body_open();
		} else {
			do_action( 'wp_body_open' );
		}

		// Framework-specific action for additional body open hooks
		do_action( 'vlt_fw_body_open' );
	}
}

/**
 * Display navigation menu by location
 *
 * Displays menu with default styling and fallback to admin message.
 * Wrapper for Menus::display_menu() static method.
 *
 * @param string $location Menu location slug
 * @param array  $args     Optional. wp_nav_menu arguments array
 *
 * @return void|false False if menu doesn't exist, void otherwise
 */
if ( !function_exists( 'vlt_fw_display_menu' ) ) {
	function vlt_fw_display_menu( $location, $args = [] ) {
		if ( class_exists( 'VLT\Framework\Modules\Core\Menus' ) ) {
			return VLT\Framework\Modules\Core\Menus::display_menu( $location, $args );
		}

		return false;
	}
}

/**
 * Check if menu location has menu assigned
 *
 * Wrapper for Menus::has_menu() static method.
 *
 * @param string $location Menu location
 *
 * @return bool
 */
if ( !function_exists( 'vlt_fw_has_menu' ) ) {
	function vlt_fw_has_menu( $location ) {
		if ( class_exists( 'VLT\Framework\Modules\Core\Menus' ) ) {
			return VLT\Framework\Modules\Core\Menus::has_menu( $location );
		}

		return false;
	}
}

/**
 * Get navigation breakpoint for responsive menu
 *
 * Returns the screen size breakpoint at which mobile menu activates.
 * Filterable via 'vlt_fw_nav_breakpoint' filter.
 *
 * Wrapper for Menus::get_nav_breakpoint() static method
 *
 * @return string Breakpoint size identifier (xs, sm, md, lg, xl), default: 'xl'
 */
if ( !function_exists( 'vlt_fw_nav_breakpoint' ) ) {
	function vlt_fw_nav_breakpoint() {
		if ( class_exists( 'VLT\Framework\Modules\Core\Menus' ) ) {
			return VLT\Framework\Modules\Core\Menus::get_nav_breakpoint();
		}

		return 'xl';
	}
}

/**
 * Get SVG icon markup
 *
 * Retrieves SVG icon from framework icon registry.
 * Icons are registered via 'vlt_fw_icons' filter.
 *
 * Wrapper for Icons::get() static method
 *
 * @param string $icon  Icon name/slug
 * @param string $class Additional CSS class to add to SVG element
 *
 * @return string SVG markup or empty string if icon not found
 */
if ( !function_exists( 'vlt_fw_get_svg_icon' ) ) {
	function vlt_fw_get_svg_icon( $icon, $class = '' ) {
		if ( class_exists( 'VLT\Framework\Modules\Features\Icons' ) ) {
			return VLT\Framework\Modules\Features\Icons::get( $icon, $class );
		}

		return '';
	}
}

/**
 * Parse video ID from URL
 *
 * Extracts video ID from YouTube, Vimeo, and other video platform URLs.
 * Returns array with 'type' and 'id' keys.
 *
 * Wrapper for Helpers::parse_video_id() static method
 *
 * @param string $url Video URL (YouTube, Vimeo, etc.)
 *
 * @return array|string Video data array or empty string on failure
 */
if ( !function_exists( 'vlt_fw_parse_video_id' ) ) {
	function vlt_fw_parse_video_id( $url ) {
		if ( function_exists( 'vlt_toolkit_parse_video_id' ) ) {
			return vlt_toolkit_parse_video_id( $url );
		}

		return '';
	}
}

/**
 * Get trimmed content with word limit
 *
 * Trims post content to specified word count.
 * Strips shortcodes and HTML tags, adds ellipsis.
 *
 * Wrapper for Helpers::get_trimmed_content() static method
 *
 * @param int|null $post_id   Post ID, null for current post
 * @param int      $max_words Maximum number of words, default: 18
 *
 * @return string Trimmed content with ellipsis or empty string
 */
if ( !function_exists( 'vlt_fw_get_trimmed_content' ) ) {
	function vlt_fw_get_trimmed_content( $post_id = null, $max_words = 18 ) {
		if ( function_exists( 'vlt_toolkit_get_trimmed_content' ) ) {
			return vlt_toolkit_get_trimmed_content( $post_id, $max_words );
		}

		return get_the_excerpt( $post_id );
	}
}

/**
 * Safely retrieve ACF field value with error handling
 *
 * Enhanced wrapper for ACF get_field() with exception handling.
 * Returns null if ACF is not active or field doesn't exist.
 *
 * @param string    $field_name   ACF field name
 * @param int|false $post_id      Post ID or false for current post, default: false (current post)
 * @param bool      $format_value Whether to apply ACF formatting, default: true
 *
 * @return mixed Field value, or null if not found or ACF not active
 */
if ( !function_exists( 'vlt_fw_get_field' ) ) {
	function vlt_fw_get_field( $field_name, $post_id = false, $format_value = true ) {
		if ( null === $field_name || !function_exists( 'get_field' ) ) {
			return null;
		}

		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$value = null;

		try {
			$value = get_field( $field_name, $post_id, $format_value );
		} catch ( Exception $e ) {
			$value = null;
		}

		return $value;
	}
}

/**
 * Get placeholder image source URL
 */
if ( !function_exists( 'vlt_fw_get_placeholder_image_src' ) ) {
	function vlt_fw_get_placeholder_image_src() {
		if ( function_exists( 'vlt_toolkit_get_placeholder_image_src' ) ) {
			return vlt_toolkit_get_placeholder_image_src();
		}

		return '';
	}
}

/**
 * Get placeholder image HTML
 */
if ( !function_exists( 'vlt_fw_get_placeholder_image' ) ) {
	function vlt_fw_get_placeholder_image( $class = '', $alt = '' ) {
		if ( function_exists( 'vlt_toolkit_get_placeholder_image' ) ) {
			return vlt_toolkit_get_placeholder_image( $class, $alt );
		}

		return '';
	}
}

/**
 * Get choices array for a specific Kirki setting
 *
 * Returns the choices array (key => value pairs) for a given Kirki setting ID.
 * Useful for retrieving select options, radio options, etc.
 *
 * @param string $setting_id The Kirki setting ID
 *
 * @return array Choices array or empty array if not found
 */
if ( !function_exists( 'vlt_fw_get_setting_choices' ) ) {
	function vlt_fw_get_setting_choices( $setting_id ) {
		if ( class_exists( '\VLT\Framework\Modules\Integrations\Kirki' ) ) {
			return VLT\Framework\Modules\Integrations\Kirki::get_setting_choices( $setting_id );
		}

		return [];
	}
}
