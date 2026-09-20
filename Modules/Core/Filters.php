<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters Module
 */
class Filters extends BaseModule {
	protected $name    = 'filters';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register() {
		add_filter( 'login_headerurl', [ $this, 'change_admin_logo_link' ] );
		add_filter( 'comment_form_logged_in', '__return_empty_string' );
		add_filter( 'big_image_size_threshold', '__return_false' );
		add_filter( 'excerpt_more', [ $this, 'excerpt_more' ] );
		add_filter( 'excerpt_length', [ $this, 'excerpt_length' ] );
		add_filter( 'wp_kses_allowed_html', [ $this, 'allow_inline_svg' ], 10, 2 );

		do_action( 'vlt_fw_filters_init' );
	}

	/**
	 * Change admin logo link to home URL
	 */
	public function change_admin_logo_link() {
		return esc_url( home_url( '/' ) );
	}

	public function excerpt_more( $more ) {
		$more = '...';

		return apply_filters( 'vlt_fw_excerpt_more', $more );
	}

	public function excerpt_length( $length ) {
		$length = 55;

		return apply_filters( 'vlt_fw_excerpt_length', $length );
	}

	/**
	 * Allow inline SVG markup (used by header/footer templates) to survive wp_kses_post().
	 */
	public function allow_inline_svg( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['svg'] = [
				'xmlns'           => true,
				'xmlns:xlink'     => true,
				'id'              => true,
				'class'           => true,
				'style'           => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'viewbox'         => true,
				'width'           => true,
				'height'          => true,
				'preserveaspectratio' => true,
				'aria-hidden'     => true,
				'aria-label'      => true,
				'role'            => true,
				'focusable'       => true
			];

			$svg_shape_attrs = [
				'id'               => true,
				'class'            => true,
				'style'            => true,
				'fill'             => true,
				'fill-rule'        => true,
				'fill-opacity'     => true,
				'clip-rule'        => true,
				'stroke'           => true,
				'stroke-width'     => true,
				'stroke-linecap'   => true,
				'stroke-linejoin'  => true,
				'stroke-dasharray' => true,
				'stroke-dashoffset' => true,
				'stroke-opacity'   => true,
				'opacity'          => true,
				'transform'        => true,
				'pathlength'       => true
			];

			$tags['path']     = $svg_shape_attrs + [ 'd' => true ];
			$tags['g']        = $svg_shape_attrs;
			$tags['circle']   = $svg_shape_attrs + [ 'cx' => true, 'cy' => true, 'r' => true ];
			$tags['ellipse']  = $svg_shape_attrs + [ 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ];
			$tags['rect']     = $svg_shape_attrs + [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ];
			$tags['line']     = $svg_shape_attrs + [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ];
			$tags['polyline'] = $svg_shape_attrs + [ 'points' => true ];
			$tags['polygon']  = $svg_shape_attrs + [ 'points' => true ];
			$tags['use']      = $svg_shape_attrs + [ 'x' => true, 'y' => true, 'xlink:href' => true, 'href' => true ];
			$tags['defs']     = [];
			$tags['clippath'] = [ 'id' => true ];
		}

		return $tags;
	}

}
