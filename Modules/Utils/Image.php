<?php

namespace VLT\Framework\Modules\Utils;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Module
 *
 * Attachment images with the Elementor "custom" size, and the placeholder image.
 * Used through vlt_fw_get_attachment_image() and the other vlt_fw_* image functions.
 */
class Image extends BaseModule {
	protected $name    = 'image';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register() {
	}

	/**
	 * Attachment image HTML
	 *
	 * @param int   $image_id Attachment ID
	 * @param array $args     size, class, image_key / settings (Elementor custom size), lazy_load
	 *
	 * @return string|false Image HTML or false without an image
	 */
	public static function get_attachment_image( $image_id, $args = [] ) {
		if ( empty( $image_id ) ) {
			return false;
		}

		$args = wp_parse_args( $args, [
			'size'      => 'full',
			'class'     => '',
			'image_key' => '',
			'settings'  => [],
			'lazy_load' => true,
		] );

		$size  = self::parse_image_size( $args['size'], $args['image_key'], $args['settings'] );
		$attrs = [];

		if ( !empty( $args['class'] ) ) {
			$attrs['class'] = trim( $args['class'] );
		}

		// Leave lazy loading to WordPress, which keeps the first images eager
		if ( !$args['lazy_load'] ) {
			$attrs['loading'] = 'eager';
		}

		$output = wp_get_attachment_image( $image_id, $size, false, $attrs );

		return apply_filters( 'vlt_fw_attachment_image', $output, $image_id, $size, $args['class'], $args['settings'] );
	}

	/**
	 * Attachment image URL
	 *
	 * @param int   $image_id Attachment ID
	 * @param array $args     size, image_key / settings (Elementor custom size)
	 *
	 * @return string|false Image URL or false without an image
	 */
	public static function get_attachment_image_src( $image_id, $args = [] ) {
		if ( empty( $image_id ) ) {
			return false;
		}

		$args = wp_parse_args( $args, [
			'size'      => 'full',
			'image_key' => '',
			'settings'  => [],
		] );

		$size      = self::parse_image_size( $args['size'], $args['image_key'], $args['settings'] );
		$image_src = wp_get_attachment_image_src( $image_id, $size );

		if ( !$image_src ) {
			return false;
		}

		return apply_filters( 'vlt_fw_attachment_image_src', $image_src[0], $image_id, $size, $args['settings'] );
	}

	/**
	 * Placeholder image URL: Elementor's when it is active, else the framework's
	 *
	 * @return string
	 */
	public static function get_placeholder_image_src() {
		$url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

		return apply_filters( 'vlt_fw_placeholder_image_src', $url ?: VLT_FW_URL . 'assets/img/placeholder.png' );
	}

	/**
	 * Placeholder image HTML
	 *
	 * @param string $class CSS class
	 * @param string $alt   Alt text
	 *
	 * @return string
	 */
	public static function get_placeholder_image( $class = '', $alt = '' ) {
		$src = self::get_placeholder_image_src();

		if ( empty( $src ) ) {
			return '';
		}

		$output = sprintf(
			'<img src="%s" alt="%s" loading="lazy"%s />',
			esc_url( $src ),
			esc_attr( $alt ?: __( 'Placeholder', '@@textdomain' ) ),
			$class ? ' class="' . esc_attr( trim( $class ) ) . '"' : ''
		);

		return apply_filters( 'vlt_fw_placeholder_image', $output, $src, $class, $alt );
	}

	/**
	 * Image size from the arguments: Elementor's "custom" size reads {image_key}_custom_dimension from the settings
	 *
	 * @param string|array $size      Size name or [ w, h, crop ]
	 * @param string       $image_key Image control key
	 * @param array        $settings  Widget settings
	 *
	 * @return string|array
	 */
	private static function parse_image_size( $size, $image_key = '', $settings = [] ) {
		if ( is_array( $size ) || 'custom' !== $size || empty( $image_key ) ) {
			return $size;
		}

		$dim = $settings[ $image_key . '_custom_dimension' ] ?? [];
		$w   = !empty( $dim['width'] ) && is_numeric( $dim['width'] ) ? (int) $dim['width'] : null;
		$h   = !empty( $dim['height'] ) && is_numeric( $dim['height'] ) ? (int) $dim['height'] : null;

		return $w || $h ? [ $w ?? 0, $h ?? 0, true ] : 'full';
	}
}
