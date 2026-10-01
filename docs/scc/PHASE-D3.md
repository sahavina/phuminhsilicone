# SCC — Giao diện theo mẫu, bước D3: thẻ sản phẩm kiểu cửa hàng + tab lọc danh mục

> Kế hoạch D1–D4: [PHASE-D1.md](PHASE-D1.md) mục 2. Mốc trước: [PHASE-D2.md](PHASE-D2.md).

## 1. Goal

- Thẻ sản phẩm như mẫu: nhãn **−%**, nút tròn trên ảnh (thêm vào giỏ / báo giá), tên 2 dòng, giá đỏ + giá gạch, dòng **thông số**, thanh **"Đã bán"** (tuỳ chọn, chỉ số thật).
- Lưới sản phẩm có **tab lọc danh mục** ("Tất cả" + danh mục).

Nghiệm thu: **khối "Sản phẩm nổi bật" của mẫu dựng được: tab + thẻ kiểu cửa hàng; chạy cả khi bán hàng lẫn chế độ catalogue.**

## 2. Architecture

| Phần | Ở đâu | Ghi chú |
|---|---|---|
| Tuỳ chọn | saha-core Theme Options → **Cửa hàng**: Kiểu thẻ (Mặc định / Cửa hàng), số dòng thông số (0–4), thanh "Đã bán" (tắt), mốc đầy thanh | |
| Thẻ | saha-theme `inc/card.php` + `_woocommerce.scss` | hook vòng lặp WooCommerce → áp cho shop, danh mục, element Sản phẩm, liên quan; body class `saha-cards-store` |
| Tab | saha-core element **Sản phẩm**: Tab lọc (Không / Theo danh mục), số tab 2–8, chữ tab đầu | mẫu ARIA tabs; JS trong `elements.js` |

| Quyết định | Lý do |
|---|---|
| Nút trên ảnh là link "thêm vào giỏ" AJAX chuẩn của WooCommerce (`add_to_cart_button ajax_add_to_cart`) | Dùng script, fragment giỏ, thông báo của WooCommerce; không viết lại giỏ hàng |
| Sản phẩm không mua được (catalogue, hết hàng) → nút "Yêu cầu báo giá" có sẵn tên + mã | Phù hợp SAHA đang ở chế độ catalogue |
| Biến thể → nút dẫn tới trang sản phẩm để chọn loại | Không thể thêm giỏ khi chưa chọn biến thể |
| Chế độ catalogue: không hiện nhãn −% | Giá bị ẩn thì % giảm vô nghĩa và lộ giá gián tiếp |
| Thanh "Đã bán" chỉ dùng `total_sales` thật, mặc định tắt, không có số giả | Mẫu có "Đã bán 126/500" — dễ là khan hiếm giả |
| Nút tròn đặt bằng `container-type: inline-size` + `100cqi` | Nút không thể nằm trong link ảnh (HTML không cho lồng); định vị theo bề rộng thẻ (ảnh vuông) |
| Tab dựng sẵn mọi lưới (ẩn), không AJAX | Dùng cache Catalog; ảnh tab ẩn tải lười; danh mục không có sản phẩm khớp tự bỏ tab |

## 3. Files

- saha-core 1.19.0: `includes/Builder/Elements/Products.php` (tách `queryArgs()`, `grid()`, thêm `tabs()`), `includes/ThemeOptions/Schema.php` (4 tuỳ chọn), `public/assets/js/elements.js` (tab), `public/assets/css/builder.css`.
- saha-theme 0.2.4: `inc/card.php` (mới), `functions.php` (nạp `card`), `src/scss/_woocommerce.scss`.

## 4. Database

Theme Options: `shop.card_style`, `shop.card_specs`, `shop.card_sold`, `shop.card_sold_goal`. Thông số lấy từ `_saha_specs` hiện có.

## 5–6. Code & Hooks

Thẻ dùng hook vòng lặp chuẩn: `woocommerce_after_shop_loop_item` (nút), `woocommerce_after_shop_loop_item_title` 15/20 (đã bán, thông số), filter `woocommerce_sale_flash` (nhãn %). Gắn ở `init` để canvas builder (REST) cũng ra thẻ mới.

## 7. Security

Mọi chữ escape; `aria-label` của nút có tên sản phẩm; link giỏ `rel="nofollow"`.

## 8. Testing

Tự động: smoke **219 passed** (+3: mặc định tab, từ chối giá trị lạ, mặc định thẻ/"Đã bán"); test JS 40 · lint sạch · http-smoke 33/0 · `wp saha qa` 91 đạt / 0 lỗi.

Đã thử trên trình duyệt (trang `/d3-the-san-pham-thu/`, shop):

- [x] Tab "Tất cả · Keo Silicone · Keo công nghiệp · PU Foam · Keo AB · Keo 502 · Chất tẩy"; ← → chuyển tab, focus đúng, chỉ một bảng hiện.
- [x] Catalogue bật: nút "Yêu cầu báo giá" trên ảnh → modal có sẵn "Keo chống thấm gốc PU"; không có nhãn %.
- [x] Catalogue tắt (tạm): nhãn −20/−30/−40% đúng giá thử; bấm nút giỏ trên thẻ → AJAX, giỏ = 1, không tải lại trang. (Đã bật lại catalogue, gỡ giá thử.)
- [x] Mobile 375: nút tròn cách góc ảnh 8px, tab cuộn ngang, không tràn ngang.

## 9. Installation

Theme Options → **Cửa hàng → Kiểu thẻ sản phẩm: Cửa hàng**. Builder → element **Sản phẩm** → **Tab lọc: Theo danh mục**.

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Thẻ như mẫu (nhãn %, nút trên ảnh, giá đỏ, thông số) | ✅ |
| Tab lọc danh mục | ✅ |
| Catalogue + bán hàng | ✅ cả hai |

## 11. Ghi chú & giới hạn

- Dữ liệu thử local: trang #125 "D3 — thẻ sản phẩm (thử)"; Theme Options đang để thẻ kiểu Cửa hàng.
- Tab chỉ theo danh mục sản phẩm (chưa theo thương hiệu/ứng dụng).
