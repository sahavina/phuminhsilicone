/**
 * SAHA — product-filter.js
 *
 * PHASE 3 (Search & Filter). File đã được register trong inc/enqueue.php nhưng
 * CHƯA enqueue — Phase 3 chỉ cần gọi wp_enqueue_script( 'saha-product-filter' ).
 *
 * Hợp đồng đã chốt ở kiến trúc (spec §10):
 * - Filter theo category, brand, price, availability, attribute.
 * - AJAX là tiến bộ dần (progressive enhancement): form phải submit bình thường
 *   được khi JS lỗi/tắt — KHÔNG phụ thuộc hoàn toàn JavaScript.
 * - Mọi lần filter phải ghi state vào URL bằng history.pushState để copy link
 *   và SEO hoạt động đúng.
 * - Có loading state; huỷ request cũ bằng AbortController.
 *
 * Markup dự kiến:
 * <form class="saha-filter" data-saha-filter method="get" action="{archive_url}">…</form>
 * <div data-saha-filter-results>…</div>
 */

(function () {
	'use strict';

	var root = document.querySelector('[data-saha-filter]');

	if (!root) {
		return;
	}

	// Đánh dấu cho CSS biết JS đã sẵn sàng; logic AJAX triển khai ở Phase 3.
	root.classList.add('saha-filter--enhanced');
})();
