# SCC Phase 2 — Mốc 2.1: Mega menu

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §3 (module 08), §5.2; kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-1.7.md](PHASE-1.7.md).

## 1. Goal

- Mục cấp 1 của menu ngang mở **bảng mega** rộng bằng khung / toàn màn hình / số px tuỳ chọn.
- Nội dung bảng: **một Block dùng chung** (dựng bằng builder — ảnh, lưới thương hiệu, CTA báo giá…) hoặc **menu con chia N cột** (cấp 2 = tiêu đề cột, cấp 3 = danh sách).
- Cài đặt ngay trong **Giao diện → Menu**, không cần code.
- Chạy với header dựng bằng builder (element Menu ngang) và header PHP của saha-theme. Menu di động (off-canvas) giữ menu thường.
- Bàn phím + `aria`: Tab mở, Esc đóng và trả focus, `aria-expanded` đúng trạng thái.

Nghiệm thu: **menu "Sản phẩm" mở mega chia cột / "Thương hiệu" mở một Block; mobile vẫn là menu thường.**

## 2. Architecture

```
Giao diện → Menu ── AdminFields (wp_nav_menu_item_custom_fields) ──► meta mục menu
                                                                      _saha_menu_type = mega
                                                                      _saha_mega_settings = {width, customWidth, columns, blockId}
                     wp_update_nav_menu ──► Settings::compile() ──► option saha_mega_menu {active, blocks[]}

wp_nav_menu( [..., 'saha_mega' => true] )        ← element Menu (ngang), header PHP của saha-theme
  └─ walker mặc định của WordPress + filter của MegaMenu\Frontend:
       wp_nav_menu_args        depth ≥ 3 (cột cần cấp 3)
       nav_menu_css_class      saha-mega-item, --container|--full|--custom, --block | --cols-N
       nav_menu_item_attributes  --saha-mega-width (tuỳ chỉnh)
       nav_menu_link_attributes  aria-expanded
       walker_nav_menu_start_el  <div class="saha-mega"> + LayoutService::renderBlock()
wp_enqueue_scripts: chỉ khi option active → mega-menu.css, mega-menu.js (defer), CSS của block
```

| Quyết định | Lý do |
|---|---|
| Filter trên walker mặc định, không viết Walker riêng (thiết kế ghi "Walker (theme)") | Chạy cho mọi `wp_nav_menu()` — element builder, header PHP, theme khác — chỉ cần thêm cờ `saha_mega`. Walker riêng phải truyền vào từng chỗ gọi. |
| Bật bằng cờ `saha_mega` trên lời gọi menu | Menu dọc/off-canvas không bao giờ thành mega → mobile không tải nội dung block, không cần media query để "tắt" mega. |
| Mục có Block: menu con của mục đó ẩn trên desktop | Block là nội dung thay thế; menu con vẫn có trong menu mobile. |
| Option `saha_mega_menu` biên dịch khi lưu menu | Frontend biết có cần nạp CSS/JS mega và CSS của block nào mà không đọc meta từng mục menu. |
| Bảng "khung"/"toàn màn" neo vào header (`.saha-hb`, `.saha-header` có `position`) | Bảng rộng hết header thay vì bị kẹp trong `<li>`; `<li>` của mục mega thành `static`. |
| Đóng trễ 0.2s (`transition` của `visibility`) | Rê chuột từ link xuống bảng đi qua phần còn lại của header không làm bảng đóng. Không dùng vùng đệm vô hình (che mất ô tìm kiếm cạnh menu). |
| Block không còn xuất bản → mục tự về kiểu chia cột | Không bao giờ có bảng trống; `wp saha qa` cảnh báo. |
| Style link menu chỉ nhắm `.menu-item > a` | Trước đây `.saha-nav a`, `.saha-menu a` và CSS của element Menu (`.saha-e-… a`) đè lên mọi link trong Block mega (thẻ thương hiệu mất bố cục — lỗi phát hiện khi thử). |

## 3. Files

### saha-core 1.15.0

| File | Vai trò |
|---|---|
| `includes/MegaMenu/Module.php` | đăng ký (admin + frontend) |
| `includes/MegaMenu/Settings.php` | đọc/ghi/chuẩn hoá meta, biên dịch option |
| `includes/MegaMenu/AdminFields.php` | ô cài đặt ở Giao diện → Menu, lưu cùng nút "Lưu menu" |
| `includes/MegaMenu/Frontend.php` | filter walker, nạp asset |
| `public/assets/css/mega-menu.css`, `public/assets/js/mega-menu.js` | giao diện bảng; `aria-expanded` + Esc |
| `includes/Builder/Elements/NavMenu.php` | cờ `saha_mega` khi menu ngang; style chỉ cho link mục menu |
| `public/assets/css/builder.css` | link menu `.menu-item > a`; menu cấp 3 trong dropdown thường thành danh sách thụt vào |
| `includes/class-loader.php`, `includes/class-qa.php` | module `mega_menu`; kiểm tra block của mega |

### saha-theme 0.2.2

| File | Thay đổi |
|---|---|
| `template-parts/header/default.php` | menu chính: `saha_mega => true`, 3 cấp |
| `src/scss/_header.scss` | link menu `.menu-item > a`; cấp 3 trong dropdown |

## 4. Database

Meta `nav_menu_item` (`_saha_menu_type`, `_saha_mega_settings`) đúng TECHNICAL-DESIGN §5.2; option `saha_mega_menu` (autoload). Mục menu thường không có meta.

## 5. Code

Bật mega cho menu trong theme/plugin khác:

```php
wp_nav_menu( array( 'theme_location' => 'primary', 'saha_mega' => true ) );
```

Cần phần tử bao ngoài header có `position` (relative/sticky) để bảng "khung"/"toàn màn" neo vào.

## 6. Hooks

Không thêm hook mới; dùng filter chuẩn của WordPress (`wp_nav_menu_args`, `nav_menu_css_class`, `nav_menu_item_attributes`, `nav_menu_link_attributes`, `walker_nav_menu_start_el`). Tham số mới của `wp_nav_menu()`: `saha_mega` (bool).

## 7. Security

- Lưu: chỉ khi có nonce `update-nav_menu` của màn hình Menu + `edit_theme_options`; mọi giá trị qua `Settings::sanitize()` (độ rộng trong danh sách, px 300–1600, cột 1–6, block phải là `saha_block`).
- Customizer lưu menu không gửi các ô SAHA → thiết lập giữ nguyên (không bị xoá).
- Nội dung bảng render qua `LayoutService::renderBlock()` (chỉ block đã xuất bản, chống vòng lặp) — escape như mọi block.

## 8. Testing

Tự động:

- `php tests/smoke.php` — **190 passed** (+9: chuẩn hoá thiết lập, block phải là `saha_block`, class cột/độ rộng, biến CSS độ rộng, `aria-expanded`, menu không cờ giữ nguyên, chỉ cấp 1, block nháp → về chia cột, nâng độ sâu 3 cấp).
- `npm run test:js` 40 passed; lint sạch; `php tests/http-smoke.php` 33 passed / 0 failed.
- `wp saha qa` — 90 đạt / 0 lỗi (+1: "Mega menu: Block nội dung còn tồn tại").

Đã chạy thử trên trình duyệt (local):

- [x] "Sản phẩm" (mega chia 4 cột): Keo Silicone (Apollo, Bamboo, Dowsil, Wacker), Keo công nghiệp (Khoá ren…), PU Foam, Keo AB, Keo 502, Chống thấm.
- [x] "Thương hiệu" (Block "Mega — Thương hiệu"): lưới thương hiệu + CTA "Yêu cầu báo giá" → modal báo giá mở tại chỗ; CSS của block được nạp.
- [x] Bàn phím: Tab tới "Sản phẩm" → bảng mở, `aria-expanded="true"`; Esc → bảng đóng, focus ở lại link, `aria-expanded="false"`.
- [x] Menu dọc trong off-canvas mobile: không có class/bảng mega, chỉ 2 cấp.
- [x] Header PHP của theme (tạm tắt header builder): có đủ class và bảng mega.
- [x] Giao diện → Menu: ô cài đặt hiện đúng giá trị; đổi số cột → "Lưu menu" → lưu đúng, mục khác giữ nguyên.

Thủ công (cần review):

- [ ] Độ rộng "Toàn màn hình" và "Tuỳ chỉnh 900px"; menu nằm ở hàng dưới cùng của header.
- [ ] Rê chuột chéo từ "Sản phẩm" sang "Thương hiệu" nhanh: bảng trước đóng, bảng sau mở, không nhấp nháy.
- [ ] Trình đọc màn hình (NVDA): đọc "đã thu gọn/đã mở rộng" ở link cấp 1.

## 9. Installation

Cập nhật code (saha-core 1.15.0, saha-theme 0.2.2) → **Giao diện → Menu** → mở mục cấp 1 → **SAHA — kiểu menu: Mega menu** → chọn nội dung (Block hoặc menu con chia cột), độ rộng, số cột → **Lưu menu**.

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Mega chia cột từ menu con | ✅ "Sản phẩm" |
| Mega nội dung Block | ✅ "Thương hiệu" (block #102) |
| Mobile vẫn là menu thường | ✅ off-canvas không có mega |
| Bàn phím + aria | ✅ Tab/Esc, `aria-expanded` |

### Lỗi phát hiện và đã sửa

- Mega chia cột thiếu cấp 3 vì element Menu đặt độ sâu 2 → menu có cờ mega lấy ít nhất 3 cấp khi site đang dùng mega.
- Link trong Block mega bị style của menu (thẻ thương hiệu "Loctite10 sản phẩm" dính nhau) → style menu chỉ nhắm `.menu-item > a` ở builder, theme và CSS sinh từ element Menu.
- Menu cấp 3 trong dropdown thường (không mega) bật thêm hộp nổi chồng lên nhau → thành danh sách thụt vào.

## 11. Ghi chú & giới hạn

- Customizer (Giao diện → Tuỳ biến → Menu) chưa có ô SAHA; cài mega ở Giao diện → Menu.
- Dữ liệu thử trên local: menu "Menu chính (thử)" thêm 13 mục danh mục, mục "Thương hiệu" (#104, mega block), block #102; "Sản phẩm" (#64) mega 3 cột.
