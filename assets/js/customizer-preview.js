/* ========================================
 * Customizer preview: the CSS of the fields' output rules (Customizer::get_field_css() in JS), rewritten in their
 * <style id="vlt-customizer-css-{settings}"> as the field or the settings of its active_callback change
 * ======================================== */
(function (api) {
	'use strict';

	var data = window.vltFwCustomizerCss || { fields: {} };

	// URLs of attachment IDs (an image field with 'save_as' => 'id'): id => url, or false while it is asked for
	var media = {};

	function mediaUrl(id) {
		if (undefined !== media[id]) {
			return media[id] || '';
		}

		media[id] = false;

		if (!data.media || !window.fetch) {
			return '';
		}

		window.fetch(data.media + id + '?_fields=source_url', { credentials: 'same-origin' })
			.then(function (response) {
				return response.ok ? response.json() : null;
			})
			.then(function (attachment) {
				if (!attachment || !attachment.source_url) {
					return;
				}

				media[id] = attachment.source_url;

				// Now known: the fields showing it again
				Object.keys(data.fields).forEach(function (fieldId) {
					var field = data.fields[fieldId];

					if (('image' === field.type || 'upload' === field.type) && String(settingValue(fieldId)) === String(id)) {
						render(fieldId, field);
					}
				});
			})
			.catch(function () {});

		return '';
	}

	function settingValue(id) {
		var setting = api(id);

		return setting ? setting.get() : null;
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

	function cssValue(field, value, choice) {
		// A key of the value: font-family of a typography field ({"family":…}), or of any object value
		if (choice) {
			var data = value;

			if ('string' === typeof value) {
				try {
					data = JSON.parse(value);
				} catch (error) {
					data = null;
				}
			}

			var part = data && 'object' === typeof data ? (undefined !== data[choice] ? data[choice] : ('font-family' === choice ? data.family : '')) : '';

			if (null === part || undefined === part || 'object' === typeof part || '' === String(part).trim()) {
				return '';
			}

			return 'font-family' === choice ? '"' + stripCss(part) + '"' : stripCss(part);
		}

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
				var url = /^\d+$/.test(value) ? mediaUrl(value) : value;

				return url ? 'url("' + encodeURI(url).replace(/"/g, '%22') + '")' : '';

			default:
				return stripCss(value);
		}
	}

	var TYPOGRAPHY = ['font-size', 'line-height', 'letter-spacing', 'word-spacing', 'font-weight', 'font-style', 'text-transform', 'text-decoration'];

	// Declarations of a typography value (Customizer::typography_css(); the saved value is sanitized)
	function typographyCss(value) {
		var data = value;

		if ('string' === typeof value) {
			try {
				data = JSON.parse(value);
			} catch (error) {
				data = null;
			}
		}

		if (!data || 'object' !== typeof data) {
			return '';
		}

		var css = data.family ? 'font-family:"' + stripCss(data.family) + '";' : '';

		TYPOGRAPHY.forEach(function (property) {
			var part = data[property];

			if (null !== part && undefined !== part && 'object' !== typeof part && '' !== String(part).trim()) {
				css += property + ':' + stripCss(part) + ';';
			}
		});

		return css;
	}

	// A Google font picked in the preview: its weights (the variants, else the weight set) from fonts.googleapis.com,
	// as Customizer::collect_typography_google_fonts() queues it; a font of another source fails quietly, it loads with the page
	function loadFont(value) {
		var data = value;

		if ('string' === typeof value) {
			try {
				data = JSON.parse(value);
			} catch (error) {
				data = null;
			}
		}

		if (!data || !data.family) {
			return;
		}

		var variants = Array.isArray(data.variants) && data.variants.length ? data.variants : [data['font-weight'] || 'regular'];
		var weights = variants.map(function (variant) {
			variant = String(variant);

			return 'bold' === variant ? 700 : parseInt(variant.replace(/[^0-9]/g, ''), 10) || 400;
		}).filter(function (weight, index, list) {
			return list.indexOf(weight) === index;
		}).sort(function (a, b) {
			return a - b;
		});

		var href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(data.family).replace(/%20/g, '+') + ':wght@' + weights.join(';') + '&display=swap';

		if (document.querySelector('link[data-vlt-preview-font="' + href.replace(/"/g, '') + '"]')) {
			return;
		}

		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		link.setAttribute('data-vlt-preview-font', href.replace(/"/g, ''));
		document.head.appendChild(link);
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

		var saved = settingValue(id);
		var blocks = {};

		// A typography value saved without a font takes the font of the field's default (Customizer::typography_saved())
		if ('typography' === field.type && field.family) {
			try {
				var font = JSON.parse(saved || '{}');

				if (font && 'object' === typeof font && !font.family) {
					font.family = field.family;
					saved = JSON.stringify(font);
				}
			} catch (error) {}
		}

		field.output.forEach(function (rule) {
			// No property: all the typography of the field
			if (!rule.property) {
				var declarations = 'typography' === field.type ? typographyCss(saved) : '';

				if (declarations) {
					blocks[rule.media_query] = (blocks[rule.media_query] || '') + rule.element + '{' + declarations + '}';
				}

				return;
			}

			var value = cssValue(field, saved, rule.choice);

			if ('' === value) {
				return;
			}

			blocks[rule.media_query] = (blocks[rule.media_query] || '') + rule.element + '{' + rule.property + ':' + rule.value_pattern.split('$').join(value) + ';}';
		});

		// The plain rules first, then each media query
		var css = blocks[''] || '';

		Object.keys(blocks).forEach(function (media) {
			if ('' !== media) {
				css += media + '{' + blocks[media] + '}';
			}
		});

		return css;
	}

	function render(id, field) {
		var style = document.getElementById('vlt-customizer-css-' + id);

		// A field this page overrides (Page Settings) keeps the page's CSS
		if (style && style.hasAttribute('data-vlt-override')) {
			return;
		}

		if (!style) {
			style = document.createElement('style');
			style.id = 'vlt-customizer-css-' + id;
			document.head.appendChild(style);
		}

		if ('typography' === field.type) {
			loadFont(settingValue(id));
		}

		style.textContent = fieldCss(id, field);
	}

	api.bind('preview-ready', function () {
		Object.keys(data.fields).forEach(function (id) {
			var field = data.fields[id];
			var watched = [id].concat(field.rules.map(function (rule) {
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
