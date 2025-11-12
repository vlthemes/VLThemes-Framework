# VLThemes Framework

A modern, modular WordPress theme framework for building high-quality WordPress themes.

## Features

- **Modular Architecture**: Clean separation of concerns with individual modules for different functionality
- **Kirki Integration**: Advanced theme customization options
- **Helper Functions**: Common utilities for images, content, taxonomies, and more
- **Menu Management**: Flexible menu system with mega menu support
- **Sidebar Management**: Dynamic sidebar registration and management
- **Asset Management**: Intelligent asset loading and optimization
- **WooCommerce Support**: Built-in WooCommerce integration
- **Icon System**: Integrated icon management
- **Sanitization**: Comprehensive input sanitization utilities

## Installation

### As a Git Submodule

```bash
git submodule add https://github.com/vlthemes/VLThemes-Framework.git vlthemes-framework
```

### Manual Installation

1. Download the framework
2. Place it in your theme's `vlthemes-framework` directory
3. Load the framework in your `functions.php`:

```php
require_once get_template_directory() . '/vlthemes-framework/bootstrap.php';
```

## Configuration

Configure the framework using the `vlt_framework_config` filter in your theme's `functions.php`:

```php
add_filter('vlt_framework_config', function($config) {
    // Theme text domain
    $config['text_domain'] = 'your-theme-textdomain';

    // Content width
    $config['content_width'] = 1310;

    // Navigation menus
    $config['nav_menus'] = [
        'primary-menu' => esc_html__('Primary Menu', 'your-theme-textdomain'),
    ];

    // Sidebars configuration
    $config['sidebars'] = [
        [
            'name' => esc_html__('Blog Sidebar', 'your-theme-textdomain'),
            'id' => 'blog-sidebar',
        ],
    ];

    return $config;
});
```

## Module System

The framework uses a modular architecture with the following module types:

### Core Modules (Priority 10)
- **Setup**: Theme setup and configuration
- **Assets**: Asset management (CSS, JS)
- **Menus**: Navigation menu registration
- **Sidebars**: Widget area management
- **Filters**: WordPress filters
- **Actions**: WordPress actions
- **BodyClass**: Body class management
- **PluginActivation**: Required plugin activation

### Utils Modules (Priority 5)
- **Helpers**: Common helper functions
- **Sanitize**: Input sanitization utilities

### Integration Modules (Priority 1)
- **Kirki**: Kirki Customizer framework integration

### Feature Modules (Priority 20)
- **Icons**: Icon system management

## Available Filters

- `vlt_framework_config` - Override framework configuration
- `vlt_framework_register_modules` - Register custom modules
- `vlt_framework_custom_sidebars` - Add dynamic sidebars

## Available Actions

- `vlt_framework_init` - Fires when framework is initialized
- `vlt_framework_loaded` - Fires when framework is fully loaded
- `vlt_framework_modules_loaded` - Fires when modules are loaded
- `vlt_framework_action_body_open` - Fires on wp_body_open
- `vlt_framework_actions_init` - Fires when actions module is initialized

## Helper Functions

### Image Functions
- `vlt_get_attachment_image()` - Get attachment image HTML
- `vlt_get_attachment_image_src()` - Get attachment image URL

### Content Functions
- `vlt_get_trimmed_content()` - Get trimmed content with word limit

### Taxonomy Functions
- `vlt_get_post_taxonomy()` - Get post taxonomy terms

### Video Functions
- `vlt_parse_video_id()` - Parse video ID from URL

### Sanitization Functions
- `vlt_string_to_bool()` - Convert string to boolean
- `vlt_sanitize_class()` - Sanitize CSS class name

### Menu Functions
- `vlt_get_nav_menu()` - Get navigation menu HTML
- `vlt_get_nav_menu_array()` - Get navigation menu as array

### Icon Functions
- `vlt_print_icon()` - Print icon HTML

### Customizer Functions
- `vlt_get_theme_mod()` - Get theme modification with fallback

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Version

1.0.0

## License

This framework is proprietary software developed by VLThemes.

## Support

For support, please visit [VLThemes](https://vlthemes.com)
