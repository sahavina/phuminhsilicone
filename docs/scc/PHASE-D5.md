# SCC — Giao diện theo mẫu, bước D5: trang danh mục sản phẩm kiểu cửa hàng

> Mốc trước: [PHASE-D4.md](PHASE-D4.md). Mẫu: trang danh mục "Két sắt chống cháy" (khung tiêu đề, cột lọc trái, thanh "Đang hiện · Sắp xếp").

## 1. Goal

Trang shop / danh mục / thương hiệu như mẫu:

- Khung tiêu đề: H1 chữ hoa, mô tả danh mục, thẻ **"N sản phẩm"** (số thật) và nhãn tuỳ chọn ("Giao hàng toàn quốc").
- Cột trái: **Danh mục** (Tất cả sản phẩm + danh mục gốc, số lượng, mục đang xem tô nền, nhánh đang xem mở danh mục con), **Khoảng giá** (radio), Thương hiệu, Ứng dụng, Tình trạng — mỗi nhóm một hộp.
- Cột phải: thanh **"Đang hiện x / y sản phẩm"** + **Sắp xếp theo**, các điều kiện đang lọc (bỏ từng cái / Xoá tất cả), lưới thẻ kiểu cửa hàng (D3), phân trang.
- Mobile/tablet (< 1024px): cột lọc thành **ngăn trượt** mở bằng nút "Danh mục & bộ lọc (n)".

Nghiệm thu: **template Shop & danh mục dựng bằng builder ra bố cục như mẫu; lọc, sắp xếp, bỏ lọc chạy AJAX và vẫn chạy khi không có JS; catalogue không lộ giá.**

## 2. Architecture

| Phần | Ở đâu | Ghi chú |
|---|---|---|
| Khung tiêu đề | saha-core element **Tiêu đề danh sách (động)**: `style` (plain / card), `showCount`, `badge`, `badgeIcon` | số sản phẩm: term meta `product_count_*` của WooCommerce (gồm danh mục con) |
| Bố cục cột lọc | saha-core element **Danh sách sản phẩm (động)**: `layout` (stack / sidebar), `categories`, `price` | cột trái = danh mục (core) + hook `saha_product_archive_sidebar` (theme in bộ lọc) |
| Khoảng giá | `Saha\Core\Filter`: query var `saha_price` ("min-max"), `price_ranges()`, `buckets()`, `price_label()`, `active_items()` | mốc ở phân vị 25/50/75%, làm tròn số đẹp; cache transient theo phiên bản sản phẩm WooCommerce |
| Bộ lọc dạng cột | saha-theme `template-parts/common/filter.php` (`layout => sidebar`) + `catalog-product.css` | cùng form GET như cũ |
| AJAX | saha-theme `catalog-product-filter.js` | thêm: sắp xếp, chip bỏ lọc, "Xoá tất cả" qua AJAX; đồng bộ ô đã chọn sau khi tải |
| Ngăn trượt | saha-core `elements.js` (`initShop`) + `builder.css` | |
| Áp một nút | `StoreKit::archive()` → template **Shop & danh mục kiểu cửa hàng**, dùng toàn site | |

| Quyết định | Lý do |
|---|---|
| Mặc định của element giữ như cũ (`stack`, `plain`) | Template đang có không đổi giao diện khi cập nhật plugin |
| Khoảng giá tự tính từ giá thật, có filter `saha_filter_price_ranges` | Mẫu có mốc cố định (3/6/10 triệu) nhưng giá keo khác hẳn két sắt; mốc cố định sẽ ra khoảng rỗng |
| Khoảng giá ẩn ở chế độ catalogue | Giá bị ẩn → lọc theo giá là lộ giá gián tiếp |
| Ô Từ/Đến nhập tay (`saha_min_price`/`saha_max_price`) được ưu tiên hơn `saha_price` | Giữ link cũ đúng |
| Sắp xếp AJAX bắt `change` ở pha capture | Handler jQuery của WooCommerce gắn trên form sẽ submit tải lại trang |
| Ngăn mở → bỏ `z-index` của khung section bằng `:has()` | `.saha-section__inner { z-index: 1 }` tạo stacking context, ngăn bị header dính / thanh liên hệ đè |
| Không `position: sticky` cho cột lọc | Cột cao hơn màn hình thì phần dưới không cuộn tới được |
| Lưới 4–6 cột tự xuống 3 cột khi vùng danh sách < 1000px (container query, chỉ desktop) | Có cột lọc 280px, 4 cột ở 1024–1280px quá chật |

## 3. Files

- saha-core 1.22.0: `includes/class-filter.php`, `includes/Builder/Elements/{ProductArchive,ArchiveTitle}.php`, `includes/Builder/StoreKit.php`, `includes/class-cli.php`, `public/assets/css/builder.css`, `public/assets/js/elements.js`.
- saha-theme 0.2.5: `template-parts/common/filter.php`, `inc/catalog.php`, `assets/js/catalog-product-filter.js`, `assets/css/catalog-product.css`, `src/scss/_woocommerce.scss` (thương hiệu dạng nhãn trên thẻ).

## 4. Database

Không đổi schema. Transient `saha_price_ranges_*` (1 ngày, khoá theo phiên bản sản phẩm của WooCommerce).

## 5–6. Hooks

| Hook | Loại | Ghi chú |
|---|---|---|
| `saha_product_archive_sidebar` | action | `( array{term: ?WP_Term, price: bool} )` — nội dung cột lọc sau danh mục |
| `saha_filter_price_ranges` | filter | `( array $ranges, ?WP_Term $term )` — đặt mốc giá cố định |

## 7. Security

Query var mới `saha_price` chỉ nhận `\d{0,12}-\d{0,12}` (đảo nếu min > max); SQL khoảng giá chỉ dùng ID ép `int`. Mọi chữ escape; `orderby` giữ lại trên link bỏ lọc qua `sanitize_key`.

## 8. Testing

Tự động: smoke **237 passed** (+7: mốc giá, < 2 mức giá, nhãn, `saha_price` lạ/đảo, mặc định element, bố cục lạ bị từ chối, template StoreKit hợp lệ) · test JS 40 · lint sạch · build · http-smoke 34/0 · `wp saha qa` 91 đạt / 0 lỗi.

Trên trình duyệt (local, template #107 đổi sang Khung + Cột lọc):

- [x] `/product-category/keo-silicone/`: khung tiêu đề "KEO SILICONE · 8 sản phẩm · Giao hàng toàn quốc", cột danh mục mở 4 danh mục con, lưới 3 cột ở 1032px.
- [x] Tích "Apollo" → AJAX, URL `?saha_brand[]=apollo`, "Đang hiện 3 / 3", chip "Apollo ×".
- [x] Sắp xếp "Mới nhất" → AJAX, giữ điều kiện lọc; bỏ chip → còn 8 sản phẩm, ô Apollo tự bỏ tích.
- [x] Catalogue tắt (tạm): hộp Khoảng giá 5 lựa chọn; chọn "50,000 $ – 60,000 $" → 3 sản phẩm đúng khoảng. (Đã bật lại catalogue.)
- [x] Mobile 375: không tràn ngang; nút "Danh mục & bộ lọc" mở ngăn trên header dính và thanh liên hệ; focus vào nút Đóng; lọc trong ngăn; bấm nền đóng, focus về nút, số "1" trên nút.
- [x] Danh mục con `/keo-cong-nghiep/khoa-ren/`: cha mở, con đang xem tô nền. Shop: "Đang hiện 1–24 / 25". Lọc không ra kết quả: ẩn thanh, giữ cột lọc + chip + trạng thái rỗng.

## 9. Installation

Site mới: **Trang → Áp dụng giao diện kiểu cửa hàng** (đã gồm template này). Site đang chạy: mở template Shop & danh mục trong builder → Tiêu đề danh sách: Kiểu **Khung**; Danh sách sản phẩm: Bố cục **Cột lọc bên trái** (xem ADMIN-GUIDE).

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Bố cục như mẫu (khung tiêu đề, cột lọc, thanh đếm + sắp xếp) | ✅ |
| Lọc / sắp xếp / bỏ lọc AJAX, không JS vẫn chạy | ✅ |
| Catalogue không lộ giá | ✅ khoảng giá ẩn |
| Mobile | ✅ ngăn trượt |

## 11. Ghi chú & giới hạn

- Số trong hộp Thương hiệu là tổng toàn site (như bộ lọc cũ), chưa theo danh mục đang xem.
- Ở chế độ catalogue, WooCommerce vẫn có lựa chọn sắp xếp "theo giá" (có từ trước, không thuộc D5).
- Bố cục "Bộ lọc phía trên" (cũ) vẫn dùng `main` làm vùng kết quả AJAX như trước.
