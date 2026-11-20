<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global Color Control
 *
 * The 'global-color' field: a color of the palette (the value 'palette-3') or a custom one picked with the alpha
 * color picker (the value '#…'). The swatches show the palette's colors as it is edited
 * (assets/js/customizer-controls.js).
 */
class GlobalColorControl extends AlphaColorControl {
	public $type = 'vlt-global-color';

	/**
	 * The settings of the palette field
	 *
	 * @var string
	 */
	public $palette = '';

	/**
	 * Number of colors of the palette
	 *
	 * @var int
	 */
	public $count = 8;

	/**
	 * Name of each palette color, in their order
	 *
	 * @var array
	 */
	public $names = [];

	/**
	 * Params for the JS control
	 */
	public function to_json() {
		parent::to_json();

		$this->json['palette'] = $this->palette;
	}

	/**
	 * Label row, the palette swatches and the custom color
	 */
	public function render_content() {
		?>
		<?php if ( $this->label ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php endif; ?>

		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>

		<div class="vlt-global-color">
			<?php for ( $i = 1; $i <= $this->count; $i++ ) : ?>
				<?php
				// translators: %d: number of the palette color
				$name = $this->names[ $i - 1 ] ?? sprintf( __( 'Color %d', '@@textdomain' ), $i );
				?>
				<button type="button" class="vlt-global-color__swatch" data-value="<?php echo esc_attr( 'palette-' . $i ); ?>" data-key="<?php echo esc_attr( 'color' . $i ); ?>" title="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $name ); ?>" aria-pressed="false"></button>
			<?php endfor; ?>

			<input type="text" class="vlt-color-picker-alpha vlt-global-color__custom" data-alpha-enabled="true" data-alpha-color-type="hex" data-name="<?php esc_attr_e( 'Custom color', '@@textdomain' ); ?>" value="" />
		</div>
		<?php
	}
}
