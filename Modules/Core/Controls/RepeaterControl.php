<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repeater Control
 *
 * List of rows, each row built from the same set of sub-fields
 * (text, textarea, url, select, image). Value is stored as an array of rows:
 * [ [ 'field_key' => 'value', ... ], ... ] — image sub-fields hold attachment IDs.
 * The UI is rendered in JS (assets/js/customizer-controls.js).
 */
class RepeaterControl extends \WP_Customize_Control {
	public $type = 'vlt-repeater';

	/**
	 * Sub-fields: key => [ type, label, description, choices, default ]
	 */
	public $fields = [];

	/**
	 * Row label: [ 'type' => 'text'|'field', 'value' => '...', 'field' => 'field_key' ]
	 */
	public $row_label = [];

	public $button_label = '';

	/**
	 * Enqueue media library and sortable for image sub-fields and reordering
	 */
	public function enqueue() {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Pass control params to JS
	 */
	public function to_json() {
		parent::to_json();

		$this->json['fields']       = $this->fields;
		$this->json['row_label']    = $this->row_label;
		$this->json['button_label'] = $this->button_label ?: __( 'Add new row', '@@textdomain' );
		$this->json['value']        = self::normalize_value( $this->value() );
	}

	/**
	 * Render the control content (rows are built in JS)
	 */
	public function render_content() {
		?>
		<div class="vlt-repeater-control">
			<?php if ( $this->label ) : ?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<?php if ( $this->description ) : ?>
				<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
			<?php endif; ?>

			<ul class="vlt-repeater-control__rows"></ul>

			<button type="button" class="button vlt-repeater-control__add"></button>
		</div>
		<?php
	}

	/**
	 * No JS template — container rendered from PHP above
	 */
	public function content_template() {}

	/**
	 * Convert a stored value (array, JSON string or legacy url-encoded
	 * JSON string) into a list of rows
	 */
	public static function normalize_value( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			if ( !is_array( $decoded ) ) {
				$decoded = json_decode( urldecode( $value ), true );
			}

			$value = $decoded;
		}

		if ( !is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_filter( $value, 'is_array' )
		);
	}
}
