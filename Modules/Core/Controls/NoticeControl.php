<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notice Control
 *
 * Renders an arbitrary HTML notice/callout inside a customizer section.
 * The HTML comes from the field's 'default' value (kses-filtered).
 *
 * Supported 'notice_type' values: info (default), success, warning, error.
 */
class NoticeControl extends \WP_Customize_Control {
	public $type = 'vlt-notice';
	public $notice_type = 'info';
	public $content = '';

	/**
	 * Render the control content
	 */
	public function render_content() {
		$type = in_array( $this->notice_type, [ 'info', 'success', 'warning', 'error' ], true )
			? $this->notice_type
			: 'info';
		?>
		<div class="vlt-notice-control vlt-notice-control--<?php echo esc_attr( $type ); ?>">
			<?php echo wp_kses_post( $this->content ); ?>
		</div>
		<?php
	}

	/**
	 * No setting is rendered for a purely visual control
	 */
	public function to_json() {
		parent::to_json();

		$this->json['value'] = '';
	}
}
