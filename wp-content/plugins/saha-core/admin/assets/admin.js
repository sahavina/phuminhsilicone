/**
 * SAHA Core — admin.js
 *
 * Media picker cho logo/banner thương hiệu, repeater thông số, danh sách tài liệu.
 * Vanilla JS + wp.media (đã có sẵn trong admin).
 */

(function () {
	'use strict';

	/**
	 * Mở wp.media và trả về attachment đã chọn.
	 *
	 * @param {Object} options title, type, multiple.
	 * @param {Function} onSelect Callback nhận mảng attachment.
	 */
	function openMedia(options, onSelect) {
		if (!window.wp || !window.wp.media) {
			return;
		}

		var frame = window.wp.media({
			title: options.title,
			library: options.type ? { type: options.type } : {},
			button: { text: options.button || 'Chọn' },
			multiple: !!options.multiple
		});

		frame.on('select', function () {
			var selection = frame.state().get('selection');

			onSelect(
				selection.map(function (item) {
					return item.toJSON();
				})
			);
		});

		frame.open();
	}

	/* --- Media field (logo / banner) ------------------------------------ */

	function initMediaFields() {
		document.querySelectorAll('[data-saha-media]').forEach(function (field) {
			var input = field.querySelector('[data-saha-media-input]');
			var preview = field.querySelector('[data-saha-media-preview]');
			var selectBtn = field.querySelector('[data-saha-media-select]');
			var removeBtn = field.querySelector('[data-saha-media-remove]');

			if (!input || !selectBtn) {
				return;
			}

			selectBtn.addEventListener('click', function () {
				openMedia({ title: 'Chọn hình ảnh', type: 'image' }, function (items) {
					var item = items[0];

					if (!item) {
						return;
					}

					input.value = item.id;

					if (preview) {
						var url = (item.sizes && item.sizes.medium && item.sizes.medium.url) || item.url;

						preview.innerHTML = '';

						var img = document.createElement('img');

						img.src = url;
						img.alt = item.alt || '';
						img.style.maxWidth = '200px';
						img.style.height = 'auto';
						preview.appendChild(img);
					}
				});
			});

			if (removeBtn) {
				removeBtn.addEventListener('click', function () {
					input.value = '0';

					if (preview) {
						preview.innerHTML = '';
					}
				});
			}
		});
	}

	/* --- Repeater thông số ---------------------------------------------- */

	/**
	 * Lấy index kế tiếp cho hàng mới.
	 *
	 * @param {HTMLElement} container Container.
	 * @param {string} selector Selector hàng.
	 * @returns {number}
	 */
	function nextIndex(container, selector) {
		return container.querySelectorAll(selector).length;
	}

	/**
	 * Render template wp.template với fallback thay chuỗi đơn giản.
	 *
	 * @param {string} id Template id (không có tiền tố tmpl-).
	 * @param {Object} data Dữ liệu.
	 * @returns {string}
	 */
	function renderTemplate(id, data) {
		var node = document.getElementById('tmpl-' + id);

		if (!node) {
			return '';
		}

		var html = node.innerHTML;

		Object.keys(data).forEach(function (key) {
			html = html.split('{{data.' + key + '}}').join(String(data[key] == null ? '' : data[key]));
		});

		return html;
	}

	function initRepeaters() {
		document.querySelectorAll('[data-saha-repeater]').forEach(function (repeater) {
			var body = repeater.querySelector('[data-saha-repeater-body]');
			var addBtn = repeater.querySelector('[data-saha-repeater-add]');

			if (!body || !addBtn) {
				return;
			}

			addBtn.addEventListener('click', function () {
				var html = renderTemplate('saha-repeater-row', {
					index: nextIndex(body, '[data-saha-repeater-row]')
				});

				if (html) {
					body.insertAdjacentHTML('beforeend', html);
				}
			});

			repeater.addEventListener('click', function (event) {
				var remove = event.target.closest('[data-saha-repeater-remove]');

				if (!remove) {
					return;
				}

				var row = remove.closest('[data-saha-repeater-row]');

				if (row) {
					row.remove();
				}
			});
		});
	}

	/* --- Danh sách tài liệu --------------------------------------------- */

	function initDocs() {
		document.querySelectorAll('[data-saha-docs]').forEach(function (docs) {
			var list = docs.querySelector('[data-saha-docs-list]');
			var addBtn = docs.querySelector('[data-saha-docs-add]');

			if (!list || !addBtn) {
				return;
			}

			addBtn.addEventListener('click', function () {
				openMedia({ title: 'Chọn tài liệu', multiple: true }, function (items) {
					items.forEach(function (item) {
						var html = renderTemplate('saha-docs-row', {
							index: nextIndex(list, '[data-saha-docs-row]'),
							id: item.id,
							title: item.title || item.filename || ''
						});

						if (html) {
							list.insertAdjacentHTML('beforeend', html);
						}
					});
				});
			});

			docs.addEventListener('click', function (event) {
				var remove = event.target.closest('[data-saha-docs-remove]');

				if (!remove) {
					return;
				}

				var row = remove.closest('[data-saha-docs-row]');

				if (row) {
					row.remove();
				}
			});
		});
	}

	function init() {
		initMediaFields();
		initRepeaters();
		initDocs();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
