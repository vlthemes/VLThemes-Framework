<?php

namespace VLT\Framework\Modules\Utils;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Sanitize Module
 *
 * Provides sanitization methods for various data types
 * Wrappers for WordPress sanitization functions with additional utilities
 */
class Sanitize extends BaseModule
{

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'sanitize';

	/**
	 * Module version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		// No functions to register - using global functions.php
	}

	/**
	 * Sanitize CSS class
	 *
	 * Supports strings, arrays, and fallback values
	 *
	 * @param string|array $class    CSS class or classes (space-separated or array).
	 * @param string|null  $fallback Fallback class if $class is empty.
	 * @return string Sanitized class names.
	 */
	public static function sanitize_class($class, $fallback = null)
	{
		if (empty($class)) {
			return !empty($fallback) ? sanitize_html_class($fallback) : '';
		}

		// Convert string to array
		if (is_string($class)) {
			$class = explode(' ', trim($class));
		}

		// Sanitize array of classes
		if (is_array($class) && !empty($class)) {
			$class = array_map('sanitize_html_class', $class);
			$class = array_filter($class); // Remove empty values
			return implode(' ', $class);
		}

		// Fallback for single value
		return !empty($class) ? sanitize_html_class($class, $fallback) : '';
	}

	/**
	 * Sanitize inline style
	 *
	 * Sanitizes inline CSS styles, allowing only safe properties
	 *
	 * @param string $style Style string to sanitize.
	 * @return string Sanitized style.
	 */
	public static function style($style)
	{
		if (empty($style)) {
			return '';
		}

		$allowed_css = [
			'color',
			'background',
			'background-color',
			'background-image',
			'font-size',
			'font-weight',
			'font-family',
			'width',
			'height',
			'max-width',
			'max-height',
			'min-width',
			'min-height',
			'position',
			'top',
			'left',
			'right',
			'bottom',
			'margin',
			'margin-top',
			'margin-right',
			'margin-bottom',
			'margin-left',
			'padding',
			'padding-top',
			'padding-right',
			'padding-bottom',
			'padding-left',
			'border',
			'border-radius',
			'border-width',
			'border-color',
			'border-style',
			'display',
			'opacity',
			'z-index',
			'text-align',
			'line-height',
		];

		return wp_kses($style, [], $allowed_css);
	}

	/**
	 * Convert string to boolean
	 *
	 * Converts various string representations to boolean values
	 *
	 * @param mixed $value Value to convert.
	 * @return bool Boolean value.
	 */
	public static function string_to_bool($value)
	{
		if (is_bool($value)) {
			return $value;
		}

		$value = strtolower(trim((string) $value));

		return in_array($value, ['1', 'true', 'show', 'enable', 'yes', 'on', 'active'], true);
	}
}
