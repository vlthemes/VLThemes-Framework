<?php

/**
 * VLT Framework Core
 *
 * Debug Logging Configuration:
 * Error logging can be controlled by defining DEV_MODE constant in wp-config.php:
 * define('DEV_MODE', true);  // Enable debug logging
 * define('DEV_MODE', false); // Disable debug logging
 *
 * If DEV_MODE is not defined, logging falls back to WP_DEBUG setting.
 */

namespace VLT\Framework;

if (!defined('ABSPATH')) {
	exit;
}

final class Framework
{

	/**
	 * Framework version
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Single instance
	 *
	 * @var Framework|null
	 */
	private static $instance = null;

	/**
	 * Configuration array
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Loaded modules
	 *
	 * @var array
	 */
	private $modules = [];

	/**
	 * Module registry
	 *
	 * @var array
	 */
	private $module_registry = [];

	/**
	 * Get singleton instance
	 *
	 * @return Framework
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Private to prevent direct instantiation
	 */
	private function __construct()
	{
		$this->init_autoloader();
		$this->load_config();
		$this->register_modules();
		$this->init_hooks();
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 */
	public function __wakeup()
	{
		throw new \Exception('Cannot unserialize singleton');
	}

	/**
	 * Initialize autoloader for framework classes
	 *
	 * PSR-4 compatible autoloader
	 */
	private function init_autoloader()
	{
		spl_autoload_register(function ($class) {
			$prefix = 'VLT\\Framework\\';
			$base_dir = VLT_FRAMEWORK_PATH;

			// Check if class uses the namespace prefix
			$len = strlen($prefix);
			if (strncmp($prefix, $class, $len) !== 0) {
				return;
			}

			// Get the relative class name
			$relative_class = substr($class, $len);

			// Replace namespace separator with directory separator
			$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

			// Debug: Log autoloader attempts for Utils modules
			$dev_mode = defined('WP_DEBUG') && WP_DEBUG;
			if (strpos($relative_class, 'Modules\\Utils\\') === 0 && $dev_mode) {
				error_log(sprintf(
					'[VLT Autoloader] Class: %s, File: %s, Exists: %s',
					$class,
					$file,
					file_exists($file) ? 'YES' : 'NO'
				));
			}

			// If file exists, require it
			if (file_exists($file)) {
				require_once $file;
			}
		});
	}

	/**
	 * Load framework configuration
	 *
	 * Loads default config from config.php and allows themes to override
	 */
	private function load_config()
	{
		$config_file = VLT_FRAMEWORK_PATH . 'Config/config.php';

		if (file_exists($config_file)) {
			$this->config = require $config_file;
		}

		// Allow themes to override/extend config
		$this->config = apply_filters('vlt_framework_config', $this->config);
	}

	/**
	 * Register all available modules
	 *
	 * Defines which modules are available and their loading conditions
	 */
	private function register_modules()
	{
		$this->module_registry = [
			// ========================================
			// PRIORITY 1: KIRKI & SANITIZE
			// ========================================
			'sanitize' => [
				'class' => 'Modules\\Utils\\Sanitize',
				'required' => true,
				'enabled' => $this->get_config('modules.sanitize', true),
				'priority' => 1,
			],

			'kirki' => [
				'class' => 'Modules\\Integrations\\Kirki',
				'required' => true,
				'enabled' => $this->get_config('modules.kirki', true),
				'priority' => 1,
			],

			// ========================================
			// PRIORITY 5: UTILS (Helpers)
			// ========================================

			'helpers' => [
				'class' => 'Modules\\Utils\\Helpers',
				'required' => true,
				'enabled' => $this->get_config('modules.helpers', true),
				'priority' => 5,
			],

			// ========================================
			// PRIORITY 10: CORE MODULES
			// ========================================
			'setup' => [
				'class' => 'Modules\\Core\\Setup',
				'required' => true,
				'enabled' => $this->get_config('modules.setup', true),
				'priority' => 10,
			],
			'assets' => [
				'class' => 'Modules\\Core\\Assets',
				'required' => true,
				'enabled' => $this->get_config('modules.assets', true),
				'priority' => 10,
			],
			'body_class' => [
				'class' => 'Modules\\Core\\BodyClass',
				'required' => true,
				'enabled' => $this->get_config('modules.body_class', true),
				'priority' => 10,
			],
			'menus' => [
				'class' => 'Modules\\Core\\Menus',
				'required' => true,
				'enabled' => $this->get_config('modules.menus', true),
				'priority' => 10,
			],
			'sidebars' => [
				'class' => 'Modules\\Core\\Sidebars',
				'required' => true,
				'enabled' => $this->get_config('modules.sidebars', true),
				'priority' => 10,
			],
			'filters' => [
				'class' => 'Modules\\Core\\Filters',
				'required' => true,
				'enabled' => $this->get_config('modules.filters', true),
				'priority' => 10,
			],
			'actions' => [
				'class' => 'Modules\\Core\\Actions',
				'required' => true,
				'enabled' => $this->get_config('modules.actions', true),
				'priority' => 10,
			],
			'plugin_activation' => [
				'class' => 'Modules\\Core\\PluginActivation',
				'required' => true,
				'enabled' => $this->get_config('modules.plugin_activation', true),
				'priority' => 10,
			],

			// ========================================
			// PRIORITY 20: FEATURE MODULES
			// ========================================
			'icons' => [
				'class' => 'Modules\\Features\\Icons',
				'required' => false,
				'enabled' => $this->get_config('modules.icons', true),
				'priority' => 20,
			],
		];

		// Allow themes/plugins to register custom modules
		$this->module_registry = apply_filters('vlt_framework_register_modules', $this->module_registry);

		// Sort by priority
		uasort($this->module_registry, function ($a, $b) {
			$priority_a = $a['priority'] ?? 20;
			$priority_b = $b['priority'] ?? 20;
			return $priority_a - $priority_b;
		});
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks()
	{
		add_action('after_setup_theme', [$this, 'load_modules'], 5);
		add_action('init', [$this, 'init'], 0);
		add_action('wp_loaded', [$this, 'wp_loaded']);
	}

	/**
	 * Load and instantiate modules
	 *
	 * Runs on 'after_setup_theme' hook with priority 5
	 */
	public function load_modules()
	{
		foreach ($this->module_registry as $key => $module_data) {
			// Skip if disabled
			if (!$module_data['enabled']) {
				continue;
			}

			// Check condition if exists
			if (isset($module_data['condition']) && !$this->check_condition($module_data['condition'])) {
				continue;
			}

			// Load module
			$class = 'VLT\\Framework\\' . $module_data['class'];

			// Debug: Log module loading attempts
			$dev_mode = defined('WP_DEBUG') && WP_DEBUG;
			if (in_array($key, ['sanitize', 'helpers']) && $dev_mode) {
				error_log(sprintf(
					'[VLT Load Module] Key: %s, Class: %s, Exists before check: %s',
					$key,
					$class,
					class_exists($class, false) ? 'YES' : 'NO'
				));
			}

			if (!class_exists($class)) {
				if ($module_data['required']) {
					$dev_mode = defined('WP_DEBUG') && WP_DEBUG;
					if ($dev_mode) {
						error_log(sprintf('VLT Framework: Required module class "%s" not found', $class));
					}
				}
				continue;
			}

			try {
				// Instantiate module
				$this->modules[$key] = new $class($this);

				// Call register method if exists
				if (method_exists($this->modules[$key], 'register')) {
					$this->modules[$key]->register();
				}
			} catch (\Exception $e) {
				$dev_mode = defined('WP_DEBUG') && WP_DEBUG;
				if ($dev_mode) {
					error_log(sprintf('VLT Framework: Error loading module "%s": %s', $key, $e->getMessage()));
				}

				if ($module_data['required']) {
					throw $e;
				}
			}
		}

		// Notify that modules are loaded
		do_action('vlt_framework_modules_loaded', $this->modules);
	}

	/**
	 * Check if condition is met for conditional modules
	 *
	 * @param string $condition Condition to check
	 * @return bool
	 */
	private function check_condition($condition)
	{
		// Check for class existence
		if (strpos($condition, 'class_exists:') === 0) {
			$class = str_replace('class_exists:', '', $condition);
			return class_exists($class);
		}

		// Check for function existence
		if (strpos($condition, 'function_exists:') === 0) {
			$function = str_replace('function_exists:', '', $condition);
			return function_exists($function);
		}

		// Check for action fired
		if (strpos($condition, 'did_action:') === 0) {
			$action = str_replace('did_action:', '', $condition);
			return did_action($action);
		}

		// Default: check if action was fired
		return did_action($condition);
	}

	/**
	 * Initialize framework
	 *
	 * Runs on 'init' hook with priority 0
	 */
	public function init()
	{
		// Initialize loaded modules
		foreach ($this->modules as $module) {
			if (method_exists($module, 'init')) {
				$module->init();
			}
		}

		// Notify that framework is initialized
		do_action('vlt_framework_init');
	}

	/**
	 * WordPress fully loaded
	 *
	 * Runs on 'wp_loaded' hook
	 */
	public function wp_loaded()
	{
		do_action('vlt_framework_loaded');
	}

	/**
	 * Get specific module instance
	 *
	 * @param string $name Module name
	 * @return object|null Module instance or null if not found
	 */
	public function get_module($name)
	{
		return $this->modules[$name] ?? null;
	}

	/**
	 * Get all loaded modules
	 *
	 * @return array Array of module instances
	 */
	public function get_modules()
	{
		return $this->modules;
	}

	/**
	 * Check if module is loaded
	 *
	 * @param string $name Module name
	 * @return bool
	 */
	public function has_module($name)
	{
		return isset($this->modules[$name]);
	}

	/**
	 * Get configuration value
	 *
	 * Supports dot notation: 'modules.portfolio'
	 *
	 * @param string $key Config key (dot notation supported)
	 * @param mixed $default Default value if key not found
	 * @return mixed Config value or default
	 */
	public function get_config($key, $default = null)
	{
		$keys = explode('.', $key);
		$value = $this->config;

		foreach ($keys as $k) {
			if (!isset($value[$k])) {
				return $default;
			}
			$value = $value[$k];
		}

		return $value;
	}

	/**
	 * Set configuration value
	 *
	 * @param string $key Config key (dot notation supported)
	 * @param mixed $value Value to set
	 */
	public function set_config($key, $value)
	{
		$keys = explode('.', $key);
		$config = &$this->config;

		foreach ($keys as $i => $k) {
			if ($i === count($keys) - 1) {
				$config[$k] = $value;
			} else {
				if (!isset($config[$k]) || !is_array($config[$k])) {
					$config[$k] = [];
				}
				$config = &$config[$k];
			}
		}
	}

	/**
	 * Get all configuration
	 *
	 * @return array
	 */
	public function get_all_config()
	{
		return $this->config;
	}

	/**
	 * Get framework version
	 *
	 * @return string
	 */
	public function get_version()
	{
		return self::VERSION;
	}

	/**
	 * Debug: Get loaded modules list
	 *
	 * @return array
	 */
	public function debug_modules()
	{
		$debug = [];

		foreach ($this->module_registry as $key => $data) {
			$debug[$key] = [
				'registered' => true,
				'loaded' => isset($this->modules[$key]),
				'enabled' => $data['enabled'],
				'required' => $data['required'],
				'class' => $data['class'],
			];
		}

		return $debug;
	}
}
