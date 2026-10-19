<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;
use VLT\Framework\Modules\Core\Controls\TypographyControl;
use VLT\Framework\Modules\Core\Controls\DividerControl;
use VLT\Framework\Modules\Core\Controls\NoticeControl;
use VLT\Framework\Modules\Core\Controls\AlphaColorControl;
use VLT\Framework\Modules\Core\Controls\RepeaterControl;
use VLT\Framework\Modules\Core\Controls\ValueControl;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native WordPress Customizer Integration Module
 *
 * Fields are queued with Customizer::add_field(). Besides the WordPress control args a field takes:
 *
 *     Customizer::add_field( [
 *         'type'       => 'dimension',
 *         'settings'   => 'header_logo_height',
 *         'section'    => 'section_header_general',
 *         'label'      => esc_html__( 'Logo Height', '@@textdomain' ),
 *         'default'    => [ 'desktop' => '18px', 'mobile' => '16px' ],
 *         'units'      => [ 'px', 'rem' ],
 *         'responsive' => true,
 *         'style'      => [ [ 'element' => '.vlt-navbar-logo img', 'property' => 'height' ] ],
 *     ] );
 *
 * - dimension: a CSS value, '18px', '80vh', '300ms' ('units' => [ 'px', 'vh' ], 'functions' => true for clamp() /
 *   min() / max() / calc() / var() or a list of them, e.g. [ 'var' ], 'keywords' => [ 'auto' ]).
 * - number: a number (min, max, step or input_attrs; 'unit' => 'px' shown next to it and added in the CSS,
 *   'units' => [ 'ms', 's' ] for a time kept in ms).
 * - responsive: one value per device of the preview (desktop, tablet, mobile), switched with the device buttons
 *   of the label. Tablet and mobile are saved as "{settings}_tablet" and "{settings}_mobile"; an empty one follows
 *   the wider device. The default is a value (desktop) or [ device => value ].
 * - style: [ element, property, mask ] rules, the CSS of the field (wp_head, live in the preview). Mask defaults
 *   to "$" (the value); a mask without "$" is printed as it is while the field has a value. Tablet and mobile go
 *   into @media (max-width) of Customizer::get_breakpoints(). Fields hidden by their active_callback print none.
 *
 * A field whose value is not the default shows a dot by its label and, on hover, a reset button.
 */
class Customizer extends BaseModule {
	protected $name    = 'customizer';
	protected $version = '1.0.0';

	/**
	 * Devices of the preview, widest first: a device without a value follows the wider one
	 */
	const DEVICES = [ 'desktop', 'tablet', 'mobile' ];

	/**
	 * CSS functions a dimension field can offer
	 */
	const FUNCTIONS = [ 'clamp', 'min', 'max', 'calc', 'var' ];

	/**
	 * CSS lengths a dimension field takes when it names none
	 */
	const UNITS = [ 'px', '%', 'em', 'rem', 'vw', 'vh' ];

	private static $queue            = [
		'panels'   => [],
		'sections' => [],
		'fields'   => []
	];
	private static $default_options  = [];
	private static $registered_choices = [];

	/**
	 * Fields with style rules [ settings => field ]
	 *
	 * @var array
	 */
	private static $styled = [];

	/**
	 * Device fields [ settings => [ 'field' => desktop settings, 'device' => device ] ]
	 *
	 * @var array
	 */
	private static $devices = [];

	/**
	 * Register module
	 */
	public function register() {
		// Register fields on every request (not just customize_register) so
		// get_option()/get_theme_mod() defaults and dynamic CSS work on the frontend too.
		add_action( 'init', [ $this, 'load_theme_customizer' ], 5 );
		add_action( 'init', [ $this, 'register_customizer_elements' ], 6 );
		add_action( 'init', [ $this, 'load_dynamic_css_config' ], 7 );

		add_action( 'customize_register', [ $this, 'apply_queue' ], 30 );

		add_action( 'wp_head', [ $this, 'output_dynamic_css' ], 100 );

		add_action( 'customize_controls_enqueue_scripts', [ $this, 'enqueue_control_assets' ] );
		add_action( 'customize_preview_init', [ $this, 'enqueue_preview_assets' ] );

		add_filter( 'vlt_fw_google_fonts', [ $this, 'collect_typography_google_fonts' ] );
	}

	/**
	 * Add fonts selected via 'typography' fields to the Google Fonts queue
	 * consumed by Modules\Core\Assets::get_google_fonts_url()
	 */
	public function collect_typography_google_fonts( $fonts ) {
		$google_fonts = array_change_key_case( $this->get_google_fonts(), CASE_LOWER );

		$registered_families = array_map(
			function ( $font ) {
				return strtolower( trim( str_replace( '+', ' ', explode( ':', $font, 2 )[0] ) ) );
			},
			$fonts
		);

		foreach ( self::$queue['fields'] as $field ) {
			if ( 'typography' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			$value = json_decode( (string) self::get_option( $field['settings'] ), true );

			if ( empty( $value['family'] ) ) {
				continue;
			}

			// Only queue families that are actually Google Fonts — custom,
			// TypeKit, or theme-registered fonts are not served from
			// fonts.googleapis.com and must not be requested from there.
			if ( !isset( $google_fonts[ strtolower( trim( $value['family'] ) ) ] ) ) {
				continue;
			}

			// Skip if the theme already registered this family (e.g. with a
			// variable-font axis range) to avoid conflicting duplicate
			// family= params in the Google Fonts CSS2 URL.
			if ( in_array( strtolower( trim( $value['family'] ) ), $registered_families, true ) ) {
				continue;
			}

			$variants = !empty( $value['variants'] ) && is_array( $value['variants'] ) ? $value['variants'] : [ 'regular' ];

			$weights = array_map(
				function ( $variant ) {
					if ( 'regular' === $variant ) {
						return 400;
					}

					return (int) preg_replace( '/[^0-9]/', '', $variant ) ?: 400;
				},
				$variants
			);

			$weights = array_unique( $weights );
			sort( $weights );

			$axis = ( count( $weights ) > 1 )
				? 'wght@' . reset( $weights ) . '..' . end( $weights )
				: 'wght@' . reset( $weights );

			$fonts[]               = $value['family'] . ':' . $axis;
			$registered_families[] = strtolower( trim( $value['family'] ) );
		}

		return array_unique( $fonts );
	}

	/**
	 * Enqueue assets for custom controls (typography, divider)
	 */
	public function enqueue_control_assets() {
		wp_enqueue_style(
			'vlt-customizer-controls',
			VLT_FW_URL . 'assets/css/customizer-controls.css',
			[],
			$this->version
		);

		wp_enqueue_script(
			'vlt-customizer-controls',
			VLT_FW_URL . 'assets/js/customizer-controls.js',
			[ 'customize-controls' ],
			$this->version,
			true
		);

		wp_add_inline_script(
			'vlt-customizer-controls',
			'window.vltFwCustomizerFonts = ' . wp_json_encode( $this->get_typography_control_fonts() ) . ';'
				. 'window.vltFwCustomizerIcons = ' . wp_json_encode( $this->get_icons() ) . ';'
				. 'window.vltFwCustomizerDefaults = ' . wp_json_encode( $this->get_field_defaults() ) . ';'
				. 'window.vltFwCustomizerDevices = ' . wp_json_encode( self::$devices ) . ';',
			'before'
		);

		// The tablet preview as wide as the tablet breakpoint starts (WordPress shows 720px, a mobile width here)
		$tablet_width = self::get_breakpoints()['mobile'] + 1;

		wp_add_inline_style(
			'vlt-customizer-controls',
			sprintf( '.wp-full-overlay.preview-tablet .wp-full-overlay-main{width:%1$dpx;margin-left:-%2$dpx}', $tablet_width, $tablet_width / 2 )
		);
	}

	/**
	 * Preview: the CSS of the fields with style rules, rebuilt as they change
	 */
	public function enqueue_preview_assets() {
		wp_enqueue_script(
			'vlt-customizer-preview',
			VLT_FW_URL . 'assets/js/customizer-preview.js',
			[ 'customize-preview' ],
			$this->version,
			true
		);

		$fields = [];

		foreach ( self::$styled as $id => $field ) {
			$fields[ $id ] = [
				'type'       => $field['type'] ?? 'text',
				'unit'       => self::number_units( $field )[0],
				'units'      => self::dimension_units( $field ),
				'keywords'   => self::dimension_keywords( $field ),
				'responsive' => !empty( $field['responsive'] ),
				'style'      => $field['style'],
				'rules'      => is_array( $field['active_callback'] ?? null ) ? array_values( $field['active_callback'] ) : [],
			];
		}

		wp_add_inline_script(
			'vlt-customizer-preview',
			'window.vltFwCustomizerCss = ' . wp_json_encode( [ 'fields' => $fields, 'breakpoints' => self::get_breakpoints() ] ) . ';',
			'before'
		);
	}

	/**
	 * Max widths of the devices below desktop, px
	 *
	 * @return array [ 'tablet' => int, 'mobile' => int ]
	 */
	public static function get_breakpoints() {
		return wp_parse_args(
			array_map( 'absint', (array) apply_filters( 'vlt_fw_customizer_breakpoints', [] ) ),
			[
				'tablet' => 991,
				'mobile' => 767
			]
		);
	}

	/**
	 * Flatten TypographyControl's grouped font list (Google + toolkit fonts)
	 * into family => {family, category, variants, source}, for the JS-side
	 * variants lookup in the typography control.
	 */
	private function get_typography_control_fonts() {
		$fonts = [];

		foreach ( TypographyControl::get_font_groups() as $group ) {
			$fonts = array_merge( $fonts, $group['fonts'] );
		}

		return $fonts;
	}

	/**
	 * Map of settings => default value, for the reset-to-default control button
	 */
	private function get_field_defaults() {
		$defaults = [];

		foreach ( self::$queue['fields'] as $field ) {
			if ( empty( $field['settings'] ) || !array_key_exists( 'default', $field ) ) {
				continue;
			}

			$defaults[ $field['settings'] ] = $field['default'];
		}

		return $defaults;
	}

	/**
	 * Collect 'icon' args from queued panels/section
	 */
	private function get_icons() {
		$icons = [
			'panel'   => [],
			'section' => []
		];

		foreach ( self::$queue['panels'] as $id => $args ) {
			if ( !empty( $args['icon'] ) ) {
				$icons['panel'][ $id ] = $args['icon'];
			}
		}

		foreach ( self::$queue['sections'] as $id => $args ) {
			if ( !empty( $args['icon'] ) ) {
				$icons['section'][ $id ] = $args['icon'];
			}
		}

		return $icons;
	}

	/**
	 * Load the framework's Google Fonts list (family => {family, category, variants})
	 */
	public function get_google_fonts() {
		static $fonts = null;

		if ( null !== $fonts ) {
			return $fonts;
		}

		$fonts = [];

		$file = apply_filters( 'vlt_fw_google_fonts_json', VLT_FW_PATH . 'assets/fonts/google-fonts.json' );

		if ( file_exists( $file ) ) {
			$data = json_decode( file_get_contents( $file ), true );

			if ( !empty( $data['items'] ) && is_array( $data['items'] ) ) {
				$fonts = $data['items'];
			}
		}

		return $fonts;
	}

	/**
	 * Load theme customizer configuration file
	 */
	public function load_theme_customizer() {
		$customizer_file = apply_filters( 'vlt_fw_customizer_file', 'inc/customizer/customizer.php' );

		$theme_customizer = trailingslashit( get_template_directory() ) . $customizer_file;

		if ( file_exists( $theme_customizer ) ) {
			require_once $theme_customizer;
		}
	}

	/**
	 * Register customizer elements (panels, sections, fields)
	 */
	public function register_customizer_elements() {
		// Allow theme to add custom elements via static methods
		do_action( 'vlt_fw_customizer_register' );
	}

	/**
	 * Load dynamic CSS configuration file
	 */
	public function load_dynamic_css_config() {
		$dynamic_css_file  = apply_filters( 'vlt_fw_customizer_dynamic_css_file', 'inc/customizer/customizer-dynamic-css.php' );
		$theme_dynamic_css = trailingslashit( get_template_directory() ) . $dynamic_css_file;

		if ( file_exists( $theme_dynamic_css ) ) {
			require_once $theme_dynamic_css;
		}
	}

	/**
	 * Flush the queued panels/sections/fields into WP_Customize_Manager
	 */
	public function apply_queue( \WP_Customize_Manager $wp_customize ) {
		foreach ( self::$queue['panels'] as $id => $args ) {
			$wp_customize->add_panel( $id, $args );
		}

		foreach ( self::$queue['sections'] as $id => $args ) {
			$wp_customize->add_section( $id, $args );
		}

		foreach ( self::$queue['fields'] as $field ) {
			$this->add_setting_and_control( $wp_customize, $field );
		}
	}

	/**
	 * Output accumulated dynamic CSS (theme hook)
	 */
	public function output_dynamic_css() {
		// The preview gets a style tag per field: customizer-preview.js rewrites it as the field changes
		if ( is_customize_preview() ) {
			foreach ( self::$styled as $id => $field ) {
				echo '<style id="' . esc_attr( 'vlt-customizer-css-' . $id ) . '">' . wp_strip_all_tags( self::get_field_css( $field ) ) . '</style>' . "\n";
			}

			$css = '';
		} else {
			$css = implode( '', array_map( [ __CLASS__, 'get_field_css' ], self::$styled ) );
		}

		// CSS no field rule gives (a theme's own)
		$css .= apply_filters( 'vlt_fw_customizer_dynamic_css', '' );

		if ( empty( $css ) ) {
			return;
		}

		echo '<style id="vlt-customizer-dynamic-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
	}

	/**
	 * CSS of a field's style rules: desktop, then tablet and mobile in their media queries
	 *
	 * @param array $field Queued field
	 *
	 * @return string '' while the field is hidden by its active_callback or has no value
	 */
	public static function get_field_css( $field ) {
		if ( !self::rules_match( $field['active_callback'] ?? null, [ __CLASS__, 'get_option' ] ) ) {
			return '';
		}

		$values      = !empty( $field['responsive'] ) ? self::get_responsive_option( $field['settings'] ) : [ 'desktop' => self::get_option( $field['settings'] ) ];
		$breakpoints = self::get_breakpoints();
		$wider       = '';
		$css         = '';

		foreach ( $values as $device => $value ) {
			$value = self::css_value( $field, $value );

			// Empty or as the wider device: follows it
			if ( '' === $value || $value === $wider ) {
				continue;
			}

			$wider = $value;
			$block = '';

			foreach ( $field['style'] as $rule ) {
				$block .= $rule['element'] . '{' . $rule['property'] . ':' . str_replace( '$', $value, $rule['mask'] ) . ';}';
			}

			$css .= 'desktop' === $device ? $block : sprintf( '@media (max-width:%dpx){%s}', $breakpoints[ $device ], $block );
		}

		return $css;
	}

	/**
	 * Value of a field as CSS
	 *
	 * @param array $field Queued field
	 * @param mixed $value Saved value
	 *
	 * @return string
	 */
	private static function css_value( $field, $value ) {
		if ( !is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		switch ( $field['type'] ?? 'text' ) {
			case 'number':
				return is_numeric( $value ) ? ( $value + 0 ) . self::number_units( $field )[0] : '';

			case 'dimension':
				return self::dimension_value( $field, $value );

			case 'checkbox':
				return rest_sanitize_boolean( $value ) ? '1' : '';

			case 'typography':
				$font = json_decode( $value, true );

				return empty( $font['family'] ) ? '' : '"' . self::strip_css( $font['family'] ) . '"';

			case 'image':
			case 'upload':
				$url = is_numeric( $value ) ? wp_get_attachment_image_url( (int) $value, 'full' ) : $value;

				return $url ? 'url("' . esc_url_raw( $url ) . '")' : '';

			default:
				return self::strip_css( $value );
		}
	}

	/**
	 * A CSS value of a dimension field: number + one of its units (a bare number takes the first), a keyword of
	 * it or, with functions, a CSS function of lengths; '' otherwise
	 *
	 * @param array  $field Queued field
	 * @param string $value Raw
	 *
	 * @return string
	 */
	private static function dimension_value( $field, $value ) {
		$value = strtolower( trim( (string) $value ) );
		$units = self::dimension_units( $field );

		if ( in_array( $value, self::dimension_keywords( $field ), true ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return ( $value + 0 ) . $units[0];
		}

		if ( preg_match( '/^(-?\d*\.?\d+)([a-z%]+)$/', $value, $match ) && in_array( $match[2], $units, true ) ) {
			return $value;
		}

		$functions = self::dimension_functions( $field );

		// Numbers, units, operators, commas and custom property names only
		if ( $functions && preg_match( '/^(' . implode( '|', $functions ) . ')\([a-z0-9%.,+*\/\s()_-]+\)$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Units of a dimension field
	 */
	private static function dimension_units( $field ) {
		$units = array_values( array_filter( array_map( fn( $unit ) => preg_replace( '/[^a-z%]/', '', strtolower( (string) $unit ) ), (array) ( $field['units'] ?? self::UNITS ) ) ) );

		return $units ?: self::UNITS;
	}

	/**
	 * Functions of a dimension field: true for all, or a list
	 */
	private static function dimension_functions( $field ) {
		$functions = $field['functions'] ?? false;

		return is_array( $functions ) ? array_values( array_intersect( self::FUNCTIONS, $functions ) ) : ( $functions ? self::FUNCTIONS : [] );
	}

	/**
	 * Keywords of a dimension field
	 */
	private static function dimension_keywords( $field ) {
		return array_values( array_filter( array_map( 'sanitize_key', (array) ( $field['keywords'] ?? [] ) ) ) );
	}

	/**
	 * Units of a number field: 'units', else 'unit' (none by default)
	 */
	private static function number_units( $field ) {
		$units = array_map( fn( $unit ) => preg_replace( '/[^a-z%]/', '', strtolower( (string) $unit ) ), (array) ( $field['units'] ?? [ $field['unit'] ?? '' ] ) );

		return $units ?: [ '' ];
	}

	/**
	 * No way out of a declaration
	 */
	private static function strip_css( $value ) {
		return trim( (string) preg_replace( '/[;{}<>\\\\]+/', '', (string) $value ) );
	}

	/**
	 * Style rules of a field: [ [ element, property, mask ], … ]
	 *
	 * @param mixed $style Raw rules
	 *
	 * @return array
	 */
	private static function style_rules( $style ) {
		$rules = [];

		foreach ( (array) $style as $rule ) {
			$property = (string) ( $rule['property'] ?? '' );
			$element  = trim( (string) preg_replace( '/[{}<>;@\\\\]+/', '', (string) ( $rule['element'] ?? '' ) ) );

			if ( '' === $element || !preg_match( '/^(?:--[A-Za-z0-9_-]+|[a-z][a-z-]*)$/', $property ) ) {
				continue;
			}

			$rules[] = [
				'element'  => $element,
				'property' => $property,
				'mask'     => self::strip_css( $rule['mask'] ?? '' ) ?: '$',
			];
		}

		return $rules;
	}

	/**
	 * Queue a panel
	 */
	public static function add_panel( $id, $args ) {
		if ( !is_string( $id ) || !is_array( $args ) || empty( $args ) ) {
			return;
		}

		self::$queue['panels'][ $id ] = $args;
	}

	/**
	 * Queue a section
	 */
	public static function add_section( $id, $args ) {
		if ( !is_string( $id ) || !is_array( $args ) || empty( $args ) ) {
			return;
		}

		self::$queue['sections'][ $id ] = $args;
	}

	/**
	 * Queue a field and store its default
	 */
	public static function add_field( $args ) {
		if ( !is_array( $args ) || empty( $args['settings'] ) ) {
			return;
		}

		$args['responsive'] = !empty( $args['responsive'] ) && !in_array( $args['type'] ?? 'text', [ 'divider', 'notice', 'repeater' ], true );

		if ( isset( $args['style'] ) ) {
			$args['style'] = self::style_rules( $args['style'] );

			if ( $args['style'] ) {
				self::$styled[ $args['settings'] ] = $args;
			}
		}

		if ( !$args['responsive'] ) {
			self::queue_field( $args );

			return;
		}

		// A field per device: tablet and mobile follow the wider device until they get a value
		$default = $args['default'] ?? '';
		$default = is_array( $default ) && array_intersect_key( $default, array_flip( self::DEVICES ) ) ? $default : [ 'desktop' => $default ];

		foreach ( self::DEVICES as $device ) {
			$field = array_merge( $args, [ 'default' => $default[ $device ] ?? '' ] );

			if ( 'desktop' !== $device ) {
				$field['settings'] = $args['settings'] . '_' . $device;
			}

			self::$devices[ $field['settings'] ] = [
				'field'  => $args['settings'],
				'device' => $device
			];

			self::queue_field( $field );
		}
	}

	/**
	 * Queue one field and store its default and choices
	 */
	private static function queue_field( $args ) {
		self::$queue['fields'][] = $args;

		if ( isset( $args['default'] ) ) {
			self::$default_options[ $args['settings'] ] = $args['default'];
		}

		if ( isset( $args['choices'] ) && is_array( $args['choices'] ) ) {
			self::$registered_choices[ $args['settings'] ] = $args['choices'];
		}
	}

	/**
	 * Values of a responsive field per device; an empty device follows the wider one
	 *
	 * @param string $name Field settings
	 *
	 * @return array [ device => value ] (DEVICES)
	 */
	public static function get_responsive_option( $name ) {
		$values = [];
		$wider  = '';

		foreach ( self::DEVICES as $device ) {
			$value = self::get_option( 'desktop' === $device ? $name : $name . '_' . $device, '' );
			$value = ( null === $value || '' === $value ) ? $wider : $value;

			$values[ $device ] = $value;
			$wider             = $value;
		}

		return $values;
	}

	/**
	 * Get option from theme_mod or fallback default
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
	 * Get an image option's attachment ID.
	 *
	 * Native WP_Customize_Image_Control stores a URL
	 */
	public static function get_attachment_id_by_option( $name, $default = null ) {
		$value = self::get_option( $name, null );

		if ( empty( $value ) ) {
			return $default;
		}

		// Legacy Kirki-stored value (attachment ID saved directly).
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$id = attachment_url_to_postid( $value );

		return $id ?: $default;
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

		return apply_filters( 'vlt_framework/customizer/get_theme_mod', $value, $key, $use_acf, $post_id, $acf_name );
	}

	/**
	 * Get all stored default options
	 */
	public static function get_default_options() {
		return self::$default_options;
	}

	/**
	 * Get choices array for a specific setting
	 */
	public static function get_setting_choices( $setting_id ) {
		return self::$registered_choices[ $setting_id ] ?? [];
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

		return sprintf(
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
	 * Register the WP_Customize_Setting + control for a queued field
	 */
	private function add_setting_and_control( \WP_Customize_Manager $wp_customize, $args ) {
		$id = $args['settings'];

		if ( 'divider' === ( $args['type'] ?? '' ) ) {
			$control_args = [
				'label'    => $args['label'] ?? '',
				'section'  => $args['section'] ?? '',
				'priority' => $args['priority'] ?? 10,
				'settings' => [],
			];

			if ( isset( $args['active_callback'] ) ) {
				$control_args['active_callback'] = function () use ( $args, $wp_customize ) {
					return $this->evaluate_active_callback( $args['active_callback'], $wp_customize );
				};
			}

			$wp_customize->add_control( new DividerControl( $wp_customize, $id, $control_args ) );

			return;
		}

		if ( 'notice' === ( $args['type'] ?? '' ) ) {
			$control_args = [
				'section'     => $args['section'] ?? '',
				'priority'    => $args['priority'] ?? 10,
				'content'     => $args['default'] ?? '',
				'notice_type' => $args['notice_type'] ?? 'info',
				'settings'    => [],
			];

			if ( isset( $args['active_callback'] ) ) {
				$control_args['active_callback'] = function () use ( $args, $wp_customize ) {
					return $this->evaluate_active_callback( $args['active_callback'], $wp_customize );
				};
			}

			$wp_customize->add_control( new NoticeControl( $wp_customize, $id, $control_args ) );

			return;
		}

		$wp_customize->add_setting(
			$id,
			[
				'default'           => $args['default'] ?? ( 'repeater' === ( $args['type'] ?? '' ) ? [] : '' ),
				'type'              => $args['option_type'] ?? 'theme_mod',
				'capability'        => $args['capability'] ?? 'edit_theme_options',
				'transport'         => $this->get_transport( $args ),
				'sanitize_callback' => $args['sanitize_callback'] ?? $this->guess_sanitize_callback( $args ),
			]
		);

		$control_args = [
			'label'       => $args['label'] ?? '',
			'description' => $args['description'] ?? '',
			'section'     => $args['section'] ?? '',
			'priority'    => $args['priority'] ?? 10,
		];

		if ( !empty( $args['choices'] ) && is_array( $args['choices'] ) ) {
			$control_args['choices'] = $args['choices'];
		}

		if ( !empty( $args['input_attrs'] ) && is_array( $args['input_attrs'] ) ) {
			$control_args['input_attrs'] = $args['input_attrs'];
		}

		// 'ai' => true: the field offers the AI assistant of VLThemes Toolkit (text and textarea only)
		if ( !empty( $args['ai'] ) && in_array( $args['type'] ?? 'text', [ 'text', 'textarea' ], true ) ) {
			$control_args['input_attrs']['data-vlt-ai'] = '';
		}

		if ( isset( $args['active_callback'] ) ) {
			$control_args['active_callback'] = function () use ( $args, $wp_customize ) {
				return $this->evaluate_active_callback( $args['active_callback'], $wp_customize );
			};
		}

		$control = $this->build_control( $wp_customize, $id, $args, $control_args );

		$wp_customize->add_control( $control );
	}

	/**
	 * Build the appropriate WP_Customize_Control
	 */
	private function build_control( \WP_Customize_Manager $wp_customize, $id, $args, $control_args ) {
		$type = $args['type'] ?? 'text';

		switch ( $type ) {
			case 'color':
				if ( !empty( $args['choices']['alpha'] ) ) {
					return new AlphaColorControl( $wp_customize, $id, $control_args );
				}

				return new \WP_Customize_Color_Control( $wp_customize, $id, $control_args );

			case 'image':
				return new \WP_Customize_Image_Control( $wp_customize, $id, $control_args );

			case 'upload':
				return new \WP_Customize_Upload_Control( $wp_customize, $id, $control_args );

			case 'typography':
				return new TypographyControl( $wp_customize, $id, $control_args );

			case 'dimension':
				return new ValueControl(
					$wp_customize,
					$id,
					$control_args + [
						'units'     => self::dimension_units( $args ),
						'functions' => self::dimension_functions( $args ),
						'keywords'  => self::dimension_keywords( $args ),
					]
				);

			case 'number':
				$attrs = $args['input_attrs'] ?? [];

				return new ValueControl(
					$wp_customize,
					$id,
					$control_args + [
						'units'   => self::number_units( $args ),
						'numeric' => true,
						'min'     => isset( $args['min'] ) ? (float) $args['min'] : ( isset( $attrs['min'] ) ? (float) $attrs['min'] : null ),
						'max'     => isset( $args['max'] ) ? (float) $args['max'] : ( isset( $attrs['max'] ) ? (float) $attrs['max'] : null ),
						'step'    => (float) ( $args['step'] ?? $attrs['step'] ?? 1 ) ?: 1,
					]
				);

			case 'repeater':
				$control_args['fields']       = $args['fields'] ?? [];
				$control_args['row_label']    = $args['row_label'] ?? [];
				$control_args['button_label'] = $args['button_label'] ?? '';

				return new RepeaterControl( $wp_customize, $id, $control_args );

			case 'select':
			case 'dropdown-pages':
			case 'radio':
			case 'checkbox':
			case 'textarea':
			case 'text':
			case 'url':
			case 'email':
				$control_args['type'] = $type;

				return new \WP_Customize_Control( $wp_customize, $id, $control_args );

			default:
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'VLT Framework Customizer: unsupported field type "%s" for "%s", falling back to text', $type, $id ) );
				}

				$control_args['type'] = 'text';

				return new \WP_Customize_Control( $wp_customize, $id, $control_args );
		}
	}

	/**
	 * Guess a sanitize callback based on field type when none is provided
	 */
	private function guess_sanitize_callback( $args ) {
		switch ( $args['type'] ?? 'text' ) {
			case 'color':
				if ( !empty( $args['choices']['alpha'] ) ) {
					return [ $this, 'sanitize_alpha_color' ];
				}

				return 'sanitize_hex_color';

			case 'image':
			case 'upload':
			case 'url':
				return 'esc_url_raw';

			case 'checkbox':
				return 'rest_sanitize_boolean';

			case 'select':
			case 'radio':
				return 'sanitize_key';

			case 'number':
				return [ $this, 'sanitize_number' ];

			case 'dimension':
				return function ( $value ) use ( $args ) {
					return '' === trim( (string) $value ) ? '' : self::dimension_value( $args, $value );
				};

			case 'typography':
				return [ $this, 'sanitize_typography' ];

			case 'repeater':
				$fields = $args['fields'] ?? [];

				return function ( $value ) use ( $fields ) {
					return $this->sanitize_repeater( $value, $fields );
				};

			default:
				return 'sanitize_text_field';
		}
	}

	/**
	 * Sanitize a number field value (supports decimals and negative numbers)
	 */
	public function sanitize_number( $value ) {
		// Empty: the default, or for tablet and mobile the wider device
		if ( '' === $value || null === $value ) {
			return '';
		}

		return is_numeric( $value ) ? $value + 0 : 0;
	}

	/**
	 * Sanitize a color value that may include an alpha channel
	 * (rgba(...), hsla(...), or plain hex — as produced by AlphaColorControl)
	 */
	public function sanitize_alpha_color( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a typography value ({"family":..,"variants":[..]} JSON string)
	 */
	public function sanitize_typography( $value ) {
		$decoded = json_decode( (string) $value, true );

		if ( !is_array( $decoded ) ) {
			return wp_json_encode( [ 'family' => '', 'variants' => [] ] );
		}

		$family   = isset( $decoded['family'] ) ? sanitize_text_field( $decoded['family'] ) : '';
		$variants = isset( $decoded['variants'] ) && is_array( $decoded['variants'] )
			? array_map( 'sanitize_text_field', $decoded['variants'] )
			: [];

		return wp_json_encode( [ 'family' => $family, 'variants' => $variants ] );
	}

	/**
	 * Sanitize a repeater value: keep only declared sub-fields and
	 * sanitize each one by its type
	 */
	public function sanitize_repeater( $value, $fields ) {
		$rows = [];

		foreach ( RepeaterControl::normalize_value( $value ) as $row ) {
			$clean = [];

			foreach ( $fields as $key => $field ) {
				$raw = $row[ $key ] ?? '';

				if ( !is_scalar( $raw ) ) {
					$raw = '';
				}

				switch ( $field['type'] ?? 'text' ) {
					case 'image':
						$clean[ $key ] = $raw ? absint( $raw ) : '';

						break;

					case 'url':
					case 'link':
						$clean[ $key ] = esc_url_raw( $raw );

						break;

					case 'textarea':
						$clean[ $key ] = sanitize_textarea_field( $raw );

						break;

					case 'select':
						$clean[ $key ] = isset( $field['choices'][ $raw ] ) ? (string) $raw : '';

						break;

					default:
						$clean[ $key ] = sanitize_text_field( $raw );
				}
			}

			$rows[] = $clean;
		}

		return $rows;
	}

	/**
	 * Evaluate active_callback
	 */
	private function evaluate_active_callback( $rules, \WP_Customize_Manager $wp_customize ) {
		return self::rules_match(
			$rules,
			function ( $id ) use ( $wp_customize ) {
				$setting = $wp_customize->get_setting( $id );

				return $setting ? $setting->value() : null;
			}
		);
	}

	/**
	 * Transport of a field: 'auto' (the default) is postMessage for a field with style rules
	 * (customizer-preview.js rewrites its CSS), refresh otherwise and for a font, which loads with the page
	 */
	private function get_transport( $args ) {
		$transport = $args['transport'] ?? 'auto';

		if ( 'auto' !== $transport ) {
			return $transport;
		}

		return !empty( $args['style'] ) && 'typography' !== ( $args['type'] ?? '' ) ? 'postMessage' : 'refresh';
	}

	/**
	 * Whether all active_callback rules match
	 *
	 * @param mixed    $rules [ [ setting, operator, value ], … ]
	 * @param callable $get   Value of a setting by its id
	 *
	 * @return bool
	 */
	private static function rules_match( $rules, callable $get ) {
		if ( !is_array( $rules ) ) {
			return true;
		}

		foreach ( $rules as $rule ) {
			$value    = $get( $rule['setting'] ?? '' );
			$operator = $rule['operator'] ?? '==';
			$compare  = $rule['value'] ?? null;

			$result = match ( $operator ) {
				'!=' => $value != $compare,
				'>' => $value > $compare,
				'>=' => $value >= $compare,
				'<' => $value < $compare,
				'<=' => $value <= $compare,
				'contains' => is_array( $value ) ? in_array( $compare, $value, true ) : str_contains( (string) $value, (string) $compare ),
				default => $value == $compare,
			};

			if ( !$result ) {
				return false;
			}
		}

		return true;
	}
}
