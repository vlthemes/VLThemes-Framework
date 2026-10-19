<?php

namespace VLT\Framework\Modules\Core\Controls;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value Control
 *
 * A value with a ruler (the 'dimension' and 'number' fields): a number with a unit, or a CSS function or keyword
 * picked from the unit menu. Drag the ruler under the field or use its arrows (Shift: by 10).
 * The field is built by assets/js/customizer-controls.js from the params below.
 *
 * - dimension: a CSS value, '18px', '80vh', '300ms' (units, functions, keywords).
 * - number (numeric): a number in units[0]; ms can be shown in s (units [ 'ms', 's' ]), the value stays in ms.
 */
class ValueControl extends \WP_Customize_Control {
	public $type = 'vlt-value';

	/**
	 * Units, the first one is the default
	 *
	 * @var array
	 */
	public $units = [];

	/**
	 * CSS functions offered in the unit menu (clamp, min, max, calc, var)
	 *
	 * @var array
	 */
	public $functions = [];

	/**
	 * Keywords offered in the unit menu (auto, none, …)
	 *
	 * @var array
	 */
	public $keywords = [];

	/**
	 * The value is a number in units[0]
	 *
	 * @var bool
	 */
	public $numeric = false;

	/**
	 * Limits and step of the number, in units[0]
	 *
	 * @var float|null
	 */
	public $min  = null;
	public $max  = null;
	public $step = 1;

	/**
	 * Params for the JS field
	 */
	public function to_json() {
		parent::to_json();

		$this->json['units']     = $this->units;
		$this->json['functions'] = $this->functions;
		$this->json['keywords']  = $this->keywords;
		$this->json['numeric']   = $this->numeric;
		$this->json['min']       = $this->min;
		$this->json['max']       = $this->max;
		$this->json['step']      = $this->step;
	}

	/**
	 * Label row and the input the setting is linked to; the JS field takes its place
	 */
	public function render_content() {
		?>
		<?php if ( $this->label ) : ?>
			<label class="customize-control-title" for="<?php echo esc_attr( '_customize-input-' . $this->id ); ?>"><?php echo esc_html( $this->label ); ?></label>
		<?php endif; ?>

		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>

		<div class="vlt-value">
			<input type="hidden" class="vlt-value__setting" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>">
		</div>
		<?php
	}

	/**
	 * No JS template — rendered from PHP above
	 */
	public function content_template() {}
}
