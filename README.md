# Tổng Kho Keo Dán SAHA — Source

Website thương mại/catalogue sản phẩm keo dán công nghiệp.
Công ty TNHH Thương mại Dịch vụ Trực tuyến SAHA — tongkhokeodan.com

## Stack

WordPress ≥ 6.0 · PHP ≥ 8.0 · MySQL · WooCommerce · Flatsome + Child Theme · UX Builder

## Nội dung repo

Repo **chỉ** version-control code do dự án viết:

```
wp-content/themes/flatsome-child/   presentation layer
wp-content/plugins/saha-core/       business layer
docs/                               kiến trúc + tài liệu từng phase
```

WordPress core, Flatsome, WooCommerce, uploads, cache, `wp-config.php` **không** được commit.

## Tài liệu

| File | Nội dung |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | kiến trúc tổng thể, data model, taxonomy, API, security, SEO, performance |
| [docs/PHASE-1.md](docs/PHASE-1.md) | Foundation — goal, files, DB, hooks, security, testing, acceptance |
| [docs/PHASE-2.md](docs/PHASE-2.md) | Catalogue — taxonomy, term meta, product fields, frontend sản phẩm & thương hiệu |
| [docs/PHASE-3.md](docs/PHASE-3.md) | Search & Filter — relevance, REST API, autocomplete, bộ lọc giữ state URL |
| [docs/PHASE-4.md](docs/PHASE-4.md) | Quote & Lead — form, REST POST, email, admin CRM mini, báo cáo |
| [docs/PHASE-5.md](docs/PHASE-5.md) | Homepage — UX elements, catalog service có cache, layout mẫu |
| [docs/layouts/](docs/layouts/) | Layout UX Builder mẫu: trang chủ, footer |
| [wp-content/plugins/saha-core/README.md](wp-content/plugins/saha-core/README.md) | module plugin |
| [wp-content/themes/flatsome-child/README.md](wp-content/themes/flatsome-child/README.md) | module theme |

## Cài đặt nhanh

1. Cài WordPress + WooCommerce + Flatsome.
2. Copy/symlink `wp-content/plugins/saha-core` và `wp-content/themes/flatsome-child` vào site.
3. Kích hoạt plugin **SAHA Core** (tạo bảng, role, settings).
4. Kích hoạt theme **Flatsome Child — SAHA**.
5. SAHA → Cấu hình: điền hotline, Zalo, email báo giá.
6. Settings → Permalinks → Save.

## Tiến độ

| Phase | Nội dung | Trạng thái |
|---|---|---|
| 1 | Foundation | ✅ hoàn thành |
| 2 | Catalogue (brand, product fields, frontend) | ✅ hoàn thành |
| 3 | Search + filter | ✅ hoàn thành |
| 4 | Quote + Lead + admin CRM | ✅ hoàn thành |
| 5 | Homepage + UX elements | ✅ hoàn thành |
| 6 | SEO | ⏳ |
| 7 | Performance | ⏳ |
| 8 | QA | ⏳ |

## Kiểm thử

```bash
php tests/smoke.php
```

Smoke test logic thuần (validate, sanitize, search tokenizer) — không cần WordPress/MySQL.
Checklist test trên site thật nằm ở mục 8 của từng `docs/PHASE-*.md`.

## Quy ước

- Không sửa WordPress / WooCommerce / Flatsome core.
- `functions.php` chỉ bootstrap.
- Prefix hàm `saha_`, CSS namespace `.saha-`, text domain `saha-core` / `flatsome-child`.
- Mọi query có input người dùng phải `$wpdb->prepare()`.
- Không hardcode domain, hotline, Zalo, email.
- Mỗi phase có tài liệu riêng trong `docs/`.

## Workflow

`Local → Git → Staging → QA → Backup → Production`, có rollback (git revert + restore DB snapshot trước migration).
