/* ========================================
 * Customizer Section Icons (Vanilla JS) — Safe Load
 * ======================================== */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	if (typeof wp !== 'undefined' && wp.customize && wp.customize.bind) {
		wp.customize.bind('ready', runCustomizerEnhancements);
	} else {
		setTimeout(runCustomizerEnhancements, 100);
	}

	function runCustomizerEnhancements() {
		/* ========================================
		 * Custom CSS Section
		 * ======================================== */
		const customCssH3 = document.querySelector('#accordion-section-custom_css > h3');
		if (customCssH3 && !customCssH3.classList.contains('dashicons-before')) {
			customCssH3.classList.add('dashicons-before', 'dashicons-admin-appearance');
		}

		const customCssSubH3 = document.querySelector('#sub-accordion-section-custom_css .customize-section-title > h3');
		if (customCssSubH3 && !customCssSubH3.querySelector('.dashicons-admin-appearance')) {
			const span = document.createElement('span');
			span.className = 'dashicons-before dashicons-admin-appearance';
			span.style.cssText = 'padding-right:.2em;padding-top:2px;float:left;';
			customCssSubH3.insertBefore(span, customCssSubH3.firstChild);
		}

		/* ========================================
		 * Static Front Page Section
		 * ======================================== */
		const staticFrontH3 = document.querySelector('#accordion-section-static_front_page > h3');
		if (staticFrontH3 && !staticFrontH3.classList.contains('dashicons-before')) {
			staticFrontH3.classList.add('dashicons-before', 'dashicons-flag');
		}

		const staticFrontSubH3 = document.querySelector('#sub-accordion-section-static_front_page .customize-section-title > h3');
		if (staticFrontSubH3 && !staticFrontSubH3.querySelector('.dashicons-flag')) {
			const span = document.createElement('span');
			span.className = 'dashicons-before dashicons-flag';
			span.style.cssText = 'padding-right:.2em;padding-top:2px;float:left;';
			staticFrontSubH3.insertBefore(span, staticFrontSubH3.firstChild);
		}

		/* ========================================
		 * Site Identity (Title & Tagline)
		 * ======================================== */
		const titleTaglineH3 = document.querySelector('#accordion-section-title_tagline > h3');
		if (titleTaglineH3 && !titleTaglineH3.classList.contains('dashicons-before')) {
			titleTaglineH3.classList.add('dashicons-before', 'dashicons-art');
		}

		const titleTaglineSubH3 = document.querySelector('#sub-accordion-section-title_tagline .customize-section-title > h3');
		if (titleTaglineSubH3 && !titleTaglineSubH3.querySelector('.dashicons-art')) {
			const span = document.createElement('span');
			span.className = 'dashicons-before dashicons-art';
			span.style.cssText = 'padding-right:.2em;padding-top:2px;float:left;';
			titleTaglineSubH3.insertBefore(span, titleTaglineSubH3.firstChild);
		}

		/* ========================================
		 * Widgets Panel
		 * ======================================== */
		const widgetsPanelH3 = document.querySelector('#accordion-panel-widgets > h3');
		if (widgetsPanelH3 && !widgetsPanelH3.classList.contains('dashicons-before')) {
			widgetsPanelH3.classList.add('dashicons-before', 'dashicons-welcome-widgets-menus');
		}

		const widgetsSubPanel = document.querySelector('#sub-accordion-panel-widgets .panel-title');
		if (widgetsSubPanel && !widgetsSubPanel.querySelector('.dashicons-welcome-widgets-menus')) {
			const span = document.createElement('span');
			span.className = 'dashicons-before dashicons-welcome-widgets-menus';
			span.style.cssText = 'position:relative;padding-right:.2em;top:2px;';
			widgetsSubPanel.insertBefore(span, widgetsSubPanel.firstChild);
		}

		/* ========================================
		 * WooCommerce Panel
		 * ======================================== */
		const woocommercePanelH3 = document.querySelector('#accordion-panel-woocommerce > h3');
		if (woocommercePanelH3 && !woocommercePanelH3.classList.contains('dashicons-before')) {
			woocommercePanelH3.classList.add('dashicons-before', 'dashicons-cart');
		}

		const woocommerceSubPanel = document.querySelector('#sub-accordion-panel-woocommerce .panel-title');
		if (woocommerceSubPanel && !woocommerceSubPanel.querySelector('.dashicons-cart')) {
			const span = document.createElement('span');
			span.className = 'dashicons-before dashicons-cart';
			span.style.cssText = 'position:relative;padding-right:.2em;top:2px;';
			woocommerceSubPanel.insertBefore(span, woocommerceSubPanel.firstChild);
		}

		/* ========================================
		 * Menus Panel
		 * ======================================== */
		const menusPanelH3 = document.querySelector('#accordion-panel-nav_menus > h3');
		if (menusPanelH3 && !menusPanelH3.classList.contains('dashicons-before')) {
			menusPanelH3.classList.add('dashicons-before', 'dashicons-menu');
		}

		const menusSubPanel = document.querySelector('#sub-accordion-panel-nav_menus .panel-title');
		if (menusSubPanel && !menusSubPanel.querySelector('.dashicons-menu')) {
			const span = document.createElement('span');
			span.className = 'dashicons-before dashicons-menu';
			span.style.cssText = 'position:relative;padding-right:.2em;top:2px;';
			menusSubPanel.insertBefore(span, menusSubPanel.firstChild);
		}

	}

});