<?php

namespace VLT\Framework\Modules\Core;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menus Module
 */
class Menus extends BaseModule {
	protected $name    = 'menus';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register() {
		add_action( 'after_setup_theme', [ $this, 'register_menus' ], 10 );
	}

	/**
	 * Register all menus
	 */
	public function register_menus() {
		// Register default menus from config
		$this->register_default_menus();

		// Register custom menus from theme
		$this->register_custom_menus();
	}

	/**
	 * Check if menu location has menu assigned
	 */
	public static function has_menu( $location ) {
		return has_nav_menu( $location );
	}

	/**
	 * Get menu by location
	 */
	public static function get_menu( $location ) {
		$locations = get_nav_menu_locations();

		if ( !isset( $locations[ $location ] ) ) {
			return false;
		}

		return wp_get_nav_menu_object( $locations[ $location ] );
	}

	/**
	 * Display navigation menu by location
	 */
	public static function display_menu( $location, $args = [] ) {
		$defaults = [
			'theme_location'  => $location,
			'container'       => 'nav',
			'container_class' => 'vlt-menu-' . $location,
			'menu_class'      => 'vlt-menu',
			'fallback_cb'     => [ __CLASS__, 'fallback' ]
		];

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['theme_location'] ) ) {
			$args['theme_location'] = $location;
		}

		wp_nav_menu( $args );
	}

	/**
	 * Fallback callback when menu is not assigned
	 */
	public static function fallback() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		$menu_link = '<a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" target="_blank">' . esc_html__( 'Appearance > Menus', '@@textdomain' ) . '</a>';
		$message   = sprintf( esc_html__( 'Please register navigation from %s', '@@textdomain' ), $menu_link );

		echo '<p class="vlt-no-menu-message">' . wp_kses_post( $message ) . '</p>';
	}

	/**
	 * Get navigation breakpoint for responsive menu
	 */
	public static function get_nav_breakpoint() {
		$breakpoint = apply_filters( 'vlt_fw_nav_breakpoint', 'xl' );

		// Validate breakpoint
		$valid_breakpoints = [ 'xs', 'sm', 'md', 'lg', 'xl' ];

		if ( !in_array( $breakpoint, $valid_breakpoints, true ) ) {
			$breakpoint = 'xl';
		}

		return $breakpoint;
	}

	/**
	 * Register default menus from config
	 */
	private function register_default_menus() {
		$menus = $this->get_config( 'nav_menus', [] );

		if ( empty( $menus ) || !is_array( $menus ) ) {
			return;
		}

		register_nav_menus( $menus );
	}

	/**
	 * Register custom menus from theme filter
	 */
	private function register_custom_menus() {
		$custom_menus = apply_filters( 'vlt_fw_custom_menus', [] );

		if ( empty( $custom_menus ) || !is_array( $custom_menus ) ) {
			return;
		}

		register_nav_menus( $custom_menus );
	}
}
