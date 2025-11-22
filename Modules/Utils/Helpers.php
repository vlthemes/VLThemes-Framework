<?php

namespace VLT\Framework\Modules\Utils;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers Module
 */
class Helpers extends BaseModule {
	protected $name    = 'helpers';
	protected $version = '1.0.0';

	/**
	 * Register module
	 */
	public function register() {
	}

	/**
	 * Get post taxonomy terms
	 */
	public static function get_post_taxonomy( $post_id, $taxonomy, $delimiter = ', ', $get = 'name', $link = true ) {
		$tags = wp_get_post_terms( $post_id, $taxonomy );

		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return '';
		}

		$list = [];

		foreach ( $tags as $tag ) {
			if ( $link ) {
				$term_link = get_term_link( $tag->term_id, $taxonomy );

				if ( !is_wp_error( $term_link ) ) {
					$list[] = '<a href="' . esc_url( $term_link ) . '">' . esc_html( $tag->$get ) . '</a>';
				}
			} else {
				$list[] = esc_html( $tag->$get );
			}
		}

		$output = implode( $delimiter, $list );

		return apply_filters( 'vlt_fw_post_taxonomy', $output, $post_id, $taxonomy, $delimiter, $get, $link );
	}

	/**
	 * Parse video ID from URL
	 */
	public static function parse_video_id( $url ) {
		if ( empty( $url ) || !is_string( $url ) ) {
			return [ 'custom', '' ];
		}

		$vendors = [
			[
				'vendor'       => 'youtube',
				'pattern'      => '/(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)\/(?:embed\/|v\/|watch\?v=|watch\?list=(.*)&v=|watch\?(.*[^&]&)v=)?((\w|-){11})(&list=(\w+)&?)?/',
				'patternIndex' => 6
			],
			[
				'vendor'       => 'vimeo',
				'pattern'      => '/https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)/',
				'patternIndex' => 3
			]
		];

		foreach ( $vendors as $vendor ) {
			$video_id = false;

			if ( preg_match( $vendor['pattern'], $url, $matches ) && isset( $matches[ $vendor['patternIndex'] ] ) ) {
				$video_id = $matches[ $vendor['patternIndex'] ];
			}

			if ( $video_id ) {
				$data = [ $vendor['vendor'], $video_id ];

				return apply_filters( 'vlt_fw_video_id', $data, $url );
			}
		}

		return apply_filters( 'vlt_fw_video_id', [ 'custom', esc_url_raw( $url ) ], $url );
	}
}
