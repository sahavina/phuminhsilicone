# SCC Phase 2 — Mốc 2.2: Template Builder + điều kiện

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §5.2, §5.5, §10; kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.1.md](PHASE-2.1.md).

## 1. Goal

- Dựng bằng builder **trang sản phẩm, shop/danh mục/thương hiệu, bài viết, blog/chuyên mục, trang, kết quả tìm kiếm, 404** — thay khung PHP của theme khi có template khớp.
- **Điều kiện** theo thiết kế §5.5: tất cả, trang chủ, trang/bài/sản phẩm cụ thể, chuyên mục, thẻ, danh mục sản phẩm, thương hiệu, loại trang danh sách, tìm kiếm, 404; nhóm **Áp dụng cho / Trừ**; **độ cụ thể** (đối tượng 30 > term 20 > term cha 15 > loại trang 10 > tất cả 0) rồi **ưu tiên** rồi ID nhỏ.
- 17 **element động**: lấy dữ liệu từ trang đang xem; trong editor hiện dữ liệu thật của một đối tượng xem trước.
- Header/footer dùng cùng engine → có thể đặt header riêng cho một trang/danh mục.

Nghiệm thu: **trang sản phẩm và danh mục dựng bằng builder, điều kiện chọn đúng template.**

## 2. Architecture

```
request ──► RequestContext::type()     single_product | product_archive | single_post | archive | page | search | 404
        └─► RequestContext::matches()  [rule, value, độ cụ thể]  (term + các term cha)
                       │
option saha_template_map (v2, biên dịch khi lưu/xoá template) ─► Conditions::resolve()  → template ID | null
                       │
template_include (99) ─► Loader: có template → saha-core/templates/builder-template.php
                                   get_header() → [div.product (sản phẩm) | div.woocommerce (shop)] → Renderer → get_footer()
                         không có → file của theme như cũ
```

| Thành phần | Vai trò |
|---|---|
| `Templates\Conditions` | rule, độ cụ thể, chuẩn hoá điều kiện, biên dịch chỉ mục, chọn template — **thuần PHP** (test không cần WordPress) |
| `Templates\RequestContext` | loại template + danh sách khớp của request |
| `Templates\Loader` | `template_include`, CSS, body class (`saha-has-template`, `saha-template-{type}`) |
| `Templates\Preview` | đối tượng xem trước trong editor; class WooCommerce cho canvas |
| `Templates\ConditionsScreen` | màn "Điều kiện hiển thị" |
| `Builder\Elements\DynamicElement`, `ProductElement` | nền element động: đối tượng đang xem, `withSubject` (đặt/khôi phục `global $post, $product`), `withArchive` (main query / query xem trước) |

| Quyết định | Lý do |
|---|---|
| Thay cả file template qua `template_include` (không qua filter của theme) | Chạy với mọi theme có header/footer chuẩn; không có template → theme không bị đụng tới. |
| Element sản phẩm gọi **hàm template của WooCommerce** (`woocommerce_show_product_images`, `woocommerce_template_single_price`…) | Plugin/theme gắn hook, chế độ catalogue, Mua ngay, biến thể vẫn chạy như trang mặc định; WooCommerce đổi markup thì element đổi theo. |
| Thêm 2 element "hook" (Khối thông tin sản phẩm, Phần dưới trang sản phẩm) và mẫu trang sản phẩm dùng chúng | Giữ nguyên mọi phần saha-theme gắn vào trang sản phẩm (mã/thương hiệu/tình trạng, CTA báo giá + hotline, thông số, tài liệu, cùng thương hiệu). Muốn bố cục khác thì thay bằng element lẻ. |
| Trang sản phẩm bọc `div.product` của WooCommerce (cả trong canvas) | Script gallery/biến thể và CSS `.woocommerce div.product …` cần; CSS riêng bỏ float/48% của `woocommerce-layout.css`. |
| Đối tượng xem trước = đối tượng khớp điều kiện của template (sản phẩm thuộc danh mục đã chọn…), chọn tay được | Dựng template "Keo Silicone" mà xem trước bằng sản phẩm Loctite là sai ngữ cảnh. |
| Template mới **chưa áp dụng** cho tới khi đặt điều kiện | Tạo template không làm đổi website ngay. |
| "Dùng cho toàn site" chỉ bỏ rule "tất cả" của template khác, giữ điều kiện cụ thể | Bản 1.5 xoá hết điều kiện của template khác — đúng khi chỉ có "tất cả", sai khi có điều kiện danh mục. |
| Giỏ hàng, thanh toán, tài khoản không dùng template "Trang" | Giữ khung WooCommerce (block Cart/Checkout). |
| Chỉ mục cũ (mốc 1.5) tự biên dịch lại một lần (`v: 2`) | Nâng cấp không cần thao tác. |

## 3. Files

### saha-core 1.16.0

| File | Vai trò |
|---|---|
| `includes/Templates/Conditions.php`, `RequestContext.php`, `Loader.php`, `Preview.php`, `ConditionsScreen.php` | **mới** — xem mục 2 |
| `templates/builder-template.php` | **mới** — khung trang |
| `includes/Templates/Repository.php` | loại nội dung, `saveConditions()`, chỉ mục v2, `resolve()` theo request (cache trong request) |
| `includes/Templates/Defaults.php` | mẫu cho 7 loại nội dung |
| `includes/Templates/AdminScreen.php`, `PostType.php`, `Module.php` | "Thêm template: [loại] → Tạo từ mẫu", cột Áp dụng/Ưu tiên, link Điều kiện; menu **SAHA → Header, Footer & Templates** |
| `includes/Builder/Elements/DynamicElement.php`, `ProductElement.php` + 17 element | Tiêu đề, Nội dung, Tóm tắt/mô tả ngắn, Ảnh đại diện, Ngày·tác giả·chuyên mục, Breadcrumb, Tiêu đề danh sách, Danh sách bài; Ảnh sản phẩm, Giá, Thêm vào giỏ/Báo giá, Mã·danh mục, Tab, Liên quan/Bán kèm, Khối thông tin (hook), Phần dưới (hook), Danh sách sản phẩm |
| `includes/Builder/ElementRegistry.php` | 50 element |
| `includes/class-qa.php` | kiểm header/footer theo loại bố cục; cảnh báo template trùng điều kiện cùng ưu tiên |
| `public/assets/css/builder.css` | CSS template sản phẩm, element động, gallery trong canvas |

### saha-builder 0.5.0

`includes/Canvas.php` (filter `saha_builder_canvas_body_class`, `saha_builder_canvas_classes`), `src/builder/panels/Inserter.js` (nhóm **Template (động)**, **Trang sản phẩm (động)**).

### saha-theme 0.2.3

`src/scss/_woocommerce.scss`: `div.product.saha-template-product` không dùng lưới 2 cột của theme.

## 4. Database

Meta `saha_template`: `_saha_template_type` (thêm 7 loại), `_saha_template_conditions` (đúng §5.5), `_saha_template_priority`, **mới** `_saha_template_preview`. Option `saha_template_map` đổi sang dạng v2 (§5.5: `type → rule → value → [id]` + `priority`, `exclude`).

## 5. Code

Ví dụ chỉ mục:

```json
{ "v": 2,
  "types": { "single_product": { "product_cat": { "21": [105] } }, "product_archive": { "all": { "*": [107] } } },
  "priority": { "105": 5, "107": 0 },
  "exclude": { "105": ["product:21"] } }
```

Element động mới cho add-on: kế thừa `DynamicElement` (hoặc `ProductElement` cho trang sản phẩm, chỉ cần `output()` in phần của WooCommerce).

## 6. Hooks

| Hook | Loại | Ghi chú |
|---|---|---|
| `saha_template_resolved` | filter (đã có) | nay áp cho mọi loại template |
| `saha_builder_canvas_body_class`, `saha_builder_canvas_classes` | filter (saha-builder) | class cho body / vùng canvas |
| `woocommerce_before_single_product`, `woocommerce_after_single_product` | action WooCommerce | Loader chạy quanh template sản phẩm (thông báo…) |

## 7. Security

- Điều kiện: chỉ `manage_saha_templates` + `edit_post` + nonce; mọi giá trị qua `Conditions::sanitize()` (rule trong danh sách theo loại, ID số dương, `archive_type` trong danh sách, không "Tất cả" trong nhóm Trừ).
- Màn điều kiện là trang ẩn (cha `options.php`), cùng quyền.
- Element động escape đầu ra; nội dung bài qua `the_content`, mô tả ngắn qua `woocommerce_short_description` như WooCommerce; bài có mật khẩu → form mật khẩu.
- Template chỉ là cách trình bày: quyền xem bài, trạng thái, catalogue… vẫn do WordPress/WooCommerce/saha-core quyết định.

## 8. Testing

Tự động:

- `php tests/smoke.php` — **199 passed** (+9 điều kiện: chuẩn hoá, `archive_type`, tất cả, danh mục thắng tất cả, đối tượng thắng danh mục, ưu tiên cùng mức, Trừ → xuống mức sau, danh mục con qua cha nhưng danh mục trực tiếp thắng, không khớp → null; cập nhật số element = 50 và danh sách element động).
- `npm run test:js` 40 passed · lint sạch · `php tests/http-smoke.php` 33 passed / 0 failed.
- `wp saha qa` — 91 đạt / 0 lỗi (+1).

Đã chạy thử trên trình duyệt (local):

- [x] Template "Sản phẩm — Keo Silicone" (danh mục Keo Silicone): Apollo A500 (danh mục con Silicone Apollo) dùng template; Loctite 243 giữ trang mặc định. Bố cục giống trang mặc định (gallery | thông tin + CTA báo giá; tab, thông số, liên quan).
- [x] Thêm "Trừ sản phẩm #21" qua màn Điều kiện → Apollo A500 về trang mặc định, Bamboo vẫn dùng template.
- [x] "Shop & danh mục" (tất cả): shop có 1 H1, bộ lọc, sắp xếp, 24 sản phẩm.
- [x] Bài viết (khung 800px, ngày + chuyên mục, bài liên quan của theme), chuyên mục (tên không có tiền tố "Danh mục:"), tìm kiếm, 404, trang "Liên hệ" (điều kiện trang cụ thể): đúng loại template, 1 H1, không lỗi PHP.
- [x] Builder: template sản phẩm xem trước bằng sản phẩm thuộc Keo Silicone, gallery/tab có style; bảng Thêm có nhóm "Template (động)", "Trang sản phẩm (động)".
- [x] "Thêm template: Trang 404 → Tạo từ mẫu" → mở builder với mẫu.
- [x] Tắt plugin SAHA Builder: trang sản phẩm và shop dùng template giống hệt khi bật.

Thủ công (cần review):

- [ ] Template sản phẩm dựng bằng element lẻ (Giá, Thêm vào giỏ, Tab…) với sản phẩm biến thể khi tắt catalogue.
- [ ] Hai template cùng danh mục khác ưu tiên; đổi ưu tiên → template khác thắng.
- [ ] Header riêng cho trang Liên hệ (header thứ hai, điều kiện "Trang cụ thể").

## 9. Installation

Cập nhật code (saha-core 1.16.0, saha-builder 0.5.0, saha-theme 0.2.3); mở wp-admin một lần (chỉ mục tự biên dịch lại). **SAHA → Header, Footer & Templates → Thêm template: [loại] → Tạo từ mẫu** → sửa trong builder → **Điều kiện** → chọn nơi áp dụng → Lưu.

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Trang sản phẩm dựng bằng builder | ✅ template #105 |
| Danh mục/shop dựng bằng builder | ✅ template #107 |
| Điều kiện chọn đúng template (độ cụ thể, ưu tiên, Trừ) | ✅ thử trên trình duyệt + 9 smoke test |

### Lỗi phát hiện và đã sửa

- Gallery và thông tin sản phẩm trong template chỉ rộng 48% cột (`woocommerce-layout.css`) → CSS riêng cho `div.product.saha-template-product`; lưới 2 cột của saha-theme không áp vào template.
- Canvas: gallery trống (`opacity: 0` chờ JS của WooCommerce), tab không có style (thiếu `.woocommerce`/`div.product`) → canvas nhận class theo loại template, gallery luôn hiện trong canvas.
- Xem trước dùng sản phẩm mới nhất bất kể điều kiện → lấy sản phẩm khớp điều kiện.
- Màn điều kiện (cha menu rỗng) làm `admin-header.php` báo Deprecated → cha `options.php`.
- Tiêu đề chuyên mục có tiền tố "Danh mục:".

## 11. Ghi chú & giới hạn

- Trang thương hiệu dùng template "Shop & danh mục" thì mất phần đầu thương hiệu (logo, mô tả, nội dung SEO) của saha-theme — muốn giữ, đặt điều kiện không gồm thương hiệu (ví dụ "Trang Shop" + danh mục cụ thể).
- Điều kiện theo vai trò người dùng (`user_role`) thuộc Phase 3 (thiết kế §5.5).
- Site local: cấu trúc permalink đã bị đặt sai ở phiên trước (`/C:/Program%20Files/Git/%postname%/` — Git Bash đổi đường dẫn khi chạy `wp rewrite structure`) → đã đặt lại `/%postname%/`. Chạy WP-CLI trong Git Bash cần `MSYS_NO_PATHCONV=1` khi tham số bắt đầu bằng `/`.
- Dữ liệu thử trên local: template #105 (sản phẩm — Keo Silicone, trừ #21, ưu tiên 5), #107 (shop & danh mục), #109 (bài viết), #111 (blog/chuyên mục), #113 (tìm kiếm), #115 (404), #117 (trang Liên hệ).
