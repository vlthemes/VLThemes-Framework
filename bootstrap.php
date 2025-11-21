<?php

/**
 * VLThemes Framework Bootstrap
 *
 * Initializes the VLThemes framework from theme directory
 *
 * @package VLT Framework
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// Define framework constants
if ( !defined( 'VLT_FW_VERSION' ) ) {
	define( 'VLT_FW_VERSION', '1.0.0' );
}

if ( !defined( 'VLT_FW_PATH' ) ) {
	define( 'VLT_FW_PATH', trailingslashit( get_template_directory() ) . 'vlthemes-framework/' );
}

if ( !defined( 'VLT_FW_URL' ) ) {
	define( 'VLT_FW_URL', trailingslashit( get_template_directory_uri() ) . 'vlthemes-framework/' );
}

// Load global functions (available immediately)
require_once VLT_FW_PATH . 'functions.php';

// Load framework core
require_once VLT_FW_PATH . 'Framework/Framework.php';

require_once VLT_FW_PATH . 'Framework/BaseModule.php';

// Initialize framework immediately
// Modules will be loaded on 'after_setup_theme' hook via Framework::init_hooks()
vlt_fw();
