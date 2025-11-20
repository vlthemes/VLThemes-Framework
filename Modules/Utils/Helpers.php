<?php

namespace VLT\Framework\Modules\Utils;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Helpers Module
 */
class Helpers extends BaseModule
{

	protected $name = 'helpers';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		// Apply dynamic content parsing to WordPress content
		add_filter('the_content', [__CLASS__, 'parse_dynamic_content'], 999);
		add_filter('the_excerpt', [__CLASS__, 'parse_dynamic_content'], 999);
		add_filter('widget_text', [__CLASS__, 'parse_dynamic_content'], 999);
	}

	/**
	 * Get trimmed content
	 */
	public static function get_trimmed_content($post_id = null, $max_words = 18)
	{
		if (! is_numeric($max_words) || $max_words < 1) {
			$max_words = 18;
		}

		$postID = $post_id ?: get_the_ID();
		$post    = get_post($postID);

		if (! $post) {
			return '';
		}

		$content = ! empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;

		if (empty($content)) {
			return '';
		}

		$content = preg_replace('~(?:\[/?)[^/\]]+/?\]~s', '', $content);

		$content = preg_replace('/<style\b[^>]*>[\s\S]*?\.elementor-\d[^}]*\}[\s\S]*?<\/style>/is', '', $content);

		$content = preg_replace('#</p>#i', "\n\n", $content);
		$content = preg_replace('#<p[^>]*>#i', '', $content);

		$content = strip_tags($content);

		$content = preg_replace('/\s+/', ' ', $content);
		$content = trim($content);

		$words = explode(' ', $content, $max_words + 1);
		if (count($words) > $max_words) {
			array_pop($words);
			$words[] = '...';
		}

		$content = implode(' ', $words);
		$content = self::parse_dynamic_content($content);
		$content = esc_html($content);

		return apply_filters('vlt_framework_trimmed_content', $content, $max_words);
	}

	/**
	 * Get attachment image
	 */
	public static function get_attachment_image($image_id, $image_size_key = 'full', $class = '', $image_key = '', $settings = [])
	{
		if (empty($image_id)) {
			return false;
		}

		$size = $image_size_key;

		// Handle custom size
		if ('custom' === $image_size_key && ! empty($image_key)) {
			$custom_key = $image_key . '_custom_dimension';
			$dim        = $settings[$custom_key] ?? [];

			$w = ! empty($dim['width']) && is_numeric($dim['width']) ? (int) $dim['width'] : null;
			$h = ! empty($dim['height']) && is_numeric($dim['height']) ? (int) $dim['height'] : null;

			if ($w || $h) {
				$size = [$w ?? 0, $h ?? 0, true];
			} else {
				$size = 'full';
			}
		}

		$attrs = [];

		if (! empty($class)) {
			$attrs['class'] = trim($class);
		}

		$attrs['loading'] = 'lazy';

		$output = wp_get_attachment_image($image_id, $size, false, $attrs);

		return apply_filters('vlt_framework_attachment_image', $output, $image_id, $size, $class, $settings);
	}

	/**
	 * Get attachment image source URL
	 */
	public static function get_attachment_image_src($image_id, $image_size_key = 'full', string $image_key = '', array $settings = [])
	{
		if (empty($image_id)) {
			return false;
		}

		$size = $image_size_key;

		if ('custom' === $image_size_key && !empty($image_key)) {
			$custom_key = $image_key . '_custom_dimension';
			$dim = $settings[$custom_key] ?? [];

			$w = !empty($dim['width']) && is_numeric($dim['width']) ? (int) $dim['width'] : null;
			$h = !empty($dim['height']) && is_numeric($dim['height']) ? (int) $dim['height'] : null;

			if ($w || $h) {
				$size = [$w ?? 0, $h ?? 0, true];
			} else {
				$size = 'full';
			}
		} elseif (is_array($image_size_key)) {
			$size = $image_size_key;
		}

		$image_src = wp_get_attachment_image_src($image_id, $size);

		if (!$image_src) {
			return false;
		}

		$output = $image_src[0];

		return apply_filters('vlt_framework_attachment_image_src', $output, $image_id, $size, $settings);
	}

	/**
	 * Get post taxonomy terms
	 */
	public static function get_post_taxonomy($postID, $taxonomy, $delimiter = ', ', $get = 'name', $link = true)
	{
		$tags = wp_get_post_terms($postID, $taxonomy);

		if (empty($tags) || is_wp_error($tags)) {
			return '';
		}

		$list = [];

		foreach ($tags as $tag) {
			if ($link) {
				$term_link = get_term_link($tag->term_id, $taxonomy);
				if (! is_wp_error($term_link)) {
					$list[] = '<a href="' . esc_url($term_link) . '">' . esc_html($tag->$get) . '</a>';
				}
			} else {
				$list[] = esc_html($tag->$get);
			}
		}

		$output = implode($delimiter, $list);

		return apply_filters('vlt_framework_post_taxonomy', $output, $postID, $taxonomy, $delimiter, $get, $link);
	}

	/**
	 * Get placeholder image source URL
	 */
	public static function get_placeholder_image_src()
	{
		$default_url = '';

		// Use Elementor placeholder if available
		if (class_exists('\Elementor\Utils')) {
			$default_url = \Elementor\Utils::get_placeholder_image_src();
		}

		// Fallback to local placeholder image
		if (empty($default_url) && defined('VLT_FRAMEWORK_URL')) {
			$default_url = VLT_FRAMEWORK_URL . 'includes/img/placeholder.png';
		}

		// Allow filtering of placeholder image URL
		return apply_filters('vlt_framework_placeholder_image_src', $default_url);
	}

	/**
	 * Get placeholder image HTML
	 */
	public static function get_placeholder_image($class = '', $alt = '')
	{
		$image_src = self::get_placeholder_image_src();

		if (empty($image_src)) {
			return '';
		}

		$attrs = [
			'src' => esc_url($image_src),
			'alt' => esc_attr($alt ?: __('Placeholder', 'vlthemes-framework')),
			'loading' => 'lazy'
		];

		if (!empty($class)) {
			$attrs['class'] = trim($class);
		}

		$attrs_string = '';
		foreach ($attrs as $key => $value) {
			$attrs_string .= sprintf(' %s="%s"', $key, $value);
		}

		$output = sprintf('<img%s />', $attrs_string);

		return apply_filters('vlt_framework_placeholder_image', $output, $image_src, $class, $alt);
	}

	/**
	 * Parse video ID from URL
	 */
	public static function parse_video_id($url)
	{
		if (empty($url) || ! is_string($url)) {
			return ['custom', ''];
		}

		$vendors = [
			[
				'vendor'       => 'youtube',
				'pattern'      => '/(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)\/(?:embed\/|v\/|watch\?v=|watch\?list=(.*)&v=|watch\?(.*[^&]&)v=)?((\w|-){11})(&list=(\w+)&?)?/',
				'patternIndex' => 6,
			],
			[
				'vendor'       => 'vimeo',
				'pattern'      => '/https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)/',
				'patternIndex' => 3,
			],
		];

		foreach ($vendors as $vendor) {
			$video_id = false;

			if (preg_match($vendor['pattern'], $url, $matches) && isset($matches[$vendor['patternIndex']])) {
				$video_id = $matches[$vendor['patternIndex']];
			}

			if ($video_id) {
				$data = [$vendor['vendor'], $video_id];
				return apply_filters('vlt_framework_video_id', $data, $url);
			}
		}

		return apply_filters('vlt_framework_video_id', ['custom', esc_url_raw($url)], $url);
	}

	/**
	 * Parse dynamic content variables in text
	 *
	 * Replaces dynamic variables with their actual values:
	 * - {{YEAR}} - Current year (e.g., 2025)
	 * - {{SITE_TITLE}} - Site title from WordPress settings
	 * - {{SITE_URL}} - Home URL of the site
	 * - {{SITE_NAME}} - Site name (blogname)
	 * - {{ADMIN_EMAIL}} - Administrator email
	 *
	 * @param string $text Text containing dynamic variables
	 * @return string Parsed text with replaced variables
	 */
	public static function parse_dynamic_content($text)
	{
		if (empty($text) || !is_string($text)) {
			return $text;
		}

		// Prepare replacements array
		$theme = wp_get_theme();
		$replacements = array(
			'{{YEAR}}'         => date('Y'),
			'{{SITE_TITLE}}'   => get_bloginfo('name'),
			'{{SITE_URL}}'     => home_url('/'),
			'{{SITE_NAME}}'    => get_bloginfo('name'),
			'{{ADMIN_EMAIL}}'  => get_bloginfo('admin_email'),
			'{{PAGE_TITLE}}'   => get_the_title(),
			'{{SITE_TAGLINE}}' => get_bloginfo('description'),
			'{{PAGE_ID}}'      => get_the_ID(),
			'{{THEME_NAME}}'   => $theme->get('Name'),
		);

		// Allow themes/plugins to add custom dynamic variables
		$replacements = apply_filters('vlt_framework_dynamic_content_vars', $replacements);

		// Perform replacements
		$text = str_replace(array_keys($replacements), array_values($replacements), $text);

		return $text;
	}
}
