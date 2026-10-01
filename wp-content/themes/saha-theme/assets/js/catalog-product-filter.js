/**
 * SAHA — product-filter.js
 *
 * Tăng cường bộ lọc sản phẩm bằng AJAX + history.pushState.
 *
 * Nguyên tắc (spec §10):
 * - Form là form GET thật. Nếu JS lỗi hoặc bị tắt, bộ lọc vẫn hoạt động.
 * - Mọi thay đổi đều ghi vào URL để copy link và SEO đúng.
 * - Nếu không tìm thấy container kết quả, rơi về điều hướng thường.
 */

(function () {
	'use strict';

	var helpers = window.SAHA || {};
	var i18n = (helpers.config && helpers.config.i18n) || {};

	/**
	 * Selector container chứa danh sách sản phẩm, thử lần lượt.
	 */
	var RESULT_SELECTORS = ['[data-saha-filter-results]', '.shop-container', '.woocommerce-products-wrapper', 'main'];

	var controller = null;

	/**
	 * Tìm container kết quả trong một document.
	 *
	 * @param {Document|HTMLElement} scope Phạm vi tìm.
	 * @returns {HTMLElement|null}
	 */
	function findResults(scope) {
		for (var i = 0; i < RESULT_SELECTORS.length; i++) {
			var node = scope.querySelector(RESULT_SELECTORS[i]);

			if (node) {
				return node;
			}
		}

		return null;
	}

	/**
	 * Dựng URL từ dữ liệu form, bỏ field rỗng để URL sạch.
	 *
	 * @param {HTMLFormElement} form Form lọc.
	 * @returns {string}
	 */
	function buildUrl(form) {
		var base = form.getAttribute('data-saha-filter-base') || form.action;
		var params = new URLSearchParams();

		new FormData(form).forEach(function (value, key) {
			if (String(value).trim() === '') {
				return;
			}

			params.append(key, value);
		});

		var query = params.toString();

		return query ? base + (base.indexOf('?') > -1 ? '&' : '?') + query : base;
	}

	/**
	 * Đặt trạng thái đang tải.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @param {boolean} loading Đang tải.
	 */
	function setLoading(form, loading) {
		var status = form.querySelector('[data-saha-filter-status]');
		var target = findResults(document);

		form.classList.toggle('saha-filter--loading', loading);

		if (target) {
			target.setAttribute('aria-busy', loading ? 'true' : 'false');
		}

		if (status) {
			status.textContent = loading ? i18n.loading || 'Đang tải…' : '';
		}
	}

	/**
	 * Tải kết quả theo URL và thay nội dung.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @param {string} url URL đích.
	 * @param {boolean} push Có ghi history không.
	 */
	async function load(form, url, push) {
		var target = findResults(document);

		if (!target || !window.DOMParser) {
			window.location.href = url;
			return;
		}

		if (controller) {
			controller.abort();
		}

		controller = new AbortController();
		setLoading(form, true);

		try {
			var response = await window.fetch(url, {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				signal: controller.signal
			});

			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}

			var html = await response.text();
			var doc = new DOMParser().parseFromString(html, 'text/html');
			var fresh = findResults(doc);

			if (!fresh) {
				window.location.href = url;
				return;
			}

			target.innerHTML = fresh.innerHTML;
			syncForm(form, doc);

			if (push && window.history && window.history.pushState) {
				window.history.pushState({ sahaFilter: true }, '', url);
			}

			if (doc.title) {
				document.title = doc.title;
			}

			target.scrollIntoView({ behavior: 'smooth', block: 'start' });

			document.dispatchEvent(new CustomEvent('saha:filter:updated', { detail: { url: url } }));
		} catch (error) {
			if (error && error.name === 'AbortError') {
				return;
			}

			// Lỗi mạng: điều hướng thường để người dùng vẫn xem được kết quả.
			window.location.href = url;
		} finally {
			setLoading(form, false);
		}
	}

	/**
	 * Đồng bộ ô đã chọn của form theo trang vừa tải (khi bỏ lọc bằng chip, sắp xếp, quay lại…).
	 *
	 * @param {HTMLFormElement} form Form.
	 * @param {Document} doc Trang vừa tải.
	 */
	function syncForm(form, doc) {
		var fresh = doc.querySelector('[data-saha-filter]');

		if (!fresh) {
			return;
		}

		form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (input) {
			var match = Array.prototype.find.call(fresh.querySelectorAll('input'), function (other) {
				return other.name === input.name && other.value === input.value;
			});

			input.checked = !!(match && match.checked);
		});
	}

	/**
	 * URL sắp xếp: giữ điều kiện lọc (field ẩn của WooCommerce), bỏ phân trang.
	 *
	 * @param {HTMLFormElement} ordering Form sắp xếp của WooCommerce.
	 * @returns {string}
	 */
	function orderingUrl(ordering) {
		var base = window.location.pathname.replace(/\/page\/\d+\/?$/, '/');
		var params = new URLSearchParams();

		new FormData(ordering).forEach(function (value, key) {
			if (key !== 'paged' && String(value).trim() !== '') {
				params.append(key, value);
			}
		});

		var query = params.toString();

		return window.location.origin + base + (query ? '?' + query : '');
	}

	/**
	 * Gắn hành vi cho một form lọc.
	 *
	 * @param {HTMLFormElement} form Form.
	 */
	function initForm(form) {
		form.classList.add('saha-filter--enhanced');

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			load(form, buildUrl(form), true);
		});

		// Checkbox/radio: áp dụng ngay. Ô giá: chờ người dùng gõ xong.
		form.addEventListener('change', function (event) {
			var field = event.target;

			if (!field || (field.type !== 'checkbox' && field.type !== 'radio')) {
				return;
			}

			load(form, buildUrl(form), true);
		});

		var debounced = helpers.debounce
			? helpers.debounce(function () {
					load(form, buildUrl(form), true);
			  }, 600)
			: null;

		if (debounced) {
			form.querySelectorAll('input[type="number"]').forEach(function (input) {
				input.addEventListener('input', debounced);
			});
		}

		// Sắp xếp trong vùng kết quả: AJAX thay cho submit của WooCommerce (bắt ở pha capture,
		// trước handler jQuery gắn trên form).
		document.addEventListener(
			'change',
			function (event) {
				var select = event.target;
				var ordering = select && select.closest ? select.closest('.woocommerce-ordering') : null;
				var results = findResults(document);

				if (!ordering || !results || !results.contains(ordering)) {
					return;
				}

				event.stopPropagation();
				load(form, orderingUrl(ordering), true);
			},
			true
		);

		// Phân trang, bỏ một điều kiện lọc, "Xoá tất cả" trong vùng kết quả cũng đi qua AJAX.
		document.addEventListener('click', function (event) {
			var link = event.target.closest('.woocommerce-pagination a, .page-numbers a, .saha-shop__chip, .saha-shop__clear');

			if (!link || !findResults(document) || !findResults(document).contains(link)) {
				return;
			}

			event.preventDefault();
			load(form, link.href, true);
		});

		window.addEventListener('popstate', function (event) {
			if (event.state && event.state.sahaFilter) {
				load(form, window.location.href, false);
			}
		});
	}

	function init() {
		document.querySelectorAll('[data-saha-filter]').forEach(initForm);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
