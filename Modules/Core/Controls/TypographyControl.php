<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typography Control
 *
 * Renders a font-family select (sourced from the framework's Google Fonts
 * JSON list) plus a variants multiselect. Value is stored as a JSON string:
 * {"family":"Roboto","variants":["regular","700"]}
 */
class TypographyControl extends \WP_Customize_Control {
	public $type = 'vlt-typography';

	/**
	 * Render the control content
	 */
	public function render_content() {
		$value = json_decode( $this->value(), true );

		if ( !is_array( $value ) ) {
			$value = [];
		}

		$family   = $value['family'] ?? '';
		$variants = $value['variants'] ?? [];

		if ( !is_array( $variants ) ) {
			$variants = [];
		}

		$fonts = $this->get_fonts();
		?>
		<label class="vlt-typography-control">
			<?php if ( $this->label ) : ?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<?php if ( $this->description ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>

			<span class="vlt-typography-control__family">
				<select class="vlt-typography-control__family-select">
					<option value=""><?php esc_html_e( 'Default', '@@textdomain' ); ?></option>
					<?php foreach ( $fonts as $font_family => $font ) : ?>
						<option value="<?php echo esc_attr( $font_family ); ?>" <?php selected( $family, $font_family ); ?>>
							<?php echo esc_html( $font_family ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</span>

			<span class="vlt-typography-control__variants">
				<select multiple class="vlt-typography-control__variants-select" size="6">
					<?php
					$available_variants = $fonts[ $family ]['variants'] ?? [];

					foreach ( $available_variants as $variant ) :
						?>
						<option value="<?php echo esc_attr( $variant ); ?>" <?php selected( in_array( $variant, $variants, true ), true ); ?>>
							<?php echo esc_html( $variant ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="vlt-typography-control__variants-note">
					<?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) and click to select multiple weights.', '@@textdomain' ); ?>
				</span>
			</span>

			<input
				type="hidden"
				class="vlt-typography-control__value"
				<?php $this->link(); ?>
				value="<?php echo esc_attr( $this->value() ); ?>"
			/>
		</label>
		<?php
	}

	/**
	 * Get the Google Fonts list (family => {family, category, variants})
	 */
	private function get_fonts() {
		static $fonts = null;

		if ( null !== $fonts ) {
			return $fonts;
		}

		$fonts = [];

		$file = apply_filters( 'vlt_fw_google_fonts_json', VLT_FW_PATH . 'assets/fonts/google-fonts.json' );

		if ( file_exists( $file ) ) {
			$data = json_decode( file_get_contents( $file ), true );

			if ( !empty( $data['items'] ) && is_array( $data['items'] ) ) {
				$fonts = $data['items'];
			}
		}

		return $fonts;
	}
}
