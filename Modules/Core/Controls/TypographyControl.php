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

		$groups    = $this->get_font_groups();
		$fonts     = $this->get_fonts();
		$is_google = empty( $family ) || 'google' === ( $fonts[ $family ]['source'] ?? 'google' );
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
					<option value="" data-source="google"><?php esc_html_e( 'Default', '@@textdomain' ); ?></option>
					<?php foreach ( $groups as $group ) : ?>
						<?php if ( !empty( $group['fonts'] ) ) : ?>
							<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
								<?php foreach ( $group['fonts'] as $font_family => $font ) : ?>
									<option value="<?php echo esc_attr( $font_family ); ?>" data-source="<?php echo esc_attr( $font['source'] ?? 'google' ); ?>" <?php selected( $family, $font_family ); ?>>
										<?php echo esc_html( $font_family ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</span>

			<span class="vlt-typography-control__variants" <?php echo $is_google ? '' : 'style="display:none;"'; ?>>
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
	 * Get the fonts list flattened (family => {family, category, variants, source}),
	 * used to look up variants for the currently selected family.
	 */
	private function get_fonts() {
		$fonts = [];

		foreach ( $this->get_font_groups() as $group ) {
			$fonts = array_merge( $fonts, $group['fonts'] );
		}

		return $fonts;
	}

	/**
	 * Get fonts grouped by source: Google Fonts plus any fonts registered
	 * by the toolkit plugin (custom fonts, TypeKit, theme fonts) via the
	 * `vlt_toolkit_fonts_list` filter. Each group is rendered as its own
	 * <optgroup> so users can tell Google fonts apart from custom ones.
	 *
	 * @return array list of ['label' => string, 'fonts' => family => {family, category, variants, source}]
	 */
	public static function get_font_groups() {
		static $groups = null;

		if ( null !== $groups ) {
			return $groups;
		}

		$groups = [
			'google' => [
				'label' => esc_html__( 'Google Fonts', '@@textdomain' ),
				'fonts' => self::get_google_fonts(),
			],
		];

		foreach ( self::get_toolkit_font_groups() as $category_id => $group ) {
			$groups[ $category_id ] = $group;
		}

		return $groups;
	}

	/**
	 * Load the framework's Google Fonts JSON list
	 */
	private static function get_google_fonts() {
		$fonts = [];

		$file = apply_filters( 'vlt_fw_google_fonts_json', VLT_FW_PATH . 'assets/fonts/google-fonts.json' );

		if ( file_exists( $file ) ) {
			$data = json_decode( file_get_contents( $file ), true );

			if ( !empty( $data['items'] ) && is_array( $data['items'] ) ) {
				foreach ( $data['items'] as $family => $font ) {
					$font['source'] = 'google';
					$fonts[ $family ] = $font;
				}
			}
		}

		return $fonts;
	}

	/**
	 * Get fonts registered by the toolkit plugin (custom fonts, TypeKit,
	 * theme fonts) via `vlt_toolkit_fonts_list`, grouped by their category
	 * and normalized to the same shape as the Google Fonts list. Each font
	 * is tagged with its category id as `source` so non-Google fonts can
	 * be told apart (their weights are fixed, not user-selectable).
	 *
	 * @return array list of ['label' => string, 'fonts' => family => {family, category, variants, source}]
	 */
	private static function get_toolkit_font_groups() {
		$fonts_list = apply_filters( 'vlt_toolkit_fonts_list', [] );

		if ( empty( $fonts_list['families'] ) ) {
			return [];
		}

		$groups = [];

		foreach ( $fonts_list['families'] as $category_id => $category_data ) {
			if ( empty( $category_data['children'] ) ) {
				continue;
			}

			$groups[ $category_id ] = [
				'label' => $category_data['text'] ?? $category_id,
				'fonts' => [],
			];

			foreach ( $category_data['children'] as $font ) {
				$font_id = $font['id'];

				$groups[ $category_id ]['fonts'][ $font_id ] = [
					'family'   => $font['text'] ?? $font_id,
					'category' => $category_data['text'] ?? $category_id,
					'variants' => [],
					'source'   => $category_id,
				];
			}
		}

		return $groups;
	}
}
