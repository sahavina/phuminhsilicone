# SAHA Core

Business layer của website Tổng Kho Keo Dán SAHA.

## Purpose

Chứa toàn bộ logic nghiệp vụ, database, API, admin và automation — tách khỏi giao diện
(`saha-theme`; `flatsome-child` đã đóng băng) và khỏi WooCommerce core.

Từ 1.8 (SAHA Commerce Core): Theme Options và **runtime của SAHA Builder** (schema,
sanitize, render, CSS, REST) cũng nằm ở đây — trang dựng bằng builder vẫn hiển thị khi
tắt plugin `saha-builder` (ứng dụng soạn thảo).

## Files

```
saha-core.php              bootstrap: constants, guard PHP/WP, autoloader, lifecycle
uninstall.php              xoá data CHỈ khi admin bật tuỳ chọn
includes/
  functions.php            helper công khai saha_* (interface cho child theme)
  class-loader.php         container + registry module
  class-install.php        activate / deactivate / upgrade
  class-migrator.php       migration runner
  class-settings.php       schema + sanitize + Settings API
  class-security.php       sanitize, nonce, honeypot, rate limit, upload mime
  class-logger.php         logger abstraction, redact key nhạy cảm
  class-roles.php          role + capability
  class-admin.php          menu SAHA, Dashboard, Cấu hình
  ThemeOptions/            Schema, Sanitizer, Repository, CssVariables, Rest/SettingsController
  Performance/             CssFileStore (file CSS có hash trong uploads/saha/css)
  Builder/                 runtime builder — xem bên dưới
public/assets/css/         builder.css (CSS nền của layout builder ở frontend)
database/migrations/       001-create-quotes · 002-create-leads · 003-create-logs
admin/views|assets|pages   UI admin
api/routes/                REST route (Phase 3+)
templates/emails/          email template (Phase 4)
```

Autoload: code mới PSR-4 `Saha\Core\Builder\Renderer` → `includes/Builder/Renderer.php`;
code cũ `Saha\Core\Foo_Bar` → `includes/class-foo-bar.php`. Không đặt thư mục PSR-4 trùng
tên thư mục cũ khác hoa/thường (ví dụ `Tables` vs `tables`) — Windows không phân biệt.

## Builder runtime

```
Builder/
  Schema/Node, Document, SchemaMigrator   cấu trúc JSON + migrate phiên bản schema
  Controls/*                              mỗi loại giá trị một sanitizer (color, size, spacing,
                                          typography, link, media, background, richtext…)
  Elements/*                              20 element Phase 1 (bố cục, nội dung, marketing, sản phẩm, blog)
  Icons                                   bộ icon SVG nội tuyến
  ElementRegistry, Sanitizer              định nghĩa element (nguồn duy nhất) + sanitize tài liệu
  Renderer, RenderContext, RenderCache    JSON → HTML; cache subtree tĩnh
  CssRules, CssGenerator                  style .saha-e-{id}, responsive 1024/767
  LayoutRepository, LayoutService         post meta, lưu (khoá, baseHash, CSS, revision)
  Frontend, Rest/BuilderController        the_content + CSS; REST /builder/*
```

Blocks dùng chung: `includes/Blocks/` — post type `saha_block` (SAHA → Blocks), REST `/blocks`,
element Block render nội dung mới nhất (chống vòng lặp, tối đa 3 cấp).

Thêm element: class kế thừa `Builder\Elements\Element` (definition + render + styles), đăng ký
qua `add_action( 'saha_builder_elements', fn( $r ) => $r->register( new My_Element() ) )`.
Chi tiết: `docs/scc/PHASE-1.2.md`.

## Hooks

Action: `saha_core_loaded`, `saha_core_activated`, `saha_core_deactivated`,
`saha_core_upgraded`, `saha_core_migrated`, `saha_core_admin_menu`, `saha_log`,
`saha_theme_options_saved`, `saha_builder_elements`, `saha_builder_register_controls`,
`saha_builder_render_before`, `saha_builder_render_after`, `saha_builder_saved`.

## Filters

`saha_core_modules`, `saha_core_settings_schema`, `saha_core_setting`,
`saha_core_roles`, `saha_core_dashboard_stats`, `saha_rate_limit`,
`saha_theme_options`, `saha_theme_options_schema`, `saha_css_variables`, `saha_theme_font_stacks`,
`saha_builder_role_caps`, `saha_builder_post_types`, `saha_builder_element_definition`,
`saha_builder_render_element`, `saha_builder_node_classes`, `saha_builder_render_cache`,
`saha_builder_migrate_document`, `saha_builder_enqueue_layout_css`, `saha_builder_icons`.

## API

`/wp-json/saha/v1/` — route nạp tự động từ `api/routes/*.php` theo thứ tự tên file.

| Method | Route | Rate limit |
|---|---|---|
| GET | `/search?q=&limit=&page=` | 30/phút |
| GET | `/products?page=&per_page=&brand=&category=&search=` | 60/phút |
| GET | `/products/{id}` | 60/phút |
| GET | `/brands`, `/brands/{slug}` | 60/phút |
| GET | `/nonce` | 20/phút |
| POST | `/quote` | 5 / 10 phút |
| POST | `/contact` | 5 / 10 phút |
| GET, POST | `/settings` | quyền `edit_theme_options` |
| GET | `/builder/elements` | quyền `edit_saha_builder` |
| GET | `/builder/{id}` | `edit_saha_builder` + `edit_post` |
| POST | `/builder/save`, `/builder/lock/{id}` | `edit_saha_builder` + `edit_post` |
| POST | `/builder/render` | `edit_saha_builder` (+ `edit_post` nếu có postId) |
| GET, POST | `/blocks` | `edit_saha_builder` (+ quyền xuất bản trang để tạo) |

Envelope: `{success, message, data}` / `{success:false, code, message, errors}`.
Thêm route mới: tạo file trong `api/routes/`, return closure nhận `$namespace`.

## Database

`wp_saha_quotes`, `wp_saha_leads`, `wp_saha_logs` (+ `wp_saha_search_logs` optional).
Version: hằng `SAHA_CORE_DB_VERSION` ↔ option `saha_core_db_version`.

Thêm thay đổi schema: tạo file mới `database/migrations/00N-*.php` return closure
`function ( string $charset_collate, wpdb $wpdb ): void`, rồi tăng `SAHA_CORE_DB_VERSION`.
Không sửa file migration đã phát hành.

## Settings

Một option duy nhất `saha_core_settings`. Thêm field bằng filter
`saha_core_settings_schema` với `type` thuộc:
`text|textarea|html|email|url|phone|int|float|bool|key`.

## Capabilities

`manage_saha`, `manage_saha_quotes`, `manage_saha_leads`, `manage_saha_products`,
`manage_saha_brands`, `manage_saha_reports`, `manage_saha_settings`,
`edit_saha_builder` (administrator, editor), `manage_saha_templates` (administrator).

Role: `saha_seo_manager`, `saha_content_manager`, `saha_sales`, `saha_warehouse`.
Luôn kiểm tra bằng `current_user_can( <capability> )`, không so tên role.

## Installation

Kích hoạt plugin trước khi kích hoạt theme. Yêu cầu PHP ≥ 8.2, WP ≥ 6.4.
WooCommerce cần cho module sản phẩm (Phase 2+).

## Testing

`php tests/smoke.php` (không cần WordPress) · `wp saha qa` · `php tests/http-smoke.php <url>`.
Checklist từng phase: `docs/PHASE-*.md`, `docs/scc/PHASE-1.x.md` mục 8.
