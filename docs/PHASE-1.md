# PHASE 1 — Foundation

## 1. Goal

Dựng nền tảng để 7 phase sau chỉ cần "cắm module vào", không phải refactor:

- Flatsome Child Theme đúng chuẩn, `functions.php` chỉ bootstrap.
- Plugin `saha-core` với container, autoload, lifecycle, migration runner.
- Global settings (hotline, Zalo, CTA, email báo giá…) — hết hardcode.
- Roles + capabilities `manage_saha*`.
- Security layer (sanitize theo type, nonce, honeypot, rate limit, chặn mime thực thi).
- Logger abstraction có redact dữ liệu nhạy cảm.
- Admin menu SAHA + Dashboard + Cấu hình.

Chưa làm trong phase này: brand taxonomy, product meta, search, quote/lead business logic, UX element nghiệp vụ, homepage.

## 2. Architecture

```
saha-core (BUSINESS)                     flatsome-child (PRESENTATION)
  Loader ──┬── Logger                      functions.php (bootstrap)
           ├── Settings ◄──────────┐         inc/setup.php
           ├── Security            │         inc/helpers.php ── wrapper an toàn
           ├── Roles               └──────── inc/enqueue.php  ── conditional
           ├── Install ── Migrator            inc/hooks.php
           └── Admin                          inc/woocommerce.php
                                              inc/shortcodes.php
includes/functions.php  ── helper saha_*      inc/ux-elements.php
```

Child theme **chỉ** gọi `saha_*()` qua wrapper `saha_theme_*()`; nếu plugin tắt, mọi wrapper trả fallback và site không lỗi.

## 3. Files

Theme — `wp-content/themes/flatsome-child/`

| File | Vai trò |
|---|---|
| `style.css` | header child theme (không chứa CSS thật) |
| `functions.php` | bootstrap 7 module trong `inc/` |
| `inc/setup.php` | textdomain, theme support, image size, admin notice thiếu dependency |
| `inc/helpers.php` | wrapper an toàn quanh plugin + `saha_theme_part()` |
| `inc/enqueue.php` | register toàn bộ asset, enqueue theo ngữ cảnh, `SAHA_CONFIG` |
| `inc/hooks.php` | body class, sticky CTA, breadcrumb dispatcher, 404 |
| `inc/woocommerce.php` | catalogue mode, SKU trong card, per-page |
| `inc/shortcodes.php` | `[saha_hotline]` `[saha_zalo]` `[saha_company]` |
| `inc/ux-elements.php` | đăng ký UX Builder element (khung + 2 element) |
| `template-parts/common/sticky-cta.php` | CTA mobile: Gọi · Zalo · Báo giá |
| `template-parts/common/breadcrumb.php` | breadcrumb fallback (không schema) |
| `template-parts/common/empty-state.php` | empty state dùng chung + 404 |
| `assets/css/main.css` · `responsive.css` | component chung + breakpoint |
| `assets/css/product.css` · `brand.css` · `search.css` | style theo ngữ cảnh |
| `assets/js/main.js` | analytics event, debounce, `SAHA.api`, quote trigger |
| `assets/js/search.js` | autocomplete (register sẵn, enqueue ở Phase 3) |
| `assets/js/product-filter.js` · `quote-form.js` | scaffold có hợp đồng, hoàn thiện ở Phase 3/4 |

Plugin — `wp-content/plugins/saha-core/`

| File | Vai trò |
|---|---|
| `saha-core.php` | constants, guard PHP/WP, autoloader, activation, bootstrap |
| `uninstall.php` | chỉ xoá data khi admin bật tuỳ chọn |
| `includes/functions.php` | helper công khai `saha_*` |
| `includes/class-loader.php` | container + registry module |
| `includes/class-install.php` | activate/deactivate/upgrade |
| `includes/class-migrator.php` | migration runner idempotent |
| `includes/class-settings.php` | schema + sanitize + Settings API |
| `includes/class-security.php` | sanitize theo type, nonce, honeypot, rate limit, upload mime |
| `includes/class-logger.php` | log ra DB/debug.log, redact key nhạy cảm |
| `includes/class-roles.php` | 4 role + 7 capability |
| `includes/class-admin.php` | menu SAHA, Dashboard, Cấu hình, dashboard widget |
| `database/migrations/001-003` | quotes, leads, logs |
| `admin/views/dashboard.php` · `settings.php` | view |
| `admin/assets/admin.css` | style admin |

## 4. Database

Tạo bằng `dbDelta()`, collation `$wpdb->get_charset_collate()`, idempotent.

- `wp_saha_quotes` — 15 cột, index: `phone`, `product_id`, `status`, `assigned_user_id`, `created_at`.
- `wp_saha_leads` — 12 cột, index: `phone`, `source`, `status`, `assigned_user_id`, `created_at`.
- `wp_saha_logs` — level, channel, message, context JSON, user_id, created_at.

Option: `saha_core_settings`, `saha_core_version`, `saha_core_db_version`, `saha_core_migrations_ran`.
`SAHA_CORE_DB_VERSION = 1.0.0`. Tăng version + thêm file `004-*.php` cho thay đổi sau; **không** DROP tự động.

## 5. Code

Xem các file ở mục 3. Toàn bộ 27 file PHP đã pass `php -l` (PHP 8.0).

## 6. Hooks

Action do plugin/theme phát:

| Hook | Khi nào |
|---|---|
| `saha_core_loaded` | mọi service đã register |
| `saha_core_activated` / `saha_core_deactivated` | lifecycle |
| `saha_core_upgraded` | version code > version đã cài |
| `saha_core_migrated` | vừa chạy migration |
| `saha_core_admin_menu` | điểm cắm submenu cho module sau |
| `saha_log` | ghi log qua action |
| `saha_theme_register_ux_elements` | điểm cắm UX element |

Filter:

| Filter | Dùng để |
|---|---|
| `saha_core_modules` | thêm/bớt module |
| `saha_core_settings_schema` | thêm setting |
| `saha_core_setting` | can thiệp giá trị khi đọc |
| `saha_core_roles` | định nghĩa role |
| `saha_core_dashboard_stats` | thêm số liệu dashboard |
| `saha_rate_limit` | đổi/tắt rate limit |
| `saha_theme_script_config` | thêm dữ liệu cho JS |
| `saha_theme_part_args` | can thiệp dữ liệu template part |
| `saha_theme_products_per_page` | số sản phẩm mỗi trang |
| `saha_theme_mobile_cta_enabled` | bật/tắt sticky CTA |

JS event: `saha:click_phone`, `saha:click_zalo`, `saha:view_product`, `saha:search`, `saha:quote:open`.

## 7. Security

- Sanitize theo type khai báo trong schema; field lạ bị loại khỏi option.
- Escape ở view: `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`.
- Trang admin check `current_user_can()` trước khi render, `wp_die( …, 403 )` nếu thiếu quyền.
- `Security::guard_admin_action()` = capability **và** nonce (nonce không phải authorization).
- Rate limit qua transient theo `md5(scope + IP + wp_salt)` — không lưu IP thô.
- Honeypot `saha_hp_email` + CSS ẩn ngoài viewport (không `display:none` để bot vẫn thấy).
- `upload_mimes` loại `php*`, `phar`, `exe`, `sh`, `js`, `asp`… dựa trên whitelist WordPress.
- Logger redact mọi key chứa `password|secret|token|api_key|auth|card|cvv|nonce`.
- `href="tel:"` chỉ chứa digits và `+`.
- Uninstall không xoá dữ liệu trừ khi admin bật tuỳ chọn.

## 8. Testing

Đã chạy: `php -l` trên 27 file — 0 lỗi.

Checklist thủ công sau khi cài vào WordPress:

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | Activate plugin | không fatal; 3 bảng `wp_saha_*` được tạo |
| 2 | `SHOW INDEX FROM wp_saha_quotes` | có 5 index như thiết kế |
| 3 | Deactivate | dữ liệu 3 bảng **còn nguyên**; role custom bị xoá |
| 4 | Activate lại | migration không chạy lại (option `saha_core_migrations_ran`) |
| 5 | Kích hoạt child theme | frontend không lỗi, không JS error |
| 6 | Tắt plugin, giữ child theme | site vẫn render, admin hiện notice thiếu dependency |
| 7 | Cấu hình → lưu hotline/Zalo | giá trị lưu đúng, cache option được xoá |
| 8 | Nhập `<script>alert(1)</script>` vào Tên công ty | bị sanitize, không thực thi |
| 9 | Nhập email sai định dạng | lưu rỗng thay vì rác |
| 10 | Login user role Sales | thấy menu SAHA, **không** thấy Cấu hình |
| 11 | Truy cập trực tiếp `admin.php?page=saha-core-settings` bằng Sales | 403 |
| 12 | Upload file `.php` vào Media | bị từ chối |
| 13 | Mobile 375px | sticky CTA hiện, không che footer (body có padding-bottom) |
| 14 | Desktop 1366px | sticky CTA ẩn |
| 15 | Click hotline | `dataLayer` nhận `saha_click_phone` |
| 16 | `[saha_hotline]` trong UX Builder | render số từ settings, `tel:` chỉ digits |
| 17 | Bật catalogue mode | giá đổi thành "Liên hệ báo giá", mất nút giỏ hàng |
| 18 | Tắt catalogue mode | giá và giỏ hàng trở lại |
| 19 | Trang 404 | có search box + danh mục, **không** redirect về homepage |
| 20 | Keyboard Tab qua sticky CTA | focus ring hiện rõ |

## 9. Installation

```bash
# 1. Trong WordPress đã cài Flatsome + WooCommerce
cd wp-content
git clone <repo> saha-src        # hoặc copy trực tiếp 2 thư mục

# 2. Đặt đúng chỗ
themes/flatsome-child/
plugins/saha-core/
```

1. WP Admin → Plugins → kích hoạt **SAHA Core** (tạo bảng + role + settings mặc định).
2. Appearance → Themes → kích hoạt **Flatsome Child — SAHA**.
3. SAHA → Cấu hình → điền hotline, Zalo, email báo giá, địa chỉ.
4. Settings → Permalinks → Save (flush rewrite).

Yêu cầu: PHP ≥ 8.0, WordPress ≥ 6.0. Plugin tự thoát an toàn kèm admin notice nếu không đạt.

## 10. Acceptance criteria

- [x] Không sửa WordPress / WooCommerce / Flatsome core.
- [x] `functions.php` chỉ bootstrap, không business logic.
- [x] Không hardcode domain, hotline, Zalo, email.
- [x] Mọi PHP file pass `php -l` (PHP 8.0).
- [x] Migration idempotent, có version, không DROP tự động.
- [x] Deactivate không mất dữ liệu.
- [x] Admin page có capability check.
- [x] Sanitize vào / escape ra ở toàn bộ view.
- [x] Conditional enqueue, không load JS không dùng.
- [x] CSS namespace `.saha-`, không `!important`.
- [x] Sticky CTA không che nội dung, có nhãn accessible.
- [ ] Checklist 20 test ở mục 8 chạy trên môi trường WordPress thật (cần môi trường local/staging).
