# VLThemes Framework

A modern, modular WordPress theme framework for building high-quality WordPress themes.

## Features

- **Modular Architecture**: Clean separation of concerns with individual modules for different functionality
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
add_filter('vlt_fw_config', function($config) {

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

## Colors

A theme can give the Customizer a color palette and global colors that pick from it, the way Blocksy does. Nothing
changes for a theme that registers no `color-palette` field.

Fields go in the theme's `inc/customizer/` like any other field:

```php
use VLT\Framework\Modules\Core\Customizer;

// The palette: --vlt-palette-1 … --vlt-palette-4 on :root, front end and block editor
Customizer::add_field( [
    'type'     => 'color-palette',
    'settings' => 'color_palette',
    'section'  => 'section_colors',
    'label'    => esc_html__( 'Color Palette', 'your-theme-textdomain' ),
    'default'  => [ '#2563eb', '#18181b', '#e4e4e7', '#ffffff' ],
    'names'    => [ 'Accent', 'Text', 'Border', 'Background' ],
    'presets'  => [
        'forest' => [ 'label' => 'Forest', 'colors' => [ '#15803d', '#18181b', '#e4e4e7', '#ffffff' ] ],
    ],
    'editor'   => true, // the block editor's color palette too
] );

// Optional: a dark palette, the same variables under the theme's dark mode selector (add it after the first one)
Customizer::add_field( [
    'type'     => 'color-palette',
    'settings' => 'color_palette_dark',
    'section'  => 'section_colors',
    'label'    => esc_html__( 'Dark Mode Palette', 'your-theme-textdomain' ),
    'default'  => [ '#60a5fa', '#fafafa', '#27272a', '#09090b' ],
    'names'    => [ 'Accent', 'Text', 'Border', 'Background' ],
    'selector' => '.night-mode',
] );

// A global color: a palette color ('palette-2') or a custom color, printed as the variable it names
Customizer::add_field( [
    'type'     => 'global-color',
    'settings' => 'color_text',
    'section'  => 'section_colors',
    'label'    => esc_html__( 'Text', 'your-theme-textdomain' ),
    'default'  => 'palette-2',
    'palette'  => 'color_palette',
    'variable' => '--vlt-text-color',
] );
```

- **color-palette**: the value is JSON, `{"color1":"#…",…}`. Each color opens the alpha color picker; a palette from
  `presets` fills them all at once. `count` is the number of colors (by default the number of `default` colors,
  else 8), `names` names them in the Customizer and the block editor, `selector` is where the variables go
  (`:root` by default). `'editor' => true` registers the colors with `add_theme_support( 'editor-color-palette' )`
  as `var(--vlt-palette-N, #saved)`, slugs `palette-1` … `palette-N`, so blocks use the same colors. That replaces a
  palette the theme sets in its `theme_support` config.
- **Dark palette**: a second `color-palette` field with the selector of the theme's dark mode. It sets the same
  `--vlt-palette-N` there, so everything that uses them follows the mode. A theme without a dark mode registers one
  palette.
- **global-color**: `palette-N` or a color. It prints `variable` as `var(--vlt-palette-N)` (or the color) on `:root`
  and on the selector of each palette field, front end and block editor. A palette color follows the dark palette;
  a custom color is the same in both modes. `palette` is the settings of its palette field (the first one by
  default).
- The theme maps its own tokens to these variables, with its colors as fallbacks, e.g.
  `--text: var(--vlt-text-color, #18181b);`.
- All of them preview live in the Customizer.

`vlt_fw_get_palette( $settings = '' )` returns the colors of a palette field (the first one by default):
`[ 'palette-1' => [ 'name' => 'Accent', 'color' => '#2563eb', 'variable' => '--vlt-palette-1' ], … ]`, or `[]` when
the theme registers none.

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
- `vlt_fw_get_attachment_image()` - Get attachment image HTML
- `vlt_fw_get_attachment_image_src()` - Get attachment image URL

### Content Functions
- `vlt_fw_get_trimmed_content()` - Get trimmed content with word limit

### Video Functions
- `vlt_fw_parse_video_id()` - Parse video ID from URL

### Sanitization Functions
- `vlt_fw_string_to_bool()` - Convert string to boolean
- `vlt_fw_sanitize_class()` - Sanitize CSS class name

### Menu Functions
- `vlt_get_nav_menu()` - Get navigation menu HTML
- `vlt_get_nav_menu_array()` - Get navigation menu as array

### Icon Functions
- `vlt_print_icon()` - Print icon HTML

### Customizer Functions
- `vlt_fw_get_theme_mod()` - Get theme modification with fallback
- `vlt_fw_get_palette()` - Get the colors of a color palette field

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Version

1.0.0

## Credits

**Developed by:** VLThemes
**Version:** 1.0.0
**License:** GPL v2 or later

## Support

For support, please visit [VLThemes Support](https://themeforest.net/user/vlthemes)

---

Made with ❤️ by VLThemes
