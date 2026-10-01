# SCC Phase 2 — Mốc 2.3: Trang sản phẩm nâng cao

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md); kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.2.md](PHASE-2.2.md), giao diện cửa hàng [PHASE-D4.md](PHASE-D4.md).

## 1. Goal

- **Swatches**: thuộc tính biến thể hiển thị thành ô chữ / ô màu / ô ảnh thay cho `<select>`.
- **Thanh "Thêm vào giỏ" dính**: cuộn qua nút mua → thanh dưới đáy (ảnh, tên, giá, nút).
- **Xem nhanh**: nút trên thẻ sản phẩm mở hộp thoại ảnh + giá + chọn biến thể + thêm vào giỏ, không rời trang.

Nghiệm thu: **biến thể chọn bằng swatch, thanh dính và xem nhanh thêm vào giỏ đúng biến thể.**

## 2. Architecture

```
woocommerce_dropdown_variation_attribute_options_html
   └─► Swatches: <div role="radiogroup"> nút + <select> gốc (ẩn, vẫn là nguồn sự thật)
          swatches.js: bấm nút → đổi select → trigger change → wc-add-to-cart-variation tính biến thể
                       woocommerce_update_variation_values → mờ ô không còn hợp lệ

wp_footer (is_product) ─► StickyCart: thanh dính
          sticky-cart.js: IntersectionObserver trên form → hiện/ẩn; nút → .single_add_to_cart_button / .saha-buy-now gốc

woocommerce_after_shop_loop_item (15) ─► QuickView: <button data-saha-quick-view="ID">
          quick-view.js ─► GET /saha/v1/products/{id}/quick-view → HTML → <dialog>.showModal()
                       └─► submit form → POST Store API /wc/store/v1/cart/add-item (nonce wc_store_api)
```

| Thành phần | Vai trò |
|---|---|
| `WooCommerce\Swatches` | kiểu thuộc tính (option `saha_woocommerce_settings`), trường admin cho thuộc tính và term (`saha_swatch_color`, `saha_swatch_image_id`), render ô chọn |
| `WooCommerce\StickyCart` | HTML thanh dính; catalogue → nút Yêu cầu báo giá |
| `WooCommerce\QuickView` | nút trên thẻ, enqueue (kèm `wc-add-to-cart-variation` + swatches), `QuickView::html()` |
| `api/routes/025-quick-view.php` | REST công khai, giới hạn 60 lần/phút/IP, `Cache-Control: no-store` |
| Theme Options → Cửa hàng | `swatches` (bật), `sticky_cart` (tắt), `quick_view` (tắt) |

| Quyết định | Lý do |
|---|---|
| Giữ `<select>` gốc, swatch chỉ điều khiển nó | Script biến thể của WooCommerce, plugin khác và form không JS vẫn chạy như cũ. |
| Thanh dính bấm **nút gốc** thay vì tự gửi form | Một đường thêm giỏ duy nhất: kiểm tra biến thể, số lượng, Mua ngay, catalogue không bị nhân đôi. |
| Xem nhanh lấy HTML qua REST (không nhúng sẵn vào thẻ) | Trang danh sách không nặng thêm; chỉ sản phẩm công khai + hiển thị được trả về (còn lại 404 — không lộ bản nháp/riêng tư). |
| Thêm giỏ trong hộp bằng **Store API** | Không tải lại trang; số trên icon giỏ cập nhật từ `items_count` trả về, không cần cart-fragments (đã tắt ở catalogue). |
| `<dialog>` + `showModal()` | Esc đóng, focus giữ trong hộp, đóng xong focus về nút đã bấm — không cần thư viện modal. |

## 3. Files

- `saha-core/includes/WooCommerce/{Swatches,StickyCart,QuickView}.php`, đăng ký trong `WooCommerce/Module.php`.
- `saha-core/api/routes/025-quick-view.php`.
- `saha-core/public/assets/js/{swatches,sticky-cart,quick-view}.js`, `css/{swatches,sticky-cart,quick-view}.css`.
- `saha-core/includes/ThemeOptions/Schema.php` — 3 tuỳ chọn mới ở nhóm Cửa hàng.
- SAHA Core **1.21.0**.

## 4. Accessibility

- Ô chọn: `role="radiogroup"` (có `aria-label` tên thuộc tính) / `role="radio"` + `aria-checked`, roving `tabindex`, phím mũi tên chuyển ô (bỏ qua ô bị khoá); ô màu/ảnh có tên đọc được (`screen-reader-text`); tổ hợp không hợp lệ → nút `disabled`.
- Thanh dính là `role="region"` có nhãn, dùng thuộc tính `hidden` khi ẩn (không lọt vào thứ tự tab).
- Hộp xem nhanh `aria-labelledby` tiêu đề; mở → focus tiêu đề; Esc / nút × / bấm nền → đóng.

## 5. Tests

| Kiểm tra | Kết quả |
|---|---|
| `tests/smoke.php` (thêm: chuẩn hoá mã màu, kiểu thuộc tính, mặc định Theme Options) | 230 passed |
| `tests/http-smoke.php` (thêm: quick-view ID không tồn tại → 404) | 34 passed, 2 skipped |
| `npm run lint:js`, `lint:css`, `test:js` | đạt — lưu ý: hai script lint chỉ quét theme + saha-builder, **không** quét JS/CSS trong `saha-core/public/assets` của mốc này (bổ sung ở mốc 2.5: `node --check`) |
| `wp saha qa` | 91 đạt, 3 cảnh báo (môi trường local), 0 lỗi |
| Trình duyệt (sản phẩm biến thể QA #136: Màu = ô màu, Dung tích = ô chữ) | chọn Đen + 300ml → đúng biến thể và giá; tổ hợp hết hàng hiện "Hết hàng"; phím mũi tên chạy |
| Thanh dính | hiện khi cuộn qua form; thêm giỏ qua nút gốc; chưa chọn biến thể → cuộn về form |
| Xem nhanh | mở hộp, focus tiêu đề, Esc trả focus; thêm giỏ qua Store API (số giỏ tăng); catalogue → nút báo giá đóng hộp và mở form báo giá |

## 6. Limitations / tiếp theo

- Hộp xem nhanh chỉ hiện ảnh đại diện (không có gallery).
- Mini cart drawer (mở sau khi thêm giỏ) thuộc mốc **2.4**.
