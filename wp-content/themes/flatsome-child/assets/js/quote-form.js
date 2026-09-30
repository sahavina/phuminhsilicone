/**
 * SAHA — quote-form.js
 *
 * Xử lý form báo giá + form liên hệ và quote modal.
 *
 * - Gửi qua REST: POST /saha/v1/quote | /saha/v1/contact (window.SAHA.api tự gắn nonce,
 *   tự làm mới nonce nếu trang đến từ page cache).
 * - Disable nút ngay khi gửi để chống double submit (spec §84); backend vẫn chống trùng.
 * - Loading / success / error; lỗi từng field lấy từ response.errors.
 * - Không có JS: form POST tới admin-post.php vẫn hoạt động.
 */

(function () {
	'use strict';

	var helpers = window.SAHA || {};
	var i18n = (helpers.config && helpers.config.i18n) || {};

	var ENDPOINTS = { quote: 'quote', contact: 'contact' };
	var EVENTS = { quote: 'quote_submit', contact: 'contact_submit' };

	/* --- Tiện ích ------------------------------------------------------- */

	function setNotice(form, type, message) {
		var notice = form.querySelector('[data-saha-form-notice]');

		if (!notice) {
			return;
		}

		notice.textContent = message || '';
		notice.hidden = !message;

		if (type) {
			notice.setAttribute('data-type', type);
		} else {
			notice.removeAttribute('data-type');
		}
	}

	function clearErrors(form) {
		form.querySelectorAll('[data-saha-error]').forEach(function (node) {
			node.textContent = '';
		});

		form.querySelectorAll('[aria-invalid="true"]').forEach(function (field) {
			field.removeAttribute('aria-invalid');
			field.removeAttribute('aria-describedby');
		});
	}

	function showErrors(form, errors) {
		var first = null;

		Object.keys(errors || {}).forEach(function (name) {
			var slot = form.querySelector('[data-saha-error="' + name + '"]');
			var field = form.querySelector('[name="' + name + '"]');

			if (slot) {
				slot.textContent = errors[name];
				slot.id = slot.id || 'saha-err-' + name + '-' + Math.random().toString(36).slice(2, 8);
			}

			if (field && field.type !== 'hidden') {
				field.setAttribute('aria-invalid', 'true');

				if (slot) {
					field.setAttribute('aria-describedby', slot.id);
				}

				first = first || field;
			}
		});

		if (first) {
			first.focus();
		}
	}

	/**
	 * Kiểm tra phía client — chỉ để phản hồi nhanh, backend vẫn validate lại.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @param {string} type quote|contact.
	 * @returns {Object} Lỗi theo field.
	 */
	function validate(form, type) {
		var errors = {};
		var value = function (name) {
			var field = form.querySelector('[name="' + name + '"]');
			return field ? String(field.value).trim() : '';
		};

		if (!value('name')) {
			errors.name = 'Vui lòng nhập họ tên.';
		}

		var digits = value('phone').replace(/\D/g, '');

		if (!value('phone')) {
			errors.phone = 'Vui lòng nhập số điện thoại.';
		} else if (digits.length < 8 || digits.length > 15) {
			errors.phone = 'Số điện thoại không hợp lệ.';
		}

		var email = value('email');

		if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			errors.email = 'Email không hợp lệ.';
		}

		if (type === 'contact' && !value('message')) {
			errors.message = 'Vui lòng nhập nội dung cần tư vấn.';
		}

		return errors;
	}

	function setBusy(form, busy) {
		var button = form.querySelector('[data-saha-submit]');

		form.classList.toggle('saha-form--busy', busy);
		form.setAttribute('aria-busy', busy ? 'true' : 'false');

		if (button) {
			button.disabled = busy;
		}
	}

	function payload(form) {
		var data = {};

		new FormData(form).forEach(function (value, key) {
			// Không gửi field chỉ dành cho luồng admin-post.
			if (key === 'action') {
				return;
			}

			data[key] = typeof value === 'string' ? value : '';
		});

		return data;
	}

	/* --- Submit --------------------------------------------------------- */

	async function submit(form) {
		var type = form.getAttribute('data-saha-form');

		if (!ENDPOINTS[type] || form.classList.contains('saha-form--busy')) {
			return;
		}

		var source = form.querySelector('[data-saha-source-url]');

		if (source) {
			source.value = window.location.href.split('#')[0];
		}

		clearErrors(form);
		setNotice(form, '', '');

		var errors = validate(form, type);

		if (Object.keys(errors).length) {
			showErrors(form, errors);
			setNotice(form, 'error', 'Vui lòng kiểm tra lại thông tin.');
			return;
		}

		setBusy(form, true);
		setNotice(form, 'info', i18n.loading || 'Đang gửi…');

		try {
			var response = await helpers.api(ENDPOINTS[type], {
				method: 'POST',
				body: payload(form)
			});

			setNotice(form, 'success', response.message || i18n.submitted || 'Đã gửi yêu cầu.');

			if (helpers.track) {
				helpers.track(EVENTS[type], {
					productId: parseInt((form.querySelector('[name="product_id"]') || {}).value || '0', 10)
				});
			}

			// Giữ product_id/nguồn, xoá phần khách đã nhập.
			form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function (field) {
				field.value = '';
			});

			// Không mở lại nút ngay: tránh khách bấm gửi lần 2 do tưởng chưa gửi.
			window.setTimeout(function () {
				setBusy(form, false);
			}, 4000);
		} catch (error) {
			if (error && error.errors && Object.keys(error.errors).length) {
				showErrors(form, error.errors);
			}

			var message = (error && error.message) || i18n.error || 'Có lỗi xảy ra, vui lòng thử lại.';

			if (error && error.status === 429) {
				message = 'Bạn gửi quá nhanh, vui lòng thử lại sau ít phút.';
			}

			setNotice(form, 'error', message);
			setBusy(form, false);
		}
	}

	/* --- Modal ---------------------------------------------------------- */

	function openModal(detail) {
		var modal = document.querySelector('[data-saha-quote-modal]');

		if (!modal || typeof modal.showModal !== 'function') {
			return false;
		}

		var form = modal.querySelector('[data-saha-form="quote"]');

		if (form) {
			var productBox = form.querySelector('[data-saha-quote-product]');
			var idField = form.querySelector('[name="product_id"]');

			// Chỉ điền lại khi nút mở mang thông tin sản phẩm khác.
			if (detail && detail.productId && idField) {
				idField.value = String(detail.productId);

				var nameNode = form.querySelector('[data-saha-quote-product-name]');
				var skuNode = form.querySelector('[data-saha-quote-product-sku]');

				if (nameNode) {
					nameNode.textContent = detail.productName || '';
				}

				if (skuNode) {
					skuNode.textContent = detail.sku ? '(' + detail.sku + ')' : '';
				}

				if (productBox) {
					productBox.hidden = !detail.productName;
				}
			}

			clearErrors(form);
			setNotice(form, '', '');
		}

		modal.showModal();

		var firstInput = modal.querySelector('input:not([type="hidden"]):not([tabindex="-1"])');

		if (firstInput) {
			firstInput.focus();
		}

		return true;
	}

	function bindModal() {
		var modal = document.querySelector('[data-saha-quote-modal]');

		if (!modal) {
			return;
		}

		modal.addEventListener('click', function (event) {
			// Click vào backdrop (chính phần tử dialog) thì đóng.
			if (event.target === modal || event.target.closest('[data-saha-quote-close]')) {
				modal.close();
			}
		});
	}

	/* --- Khởi tạo ------------------------------------------------------- */

	function init() {
		document.querySelectorAll('[data-saha-form]').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				if (!helpers.api) {
					// main.js không có: để form POST thường qua admin-post.php.
					return;
				}

				event.preventDefault();
				submit(form);
			});
		});

		bindModal();

		// main.js bắn event này khi bấm nút [data-saha-open-quote].
		document.addEventListener('saha:quote:open', function (event) {
			if (openModal(event.detail) && event.detail && event.detail.originalEvent) {
				event.detail.originalEvent.preventDefault();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
