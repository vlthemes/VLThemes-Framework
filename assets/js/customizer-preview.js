/* ========================================
 * Customizer preview: the CSS of the fields with style rules (Customizer::get_field_css() in JS), rewritten in
 * their <style id="vlt-customizer-css-{settings}"> as the field, its devices or the settings of its
 * active_callback change
 * ======================================== */
(function (api) {
	'use strict';

	var data = window.vltFwCustomizerCss || { fields: {}, breakpoints: {} };
	var DEVICES = ['desktop', 'tablet', 'mobile'];

	function settingValue(id) {
		var setting = api(id);

		return setting ? setting.get() : null;
	}

	function settingIds(id, field) {
		return field.responsive ? DEVICES.map(function (device) {
			return 'desktop' === device ? id : id + '_' + device;
		}) : [id];
	}

	function stripCss(value) {
		return String(value).replace(/[;{}<>\\]+/g, '').trim();
	}

	function dimensionValue(field, value) {
		value = String(value).trim().toLowerCase();

		if (field.keywords.indexOf(value) !== -1) {
			return value;
		}

		if (/^-?\d*\.?\d+$/.test(value)) {
			return parseFloat(value) + field.units[0];
		}

		var match = value.match(/^(-?\d*\.?\d+)([a-z%]+)$/);

		if (match) {
			return field.units.indexOf(match[2]) !== -1 ? value : '';
		}

		// The sanitized value comes back from the server on save; here a value being typed is skipped
		return /^(clamp|min|max|calc|var)\([a-z0-9%.,+*\/\s()_-]+\)$/.test(value) ? value : '';
	}

	function cssValue(field, value) {
		if (null === value || undefined === value || 'object' === typeof value) {
			return '';
		}

		value = String(value).trim();

		if ('' === value) {
			return '';
		}

		switch (field.type) {
			case 'number':
				return isNaN(parseFloat(value)) ? '' : parseFloat(value) + field.unit;

			case 'dimension':
				return dimensionValue(field, value);

			case 'checkbox':
				return value && 'false' !== value && '0' !== value ? '1' : '';

			case 'typography':
				try {
					var font = JSON.parse(value);

					return font && font.family ? '"' + stripCss(font.family) + '"' : '';
				} catch (error) {
					return '';
				}

			case 'image':
			case 'upload':
				return /^\d+$/.test(value) ? '' : 'url("' + encodeURI(value).replace(/"/g, '%22') + '")';

			default:
				return stripCss(value);
		}
	}

	function rulesMatch(rules) {
		return rules.every(function (rule) {
			var value = settingValue(rule.setting);
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

	function fieldCss(id, field) {
		if (!rulesMatch(field.rules)) {
			return '';
		}

		var css = '';
		var wider = '';

		settingIds(id, field).forEach(function (settingId, index) {
			var device = DEVICES[index];
			var value = cssValue(field, settingValue(settingId));

			// Empty or as the wider device: follows it
			if ('' === value || value === wider) {
				return;
			}

			wider = value;

			var block = field.style.map(function (rule) {
				return rule.element + '{' + rule.property + ':' + rule.mask.split('$').join(value) + ';}';
			}).join('');

			css += 'desktop' === device ? block : '@media (max-width:' + data.breakpoints[device] + 'px){' + block + '}';
		});

		return css;
	}

	function render(id, field) {
		var style = document.getElementById('vlt-customizer-css-' + id);

		if (!style) {
			style = document.createElement('style');
			style.id = 'vlt-customizer-css-' + id;
			document.head.appendChild(style);
		}

		style.textContent = fieldCss(id, field);
	}

	api.bind('preview-ready', function () {
		Object.keys(data.fields).forEach(function (id) {
			var field = data.fields[id];
			var watched = settingIds(id, field).concat(field.rules.map(function (rule) {
				return rule.setting;
			}));

			watched.forEach(function (settingId) {
				api(settingId, function (setting) {
					setting.bind(function () {
						render(id, field);
					});
				});
			});
		});
	});
})(wp.customize);
