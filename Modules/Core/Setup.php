<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Module
 */
class Setup extends BaseModule {
	protected $name    = 'setup';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register() {
		add_action( 'after_setup_theme', [ $this, 'theme_setup' ] );
		add_action( 'after_setup_theme', [ $this, 'content_width' ], 0 );
	}

	/**
	 * Theme setup
	 */
	public function theme_setup() {
		$theme_domain_path = apply_filters(
			'vlt_fw_theme_domain_path',
			get_template_directory() . '/languages'
		);

		load_theme_textdomain( '@@textdomain', $theme_domain_path );

		$this->add_theme_support();
		$this->register_image_sizes();

		do_action( 'vlt_fw_after_theme_setup' );
	}

	/**
	 * Set content width
	 */
	public function content_width() {
		$GLOBALS['content_width'] = apply_filters( 'vlt_fw_content_width', 1300 );
	}

	/**
	 * Add theme support features from config
	 */
	private function add_theme_support() {
		$supports = $this->get_config( 'theme_support', [] );

		foreach ( $supports as $feature => $args ) {
			if ( is_numeric( $feature ) ) {
				add_theme_support( $args );
			} else {
				add_theme_support( $feature, $args );
			}
		}
	}

	/**
	 * Register custom image sizes from config
	 */
	private function register_image_sizes() {
		$sizes = $this->get_config( 'image_sizes', [] );

		foreach ( $sizes as $name => $size ) {
			add_image_size(
				$name,
				$size[0] ?? 0,
				$size[1] ?? 0,
				$size[2] ?? false
			);
		}
	}
}
