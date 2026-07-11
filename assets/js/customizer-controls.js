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

	wp.customize.bind('ready', function () {
		document.querySelectorAll('.vlt-typography-control').forEach(initTypographyControl);

		applySectionIcons();
		applyPanelIcons();
	});

	function initTypographyControl(wrapper) {
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
