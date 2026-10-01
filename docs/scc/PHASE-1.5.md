# SCC Phase 1 — Mốc 1.5: Header & Footer Builder

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §5.1–5.5, §15. Mốc trước: [PHASE-1.4.md](PHASE-1.4.md).

## 1. Goal

- Header và footer **dựng bằng SAHA Builder** thay cho bản PHP của theme.
- Header: các hàng (thanh trên / hàng chính / hàng dưới) × vùng trái–giữa–phải, khác nhau theo desktop / tablet / mobile, **dính khi cuộn** (luôn hiện / chỉ khi cuộn lên), **menu di động off-canvas** đúng chuẩn truy cập (`aria-expanded`, `aria-controls`, Esc, giữ focus).
- 13 element mới: Header, Hàng header, Vùng header, Menu di động, Nút menu, Logo, Menu, Tìm kiếm, Tài khoản, Giỏ hàng, Hotline/Email, Mạng xã hội, Bản quyền.
- Header & footer mặc định tái tạo header/footer PHP hiện có — tạo bằng một nút.

Nghiệm thu: **header/footer mặc định dựng hoàn toàn bằng builder.**

## 2. Architecture

```
saha_template #74 (type header, điều kiện {"include":[{"rule":"all"}]})
saha_template #76 (type footer, …)
        │ lưu / đổi trạng thái / xoá → Repository::compile() → option saha_template_map
        ▼                                  { header: { all: [74] }, footer: { all: [76] } }
saha-theme header.php → saha_theme_render_header() → filter saha_render_header
        └─ Templates\Frontend: resolve('header') (chỉ đọc option, không query) → Renderer → <header>
           không có template → theme in header PHP mặc định (không bao giờ thiếu header)
```

Tài liệu header có **gốc riêng** (`header-root`): chỉ nhận element "Header"; bảng Thêm của builder chỉ hiện element đặt được (không có Section, Hàng, Banner… trong header). Footer dùng gốc thường — dựng bằng Section/Hàng/Cột như trang.

| Quyết định | Lý do |
|---|---|
| Header khác nhau theo thiết bị = **nhiều hàng**, mỗi hàng bật "Ẩn trên …" (tab Nâng cao) | Một cơ chế duy nhất (đã có từ mốc 1.2), không phải ba cây dữ liệu song song. Mặc định: hàng desktop ẩn ở tablet/mobile; hàng mobile ẩn ở desktop. |
| Thiết lập dính nằm trên element "Header", **không** ở meta `_saha_header_settings` (thiết kế) | Một nguồn dữ liệu, sửa ngay trong bảng thiết lập, có undo và revision. |
| Điều kiện hiển thị: chỉ "toàn site" ở mốc này | Bộ điều kiện đầy đủ (trang, danh mục, sản phẩm… theo độ cụ thể) thuộc Template Builder — Phase 2. Cấu trúc `_saha_template_conditions` + `saha_template_map` đã đúng thiết kế nên Phase 2 chỉ thêm rule. |
| Header/footer cần quyền `manage_saha_templates` (chỉ administrator) | Ảnh hưởng toàn site; editor vẫn dựng trang/block được. |
| Vùng hai bên rộng theo nội dung, vùng giữa lấy phần còn lại | Menu dài không bao giờ đè lên ô tìm kiếm (lỗi phát hiện khi thử). |
| JS header riêng trong saha-core (`public/assets/js/header.js`, ~2 KB, defer) | Chạy với mọi theme; chỉ nạp khi có header dựng bằng builder. |
| Màu link trong header/footer có specificity class | Cùng bài học mốc 1.3: `:where()` thua `a { color }` của theme. |

## 3. Files

### saha-core 1.12.0

| File | Vai trò |
|---|---|
| `includes/Templates/PostType.php` | post type `saha_template` (quyền `manage_saha_templates`, không tạo qua post-new.php), menu SAHA → Header & Footer, gốc `header-root`, biên dịch lại chỉ mục khi lưu/xoá |
| `includes/Templates/Repository.php` | loại, điều kiện, `activate()`, `compile()`, `resolve()` + filter `saha_template_resolved` |
| `includes/Templates/Defaults.php` | header/footer mặc định, tài liệu khởi đầu, `install()` |
| `includes/Templates/Frontend.php` | filter `saha_render_header` / `saha_render_footer`, nạp CSS/JS, class body, fragment số giỏ hàng |
| `includes/Templates/AdminScreen.php` | nút Thêm header / Thêm footer / Tạo mặc định, "Dùng cho toàn site", cột Loại / Đang dùng |
| `includes/Builder/Elements/{SiteHeader, HeaderRow, HeaderZone, HeaderOffcanvas, MenuToggle, Logo, NavMenu, Search, Account, Cart, Contact, Social, Copyright}.php` | 13 element |
| `includes/Builder/Icons.php` | + menu, đóng, Facebook, Zalo, YouTube, TikTok, Instagram, LinkedIn |
| `includes/Builder/Sanitizer.php`, `LayoutRepository.php`, `LayoutService.php` | loại gốc theo tài liệu (filter `saha_builder_root_type`) |
| `includes/Builder/Elements/Element.php`, `Row.php`, `ElementRegistry.php` | `initialChildren` (Hàng → 2 cột, Hàng header → 3 vùng); element nội dung đặt được trong vùng header và menu di động |
| `includes/Builder/Frontend.php` | `enqueueDocumentCss()` dùng chung cho trang và template |
| `public/assets/js/header.js`, `public/assets/css/builder.css` | off-canvas, dính khi cuộn; CSS header/footer |
| `includes/class-qa.php` | kiểm header/footer đang dùng |

### saha-builder 0.4.0

| File | Thay đổi |
|---|---|
| `src/builder/store/tree.js` | `ROOT` theo tài liệu (`setRootType`), `placeableTypes()`, `createNode` đọc `initialChildren` từ server (+3 test) |
| `src/builder/panels/Inserter.js` | chỉ hiện element đặt được; nhóm "Header & Footer" |
| `src/builder/App.js`, `includes/Admin/BuilderScreen.php` | truyền loại gốc cho editor |

## 4. Database

| | |
|---|---|
| Post type | `saha_template`, meta `_saha_template_type` (header/footer), `_saha_template_conditions` (JSON), `_saha_template_priority`, + meta builder |
| Option | `saha_template_map` (autoload) — chỉ mục đã biên dịch |
| File | `uploads/saha/css/post-{templateId}-{hash}.css` |

## 5. Code

### Cấu trúc header mặc định

```
Header (dính: luôn hiện)
├─ Hàng — thanh trên (chỉ desktop, ẩn khi đang dính): Hotline, Email │ — │ Mạng xã hội
├─ Hàng chính desktop:  Logo │ Menu (vị trí "primary") │ Tìm kiếm, Hotline, Giỏ hàng
├─ Hàng chính tablet/mobile:  ☰ │ Logo │ Giỏ hàng
└─ Menu di động: Tìm kiếm, Menu dọc, Nút gọi hotline
```

Footer mặc định: Section 4 cột (Logo + giới thiệu · Menu vị trí "footer" · Liên hệ 2 hotline + email · Mạng xã hội) + Section bản quyền.

Hotline, email, Zalo lấy từ **SAHA → Cấu hình** khi để trống (đổi một chỗ, header/footer đổi theo). Menu theo **vị trí** (Giao diện → Menu) — quản trị viên đổi menu không cần mở builder.

### Dùng header khác cho một trang (lập trình, trước khi có điều kiện ở Phase 2)

```php
add_filter( 'saha_template_resolved', function ( $id, $type ) {
    return ( 'header' === $type && is_page( 'landing' ) ) ? 123 : $id;
}, 10, 2 );
```

## 6. Hooks

| Hook | Loại | Mô tả |
|---|---|---|
| `saha_template_resolved` | filter | `(?int $id, string $type)` — ép dùng template khác |
| `saha_template_saved` | action | `(array $map)` — sau khi biên dịch lại chỉ mục |
| `saha_builder_root_type` | filter | `(string $root, int $postId)` — loại gốc của tài liệu |
| `saha_render_header` / `saha_render_footer` | filter (theme) | saha-core in template và trả `true` |

## 7. Security

- Header/footer: mọi thao tác (sửa, tạo, dùng cho toàn site) cần `manage_saha_templates` + `edit_saha_builder`; các nút quản trị dùng `admin-post.php` + nonce (`check_admin_referer`).
- Không tạo template qua `post-new.php` (`create_posts => do_not_allow`) — luôn có loại hợp lệ.
- Gốc `header-root`: Sanitizer từ chối Section… trong header và element Header trong trang (có test).
- Link mạng xã hội qua `Link::url` (chặn `javascript:`), mở tab mới có `rel="noopener"`; email qua `antispambot`; hotline qua `saha_tel_href`.
- Off-canvas: `role="dialog"`, `aria-modal`, nút đóng có nhãn; chỉ một nguồn focus trap.

## 8. Testing

Tự động:

- `php tests/smoke.php` — **168 passed** (+15: header/footer mặc định hợp lệ đúng gốc, Section bị chặn ở gốc header, `<header>` dính, `aria-controls` khớp id bảng, off-canvas hidden + role=dialog, hotline từ cấu hình, `<nav aria-label>`, logo chữ + `rel=home`, ẩn theo thiết bị, editor hiện off-canvas tĩnh, bản quyền có năm, mailto).
- `npm run test:js` — **40 passed** (+3: element đặt được theo gốc, hàng header có 3 vùng).
- `wp saha qa` — header/footer đang dùng.

Thủ công:

- [ ] SAHA → Header & Footer → "Tạo header & footer mặc định" → cả hai "✓ Đang dùng"; website đổi sang header/footer builder.
- [ ] Desktop: thanh trên + hàng chính; cuộn xuống → header dính, thanh trên ẩn, có bóng.
- [ ] Header "Chỉ hiện khi cuộn lên": cuộn xuống ẩn, cuộn lên hiện.
- [ ] Mobile (375): chỉ hàng ☰ │ Logo │ Giỏ; ☰ mở menu trượt; Tab không thoát khỏi bảng; Esc / nền mờ / nút ✕ đóng và focus về ☰; không cuộn ngang.
- [ ] Chế độ catalogue: không có icon giỏ; tắt catalogue: giỏ hiện số lượng, thêm vào giỏ → số tự tăng.
- [ ] Đổi hotline ở SAHA → Cấu hình → header/footer đổi theo.
- [ ] Giao diện → Menu: đổi menu vị trí "Menu chính" → header đổi, không cần mở builder.
- [ ] Mở header trong builder: bảng Thêm không có Section/Hàng/Banner; kéo Logo, Menu… vào vùng; menu di động hiện khung nét đứt bên dưới để kéo thả.
- [ ] Thêm header thứ hai → "Dùng cho toàn site" → website dùng header mới; header cũ thôi "Đang dùng".
- [ ] Chuyển header đang dùng sang Nháp / Thùng rác → website quay về header PHP của theme (không trắng).
- [ ] Editor (không có `manage_saha_templates`) không thấy menu Header & Footer, không mở được header trong builder.
- [ ] Mỗi trang vẫn đúng 1 H1 (logo không phải H1).

## 9. Installation

Cập nhật code → mở wp-admin bằng admin (saha-core 1.12.0). Vào **SAHA → Header & Footer → Tạo header & footer mặc định**, rồi gán menu ở **Giao diện → Menu** (vị trí "Menu chính", "Menu footer").

## 10. Acceptance criteria

- [x] Header #74 và footer #76 tạo bằng nút "Tạo header & footer mặc định", đang dùng cho toàn site; website (saha-theme) hiển thị header/footer dựng bằng builder, không còn header PHP (`#saha-header` không còn trong trang).
- [x] Desktop: dính dưới admin bar, thanh trên ẩn khi dính; menu không đè ô tìm kiếm. Mobile: chỉ hàng mobile hiện; ☰ mở bảng (aria-expanded=true, focus vào bảng, khoá cuộn, 6 link menu), Esc đóng, focus về ☰; không cuộn ngang.
- [x] Sửa header trong builder (màu hotline = màu chính) → lưu → website đổi.
- [x] Hồi quy: `php -l` 0 lỗi · smoke 168/168 · test JS 40/40 · lint JS/SCSS 0 lỗi · `wp saha qa` 77 đạt / 0 lỗi · `http-smoke --write` 41 đạt / 0 lỗi · `debug.log` không lỗi PHP mới.

### Lỗi phát hiện và đã sửa

| # | Lỗi | Sửa |
|---|---|---|
| 1 | Menu dài đè lên ô tìm kiếm | vùng hai bên rộng theo nội dung, vùng giữa lấy phần còn lại |
| 2 | Nút đóng menu di động bị admin bar che | z-index bảng trên admin bar |
| 3 | Logo ở footer bị màu link của theme (xanh), tên bị cắt "…" | màu link có specificity class; chỉ cắt "…" trong header |
| 4 | Màu đặt riêng cho hotline không áp | luật `color: inherit` dùng `:not()` tăng specificity → bỏ |
| 5 | Block dùng chung, Container không đặt được trong header | thêm vùng header / menu di động vào cha hợp lệ |

## 11. Ghi chú & giới hạn

- Chỉ điều kiện "toàn site"; header riêng theo trang/danh mục ở Phase 2 (hoặc filter `saha_template_resolved`).
- Header builder chạy qua filter của **saha-theme**; theme khác (flatsome-child đã đóng băng) không gọi filter này.
- Thanh trên "ẩn khi dính" làm header ngắn lại khi bắt đầu cuộn (nội dung nhích lên một chút). Chấp nhận được; nếu cần mượt hơn sẽ đổi sang thu gọn chiều cao.
- Off-canvas: nếu đặt ID riêng (tab Nâng cao) cho element "Menu di động" sẽ có 2 thuộc tính id — không nên đặt.
- Dữ liệu thử trên local: menu "Menu chính (thử)", "Menu chân trang (thử)" (gán vào vị trí primary/footer), header #74, footer #76.
