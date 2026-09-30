/**
 * SAHA — search.js
 *
 * Autocomplete tìm kiếm sản phẩm: debounce, loading/empty/error state,
 * điều hướng bằng bàn phím. Gọi GET /wp-json/saha/v1/search (Phase 3).
 *
 * Markup yêu cầu:
 * <div class="saha-search" data-saha-search>
 *   <input class="saha-search__input" type="search" data-saha-search-input>
 *   <div class="saha-search__panel" data-saha-search-panel hidden></div>
 * </div>
 */

(function () {
	'use strict';

	var helpers = window.SAHA || {};
	var i18n = (helpers.config && helpers.config.i18n) || {};
	var MIN_CHARS = 2;
	var DEBOUNCE_MS = 300;

	/**
	 * Escape text trước khi nhúng vào HTML.
	 *
	 * @param {string} value Giá trị thô.
	 * @returns {string}
	 */
	function escapeHtml(value) {
		var div = document.createElement('div');

		div.textContent = value == null ? '' : String(value);

		return div.innerHTML;
	}

	/**
	 * Khởi tạo một widget search.
	 *
	 * @param {HTMLElement} root Container.
	 */
	function initWidget(root) {
		var input = root.querySelector('[data-saha-search-input]');
		var panel = root.querySelector('[data-saha-search-panel]');

		if (!input || !panel || !helpers.api) {
			return;
		}

		var controller = null;
		var activeIndex = -1;

		input.setAttribute('autocomplete', 'off');
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-autocomplete', 'list');

		function close() {
			panel.hidden = true;
			panel.innerHTML = '';
			activeIndex = -1;
			input.setAttribute('aria-expanded', 'false');
		}

		function showMessage(message) {
			panel.hidden = false;
			panel.innerHTML = '<p class="saha-search__status">' + escapeHtml(message) + '</p>';
			input.setAttribute('aria-expanded', 'true');
		}

		function showSkeleton() {
			panel.hidden = false;
			panel.innerHTML =
				'<div class="saha-search__skeleton" aria-live="polite" aria-busy="true">' +
				'<div class="saha-skeleton-line" style="width:80%"></div>' +
				'<div class="saha-skeleton-line" style="width:60%"></div>' +
				'<div class="saha-skeleton-line" style="width:70%"></div>' +
				'</div>';
			input.setAttribute('aria-expanded', 'true');
		}

		function render(items) {
			if (!items.length) {
				showMessage(i18n.noResult || 'Không tìm thấy sản phẩm phù hợp.');
				return;
			}

			var html = items
				.map(function (item) {
					var meta = [item.sku, item.brand, item.category]
						.filter(Boolean)
						.map(function (part) {
							return '<span>' + escapeHtml(part) + '</span>';
						})
						.join('');

					var thumb = item.thumbnail
						? '<img class="saha-search-result__thumb" src="' +
						  encodeURI(item.thumbnail) +
						  '" alt="' +
						  escapeHtml(item.name) +
						  '" width="48" height="48" loading="lazy">'
						: '<span class="saha-search-result__thumb" aria-hidden="true"></span>';

					return (
						'<a class="saha-search-result" role="option" aria-selected="false" href="' +
						encodeURI(item.url || '#') +
						'">' +
						thumb +
						'<span class="saha-search-result__body">' +
						'<span class="saha-search-result__name">' +
						escapeHtml(item.name) +
						'</span>' +
						(meta ? '<span class="saha-search-result__meta">' + meta + '</span>' : '') +
						'</span></a>'
					);
				})
				.join('');

			panel.hidden = false;
			panel.innerHTML = '<div class="saha-search__list" role="listbox">' + html + '</div>';
			activeIndex = -1;
			input.setAttribute('aria-expanded', 'true');
		}

		async function query(term) {
			if (controller) {
				controller.abort();
			}

			controller = new AbortController();

			showSkeleton();

			try {
				var response = await helpers.api(
					'search?q=' + encodeURIComponent(term) + '&limit=8',
					{ signal: controller.signal }
				);
				var items = (response && response.data && response.data.items) || [];

				render(items);

				if (helpers.track) {
					helpers.track('search', { query: term, resultCount: items.length });
				}
			} catch (error) {
				if (error && 'AbortError' === error.name) {
					return;
				}

				showMessage(i18n.error || 'Có lỗi xảy ra, vui lòng thử lại.');
			}
		}

		var onInput = helpers.debounce
			? helpers.debounce(function () {
					var term = input.value.trim();

					if (term.length < MIN_CHARS) {
						close();
						return;
					}

					query(term);
			  }, DEBOUNCE_MS)
			: function () {};

		input.addEventListener('input', onInput);

		input.addEventListener('keydown', function (event) {
			var options = panel.querySelectorAll('.saha-search-result');

			if ('Escape' === event.key) {
				close();
				return;
			}

			if (!options.length) {
				return;
			}

			if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
				event.preventDefault();

				activeIndex =
					'ArrowDown' === event.key
						? (activeIndex + 1) % options.length
						: (activeIndex - 1 + options.length) % options.length;

				options.forEach(function (option, index) {
					option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
				});

				options[activeIndex].focus();
				return;
			}

			if ('Enter' === event.key && activeIndex > -1) {
				event.preventDefault();
				options[activeIndex].click();
			}
		});

		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) {
				close();
			}
		});
	}

	function init() {
		document.querySelectorAll('[data-saha-search]').forEach(initWidget);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
