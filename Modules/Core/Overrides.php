<?php

namespace VLT\Framework\Modules\Core;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page overrides of Customizer fields
 *
 * A field queued with 'override' => true shows in the "Page Settings" box of the post types of
 * vlt_fw_customizer_override_post_types (post and page by default). Each field there inherits the Customizer
 * value or overrides it for that page; the overrides are kept together in the post meta _vlt_overrides
 * [ settings => value ]. On that page Customizer::get_option() / get_theme_mod() give the override, and the
 * field's CSS output follows it.
 *
 *     Customizer::add_field( [ 'type' => 'select', 'settings' => 'navigation_type', 'override' => true, … ] );
 *     vlt_fw_get_theme_mod( 'navigation_type' ); // this page's override, else the Customizer value
 *
 * Filters:
 * - vlt_fw_customizer_override_post_types: the post types with the box;
 * - vlt_fw_customizer_override_post_id: the post whose overrides the page shown takes (the singular post; a theme
 *   can add e.g. the shop page);
 * - vlt_fw_customizer_overrides: a post's overrides, e.g. to read older values.
 */
class Overrides {
	/**
	 * Post meta of the overrides
	 */
	const META = '_vlt_overrides';

	/**
	 * Nonce of the box
	 */
	const NONCE = 'vlt_overrides_nonce';

	/**
	 * Field types the box can show
	 */
	const TYPES = [ 'select', 'radio', 'checkbox', 'text', 'textarea', 'url', 'email', 'number', 'dimension', 'color', 'image' ];

	/**
	 * The Customizer module (sanitize callbacks of the fields)
	 *
	 * @var Customizer
	 */
	private $customizer;

	/**
	 * Overrides by post [ post id => [ settings => value ] ]
	 *
	 * @var array
	 */
	private static $cache = [];

	public function __construct( Customizer $customizer ) {
		$this->customizer = $customizer;
	}

	/**
	 * Hooks
	 */
	public function register() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post', [ $this, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Post types with the box
	 */
	public static function post_types() {
		return (array) apply_filters( 'vlt_fw_customizer_override_post_types', [ 'post', 'page' ] );
	}

	/**
	 * The post whose overrides the page shown takes: the singular post, 0 elsewhere (and in the admin, before the
	 * query)
	 */
	public static function post_id() {
		if ( is_admin() || !did_action( 'wp' ) ) {
			return 0;
		}

		return (int) apply_filters( 'vlt_fw_customizer_override_post_id', is_singular() ? (int) get_queried_object_id() : 0 );
	}

	/**
	 * A post's overrides [ settings => value ]
	 */
	public static function get( $post_id ) {
		$post_id = (int) $post_id;

		if ( !$post_id ) {
			return [];
		}

		if ( !isset( self::$cache[ $post_id ] ) ) {
			$overrides = get_post_meta( $post_id, self::META, true );

			self::$cache[ $post_id ] = (array) apply_filters( 'vlt_fw_customizer_overrides', is_array( $overrides ) ? $overrides : [], $post_id );
		}

		return self::$cache[ $post_id ];
	}

	/**
	 * The override of a field on a post (the page shown by default)
	 *
	 * @param string   $name    Setting
	 * @param bool     $found   Set: whether the post overrides it
	 * @param int|null $post_id The post
	 *
	 * @return mixed null when not found
	 */
	public static function value( $name, &$found = false, $post_id = null ) {
		$found = false;

		if ( !isset( Customizer::get_overridable_fields()[ $name ] ) ) {
			return null;
		}

		$overrides = self::get( null === $post_id ? self::post_id() : $post_id );

		if ( !array_key_exists( $name, $overrides ) ) {
			return null;
		}

		$found = true;

		return $overrides[ $name ];
	}

	/**
	 * Whether the page shown overrides a field
	 */
	public static function has( $name ) {
		$found = false;
		self::value( $name, $found );

		return $found;
	}

	/**
	 * The box, on the post types with overridable fields
	 */
	public function add_meta_box( $post_type ) {
		if ( !Customizer::get_overridable_fields() || !in_array( $post_type, self::post_types(), true ) ) {
			return;
		}

		add_meta_box( 'vlt-overrides', esc_html__( 'Page Settings', '@@textdomain' ), [ $this, 'render' ], $post_type, 'side', 'default' );
	}

	/**
	 * The fields by Customizer section, as the editor's panels (PanelBody): a section is a panel opening on its
	 * title, its fields one bordered group (ItemGroup); all closed at first (overrides.js reopens the ones opened)
	 */
	public function render( $post ) {
		$overrides = self::get( $post->ID );
		$sections  = [];
		$values    = [];

		foreach ( Customizer::get_overridable_fields() as $id => $field ) {
			$sections[ $field['section'] ?? '' ][ $id ] = $field;
			$values[ $id ] = Customizer::get_saved( $id );
		}

		$opened = [];

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<div class="vlt-overrides" data-values="<?php echo esc_attr( wp_json_encode( $values ) ); ?>">
			<?php foreach ( $sections as $section => $fields ) : ?>
				<?php
				$open     = in_array( $section, $opened, true );
				$panel_id = 'vlt-overrides-panel-' . $section;
				?>
				<div class="vlt-overrides__panel<?php echo $open ? ' is-opened' : ''; ?>" data-panel="<?php echo esc_attr( $section ); ?>">
					<h3 class="vlt-overrides__panel-title">
						<button type="button" class="vlt-overrides__panel-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
							<span class="vlt-overrides__panel-name"><?php echo esc_html( Customizer::get_section( $section )['title'] ?? $section ); ?></span>
							<span class="vlt-overrides__panel-count"></span>
							<span class="vlt-overrides__panel-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>
					</h3>

					<div class="vlt-overrides__panel-body" id="<?php echo esc_attr( $panel_id ); ?>"<?php echo $open ? '' : ' hidden'; ?>>
						<div class="vlt-overrides__group">
							<?php
							foreach ( $fields as $id => $field ) {
								$this->render_field( $id, $field, $values[ $id ], $overrides );
							}
							?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="vlt-overrides__footer">
				<span class="vlt-overrides__count" data-label="<?php esc_attr_e( 'overridden', '@@textdomain' ); ?>"></span>
				<button type="button" class="button-link vlt-overrides__reset"><?php esc_html_e( 'Reset all', '@@textdomain' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * One field. Inheriting: one line, its label and the Customizer value, a click overrides it. Overridden: its
	 * label, Revert, its control and the Customizer value under it. The control is disabled while the field
	 * inherits, so it is not sent. Its dot, as in the Customizer: the page's value is not the default.
	 */
	private function render_field( $id, $field, $inherited, $overrides ) {
		$overridden = array_key_exists( $id, $overrides );
		$value      = $overridden ? $overrides[ $id ] : $inherited;
		$input_id   = 'vlt-override-' . $id;
		$label      = wp_strip_all_tags( $field['label'] ?? $id );
		$shown      = $this->display_value( $field, $inherited );
		$rules      = is_array( $field['active_callback'] ?? null ) ? array_values( $field['active_callback'] ) : [];
		$default    = Customizer::get_default_options()[ $id ] ?? '';
		?>
		<div class="vlt-overrides__field<?php echo $overridden ? ' is-overridden' : ''; ?>" data-setting="<?php echo esc_attr( $id ); ?>" data-type="<?php echo esc_attr( $field['type'] ?? 'text' ); ?>" data-rules="<?php echo esc_attr( wp_json_encode( $rules ) ); ?>" data-default="<?php echo esc_attr( wp_json_encode( $default ) ); ?>">
			<input type="checkbox" class="vlt-overrides__toggle" hidden <?php checked( $overridden ); ?>>

			<button type="button" class="vlt-overrides__summary" title="<?php esc_attr_e( 'Override for this page', '@@textdomain' ); ?>">
				<span class="vlt-overrides__name"><?php echo esc_html( $label ); ?></span>
				<span class="vlt-overrides__value"><?php echo wp_kses_post( $shown ); ?></span>
				<span class="vlt-overrides__edit dashicons dashicons-edit" aria-hidden="true"></span>
			</button>

			<div class="vlt-overrides__editor">
				<div class="vlt-overrides__head">
					<label class="vlt-overrides__label" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
					<button type="button" class="vlt-overrides__revert dashicons dashicons-image-rotate" title="<?php esc_attr_e( 'Use the Customizer value', '@@textdomain' ); ?>" aria-label="<?php esc_attr_e( 'Use the Customizer value', '@@textdomain' ); ?>"></button>
				</div>

				<div class="vlt-overrides__control">
					<?php $this->render_control( $id, $field, $value, $input_id, !$overridden ); ?>
				</div>

				<div class="vlt-overrides__inherit">
					<?php esc_html_e( 'Customizer:', '@@textdomain' ); ?>
					<span><?php echo wp_kses_post( $shown ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * The control of a field type, named vlt_overrides[settings]
	 */
	private function render_control( $id, $field, $value, $input_id, $disabled ) {
		$name     = 'vlt_overrides[' . $id . ']';
		$type     = $field['type'] ?? 'text';
		$choices  = is_array( $field['choices'] ?? null ) ? $field['choices'] : [];
		$value    = is_scalar( $value ) ? (string) $value : '';
		$attrs    = 'name="' . esc_attr( $name ) . '" class="vlt-overrides__input"' . disabled( $disabled, true, false );

		switch ( $type ) {
			case 'select':
				echo '<select id="' . esc_attr( $input_id ) . '" ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				foreach ( $choices as $choice => $label ) {
					echo '<option value="' . esc_attr( $choice ) . '"' . selected( $value, (string) $choice, false ) . '>' . esc_html( $label ) . '</option>';
				}

				echo '</select>';

				break;

			case 'radio':
				foreach ( $choices as $choice => $label ) {
					echo '<label class="vlt-overrides__radio"><input type="radio" value="' . esc_attr( $choice ) . '" ' . $attrs . checked( $value, (string) $choice, false ) . '> ' . esc_html( $label ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}

				break;

			case 'checkbox':
				// Off is sent too: the hidden '' before the box
				echo '<input type="hidden" value="" ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<label class="vlt-overrides__checkbox"><input type="checkbox" id="' . esc_attr( $input_id ) . '" value="1" ' . $attrs . checked( rest_sanitize_boolean( $value ), true, false ) . '> ' . esc_html__( 'On', '@@textdomain' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				break;

			case 'textarea':
				echo '<textarea id="' . esc_attr( $input_id ) . '" rows="3" ' . $attrs . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				break;

			case 'color':
				$alpha = empty( $field['choices']['alpha'] ) ? '' : ' data-alpha-enabled="true" data-alpha-color-type="rgba"';

				echo '<input type="text" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( $value ) . '" data-color' . $alpha . ' ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				break;

			case 'image':
				$url = is_numeric( $value ) ? wp_get_attachment_image_url( (int) $value, 'medium' ) : $value;
				?>
				<div class="vlt-overrides__image">
					<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $value ); ?>" data-save-as="<?php echo esc_attr( 'id' === ( $field['save_as'] ?? '' ) ? 'id' : 'url' ); ?>" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span class="vlt-overrides__image-preview"><?php echo $url ? '<img src="' . esc_url( $url ) . '" alt="">' : ''; ?></span>
					<button type="button" class="button vlt-overrides__image-select"><?php esc_html_e( 'Select Image', '@@textdomain' ); ?></button>
					<button type="button" class="button-link vlt-overrides__image-remove"><?php esc_html_e( 'Remove', '@@textdomain' ); ?></button>
				</div>
				<?php
				break;

			case 'number':
				$step = $field['step'] ?? $field['input_attrs']['step'] ?? 1;
				$min  = $field['min'] ?? $field['input_attrs']['min'] ?? '';
				$max  = $field['max'] ?? $field['input_attrs']['max'] ?? '';
				$unit = $field['unit'] ?? ( (array) ( $field['units'] ?? [ '' ] ) )[0];

				echo '<span class="vlt-overrides__number"><input type="number" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( $value ) . '" step="' . esc_attr( $step ) . '"' . ( '' === $min ? '' : ' min="' . esc_attr( $min ) . '"' ) . ( '' === $max ? '' : ' max="' . esc_attr( $max ) . '"' ) . ' ' . $attrs . '>' . ( $unit ? '<span class="vlt-overrides__unit">' . esc_html( $unit ) . '</span>' : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				break;

			default:
				// text, url, email, dimension (a CSS value: 18px, 80vh)
				$input_type = in_array( $type, [ 'url', 'email' ], true ) ? $type : 'text';
				$hint       = 'dimension' === $type ? ' placeholder="' . esc_attr( implode( ', ', (array) ( $field['units'] ?? Customizer::UNITS ) ) ) . '"' : '';

				echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( $value ) . '"' . $hint . ' ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * The Customizer value as text (a choice's label, On / Off, a swatch, a thumbnail)
	 */
	private function display_value( $field, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $field['type'] ?? 'text' ) {
			case 'select':
			case 'radio':
				return esc_html( $field['choices'][ $value ] ?? ( '' === $value ? '—' : $value ) );

			case 'checkbox':
				return rest_sanitize_boolean( $value ) ? esc_html__( 'On', '@@textdomain' ) : esc_html__( 'Off', '@@textdomain' );

			case 'color':
				return '' === $value ? '—' : '<span class="vlt-overrides__swatch" style="background:' . esc_attr( $value ) . '"></span>' . esc_html( $value );

			case 'image':
				$url = is_numeric( $value ) ? wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : $value;

				return $url ? '<img class="vlt-overrides__thumb" src="' . esc_url( $url ) . '" alt="">' : esc_html__( 'None', '@@textdomain' );

			default:
				return '' === $value ? '—' : esc_html( $value . ( 'number' === ( $field['type'] ?? '' ) ? ( $field['unit'] ?? '' ) : '' ) );
		}
	}

	/**
	 * Save the fields switched to Override, each sanitized as in the Customizer; the others inherit
	 */
	public function save( $post_id ) {
		if ( !isset( $_POST[ self::NONCE ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || !current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$posted    = isset( $_POST['vlt_overrides'] ) && is_array( $_POST['vlt_overrides'] ) ? wp_unslash( $_POST['vlt_overrides'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below
		$overrides = [];

		foreach ( Customizer::get_overridable_fields() as $id => $field ) {
			if ( !array_key_exists( $id, $posted ) || !is_scalar( $posted[ $id ] ) ) {
				continue;
			}

			$callback = $this->customizer->get_sanitize_callback( $field );
			$value    = is_callable( $callback ) ? call_user_func( $callback, $posted[ $id ] ) : sanitize_text_field( $posted[ $id ] );

			// A select or radio takes one of its choices only
			if ( in_array( $field['type'] ?? '', [ 'select', 'radio' ], true ) && !array_key_exists( (string) $value, (array) ( $field['choices'] ?? [] ) ) ) {
				continue;
			}

			$overrides[ $id ] = $value;
		}

		// Saved even when empty: the post then inherits everything, older values included
		update_post_meta( $post_id, self::META, $overrides );
		unset( self::$cache[ $post_id ] );
	}

	/**
	 * The box's script and styles, on the edit screens of its post types
	 */
	public function enqueue( $hook ) {
		if ( !in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || !Customizer::get_overridable_fields() ) {
			return;
		}

		$screen = get_current_screen();

		if ( !$screen || !in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'vlt-wp-color-picker-alpha', VLT_FW_URL . 'assets/js/vendor/wp-color-picker-alpha.min.js', [ 'wp-color-picker' ], '3.0.4', true );

		// Versioned by the file: a change reaches the editor, not a cached copy
		wp_enqueue_style( 'vlt-overrides', VLT_FW_URL . 'assets/css/overrides.css', [], (string) filemtime( VLT_FW_PATH . 'assets/css/overrides.css' ) );
		wp_enqueue_script( 'vlt-overrides', VLT_FW_URL . 'assets/js/overrides.js', [ 'jquery', 'wp-color-picker', 'vlt-wp-color-picker-alpha' ], (string) filemtime( VLT_FW_PATH . 'assets/js/overrides.js' ), true );
	}
}
