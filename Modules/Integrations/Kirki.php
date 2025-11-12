<?php

namespace VLT\Framework\Modules\Integrations;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Kirki Customizer Integration Module
 *
 * Provides wrapper methods for Kirki customizer framework
 * Handles panels, sections, fields registration and dynamic CSS output
 */
class Kirki extends BaseModule
{

	/**
	 * Module name
	 */
	protected $name = 'kirki';

	/**
	 * Module version
	 */
	protected $version = '1.0.0';

	/**
	 * Kirki config ID
	 */
	private $config_id;

	/**
	 * Stored default values from fields
	 */
	private static $default_options = [];

	/**
	 * Register module
	 */
	public function register()
	{
		// Load editor styles always
		add_action('admin_enqueue_scripts', [$this, 'load_customizer_editor_styles']);

		// Check if Kirki is available
		if (!class_exists('Kirki')) {
			// Load frontend styles only if Kirki is not installed
			add_action('wp_enqueue_scripts', [$this, 'load_customizer_frontend_styles']);
		}

		// Get config from framework
		$config = $this->get_config('customizer', []);
		$this->config_id = $config['config_id'] ?? 'vlt_customize';

		// Initialize Kirki configuration
		$this->init_kirki_config();

		// Load theme customizer configuration
		add_action('init', [$this, 'load_theme_customizer'], 10);

		// Register customizer elements
		add_action('init', [$this, 'register_customizer_elements'], 20);

		// Load dynamic CSS configuration after Kirki is ready
		add_action('init', [$this, 'load_dynamic_css_config'], 30);

		if (!empty($this->config_id)) {
			add_filter('kirki_' . $this->config_id . '_dynamic_css', [$this, 'output_dynamic_css']);
		}
	}


	/**
	 * Initialize module
	 */
	public function init()
	{
		// Nothing to do here - functions already registered in register()
	}


	/**
	 * Initialize Kirki configuration
	 */
	private function init_kirki_config()
	{
		if (class_exists('Kirki')) {
			$config = $this->get_config('customizer', []);

			\Kirki::add_config($this->config_id, [
				'capability' => $config['capability'] ?? 'edit_theme_options',
				'option_type' => $config['option_type'] ?? 'theme_mod',
			]);
		}
	}

	/**
	 * Load customizer editor styles (admin)
	 */
	public function load_customizer_editor_styles()
	{
		$css_file = apply_filters('vlt_framework_kirki_editor_css', 'inc/kirki/css/customizer-editor.css');
		$css_path = LEEDO_THEME_DIR . $css_file;
		$css_url = LEEDO_THEME_URI . $css_file;

		if (file_exists($css_path)) {
			wp_enqueue_style('vlt-customizer-editor', $css_url, [], $this->version);
		}
	}

	/**
	 * Load customizer frontend styles
	 */
	public function load_customizer_frontend_styles()
	{
		$css_file = apply_filters('vlt_framework_kirki_frontend_css', 'inc/kirki/css/customizer-frontend.css');
		$css_path = LEEDO_THEME_DIR . $css_file;
		$css_url = LEEDO_THEME_URI . $css_file;

		if (file_exists($css_path)) {
			wp_enqueue_style('vlt-customizer-frontend', $css_url, [], $this->version);
		}
	}

	/**
	 * Load theme customizer configuration file
	 */
	public function load_theme_customizer()
	{
		// Allow theme to specify customizer file path
		$customizer_file = apply_filters('vlt_framework_kirki_customizer_file', 'inc/kirki/customizer.php');

		// Check if file exists in theme
		$theme_customizer = LEEDO_THEME_DIR . $customizer_file;

		if (file_exists($theme_customizer)) {
			require_once $theme_customizer;
		}
	}

	/**
	 * Load dynamic CSS configuration file
	 */
	public function load_dynamic_css_config()
	{
		// Load dynamic CSS file
		$dynamic_css_file = apply_filters('vlt_framework_kirki_dynamic_css_file', 'inc/kirki/customizer-dynamic-css.php');
		$theme_dynamic_css = LEEDO_THEME_DIR . $dynamic_css_file;

		if (file_exists($theme_dynamic_css)) {
			require_once $theme_dynamic_css;
		}
	}

	/**
	 * Register customizer elements (panels, sections, fields)
	 */
	public function register_customizer_elements()
	{
		// Allow theme to add custom elements via static methods
		do_action('vlt_framework_kirki_register', $this->config_id);
	}

	/**
	 * Output dynamic CSS
	 */
	public function output_dynamic_css($styles)
	{
		// Get additional styles from framework filter
		$additional_styles = apply_filters('vlt_framework_kirki_dynamic_css', '');

		// Concatenate additional styles with existing Kirki styles
		if (!empty($additional_styles)) {
			$styles .= "\n" . $additional_styles;
		}

		return $styles;
	}

	/**
	 * Proxy Kirki::add_config
	 *
	 * @param array $args Config arguments
	 */
	public static function add_config($args)
	{
		if (class_exists('Kirki') && is_array($args) && !empty($args)) {
			$config_id = self::get_config_id();
			\Kirki::add_config($config_id, $args);
		}
	}

	/**
	 * Proxy Kirki::add_panel
	 *
	 * @param string $name Panel ID
	 * @param array $args Panel arguments
	 */
	public static function add_panel($name, $args)
	{
		if (class_exists('Kirki') && is_string($name) && is_array($args) && !empty($args)) {
			\Kirki::add_panel($name, $args);
		}
	}

	/**
	 * Proxy Kirki::add_section
	 *
	 * @param string $name Section ID
	 * @param array $args Section arguments
	 */
	public static function add_section($name, $args)
	{
		if (class_exists('Kirki') && is_string($name) && is_array($args) && !empty($args)) {
			\Kirki::add_section($name, $args);
		}
	}

	/**
	 * Proxy Kirki::add_field and store defaults locally
	 *
	 * @param array $args Field arguments
	 */
	public static function add_field($args)
	{
		if (!is_array($args) || empty($args)) {
			return;
		}

		if (class_exists('Kirki')) {
			$config_id = self::get_config_id();
			\Kirki::add_field($config_id, $args);
		}

		// Store default value for later retrieval
		if (isset($args['settings'], $args['default'])) {
			self::$default_options[$args['settings']] = $args['default'];
		}
	}

	/**
	 * Get option from theme_mod or fallback to stored Kirki defaults or provided default
	 *
	 * @param string $name Option name
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public static function get_option($name, $default = null)
	{
		if ($name === null) {
			return $default;
		}

		// Try theme_mod first
		$value = get_theme_mod($name, null);

		// Fallback to stored default from field registration
		if ($value === null && isset(self::$default_options[$name])) {
			$value = self::$default_options[$name];
		}

		// Fallback to provided default
		if ($value === null) {
			$value = $default;
		}

		return $value;
	}

	/**
	 * Universal theme option getter with ACF override support
	 *
	 * Order: ACF (post/options page) → theme_mod → stored field default → null
	 * Archives/search/404 pages always return global values.
	 *
	 * @param string      $key      Setting key
	 * @param bool        $use_acf  Try ACF first? (default: true)
	 * @param int|null    $postID   Post ID for ACF (default: current post)
	 * @param string|null $acf_name ACF field name if different from $key
	 * @return mixed Theme mod value or null if not found
	 */
	public static function get_theme_mod($key, $use_acf = true, $postID = null, $acf_name = null)
	{
		if (empty($key)) {
			return null;
		}

		$value = null;

		// 1. Try ACF (post-specific or options page)
		if ($use_acf && function_exists('get_field')) {
			// Skip ACF on archives/search/404
			if (!is_archive() && !is_search() && !is_404()) {
				$field_name = $acf_name ?: $key;
				$postID = $postID ?: get_the_ID();

				// Try post-specific ACF field
				if ($postID) {
					$acf_value = get_field($field_name, $postID);
					if ($acf_value !== false && $acf_value !== null && $acf_value !== '') {
						$value = $acf_value;
					}
				}

				// Try ACF options page if no post value
				if ($value === null) {
					$acf_options_value = get_field($field_name, 'option');
					if ($acf_options_value !== false && $acf_options_value !== null && $acf_options_value !== '') {
						$value = $acf_options_value;
					}
				}
			}
		}

		// 2. If no ACF value, use get_option() for theme_mod → stored default → null
		if (empty($value)) {
			$value = self::get_option($key);
		}

		return apply_filters('vlt_framework/kirki/get_theme_mod', $value, $key, $use_acf, $postID, $acf_name);
	}

	/**
	 * Generate HSL CSS variables from color
	 *
	 * @param string $var_name CSS variable name
	 * @param string $color Color value (hex, rgb, hsl)
	 * @return string CSS variables
	 */
	public static function get_hsl_variables($var_name, $color)
	{
		if (empty($color)) {
			return '';
		}

		// Convert color to HSL
		$hsl = self::hex_to_hsl($color);

		if (!$hsl) {
			return '';
		}

		// Generate CSS variables
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
	 * Convert HEX to HSL
	 *
	 * @param string $hex Hex color
	 * @return array|false HSL values or false
	 */
	private static function hex_to_hsl($hex)
	{
		// Remove # if present
		$hex = ltrim($hex, '#');

		// Convert hex to RGB
		if (strlen($hex) == 3) {
			$r = hexdec(str_repeat(substr($hex, 0, 1), 2));
			$g = hexdec(str_repeat(substr($hex, 1, 1), 2));
			$b = hexdec(str_repeat(substr($hex, 2, 1), 2));
		} elseif (strlen($hex) == 6) {
			$r = hexdec(substr($hex, 0, 2));
			$g = hexdec(substr($hex, 2, 2));
			$b = hexdec(substr($hex, 4, 2));
		} else {
			return false;
		}

		// Convert RGB to HSL
		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$l = ($max + $min) / 2;

		if ($max == $min) {
			$h = $s = 0;
		} else {
			$d = $max - $min;
			$s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

			switch ($max) {
				case $r:
					$h = ($g - $b) / $d + ($g < $b ? 6 : 0);
					break;
				case $g:
					$h = ($b - $r) / $d + 2;
					break;
				case $b:
					$h = ($r - $g) / $d + 4;
					break;
			}

			$h /= 6;
		}

		return [
			'h' => round($h * 360),
			's' => round($s * 100),
			'l' => round($l * 100),
		];
	}

	/**
	 * Get Kirki config ID
	 *
	 * @return string
	 */
	private static function get_config_id()
	{
		// Try to get from framework config
		if (function_exists('vlt_framework')) {
			$config = vlt_framework()->get_config('customizer', []);
			return $config['config_id'] ?? 'vlt_customize';
		}

		return 'vlt_customize';
	}

	/**
	 * Get all stored default options (for debugging)
	 *
	 * @return array
	 */
	public static function get_default_options()
	{
		return self::$default_options;
	}
}
