/**
 * SAHA — main.js
 *
 * Vanilla JS, ES6+. Không framework (spec §38).
 * Nhiệm vụ: analytics event, mở quote modal, tiện ích dùng chung cho các module khác.
 */

(function () {
	'use strict';

	var config = window.SAHA_CONFIG || {};

	/**
	 * Phát event analytics. Không hardcode Google Analytics (spec §49):
	 * chỉ push vào dataLayer nếu có, và bắn CustomEvent để tích hợp sau.
	 *
	 * @param {string} name Tên event.
	 * @param {Object} payload Dữ liệu kèm.
	 */
	function track(name, payload) {
		var detail = Object.assign({ event: 'saha_' + name }, payload || {});

		if (Array.isArray(window.dataLayer)) {
			window.dataLayer.push(detail);
		}

		document.dispatchEvent(new CustomEvent('saha:' + name, { detail: detail }));
	}

	/**
	 * Debounce dùng chung cho search/filter.
	 *
	 * @param {Function} fn Hàm cần hoãn.
	 * @param {number} wait Thời gian chờ (ms).
	 * @returns {Function}
	 */
	function debounce(fn, wait) {
		var timer = null;

		return function () {
			var args = arguments;
			var self = this;

			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				fn.apply(self, args);
			}, wait || 250);
		};
	}

	/**
	 * Fetch JSON tới REST API của plugin, luôn kèm nonce cho request ghi.
	 *
	 * @param {string} path Đường dẫn sau namespace.
	 * @param {Object} options Tuỳ chọn fetch.
	 * @returns {Promise<Object>}
	 */
	async function api(path, options) {
		var opts = options || {};
		var headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});

		if (opts.body && !headers['Content-Type']) {
			headers['Content-Type'] = 'application/json';
		}

		if (config.nonce) {
			headers['X-WP-Nonce'] = config.nonce;
		}

		var base = (config.restUrl || '/wp-json/saha/v1/').replace(/\/?$/, '/');
		var response = await window.fetch(base + String(path).replace(/^\//, ''), {
			method: opts.method || 'GET',
			headers: headers,
			body: opts.body ? JSON.stringify(opts.body) : undefined,
			credentials: 'same-origin',
			signal: opts.signal
		});

		var data = null;

		try {
			data = await response.json();
		} catch (error) {
			data = null;
		}

		if (!response.ok) {
			var message = (data && data.message) || (config.i18n && config.i18n.error) || 'Request failed';
			var failure = new Error(message);

			failure.status = response.status;
			failure.errors = (data && data.errors) || {};

			throw failure;
		}

		return data || {};
	}

	/**
	 * Gắn tracking cho CTA điện thoại / Zalo.
	 */
	function bindCtaTracking() {
		document.addEventListener('click', function (event) {
			var target = event.target.closest('[data-saha-event]');

			if (!target) {
				return;
			}

			track(target.getAttribute('data-saha-event'), {
				region: target.getAttribute('data-saha-region') || '',
				href: target.getAttribute('href') || ''
			});
		});
	}

	/**
	 * Nút mở form báo giá. Module quote-form.js (Phase 4) sẽ lắng nghe event này.
	 */
	function bindQuoteTriggers() {
		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-saha-open-quote]');

			if (!trigger) {
				return;
			}

			event.preventDefault();

			document.dispatchEvent(
				new CustomEvent('saha:quote:open', {
					detail: {
						productId: parseInt(trigger.getAttribute('data-saha-product-id') || '0', 10),
						productName: trigger.getAttribute('data-saha-product-name') || '',
						sku: trigger.getAttribute('data-saha-sku') || ''
					}
				})
			);
		});
	}

	/**
	 * Bắn event view_product trên trang sản phẩm.
	 */
	function trackProductView() {
		var node = document.querySelector('[data-saha-product-view]');

		if (!node) {
			return;
		}

		track('view_product', {
			productId: parseInt(node.getAttribute('data-saha-product-view') || '0', 10),
			sku: node.getAttribute('data-saha-sku') || ''
		});
	}

	// API công khai cho các module khác của theme.
	window.SAHA = window.SAHA || {};
	window.SAHA.track = track;
	window.SAHA.debounce = debounce;
	window.SAHA.api = api;
	window.SAHA.config = config;

	function init() {
		bindCtaTracking();
		bindQuoteTriggers();
		trackProductView();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
