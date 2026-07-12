/* ========================================
 * Typography Control (Vanilla JS)
 * ======================================== */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.customize) {
		return;
	}

	var fonts = window.vltFwCustomizerFonts || {};
	var icons = window.vltFwCustomizerIcons || {};
	var defaultValues = window.vltFwCustomizerDefaults || {};

	wp.customize.bind('ready', function () {
		wp.customize.control.each(function (control) {
			control.deferred.embedded.done(function () {
				var wrapper = control.container.find('.vlt-typography-control').get(0);
				if (wrapper) {
					initTypographyControl(wrapper, control);
				}
			});
		});

		applySectionIcons();
		applyPanelIcons();
		initAlphaColorPickers();
		initResetButtons();
	});

	/* ========================================
	 * Reset-to-default button
	 * ======================================== */
	function initResetButtons() {
		wp.customize.control.each(function (control) {
			control.deferred.embedded.done(function () {
				addResetButton(control);
			});
		});

		wp.customize.control.bind('add', function (control) {
			control.deferred.embedded.done(function () {
				addResetButton(control);
			});
		});
	}

	function addResetButton(control) {
		if (!control.setting || control.container.find('.vlt-reset-control').length) {
			return;
		}

		if (!Object.prototype.hasOwnProperty.call(defaultValues, control.id)) {
			return;
		}

		var title = control.container.find('.customize-control-title').first();

		if (!title.length) {
			return;
		}

		var defaultValue = defaultValues[control.id];

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'vlt-reset-control dashicons dashicons-image-rotate';
		button.setAttribute('aria-label', 'Reset to default');
		button.title = 'Reset to default';

		button.addEventListener('click', function (e) {
			e.preventDefault();
			control.setting.set(defaultValue);
		});

		title.get(0).appendChild(button);
		title.get(0).classList.add('vlt-has-reset-control');
	}

	/* ========================================
	 * Alpha Color Picker
	 * (https://github.com/kallookoo/wp-color-picker-alpha)
	 * ======================================== */
	function initAlphaColorPickers() {
		if (typeof jQuery === 'undefined' || !jQuery.fn.wpColorPicker) {
			return;
		}

		wp.customize.control.each(function (control) {
			if (control.params.type !== 'vlt-color-alpha') {
				return;
			}

			control.deferred.embedded.done(function () {
				var input = jQuery(control.container.find('.vlt-color-picker-alpha'));

				if (!input.length || input.data('wpWpColorPicker')) {
					return;
				}

				input.wpColorPicker({
					change: function () {
						setTimeout(function () {
							input.trigger('change');
						}, 1);
					}
				});

				// Re-render the picker's own UI (swatch, alpha slider) when
				// the setting changes programmatically (e.g. reset button).
				control.setting.bind(function (newValue) {
					if (input.val() !== newValue) {
						input.val(newValue);
					}
					input.wpColorPicker('color', newValue);
				});
			});
		});
	}

	function initTypographyControl(wrapper, control) {
		var familySelect   = wrapper.querySelector('.vlt-typography-control__family-select');
		var variantsSelect = wrapper.querySelector('.vlt-typography-control__variants-select');
		var hiddenInput    = wrapper.querySelector('.vlt-typography-control__value');

		if (!familySelect || !variantsSelect || !hiddenInput) {
			return;
		}

		familySelect.addEventListener('change', function () {
			populateVariants(variantsSelect, familySelect.value, []);
			updateValue();
		});

		variantsSelect.addEventListener('change', updateValue);

		function updateValue() {
			var selectedVariants = Array.prototype.slice
				.call(variantsSelect.selectedOptions)
				.map(function (option) { return option.value; });

			var value = JSON.stringify({
				family: familySelect.value,
				variants: selectedVariants
			});

			hiddenInput.value = value;
			hiddenInput.dispatchEvent(new Event('change'));
		}

		// Re-render the family/variants selects when the setting changes
		// programmatically (e.g. via the reset-to-default button).
		if (control && control.setting) {
			control.setting.bind(function (newValue) {
				var parsed = {};

				try {
					parsed = JSON.parse(newValue || '{}');
				} catch (err) {
					parsed = {};
				}

				if (familySelect.value !== (parsed.family || '')) {
					familySelect.value = parsed.family || '';
				}

				populateVariants(variantsSelect, parsed.family || '', parsed.variants || []);
			});
		}
	}

	function populateVariants(select, family, selected) {
		var variants = (fonts[family] && fonts[family].variants) || [];

		select.innerHTML = '';

		variants.forEach(function (variant) {
			var option = document.createElement('option');
			option.value = variant;
			option.textContent = variant;
			option.selected = selected.indexOf(variant) !== -1;
			select.appendChild(option);
		});
	}

	/* ========================================
	 * Panel & Section Icons
	 * ======================================== */
	function applySectionIcons() {
		if (!icons.section) {
			return;
		}

		Object.keys(icons.section).forEach(function (sectionId) {
			var icon    = icons.section[sectionId];
			var section = wp.customize.section(sectionId);

			if (!section) {
				return;
			}

			decorate(section);
			section.container.on('expanded', function () {
				decorate(section);
			});

			function decorate() {
				var listTitle = document.querySelector('#accordion-section-' + sectionId + ' > h3');
				if (listTitle) {
					listTitle.classList.add('dashicons-before', icon);
				}

				var openTitle = document.querySelector('#sub-accordion-section-' + sectionId + ' .customize-section-title > h3');
				if (openTitle && !openTitle.querySelector('.' + icon)) {
					var span = document.createElement('span');
					span.className = 'dashicons ' + icon;
					span.style.cssText = 'float:left;padding-right:.1em;padding-top:2px;';
					openTitle.insertBefore(span, openTitle.firstChild);
				}
			}
		});
	}

	function applyPanelIcons() {
		if (!icons.panel) {
			return;
		}

		Object.keys(icons.panel).forEach(function (panelId) {
			var icon  = icons.panel[panelId];
			var panel = wp.customize.panel(panelId);

			if (!panel) {
				return;
			}

			decorate();
			panel.container.on('expanded', decorate);

			function decorate() {
				document.querySelectorAll(
					'#accordion-panel-' + panelId + ' > h3, #sub-accordion-panel-' + panelId + ' .panel-title'
				).forEach(function (el) {
					el.classList.add('dashicons-before', icon);
				});
			}
		});
	}
});
