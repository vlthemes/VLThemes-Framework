<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Divider Control
 */
class DividerControl extends \WP_Customize_Control {
	public $type = 'vlt-divider';

	/**
	 * Render the control content
	 */
	public function render_content() {
		?>
		<div class="vlt-divider-control">
			<?php if ( $this->label ) : ?>
				<span class="vlt-divider-control__label"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>
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
