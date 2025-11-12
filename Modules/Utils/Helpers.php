<?php

namespace VLT\Framework\Modules\Utils;

use VLT\Framework\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Helpers Module
 *
 * Provides various helper functions and utilities
 * Handles MIME types, content trimming, and other common tasks
 */
class Helpers extends BaseModule
{

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'helpers';

	/**
	 * Module version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register()
	{
		// No functions to register - using global functions.php
	}

	/**
	 * Get trimmed content
	 *
	 * Removes shortcodes, strips tags, and limits content to specified word count
	 * Can accept either content string or post ID (uses excerpt if available)
	 *
	 * @param string|int|null $content_or_post_id Content string or Post ID.
	 * @param int             $max_words          Maximum number of words.
	 * @return string Trimmed content.
	 */
	public static function get_trimmed_content($content_or_post_id = null, $max_words = 18)
	{

		// Validate max_words
		if (! is_numeric($max_words) || $max_words < 1) {
			$max_words = 18;
		}

		$content = '';

		// If numeric, treat as post ID
		if (is_numeric($content_or_post_id) || is_null($content_or_post_id)) {
			$postID = $content_or_post_id ?: get_the_ID();
			$post    = get_post($postID);

			if (! $post) {
				return '';
			}

			// Use excerpt if available, otherwise use content
			$content = ! empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;
		} else {
			// Treat as content string
			$content = $content_or_post_id;
		}

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
		$content = esc_html($content);

		return apply_filters('vlt_framework_trimmed_content', $content, $content_or_post_id, $max_words);
	}

	/**
	 * Get attachment image
	 *
	 * Returns formatted image HTML with lazy loading and custom size support
	 *
	 * @param int    $image_id       Image attachment ID.
	 * @param string $image_size_key Image size key (or 'custom').
	 * @param string $class          Additional CSS classes.
	 * @param string $image_key      Image key for custom dimensions (optional).
	 * @param array  $settings       Settings array with custom dimensions (optional).
	 * @return string|false Image HTML or false if no image.
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
	 * Get attachment image
	 *
	 * Returns formatted image HTML with lazy loading and custom size support
	 *
	 * @param int    $image_id       Image attachment ID.
	 * @param string $image_size_key Image size key (or 'custom').
	 * @param string $class          Additional CSS classes.
	 * @param string $image_key      Image key for custom dimensions (optional).
	 * @param array  $settings       Settings array with custom dimensions (optional).
	 * @return string|false Image HTML or false if no image.
	 */
	public static function get_attachment_image_src($image_id, $image_size_key = 'full', string $image_key = '', array $settings = [])
	{
		if (empty($image_id)) {
			return false;
		}

		$size = $image_size_key;

		// Parse custom dimensions from settings
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
	 *
	 * Returns formatted list of taxonomy terms for a post
	 * Can return linked or plain text list
	 *
	 * @param int    $postID   Post ID.
	 * @param string $taxonomy  Taxonomy name (e.g. 'category', 'post_tag').
	 * @param string $delimiter Separator between terms.
	 * @param string $get       Term field to display ('name', 'slug', etc.).
	 * @param bool   $link      Whether to create links to term archives.
	 * @return string Formatted taxonomy terms list.
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
	 * Parse video ID from URL
	 *
	 * Detects YouTube or Vimeo video URLs and extracts the video ID.
	 * Returns array with vendor and ID, or ['custom', $url] for unsupported formats.
	 *
	 * @param string $url Video URL.
	 * @return array ['vendor' => 'youtube|vimeo|custom', 'id' => string]
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

		// Fallback: custom video URL
		return apply_filters('vlt_framework_video_id', ['custom', esc_url_raw($url)], $url);
	}
}
