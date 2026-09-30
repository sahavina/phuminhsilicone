# SAHA Commerce Core (SCC) — Phân tích kiến trúc & Technical Design

> Phản hồi đầu tiên theo §120 của master prompt: **chỉ thiết kế, chưa code.**
> Tài liệu này là nền để review trước khi bắt đầu Phase 1.

| | |
|---|---|
| Phạm vi | theme riêng + builder riêng thay Flatsome, trên WordPress + WooCommerce |
| Tên hệ thống | SAHA Commerce Core (SCC) |
| Repo | `sahavina/phuminhsilicone` — dùng tiếp, không tạo repo mới |
| Trạng thái | **Đã duyệt** — builder riêng (R2), đóng băng `flatsome-child` (D4). Chưa xác nhận: PHP hosting, quy mô team |

---

## 0. Hiện trạng và các quyết định nền tảng

Spec SCC **không bắt đầu từ số 0**. Repo đã có code chạy thật, đã QA trên WordPress 7.1.2 + WooCommerce 11.1.2 (xem `docs/PHASE-8.md`):

| Đang có | Trạng thái | Với SCC |
|---|---|---|
| Plugin `saha-core` — namespace `Saha\Core`, REST `saha/v1` (`/products`, `/search`, `/brands`, `/quote`, `/contact`, `/nonce`), tìm kiếm relevance, bộ lọc, báo giá, CRM, SEO bridge, cache, migration, WP-CLI, QA | ✅ QA xong | **Giữ và mở rộng.** Trùng đúng tên plugin, namespace và REST namespace mà spec yêu cầu → không tạo plugin thứ hai cùng tên |
| Theme `flatsome-child` | ⚠️ chưa QA giao diện (thiếu Flatsome) | **Đóng băng.** Chỉ 2 file phụ thuộc Flatsome (`inc/hooks.php`, `inc/ux-elements.php`); các template part còn lại chuyển sang `saha-theme` |
| `docs/layouts/*.ux.txt` (shortcode UX Builder) | chưa dùng | Thay bằng layout JSON của SAHA Builder |

### Quyết định nền tảng

| # | Quyết định | Lý do |
|---|---|---|
| D1 | `saha-core` hiện có **là** SAHA Core của SCC; module mới thêm vào cùng plugin | Trùng tên/namespace/REST; tránh hai plugin cùng slug; giữ code đã QA (spec §121: ổn định trước) |
| D2 | Tách **runtime** và **editor**: `saha-core` chứa lưu trữ + schema + renderer + REST của builder; plugin `saha-builder` chỉ chứa ứng dụng soạn thảo (React) | Trang đã dựng **vẫn hiển thị** nếu editor bị tắt/lỗi; editor (nặng, cần build Node) phát hành độc lập. Spec §4 và §6 mâu thuẫn nhẹ về chỗ đặt "render" — chọn phương án an toàn cho frontend |
| D3 | `saha-theme` là **classic PHP theme** độc lập (không phải child của theme nào), có `theme.json` chỉ để style block editor và block Cart/Checkout của WooCommerce | Header/footer/template do SAHA Builder quản lý, không dùng Site Editor; nhưng WooCommerce 11 dùng block Cart/Checkout mặc định (đã xác minh trên site local) nên phải style block |
| D4 | `flatsome-child` đóng băng, không thêm tính năng; xoá sau khi `saha-theme` đạt đủ chức năng | Spec cấm phụ thuộc Flatsome; tránh làm hai giao diện song song |
| D5 | Text domain: theme `saha` (spec §75); plugin giữ `saha-core`, `saha-builder` | WordPress khuyến nghị text domain = slug plugin để nạp bản dịch |
| D6 | PHP tối thiểu **8.2** cho `saha-theme`, `saha-builder` và module mới của `saha-core` | Theo spec §3. **Máy local đang PHP 8.0.30 → phải nâng XAMPP lên 8.2 trước Phase 1** |
| D7 | Code mới theo spec §79: PSR-4, class PascalCase, method camelCase. Code cũ (`includes/class-*.php`, snake_case) **giữ nguyên**, autoloader hỗ trợ cả hai | Không viết lại code đã QA chỉ để đổi style |
| D8 | Build JS/CSS bằng **`@wordpress/scripts`** (webpack) thay vì Vite | Tự external hoá `wp.*` (components, data, api-fetch, i18n), sinh `*.asset.php` (dependency + version), tích hợp dịch chuỗi JS. Vite phải tự cấu hình lại toàn bộ phần này |
| D9 | Commit thư mục `build/` đã biên dịch; `node_modules/` không commit | Deploy bằng rsync (như README) không cần Node trên server |
| D10 | REST giữ envelope hiện tại và **thêm** `code` vào lỗi: `{success:false, code, message, errors}` | Spec §99 cần `code`; frontend hiện tại đọc `errors` — thêm field không phá client cũ |
| D11 | Mini cart, Quick View add-to-cart dùng **WooCommerce Store API** (`/wc/store/v1/cart`) | Spec §118: không viết lại WooCommerce; Store API có sẵn update quantity, nonce, tương thích block Cart |

---

## 1. Phân tích kiến trúc tổng thể

### 1.1. Trách nhiệm theo tầng (spec §118)

| Tầng | Chịu trách nhiệm | Không làm |
|---|---|---|
| WordPress | CMS, user, post, media, quyền, revisions, cron | — |
| WooCommerce | sản phẩm, giá, tồn kho, giỏ, checkout, thanh toán, vận chuyển, đơn hàng, Product schema | — |
| `saha-core` | business data (thương hiệu, báo giá, lead…), builder runtime (schema, registry, renderer, CSS), template engine + điều kiện, reusable block, mega menu, theme options storage, REST, cache, bảo mật, tích hợp | UI soạn thảo; HTML layout trang |
| `saha-builder` | ứng dụng React: page builder, header/footer builder, template editor, theme options UI, navigator, history | lưu dữ liệu trực tiếp (chỉ gọi REST của core) |
| `saha-theme` | khung trang (`header.php`, `footer.php`…), template WordPress/WooCommerce fallback, component frontend (product card, blog card, mini cart drawer…), asset, responsive, a11y | business logic, query phức tạp |
| `saha-theme-child` | tuỳ biến riêng cho từng khách hàng | — |

### 1.2. Luồng render một request frontend

```
Request /san-pham/keo-khoa-ren-loctite-243/
  │
  ├─ WordPress template hierarchy → saha-theme/woocommerce.php
  │
  ├─ saha-core TemplateResolver
  │     tìm saha_template type=header  khớp điều kiện → Header
  │     tìm saha_template type=single_product khớp điều kiện (ID > category > all) → Body
  │     tìm saha_template type=footer  khớp điều kiện → Footer
  │     (không có template nào → dùng template PHP mặc định của saha-theme)
  │
  ├─ Builder Renderer: JSON → cây Node → HTML
  │     element tĩnh  → render cache (object cache / transient)
  │     element động  → render mỗi request (sản phẩm, giỏ hàng, giá)
  │     block ref     → render block mới nhất (sửa block là mọi nơi cập nhật)
  │
  └─ Asset: global.css (theme options) + post-{id}.css (style của layout)
            + JS chỉ của element có trên trang (slider, gallery, tabs…)
```

### 1.3. Luồng soạn thảo

```
Admin mở SAHA → Builder → Trang X
  │
  saha-builder (React, chỉ nạp ở màn hình builder)
  ├─ GET  /saha/v1/builder/elements   ← định nghĩa element + controls (nguồn duy nhất từ PHP)
  ├─ GET  /saha/v1/builder/{id}       ← JSON layout + khoá chỉnh sửa
  ├─ Canvas (iframe nạp CSS frontend thật)
  │     element tĩnh  → component React vẽ ngay (không gọi server)
  │     element động  → POST /builder/render (HTML từ PHP renderer, có cache)
  ├─ Undo/redo 50+ bước, copy/paste, duplicate, navigator (client)
  └─ POST /saha/v1/builder/save       → validate + sanitize theo controls → lưu meta
                                         → sinh lại CSS → xoá render cache → revision
```

**Nguyên tắc quan trọng nhất:** định nghĩa element (loại control, giá trị mặc định, ràng buộc cha–con) chỉ viết **một lần ở PHP**. Editor đọc qua REST và tự dựng panel setting. Server sanitize khi lưu theo đúng định nghĩa đó → client gửi gì cũng không vượt được schema.

---

## 2. Sơ đồ hệ thống

### 2.1. Component diagram

```
┌──────────────────────────────── WordPress ────────────────────────────────┐
│                                                                           │
│  ┌──────────── saha-builder (admin only) ────────────┐                    │
│  │ Builder App │ Header/Footer App │ Template Conditions │ Theme Options App│
│  │   └──────── @wordpress/components, data, api-fetch, i18n, dnd-kit ─────┘ │
│  └──────────────────────────┬────────────────────────┘                    │
│                             │ REST (cookie + wp_rest nonce + capability)   │
│  ┌────────────────────────── saha-core ─────────────────────────────────┐ │
│  │ API ─┬─ Builder: Schema · ElementRegistry · Sanitizer · Renderer ·    │ │
│  │      │           CssGenerator · RenderCache · SchemaMigrator           │ │
│  │      ├─ Templates: saha_template CPT · Conditions · Resolver · Map     │ │
│  │      ├─ Blocks: saha_block CPT · BlockRef                              │ │
│  │      ├─ MegaMenu: menu item meta · Walker data                         │ │
│  │      ├─ ThemeOptions: Schema · Repository · CssVariables               │ │
│  │      ├─ WooCommerce: ProductCard · QuickView · BuyNow · Swatches ·     │ │
│  │      │               StickyCart · CatalogMode · (B2B Pricing)          │ │
│  │      ├─ Hiện có: Search · Filter · Catalog · Brand · Quote · Lead ·    │ │
│  │      │           CRM · SEO · Cache · Security · Logger · Migrator      │ │
│  │      ├─ ImportExport · Demo (Phase 3) · Webhooks/Events (Phase 4)      │ │
│  │      └─ System: health · versions · cron                               │ │
│  └────────────────────────────┬─────────────────────────────────────────┘ │
│                               │ PHP API (service) + hooks                 │
│  ┌────────────────────────── saha-theme ─────────────────────────────────┐ │
│  │ header.php/footer.php → gọi TemplateResolver; fallback PHP mặc định    │ │
│  │ template-parts/components: product-card, blog-card, mini-cart-drawer…  │ │
│  │ woocommerce/: override tối thiểu · assets: global + conditional       │ │
│  └────────────────────────────┬─────────────────────────────────────────┘ │
│                               │                                           │
│  ┌──────────────── WooCommerce (Store API, hooks, template) ─────────────┐ │
└──┴───────────────────────────────────────────────────────────────────────┴─┘
```

### 2.2. Phụ thuộc

```
saha-builder ──requires──▶ saha-core ◀──uses (function_exists)── saha-theme
                               │
                               └──requires──▶ WooCommerce (module WooCommerce tự tắt nếu thiếu)
```

- `saha-theme` **không fatal** khi thiếu `saha-core`: hiển thị template PHP mặc định (như `flatsome-child` hiện nay).
- `saha-builder` báo lỗi admin và tự tắt nếu thiếu `saha-core` hoặc sai phiên bản API builder.

---

## 3. Chia module

20 module của spec §7, ánh xạ vào plugin và phase:

| # | Module | Nơi | Service / Controller / Repository | REST | Admin UI | Phase |
|---|---|---|---|---|---|---|
| 01 | Core | core | `Core\Plugin`, `Core\ModuleRegistry`, `Core\Container` | `/system` | SAHA → Dashboard, System | 1 |
| 02 | Theme Options | core + builder | `ThemeOptions\Schema`, `Repository`, `CssVariables` | `/settings` | Appearance → SAHA Theme Options | 1 |
| 03 | Builder | core (runtime) + builder (app) | `Builder\Schema`, `ElementRegistry`, `Sanitizer`, `Renderer`, `CssGenerator`, `RenderCache`, `SchemaMigrator`, `LayoutRepository` | `/builder/*` | SAHA → Builder | 1 |
| 04 | Component Library | core + builder | `Builder\Elements\*` (PHP render) + `elements/*` (React edit) | `/builder/elements` | trong builder | 1 (cơ bản), 2–3 (mở rộng) |
| 05 | Reusable Blocks | core | `Blocks\PostType`, `Blocks\Repository` | `/blocks` | SAHA → Blocks | 1 |
| 06 | Header Builder | core + builder | `Templates\*` (type header), `Header\Elements\*` | `/templates` | SAHA → Header | 1 |
| 07 | Footer Builder | core + builder | như Header (type footer) | `/templates` | SAHA → Footer | 1 |
| 08 | Mega Menu | core | `MegaMenu\ItemSettings`, `MegaMenu\Walker` (theme) | — | Appearance → Menus | 2 |
| 09 | Product Catalog | core + theme | `WooCommerce\Shop`, `ProductCard`, **`Catalog` (hiện có)** | `/products` (hiện có) | Theme Options → Shop | 1 |
| 10 | Product Detail | core + theme | `WooCommerce\Product\Gallery`, `BuyNow`, `StickyCart`, `Swatches` | `/products/{id}/quick-view` | Theme Options → Product | 1 (layout, gallery, buy now), 2 (swatches, sticky) |
| 11 | Product Filter | core | **`Filter` (hiện có)** mở rộng giá/rating/stock/attribute | `/products?…` | element Product Filter | 2 |
| 12 | Product Search | core | **`Search` (hiện có)**, thêm giá vào kết quả | `/search` (hiện có) | element Product Search | 2 |
| 13 | Cart | theme | mini cart drawer (Store API) + style block Cart | Store API | Theme Options → Cart | 1 (link/đếm), 2 (drawer) |
| 14 | Checkout | theme | style block Checkout + template classic tối thiểu | — | Theme Options → Checkout | 1 |
| 15 | Account | theme | style My Account endpoints | — | — | 1 |
| 16 | Blog | theme + core | `Blog\PostCard`, layout grid/list/masonry | — | Theme Options → Blog | 1 (grid/list), 2 (masonry) |
| 17 | Template Builder | core + builder | `Templates\PostType`, `Conditions`, `Resolver`, `TemplateMap` | `/templates` | SAHA → Templates | 2 |
| 18 | WooCommerce Extension | core | `WooCommerce\CatalogMode`, **`Quote` (hiện có)** → quote list, B2B pricing | `/quote/*` | SAHA → WooCommerce | 1 (catalog mode), 3 (B2B) |
| 19 | Performance | core + theme | `Performance\AssetManager`, `CssFileStore`, **`Cache` (hiện có)** | — | SAHA → Performance | 1 |
| 20 | SEO Compatibility | core | **`Seo` (hiện có)** | — | — | có sẵn |

Thêm ngoài danh sách 20: **Portfolio** (`saha_portfolio`, spec §51, Phase 3), **Import/Export** (spec §83, Phase 2), **Demo Import** (spec §84, Phase 3), **Events & Webhooks** (spec §104–105, Phase 4).

Mỗi module mới có đủ các lớp theo spec §7 khi cần: `Service` (logic) · `Controller` (REST) · `Repository` (lưu trữ) · `Admin` (màn hình) · `Renderer` (frontend) · `hooks.php` (đăng ký) · `config` (mặc định).

---

## 4. Cấu trúc source code

```
wp-content/
├─ plugins/
│  ├─ saha-core/                                  (đã có — mở rộng)
│  │  ├─ saha-core.php                            bootstrap (thêm: PSR-4 autoload, ModuleRegistry)
│  │  ├─ includes/
│  │  │  ├─ class-*.php                           CODE HIỆN CÓ — giữ nguyên
│  │  │  ├─ Core/          Plugin.php, ModuleRegistry.php, Container.php, Version.php
│  │  │  ├─ API/           RestController.php (base), Response.php
│  │  │  ├─ Builder/
│  │  │  │  ├─ Schema/     Document.php, Node.php, SchemaMigrator.php, Limits.php
│  │  │  │  ├─ Controls/   Control.php, Text.php, Number.php, Select.php, Color.php, Media.php,
│  │  │  │  │              Spacing.php, Typography.php, Background.php, Border.php, Link.php, Html.php …
│  │  │  │  ├─ Elements/   Element.php (abstract), Section.php, Container.php, Row.php, Column.php,
│  │  │  │  │              Heading.php, Text.php, Button.php, Image.php, Spacer.php, Divider.php,
│  │  │  │  │              Icon.php, Html.php, Shortcode.php, Banner.php, BlockRef.php …
│  │  │  │  ├─ ElementRegistry.php, Sanitizer.php, Renderer.php, RenderContext.php,
│  │  │  │  ├─ CssGenerator.php, RenderCache.php, LayoutRepository.php
│  │  │  │  └─ Rest/       BuilderController.php
│  │  │  ├─ Blocks/        PostType.php, Repository.php, Rest/BlocksController.php
│  │  │  ├─ Templates/     PostType.php, Conditions/ (Rule*.php), Resolver.php, TemplateMap.php,
│  │  │  │                 Rest/TemplatesController.php
│  │  │  ├─ Header/        Elements/ (Logo, Menu, Search, Account, Cart, Social…)
│  │  │  ├─ Footer/        Elements/ (Newsletter, Contact…)
│  │  │  ├─ MegaMenu/      ItemSettings.php
│  │  │  ├─ ThemeOptions/  Schema.php, Repository.php, CssVariables.php, Rest/SettingsController.php
│  │  │  ├─ WooCommerce/   Shop.php, CatalogMode.php,
│  │  │  │                 Product/ (ProductCard.php, Gallery.php, QuickView.php, BuyNow.php,
│  │  │  │                           StickyCart.php, VariationSwatches.php)
│  │  │  ├─ Performance/   AssetManager.php, CssFileStore.php
│  │  │  ├─ ImportExport/  Exporter.php, Importer.php, MediaResolver.php
│  │  │  ├─ System/        Health.php, Rest/SystemController.php
│  │  │  └─ tables/        (hiện có — không đặt module PSR-4 tên "Tables", xem rủi ro R9)
│  │  ├─ database/migrations/   001…005 hiện có; 006+ cho SCC
│  │  ├─ api/routes/            route hiện có (giữ)
│  │  ├─ templates/emails/
│  │  └─ admin/
│  │
│  └─ saha-builder/                               (MỚI — chỉ admin)
│     ├─ saha-builder.php                         bootstrap, kiểm tra saha-core + API version
│     ├─ includes/        Admin/Screens.php (đăng ký trang, nạp app), Assets.php
│     ├─ src/                                     React (không deploy)
│     │  ├─ builder/      App.jsx, store/ (Data API), canvas/, panels/, navigator/, history/
│     │  ├─ elements/     edit component cho từng element (heading/edit.jsx …)
│     │  ├─ controls/     control UI (SpacingControl, ResponsiveControl, TypographyControl…)
│     │  ├─ header-footer/ app header/footer builder
│     │  ├─ theme-options/ app theme options
│     │  └─ shared/       api.js, schema.js, i18n.js
│     ├─ build/                                   ĐÃ BIÊN DỊCH — commit (D9)
│     ├─ package.json, webpack.config.js (mở rộng @wordpress/scripts)
│     └─ languages/
│
└─ themes/
   ├─ saha-theme/                                 (MỚI)
   │  ├─ style.css, functions.php (chỉ nạp inc/), theme.json
   │  ├─ index.php, header.php, footer.php, front-page.php, page.php, single.php,
   │  │  archive.php, search.php, 404.php, woocommerce.php
   │  ├─ inc/            setup.php, assets.php, hooks.php, helpers.php, template-functions.php,
   │  │                  woocommerce.php, performance.php, seo.php
   │  ├─ template-parts/ header/, footer/, blog/, product/, components/
   │  ├─ woocommerce/    override tối thiểu (xem §10)
   │  ├─ src/scss/, src/js/  → assets/css, assets/js (build)
   │  └─ languages/
   │
   ├─ saha-theme-child/                           (MỚI — starter cho khách hàng)
   └─ flatsome-child/                             (ĐÓNG BĂNG — D4)
```

---

## 5. Thiết kế database

Spec §62: ưu tiên `wp_posts`, `wp_postmeta`, `wp_options`. Bảng riêng chỉ khi dữ liệu có quan hệ nhiều dòng cần truy vấn/báo cáo.

### 5.1. Post type

| Post type | Dùng cho | Public | Ghi chú |
|---|---|---|---|
| `saha_block` | block tái sử dụng: USP, CTA, promotion, footer column, nội dung mega menu | không | `show_in_rest` cho builder; không có URL công khai |
| `saha_template` | header, footer, template trang/sản phẩm/danh mục/bài/archive/search/404 | không | loại lưu trong meta `_saha_template_type` |
| `saha_portfolio` | portfolio (Phase 3) | có | taxonomy `saha_portfolio_cat` |

Header và footer dùng chung `saha_template` (type `header` / `footer`) vì cùng cơ chế điều kiện hiển thị — ví dụ header riêng cho trang landing. Admin vẫn có menu **Header** và **Footer** riêng (lọc theo type).

### 5.2. Post meta

| Key | Trên | Kiểu | Ghi chú |
|---|---|---|---|
| `_saha_builder_enabled` | page, post, product, `saha_block`, `saha_template` | `'1'` | spec §63 |
| `_saha_builder_data` | như trên | JSON (longtext) | nguồn chính — HTML luôn render từ đây (spec §61). Đăng ký `register_post_meta(… 'revisions_enabled' => true)` (WP ≥ 6.4) để có lịch sử phiên bản |
| `_saha_builder_version` | như trên | int | phiên bản **schema** của JSON, dùng cho `SchemaMigrator` |
| `_saha_builder_hash` | như trên | string | hash nội dung — khoá render cache, tên file CSS, chống ghi đè khi 2 người cùng sửa |
| `_saha_css_file` | như trên | string | đường dẫn tương đối file CSS đã sinh |
| `_saha_template_type` | `saha_template` | enum | `header, footer, page, single_product, product_archive, single_post, archive, search, 404` |
| `_saha_template_conditions` | `saha_template` | JSON | xem 5.5 |
| `_saha_template_priority` | `saha_template` | int | phân xử khi cùng mức cụ thể |
| `_saha_header_settings` | `saha_template` (header) | JSON | sticky, chiều cao, trong suốt… |
| `_saha_menu_type` | `nav_menu_item` | enum | `normal, dropdown, mega` |
| `_saha_mega_settings` | `nav_menu_item` | JSON | `width: container|full|px`, `columns`, `blockId` |
| `_saha_price_tiers` | product/variation | JSON | Phase 3 — `{dealer, distributor, vip}` |

Meta sản phẩm hiện có (`_saha_specs`, `_saha_docs`, `_saha_availability`…) giữ nguyên.

### 5.3. Term meta

| Key | Trên | Ghi chú |
|---|---|---|
| `saha_swatch_color` | term của attribute `pa_*` | mã màu |
| `saha_swatch_image_id` | term của attribute `pa_*` | attachment ID |
| (hiện có) `saha_brand_*` | `product_brand` | giữ nguyên |

Kiểu swatch của từng attribute (`label | color | image`) lưu trong `saha_woocommerce_settings['swatches']` vì bảng attribute của WooCommerce không có meta.

### 5.4. Option (spec §64 — ít option, dạng mảng)

| Option | Autoload | Nội dung |
|---|---|---|
| `saha_theme_options` | có | toàn bộ Theme Options (§9) |
| `saha_builder_settings` | không | breakpoints, element bật/tắt, giới hạn, render cache |
| `saha_woocommerce_settings` | có | shop/product/cart/checkout/swatches/quote list |
| `saha_template_map` | có | chỉ mục điều kiện template đã biên dịch (5.5) |
| `saha_core_settings` | có | **hiện có** — dữ liệu kinh doanh: hotline, Zalo, email, chế độ catalogue |
| `saha_core_db_version`, `saha_cache_gen` … | | hiện có |

`catalogue_mode` **chỉ lưu một chỗ** (`saha_core_settings`). Theme Options hiển thị cùng toggle nhưng đọc/ghi đúng key đó — không có hai nguồn sự thật.

### 5.5. Điều kiện template + chỉ mục

```json
{
  "include": [
    { "rule": "product_cat", "value": [12, 15] },
    { "rule": "product",     "value": [123] }
  ],
  "exclude": [
    { "rule": "product",     "value": [456] }
  ]
}
```

Rule: `all`, `front_page`, `page`, `post`, `category`, `post_tag`, `product`, `product_cat`, `product_brand`, `archive_type`, `search`, `404`, `user_role` (Phase 3).

Độ cụ thể (spec §54): `object ID` (30) > `term` (20) > `archive type` (10) > `all` (0). Cùng mức → `_saha_template_priority` → ID nhỏ hơn.

`TemplateMap` biên dịch mọi template đã xuất bản thành chỉ mục `type → rule → value → [templateId…]` và lưu vào `saha_template_map` khi một template được lưu/xoá. Mỗi request chỉ tra mảng trong bộ nhớ, **không query danh sách template**.

### 5.6. Bảng riêng

| Bảng | Trạng thái | Lý do |
|---|---|---|
| `wp_saha_quotes`, `wp_saha_leads`, `wp_saha_logs`, `wp_saha_search_logs` | hiện có | — |
| `wp_saha_quote_items` | **mới — Phase 2** (migration 006) | spec §102: một yêu cầu báo giá gồm **nhiều** sản phẩm (hiện là 1 sản phẩm/yêu cầu); cần truy vấn "sản phẩm nào được hỏi nhiều" → quan hệ 1-n, không nhét JSON |
| `wp_saha_webhook_deliveries` | Phase 4 | nhật ký gửi webhook + thử lại; tăng nhanh, cần dọn định kỳ |

Cột `wp_saha_quote_items`: `id, quote_id, product_id, variation_id, sku, product_name, quantity, note, created_at` — index `quote_id`, `product_id`. Mọi tên bảng qua `$wpdb->prefix` (spec §88, multisite).

### 5.7. File sinh ra

```
wp-content/uploads/saha/
  css/global-{hash}.css          từ Theme Options
  css/post-{id}-{hash}.css       từ layout builder
  export/                        file export tạm (xoá sau khi tải)
```

Đường dẫn qua `wp_upload_dir()` → đúng thư mục riêng từng site trên multisite. Không ghi được → fallback `<style>` inline (có giới hạn kích thước) + cảnh báo trong System.

---

## 6. Builder JSON Schema

### 6.1. Tài liệu

```json
{
  "version": 1,
  "elements": [
    {
      "id": "k3f9a2c1",
      "type": "section",
      "props": {
        "width": "container",
        "minHeight": { "desktop": "480px", "mobile": "320px" },
        "background": {
          "color": "var(--saha-background)",
          "image": { "id": 321, "size": "full" },
          "position": "center center",
          "size": "cover",
          "overlay": { "color": "#000000", "opacity": 0.4 }
        },
        "spacing": {
          "padding": {
            "desktop": { "top": "64px", "right": "0", "bottom": "64px", "left": "0" },
            "mobile":  { "top": "32px", "right": "0", "bottom": "32px", "left": "0" }
          }
        }
      },
      "advanced": {
        "cssId": "",
        "cssClass": "",
        "visibility": { "hideDesktop": false, "hideTablet": false, "hideMobile": false }
      },
      "children": [
        {
          "id": "p0w8e7r2",
          "type": "row",
          "props": { "columns": 2, "gap": { "desktop": "32px", "mobile": "16px" }, "wrap": true },
          "children": [
            {
              "id": "c1a1b2c3",
              "type": "column",
              "props": { "width": { "desktop": "50%", "tablet": "50%", "mobile": "100%" } },
              "children": [
                {
                  "id": "h7t6y5u4",
                  "type": "heading",
                  "props": {
                    "text": "Tổng kho keo dán chính hãng",
                    "tag": "h1",
                    "typography": { "fontSize": { "desktop": "40px", "tablet": "32px", "mobile": "26px" }, "fontWeight": "700" },
                    "color": "var(--saha-heading)",
                    "align": { "desktop": "left", "mobile": "center" }
                  }
                },
                {
                  "id": "b9n8m7l6",
                  "type": "block",
                  "props": { "blockId": 45 }
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

### 6.2. Quy tắc

| Quy tắc | Chi tiết |
|---|---|
| `version` | phiên bản schema của tài liệu. Đổi cấu trúc → tăng version + viết bước migrate trong `SchemaMigrator` (PHP) và `migrate.js` (editor). Không bao giờ sửa dữ liệu cũ tại chỗ mà không có migration (spec §91) |
| `id` | 8 ký tự `[a-z0-9]`, duy nhất trong tài liệu; dùng làm class CSS `.saha-e-{id}` |
| `type` | phải có trong `ElementRegistry`. Type lạ (ví dụ element của plugin mở rộng đã tắt) được **giữ nguyên khi lưu** nhưng không render — không mất dữ liệu |
| Giá trị responsive | mọi prop khai báo `responsive: true` nhận **scalar** hoặc `{desktop, tablet, mobile}`; thiếu breakpoint thì kế thừa desktop → tablet → mobile (spec §55) |
| Breakpoint | desktop > 1024 · tablet 768–1024 · mobile < 768 (spec §55), cấu hình trong `saha_builder_settings` |
| Media | luôn lưu **attachment ID** (+ size), không lưu URL tuyệt đối — không vỡ khi đổi domain (spec §80) |
| Màu | giá trị hex/rgb **hoặc** biến `var(--saha-*)` từ Theme Options → đổi màu toàn cục là toàn site đổi |
| Tham chiếu | `block` → `blockId`; render nội dung mới nhất của block (spec §20) |
| Lồng nhau | mỗi element khai báo `allowedParents` / `allowedChildren` (ví dụ `column` chỉ nằm trong `row`) — cả editor lẫn `Sanitizer` đều kiểm |
| Giới hạn | độ sâu ≤ 12, ≤ 2.000 element, JSON ≤ 1 MB, block ref lồng ≤ 3 cấp và cấm vòng lặp A→B→A |
| Dự phòng Phase 3 | `"$dynamic": "product.price"` cho dynamic data, `"animation": {...}` trong `advanced` — đã chừa chỗ, chưa triển khai |

### 6.3. Định nghĩa element (PHP — nguồn duy nhất)

```php
// saha-core/includes/Builder/Elements/Heading.php (minh hoạ cấu trúc, chưa phải code triển khai)
final class Heading extends Element {
    public function definition(): array {
        return [
            'type'           => 'heading',
            'name'           => __( 'Heading', 'saha-core' ),
            'icon'           => 'heading',
            'category'       => 'content',
            'allowedParents' => [ 'column', 'container', 'banner' ],
            'controls'       => [
                'text'       => [ 'type' => 'text',       'default' => 'Heading' ],
                'tag'        => [ 'type' => 'select',     'default' => 'h2', 'options' => [ 'h1','h2','h3','h4','h5','h6' ] ],
                'typography' => [ 'type' => 'typography', 'responsive' => true ],
                'color'      => [ 'type' => 'color' ],
                'align'      => [ 'type' => 'align',      'responsive' => true ],
                'spacing'    => [ 'type' => 'spacing',    'responsive' => true ],
            ],
            'assets'         => [], // script/style chỉ nạp khi element có trên trang
        ];
    }
    public function render( Node $node, RenderContext $ctx ): string { /* escape toàn bộ */ }
    public function styles( Node $node ): CssRules { /* → .saha-e-{id} */ }
}
```

Editor (React) chỉ đăng ký **component vẽ trên canvas**; tên, icon, control, mặc định lấy từ `GET /builder/elements`:

```js
registerElementEdit( 'heading', HeadingEdit );
```

Mỗi **loại control** có sanitizer riêng ở PHP (`Controls/Color.php` chỉ nhận hex/rgb/`var(--saha-*)`; `Controls/Html.php` yêu cầu `unfiltered_html`…). Plugin khác thêm element qua filter `saha_builder_elements`.

### 6.4. Danh sách element theo phase

| Nhóm | Phase 1 (MVP) | Phase 2 | Phase 3 |
|---|---|---|---|
| Layout | Section, Container, Row, Column, Spacer, Divider | Grid, Stack, Tabs, Accordion | — |
| Content | Heading, Text (rich text), Button, Image, Icon, Icon Box, HTML, Shortcode | Gallery, Video | — |
| Marketing | Banner, CTA, **Block** | Slider, Logo, Logo Slider, Testimonial, Countdown | Team |
| WooCommerce | Products / Product Grid (featured, sale, best selling, recent, category, brand), Product Categories | Product Slider, Featured Product, Product Search, Product Filter | Custom Query |
| Blog | Posts / Post Grid, Recent Posts | Post Slider, Categories | — |
| Header/Footer | Logo, Menu, Search, Account, Cart, HTML, Button, Social, Divider, Icon, Text, Contact, Block | Newsletter, Language, Wishlist (nếu có plugin) | — |

Element WooCommerce Phase 1 **tái dùng service `Catalog` hiện có** (đã có cache theo thế hệ, prime cache chống N+1).

---

## 7. REST API

Namespace `saha/v1` (dùng chung với route hiện có). Envelope (D10):

```json
{ "success": true,  "message": "", "data": {} }
{ "success": false, "code": "invalid_request", "message": "…", "errors": { "field": "…" } }
```

| Method | Route | Quyền | Mô tả | Phase |
|---|---|---|---|---|
| GET | `/products`, `/products/{id}` | public + rate limit | **hiện có** | — |
| GET | `/products/{id}/quick-view` | public + rate limit | HTML quick view (đã render, có cache) | 2 |
| GET | `/search` | public + rate limit | **hiện có**; thêm `price_html` | 2 |
| GET | `/brands`, `/brands/{slug}` | public | **hiện có** | — |
| POST | `/quote`, `/contact` | nonce + rate limit + honeypot | **hiện có** | — |
| POST | `/quote/list` | nonce + rate limit | gửi danh sách báo giá nhiều sản phẩm | 2 |
| GET | `/nonce` | public | **hiện có** | — |
| GET | `/builder/elements` | `edit_saha_builder` | định nghĩa element + control | 1 |
| GET | `/builder/{id}` | `edit_saha_builder` + `edit_post` | layout JSON, hash, khoá chỉnh sửa | 1 |
| POST | `/builder/save` | `edit_saha_builder` + `edit_post` | `{postId, data, baseHash}` → validate, sanitize, lưu, sinh CSS; `409` nếu `baseHash` lệch (người khác vừa lưu) | 1 |
| POST | `/builder/render` | `edit_saha_builder` | render HTML một node cho canvas (element động) | 1 |
| POST | `/builder/lock/{id}` | `edit_saha_builder` | giữ khoá chỉnh sửa (dùng cơ chế post lock của WP) | 1 |
| GET | `/blocks`, `/blocks/{id}` | `edit_saha_builder` | danh sách + chi tiết block | 1 |
| GET | `/templates` | `edit_saha_builder` | lọc theo `type` | 1 |
| POST | `/templates/{id}/conditions` | `manage_saha_templates` | lưu điều kiện, biên dịch lại `TemplateMap` | 2 |
| GET, POST | `/settings` | `edit_theme_options` | Theme Options (schema + giá trị) | 1 |
| GET | `/system` | `manage_saha_settings` | phiên bản, sức khoẻ hệ thống (spec §82) | 1 |
| GET | `/export` · POST `/import` | `manage_options` | JSON (spec §83) | 2 |
| GET, POST | `/webhooks` | `manage_options` | đăng ký webhook | 4 |

Mọi route **ghi**: cookie đăng nhập + header `X-WP-Nonce` (`wp_rest`) + `current_user_can()` + sanitize/validate theo schema (spec §66). Mọi route có `permission_callback` (đã có kiểm tra tự động trong `wp saha qa`).

Bài học từ QA Phase 8 áp dụng ngay cho route mới: không truyền thẳng hàm WordPress nhiều tham số làm `sanitize_callback`; rate limit nhớ kết quả trong request.

---

## 8. Hooks / Filters

### 8.1. Action

| Hook | Nơi bắn | Dùng để |
|---|---|---|
| `saha_before_header` / `saha_after_header` | `header.php` | chèn thanh thông báo, tracking |
| `saha_before_content` / `saha_after_content` | wrapper nội dung | breadcrumb, banner trang |
| `saha_before_footer` / `saha_after_footer` | `footer.php` | CTA toàn site, script |
| `saha_before_product` / `saha_after_product` | single product | khối bổ sung |
| `saha_builder_render_before` / `_after` | Renderer | `(Node, RenderContext)` |
| `saha_builder_saved` | sau khi lưu layout | `(postId, Document)` — dọn cache, purge CDN |
| `saha_template_saved` | sau khi lưu template | biên dịch lại `TemplateMap` |
| `saha_theme_options_saved` | sau khi lưu Theme Options | sinh lại `global.css` |
| `saha_event` | Phase 4 | `(name, payload)` — bus sự kiện cho webhook |
| (hiện có) `saha_quote_created`, `saha_lead_created`, `saha_cache_bumped`… | | giữ nguyên |

### 8.2. Filter

| Filter | Tham số | Dùng để |
|---|---|---|
| `saha_builder_elements` | `ElementRegistry` | thêm/bớt element (spec §78) |
| `saha_builder_element_definition` | `array, type` | sửa định nghĩa element |
| `saha_builder_render_element` | `html, Node, ctx` | sửa HTML một element |
| `saha_builder_node_classes` | `string[], Node` | thêm class |
| `saha_header_classes` / `saha_footer_classes` | `string[]` | spec §78 |
| `saha_theme_options` | `array` | spec §78 — đọc option |
| `saha_theme_options_schema` | `array` | thêm group/field |
| `saha_css_variables` | `array` | thêm biến CSS toàn cục |
| `saha_template_rules` | `Rule[]` | thêm loại điều kiện |
| `saha_template_resolved` | `?int templateId, type` | ép dùng template khác |
| `saha_product_card_data` | `array, productId` | **đã có** (spec §78) — dùng chung cho product card mới |
| `saha_product_card_parts` | `string[]` | bật/tắt/sắp xếp phần của card |
| `saha_quick_view_enabled` / `saha_buy_now_enabled` | `bool, WC_Product` | tắt theo sản phẩm |
| `saha_product_price_tier` | `?string tier, WP_User` | Phase 3 — B2B |
| `saha_asset_should_load` | `bool, handle` | điều khiển conditional asset |

Quy ước: action/filter mới đều tiền tố `saha_`, tham số tài liệu hoá trong `docs/scc/hooks.md`.

---

## 9. Theme Options

Admin: **Appearance → SAHA Theme Options** (spec §27) và lối tắt ở **SAHA → Theme Options**. Ứng dụng React (WordPress Components), lưu qua `POST /settings`, một option `saha_theme_options`.

Schema khai báo ở PHP (`ThemeOptions\Schema`) → REST trả schema → UI tự dựng; sanitize theo schema khi lưu (giống builder).

| Nhóm | Field chính (key) | Sinh ra |
|---|---|---|
| General | `logo_id`, `logo_mobile_id`, `favicon_id` (dùng site icon WP), `back_to_top` | — |
| Layout | `site_width` (1200/1280/1440/custom), `container_width`, `content_width`, `sidebar_width`, `boxed` | `--saha-container`, `--saha-content`, `--saha-sidebar` |
| Colors (spec §28) | `primary`, `secondary`, `accent`, `success`, `warning`, `error`, `text`, `heading`, `border`, `background` | `--saha-primary` … `--saha-background` |
| Typography (spec §29) | `body`, `heading`, `menu`, `button` — mỗi cái `{fontFamily, fontSize(responsive), fontWeight, lineHeight, letterSpacing}`; `font_source`: `system` / `self-hosted` | `--saha-font-body` …; `@font-face` tự host, không gọi Google Fonts runtime (hiệu năng + riêng tư) |
| Header | `header_template_id` (mặc định), `sticky: {enabled, mode: always|scrollUp, height, background}` (spec §23), `mobile_breakpoint` | class trên `<header>` |
| Footer | `footer_template_id` | — |
| Blog | `layout: grid|list|masonry`, `columns`, card parts (image/category/title/excerpt/date/author) | — |
| Shop | `products_per_page`, `columns: {desktop, tablet, mobile}`, `toolbar` (result count/sorting/view switcher/filter button), `sidebar: left|right|off-canvas|none` | — |
| Product | `gallery: slider|vertical|grid|stacked`, `zoom`, `lightbox`, `sticky_add_to_cart`, `buy_now`, `share`, `card: {parts, image_hover: none|zoom|second|fade, quick_view}` | — |
| Cart | `mini_cart: link|drawer`, `free_shipping_bar` (Phase 3) | — |
| Checkout | `layout: two_column|single` | — |
| Catalog mode | **đọc/ghi `saha_core_settings.catalogue_mode`** (D-5.4) + `hide_cart`, `hide_checkout`, `cta: contact|quote` | — |
| Performance | `lazy_load`, `defer_js`, `disable_emoji`, `preload_hero` (tính năng Phase 7 hiện có), `css_file_mode: file|inline` | — |
| Custom CSS | `custom_css` — cần `edit_css`; lọc `</style>` và `<` | nối cuối `global.css` |

Export/Import Theme Options dùng chung module Import/Export (Phase 2).

---

## 10. WooCommerce integration

Nguyên tắc: **dùng hook và API của WooCommerce; override template ít nhất có thể** (mỗi file override phải được theo dõi khi WooCommerce đổi phiên bản template).

| Chức năng | Cách làm | Override template? |
|---|---|---|
| Shop / category layout (§31, §40) | hook `woocommerce_before_shop_loop` (toolbar, filter), `woocommerce_archive_description`, `loop_shop_columns`, `loop_shop_per_page`; subcategory qua `woocommerce_product_subcategories` | `archive-product.php` — 1 file, để bọc layout sidebar/off-canvas |
| Product card (§32–33) | `content-product.php` gọi `ProductCard::render()`; phần card bật/tắt qua option; dữ liệu qua `saha_product_card_data` (đã có) | `content-product.php` |
| Quick view (§34) | nút trong card → `GET /products/{id}/quick-view` → modal `<dialog>` (đã dùng cho quote modal); add to cart qua **Store API**; biến thể dùng lại `wc-add-to-cart-variation` | không |
| Product page (§35) | hook `woocommerce_single_product_summary` (đã dùng ở Phase 2); template builder (Phase 2) có thể thay toàn bộ | không (Phase 1) |
| Gallery (§36) | `add_theme_support( 'wc-product-gallery-zoom' / '-lightbox' / '-slider' )` — WooCommerce có sẵn zoom (jQuery Zoom), lightbox (PhotoSwipe), slider (FlexSlider). Bố cục vertical/grid/stacked bằng CSS + filter `woocommerce_single_product_carousel_options`; video qua meta attachment | không |
| Swatches (§37) | filter `woocommerce_dropdown_variation_attribute_options_html`: **giữ `<select>` gốc (ẩn)** và vẽ swatch đồng bộ với nó → script biến thể của WooCommerce vẫn chạy nguyên | không |
| Sticky add to cart (§38) | `IntersectionObserver` trên form gốc; thanh sticky **điều khiển form gốc** (không nhân bản logic giỏ hàng) | không |
| Buy now (§39) | nút submit cùng form, name `saha_buy_now`; filter `woocommerce_add_to_cart_redirect` → `wc_get_checkout_url()` khi có cờ; hoạt động với biến thể vì đi qua đúng luồng add-to-cart | không |
| Filter (§41) | mở rộng **`Filter` hiện có** (đã chạy server-side, giữ state URL, noindex URL lọc): thêm giá, rating, stock, attribute | không |
| Live search (§42) | **`Search` hiện có** (relevance đã QA) + debounce 300 ms (đã có) + thêm giá | không |
| Toolbar (§43) | `woocommerce_result_count`, `woocommerce_catalog_ordering`, view switcher (grid/list, lưu `localStorage`) | không |
| Mini cart drawer (§44) | Store API `GET/POST /wc/store/v1/cart/*`, header `Nonce` (Store API nonce); fallback link tới `/cart` khi JS tắt | không |
| Cart / Checkout (§45–46) | WooCommerce 11 mặc định dùng **Cart/Checkout block** → style qua `theme.json` + CSS; layout 2 cột/1 cột bằng CSS. Site dùng shortcode classic vẫn được hỗ trợ bằng CSS | không |
| My Account (§47) | style endpoint gốc | không |
| Catalog mode (§48) | **đã có** (ẩn giá, ẩn add-to-cart, bỏ cart fragments); thêm ẩn trang cart/checkout (redirect về trang báo giá) | không |
| Quote list (§102) | mở rộng **`Quote` hiện có**: danh sách báo giá phía khách (localStorage + gửi một lần) → `wp_saha_quote_items` | không |
| B2B price (§103) | Phase 3: meta `_saha_price_tiers` + ánh xạ role → tier; filter `woocommerce_product_get_price` / `…_variation_get_price` **chỉ khi user có tier**; không động vào giá lưu trong DB, không phá cache giá của WooCommerce cho khách lẻ | không |
| Schema (§73) | để WooCommerce/Rank Math quản lý — **đã có** cơ chế một nguồn (Phase 6) | — |

Loại sản phẩm cần test (spec §86): simple, variable, grouped, external, virtual, downloadable.

---

## 11. Chia phase phát triển

Ước lượng cho **1 lập trình viên senior toàn thời gian**, chưa gồm thời gian chờ duyệt/phản hồi. Đây là ước lượng thô để lập kế hoạch, không phải cam kết.

| Phase | Nội dung | Ước lượng |
|---|---|---|
| **1 — MVP** | xem §12 | 12–16 tuần |
| **2** | Mega menu, Template builder + điều kiện, Product filter UI, Live search UI, Quick view, Swatches, Sticky add to cart, mini cart drawer, quote list nhiều sản phẩm, Import/Export, element Phase 2 | 8–12 tuần |
| **3** | Demo import (starter sites), builder nâng cao (animation, dynamic data, custom query), Portfolio, B2B price tiers, Setup Wizard | 8–12 tuần |
| **4** | Events + Webhook (Action Scheduler), tích hợp Odoo/CRM/ERP, analytics, AI | theo phạm vi tích hợp |

Phase 1 chia mốc — mỗi mốc có tài liệu `docs/scc/PHASE-1.x.md` theo cùng mẫu 10 mục như PHASE-1…8 hiện có, và dừng chờ duyệt:

| Mốc | Nội dung | Kết thúc khi |
|---|---|---|
| 1.0 | Nâng môi trường: XAMPP PHP 8.2, cấu hình build `@wordpress/scripts`, CI chạy `php -l` + smoke + build | `npm run build` và smoke test chạy trên PHP 8.2 |
| 1.1 | Nền: `saha-theme` skeleton (template, hook, asset), `saha-core` Core/ModuleRegistry + PSR-4, capability `edit_saha_builder`, Theme Options (schema, REST, CSS variables, UI) | đổi màu/typography trong admin → frontend đổi |
| 1.2 | Builder runtime: Schema, ElementRegistry, Sanitizer, Renderer, CssGenerator, RenderCache, LayoutRepository, REST `/builder/*` | lưu JSON qua REST → frontend render đúng, test sanitize |
| 1.3 | Builder app: khung toolbar/panel/canvas iframe, kéo thả, settings tự dựng từ controls, responsive switcher, navigator, undo/redo, copy/paste/duplicate, khoá chỉnh sửa | dựng được trang bằng Section/Row/Column/Heading/Text/Button/Image |
| 1.4 | Element MVP còn lại (§6.4) + Blocks (`saha_block`, element Block) | block sửa một chỗ → mọi trang dùng cập nhật |
| 1.5 | Header/Footer builder (top/main/bottom × desktop/tablet/mobile, sticky, off-canvas mobile) | header/footer mặc định dựng hoàn toàn bằng builder |
| 1.6 | WooCommerce: shop, category, product card, product page + gallery + buy now, cart/checkout/account styling, catalog mode; chuyển template part từ `flatsome-child` | luồng mua hàng và luồng báo giá chạy trên `saha-theme` |
| 1.7 | QA Phase 1 (công cụ Phase 8 + checklist mới), tài liệu dev + admin (spec §115–116) | nghiệm thu MVP |

---

## 12. MVP

**Mục tiêu:** dựng lại website Tổng Kho Keo Dán SAHA hoàn toàn trên `saha-theme` + SAHA Builder, **không cần Flatsome**, admin không phải sửa shortcode/JSON/code (spec §100).

**Trong MVP** (spec §106): Theme core · Theme Options · Builder (runtime + app) · element cơ bản (§6.4 cột Phase 1) · WooCommerce shop + product page · Header · Footer · Blocks.

**Không trong MVP:** mega menu, template builder có điều kiện (MVP dùng template PHP mặc định cho single/archive), quick view, swatches, sticky add to cart, mini cart drawer (MVP: icon + số lượng + link), import/export, demo import, portfolio, B2B, webhook.

**Tiêu chí nghiệm thu MVP:**

1. Trang chủ SAHA (14 khối như `docs/PHASE-5.md`) dựng lại bằng builder, không dùng shortcode.
2. Header/footer dựng bằng builder, responsive 3 thiết bị, sticky, menu mobile off-canvas có `aria-expanded`/`aria-controls`.
3. Đổi màu chính trong Theme Options → toàn site đổi, không sửa CSS.
4. Luồng khách: tìm "243" → trang sản phẩm → yêu cầu báo giá (catalog mode) **hoặc** thêm giỏ → checkout (tắt catalog mode).
5. Tắt `saha-builder` → mọi trang đã dựng vẫn hiển thị đúng.
6. `wp saha qa --strict` đạt; checklist testing §17 đạt; Lighthouse mobile trang chủ LCP < 2.5s, CLS < 0.1 trên staging.

---

## 13. Thứ tự triển khai code

1. **Nâng môi trường** (1.0): PHP 8.2 local, `package.json` + `@wordpress/scripts`, lint JS/PHP, cập nhật `wp saha qa` kiểm tra PHP ≥ 8.2.
2. **Core module system** trong `saha-core`: PSR-4 autoloader song song autoloader cũ, `ModuleRegistry`, `Version`, capability `edit_saha_builder` (gán administrator, editor — spec §67).
3. **Theme Options** (schema → repository → CSS variables → REST → UI): module nhỏ, đi qua đủ tầng → kiểm chứng kiến trúc sớm trước khi làm builder.
4. **`saha-theme` skeleton**: template hierarchy, hook §77, asset manager, fallback header/footer PHP, chuyển template part từ `flatsome-child`.
5. **Builder schema + Sanitizer + Renderer + CssGenerator** (PHP, có unit test không cần WordPress như `tests/smoke.php`).
6. **REST builder** (`elements`, `{id}`, `save`, `render`, `lock`).
7. **Builder app**: store (Data API) → canvas iframe → kéo thả (dnd-kit) → panel settings tự dựng → history → navigator → responsive.
8. **Element MVP** từng nhóm; mỗi element: định nghĩa PHP + render + style + edit component + test sanitize.
9. **Blocks** + element Block.
10. **Header/Footer builder** + element header.
11. **WooCommerce** shop/product/cart/checkout/account + catalog mode.
12. **Dựng lại site SAHA** bằng builder → QA → tài liệu.

Lý do đặt Theme Options trước Builder: cùng mô hình "schema PHP → REST → UI React tự dựng → sanitize theo schema", nhưng nhỏ hơn nhiều — phát hiện vấn đề kiến trúc với chi phí thấp.

---

## 14. Rủi ro kỹ thuật

| # | Rủi ro | Mức | Giảm thiểu |
|---|---|---|---|
| R1 | **Khối lượng**: visual builder là phần mềm lớn (Elementor, Flatsome UX Builder là sản phẩm nhiều năm). MVP 12–16 tuần là ước lượng lạc quan cho 1 người | Cao | Giữ MVP đúng phạm vi §12; mỗi mốc dừng duyệt; ưu tiên ổn định hơn số lượng element |
| R2 | **Phương án thay thế chưa được cân nhắc**: WordPress đã có block editor (kéo thả, undo/redo, reusable/synced pattern, template part cho header/footer). Viết custom block + dùng Site Editor có thể đạt ~70% yêu cầu với chi phí thấp hơn nhiều, nhưng không đạt spec "builder riêng" và trải nghiệm kiểu Flatsome | Cao | **Cần anh/chị quyết định trước Phase 1.** Thiết kế này đi theo spec (builder riêng) nhưng dùng tối đa package của WordPress (components, data, api-fetch, i18n) để giảm khối lượng |
| R3 | Canvas editor và HTML frontend lệch nhau | Cao | Canvas là iframe nạp CSS frontend thật; element động render bằng chính PHP renderer qua `/builder/render`; test so sánh snapshot |
| R4 | PHP 8.2 vs môi trường hiện tại 8.0.30 (đã xác minh) và hosting chưa rõ | Trung bình | Mốc 1.0 nâng XAMPP; kiểm tra PHP hosting production trước khi code |
| R5 | Stored XSS qua element HTML, Custom CSS, rich text | Cao | Control `html` cần `unfiltered_html`; rich text qua `wp_kses_post`; CSS lọc `</style>`, `expression(`, `url(javascript:` ; không bao giờ lưu HTML làm nguồn chính |
| R6 | Render cache chứa nội dung động/theo user (giỏ hàng, giá theo role, nonce) | Cao | Chỉ cache subtree toàn element tĩnh; element động khai báo `dynamic: true` → không cache; giá B2B không bao giờ vào cache chung |
| R7 | Tương thích WooCommerce: block Cart/Checkout (mặc định WC 11) vs classic; WooCommerce đổi template theo phiên bản | Trung bình | Override template tối thiểu (2 file); style block qua `theme.json`; `wp saha qa` cảnh báo template override lỗi thời (so `WC_Admin_Status::get_file_version()` của file theme với file gốc, danh sách từ `WC_Admin_Status::scan_template_files()` — đã xác minh có trong WooCommerce 11.1) |
| R8 | Hai người cùng sửa một trang ghi đè nhau | Trung bình | Post lock của WordPress + `baseHash` → `409 Conflict` |
| R9 | Hai kiểu autoload cùng tồn tại; `includes/tables` (cũ) và `includes/Tables` (PSR-4) trùng nhau trên Windows (không phân biệt hoa thường) | Thấp | Cấm module PSR-4 tên trùng thư mục cũ; test autoload trên Linux trong CI |
| R10 | File CSS sinh ra: quyền ghi, multisite, CDN cache cũ | Trung bình | Tên file chứa hash; `wp_upload_dir()`; fallback inline; System báo lỗi ghi |
| R11 | DOM quá sâu (section/row/column/…) làm chậm INP/LCP | Trung bình | Container flex thay row/column khi có thể; renderer không sinh wrapper thừa; giới hạn độ sâu 12 |
| R12 | Nội dung hiện có dùng shortcode UX Builder (`docs/layouts/*.ux.txt`) không chuyển được tự động | Thấp | Site chưa go-live → dựng lại bằng builder (mốc 1.7); không viết bộ chuyển đổi |
| R13 | Build JS: team cần Node; lệch phiên bản build | Trung bình | Khoá phiên bản trong `package-lock.json`; commit `build/`; CI build lại và so sánh |
| R14 | `saha-builder` phụ thuộc phiên bản API của `saha-core` | Trung bình | `SAHA_BUILDER_API_VERSION` trong core; builder kiểm tra khi khởi động và tự tắt nếu lệch |

---

## 15. Checklist bảo mật

- [ ] Mọi route REST ghi: `X-WP-Nonce` (`wp_rest`) + `current_user_can()` phù hợp + `edit_post( $id )` khi thao tác trên post cụ thể.
- [ ] Nonce không bao giờ là cơ chế phân quyền (đã có trong spec cũ §93).
- [ ] Builder: `edit_saha_builder` (administrator, editor — spec §67); Theme Options: `edit_theme_options`; Templates điều kiện: `manage_saha_templates`; Import: `manage_options`.
- [ ] JSON builder: sanitize **theo định nghĩa control ở server**, không tin client; bỏ prop lạ; kiểm type, `allowedParents`, độ sâu, số element, kích thước.
- [ ] Element HTML / Shortcode: chỉ user có `unfiltered_html` mới lưu được nội dung không lọc; user khác → `wp_kses_post`.
- [ ] Custom CSS: cần `edit_css`; lọc `</style`, `<`, `expression(`, `javascript:`, `@import` ngoài domain.
- [ ] Output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` trong mọi renderer; test XSS cho từng loại control.
- [ ] Block ref: kiểm quyền đọc block (`publish` hoặc user có quyền) — không render block nháp cho khách; chống vòng lặp.
- [ ] Import: chỉ `manage_options`; validate schema file; giới hạn kích thước; không import user/đơn hàng (spec §84); ảnh tải về qua `media_sideload_image` với kiểm mime của WordPress (spec §75).
- [ ] Không SQL trực tiếp khi WooCommerce/WordPress có API (spec §98); SQL còn lại qua `$wpdb->prepare()`.
- [ ] Quick view / search / products: rate limit (đã có) + chỉ trả sản phẩm `publish` + ẩn sản phẩm `exclude-from-catalog`.
- [ ] Giá B2B: tính theo user đang đăng nhập, không bao giờ trả qua endpoint công khai/cache chung.
- [ ] Webhook (Phase 4): ký HMAC, secret không hiển thị lại sau khi tạo, không log payload chứa dữ liệu nhạy cảm.
- [ ] `SAHA_DEBUG` (spec §89) chỉ ghi log, không in lỗi ra frontend.
- [ ] Uninstall không xoá dữ liệu trừ khi bật "Delete all data on uninstall" (đã có — mở rộng cho CPT/option mới).

## 16. Checklist performance

- [ ] Không nạp JS/CSS của builder ở frontend (spec §68) — kiểm bằng `http-smoke.php`.
- [ ] Asset element chỉ nạp khi element có trên trang (slider, gallery, tabs, countdown…) — `AssetManager` thu thập trong lúc render.
- [ ] Script WooCommerce chỉ ở trang WooCommerce; `wc-cart-fragments` tắt (đã có ở catalog mode; khi bán hàng dùng Store API cho mini cart).
- [ ] CSS layout sinh thành file theo trang, có hash, không inline lớn (spec §70); `global.css` từ Theme Options.
- [ ] Font tự host, `font-display: swap`, preload font chính.
- [ ] Ảnh: `srcset`/`sizes`, lazy load trừ ảnh LCP (preload hero — đã có Phase 7, mở rộng cho element Banner đầu trang).
- [ ] Render cache cho subtree tĩnh; query sản phẩm qua `Catalog` (cache theo thế hệ — đã có); `TemplateMap` tra trong bộ nhớ.
- [ ] JS `defer`, không jQuery cho code mới (trừ khi tích hợp script WooCommerce cần).
- [ ] DOM: không wrapper thừa; giới hạn độ sâu.
- [ ] Mục tiêu spec §96: LCP < 2.5s, CLS < 0.1, INP < 200ms trên staging có dữ liệu thật.

## 17. Checklist testing

| Loại | Công cụ | Phạm vi |
|---|---|---|
| Unit PHP (không WordPress) | `tests/smoke.php` mở rộng → tách `tests/unit/` | Schema, Sanitizer (mọi loại control), CssGenerator, điều kiện template + độ cụ thể, migrate schema |
| Unit JS | `@wordpress/scripts test-unit-js` (Jest) | store builder (thêm/xoá/di chuyển/duplicate, undo/redo 50 bước), responsive cascade, copy/paste |
| Tích hợp WordPress | `wp saha qa` mở rộng + site local | CPT, capability, REST route + `permission_callback`, file CSS ghi được, template override lỗi thời |
| HTTP | `tests/http-smoke.php` mở rộng | route builder từ chối khi không đăng nhập/không nonce, frontend không nạp asset builder, render trang builder |
| E2E trình duyệt | kiểm tra bằng trình duyệt (như Phase 8) → sau này `@wordpress/e2e-test-utils-playwright` | kéo thả, lưu, preview, publish; header sticky, off-canvas, bàn phím |
| WooCommerce (spec §86–87) | seeder mở rộng: simple, variable, grouped, external, virtual, downloadable | add to cart, buy now với biến thể, coupon, thuế, ship, hết hàng, backorder, catalog mode |
| Responsive (spec §95) | 1920 · 1440 · 1366 · 1024 · 768 · 430 · 390 · 375 | mọi template + trang builder |
| Trình duyệt (spec §94) | Chrome, Edge, Firefox, Safari, Android, iOS | luồng chính |
| A11y (spec §74) | bàn phím, focus, `aria-*`, axe DevTools | builder admin + frontend, mục tiêu WCAG 2.2 AA |
| Bảo mật | checklist §15 | XSS từng control, quyền từng route, import độc hại |
| Hiệu năng | Lighthouse / PageSpeed | trang chủ, danh mục, sản phẩm |
| Nâng cấp | cài bản cũ → nâng bản mới | migration DB + migration schema JSON không mất dữ liệu |
| Multisite (spec §88) | network 2 site | option, file CSS, bảng tách riêng từng site |

---

## Quyết định

| # | Câu hỏi | Kết quả |
|---|---|---|
| 1 | Builder riêng hay xây trên block editor (R2) | **Builder riêng** — theo spec |
| 2 | PHP 8.2 trên hosting production | **Chưa xác nhận** — phải kiểm tra trước khi deploy Phase 1 |
| 3 | Đóng băng `flatsome-child` (D4) | **Đồng ý** — không QA giao diện Flatsome nữa |
| 4 | Một người hay team | **Chưa xác nhận** — giả định 1 người, làm tuần tự từng mốc |
