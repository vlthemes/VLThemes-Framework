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
		initRepeaterControls();
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

	/* ========================================
	 * Repeater Control
	 * ======================================== */
	function initRepeaterControls() {
		wp.customize.control.each(function (control) {
			if (control.params.type !== 'vlt-repeater') {
				return;
			}

			control.deferred.embedded.done(function () {
				var wrapper = control.container.find('.vlt-repeater-control').get(0);
				if (wrapper && !wrapper.dataset.initialized) {
					wrapper.dataset.initialized = '1';
					initRepeaterControl(wrapper, control);
				}
			});
		});
	}

	function initRepeaterControl(wrapper, control) {
		var fields    = control.params.fields || {};
		var rowLabel  = control.params.row_label || {};
		var list      = wrapper.querySelector('.vlt-repeater-control__rows');
		var addButton = wrapper.querySelector('.vlt-repeater-control__add');
		var rows      = normalizeRows(control.setting.get());
		var openRows  = [];

		addButton.textContent = control.params.button_label;

		addButton.addEventListener('click', function () {
			var row = {};

			Object.keys(fields).forEach(function (key) {
				row[key] = fields[key]['default'] !== undefined ? fields[key]['default'] : '';
			});

			rows.push(row);
			openRows.push(rows.length - 1);
			save();
			render();
		});

		// Re-render when the setting changes programmatically (e.g. reset button).
		control.setting.bind(function (newValue) {
			var normalized = normalizeRows(newValue);

			if (JSON.stringify(normalized) !== JSON.stringify(rows)) {
				rows = normalized;
				render();
			}
		});

		if (typeof jQuery !== 'undefined' && jQuery.fn.sortable) {
			jQuery(list).sortable({
				handle: '.vlt-repeater-row__header',
				axis: 'y',
				update: function () {
					var order = Array.prototype.map.call(list.children, function (item) {
						return parseInt(item.dataset.index, 10);
					});

					rows = order.map(function (index) {
						return rows[index];
					});
					openRows = [];
					save();
					render();
				}
			});
		}

		render();

		function save() {
			control.setting.set(JSON.parse(JSON.stringify(rows)));
		}

		function render() {
			list.innerHTML = '';

			rows.forEach(function (row, index) {
				list.appendChild(renderRow(row, index));
			});
		}

		function getRowLabel(row, index) {
			if (rowLabel.type === 'field' && rowLabel.field && row[rowLabel.field]) {
				var field = fields[rowLabel.field] || {};
				var value = row[rowLabel.field];

				return (field.choices && field.choices[value]) || value;
			}

			return (rowLabel.value || 'Row') + ' ' + (index + 1);
		}

		function renderRow(row, index) {
			var item = document.createElement('li');
			item.className = 'vlt-repeater-row';
			item.dataset.index = index;

			if (openRows.indexOf(index) === -1) {
				item.classList.add('is-collapsed');
			}

			var header = document.createElement('div');
			header.className = 'vlt-repeater-row__header';

			var title = document.createElement('span');
			title.className = 'vlt-repeater-row__title';
			title.textContent = getRowLabel(row, index);
			header.appendChild(title);

			var toggle = document.createElement('span');
			toggle.className = 'vlt-repeater-row__toggle dashicons dashicons-arrow-down';
			header.appendChild(toggle);

			header.addEventListener('click', function () {
				item.classList.toggle('is-collapsed');

				var position = openRows.indexOf(index);
				if (item.classList.contains('is-collapsed')) {
					if (position !== -1) {
						openRows.splice(position, 1);
					}
				} else if (position === -1) {
					openRows.push(index);
				}
			});

			var body = document.createElement('div');
			body.className = 'vlt-repeater-row__body';

			Object.keys(fields).forEach(function (key) {
				body.appendChild(renderField(key, fields[key], row, function () {
					title.textContent = getRowLabel(row, index);
					save();
				}));
			});

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'button-link button-link-delete vlt-repeater-row__remove';
			remove.textContent = 'Remove';
			remove.addEventListener('click', function () {
				rows.splice(index, 1);
				openRows = openRows
					.filter(function (i) { return i !== index; })
					.map(function (i) { return i > index ? i - 1 : i; });
				save();
				render();
			});
			body.appendChild(remove);

			item.appendChild(header);
			item.appendChild(body);

			return item;
		}

		function renderField(key, field, row, onChange) {
			var wrap = document.createElement('div');
			wrap.className = 'vlt-repeater-field vlt-repeater-field--' + (field.type || 'text');

			if (field.label) {
				var label = document.createElement('span');
				label.className = 'customize-control-title';
				label.textContent = field.label;
				wrap.appendChild(label);
			}

			if (field.description) {
				var description = document.createElement('span');
				description.className = 'description customize-control-description';
				description.textContent = field.description;
				wrap.appendChild(description);
			}

			var value = row[key] !== undefined && row[key] !== null ? row[key] : '';
			var input;

			switch (field.type) {
				case 'image':
					wrap.appendChild(renderImageField(value, function (id) {
						row[key] = id;
						onChange();
					}));

					return wrap;

				case 'textarea':
					input = document.createElement('textarea');
					input.rows = 3;
					break;

				case 'select':
					input = document.createElement('select');

					var empty = document.createElement('option');
					empty.value = '';
					empty.textContent = '—';
					input.appendChild(empty);

					Object.keys(field.choices || {}).forEach(function (choice) {
						var option = document.createElement('option');
						option.value = choice;
						option.textContent = field.choices[choice];
						input.appendChild(option);
					});
					break;

				default:
					input = document.createElement('input');
					input.type = (field.type === 'url' || field.type === 'link') ? 'url' : 'text';
			}

			input.className = 'widefat';
			input.value = value;

			input.addEventListener(field.type === 'select' ? 'change' : 'input', function () {
				row[key] = input.value;
				onChange();
			});

			wrap.appendChild(input);

			return wrap;
		}

		function renderImageField(value, onSelect) {
			var holder = document.createElement('div');
			holder.className = 'vlt-repeater-image';

			var preview = document.createElement('div');
			preview.className = 'vlt-repeater-image__preview';

			var select = document.createElement('button');
			select.type = 'button';
			select.className = 'button';

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'button-link button-link-delete';
			remove.textContent = 'Remove';

			function update(id) {
				preview.innerHTML = '';
				select.textContent = id ? 'Change Image' : 'Select Image';
				remove.style.display = id ? '' : 'none';

				if (!id || !wp.media) {
					return;
				}

				var attachment = wp.media.attachment(id);

				attachment.fetch().done(function () {
					var sizes = attachment.get('sizes') || {};
					var url   = (sizes.thumbnail || sizes.full || {}).url || attachment.get('url');

					if (url) {
						var img = document.createElement('img');
						img.src = url;
						preview.appendChild(img);
					}
				});
			}

			select.addEventListener('click', function () {
				if (!wp.media) {
					return;
				}

				var frame = wp.media({
					title: 'Select Image',
					multiple: false,
					library: { type: 'image' }
				});

				frame.on('select', function () {
					var id = frame.state().get('selection').first().get('id');
					update(id);
					onSelect(id);
				});

				frame.open();
			});

			remove.addEventListener('click', function () {
				update('');
				onSelect('');
			});

			update(value);

			holder.appendChild(preview);
			holder.appendChild(select);
			holder.appendChild(remove);

			return holder;
		}
	}

	function normalizeRows(value) {
		if (typeof value === 'string') {
			try {
				value = JSON.parse(value);
			} catch (err) {
				try {
					value = JSON.parse(decodeURIComponent(value));
				} catch (err2) {
					value = [];
				}
			}
		}

		if (value && !Array.isArray(value) && typeof value === 'object') {
			value = Object.keys(value).map(function (key) {
				return value[key];
			});
		}

		return Array.isArray(value)
			? JSON.parse(JSON.stringify(value.filter(function (row) { return row && typeof row === 'object'; })))
			: [];
	}

	function initTypographyControl(wrapper, control) {
		var familySelect    = wrapper.querySelector('.vlt-typography-control__family-select');
		var variantsWrapper = wrapper.querySelector('.vlt-typography-control__variants');
		var variantsSelect  = wrapper.querySelector('.vlt-typography-control__variants-select');
		var hiddenInput     = wrapper.querySelector('.vlt-typography-control__value');

		if (!familySelect || !variantsWrapper || !variantsSelect || !hiddenInput) {
			return;
		}

		familySelect.addEventListener('change', function () {
			populateVariants(variantsWrapper, variantsSelect, familySelect.value, []);
			updateValue();
		});

		variantsSelect.addEventListener('change', updateValue);

		function updateValue() {
			var isGoogle = isGoogleFont(familySelect.value);

			var selectedVariants = isGoogle
				? Array.prototype.slice.call(variantsSelect.selectedOptions).map(function (option) { return option.value; })
				: [];

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

				populateVariants(variantsWrapper, variantsSelect, parsed.family || '', parsed.variants || []);
			});
		}
	}

	function isGoogleFont(family) {
		return !family || (fonts[family] && fonts[family].source) === 'google' || !(fonts[family] && fonts[family].source);
	}

	function allVariants(family) {
		return (fonts[family] && fonts[family].variants) || [];
	}

	function populateVariants(wrapper, select, family, selected) {
		var isGoogle = isGoogleFont(family);

		wrapper.style.display = isGoogle ? '' : 'none';

		var variants = isGoogle ? allVariants(family) : [];

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
