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

}
