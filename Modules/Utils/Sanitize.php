<?php

namespace VLT\Framework\Modules\Utils;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize Module
 */
class Sanitize extends BaseModule {
	protected $name    = 'sanitize';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register() {
	}

	/**
	 * Sanitize CSS class
	 */
	public static function sanitize_class( $class, $fallback = null ) {
		if ( empty( $class ) ) {
			return !empty( $fallback ) ? sanitize_html_class( $fallback ) : '';
		}

		if ( is_string( $class ) ) {
			$class = explode( ' ', trim( $class ) );
		}

		if ( is_array( $class ) && !empty( $class ) ) {
			$class = array_map( 'sanitize_html_class', $class );
			$class = array_filter( $class );

			return implode( ' ', $class );
		}

		return !empty( $class ) ? sanitize_html_class( $class, $fallback ) : '';
	}

	/**
	 * Sanitize inline style
	 */
	public static function style( $style ) {
		if ( empty( $style ) ) {
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
			'line-height'
		];

		return wp_kses( $style, [], $allowed_css );
	}

	/**
	 * Convert string to boolean
	 */
	public static function string_to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, [ '1', 'true', 'show', 'enable', 'yes', 'on', 'active' ], true );
	}
}
