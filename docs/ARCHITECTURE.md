# Tổng Kho Keo Dán SAHA — Kiến trúc hệ thống

Công ty TNHH Thương mại Dịch vụ Trực tuyến SAHA — tongkhokeodan.com
Stack: WordPress + WooCommerce + Flatsome + Flatsome Child + `saha-core` plugin + MySQL + PHP 8.x

---

## A. Kiến trúc tổng thể

```
WordPress Core  (không sửa)
  └── WooCommerce            → product engine (post_type=product)
        └── Flatsome         (không sửa)
              └── flatsome-child   → PRESENTATION layer
              └── saha-core plugin → BUSINESS layer
                    └── MySQL (native tables + custom tables)
```

Quy tắc phân tách:

| Layer | Được phép | Không được phép |
|---|---|---|
| `flatsome-child` | template, CSS, JS, UX element registration, shortcode render | query phức tạp, SQL, business rule, ghi DB |
| `saha-core` | service, repository, REST API, DB, admin, roles, logger, settings | echo HTML layout trang, CSS |
| WooCommerce | sản phẩm, danh mục, attribute, stock, giá | — |

Child theme **gọi service** của plugin qua helper `saha_*`, không truy cập `$wpdb` trực tiếp. Nếu plugin bị deactivate, child theme vẫn render (degrade an toàn qua `function_exists`).

## B. Sitemap

```
/                               Trang chủ (UX Builder)
/san-pham/                      Shop archive (WooCommerce)
/danh-muc/{slug}/               product_cat
/thuong-hieu/                   Brand index
/thuong-hieu/{slug}/            product_brand archive
/ung-dung/{slug}/               product_application archive
/san-pham/{slug}/               single product
/tin-tuc/                       Blog
/tin-tuc/{cat}/{slug}/          Bài viết
/lien-he/                       Contact + form
/bao-gia/                       Yêu cầu báo giá (landing)
/gioi-thieu/  /chinh-sach/*     Trang tĩnh
/tim-kiem/                      Kết quả tìm kiếm sản phẩm
404                             search box + danh mục phổ biến
```

## C. Data model

```
wp_posts(product)      ─┬─ wp_postmeta (_sku, _price, _saha_* group fields)
                        ├─ wp_term_relationships → product_cat
                        ├─                        → product_brand
                        ├─                        → product_application
                        ├─                        → product_material
                        └─ pa_color / pa_volume / pa_origin / pa_packaging (attributes)

wp_termmeta(product_brand) → logo, banner, short_description, seo_content,
                             website, country, seo_title, meta_description

wp_saha_quotes      → yêu cầu báo giá, pipeline status
wp_saha_leads       → lead tổng hợp mọi nguồn
wp_saha_logs        → log hệ thống + audit admin action
wp_saha_search_logs → query, result_count, created_at (optional, tắt mặc định)
```

Quy tắc chọn nơi lưu:
- Dữ liệu **filter/SEO được** → taxonomy.
- Dữ liệu **hiển thị / chỉ thuộc 1 sản phẩm** → postmeta, prefix `_saha_`.
- Dữ liệu **nhiều dòng, cần index/báo cáo** (quote/lead/log) → custom table.

## D. WooCommerce data strategy

- `post_type=product` là nguồn duy nhất. **Không** tạo CPT sản phẩm.
- SKU/giá/gallery/stock dùng WooCommerce native (`_sku`, `_price`, `_thumbnail_id`, `_product_image_gallery`, `_stock_status`).
- Trường ngành keo không thuộc Woo → postmeta nhóm theo panel:
  `_saha_specs` (repeater key/value), `_saha_application_text`, `_saha_usage`, `_saha_warning`,
  `_saha_docs` (attachment_id + type TDS/SDS/Catalogue/Manual), `_saha_unit`,
  `_saha_availability` (map `_stock_status`), `_saha_product_line`, `_saha_cta_mode`.
- Catalogue mode: ẩn giá/cart bằng filter, **không** xoá WooCommerce → bật bán hàng sau chỉ cần đổi 1 setting.

## E. Taxonomy strategy

| Taxonomy | Nguồn | Hierarchical | Rewrite | Phase |
|---|---|---|---|---|
| `product_cat` | Woo | có | `danh-muc` | 2 |
| `product_tag` | Woo | không | mặc định | 2 |
| `product_brand` | saha-core | không | `thuong-hieu` | 2 |
| `product_application` | saha-core | có | `ung-dung` | 2 |
| `product_material` | saha-core | không | `vat-lieu` (mặc định tắt) | 2 |
| `pa_color / pa_volume / pa_origin / pa_packaging` | Woo attribute | — | — | 2 |

`product_material` chỉ bật qua setting khi thật sự dùng để filter (spec §69).
Nếu WooCommerce đã có `product_brand` native → saha-core **phát hiện và dùng lại**, chỉ bổ sung term meta.

## F. Custom table strategy

Tạo bằng `dbDelta()`, charset `$wpdb->get_charset_collate()`.
Versioned migration: hằng `SAHA_CORE_DB_VERSION` ↔ option `saha_core_db_version`; idempotent; không DROP tự động; không xoá data khi deactivate.

## G. Plugin architecture (`saha-core`)

Namespace `Saha\Core`, autoload nội bộ, prefix hàm `saha_`, text domain `saha-core`.

```
saha-core.php            bootstrap, constants, guard PHP/WP version, activation hook
uninstall.php            chỉ xoá data khi admin bật "Delete Data on Uninstall"
includes/
  class-loader.php        container + hook registry
  class-install.php       activate/deactivate, migration runner
  class-migrator.php      chạy database/migrations/*.php theo version
  class-settings.php      option `saha_core_settings` + Settings API
  class-security.php      nonce, capability, sanitize, rate limit, honeypot
  class-logger.php        logger abstraction → wp_saha_logs / error_log
  class-roles.php         roles + capabilities
  class-brand.php         taxonomy + term meta + service        (Phase 2)
  class-product.php       meta panel, meta service              (Phase 2)
  class-search.php        search service                        (Phase 3)
  class-quote.php         repository + service                  (Phase 4)
  class-lead.php          repository                            (Phase 4)
  class-customer.php      gộp lead theo phone                   (Phase 4)
  class-api.php           REST bootstrap                        (Phase 3+)
  class-admin.php         menu SAHA, list table, dashboard
  class-analytics.php     do_action events                      (Phase 6)
  class-cache.php         transient/object cache + invalidation (Phase 7)
api/routes/               products.php brands.php search.php quote.php contact.php
database/migrations/      001-*.php 002-*.php 003-*.php
admin/pages | views | assets
templates/emails/
```

## H. Child theme architecture

`functions.php` **chỉ bootstrap**:

```php
foreach ( array( 'setup', 'enqueue', 'helpers', 'hooks', 'woocommerce', 'shortcodes', 'ux-elements' ) as $f ) {
    require_once get_stylesheet_directory() . "/inc/{$f}.php";
}
```

- `enqueue.php`: conditional enqueue (search JS chỉ khi bật header search, quote JS chỉ `is_product()` / trang báo giá), version theo `filemtime()`.
- `template-parts/`: component tái sử dụng (product-card, brand-card, breadcrumb, cta, quote-modal, pagination, empty-state).
- `woocommerce/`: override template qua cơ chế chính thức của Woo.
- CSS namespace `.saha-`, biến trong `:root`, không `!important` tràn lan.

## I. API architecture

`/wp-json/saha/v1/`

| Method | Route | Auth | Rate limit |
|---|---|---|---|
| GET | `/products` | public | 60/min IP |
| GET | `/products/{id}` | public | 60/min |
| GET | `/brands`, `/brands/{slug}` | public | 60/min |
| GET | `/search?q=&limit=&page=` | public | 30/min |
| POST | `/quote` | nonce + honeypot | 5/10min |
| POST | `/contact` | nonce + honeypot | 5/10min |
| * | `/admin/*` | `permission_callback` + capability | — |

Envelope: `{success, message, data}` / `{success:false, message, errors}`; HTTP status đúng (200/201/400/403/404/429/500). `per_page` max 50.

## J. Security architecture

Nonce ≠ authorization → luôn kèm `current_user_can()`. `$wpdb->prepare()` cho mọi query có input. Sanitize vào / escape ra. Honeypot + rate limit transient theo hash(IP+salt), không lưu IP thô dài hạn. REST `permission_callback` bắt buộc. Backend xác thực `product_id` tồn tại. Không log password/token/secret. Bulk action có nonce + capability.

## K. SEO architecture

Rank Math/Yoast là chủ; saha-core chỉ **feed dữ liệu**, không tạo schema/meta trùng. Brand archive indexable, H1 = "Tên thương hiệu + nhóm sản phẩm", intro trên + SEO content dưới grid, pagination canonical đúng. Breadcrumb: dùng Rank Math nếu active, fallback custom, **không** render 2 lần. Sitemap để plugin SEO lo. Robots chặn `wp-admin`, param search rác; không chặn CSS/JS.

## L. Performance architecture

Conditional enqueue; transient/object cache cho brand grid, featured product, autocomplete; invalidate ở `save_post_product`, `edited_product_cat`, `edited_product_brand`, `delete_post`. Tránh N+1. WP_Query luôn pagination, dùng `fields => ids` khi đủ. Không `meta_query` khi taxonomy làm được. Mục tiêu LCP <2.5s, CLS <0.1, INP <200ms. Tương thích LiteSpeed / Redis / Cloudflare, không hardcode plugin cache nào.

## M. Admin architecture

Menu `SAHA`: Dashboard · Yêu cầu báo giá · Khách hàng tiềm năng · Liên hệ · Thương hiệu · Báo cáo · Cấu hình.
Quote/Lead dùng `WP_List_Table`: search, filter status/sales/date, pagination, bulk action (gán sales, đổi trạng thái), ghi chú.
Roles: Administrator, SEO Manager, Content Manager, Sales, Warehouse — kiểm tra bằng capability `manage_saha*`, không theo tên role.
Product edit: field chia panel (Thông tin · Thông số · Ứng dụng/HDSD · Tài liệu · CTA) trong WooCommerce product data.

## N. Deployment architecture

`Local → Git → Staging → QA → Backup → Production`. Chỉ version-control `flatsome-child` + `saha-core`. Không commit wp-config, uploads, cache, DB. Không hardcode domain (`home_url()`, `plugins_url()`, `get_stylesheet_directory_uri()`). Env qua `WP_ENVIRONMENT_TYPE`. Rollback = git revert + restore DB snapshot trước migration.

## O. Danh sách phase

| Phase | Nội dung | Trạng thái |
|---|---|---|
| 1 | Foundation: child theme, core plugin, loader, settings, roles, security, logger, migration | hoàn thành |
| 2 | Catalogue: brand/application taxonomy, product meta panel, single product, archive, card | hoàn thành |
| 3 | Search: service, REST, autocomplete, filter giữ URL | hoàn thành |
| 4 | Lead/Quote: form, service, admin list table, email | hoàn thành |
| 5 | Homepage: UX elements, shortcode, block | **hoàn thành** |
| 6 | SEO: Rank Math bridge, breadcrumb, brand SEO | chờ |
| 7 | Performance: cache layer, invalidation, asset | chờ |
| 8 | QA: responsive, browser, security, SEO, performance | chờ |

## P. File sẽ tạo (Phase 1)

Xem `docs/PHASE-1.md`.

## Q. Những điểm cần tránh

1. Sửa WordPress / WooCommerce / Flatsome core hoặc ghi file trong theme cha.
2. Tạo CPT sản phẩm song song WooCommerce.
3. Business logic trong `functions.php`.
4. Hardcode domain, Zalo link, hotline, email nhận báo giá, banner.
5. SQL nối chuỗi, thiếu `prepare()`.
6. Coi nonce là authorization.
7. Tin `product_id` từ hidden field.
8. Custom field trùng WooCommerce attribute.
9. Tạo Product/Breadcrumb schema trùng Rank Math.
10. Load toàn bộ JS trên mọi trang.
11. `!important` và CSS inline tràn lan.
12. Xoá dữ liệu quote/lead khi deactivate.
13. Redirect toàn bộ 404 về homepage.
14. `meta_query` cho dữ liệu đáng lẽ là taxonomy.
15. Tự viết lại SEO plugin / redirect engine / cookie consent.
