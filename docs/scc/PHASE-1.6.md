# SCC Phase 1 — Mốc 1.6: WooCommerce trên saha-theme

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §2, §9, §12. Mốc trước: [PHASE-1.5.md](PHASE-1.5.md).

## 1. Goal

- Shop, danh mục, thẻ sản phẩm, trang sản phẩm (gallery, **Mua ngay**), giỏ hàng / thanh toán (block WooCommerce), tài khoản — giao diện của `saha-theme`, màu theo Theme Options.
- **Chế độ catalogue phía server** trong `saha-core`: không còn phụ thuộc theme (trước đây nằm ở `flatsome-child` → đổi theme là lộ giá).
- Chuyển lớp catalogue đã QA của `flatsome-child` sang `saha-theme`: template part, 17 shortcode, JS (tìm kiếm, bộ lọc, form báo giá), CSS.

Nghiệm thu: **luồng mua hàng và luồng báo giá chạy trên `saha-theme`.**

## 2. Architecture

```
saha-core  WooCommerce\Module (chỉ khi WooCommerce bật)
  ├─ CatalogMode   init:20 — bật khi saha_core_settings.catalogue_mode
  │                 is_purchasable/variation_is_purchasable = false (chặn cả form, AJAX, Store API)
  │                 get_price_html → "Liên hệ báo giá"; cart_needs_payment = false
  │                 gỡ nút thêm vào giỏ ở loop và trang sản phẩm
  └─ BuyNow        nút submit thứ hai trong form "Thêm vào giỏ" → dùng nguyên luồng WC
                    (tồn kho, biến thể, số lượng) rồi chuyển hướng sang thanh toán

saha-theme
  ├─ woocommerce.php      khung chung; chạy woocommerce_before/after_main_content
  ├─ inc/woocommerce.php  gỡ wrapper/breadcrumb WC, số cột shop (Theme Options)
  ├─ inc/catalog.php      hook trình bày: thương hiệu + SKU trên thẻ, meta/CTA báo giá/thông số/
  │                       cùng thương hiệu ở trang sản phẩm, header thương hiệu, bộ lọc, trạng thái rỗng,
  │                       modal báo giá, CTA dính mobile, bài liên quan; nạp CSS/JS theo trang
  ├─ inc/shortcodes.php   17 shortcode [saha_*] (trang Liên hệ, Báo giá, Thương hiệu… không đổi nội dung)
  ├─ template-parts/{product,quote,brand,common,search,contact,blog}/  chuyển từ flatsome-child
  ├─ assets/css/catalog-*.css, assets/js/catalog-*.js                  chuyển từ flatsome-child
  └─ src/scss/_woocommerce.scss  nút, thẻ, trang sản phẩm, giỏ/thanh toán block, tài khoản
```

| Quyết định | Lý do |
|---|---|
| Catalogue ở `saha-core`, theme chỉ trình bày | Ẩn giá là quy tắc kinh doanh, không được mất khi đổi theme. `is_purchasable = false` chặn luôn Store API (Cart/Checkout block) — ẩn bằng CSS là không đủ. |
| `flatsome-child` giữ bản cũ của nó | Theme đã đóng băng; hai bản cùng đọc `catalogue_mode` nên cho kết quả giống nhau. |
| Mua ngay = nút submit thứ hai trong form của WC, không phải AJAX riêng | Dùng lại mọi kiểm tra của WooCommerce (nonce form, tồn kho, biến thể); không thêm endpoint. |
| CSS/JS catalogue chép nguyên (`assets/css`, `assets/js`), không đưa qua webpack | Đã QA Phase 2–8; chỉ đổi biến màu sang `--saha-*` của Theme Options. Viết lại thành SCSS khi có Phase 2 thiết kế mới. |
| Selector WooCommerce có độ cụ thể bằng WC (`div.images`, `div.summary`, `button.button.alt`) | `woocommerce-layout.css` ép gallery/summary 48% và thumbnail 25%; selector yếu hơn thua (lỗi phát hiện khi thử). |
| Không override template WooCommerce (`saha-theme/woocommerce/` rỗng) | Chỉ dùng hook + CSS → cập nhật WooCommerce không vỡ template. |

## 3. Files

### saha-core 1.13.0

| File | Vai trò |
|---|---|
| `includes/WooCommerce/Module.php` | Bật hai service khi có WooCommerce |
| `includes/WooCommerce/CatalogMode.php` | Chế độ catalogue phía server |
| `includes/WooCommerce/BuyNow.php` | Nút Mua ngay |
| `includes/class-loader.php` | Đăng ký module `woocommerce` |
| `includes/class-qa.php` | Nhóm kiểm tra **WooCommerce** trong `wp saha qa` |

### saha-theme 0.2.0

| File | Vai trò |
|---|---|
| `inc/catalog.php` | Hook trình bày + nạp asset + `SAHA_CONFIG` cho JS |
| `inc/shortcodes.php` | `saha_hotline`, `saha_zalo`, `saha_company`, `saha_brand_grid`, `saha_search`, `saha_product_filter`, `saha_quote_form`, `saha_contact_form`, `saha_products`, `saha_featured_products`, `saha_brand_products`, `saha_category_grid`, `saha_application_grid`, `saha_quote_cta`, `saha_term_links`, `saha_social`, `saha_copyright` |
| `inc/helpers.php` | + `saha_theme_setting`, `saha_theme_hotlines`, `saha_theme_zalo_url`, `saha_theme_cta_label`, `saha_theme_catalogue_mode`, `saha_theme_part`, `saha_theme_content_has_shortcode` |
| `template-parts/…` | 22 template part (sản phẩm, báo giá, thương hiệu, bộ lọc, trạng thái rỗng, CTA, tìm kiếm, liên hệ, bài liên quan) |
| `assets/css/catalog-{main,responsive,product,brand,search,form,sections}.css` | CSS catalogue |
| `assets/js/catalog-{main,search,product-filter,quote-form}.js` | JS catalogue (vanilla, defer) |
| `src/scss/_woocommerce.scss` | Giao diện WooCommerce |
| `woocommerce.php` | Chạy `woocommerce_before/after_main_content` |

## 4. Database

Không đổi schema. Báo giá vẫn vào `wp_saha_quotes`, liên hệ vào `wp_saha_leads`.

## 5. Code

### Nạp asset theo trang

| Trang | CSS | JS |
|---|---|---|
| Mọi trang | `catalog-main`, `catalog-responsive`, `catalog-form` | `catalog-main` (+ `SAHA_CONFIG`) |
| WooCommerce (shop, danh mục, sản phẩm, giỏ, thanh toán, tài khoản) | + `catalog-product` | |
| Sản phẩm, thương hiệu, ứng dụng, trang có `[saha_brand_grid]` | + `catalog-brand` | |
| Tìm kiếm | + `catalog-search` | |
| Archive có bộ lọc | | + `catalog-product-filter` |
| Sản phẩm / trang có `[saha_quote_cta]` | | + `catalog-quote-form` (modal báo giá) |
| Trang có shortcode `[saha_…]` | + `catalog-sections`, `catalog-product`, `catalog-brand` | |

### Tắt Mua ngay cho một sản phẩm

```php
add_filter( 'saha_buy_now_enabled', fn( $on, $product ) => $on && ! has_term( 'phu-kien', 'product_cat', $product->get_id() ), 10, 2 );
```

## 6. Hooks

| Hook | Loại | Ghi chú |
|---|---|---|
| `saha_buy_now_enabled` | filter (saha-core) | bật/tắt Mua ngay theo sản phẩm |
| `saha_theme_script_config` | filter (theme) | config `SAHA_CONFIG` cho JS |
| `saha_theme_mobile_cta_enabled` | filter (theme) | CTA dính mobile (mặc định tắt ở giỏ/thanh toán/404) |
| `saha_theme_show_archive_filter` | filter (theme) | bộ lọc trên shop/danh mục/tìm kiếm |

## 7. Security

- Catalogue chặn mua ở tầng dữ liệu (`is_purchasable`), không chỉ ẩn nút: gọi thẳng `?add-to-cart=ID` hay Store API cũng bị từ chối; giỏ còn hàng cũ được WooCommerce tự xoá kèm thông báo.
- Mua ngay chỉ chép `absint` ID sang `add-to-cart`; mọi kiểm tra (nonce form, tồn kho, biến thể) vẫn là của WooCommerce. ID không hợp lệ → bỏ qua.
- Template part escape đầu ra (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` cho nội dung SEO thương hiệu). Form báo giá/liên hệ dùng REST có nonce + honeypot + rate limit (không đổi).
- `SAHA_CONFIG` không chứa dữ liệu nội bộ: URL REST, nonce công khai, hotline, link Zalo, chuỗi i18n.

## 8. Testing

Tự động:

- `php tests/smoke.php` — **174 passed** (+6: Mua ngay đặt `add-to-cart`, chuyển sang thanh toán, giữ `add-to-cart` của form biến thể, bỏ qua ID không hợp lệ, không đổi chuyển hướng khi thêm vào giỏ thường, nhãn thay giá).
- `npm run test:js` — **40 passed**. `npm run lint:css`, `npm run lint:js` sạch.
- `wp saha qa` — **84 đạt / 0 lỗi** (+7 nhóm WooCommerce: catalogue bật → không mua được, giá không có trong HTML, không có nút giỏ; trang giỏ/thanh toán/tài khoản đã gán. Catalogue tắt → có phương thức thanh toán, có nút Mua ngay).
- `php tests/http-smoke.php http://localhost/saha --write` — 40 passed / 2 skipped (lần chạy ngay sau khi gửi báo giá thử bằng tay thì ca "gửi lại → duplicate" nhận 429 do rate limit 5 lần/10 phút — đúng thiết kế).

Đã chạy thử trên trình duyệt (local):

- [x] **Luồng mua hàng** (catalogue tắt, bật COD): trang sản phẩm → **Mua ngay** → thẳng tới Checkout block → đặt đơn → "Đơn hàng đã nhận" (#79). Thêm vào giỏ từ shop → Cart block → "Tiến hành thanh toán".
- [x] **Luồng báo giá** (catalogue bật): giá thành "Liên hệ báo giá", không có nút giỏ; **Yêu cầu báo giá** → modal có sẵn tên + SKU sản phẩm → gửi → thông báo thành công → bản ghi trong `wp_saha_quotes` đúng `product_id`, `sku`, `source_url`.
- [x] Gallery: ảnh chính, thumbnail 5 cột, chuyển ảnh, zoom.
- [x] Shop: thẻ có thương hiệu + mã SKU, bộ lọc thương hiệu/ứng dụng/tình trạng; trang thương hiệu có header riêng, đúng 1 H1.
- [x] `/lien-he/`, `/bao-gia/`, `/thuong-hieu/` không còn shortcode thô `[saha_…]`.
- [x] Mobile 375px: không cuộn ngang, CTA dính (Gọi điện · Yêu cầu báo giá).

Thủ công (cần review):

- [ ] Sản phẩm biến thể: chọn thuộc tính → Mua ngay → thanh toán đúng biến thể.
- [ ] Tài khoản: đăng nhập / đơn hàng / địa chỉ hiển thị gọn trên desktop + mobile.
- [ ] Đổi màu chính ở Theme Options → nút, giá, tab, nút thanh toán đổi theo.

## 9. Installation

Cập nhật code → mở wp-admin bằng admin (saha-core 1.13.0). Bật/tắt catalogue ở **SAHA → Cấu hình** (hoặc Theme Options). Muốn bán hàng: tắt catalogue, bật ít nhất một phương thức ở **WooCommerce → Cài đặt → Thanh toán**, kiểm tra tiền tệ (site local đang để USD).

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Luồng mua hàng chạy trên `saha-theme` | ✅ đơn thử #79 qua Mua ngay → Checkout block |
| Luồng báo giá chạy trên `saha-theme` | ✅ báo giá #12 từ modal trang sản phẩm |
| Catalogue không phụ thuộc theme | ✅ `saha-core` WooCommerce\CatalogMode, có kiểm tra trong `wp saha qa` |

### Lỗi phát hiện và đã sửa

- Gallery và phần tóm tắt chỉ rộng 48% của cột lưới — `woocommerce-layout.css` cụ thể hơn → selector `div.images` / `div.summary`.
- Thumbnail gallery 21px (WC ép `width: 25%`) → cùng độ cụ thể với selector của WooCommerce.
- `common/section-heading.php` in `< class="…">` khi không truyền `tag` (lỗi có sẵn từ flatsome-child: đọc `$args['tag']` sau khi kiểm bằng `??`) → đọc một lần vào biến. Bản ở flatsome-child giữ nguyên (đã đóng băng).
- Bỏ quy tắc CSS cũ `.saha-catalog-mode .price { display: none }` — giá đã được thay phía server, quy tắc này lại che mất nhãn "Liên hệ báo giá".

## 11. Ghi chú & giới hạn

- Dữ liệu thử trên local: 3 ảnh QA (#80–82) gán cho sản phẩm #34; đơn #79; báo giá #12. COD đã tắt lại, catalogue bật lại sau khi thử.
- CSS/JS catalogue chưa qua bundler/lint của wp-scripts (giữ nguyên bản đã QA); sẽ chuyển sang SCSS ở Phase 2.
- Template thương hiệu/sản phẩm tuỳ biến bằng builder (Template Builder) thuộc Phase 2.
