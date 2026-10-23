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
 *         'type'     => 'dimension',
 *         'settings' => 'header_logo_height',
 *         'section'  => 'section_header_general',
 *         'label'    => esc_html__( 'Logo Height', '@@textdomain' ),
 *         'default'  => '18px',
 *         'units'    => [ 'px', 'vh' ],
 *         'output'   => [
 *             [ 'element' => '.vlt-navbar-logo img', 'property' => 'height' ],
 *             [ 'element' => '.vlt-navbar-logo img', 'property' => 'height', 'value_pattern' => 'calc($ * .8)', 'media_query' => '@media (max-width: 575px)' ],
 *         ],
 *     ] );
 *
 * - dimension: a CSS value, '18px', '80vh', '300ms' ('units' => [ 'px', 'vh' ], 'functions' => true for clamp() /
 *   min() / max() / calc() / var() or a list of them, e.g. [ 'var' ], 'keywords' => [ 'auto' ]).
 * - number: a number (min, max, step or input_attrs; 'unit' => 'px' shown next to it and added in the CSS,
 *   'units' => [ 'ms', 's' ] for a time kept in ms).
 * - image: a URL, or with 'save_as' => 'id' the attachment ID (the media control; its CSS and the preview take its
 *   full size URL).
 * - typography: a font, {"family":…,"variants":[…]} ('properties' => true for all of Customizer::TYPOGRAPHY
 *   too, or a list of them, e.g. [ 'font-size', 'font-weight' ]; a length without a unit is px, line height none;
 *   'family' => false for no font select, e.g. a field of sizes for phones; 'icon' => 'dashicons-tablet' before
 *   the label). Its default can be an array:
 *   [ 'family' => 'Mulish', 'font-size' => '16px' ].
 * - output: the CSS of the field, rules of
 *   - element, property: the selector and the property (a custom property too: '--vlt-primary-font'); a typography
 *     field without a property prints all of its own (font-family, font-size, …);
 *   - value_pattern: '$' is the value (the default); a pattern without '$' is printed as it is while the field
 *     has a value, e.g. 'cover' for background-size next to an image;
 *   - media_query: the rule inside it, e.g. '@media (max-width: 575px)';
 *   - choice: a key of the value, e.g. 'font-family' of a typography field;
 *   - context: [ 'front' ] (the default), 'editor' for the block editor too.
 *   Printed in wp_head (and the block editor), live in the Customizer preview. Fields hidden by their
 *   active_callback print none.
 *
 * A field whose value is not the default shows a dot by its label and, on hover, a reset button.
 *
 * 'override' => true: the field shows in the Page Settings box too (Overrides), where a page can set its own value.
 * Its value on that page (get_option(), get_theme_mod() and its CSS) is then the page's.
 */
class Customizer extends BaseModule {
	protected $name    = 'customizer';
	protected $version = '1.0.0';

	/**
	 * CSS functions a dimension field can offer
	 */
	const FUNCTIONS = [ 'clamp', 'min', 'max', 'calc', 'var' ];

	/**
	 * CSS lengths a dimension field takes when it names none
	 */
	const UNITS = [ 'px', '%', 'em', 'rem', 'vw', 'vh' ];

	/**
	 * Typography properties: CSS lengths first, then the ones with choices (here, not in TypographyControl:
	 * the front end has no WP_Customize_Control to load it on)
	 */
	const TYPOGRAPHY = [ 'font-size', 'line-height', 'letter-spacing', 'word-spacing', 'font-weight', 'font-style', 'text-transform', 'text-decoration' ];

	/**
	 * Values of the typography properties with choices: value => label (the control's toggles are some of them)
	 */
	const TYPOGRAPHY_CHOICES = [
		'font-weight'     => [ '100' => '100 Thin', '200' => '200 Extra Light', '300' => '300 Light', '400' => '400 Regular', '500' => '500 Medium', '600' => '600 Semi Bold', '700' => '700 Bold', '800' => '800 Extra Bold', '900' => '900 Black', 'normal' => 'Normal', 'bold' => 'Bold' ],
		'font-style'      => [ 'normal' => 'Normal', 'italic' => 'Italic', 'oblique' => 'Oblique' ],
		'text-transform'  => [ 'uppercase' => 'Uppercase', 'lowercase' => 'Lowercase', 'capitalize' => 'Capitalize', 'none' => 'None' ],
		'text-decoration' => [ 'underline' => 'Underline', 'overline' => 'Overline', 'line-through' => 'Line Through', 'none' => 'None' ],
	];

	private static $queue            = [
		'panels'   => [],
		'sections' => [],
		'fields'   => []
	];
	private static $default_options  = [];

	/**
	 * Fields a page can override [ settings => field ]
	 *
	 * @var array
	 */
	private static $overridable = [];
	private static $registered_choices = [];

	/**
	 * Fields with output rules [ settings => field ]
	 *
	 * @var array
	 */
	private static $outputs = [];

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
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_css' ] );

		add_filter( 'vlt_fw_google_fonts', [ $this, 'collect_typography_google_fonts' ] );

		( new Overrides( $this ) )->register();
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

		// Weights of each family [ lowercase family => [ family, weights ] ]: fields of one family share one request
		$families = [];

		foreach ( self::$queue['fields'] as $field ) {
			if ( 'typography' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			$value = json_decode( self::typography_saved( $field ), true );

			if ( empty( $value['family'] ) ) {
				continue;
			}

			$key = strtolower( trim( $value['family'] ) );

			// Only queue families that are actually Google Fonts — custom,
			// TypeKit, or theme-registered fonts are not served from
			// fonts.googleapis.com and must not be requested from there.
			if ( !isset( $google_fonts[ $key ] ) ) {
				continue;
			}

			// Skip if the theme already registered this family (e.g. with a
			// variable-font axis range) to avoid conflicting duplicate
			// family= params in the Google Fonts CSS2 URL.
			if ( in_array( $key, $registered_families, true ) ) {
				continue;
			}

			// The variants, else the weight of a field with properties
			$variants = !empty( $value['variants'] ) && is_array( $value['variants'] ) ? $value['variants'] : [ (string) ( $value['font-weight'] ?? 'regular' ) ];

			$weights = array_map(
				function ( $variant ) {
					if ( 'regular' === $variant ) {
						return 400;
					}

					if ( 'bold' === $variant ) {
						return 700;
					}

					return (int) preg_replace( '/[^0-9]/', '', $variant ) ?: 400;
				},
				$variants
			);

			$families[ $key ] = [
				'family'  => $families[ $key ]['family'] ?? $value['family'],
				'weights' => array_merge( $families[ $key ]['weights'] ?? [], $weights ),
			];
		}

		foreach ( $families as $font ) {
			$weights = array_unique( $font['weights'] );
			sort( $weights );

			$axis = ( count( $weights ) > 1 )
				? 'wght@' . reset( $weights ) . '..' . end( $weights )
				: 'wght@' . reset( $weights );

			$fonts[] = $font['family'] . ':' . $axis;
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
				. 'window.vltFwCustomizerDefaults = ' . wp_json_encode( $this->get_field_defaults() ) . ';',
			'before'
		);

	}

	/**
	 * Preview: the CSS of the fields with output rules, rebuilt as they change
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

		foreach ( self::$outputs as $id => $field ) {
			$rules = array_values( array_filter( $field['output'], fn( $rule ) => in_array( 'front', $rule['context'], true ) ) );

			if ( $rules ) {
				$fields[ $id ] = [
					'type'     => $field['type'] ?? 'text',
					'unit'     => self::number_units( $field )[0],
					'units'    => self::dimension_units( $field ),
					'keywords' => self::dimension_keywords( $field ),
					'output'   => $rules,
					'rules'    => is_array( $field['active_callback'] ?? null ) ? array_values( $field['active_callback'] ) : [],
					'family'   => 'typography' === ( $field['type'] ?? '' ) ? self::typography_family( $field ) : '',
				];
			}
		}

		wp_add_inline_script(
			'vlt-customizer-preview',
			'window.vltFwCustomizerCss = ' . wp_json_encode( [ 'fields' => $fields, 'media' => esc_url_raw( rest_url( 'wp/v2/media/' ) ) ] ) . ';',
			'before'
		);
	}

	/**
	 * Block editor: the output rules with the 'editor' context
	 */
	public function enqueue_editor_css() {
		if ( !is_admin() ) {
			return;
		}

		$css = self::get_css( 'editor' );

		if ( '' === $css ) {
			return;
		}

		wp_register_style( 'vlt-customizer-editor', false, [], $this->version );
		wp_enqueue_style( 'vlt-customizer-editor' );
		wp_add_inline_style( 'vlt-customizer-editor', wp_strip_all_tags( $css ) );
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
			// A field this page overrides keeps the page's CSS: the Customizer's value does not show here
			foreach ( self::$outputs as $id => $field ) {
				$override = Overrides::has( $id ) ? ' data-vlt-override' : '';

				echo '<style id="' . esc_attr( 'vlt-customizer-css-' . $id ) . '"' . $override . '>' . wp_strip_all_tags( self::get_field_css( $field ) ) . '</style>' . "\n";
			}

			$css = '';
		} else {
			$css = self::get_css();
		}

		// CSS no field rule gives (a theme's own)
		$css .= apply_filters( 'vlt_fw_customizer_dynamic_css', '' );

		if ( empty( $css ) ) {
			return;
		}

		echo '<style id="vlt-customizer-dynamic-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
	}

	/**
	 * CSS of all fields' output rules of a context, the rules of one media query together
	 *
	 * @param string $context front or editor
	 *
	 * @return string
	 */
	public static function get_css( $context = 'front' ) {
		$blocks = [];

		foreach ( self::$outputs as $field ) {
			foreach ( self::field_blocks( $field, $context ) as $media => $css ) {
				$blocks[ $media ] = ( $blocks[ $media ] ?? '' ) . $css;
			}
		}

		return self::join_blocks( $blocks );
	}

	/**
	 * CSS of a field's output rules
	 *
	 * @param array  $field   Queued field
	 * @param string $context front or editor
	 *
	 * @return string '' while the field is hidden by its active_callback or has no value
	 */
	public static function get_field_css( $field, $context = 'front' ) {
		return self::join_blocks( self::field_blocks( $field, $context ) );
	}

	/**
	 * Declarations of a field by media query
	 *
	 * @param array  $field   Queued field
	 * @param string $context front or editor
	 *
	 * @return array [ media query ('' = none) => css ]
	 */
	private static function field_blocks( $field, $context ) {
		if ( !self::rules_match( $field['active_callback'] ?? null, [ __CLASS__, 'get_option' ] ) ) {
			return [];
		}

		$saved  = 'typography' === ( $field['type'] ?? '' ) ? self::typography_saved( $field ) : self::get_option( $field['settings'] );
		$blocks = [];

		foreach ( $field['output'] as $rule ) {
			if ( !in_array( $context, $rule['context'], true ) ) {
				continue;
			}

			// No property: all the typography of the field
			if ( '' === $rule['property'] ) {
				$css = 'typography' === ( $field['type'] ?? '' ) ? self::typography_css( $saved ) : '';

				if ( '' !== $css ) {
					$blocks[ $rule['media_query'] ] = ( $blocks[ $rule['media_query'] ] ?? '' ) . $rule['element'] . '{' . $css . '}';
				}

				continue;
			}

			$value = self::css_value( $field, $saved, $rule['choice'] );

			if ( '' === $value ) {
				continue;
			}

			$blocks[ $rule['media_query'] ] = ( $blocks[ $rule['media_query'] ] ?? '' ) . $rule['element'] . '{' . $rule['property'] . ':' . str_replace( '$', $value, $rule['value_pattern'] ) . ';}';
		}

		return $blocks;
	}

	/**
	 * Blocks by media query as CSS: the plain rules first
	 *
	 * @param array $blocks [ media query => css ]
	 *
	 * @return string
	 */
	private static function join_blocks( array $blocks ) {
		$css = $blocks[''] ?? '';

		unset( $blocks[''] );

		foreach ( $blocks as $media => $block ) {
			$css .= $media . '{' . $block . '}';
		}

		return $css;
	}

	/**
	 * Value of a field as CSS
	 *
	 * @param array  $field  Queued field
	 * @param mixed  $value  Saved value
	 * @param string $choice Key of an array or JSON value ('' = the value itself)
	 *
	 * @return string
	 */
	private static function css_value( $field, $value, $choice = '' ) {
		// A key of the value: font-family of a typography field ({"family":…}), or of any array value
		if ( '' !== $choice ) {
			$data  = is_array( $value ) ? $value : json_decode( (string) $value, true );
			$value = is_array( $data ) ? ( $data[ $choice ] ?? ( 'font-family' === $choice ? $data['family'] ?? '' : '' ) ) : '';

			return '' === trim( (string) $value ) || !is_scalar( $value ) ? '' : ( 'font-family' === $choice ? '"' . self::strip_css( $value ) . '"' : self::strip_css( $value ) );
		}

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
	 * Declarations of a typography value: font-family and each property set
	 *
	 * @param mixed $value Saved value (JSON)
	 *
	 * @return string
	 */
	private static function typography_css( $value ) {
		$data = is_array( $value ) ? $value : json_decode( (string) $value, true );

		if ( !is_array( $data ) ) {
			return '';
		}

		$css = empty( $data['family'] ) ? '' : 'font-family:"' . self::strip_css( $data['family'] ) . '";';

		foreach ( self::TYPOGRAPHY as $property ) {
			$part = self::typography_value( $property, $data[ $property ] ?? '' );

			if ( '' !== $part ) {
				$css .= $property . ':' . $part . ';';
			}
		}

		return $css;
	}

	/**
	 * A value of a typography property: one of its choices, or a length (px without a unit, none for line height)
	 *
	 * @param string $property One of self::TYPOGRAPHY
	 * @param mixed  $value    Raw
	 *
	 * @return string
	 */
	private static function typography_value( $property, $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( isset( self::TYPOGRAPHY_CHOICES[ $property ] ) ) {
			return isset( self::TYPOGRAPHY_CHOICES[ $property ][ $value ] ) ? $value : '';
		}

		if ( 'line-height' === $property && is_numeric( $value ) ) {
			return (string) ( $value + 0 );
		}

		return self::dimension_value( [ 'units' => [ 'px', 'em', 'rem', '%', 'vw', 'vh' ], 'functions' => true, 'keywords' => [ 'normal' ] ], $value );
	}

	/**
	 * The font of a typography field's default ('' for none)
	 */
	private static function typography_family( $field ) {
		$default = json_decode( (string) ( $field['default'] ?? '' ), true );

		return is_array( $default ) ? (string) ( $default['family'] ?? '' ) : '';
	}

	/**
	 * A typography field's value (JSON) with no empty font: one saved without a font (there is no "Default" to
	 * pick) takes the font of the field's default
	 *
	 * @param array $field Queued field
	 *
	 * @return string
	 */
	private static function typography_saved( $field ) {
		$saved  = self::get_option( $field['settings'] );
		$data   = is_array( $saved ) ? $saved : json_decode( (string) $saved, true );
		$family = self::typography_family( $field );

		if ( !is_array( $data ) || !empty( $data['family'] ) || '' === $family ) {
			return is_array( $saved ) ? wp_json_encode( $saved ) : (string) $saved;
		}

		$data['family'] = $family;

		return wp_json_encode( $data );
	}

	/**
	 * A typography default (array or JSON) as JSON: family, variants, then the properties in their order
	 *
	 * @param mixed $value Raw default
	 *
	 * @return string
	 */
	private static function typography_default( $value ) {
		$data    = is_array( $value ) ? $value : json_decode( (string) $value, true );
		$data    = is_array( $data ) ? $data : [];
		$default = [
			'family'   => (string) ( $data['family'] ?? '' ),
			'variants' => array_values( (array) ( $data['variants'] ?? [] ) ),
		];

		foreach ( self::TYPOGRAPHY as $property ) {
			if ( isset( $data[ $property ] ) && '' !== (string) $data[ $property ] ) {
				$default[ $property ] = (string) $data[ $property ];
			}
		}

		return wp_json_encode( $default );
	}

	/**
	 * Properties of a typography field: 'properties' => true for all, or a list (none by default)
	 */
	private static function typography_properties( $field ) {
		$properties = $field['properties'] ?? [];

		return true === $properties ? self::TYPOGRAPHY : array_values( array_intersect( self::TYPOGRAPHY, (array) $properties ) );
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
	 * Output rules of a field: [ [ element, property, value_pattern, media_query, choice, context ], … ]
	 *
	 * @param mixed $output Raw rules
	 *
	 * @return array
	 */
	private static function output_rules( $output ) {
		$rules = [];

		foreach ( (array) $output as $rule ) {
			$property = (string) ( $rule['property'] ?? '' );
			$element  = trim( (string) preg_replace( '/[{}<>;@\\\\]+/', '', implode( ',', (array) ( $rule['element'] ?? '' ) ) ) );
			$media    = trim( (string) preg_replace( '/[{};<>\\\\]+/', '', (string) ( $rule['media_query'] ?? '' ) ) );
			$context  = array_values( array_intersect( [ 'front', 'editor' ], (array) ( $rule['context'] ?? [ 'front' ] ) ) );

			// A media query is an at-rule (@media, @supports, @container): a rule with another one is left out
			// No property: a typography field prints all of its own
			if ( '' === $element || ( '' !== $property && !preg_match( '/^(?:--[A-Za-z0-9_-]+|[a-z][a-z-]*)$/', $property ) ) || ( '' !== $media && !preg_match( '/^@(media|supports|container)\b/', $media ) ) ) {
				continue;
			}

			$rules[] = [
				'element'       => $element,
				'property'      => $property,
				'value_pattern' => self::strip_css( $rule['value_pattern'] ?? '' ) ?: '$',
				'media_query'   => $media,
				'choice'        => sanitize_key( (string) ( $rule['choice'] ?? '' ) ),
				'context'       => $context ?: [ 'front' ],
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

		// A typography default as the control writes its value, so the changed dot compares like with like
		if ( 'typography' === ( $args['type'] ?? '' ) && isset( $args['default'] ) ) {
			$args['default'] = self::typography_default( $args['default'] );
		}

		if ( isset( $args['output'] ) ) {
			$args['output'] = self::output_rules( $args['output'] );

			if ( $args['output'] ) {
				self::$outputs[ $args['settings'] ] = $args;
			}
		}

		self::queue_field( $args );
	}

	/**
	 * Queue one field and store its default and choices
	 */
	private static function queue_field( $args ) {
		self::$queue['fields'][] = $args;

		if ( !empty( $args['override'] ) && in_array( $args['type'] ?? 'text', Overrides::TYPES, true ) ) {
			self::$overridable[ $args['settings'] ] = $args;
		}

		if ( isset( $args['default'] ) ) {
			self::$default_options[ $args['settings'] ] = $args['default'];
		}

		if ( isset( $args['choices'] ) && is_array( $args['choices'] ) ) {
			self::$registered_choices[ $args['settings'] ] = $args['choices'];
		}
	}

	/**
	 * Get option from theme_mod or fallback default
	 */
	public static function get_option( $name, $default = null ) {
		if ( null === $name ) {
			return $default;
		}

		// The page's own value of an overridable field
		$found = false;
		$value = Overrides::value( $name, $found );

		return $found ? $value : self::get_saved( $name, $default );
	}

	/**
	 * Get option from theme_mod or fallback default, no page override
	 */
	public static function get_saved( $name, $default = null ) {
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
	 * Theme option: the page's override (Page Settings) of an overridable field, else the Customizer value
	 *
	 * @param string   $key           Setting
	 * @param bool     $use_overrides False for the Customizer value even where a page overrides it
	 * @param int|null $post_id       The post whose override counts (default: the page shown)
	 *
	 * @return mixed
	 */
	public static function get_theme_mod( $key, $use_overrides = true, $post_id = null ) {
		if ( empty( $key ) ) {
			return null;
		}

		$found = false;
		$value = $use_overrides ? Overrides::value( $key, $found, $post_id ) : null;

		if ( !$found ) {
			$value = self::get_saved( $key );
		}

		return apply_filters( 'vlt_framework/customizer/get_theme_mod', $value, $key, $use_overrides, $post_id );
	}

	/**
	 * Fields a page can override, in their order [ settings => field ]
	 */
	public static function get_overridable_fields() {
		return self::$overridable;
	}

	/**
	 * A queued section's args ([] for none)
	 */
	public static function get_section( $id ) {
		return self::$queue['sections'][ $id ] ?? [];
	}

	/**
	 * The sanitize callback of a field: its own, else the one of its type
	 */
	public function get_sanitize_callback( $args ) {
		return $args['sanitize_callback'] ?? $this->guess_sanitize_callback( $args );
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
				// 'save_as' => 'id': the attachment ID, else the URL
				if ( 'id' === ( $args['save_as'] ?? '' ) ) {
					return new \WP_Customize_Media_Control( $wp_customize, $id, $control_args + [ 'mime_type' => 'image' ] );
				}

				return new \WP_Customize_Image_Control( $wp_customize, $id, $control_args );

			case 'upload':
				return new \WP_Customize_Upload_Control( $wp_customize, $id, $control_args );

			case 'typography':
				return new TypographyControl( $wp_customize, $id, $control_args + [ 'properties' => self::typography_properties( $args ), 'family' => false !== ( $args['family'] ?? true ), 'icon' => sanitize_html_class( $args['icon'] ?? '' ) ] );

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
				if ( 'id' === ( $args['save_as'] ?? '' ) ) {
					return fn( $value ) => absint( $value ) ?: '';
				}

				return 'esc_url_raw';

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
	 * Sanitize a typography value ({"family":..,"variants":[..],"font-size":..} JSON string)
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

		$clean = [ 'family' => $family, 'variants' => $variants ];

		foreach ( self::TYPOGRAPHY as $property ) {
			$part = self::typography_value( $property, $decoded[ $property ] ?? '' );

			if ( '' !== $part ) {
				$clean[ $property ] = $part;
			}
		}

		return wp_json_encode( $clean );
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
	 * Transport of a field: 'auto' (the default) is postMessage for a field with output rules
	 * (customizer-preview.js rewrites its CSS and loads a Google font picked), refresh otherwise
	 */
	private function get_transport( $args ) {
		$transport = $args['transport'] ?? 'auto';

		if ( 'auto' !== $transport ) {
			return $transport;
		}

		return !empty( $args['output'] ) ? 'postMessage' : 'refresh';
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
