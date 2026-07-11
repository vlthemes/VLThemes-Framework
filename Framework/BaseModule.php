<?php
/**
 * Base module class for all framework modules
 */

namespace VLT\Framework;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseModule {
	protected $framework;
	protected $name    = '';
	protected $version = '1.0.0';
	protected $enabled = true;

	/**
	 * Module constructor
	 */
	public function __construct( $framework ) {
		$this->framework = $framework;
	}

	/**
	 * Register module hooks and filters
	 */
	public function register() {
	}

	/**
	 * Initialize module on 'init' hook
	 */
	public function init() {
	}

	/**
	 * Check if module is enabled
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get module name
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get module version
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get framework instance
	 */
	protected function get_framework() {
		return $this->framework;
	}

	/**
	 * Get config value with dot notation support
	 */
	protected function get_config( $key, $default = null ) {
		return $this->framework->get_config( $key, $default );
	}

	/**
	 * Get another module instance
	 */
	protected function get_module( $name ) {
		return $this->framework->get_module( $name );
	}
}
