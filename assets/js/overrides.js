/* ========================================
 * Page Settings box (Modules/Core/Overrides.php): a field inherits the Customizer value (one line; a click
 * overrides it) or gives the page its own (its control; Revert inherits again); a field whose active_callback
 * fails on the page's values is hidden
 * ======================================== */
(function ($) {
	'use strict';

	$(function () {
		var $box = $('.vlt-overrides');

		if (!$box.length) {
			return;
		}

		var inherited = $box.data('values') || {};
		var $fields = $box.find('.vlt-overrides__field');
		var $count = $box.find('.vlt-overrides__count');
		var $panels = $box.find('.vlt-overrides__panel');

		// The box's title counts the overrides of the page
		var $total = $('<span class="vlt-overrides__total" aria-hidden="true"></span>').appendTo($box.closest('.postbox').find('.hndle').first());
		var storage = 'vlt-overrides-panels';

		// Panels: a Customizer section each, closed at first, opened and closed on its title; the ones opened are
		// remembered
		function setPanel($panel, open) {
			$panel.toggleClass('is-opened', open);
			$panel.find('.vlt-overrides__panel-toggle').attr('aria-expanded', open ? 'true' : 'false');
			$panel.find('.vlt-overrides__panel-body').prop('hidden', !open);
		}

		try {
			var remembered = JSON.parse(window.localStorage.getItem(storage) || 'null');

			if (Array.isArray(remembered)) {
				$panels.each(function () {
					setPanel($(this), remembered.indexOf($(this).data('panel')) !== -1);
				});
			}
		} catch (error) {}

		$box.on('click', '.vlt-overrides__panel-toggle', function () {
			var $panel = $(this).closest('.vlt-overrides__panel');

			setPanel($panel, !$panel.hasClass('is-opened'));

			try {
				window.localStorage.setItem(storage, JSON.stringify($panels.filter('.is-opened').map(function () {
					return $(this).data('panel');
				}).get()));
			} catch (error) {}
		});

		// Colors: the picker as wide as the sidebar
		$box.find('input[data-color]').each(function () {
			$(this).wpColorPicker({
				// The editor's width: the box less its side padding (16px)
				width: Math.max(180, Math.min(255, $box.width() - 16)),
				change: function () {
					setTimeout(update, 1);
				},
				clear: function () {
					setTimeout(update, 1);
				}
			});
		});

		// Images: the attachment ID or its URL, as the field saves it
		$box.on('click', '.vlt-overrides__image-select', function () {
			var $image = $(this).closest('.vlt-overrides__image');
			var $input = $image.find('.vlt-overrides__input');
			var frame = wp.media({ multiple: false, library: { type: 'image' } });

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var preview = (attachment.sizes && (attachment.sizes.medium || attachment.sizes.full) || attachment).url;

				$input.val('id' === $input.data('save-as') ? attachment.id : attachment.url).trigger('change');
				$image.find('.vlt-overrides__image-preview').html($('<img alt="">').attr('src', preview));
			});

			frame.open();
		});

		$box.on('click', '.vlt-overrides__image-remove', function () {
			var $image = $(this).closest('.vlt-overrides__image');

			$image.find('.vlt-overrides__input').val('').trigger('change');
			$image.find('.vlt-overrides__image-preview').empty();
		});

		// Overridden: the control is sent; inheriting: disabled, so it is not
		function setOverride($field, on) {
			$field.toggleClass('is-overridden', on);
			$field.find('.vlt-overrides__toggle').prop('checked', on);
			$field.find('.vlt-overrides__input').prop('disabled', !on);
			$field.find('.vlt-overrides__image button, .wp-color-result, .wp-picker-clear').prop('disabled', !on);
		}

		$fields.each(function () {
			setOverride($(this), $(this).hasClass('is-overridden'));
		});

		$box.on('click', '.vlt-overrides__summary', function () {
			var $field = $(this).closest('.vlt-overrides__field');

			setOverride($field, true);
			update();

			// A color: its swatch button, the picker opens on a click (focusing its input would open it)
			$field.find('.wp-color-result, .vlt-overrides__input:visible, .vlt-overrides__image-select').first().trigger('focus');
		});

		$box.on('click', '.vlt-overrides__revert', function () {
			var $field = $(this).closest('.vlt-overrides__field');

			setOverride($field, false);
			update();
			$field.find('.vlt-overrides__summary').trigger('focus');
		});

		$box.on('click', '.vlt-overrides__reset', function () {
			$fields.each(function () {
				setOverride($(this), false);
			});

			update();
		});

		// The value a field has on this page: its control while overridden, else the Customizer's
		function pageValue(setting) {
			var $field = $fields.filter('[data-setting="' + setting + '"]');

			if (!$field.length || !$field.hasClass('is-overridden')) {
				return undefined !== inherited[setting] ? inherited[setting] : null;
			}

			switch ($field.data('type')) {
				case 'checkbox':
					return $field.find('input[type="checkbox"].vlt-overrides__input').is(':checked');
				case 'radio':
					return $field.find('.vlt-overrides__input:checked').val();
				default:
					return $field.find('.vlt-overrides__input').last().val();
			}
		}

		// Customizer::rules_match() on the page's values
		function rulesMatch(rules) {
			return rules.every(function (rule) {
				var value = pageValue(rule.setting);
				var compare = rule.value;

				switch (rule.operator || '==') {
					case '!=':
						return value != compare; // eslint-disable-line eqeqeq
					case '>':
						return value > compare;
					case '>=':
						return value >= compare;
					case '<':
						return value < compare;
					case '<=':
						return value <= compare;
					case 'contains':
						return Array.isArray(value) ? value.indexOf(compare) !== -1 : String(value).indexOf(compare) !== -1;
					default:
						return value == compare; // eslint-disable-line eqeqeq
				}
			});
		}

		// A field's default: data-default is JSON (jQuery's data() keeps a JSON string's quotes)
		function fieldDefault(field) {
			try {
				return JSON.parse(field.getAttribute('data-default'));
			} catch (error) {
				return '';
			}
		}

		// Same value: '18' is 18, an unset value is '' (customizer-controls.js sameValue())
		function sameValue(a, b) {
			var normalize = function (value) {
				if (null === value || undefined === value || false === value) {
					return '';
				}

				return true === value ? '1' : String(value);
			};

			return normalize(a) === normalize(b);
		}

		// The fields shown, their dot (the page's value is not the default), and the overrides counted in the
		// footer and on each panel's title
		function update() {
			$fields.each(function () {
				var $field = $(this);
				var rules = $field.data('rules') || [];

				this.hidden = rules.length > 0 && !rulesMatch(rules);
				$field.toggleClass('is-changed', !sameValue(pageValue($field.data('setting')), fieldDefault(this)));
			});

			var overridden = $fields.filter('.is-overridden').length;

			$count.text(overridden ? overridden + ' ' + $count.data('label') : '');
			$total.text(overridden ? overridden : '');
			$box.find('.vlt-overrides__reset').prop('hidden', !overridden);

			$panels.each(function () {
				var n = $(this).find('.vlt-overrides__field.is-overridden').length;

				$(this).find('.vlt-overrides__panel-count').text(n ? n : '');
			});
		}

		$box.on('change input', '.vlt-overrides__input', update);

		update();
	});
})(jQuery);
