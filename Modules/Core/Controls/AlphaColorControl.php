<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alpha Color Control
 *
 * Color picker with alpha/transparency channel support, using
 * https://github.com/kallookoo/wp-color-picker-alpha to extend the
 * native wp-color-picker.
 */
class AlphaColorControl extends \WP_Customize_Control {
	public $type = 'vlt-color-alpha';
	public $statuses;

	public function __construct( $manager, $id, $args = [] ) {
		$this->statuses = [ '' => __( 'Default' ) ];

		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Enqueue the color picker + alpha extension
	 */
	public function enqueue() {
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_script(
			'vlt-wp-color-picker-alpha',
			VLT_FW_URL . 'assets/js/vendor/wp-color-picker-alpha.min.js',
			[ 'wp-color-picker' ],
			'3.0.4',
			true
		);
	}

	/**
	 * Render the control content (PHP, not the JS template WP core color
	 * control uses, so we can attach the alpha data attributes)
	 */
	public function render_content() {
		$default_color = $this->setting->default;
		?>
		<label>
			<?php if ( $this->label ) : ?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<?php if ( $this->description ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>

			<div class="customize-control-content">
				<input
					type="text"
					class="vlt-color-picker-alpha"
					data-alpha-enabled="true"
					data-default-color="<?php echo esc_attr( $default_color ); ?>"
					<?php $this->link(); ?>
					value="<?php echo esc_attr( $this->value() ); ?>"
				/>
			</div>
		</label>
		<?php
	}

	/**
	 * No JS template — rendered entirely from PHP above
	 */
	public function content_template() {}
}
