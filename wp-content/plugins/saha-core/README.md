# SAHA Core

Business layer của website Tổng Kho Keo Dán SAHA.

## Purpose

Chứa toàn bộ logic nghiệp vụ, database, API, admin và automation — tách hoàn toàn khỏi
giao diện (nằm ở child theme `flatsome-child`) và khỏi WooCommerce/Flatsome core.

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
database/migrations/       001-create-quotes · 002-create-leads · 003-create-logs
admin/views|assets|pages   UI admin
api/routes/                REST route (Phase 3+)
templates/emails/          email template (Phase 4)
```

Autoload: `Saha\Core\Foo_Bar` → `includes/class-foo-bar.php`.

## Hooks

Action: `saha_core_loaded`, `saha_core_activated`, `saha_core_deactivated`,
`saha_core_upgraded`, `saha_core_migrated`, `saha_core_admin_menu`, `saha_log`.

## Filters

`saha_core_modules`, `saha_core_settings_schema`, `saha_core_setting`,
`saha_core_roles`, `saha_core_dashboard_stats`, `saha_rate_limit`.

## API

`/wp-json/saha/v1/` — khai báo ở `docs/ARCHITECTURE.md` mục I, triển khai từ Phase 3.

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
`manage_saha_brands`, `manage_saha_reports`, `manage_saha_settings`.

Role: `saha_seo_manager`, `saha_content_manager`, `saha_sales`, `saha_warehouse`.
Luôn kiểm tra bằng `current_user_can( <capability> )`, không so tên role.

## Installation

Kích hoạt plugin trước khi kích hoạt child theme. Yêu cầu PHP ≥ 8.0, WP ≥ 6.0.
WooCommerce cần cho module sản phẩm (Phase 2+).

## Testing

Xem `docs/PHASE-1.md` mục 8.
