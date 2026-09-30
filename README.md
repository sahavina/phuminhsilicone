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
| 3 | Search + filter | ⏳ |
| 4 | Quote + Lead + admin CRM | ⏳ |
| 5 | Homepage + UX elements | ⏳ |
| 6 | SEO | ⏳ |
| 7 | Performance | ⏳ |
| 8 | QA | ⏳ |

## Quy ước

- Không sửa WordPress / WooCommerce / Flatsome core.
- `functions.php` chỉ bootstrap.
- Prefix hàm `saha_`, CSS namespace `.saha-`, text domain `saha-core` / `flatsome-child`.
- Mọi query có input người dùng phải `$wpdb->prepare()`.
- Không hardcode domain, hotline, Zalo, email.
- Mỗi phase có tài liệu riêng trong `docs/`.

## Workflow

`Local → Git → Staging → QA → Backup → Production`, có rollback (git revert + restore DB snapshot trước migration).
