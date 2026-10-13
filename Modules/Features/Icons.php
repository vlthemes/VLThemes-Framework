<?php

namespace VLT\Framework\Modules\Features;

use VLT\Framework\BaseModule;
use VLT\Framework\Modules\Utils\Sanitize;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icons Module: an SVG sprite
 *
 * The theme registers its icons with the 'vlt_fw_icons' filter as [ id => '<svg viewBox="…">…</svg>' ].
 * The sprite of all of them is printed after <body> (vlt_fw_body_open()), and an icon is a reference to it:
 * <svg class="vlt-icon" viewBox="…"><use href="#i-{id}"/></svg>. The viewBox keeps the icon's proportions,
 * so CSS can size it by one side as an inline SVG.
 */
class Icons extends BaseModule {
	/**
	 * Symbol id prefix
	 */
	const PREFIX = 'i-';

	/**
	 * Parsed icons [ id => [ 'attrs' => string, 'inner' => string ] ]
	 *
	 * @var array|null
	 */
	private static $icons_cache = null;

	/**
	 * Register module
	 */
	public function register() {
		add_action( 'vlt_fw_body_open', [ __CLASS__, 'render_sprite' ] );
	}

	/**
	 * Get icon markup
	 *
	 * @param string|array $icon  Icon id, or several
	 * @param string       $class Extra CSS classes
	 *
	 * @return string '' for an unknown icon
	 */
	public static function get( $icon, $class = '' ) {
		if ( is_array( $icon ) ) {
			return implode( '', array_map( fn( $single_icon ) => self::get( $single_icon, $class ), $icon ) );
		}

		$icons = self::get_icons();

		if ( !isset( $icons[ $icon ] ) ) {
			return '';
		}

		$view_box = preg_match( '/viewBox="([^"]*)"/', $icons[ $icon ]['attrs'], $match ) ? ' viewBox="' . esc_attr( $match[1] ) . '"' : '';

		return sprintf(
			'<svg class="%s"%s aria-hidden="true"><use href="#%s"/></svg>',
			esc_attr( trim( 'vlt-icon ' . self::sanitize_class( $class ) ) ),
			$view_box,
			esc_attr( self::PREFIX . $icon )
		);
	}

	/**
	 * Check if icon exists
	 *
	 * @param string $icon Icon id
	 *
	 * @return bool
	 */
	public static function exists( $icon ) {
		return isset( self::get_icons()[ $icon ] );
	}

	/**
	 * All icons as standalone SVG markup (icon pickers, previews)
	 *
	 * @return array [ id => '<svg …>…</svg>' ]
	 */
	public static function get_all() {
		return array_map( fn( $icon ) => '<svg xmlns="http://www.w3.org/2000/svg" ' . $icon['attrs'] . '>' . $icon['inner'] . '</svg>', self::get_icons() );
	}

	/**
	 * Get icon ids
	 *
	 * @return array
	 */
	public static function get_names() {
		return array_keys( self::get_icons() );
	}

	/**
	 * Clear icons cache
	 */
	public static function clear_cache() {
		self::$icons_cache = null;
	}

	/**
	 * Print the sprite
	 */
	public static function render_sprite() {
		$icons = self::get_icons();

		if ( !$icons ) {
			return;
		}

		echo '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="position: absolute; width: 0; height: 0; overflow: hidden">';

		foreach ( $icons as $id => $icon ) {
			// Markup comes from the theme's own filter (trusted code, like a template part), so it is printed as-is
			echo '<symbol id="' . esc_attr( self::PREFIX . $id ) . '" ' . $icon['attrs'] . '>' . $icon['inner'] . '</symbol>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</svg>';
	}

	/**
	 * Icons of the filter, parsed once
	 *
	 * @return array [ id => [ 'attrs' => string, 'inner' => string ] ]
	 */
	private static function get_icons() {
		if ( null !== self::$icons_cache ) {
			return self::$icons_cache;
		}

		self::$icons_cache = [];

		foreach ( (array) apply_filters( 'vlt_fw_icons', [] ) as $id => $svg ) {
			if ( !preg_match( '/<svg\b([^>]*)>(.*)<\/svg>/s', (string) $svg, $match ) ) {
				continue;
			}

			self::$icons_cache[ sanitize_key( $id ) ] = [
				// xmlns belongs to the outer <svg>, not to <symbol>
				'attrs' => trim( preg_replace( '/\s+xmlns(:\w+)?="[^"]*"/', '', $match[1] ) ),
				'inner' => trim( $match[2] ),
			];
		}

		return self::$icons_cache;
	}

	/**
	 * Sanitize CSS class
	 *
	 * @param string $class CSS class
	 *
	 * @return string
   	 */
	private static function sanitize_class( $class ) {
		return Sanitize::sanitize_class( $class );
	}
}
