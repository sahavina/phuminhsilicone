# Flatsome Child — SAHA

Presentation layer của website Tổng Kho Keo Dán SAHA.

## Purpose

Chỉ chứa giao diện: template, CSS, JS, UX Builder element, shortcode.
**Không** chứa business logic, không truy cập `$wpdb`, không ghi database.
Mọi dữ liệu nghiệp vụ lấy qua helper `saha_*()` của plugin `saha-core`.

## Files

```
functions.php                 CHỈ bootstrap 7 module trong inc/
inc/setup.php                 textdomain, theme support, image size, dependency notice
inc/helpers.php               wrapper an toàn quanh plugin + saha_theme_part()
inc/enqueue.php               register asset + conditional enqueue + SAHA_CONFIG
inc/hooks.php                 body class, sticky CTA, breadcrumb, 404
inc/woocommerce.php           catalogue mode, SKU trong card, per-page
inc/shortcodes.php            [saha_hotline] [saha_zalo] [saha_company]
inc/ux-elements.php           UX Builder element
template-parts/common/        sticky-cta · breadcrumb · empty-state
template-parts/product|brand|search/   component theo module (Phase 2–3)
woocommerce/                  override template Woo (Phase 2)
assets/css/                   main · responsive · product · brand · search
assets/js/                    main · search · product-filter · quote-form
```

## Hooks

Filter: `saha_theme_script_config`, `saha_theme_part_args`,
`saha_theme_products_per_page`, `saha_theme_mobile_cta_enabled`.

Action: `saha_theme_register_ux_elements`.

JS CustomEvent trên `document`: `saha:click_phone`, `saha:click_zalo`,
`saha:view_product`, `saha:search`, `saha:quote:open`.

## JavaScript API

`window.SAHA` do `main.js` cung cấp:

| Hàm | Mô tả |
|---|---|
| `SAHA.api(path, options)` | fetch tới `/wp-json/saha/v1/`, tự gắn `X-WP-Nonce`, throw Error có `status` + `errors` |
| `SAHA.debounce(fn, wait)` | debounce dùng cho search/filter |
| `SAHA.track(name, payload)` | bắn event analytics (dataLayer + CustomEvent) |
| `SAHA.config` | dữ liệu từ `SAHA_CONFIG` (restUrl, nonce, hotlines, i18n…) |

## Shortcode / UX element

Tất cả nằm trong nhóm **SAHA** của UX Builder. Danh sách đầy đủ + tham số: `docs/PHASE-5.md` mục 6.

| Nhóm | Shortcode |
|---|---|
| Liên hệ | `[saha_hotline]` `[saha_zalo]` `[saha_company]` `[saha_social]` `[saha_copyright]` |
| Sản phẩm | `[saha_products]` `[saha_featured_products]` `[saha_brand_products]` |
| Danh mục | `[saha_category_grid]` `[saha_application_grid]` `[saha_brand_grid]` `[saha_term_links]` |
| Tìm kiếm | `[saha_search]` `[saha_product_filter]` |
| Form | `[saha_quote_form]` `[saha_contact_form]` `[saha_quote_cta]` |

Layout mẫu: `docs/layouts/homepage.ux.txt`, `docs/layouts/footer-block.ux.txt`.

## Asset

Mọi asset `wp_register_*` một lần, enqueue theo ngữ cảnh:

| Handle | Điều kiện load |
|---|---|
| `saha-main`, `saha-responsive` | mọi trang |
| `saha-product` | `is_woocommerce()` / cart / checkout |
| `saha-brand` | `is_tax( product_brand \| product_application )` |
| `saha-search` | `is_search()` |
| `saha-search` (JS) | Phase 3 |
| `saha-product-filter`, `saha-quote-form` (JS) | Phase 3 / 4 |

Version = `filemtime()` để cache-bust chính xác.

## Settings

Không hardcode hotline, Zalo, email, CTA, domain. Đọc qua
`saha_theme_setting( 'key' )` — tự fallback khi plugin tắt.

## Installation

Kích hoạt `SAHA Core` trước, sau đó kích hoạt theme này (Template: `flatsome`).

## Testing

Xem `docs/PHASE-1.md` mục 8 (test 5, 6, 13–20 thuộc theme).
