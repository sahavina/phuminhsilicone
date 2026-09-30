# PHASE 2 — Catalogue

## 1. Goal

Biến WooCommerce thành catalogue keo dán đúng nghiệp vụ SAHA:

- Taxonomy `product_brand`, `product_application` (+ `product_material` tuỳ chọn).
- Term meta thương hiệu: logo, banner, mô tả ngắn, nội dung SEO, website, xuất xứ, SEO title, meta description.
- Field sản phẩm ngành keo, chia panel trong WooCommerce product data.
- Frontend: product card có brand + SKU, single product đầy đủ khối kỹ thuật, trang thương hiệu, brand grid.

Không làm ở phase này: search/autocomplete/filter (Phase 3), form báo giá (Phase 4), homepage (Phase 5).

## 2. Architecture

```
Taxonomies  ── đăng ký taxonomy ở init:11 (sau WooCommerce init:10)
    │           nếu product_brand đã tồn tại → dùng lại, KHÔNG đăng ký đè
    ▼
Brand       ── term meta + admin form + service (get / get_all / get_for_product)
    │           cache transient + invalidation
    ▼
Product     ── panel field trong product data + service (specs / docs / availability / card data)
    │
    ▼
includes/functions.php  ── saha_get_brand, saha_get_brands, saha_get_product_specs, …
    │
    ▼
flatsome-child ── hook WooCommerce + template-parts, KHÔNG đọc meta trực tiếp
```

Điểm quan trọng: **không tạo file `taxonomy-product_brand.php`**. WooCommerce tự map mọi taxonomy của `product` sang `archive-product.php`, nên trang thương hiệu dùng hook `woocommerce_archive_description` / `woocommerce_after_main_content` — ít code hơn và tự tương thích khi Flatsome/Woo đổi markup.

## 3. Files

Thêm mới — plugin:

| File | Vai trò |
|---|---|
| `includes/class-taxonomies.php` | đăng ký 3 taxonomy, phát hiện `product_brand` có sẵn |
| `includes/class-brand.php` | term meta, admin form, cột logo, service, cache |
| `includes/class-product.php` | panel field, sanitize, service sản phẩm |
| `admin/views/brand-fields.php` | field term (add + edit form) |
| `admin/views/product-panels.php` | 5 panel trong product data + template row |
| `admin/assets/admin.js` | media picker, repeater thông số, danh sách tài liệu |

Thêm mới — theme:

| File | Vai trò |
|---|---|
| `template-parts/product/meta.php` | SKU, thương hiệu, tình trạng, đơn vị, dòng SP |
| `template-parts/product/cta.php` | CTA báo giá + hotline + Zalo |
| `template-parts/product/sections.php` | ứng dụng, thông số, hướng dẫn, lưu ý, tài liệu |
| `template-parts/product/brand-products.php` | sản phẩm khác cùng thương hiệu |
| `template-parts/brand/card.php` · `grid.php` · `header.php` | component thương hiệu |

Sửa: `class-loader.php` (3 module mới), `class-admin.php` (enqueue media/JS theo screen, helper `asset_version`), `class-install.php` (flush rewrite khi upgrade), `includes/functions.php` (9 helper mới), `inc/woocommerce.php`, `inc/shortcodes.php`, `inc/helpers.php`, `inc/enqueue.php`, `inc/ux-elements.php`, `product.css`, `brand.css`, `saha-core.php` (version 1.1.0).

## 4. Database

**Không thêm bảng, không đổi schema** — `SAHA_CORE_DB_VERSION` giữ `1.0.0`.

Dữ liệu mới nằm ở native storage:

| Nơi lưu | Key |
|---|---|
| `wp_termmeta` (product_brand) | `saha_brand_logo_id`, `saha_brand_banner_id`, `saha_brand_short_description`, `saha_brand_seo_content`, `saha_brand_website`, `saha_brand_country`, `saha_brand_seo_title`, `saha_brand_meta_description` |
| `wp_postmeta` (product) | `_saha_unit`, `_saha_product_line`, `_saha_availability`, `_saha_specs`, `_saha_application_text`, `_saha_usage`, `_saha_warning`, `_saha_docs`, `_saha_cta_mode`, `_saha_cta_label` |
| `wp_options` | `saha_brand_cache_keys` (danh sách transient để invalidate chính xác) |

Tài liệu lưu **attachment ID**, không lưu URL tuyệt đối (spec §67).
Màu / dung tích / xuất xứ / quy cách **không** tạo field riêng — dùng WooCommerce attribute `pa_*` (spec §6).

## 5. Code

Xem mục 3. 39 file PHP pass `php -l` (PHP 8.0).

## 6. Hooks

Filter mới: `saha_brand_taxonomy_args`, `saha_application_taxonomy_args`, `saha_material_taxonomy_args`, `saha_brand_data`, `saha_product_panels`, `saha_product_card_data`, `saha_theme_brand_products_limit`, `saha_theme_brand_heading_suffix`.

Action mới: `saha_brand_saved`, `saha_product_meta_saved`.

Shortcode / UX element mới: `[saha_brand_grid]` (UX Builder: **SAHA Brand Grid**).

Helper mới: `saha_get_brand`, `saha_get_brands`, `saha_get_product_brand`, `saha_get_product_meta`, `saha_get_product_specs`, `saha_get_product_documents`, `saha_get_availability_label`, `saha_get_product_card_data`, `saha_get_product_cta`.

## 7. Security

- Term meta và product meta đều qua `Security::guard_admin_action()` = **capability + nonce**.
- Attachment ID từ form được xác thực `get_post_type() === 'attachment'` trước khi lưu, và kiểm tra lại khi đọc — không tin ID client gửi lên (spec §78).
- `select` chỉ nhận giá trị nằm trong danh sách option; giá trị lạ bị loại.
- Repeater giới hạn 80 dòng, tài liệu giới hạn 20 mục — chặn payload phình to.
- Nội dung HTML (`application_text`, `usage`, `warning`, `seo_content`) qua `wp_kses_post` cả khi lưu lẫn khi render.
- Taxonomy dùng capability riêng: sửa thương hiệu cần `manage_saha_brands`, gán term cần `manage_saha_products`.
- Cache key sinh từ `md5(json_encode(args))` — args đã được whitelist (`orderby` chỉ nhận 4 giá trị, `number` clamp ≤ 200).

## 8. Testing

Đã chạy: `php -l` trên 39 file — 0 lỗi.

Checklist thủ công:

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | Update plugin lên 1.1.0 | rewrite tự flush, không cần Save Permalinks thủ công |
| 2 | Products → Thương hiệu | thấy menu, thêm được term |
| 3 | Thêm thương hiệu + logo + banner | media picker mở, ảnh lưu đúng |
| 4 | Sửa thương hiệu | field hiện lại đúng giá trị đã lưu |
| 5 | Cột Logo ở danh sách term | hiện thumbnail 48px |
| 6 | Xem `/thuong-hieu/apollo/` | banner, logo, H1 "Apollo chính hãng", grid sản phẩm |
| 7 | Nội dung SEO thương hiệu | hiện **dưới** grid, chỉ ở trang 1 |
| 8 | `/thuong-hieu/apollo/page/2/` | không lặp lại nội dung SEO |
| 9 | Kiểm tra số H1 trên trang thương hiệu | đúng **1** thẻ H1 |
| 10 | Sửa sản phẩm | 5 tab SAHA hiện trong Product data |
| 11 | Thêm 3 dòng thông số, lưu | hiện đúng ở frontend dạng bảng |
| 12 | Xoá hết dòng thông số, lưu | khối "Thông số kỹ thuật" biến mất khỏi frontend |
| 13 | Thêm tài liệu PDF | link tải hoạt động, hiện nhãn loại (TDS/SDS…) |
| 14 | Xoá file PDF khỏi Media Library | mục tài liệu tự biến mất, không lỗi 404 |
| 15 | Sản phẩm không có field nào | không render khối rỗng nào |
| 16 | Tình trạng = "Liên hệ" | frontend hiện "Liên hệ" thay vì tồn kho Woo |
| 17 | Để trống tình trạng | fallback theo stock status của WooCommerce |
| 18 | CTA mode = "Ẩn CTA" | không hiện nút báo giá trên sản phẩm đó |
| 19 | Product card ở archive | hiện brand + SKU, title không tràn 2 dòng |
| 20 | Single product | có khối "Sản phẩm khác của {brand}" |
| 21 | Sản phẩm không gắn brand | không render khối brand products, không lỗi |
| 22 | `[saha_brand_grid]` trong page | render grid, `brand.css` được load |
| 23 | Trang không có shortcode | `brand.css` **không** load (xem view-source) |
| 24 | Thêm/sửa/xoá thương hiệu | transient brand bị xoá, grid cập nhật ngay |
| 25 | Login role Sales, sửa thương hiệu | bị chặn (thiếu `manage_saha_brands`) |
| 26 | POST product form thiếu nonce SAHA | meta cũ **không** bị xoá |
| 27 | Nhập `<script>` vào thông số | bị escape, không thực thi |
| 28 | Nhập attachment ID không tồn tại | bị loại, không lưu |
| 29 | Mobile 375px | bảng thông số cuộn được, CTA xếp dọc |
| 30 | Rank Math active | breadcrumb 1 lần, không schema trùng |

## 9. Installation

Cập nhật từ Phase 1, không cần thao tác DB:

1. Pull code mới (plugin lên 1.1.0).
2. Vào admin một lần — `maybe_upgrade()` tự flush rewrite.
3. Products → Thương hiệu: nhập danh sách (Apollo, Bamboo, Wacker, Dowsil, Loctite…).
4. Tạo page `/thuong-hieu/` với shortcode `[saha_brand_grid]` hoặc UX element **SAHA Brand Grid**.
5. Nếu muốn bật taxonomy vật liệu: SAHA → Cấu hình → *Bật taxonomy vật liệu*, rồi Save Permalinks.

## 10. Acceptance criteria

- [x] Dùng `post_type=product`, không tạo CPT sản phẩm.
- [x] Không đăng ký đè `product_brand` nếu WooCommerce/plugin khác đã có.
- [x] Không tạo field trùng WooCommerce attribute.
- [x] Field trống không render ra frontend.
- [x] Tài liệu lưu attachment ID, không lưu URL tuyệt đối.
- [x] Admin field chia panel, không nhồi 1 panel dài.
- [x] Capability + nonce ở mọi thao tác lưu.
- [x] Attachment ID được xác thực cả lúc lưu lẫn lúc đọc.
- [x] Nội dung SEO thương hiệu nằm dưới grid, không lặp khi phân trang.
- [x] Cache brand có invalidation đúng event.
- [x] `brand.css` chỉ load khi cần.
- [x] 39 file PHP pass `php -l`.
- [ ] Checklist 30 test chạy trên WordPress thật (cần môi trường local/staging).
