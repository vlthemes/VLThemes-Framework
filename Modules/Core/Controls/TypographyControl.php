<?php

namespace VLT\Framework\Modules\Core\Controls;

use VLT\Framework\Modules\Core\Customizer;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typography Control
 *
 * Renders a font-family select (sourced from the framework's Google Fonts
 * JSON list) plus a variants multiselect. With properties, them too: the
 * weight next to the family, a bar of toggles (italic, case, underline,
 * strikethrough) and the lengths as value fields (assets/js/customizer-controls.js),
 * two to a row; the variants then give way to the weight when it is one of
 * them. Value is stored as a JSON string:
 * {"family":"Roboto","variants":["regular","700"],"font-size":"18px","font-weight":"700"}
 */
class TypographyControl extends \WP_Customize_Control {
	public $type = 'vlt-typography';

	/**
	 * Properties the control offers, in the order of Customizer::TYPOGRAPHY
	 *
	 * @var array
	 */
	public $properties = [];

	/**
	 * The font-family select (and the variants) shows; off for a field of sizes only, e.g. one for phones
	 *
	 * @var bool
	 */
	public $family = true;

	/**
	 * A dashicon before the label, e.g. dashicons-tablet
	 *
	 * @var string
	 */
	public $icon = '';

	/**
	 * The toggles: property, value and its look (a dashicon or text)
	 */
	const TOGGLES = [
		[ 'font-style', 'italic', 'dashicons-editor-italic' ],
		[ 'text-transform', 'uppercase', 'TT' ],
		[ 'text-transform', 'capitalize', 'Tt' ],
		[ 'text-transform', 'lowercase', 'tt' ],
		[ 'text-decoration', 'underline', 'dashicons-editor-underline' ],
		[ 'text-decoration', 'line-through', 'dashicons-editor-strikethrough' ],
	];

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

		$groups       = $this->get_font_groups();
		$fonts        = $this->get_fonts();
		$is_google    = empty( $family ) || 'google' === ( $fonts[ $family ]['source'] ?? 'google' );
		$has_variants = $this->family && !in_array( 'font-weight', $this->properties, true );
		$has_weight   = in_array( 'font-weight', $this->properties, true );
		$lengths      = array_values( array_intersect( [ 'font-size', 'line-height', 'letter-spacing', 'word-spacing' ], $this->properties ) );
		$toggles      = array_values( array_filter( self::TOGGLES, fn( $toggle ) => in_array( $toggle[0], $this->properties, true ) ) );
		$labels       = [
			'font-size'      => esc_html__( 'Size', '@@textdomain' ),
			'line-height'    => esc_html__( 'Line Height', '@@textdomain' ),
			'letter-spacing' => esc_html__( 'Letter Spacing', '@@textdomain' ),
			'word-spacing'   => esc_html__( 'Word Spacing', '@@textdomain' ),
		];
		?>
		<div class="vlt-typography-control">
			<?php if ( $this->label ) : ?>
				<span class="customize-control-title">
					<?php if ( $this->icon ) : ?>
						<span class="vlt-typography-control__icon dashicons <?php echo esc_attr( $this->icon ); ?>" aria-hidden="true"></span>
					<?php endif; ?>
					<?php echo esc_html( $this->label ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $this->description ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>

			<div class="vlt-typography-control__panel">
				<?php if ( $this->family || $has_weight ) : ?>
					<div class="vlt-typography-control__row">
						<?php if ( $this->family ) : ?>
							<label class="vlt-typography-control__field vlt-typography-control__family">
								<span class="vlt-typography-control__label"><?php esc_html_e( 'Font', '@@textdomain' ); ?></span>
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
							</label>
						<?php endif; ?>

						<?php if ( $has_weight ) : ?>
							<label class="vlt-typography-control__field vlt-typography-control__weight">
								<span class="vlt-typography-control__label"><?php esc_html_e( 'Weight', '@@textdomain' ); ?></span>
								<select data-property="font-weight">
									<option value=""><?php esc_html_e( 'Default', '@@textdomain' ); ?></option>
									<?php foreach ( Customizer::TYPOGRAPHY_CHOICES['font-weight'] as $choice => $choice_label ) : ?>
										<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( (string) ( $value['font-weight'] ?? '' ), (string) $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $toggles ) : ?>
					<div class="vlt-typography-control__toggles" role="group" aria-label="<?php esc_attr_e( 'Font Style', '@@textdomain' ); ?>">
						<?php foreach ( $toggles as [ $property, $toggle, $look ] ) : ?>
							<button
								type="button"
								class="vlt-typography-control__toggle"
								data-property="<?php echo esc_attr( $property ); ?>"
								data-toggle="<?php echo esc_attr( $toggle ); ?>"
								title="<?php echo esc_attr( Customizer::TYPOGRAPHY_CHOICES[ $property ][ $toggle ] ); ?>"
								aria-pressed="false"
							>
								<?php if ( str_starts_with( $look, 'dashicons-' ) ) : ?>
									<span class="dashicons <?php echo esc_attr( $look ); ?>" aria-hidden="true"></span>
								<?php else : ?>
									<span aria-hidden="true"><?php echo esc_html( $look ); ?></span>
								<?php endif; ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $lengths ) : ?>
					<div class="vlt-typography-control__lengths">
						<?php foreach ( $lengths as $property ) : ?>
							<div class="vlt-typography-control__field" data-length="<?php echo esc_attr( $property ); ?>">
								<span class="vlt-typography-control__label"><?php echo esc_html( $labels[ $property ] ); ?></span>
								<div class="vlt-value"></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $has_variants ) : ?>
					<div class="vlt-typography-control__variants" style="<?php echo esc_attr( $is_google ? '' : 'display:none;' ); ?>">
						<span class="vlt-typography-control__label"><?php esc_html_e( 'Weights to Load', '@@textdomain' ); ?></span>
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
					</div>
				<?php endif; ?>
			</div>

			<input
				type="hidden"
				class="vlt-typography-control__value"
				<?php $this->link(); ?>
				value="<?php echo esc_attr( $this->value() ); ?>"
			/>
		</div>
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
