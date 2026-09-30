/**
 * SAHA — quote-form.js
 *
 * PHASE 4 (Quote/Lead). File đã register trong inc/enqueue.php nhưng CHƯA enqueue —
 * Phase 4 chỉ cần wp_enqueue_script( 'saha-quote-form' ) trên is_product() và
 * trang báo giá.
 *
 * Hợp đồng đã chốt ở kiến trúc:
 * - POST /wp-json/saha/v1/quote với X-WP-Nonce (window.SAHA.api đã tự gắn).
 * - Payload: name, phone, email, company, product_id, product_name, sku,
 *   quantity, message, source_url + field honeypot saha_hp_email.
 * - Disable nút submit ngay khi gửi để chống double submit (spec §84);
 *   backend vẫn có rate limit riêng.
 * - Hiển thị loading / success / error; lỗi field lấy từ response.errors.
 * - Lắng nghe event 'saha:quote:open' do main.js bắn ra để mở modal.
 *
 * Markup dự kiến:
 * <form class="saha-quote-form" data-saha-quote-form>…</form>
 */

(function () {
	'use strict';

	document.addEventListener('saha:quote:open', function (event) {
		var form = document.querySelector('[data-saha-quote-form]');

		if (!form) {
			return;
		}

		var detail = event.detail || {};
		var map = {
			product_id: detail.productId,
			product_name: detail.productName,
			sku: detail.sku
		};

		Object.keys(map).forEach(function (name) {
			var field = form.querySelector('[name="' + name + '"]');

			if (field && map[name]) {
				field.value = map[name];
			}
		});

		// Mở modal và submit AJAX: triển khai ở Phase 4.
	});
})();
