<?php

namespace VLT\Framework;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Base Module Abstract Class
 *
 * All framework modules must extend this class
 * Provides common methods for accessing framework instance and config
 */
abstract class BaseModule
{

	/**
	 * Framework instance
	 *
	 * @var Framework
	 */
	protected $framework;

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Module version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Module enabled status
	 *
	 * @var bool
	 */
	protected $enabled = true;

	/**
	 * Constructor
	 *
	 * @param Framework $framework Framework instance.
	 */
	public function __construct($framework)
	{
		$this->framework = $framework;
	}

	/**
	 * Register module
	 *
	 * Called when module is loaded on 'after_setup_theme' hook
	 * Override this method in child class to register hooks and filters
	 */
	public function register()
	{
		// Override in child class
	}

	/**
	 * Initialize module
	 *
	 * Called on 'init' WordPress hook with priority 0
	 * Override this method in child class for initialization logic
	 */
	public function init()
	{
		// Override in child class
	}

	/**
	 * Check if module is enabled
	 *
	 * @return bool True if module is enabled.
	 */
	public function is_enabled()
	{
		return $this->enabled;
	}

	/**
	 * Get module name
	 *
	 * @return string Module name.
	 */
	public function get_name()
	{
		return $this->name;
	}

	/**
	 * Get module version
	 *
	 * @return string Module version.
	 */
	public function get_version()
	{
		return $this->version;
	}

	/**
	 * Get framework instance
	 *
	 * @return Framework Framework instance.
	 */
	protected function get_framework()
	{
		return $this->framework;
	}

	/**
	 * Get configuration value
	 *
	 * Supports dot notation for nested config values (e.g. 'modules.icons')
	 *
	 * @param string $key     Config key with dot notation support.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Config value or default.
	 */
	protected function get_config($key, $default = null)
	{
		return $this->framework->get_config($key, $default);
	}

	/**
	 * Get another module instance
	 *
	 * @param string $name Module name.
	 * @return BaseModule|null Module instance or null if not found.
	 */
	protected function get_module($name)
	{
		return $this->framework->get_module($name);
	}
}
