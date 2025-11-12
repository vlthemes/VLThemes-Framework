<?php

/**
 * VLThemes Framework Bootstrap
 *
 * Initializes the VLThemes framework from theme directory
 *
 * @package VLT Framework
 */

if (! defined('ABSPATH')) {
	exit;
}

// Define framework constants
if (! defined('VLT_FRAMEWORK_VERSION')) {
	define('VLT_FRAMEWORK_VERSION', '1.0.0');
}

if (! defined('VLT_FRAMEWORK_PATH')) {
	define('VLT_FRAMEWORK_PATH', trailingslashit(get_template_directory()) . 'vlthemes-framework/');
}

if (! defined('VLT_FRAMEWORK_URL')) {
	define('VLT_FRAMEWORK_URL', trailingslashit(get_template_directory_uri()) . 'vlthemes-framework/');
}

// Load global functions (available immediately)
require_once VLT_FRAMEWORK_PATH . 'functions.php';

// Load framework core
require_once VLT_FRAMEWORK_PATH . 'Framework/Framework.php';
require_once VLT_FRAMEWORK_PATH . 'Framework/BaseModule.php';

// Initialize framework immediately
// Modules will be loaded on 'after_setup_theme' hook via Framework::init_hooks()
vlt_framework();
