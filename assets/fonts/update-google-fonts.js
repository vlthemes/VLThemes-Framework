#!/usr/bin/env node

/**
 * Updates google-fonts.json with the latest font list from the
 * Google Fonts Developer API.
 *
 * Usage:
 *   node update-google-fonts.js
 *   (reads GOOGLE_FONTS_API_KEY from the theme's .env file, or from the
 *   environment if already set, e.g. GOOGLE_FONTS_API_KEY=xxx node update-google-fonts.js)
 *
 * Get a free key at: https://developers.google.com/fonts/docs/developer_api
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

/**
 * Minimal .env loader (KEY=VALUE per line, no quoting/escaping support)
 */
function loadEnvFile(envPath) {
	if (!fs.existsSync(envPath)) {
		return;
	}

	fs.readFileSync(envPath, 'utf8').split('\n').forEach((line) => {
		const match = line.match(/^\s*([\w.-]+)\s*=\s*(.*)\s*$/);

		if (match && !process.env[match[1]]) {
			process.env[match[1]] = match[2];
		}
	});
}

loadEnvFile(path.join(__dirname, '../../../.env'));

const apiKey = process.env.GOOGLE_FONTS_API_KEY;

if (!apiKey) {
	console.error('✗ Missing GOOGLE_FONTS_API_KEY. Add it to the theme\'s .env file or pass it as an env var.');
	process.exit(1);
}

const destFile = path.join(__dirname, 'google-fonts.json');

function fetchJSON(url) {
	return new Promise((resolve, reject) => {
		https.get(url, (res) => {
			let body = '';
			res.on('data', (chunk) => { body += chunk; });
			res.on('end', () => {
				if (res.statusCode !== 200) {
					reject(new Error('Google Fonts API returned status ' + res.statusCode + ': ' + body));
					return;
				}
				try {
					resolve(JSON.parse(body));
				} catch (err) {
					reject(err);
				}
			});
		}).on('error', reject);
	});
}

async function run() {
	console.log('→ Fetching font list from the Google Fonts API...');

	const [alphaData, popularityData, trendingData] = await Promise.all([
		fetchJSON('https://www.googleapis.com/webfonts/v1/webfonts?sort=alpha&key=' + apiKey),
		fetchJSON('https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=' + apiKey),
		fetchJSON('https://www.googleapis.com/webfonts/v1/webfonts?sort=trending&key=' + apiKey)
	]);

	const result = {
		items: {},
		order: {
			alpha: [],
			popularity: [],
			trending: []
		}
	};

	alphaData.items.forEach((font) => {
		result.order.alpha.push(font.family);
		result.items[font.family] = {
			family: font.family,
			category: font.category,
			variants: [...font.variants].sort()
		};
	});

	popularityData.items.forEach((font) => {
		result.order.popularity.push(font.family);
	});

	trendingData.items.forEach((font) => {
		result.order.trending.push(font.family);
	});

	fs.writeFileSync(destFile, JSON.stringify(result));

	console.log('✓ google-fonts.json updated, fonts: ' + alphaData.items.length);
}

run().catch((err) => {
	console.error('✗ Update failed:', err.message);
	process.exit(1);
});
