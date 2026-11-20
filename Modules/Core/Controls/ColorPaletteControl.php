<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color Palette Control
 *
 * The 'color-palette' field: a row of swatches, each opening the alpha color picker (AlphaColorControl's), and
 * the palettes (presets) that fill all the colors at once. The value is JSON, {"color1":"#…","color2":"#…",…}.
 * assets/js/customizer-controls.js builds the pickers and writes the value.
 */
class ColorPaletteControl extends AlphaColorControl {
	public $type = 'vlt-color-palette';

	/**
	 * Number of colors
	 *
	 * @var int
	 */
	public $count = 8;

	/**
	 * Name of each color, in their order
	 *
	 * @var array
	 */
	public $names = [];

	/**
	 * Palettes offered: [ id => [ 'label' => …, 'colors' => [ '#…', … ] ] ]
	 *
	 * @var array
	 */
	public $presets = [];

	/**
	 * Params for the JS control
	 */
	public function to_json() {
		parent::to_json();

		$this->json['count'] = $this->count;
	}

	/**
	 * Label row, the swatches and the palettes
	 */
	public function render_content() {
		$value   = json_decode( (string) $this->value(), true );
		$default = json_decode( (string) $this->setting->default, true );
		$value   = is_array( $value ) ? $value : [];
		$default = is_array( $default ) ? $default : [];
		?>
		<?php if ( $this->label ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php endif; ?>

		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>

		<div class="vlt-palette">

			<div class="vlt-palette__colors">
				<?php for ( $i = 1; $i <= $this->count; $i++ ) : ?>
					<input
						type="text"
						class="vlt-color-picker-alpha vlt-palette__input"
						data-alpha-enabled="true"
						data-alpha-color-type="hex"
						data-key="<?php echo esc_attr( 'color' . $i ); ?>"
						data-name="<?php echo esc_attr( $this->names[ $i - 1 ] ?? '' ); ?>"
						data-default-color="<?php echo esc_attr( $default[ 'color' . $i ] ?? '' ); ?>"
						value="<?php echo esc_attr( $value[ 'color' . $i ] ?? '' ); ?>"
					/>
				<?php endfor; ?>
			</div>

			<?php if ( $this->presets ) : ?>
				<span class="vlt-palette__presets-title"><?php esc_html_e( 'Palettes', '@@textdomain' ); ?></span>

				<div class="vlt-palette__presets">
					<?php foreach ( $this->presets as $id => $preset ) : ?>
						<button type="button" class="vlt-palette__preset" data-preset="<?php echo esc_attr( $id ); ?>" data-colors="<?php echo esc_attr( wp_json_encode( $preset['colors'] ) ); ?>" aria-pressed="false">
							<span class="vlt-palette__preset-colors" aria-hidden="true">
								<?php foreach ( $preset['colors'] as $color ) : ?>
									<span style="<?php echo esc_attr( 'background-color:' . $color ); ?>"></span>
								<?php endforeach; ?>
							</span>
							<span class="vlt-palette__preset-label"><?php echo esc_html( $preset['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}
}
