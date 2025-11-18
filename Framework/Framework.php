<?php

namespace VLT\Framework;

if (!defined('ABSPATH')) {
	exit;
}

final class Framework
{

	const VERSION = '1.0.0';

	private static $instance = null;
	private $config = [];
	private $modules = [];
	private $module_registry = [];

	/**
	 * Get singleton instance
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct()
	{
		$this->init_autoloader();
		$this->load_config();
		$this->register_modules();
		$this->init_hooks();
	}

	private function __clone() {}

	public function __wakeup()
	{
		throw new \Exception('Cannot unserialize singleton');
	}

	/**
	 * Initialize autoloader
	 */
	private function init_autoloader()
	{
		spl_autoload_register(function ($class) {
			$prefix = 'VLT\\Framework\\';
			$base_dir = VLT_FRAMEWORK_PATH;

			$len = strlen($prefix);
			if (strncmp($prefix, $class, $len) !== 0) {
				return;
			}

			$relative_class = substr($class, $len);
			$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

			if (file_exists($file)) {
				require_once $file;
			}
		});
	}

	/**
	 * Load framework configuration
	 */
	private function load_config()
	{
		$config_file = VLT_FRAMEWORK_PATH . 'Config/config.php';

		if (file_exists($config_file)) {
			$this->config = require $config_file;
		}

		$this->config = apply_filters('vlt_framework_config', $this->config);
	}

	/**
	 * Register all available modules
	 */
	private function register_modules()
	{
		$this->module_registry = [
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

			'helpers' => [
				'class' => 'Modules\\Utils\\Helpers',
				'required' => true,
				'enabled' => $this->get_config('modules.helpers', true),
				'priority' => 5,
			],

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

			'icons' => [
				'class' => 'Modules\\Features\\Icons',
				'required' => false,
				'enabled' => $this->get_config('modules.icons', true),
				'priority' => 20,
			],
		];

		$this->module_registry = apply_filters('vlt_framework_register_modules', $this->module_registry);

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
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts()
	{
		wp_enqueue_script(
			'vlt-framework-admin',
			VLT_FRAMEWORK_URL . 'assets/js/admin.js',
			[],
			$this->get_version(),
			true
		);

		wp_enqueue_style(
			'vlt-framework-admin',
			VLT_FRAMEWORK_URL . 'assets/css/admin.css',
			[],
			$this->get_version()
		);
	}

	/**
	 * Load and instantiate modules
	 */
	public function load_modules()
	{
		foreach ($this->module_registry as $key => $module_data) {
			if (!$module_data['enabled']) {
				continue;
			}

			if (isset($module_data['condition']) && !$this->check_condition($module_data['condition'])) {
				continue;
			}

			$class = 'VLT\\Framework\\' . $module_data['class'];

			if (!class_exists($class)) {
				if ($module_data['required'] && defined('WP_DEBUG') && WP_DEBUG) {
					error_log(sprintf('VLT Framework: Required module class "%s" not found', $class));
				}
				continue;
			}

			try {
				$this->modules[$key] = new $class($this);

				if (method_exists($this->modules[$key], 'register')) {
					$this->modules[$key]->register();
				}
			} catch (\Exception $e) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log(sprintf('VLT Framework: Error loading module "%s": %s', $key, $e->getMessage()));
				}

				if ($module_data['required']) {
					throw $e;
				}
			}
		}

		do_action('vlt_framework_modules_loaded', $this->modules);
	}

	/**
	 * Check if condition is met for conditional modules
	 */
	private function check_condition($condition)
	{
		if (strpos($condition, 'class_exists:') === 0) {
			$class = str_replace('class_exists:', '', $condition);
			return class_exists($class);
		}

		if (strpos($condition, 'function_exists:') === 0) {
			$function = str_replace('function_exists:', '', $condition);
			return function_exists($function);
		}

		if (strpos($condition, 'did_action:') === 0) {
			$action = str_replace('did_action:', '', $condition);
			return did_action($action);
		}

		return did_action($condition);
	}

	/**
	 * Initialize framework
	 */
	public function init()
	{
		foreach ($this->modules as $module) {
			if (method_exists($module, 'init')) {
				$module->init();
			}
		}

		do_action('vlt_framework_init');
	}

	/**
	 * WordPress fully loaded
	 */
	public function wp_loaded()
	{
		do_action('vlt_framework_loaded');
	}

	/**
	 * Get specific module instance
	 */
	public function get_module($name)
	{
		return $this->modules[$name] ?? null;
	}

	/**
	 * Get all loaded modules
	 */
	public function get_modules()
	{
		return $this->modules;
	}

	/**
	 * Check if module is loaded
	 */
	public function has_module($name)
	{
		return isset($this->modules[$name]);
	}

	/**
	 * Get configuration value (supports dot notation)
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
	 * Set configuration value (supports dot notation)
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
	 */
	public function get_all_config()
	{
		return $this->config;
	}

	/**
	 * Get framework version
	 */
	public function get_version()
	{
		return self::VERSION;
	}

	/**
	 * Get loaded modules list for debugging
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
