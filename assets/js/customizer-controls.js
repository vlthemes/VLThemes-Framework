/* ========================================
 * Customizer controls (Vanilla JS): changed dot, device switch, value field, typography, alpha color, repeater,
 * panel and section icons
 * ======================================== */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.customize) {
		return;
	}

	var fonts = window.vltFwCustomizerFonts || {};
	var icons = window.vltFwCustomizerIcons || {};
	var defaultValues = window.vltFwCustomizerDefaults || {};
	var devices = window.vltFwCustomizerDevices || {};

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
		initFieldControls();
	});

	/* ========================================
	 * Field controls: the changed dot (click: reset), the device switch of responsive fields, the value field
	 * ======================================== */
	function initFieldControls() {
		var api = wp.customize;
		var setDevice = function (device) {
			document.body.setAttribute('data-vlt-device', device);
		};

		// Responsive fields show the device being previewed
		setDevice(api.previewedDevice.get() || 'desktop');
		api.previewedDevice.bind(setDevice);

		var init = function (control) {
			control.deferred.embedded.done(function () {
				if ('vlt-value' === control.params.type) {
					initValueControl(control);
				}

				addDeviceSwitch(control);
				addChangedDot(control);
			});
		};

		api.control.each(init);
		api.control.bind('add', init);
	}

	// Same value: '18' is 18, an unset value is ''
	function sameValue(a, b) {
		var normalize = function (value) {
			if (null === value || undefined === value || false === value) {
				return '';
			}

			if (true === value) {
				return '1';
			}

			return 'object' === typeof value ? JSON.stringify(value) : String(value);
		};

		return normalize(a) === normalize(b);
	}

	// The label of a control: its title, or the label of a checkbox
	function controlTitle(control) {
		var title = control.container.find('.customize-control-title').first();

		if (!title.length) {
			title = control.container.find('.customize-inside-control-row > label').first();
		}

		return title.length ? title.get(0) : null;
	}

	// The dot: the value is not the default; the reset button next to it sets the default back
	function addChangedDot(control) {
		if (!control.setting || !Object.prototype.hasOwnProperty.call(defaultValues, control.id) || control.container.find('.vlt-changed-dot').length) {
			return;
		}

		var title = controlTitle(control);

		if (!title) {
			return;
		}

		var defaultValue = defaultValues[control.id];

		var dot = document.createElement('span');
		dot.className = 'vlt-changed-dot';
		dot.title = 'Changed from the default';

		var reset = document.createElement('button');
		reset.type = 'button';
		reset.className = 'vlt-reset-control dashicons dashicons-image-rotate';
		reset.title = 'Reset to default';
		reset.setAttribute('aria-label', 'Reset to default');

		reset.addEventListener('click', function (event) {
			// Inside a checkbox label: the checkbox stays as it is
			event.preventDefault();
			event.stopPropagation();
			control.setting.set(defaultValue);
		});

		var update = function () {
			var same = sameValue(control.setting.get(), defaultValue);

			dot.hidden = same;
			reset.hidden = same;
		};

		title.classList.add('vlt-control-title');
		title.appendChild(dot);
		title.appendChild(reset);
		control.setting.bind(update);
		update();
	}

	var DEVICE_ICONS = {
		desktop: '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M9 20h6M12 16v4"/>',
		tablet: '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/>',
		mobile: '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>'
	};

	var DEVICE_LABELS = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };

	function addDeviceSwitch(control) {
		var info = devices[control.id];

		if (!info) {
			return;
		}

		control.container.addClass('vlt-device-control vlt-device-control--' + info.device);

		var title = controlTitle(control);

		if (!title || title.querySelector('.vlt-devices')) {
			return;
		}

		var group = document.createElement('span');
		group.className = 'vlt-devices';

		Object.keys(DEVICE_ICONS).forEach(function (device) {
			var button = document.createElement('button');

			button.type = 'button';
			button.className = 'vlt-devices__item' + (device === info.device ? ' is-active' : '');
			button.title = DEVICE_LABELS[device];
			button.setAttribute('aria-label', DEVICE_LABELS[device]);
			button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + DEVICE_ICONS[device] + '</svg>';

			button.addEventListener('click', function (event) {
				event.preventDefault();
				wp.customize.previewedDevice.set(device);
			});

			group.appendChild(button);
		});

		title.classList.add('vlt-control-title');
		title.appendChild(group);
	}

	// Settings of the wider devices of a responsive field, nearest first
	function widerSettings(id) {
		var info = devices[id];

		if (!info) {
			return [];
		}

		var order = ['desktop', 'tablet', 'mobile'];

		return order.slice(0, order.indexOf(info.device)).reverse().map(function (device) {
			return 'desktop' === device ? info.field : info.field + '_' + device;
		});
	}

	/* ========================================
	 * Value field (Modules/Core/Controls/ValueControl.php): a number with a unit, or a CSS function or keyword
	 * picked from the unit menu; under it a ruler, dragged sideways or stepped with its arrows (Shift: by 10)
	 * ======================================== */
	var LENGTH_UNITS = ['px', '%', 'em', 'rem', 'vw', 'vh'];
	var FUNCTIONS = ['clamp', 'min', 'max', 'calc', 'var'];
	var FUNCTION_EXAMPLES = {
		clamp: '200px, 50vh, 600px',
		min: '100px, 50%',
		max: '300px, 50vw',
		calc: '100% - 20px',
		var: '--name'
	};

	// Times a numeric field can switch between, in ms
	var TIME = { ms: 1, s: 1000 };

	// How far the ruler turns for one step, px
	var TURN = 2;

	// No float noise (0.30000000000000004) in the field
	function tidy(number) {
		return String(parseFloat(number.toFixed(4)));
	}

	function clampNumber(number, min, max) {
		return Math.min(null === max || undefined === max ? Infinity : max, Math.max(null === min || undefined === min ? -Infinity : min, number));
	}

	function initValueControl(control) {
		var root = control.container.find('.vlt-value').get(0);

		if (!root || root.dataset.ready) {
			return;
		}

		root.dataset.ready = '1';

		var params = control.params;
		var units = params.units && params.units.length ? params.units : LENGTH_UNITS;
		var keywords = params.keywords || [];
		var offered = params.functions || [];
		var numeric = !!params.numeric;
		var min = params.min;
		var max = params.max;
		var step = parseFloat(params.step) || 1;
		var wider = widerSettings(control.id);

		var mode = units[0];
		var text = '';
		var sent;
		var spin = 0;
		var drag = null;

		// How many units[0] one unit shown is (1 but for a time shown in s): min, max and step are in units[0]
		var ratio = function (unit) {
			return TIME[unit] && TIME[units[0]] ? TIME[unit] / TIME[units[0]] : 1;
		};

		var parse = function (value) {
			var raw = String(null === value || undefined === value ? '' : value).trim();
			var match = raw.match(/^(-?\d*\.?\d+)([a-z%]*)$/i);

			if (match && (!match[2] || units.indexOf(match[2].toLowerCase()) !== -1)) {
				return { mode: match[2].toLowerCase() || units[0], text: match[1] };
			}

			match = raw.match(/^(clamp|min|max|calc|var)\((.*)\)$/i);

			if (match) {
				return { mode: match[1].toLowerCase(), text: match[2].trim() };
			}

			if (keywords.indexOf(raw) !== -1) {
				return { mode: raw, text: '' };
			}

			return { mode: units[0], text: raw };
		};

		var format = function (nextMode, nextText) {
			var value = String(nextText).trim();

			if (keywords.indexOf(nextMode) !== -1) {
				return nextMode;
			}

			if ('' === value) {
				return '';
			}

			if ('var' === nextMode) {
				var parts = value.split(',').map(function (part) {
					return part.trim();
				});
				var name = parts.shift();
				var variable = 0 === name.indexOf('--') ? name : '--' + name;

				return parts.length ? 'var(' + variable + ', ' + parts.join(', ') + ')' : 'var(' + variable + ')';
			}

			return FUNCTIONS.indexOf(nextMode) !== -1 ? nextMode + '(' + value + ')' : value + nextMode;
		};

		// The value of the nearest wider device with one: shown as the placeholder
		var inherited = function () {
			for (var i = 0; i < wider.length; i++) {
				var value = wp.customize(wider[i]) ? wp.customize(wider[i]).get() : '';

				if (null !== value && undefined !== value && '' !== value) {
					return value;
				}
			}

			return '';
		};

		// The value in units[0]: the number typed, else the placeholder's
		var stored = function () {
			var number = parseFloat(text);

			if (isNaN(number)) {
				var fallback = parseFloat(numeric ? inherited() : parse(inherited()).text);

				return isNaN(fallback) ? 0 : fallback * (numeric ? 1 : ratio(mode));
			}

			return number * ratio(mode);
		};

		// Markup
		var box = document.createElement('div');
		box.className = 'vlt-value__box';

		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'vlt-value__input';
		input.id = '_customize-input-' + control.id;
		input.autocomplete = 'off';
		box.appendChild(input);

		var calls = offered.filter(function (name) {
			return 'var' !== name;
		});

		var groups = [
			units.length > 1 && { label: 'Units', options: units },
			calls.length && { label: 'Functions', options: calls },
			offered.indexOf('var') !== -1 && { label: 'Variable', options: ['var'] },
			keywords.length && { label: 'Keywords', options: keywords }
		].filter(Boolean);

		var unit = document.createElement(groups.length ? 'button' : 'span');
		unit.className = 'vlt-value__unit' + (groups.length ? '' : ' is-static');
		box.appendChild(unit);

		var menu = null;

		if (groups.length) {
			unit.type = 'button';
			unit.setAttribute('aria-haspopup', 'listbox');
			unit.setAttribute('aria-expanded', 'false');
			unit.setAttribute('aria-label', 'Unit');

			menu = document.createElement('div');
			menu.className = 'vlt-value__menu';
			menu.setAttribute('role', 'listbox');
			menu.hidden = true;

			groups.forEach(function (group) {
				var section = document.createElement('div');
				section.className = 'vlt-value__menu-group';
				section.innerHTML = '<div class="vlt-value__menu-label">' + group.label + '</div>';

				group.options.forEach(function (option) {
					var item = document.createElement('button');
					item.type = 'button';
					item.className = 'vlt-value__menu-item';
					item.setAttribute('role', 'option');
					item.dataset.mode = option;
					item.textContent = option;

					item.addEventListener('click', function () {
						select(option);
						toggleMenu(false);
					});

					section.appendChild(item);
				});

				menu.appendChild(section);
			});

			unit.addEventListener('click', function () {
				toggleMenu(menu.hidden);
			});

			document.addEventListener('mousedown', function (event) {
				if (!menu.hidden && !root.contains(event.target) && !menu.contains(event.target)) {
					toggleMenu(false);
				}
			});

			// The menu sits on the unit button: a scrolled pane or a resized window closes it
			document.addEventListener('scroll', function () {
				if (!menu.hidden) {
					toggleMenu(false);
				}
			}, true);

			window.addEventListener('resize', function () {
				if (!menu.hidden) {
					toggleMenu(false);
				}
			});
		}

		var ruler = document.createElement('div');
		ruler.className = 'vlt-value__ruler';
		ruler.innerHTML = '<button type="button" class="vlt-value__step" tabindex="-1" aria-hidden="true">◀</button>'
			+ '<span class="vlt-value__track" title="Drag to change (Shift: faster)"></span>'
			+ '<button type="button" class="vlt-value__step" tabindex="-1" aria-hidden="true">▶</button>';

		var track = ruler.querySelector('.vlt-value__track');
		var steps = ruler.querySelectorAll('.vlt-value__step');

		root.appendChild(box);

		// In the body: the panes of the Customizer cut off what overflows them
		if (menu) {
			document.body.appendChild(menu);
		}

		root.appendChild(ruler);

		// State to the markup
		var isLength = function () {
			return units.indexOf(mode) !== -1;
		};

		var placeholder = function () {
			var value = inherited();

			if (keywords.indexOf(mode) !== -1) {
				return mode;
			}

			if ('' === value) {
				return FUNCTION_EXAMPLES[mode] || '';
			}

			if (numeric) {
				return isNaN(parseFloat(value)) ? '' : tidy(parseFloat(value) / ratio(mode));
			}

			var parsed = parse(value);

			return parsed.mode === mode ? parsed.text : String(value);
		};

		var paint = function () {
			var keyword = keywords.indexOf(mode) !== -1;

			input.value = text;
			input.placeholder = placeholder();
			input.disabled = keyword;
			input.inputMode = isLength() ? 'decimal' : 'text';
			box.classList.toggle('is-disabled', keyword);
			unit.textContent = mode;
			unit.hidden = !groups.length && !mode;
			ruler.hidden = !isLength();

			if (menu) {
				menu.querySelectorAll('.vlt-value__menu-item').forEach(function (item) {
					item.classList.toggle('is-active', item.dataset.mode === mode);
					item.setAttribute('aria-selected', item.dataset.mode === mode ? 'true' : 'false');
				});
			}
		};

		var show = function (value) {
			var parsed = parse(value);

			// A number keeps the unit it is shown in
			if (numeric) {
				text = '' === parsed.text || isNaN(parseFloat(parsed.text)) ? '' : tidy(parseFloat(parsed.text) / ratio(mode));
			} else {
				mode = parsed.mode;
				text = parsed.text;
			}

			paint();
		};

		var commit = function (nextMode, nextText) {
			mode = nextMode;
			text = String(nextText);

			var number = parseFloat(text);
			var next = numeric
				? ('' === text.trim() ? '' : clampNumber(isNaN(number) ? 0 : Math.round(number * ratio(mode) * 1e4) / 1e4, min, max))
				: format(mode, text);

			sent = JSON.stringify(next);
			control.setting.set(next);
			paint();
		};

		var turn = function (count) {
			spin += count * TURN;
			track.style.backgroundPositionX = Math.round(spin) + 'px';
		};

		var stepBy = function (delta) {
			turn(delta / step);
			commit(mode, tidy(clampNumber(Math.round((stored() + delta) * 100) / 100, min, max) / ratio(mode)));
		};

		var select = function (next) {
			// A number keeps its value across units; a function starts from its example, a variable empty
			if (numeric) {
				var value = stored();

				mode = next;
				commit(next, '' === text.trim() ? '' : tidy(value / ratio(next)));
			} else if (FUNCTIONS.indexOf(next) !== -1) {
				commit(next, 'var' === next ? '' : (FUNCTIONS.indexOf(mode) !== -1 ? text : FUNCTION_EXAMPLES[next]));
			} else if (TIME[mode] && TIME[next] && '' !== text.trim() && !isNaN(parseFloat(text))) {
				var time = stored();

				commit(next, tidy(time / ratio(next)));
			} else {
				commit(next, units.indexOf(next) !== -1 && units.indexOf(mode) === -1 ? '' : text);
			}
		};

		var toggleMenu = function (open) {
			menu.hidden = !open;
			unit.setAttribute('aria-expanded', open ? 'true' : 'false');

			if (!open) {
				return;
			}

			// Under the unit button, its right edge; above it when there is no room below
			var rect = unit.getBoundingClientRect();
			var below = window.innerHeight - rect.bottom - 8;
			var height = menu.offsetHeight;

			menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
			menu.style.top = (height > below && rect.top - 8 > below ? rect.top - 4 - height : rect.bottom + 4) + 'px';
		};

		// Events
		input.addEventListener('input', function () {
			var from = parseFloat(text);
			var to = parseFloat(input.value);

			if (isLength() && !isNaN(from) && !isNaN(to)) {
				turn(((to - from) * ratio(mode)) / step);
			}

			commit(mode, input.value);
		});

		input.addEventListener('keydown', function (event) {
			if (isLength() && ('ArrowUp' === event.key || 'ArrowDown' === event.key)) {
				event.preventDefault();
				stepBy(('ArrowUp' === event.key ? 1 : -1) * step * (event.shiftKey ? 10 : 1));
			}
		});

		steps[0].addEventListener('click', function (event) {
			stepBy(-step * (event.shiftKey ? 10 : 1));
		});

		steps[1].addEventListener('click', function (event) {
			stepBy(step * (event.shiftKey ? 10 : 1));
		});

		track.addEventListener('pointerdown', function (event) {
			if (!isLength()) {
				return;
			}

			track.setPointerCapture(event.pointerId);
			drag = { x: event.clientX, value: stored(), text: null, frame: 0 };
			root.classList.add('is-dragging');
		});

		track.addEventListener('pointermove', function (event) {
			if (!drag) {
				return;
			}

			var count = (event.clientX - drag.x) * (event.shiftKey ? 10 : 1);
			var state = drag;

			turn(count);
			state.x = event.clientX;
			state.value = clampNumber(state.value + count * step, min, max);
			state.text = tidy((Math.round(state.value / step) * step) / ratio(mode));

			// The setting (and the preview) once a frame
			if (!state.frame) {
				state.frame = requestAnimationFrame(function () {
					state.frame = 0;
					commit(mode, state.text);
				});
			}
		});

		var release = function () {
			if (drag && drag.frame) {
				cancelAnimationFrame(drag.frame);
				commit(mode, drag.text);
			}

			drag = null;
			root.classList.remove('is-dragging');
		};

		track.addEventListener('pointerup', release);
		track.addEventListener('pointercancel', release);

		// A new value from outside (a reset, the setting loaded): shown; what this field sent stays as typed
		control.setting.bind(function (value) {
			if (JSON.stringify(value) !== sent) {
				show(value);
			}
		});

		// The wider devices change the placeholder
		wider.forEach(function (id) {
			wp.customize(id, function (setting) {
				setting.bind(paint);
			});
		});

		show(control.setting.get());
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
