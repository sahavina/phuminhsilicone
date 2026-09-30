# SCC Phase 1 — Mốc 1.1: Nền (saha-theme, capability, Theme Options)

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §15. Mốc trước: [PHASE-1.0.md](PHASE-1.0.md).

## 1. Goal

- `saha-theme`: theme classic PHP tự viết, thay Flatsome (D3). Đủ template WordPress + WooCommerce, header/footer mặc định, hook mở rộng, không jQuery.
- `saha-core`: PSR-4 cho code mới, capability cho builder, envelope lỗi REST có `code`.
- **Theme Options**: schema khai báo ở PHP → REST → form React tự dựng → CSS variables `--saha-*` → theme dùng.
- `saha-builder`: plugin chứa ứng dụng admin (mốc này: màn hình Theme Options).
- `saha-theme-child`: child theme rỗng cho tuỳ biến riêng.
- `flatsome-child` **đóng băng** (không sửa, không xoá).

Nghiệm thu: **đổi màu/typography trong admin → frontend đổi.**

## 2. Architecture

```
Appearance → SAHA Theme Options  (saha-builder, React)
        │  GET/POST /wp-json/saha/v1/settings   (chỉ gửi field đã đổi)
        ▼
saha-core ThemeOptions
  Schema ──► Sanitizer (từng field, theo type) ──► Repository (option saha_theme_options)
                                                      │ action saha_theme_options_saved
                                                      ▼
                                   CssVariables ──► Performance\CssFileStore
                                                      uploads/saha/css/global-{hash}.css
        ▼
saha-theme: frontend.css dùng var(--saha-*), mặc định trong :where(:root)
```

- **Một nguồn schema**: thêm field ở `Schema::groups()` là form admin tự có control, REST tự validate, CSS tự có biến (nếu field khai báo `cssVar`). Không sửa JS.
- **Server sanitize mọi giá trị**; UI chỉ giúp nhập đúng. Lưu từng phần: field sai → 422 kèm `errors` theo `nhóm.field`, không field nào được lưu (tránh trạng thái nửa vời).
- **CSS file có hash trong tên** → cache trình duyệt vĩnh viễn, không cần `?ver`. Ghi nguyên tử (file tạm + rename), giữ file mới nhất, xoá file cũ. Không ghi được uploads → in CSS inline (vẫn chạy).
- CSS tự sinh lại khi: lưu Theme Options, phiên bản plugin đổi, hoặc file bị xoá.
- **Theme luôn thắng quyền ưu tiên đúng chiều**: giá trị mặc định của theme khai báo trong `:where(:root)` (specificity 0) nên `:root` của `global.css` luôn đè được, bất kể thứ tự nạp.
- **Chế độ catalogue** vẫn một nguồn: `saha_core_settings.catalogue_mode`. Field `catalog.enabled` trong Theme Options chỉ là "cửa sổ" đọc/ghi vào đó (khai báo `storage`).
- Theme **không phụ thuộc cứng** saha-core: mọi lời gọi qua `inc/helpers.php` có giá trị dự phòng. Tắt saha-core, theme vẫn render bằng giá trị mặc định.
- Header/footer đi qua filter `saha_render_header` / `saha_render_footer` — mốc 1.5 (Header/Footer Builder) cắm vào đây.

### Sai khác so với thiết kế

- Thiết kế ghi `Core\ModuleRegistry` + `Core\Container`. `Saha\Core\Loader` hiện có đã làm đúng hai việc đó (map module, `get()`/`has()`, filter `saha_core_modules`), nên **giữ Loader** và đăng ký module PSR-4 vào nó thay vì tạo lớp song song. Autoloader thử PSR-4 (`includes/Ns/Class.php`) trước, rồi dạng cũ (`class-*.php`).
- Tab của màn hình Theme Options là component tự viết (`GroupTabs.js`), không dùng `TabPanel` của `@wordpress/components`: bản Ariakit của TabPanel chọn tab theo focus, dễ đổi tab ngoài ý muốn giữa lúc nhập.

## 3. Files

### saha-core (1.8.0)

| File | Vai trò |
|---|---|
| `includes/ThemeOptions/Schema.php` | 10 nhóm field, font stack hệ thống, `defaults()`, `forClient()` |
| `includes/ThemeOptions/Sanitizer.php` | sanitize theo type: color, size (min/max, đơn vị), number, select, toggle, media, typography, css, responsive |
| `includes/ThemeOptions/InvalidValue.php` | exception khi giá trị sai |
| `includes/ThemeOptions/Repository.php` | đọc/ghi option `saha_theme_options`, field có `storage` ghi sang option khác |
| `includes/ThemeOptions/CssVariables.php` | sinh `:root{}` + `@media` tablet (≤1024) / mobile (≤767) + CSS tuỳ chỉnh |
| `includes/ThemeOptions/Module.php` | đăng ký REST, sinh lại CSS, enqueue `saha-global` khi theme hỗ trợ `saha-theme-options` |
| `includes/ThemeOptions/Rest/SettingsController.php` | `GET/POST /saha/v1/settings` |
| `includes/Performance/CssFileStore.php` | ghi file CSS có hash, nguyên tử, dọn file cũ |
| `includes/class-api.php` | `error()` thêm `code` (`validation_failed`, `forbidden`…) |
| `includes/class-roles.php` | capability builder, tự vá role khi định nghĩa đổi |
| `includes/class-install.php`, `class-loader.php`, `saha-core.php`, `uninstall.php` | đăng ký module, PSR-4, dọn dữ liệu khi gỡ |

### saha-builder (0.1.0, mới)

| File | Vai trò |
|---|---|
| `saha-builder.php` | bootstrap; `Requires Plugins: saha-core`; kiểm tra `SAHA_BUILDER_API_VERSION` |
| `includes/Plugin.php`, `includes/Assets.php` | kiểm tra phụ thuộc, nạp entry từ `build/*.asset.php` |
| `includes/Admin/ThemeOptionsScreen.php` | trang Appearance → SAHA Theme Options |
| `src/theme-options/` | `App.js`, `GroupTabs.js`, `utils.js`, `fields/*` (10 loại control), `editor.scss` |
| `build/` | output đã build (commit) |

### saha-theme (0.1.0, mới)

| File | Vai trò |
|---|---|
| `style.css`, `theme.json`, `functions.php` | khai báo theme; `theme.json` tối thiểu (palette trỏ về `var(--saha-*)`) |
| `inc/helpers.php` | cầu nối an toàn sang saha-core, logo, hotline, icon SVG |
| `inc/setup.php`, `inc/assets.php` | theme support, menu (primary/mobile/footer), 4 vùng widget footer, nạp asset |
| `inc/hooks.php`, `inc/template-functions.php` | hook `saha_*`, class header/body, render header/footer, vòng lặp bài viết |
| `inc/woocommerce.php` | bỏ wrapper WC, số sản phẩm/cột từ Theme Options |
| `inc/seo.php` | breadcrumb một nguồn (Rank Math → Yoast → WooCommerce) |
| `inc/performance.php` | tắt emoji; tắt cart fragments khi catalogue |
| `header.php`, `footer.php`, `index.php`, `front-page.php`, `page.php`, `single.php`, `archive.php`, `search.php`, `404.php`, `woocommerce.php` | template |
| `template-parts/header/default.php`, `footer/default.php`, `blog/card.php`, `components/*` | phần dùng lại |
| `src/js/frontend.js`, `src/scss/*` | off-canvas, header dính; style dùng biến `--saha-*` |

### saha-theme-child (mới)

`style.css` (`Template: saha-theme`), `functions.php` (nạp style.css sau theme cha).

## 4. Database

| Option | Nội dung |
|---|---|
| `saha_theme_options` | giá trị Theme Options (autoload) |
| `saha_theme_css` | `{file, hash, inline, core}` của CSS toàn cục |
| `saha_core_roles_hash` | hash định nghĩa role/capability — lệch là tự cài lại role |

File: `wp-content/uploads/saha/css/global-{12 hex}.css`. Gỡ plugin (`uninstall.php`) xoá cả ba option và thư mục CSS.

## 5. Code

REST:

```
GET  /wp-json/saha/v1/settings   → { success, data: { schema, values } }
POST /wp-json/saha/v1/settings   body: { values: { nhóm: { field: giá trị } } }  (chỉ field muốn đổi)
     200 → { success, data: { values } }
     422 → { success:false, code:"validation_failed", errors:{ "nhóm.field": "thông báo" }, data:{ values } }
```

Quyền: `edit_theme_options`. Nonce: `wp_rest` (apiFetch tự gửi).

Đọc giá trị trong theme / plugin khác:

```php
saha_theme_option( 'header.sticky', true );                 // trong theme (có fallback)
\Saha\Core\ThemeOptions\Repository::get( 'colors.primary' ); // trong plugin
```

## 6. Hooks

| Hook | Loại | Mô tả |
|---|---|---|
| `saha_before_header`, `saha_after_header`, `saha_before_content`, `saha_after_content`, `saha_before_footer`, `saha_after_footer` | action | vị trí chèn nội dung quanh khung trang |
| `saha_before_product`, `saha_after_product` | action | bắc cầu từ hook sản phẩm WooCommerce |
| `saha_render_header`, `saha_render_footer` | filter (bool) | trả `true` nếu đã tự render (builder mốc 1.5) |
| `saha_header_classes`, `saha_body_classes` | filter | class của `<header>` / `<body>` |
| `saha_theme_options` | filter | giá trị Theme Options khi đọc |
| `saha_theme_options_saved` | action | sau khi lưu (sinh lại CSS) |
| `saha_css_variables` | filter | thêm/sửa biến CSS theo thiết bị |
| `saha_theme_font_stacks` | filter | thêm font stack (font tự host) |
| `saha_builder_role_caps` | filter | role → capability builder |
| `saha_theme_options_schema` | filter | thêm/sửa nhóm, field Theme Options |

## 7. Security

- REST `settings`: khách 401, editor 403 (cần `edit_theme_options`). Đã thử thật.
- Mọi giá trị qua `Sanitizer` theo type; màu chỉ nhận hex/rgb/rgba/`var(--saha-*)`; kích thước kiểm đơn vị và min/max.
- CSS tuỳ chỉnh: bỏ thẻ HTML, `<` `>`, `@import`, `expression(`, `javascript:`, `behavior:`, `-moz-binding`.
- `CssVariables` lọc lần hai lúc sinh CSS: tên biến chỉ `[a-z0-9-]`, giá trị không được chứa `;` `{` `}` `<` — dữ liệu bẩn trong DB (nhập tay, import) cũng không thoát khỏi khai báo.
- Logo lưu attachment ID (đã kiểm là ảnh), không lưu URL.
- Capability: `edit_saha_builder` (administrator, editor), `manage_saha_templates` (administrator) — chưa dùng ở 1.1, chuẩn bị cho 1.3.
- Template theme escape toàn bộ output (`esc_html`, `esc_url`, `esc_attr`).

## 8. Testing

Tự động:

- `php tests/smoke.php` — thêm 20 case Theme Options (Sanitizer, CssVariables, font stack): **92 passed**.
- `npm run lint:js`, `npm run lint:css` — 0 lỗi.

Thủ công (đã chạy trên local, WordPress 7.1.2 + WooCommerce 11.1.2, PHP 8.2):

- [ ] Appearance → SAHA Theme Options mở được, đủ 10 tab, chuyển tab không mất giá trị đang nhập.
- [ ] Đổi **Màu chính** → Lưu → "Đã lưu Theme Options" → trang chủ: link, nút, hotline đổi màu.
- [ ] Đổi **Chữ → Nội dung → Font** → Lưu → frontend đổi font; tiếng Việt có dấu hiển thị liền (ế, ộ, ữ).
- [ ] Nhập độ rộng khung `5000px` → Lưu → báo lỗi ngay dưới field, tab có dấu ⚠, không giá trị nào được lưu.
- [ ] Rời trang khi còn thay đổi chưa lưu → trình duyệt hỏi xác nhận.
- [ ] Đổi khoảng cách lề mobile → chỉ đổi ở màn hình ≤ 767px.
- [ ] Nhập `</style><script>alert(1)</script>` vào CSS tuỳ chỉnh → Lưu → xem nguồn trang: không có thẻ script.
- [ ] Tài khoản editor mở `/wp-json/saha/v1/settings` → 403.
- [ ] Bật chế độ catalogue trong Theme Options → **SAHA → Cấu hình** cũng hiện bật (một nguồn).
- [ ] Mobile (375px): không cuộn ngang; bấm ☰ mở menu, `Esc` đóng, focus quay về nút ☰; Tab không thoát khỏi menu khi đang mở.
- [ ] Header dính: chế độ "Luôn hiện" dính khi cuộn; "Chỉ hiện khi cuộn lên" ẩn khi cuộn xuống, hiện khi cuộn lên.
- [ ] Trang shop: số cột desktop/tablet/mobile theo Theme Options → Cửa hàng.
- [ ] 404 hiện ô tìm sản phẩm + danh mục, không chuyển về trang chủ.
- [ ] Mỗi trang có đúng 1 H1 (trang chủ dạng danh sách bài: H1 ẩn là tên website).
- [ ] Tắt saha-core → theme vẫn hiển thị (màu mặc định), không lỗi PHP.

## 9. Installation

```bash
# Local (Windows): nối thư mục
New-Item -ItemType Junction -Path "$site\plugins\saha-builder"     -Target "$repo\plugins\saha-builder"
New-Item -ItemType Junction -Path "$site\themes\saha-theme"        -Target "$repo\themes\saha-theme"
New-Item -ItemType Junction -Path "$site\themes\saha-theme-child"  -Target "$repo\themes\saha-theme-child"

wp plugin activate saha-builder
wp theme activate saha-theme          # hoặc saha-theme-child
```

Mở wp-admin một lần bằng admin để saha-core 1.8.0 chạy nâng cấp (capability builder).

Không cần Node trên server: `build/` đã commit. Sửa `src/` thì chạy `npm run build` và commit cả `build/`.

## 10. Acceptance criteria

- [x] Đổi màu chính trong admin (`#c0392b` → `#127a3f`) → frontend `--saha-primary: #127a3f`, link đổi màu (kiểm bằng trình duyệt).
- [x] Đổi font body → CSS `--saha-type-body-font` đổi, frontend đổi font.
- [x] Lỗi validate hiện đúng field, không lưu nửa vời (422).
- [x] Mọi trang chính (chủ, bài, trang, lưu trữ, tìm kiếm, 404, shop, danh mục, sản phẩm) → 200/404 đúng, có header/footer, không lỗi PHP.
- [x] Hồi quy: `php -l` 0 lỗi · smoke 92/92 · `wp saha qa` 63 đạt / 4 cảnh báo / 0 lỗi · `http-smoke --write` 33 đạt / 0 lỗi / 2 bỏ qua · `debug.log` chỉ có lỗi mail (local không có SMTP, như trước).

### Lỗi phát hiện và đã sửa khi kiểm trên trình duyệt

| # | Lỗi | Sửa |
|---|---|---|
| 1 | Màn hình Theme Options trắng (React #130) | `MediaUploadCheck` không có trong `@wordpress/media-utils` → bỏ, kiểm `upload_files` ở server (`canUpload`) |
| 2 | CSS của builder không nạp | wp-scripts tách `style.scss` thành `style-{entry}.css` → đổi tên `editor.scss` |
| 3 | Font "Serif" làm vỡ dấu tiếng Việt (ế → e + dấu rời) | Georgia thiếu glyph dựng sẵn → stack `"Times New Roman", Times, "Noto Serif", serif` |
| 4 | Mobile cuộn ngang 63px | tên website dạng chữ quá dài → cắt `…`, thương hiệu co giãn |
| 5 | Sản phẩm trong lưới chỉ rộng một nửa ô | CSS `woocommerce-smallscreen` (`[class*=columns-]`) đè `width` → tăng độ cụ thể |
| 6 | Trang chủ dạng danh sách bài không có H1 | thêm H1 ẩn là tên website |
| 7 | Dấu "·" thừa khi bài không có tác giả | chỉ in khi có tác giả |

## 11. Ghi chú & giới hạn

- **Ẩn giá khi catalogue** ở `saha-theme` hiện chỉ bằng CSS (`.saha-catalog-mode .price`). Logic ẩn giá phía server nằm trong `flatsome-child` (đóng băng) → chuyển sang `saha-theme` ở mốc 1.6 (WooCommerce). **Đừng bật `saha-theme` trên production trước mốc 1.6** nếu site dùng chế độ catalogue.
- Giao diện sản phẩm, trang sản phẩm, giỏ hàng: dùng mặc định WooCommerce đến mốc 1.6.
- Chỉ có font hệ thống (không tải font ngoài). Font tự host thêm qua filter `saha_theme_font_stacks` + `@font-face` ở child theme.
- Header/footer là bản PHP cố định; builder kéo thả ở mốc 1.5.
- Trang "Xem trước" trực tiếp trong Theme Options chưa có — phải Lưu rồi mở website.
- Lưu Theme Options mất ~2–3 giây trên XAMPP local (ghi option + sinh file CSS); chưa đo trên hosting.
