<?php

namespace VLT\Framework\Modules\Integrations;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kirki Customizer Integration Module
 */
class Kirki extends BaseModule {
	protected $name    = 'kirki';
	protected $version = '1.0.0';
	private $config_id;
	private static $default_options = [];

	/**
	 * Register module
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', [ $this, 'load_customizer_editor_styles' ] );

		if ( !class_exists( 'Kirki' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'load_customizer_frontend_styles' ] );
		}

		$config          = $this->get_config( 'customizer', [] );
		$this->config_id = $config['config_id'] ?? 'vlt_customize';

		$this->init_kirki_config();

		add_action( 'init', [ $this, 'load_theme_customizer' ], 10 );
		add_action( 'init', [ $this, 'register_customizer_elements' ], 20 );
		add_action( 'init', [ $this, 'load_dynamic_css_config' ], 30 );

		if ( !empty( $this->config_id ) ) {
			add_filter( 'kirki_' . $this->config_id . '_dynamic_css', [ $this, 'output_dynamic_css' ] );
		}
	}

	/**
	 * Initialize module
	 */
	public function init() {
	}

	/**
	 * Load customizer editor styles (admin)
	 */
	public function load_customizer_editor_styles() {
		$css_file = apply_filters( 'vlt_fw_kirki_editor_css', 'inc/kirki/css/customizer-editor.css' );
		$css_path = trailingslashit( get_template_directory() ) . $css_file;
		$css_url  = trailingslashit( get_template_directory_uri() ) . $css_file;

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'vlt-customizer-editor', $css_url, [], $this->version );
		}
	}

	/**
	 * Load customizer frontend styles
	 */
	public function load_customizer_frontend_styles() {
		$css_file = apply_filters( 'vlt_fw_kirki_frontend_css', 'inc/kirki/css/customizer-frontend.css' );
		$css_path = trailingslashit( get_template_directory() ) . $css_file;
		$css_url  = trailingslashit( get_template_directory_uri() ) . $css_file;

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'vlt-customizer-frontend', $css_url, [], $this->version );
		}
	}

	/**
	 * Load theme customizer configuration file
	 */
	public function load_theme_customizer() {
		// Allow theme to specify customizer file path
		$customizer_file = apply_filters( 'vlt_fw_kirki_customizer_file', 'inc/kirki/customizer.php' );

		// Check if file exists in theme
		$theme_customizer = trailingslashit( get_template_directory() ) . $customizer_file;

		if ( file_exists( $theme_customizer ) ) {
			require_once $theme_customizer;
		}
	}

	/**
	 * Load dynamic CSS configuration file
	 */
	public function load_dynamic_css_config() {
		// Load dynamic CSS file
		$dynamic_css_file  = apply_filters( 'vlt_fw_kirki_dynamic_css_file', 'inc/kirki/customizer-dynamic-css.php' );
		$theme_dynamic_css = trailingslashit( get_template_directory() ) . $dynamic_css_file;

		if ( file_exists( $theme_dynamic_css ) ) {
			require_once $theme_dynamic_css;
		}
	}

	/**
	 * Register customizer elements (panels, sections, fields)
	 */
	public function register_customizer_elements() {
		// Allow theme to add custom elements via static methods
		do_action( 'vlt_fw_kirki_register', $this->config_id );
	}

	/**
	 * Output dynamic CSS
	 */
	public function output_dynamic_css( $styles ) {
		// Get additional styles from framework filter
		$additional_styles = apply_filters( 'vlt_fw_kirki_dynamic_css', '' );

		// Concatenate additional styles with existing Kirki styles
		if ( !empty( $additional_styles ) ) {
			$styles .= "\n" . $additional_styles;
		}

		return $styles;
	}

	/**
	 * Proxy Kirki::add_config
	 */
	public static function add_config( $args ) {
		if ( class_exists( 'Kirki' ) && is_array( $args ) && !empty( $args ) ) {
			$config_id = self::get_config_id();
			\Kirki::add_config( $config_id, $args );
		}
	}

	/**
	 * Proxy Kirki::add_panel
	 */
	public static function add_panel( $name, $args ) {
		if ( class_exists( 'Kirki' ) && is_string( $name ) && is_array( $args ) && !empty( $args ) ) {
			\Kirki::add_panel( $name, $args );
		}
	}

	/**
	 * Proxy Kirki::add_section
	 */
	public static function add_section( $name, $args ) {
		if ( class_exists( 'Kirki' ) && is_string( $name ) && is_array( $args ) && !empty( $args ) ) {
			\Kirki::add_section( $name, $args );
		}
	}

	/**
	 * Proxy Kirki::add_field and store defaults
	 */
	public static function add_field( $args ) {
		if ( !is_array( $args ) || empty( $args ) ) {
			return;
		}

		if ( class_exists( 'Kirki' ) ) {
			$config_id = self::get_config_id();
			\Kirki::add_field( $config_id, $args );
		}

		if ( isset( $args['settings'], $args['default'] ) ) {
			self::$default_options[ $args['settings'] ] = $args['default'];
		}
	}

	/**
	 * Get option from theme_mod or fallback
	 */
	public static function get_option( $name, $default = null ) {
		if ( null === $name ) {
			return $default;
		}

		$value = get_theme_mod( $name, null );

		if ( null === $value && isset( self::$default_options[ $name ] ) ) {
			$value = self::$default_options[ $name ];
		}

		if ( null === $value ) {
			$value = $default;
		}

		return $value;
	}

	/**
	 * Universal theme option getter with ACF override support
	 */
	public static function get_theme_mod( $key, $use_acf = true, $post_id = null, $acf_name = null ) {
		if ( empty( $key ) ) {
			return null;
		}

		$value = null;

		if ( $use_acf && function_exists( 'get_field' ) ) {
			if ( !is_archive() && !is_search() && !is_404() ) {
				$field_name = $acf_name ?: $key;
				$post_id    = $post_id ?: get_the_ID();

				if ( $post_id ) {
					$acf_value = get_field( $field_name, $post_id );

					if ( false !== $acf_value && null !== $acf_value && '' !== $acf_value ) {
						$value = $acf_value;
					}
				}

				if ( null === $value ) {
					$acf_options_value = get_field( $field_name, 'option' );

					if ( false !== $acf_options_value && null !== $acf_options_value && '' !== $acf_options_value ) {
						$value = $acf_options_value;
					}
				}
			}
		}

		if ( empty( $value ) ) {
			$value = self::get_option( $key );
		}

		return apply_filters( 'vlt_framework/kirki/get_theme_mod', $value, $key, $use_acf, $post_id, $acf_name );
	}

	/**
	 * Generate HSL CSS variables from color
	 */
	public static function get_hsl_variables( $var_name, $color ) {
		if ( empty( $color ) ) {
			return '';
		}

		$hsl = self::hex_to_hsl( $color );

		if ( !$hsl ) {
			return '';
		}

		$css = sprintf(
			'%s: %d, %d%%, %d%%; %s-h: %d; %s-s: %d%%; %s-l: %d%%;',
			$var_name,
			$hsl['h'],
			$hsl['s'],
			$hsl['l'],
			$var_name,
			$hsl['h'],
			$var_name,
			$hsl['s'],
			$var_name,
			$hsl['l']
		);

		return $css;
	}

	/**
	 * Get all stored default options
	 */
	public static function get_default_options() {
		return self::$default_options;
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
	public static function get_setting_choices( $setting_id ) {
		if ( !class_exists( 'Kirki' ) ) {
			return [];
		}

		// Get all registered fields from Kirki
		$fields = \Kirki::$all_fields ?? [];

		// Search for the setting
		if ( isset( $fields[ $setting_id ] ) ) {
			return $fields[ $setting_id ]['choices'] ?? [];
		}

		return [];
	}

	/**
	 * Initialize Kirki configuration
	 */
	private function init_kirki_config() {
		if ( class_exists( 'Kirki' ) ) {
			$config = $this->get_config( 'customizer', [] );

			\Kirki::add_config(
				$this->config_id,
				[
					'capability'  => $config['capability'] ?? 'edit_theme_options',
					'option_type' => $config['option_type'] ?? 'theme_mod'
				]
			);
		}
	}

	/**
	 * Convert HEX to HSL
	 */
	private static function hex_to_hsl( $hex ) {
		$hex = ltrim( $hex, '#' );

		if ( 3 == strlen( $hex ) ) {
			$r = hexdec( str_repeat( substr( $hex, 0, 1 ), 2 ) );
			$g = hexdec( str_repeat( substr( $hex, 1, 1 ), 2 ) );
			$b = hexdec( str_repeat( substr( $hex, 2, 1 ), 2 ) );
		} elseif ( 6 == strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		} else {
			return false;
		}

		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;

		if ( $max == $min ) {
			$h = $s = 0;
		} else {
			$d = $max - $min;
			$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );

			switch ( $max ) {
				case $r:
					$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );

					break;

				case $g:
					$h = ( $b - $r ) / $d + 2;

					break;

				case $b:
					$h = ( $r - $g ) / $d + 4;

					break;
			}

			$h /= 6;
		}

		return [
			'h' => round( $h * 360 ),
			's' => round( $s * 100 ),
			'l' => round( $l * 100 )
		];
	}

	/**
	 * Get Kirki config ID
	 */
	private static function get_config_id() {
		if ( function_exists( 'vlt_framework' ) ) {
			$config = vlt_framework()->get_config( 'customizer', [] );

			return $config['config_id'] ?? 'vlt_customize';
		}

		return 'vlt_customize';
	}
}
