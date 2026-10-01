# SCC Phase 1 — Mốc 1.7: QA Phase 1 + tài liệu (nghiệm thu MVP)

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §12 (MVP), §17 (testing). Mốc trước: [PHASE-1.6.md](PHASE-1.6.md).

## 1. Goal

- **Dựng lại trang chủ SAHA bằng builder**, không shortcode (MVP tiêu chí 1) — có sẵn thành "trang chủ mẫu" tạo bằng một nút / một lệnh.
- Mở rộng công cụ QA Phase 8 cho SCC: `wp saha qa`, `tests/http-smoke.php`, `tests/smoke.php`, checklist `docs/QA.md`.
- Tài liệu: [DEV-GUIDE.md](DEV-GUIDE.md) (spec §115), [ADMIN-GUIDE.md](ADMIN-GUIDE.md) (spec §116).
- Chạy nghiệm thu MVP trên local, ghi rõ phần còn phải chạy trên staging.

Nghiệm thu: **MVP** (TECHNICAL-DESIGN §12).

## 2. Architecture

```
Trang → "Tạo trang chủ mẫu (SAHA Builder)"  ─┐
wp saha homepage --front                      ├─ Builder\Starter::installHomepage()
                                              │    homepage() → tài liệu 14 khối (element builder, không shortcode)
                                              │    LayoutService::save() (đủ sanitize như lưu từ editor)
                                              └─ show_on_front = page, page_on_front = ID

Cta "Mở form báo giá" → <button data-saha-open-quote> + do_action('saha_quote_modal_needed')
                         └─ saha-theme in modal báo giá ở footer (như [saha_quote_cta] cũ)
                         Cta::isDynamic() = true → không vào render cache (action phải chạy mỗi request)
```

| Quyết định | Lý do |
|---|---|
| Trang chủ mẫu là **code** (`Builder\Starter`), không phải file JSON export | Qua đúng Sanitizer như editor; tự bỏ khối khi site thiếu danh mục/thương hiệu (term không tồn tại thì không lưu được); dịch được. |
| Hero = Section nền màu phụ + H1 + mô tả + ô tìm kiếm (giống `homepage.ux.txt`) | Ô tìm kiếm theo mã ("243") là chức năng chính của hero. Ảnh nền chọn sau trong builder; muốn LCP tốt nhất thì dùng Banner "ưu tiên tải". |
| Thêm "Thương hiệu" vào element Danh mục sản phẩm, "Mở form báo giá" vào CTA — không thêm element mới | Thay `[saha_brand_grid]`, `[saha_quote_cta]` bằng thiết lập của element sẵn có; bảng Thêm không phình. |
| `Element::isDynamic( Node )` thay vì chỉ cờ `dynamic` theo loại | CTA chỉ động khi nút mở form báo giá; CTA thường vẫn được cache. |
| `wp saha qa` coi `saha-theme` là theme chuẩn; Flatsome Child → cảnh báo | Quyết định D4 (đóng băng flatsome-child). |

## 3. Files

### saha-core 1.14.0

| File | Thay đổi |
|---|---|
| `includes/Builder/Starter.php` | **mới** — trang chủ mẫu + nút ở Trang → Tất cả trang (quyền `edit_saha_builder` + `manage_options`) |
| `includes/Builder/Elements/Cta.php` | nút chính: **Đi tới liên kết / Mở form báo giá** |
| `includes/Builder/Elements/Element.php`, `RenderCache.php` | `isDynamic( Node )` |
| `includes/Builder/Elements/ProductCategories.php`, `class-catalog.php` | loại **Thương hiệu** (ảnh thương hiệu của WooCommerce) |
| `includes/class-qa.php` | theme SAHA, plugin builder, trang chủ dựng bằng builder, trang còn shortcode Flatsome, template WooCommerce override lỗi thời |
| `includes/class-cli.php` | `wp saha homepage [--front]` |
| `public/assets/css/builder.css` | tiêu đề có link giữ màu tiêu đề |

### saha-theme 0.2.1

| File | Thay đổi |
|---|---|
| `inc/catalog.php` | in modal báo giá khi có `saha_quote_modal_needed`; nạp CSS thẻ sản phẩm ở trang dựng bằng builder |

### Kiểm thử & tài liệu

`tests/smoke.php`, `tests/http-smoke.php`, `tests/build-qa-checklist.py` → `docs/QA.md`, `docs/scc/DEV-GUIDE.md`, `docs/scc/ADMIN-GUIDE.md`.

## 4. Database

Không đổi schema. Trang chủ mẫu: một `page` + meta builder như trang dựng tay.

## 5. Code

Trang chủ mẫu — 9 section, 14 khối của [PHASE-5.md](../PHASE-5.md):

| # | Khối | Element |
|---|---|---|
| 1 | Hero (H1 duy nhất) + tìm kiếm | Section › Hàng › Cột › Tiêu đề H1, Văn bản, Tìm kiếm |
| 2 | Danh mục chính | Tiêu đề + Danh mục sản phẩm |
| 3 | Thương hiệu nổi bật | Tiêu đề (link /thuong-hieu/) + Danh mục sản phẩm › Thương hiệu |
| 4–5 | Nổi bật, Mới | Tiêu đề + Sản phẩm |
| 6–7 | Keo Silicone, Keo công nghiệp | Tiêu đề + mô tả + Sản phẩm theo danh mục |
| 8 | Báo giá số lượng lớn | CTA › Mở form báo giá |
| 9–10 | PU Foam, Keo Loctite | Sản phẩm theo danh mục / thương hiệu |
| 11 | Ứng dụng | Danh mục sản phẩm › Ứng dụng |
| 12 | Vì sao chọn SAHA | Hàng 4 cột × Hộp icon |
| 13 | Kiến thức & hướng dẫn | Bài viết |
| 14 | Chưa tìm thấy sản phẩm | CTA › Mở form báo giá |

## 6. Hooks

| Hook | Loại | Ghi chú |
|---|---|---|
| `saha_quote_modal_needed` | action | element cần modal báo giá trên trang; theme in modal ở `wp_footer` |

## 7. Security

- Nút "Tạo trang chủ mẫu": nonce + `edit_saha_builder` + `manage_options` (đổi trang chủ của site là thiết lập toàn site).
- Tài liệu mẫu đi qua `LayoutService::save()` → Sanitizer như mọi lần lưu; không ghi thẳng meta.
- QA chỉ đọc; kiểm tra shortcode Flatsome giới hạn 200 bài/trang mới nhất.

## 8. Testing

### Tự động

| Công cụ | Kết quả |
|---|---|
| `php tests/smoke.php` | **181 passed** (+7: trang chủ mẫu hợp lệ, 1 H1, không shortcode, 9 section; thiếu term → bỏ đúng khối; CTA báo giá là `<button data-saha-open-quote>`; CTA báo giá không cache, CTA link có cache) |
| `npm run test:js` | **40 passed** · `lint:js`, `lint:css` sạch |
| `wp saha qa` | **89 đạt · 3 cảnh báo · 0 lỗi** (cảnh báo môi trường local: chưa có plugin SEO, chưa nhập Zalo, chưa có Redis) |
| `php tests/http-smoke.php <local> --write` | **42 passed · 0 failed · 2 skipped** (+1: không có shortcode thô; skip: robots.txt ở thư mục con, chưa có ảnh hero) |

Đã thử cả trường hợp lỗi của kiểm tra mới: trang có `[ux_banner]` → cảnh báo kèm tên trang; template `woocommerce/single-product/price.php` `@version 1.0.0` → "Cần cập nhật: single-product/price.php (1.0.0 < 3.0.0)".

### Checklist SCC

Đưa vào `docs/QA.md` (mục SCC) bằng `python tests/build-qa-checklist.py`.

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | Kích hoạt SAHA Core, SAHA Builder, SAHA Theme rồi chạy `wp saha qa` | 0 lỗi; "Theme SAHA Theme đang bật" |
| 2 | Theme Options: đổi màu chính, font chữ, số cột shop | toàn site đổi (nút, giá, tab sản phẩm, header, footer, thanh toán) — không sửa CSS |
| 3 | Tắt plugin SAHA Builder, mở trang chủ và một trang dựng bằng builder | HTML giống hệt khi bật; bật lại sửa tiếp được |
| 4 | Dựng trang mới: Section › Hàng › Cột › Tiêu đề, Văn bản, Nút, Ảnh → Lưu → Xem trang | frontend giống canvas |
| 5 | Undo/redo, copy/paste, nhân đôi, xem theo thiết bị | đúng thao tác; giá trị tablet/mobile kế thừa desktop khi để trống |
| 6 | Editor (không `unfiltered_html`) lưu element HTML có `<script>` | script bị lọc |
| 7 | Hai tài khoản cùng mở một trang trong builder, cả hai bấm Lưu | người sau bị báo đang có người sửa / tải lại bản mới; không ai bị ghi đè |
| 8 | Lưu link `javascript:` hoặc dữ liệu sai | không lưu; lỗi chỉ đúng ô |
| 9 | Gọi REST `/builder/*` khi chưa đăng nhập | 401/403 |
| 10 | Mở trang ngoài frontend | không nạp JS/CSS của ứng dụng builder |
| 11 | Sửa một Block đang dùng ở 2 trang | cả hai trang cập nhật |
| 12 | Block chèn chính nó (trực tiếp/gián tiếp) | không treo; editor báo lỗi vòng lặp |
| 13 | SAHA → Header & Footer → Tạo mặc định | header/footer builder "✓ Đang dùng" |
| 14 | Header: dính khi cuộn; mobile ☰ mở off-canvas; Tab/Esc/focus | `aria-expanded`, `aria-controls` đúng; Esc đóng, focus về ☰ |
| 15 | Đăng nhập editor | không thấy Header & Footer |
| 16 | Trang → "Tạo trang chủ mẫu (SAHA Builder)" | mở builder; trang chủ 14 khối, đúng 1 H1, không shortcode |
| 17 | Tạo trang chủ mẫu trên site thiếu danh mục "pu-foam" | vẫn tạo được, khối PU Foam bị bỏ |
| 18 | CTA "Mở form báo giá" trên trang chủ | modal báo giá mở tại chỗ; gửi được |
| 19 | Tìm "243" | tới thẳng trang Keo khoá ren Loctite 243 |
| 20 | Bật catalogue: trang sản phẩm, shop, gọi `?add-to-cart=ID` | giá "Liên hệ báo giá"; không có nút giỏ; không thêm được vào giỏ |
| 21 | Tắt catalogue, bật COD: Mua ngay sản phẩm đơn giản → đặt đơn | tới thẳng Checkout; "Đơn hàng đã nhận" |
| 22 | Mua ngay sản phẩm biến thể (chọn thuộc tính) | Checkout đúng biến thể, đúng giá |
| 23 | Cart/Checkout block, Tài khoản (đăng nhập, đơn hàng, địa chỉ) | màu theo Theme Options; mobile gọn |
| 24 | Trang sản phẩm có 3+ ảnh | ảnh chính, thumbnail, chuyển ảnh, zoom, lightbox |
| 25 | `wp saha qa` sau khi cập nhật WooCommerce | không có template override lỗi thời |
| 26 | Trang chủ, liên hệ, báo giá, thương hiệu | không còn shortcode thô `[saha_…]`/`[ux_…]`; QA không liệt kê trang dùng UX Builder |
| 27 | Ma trận 1920 · 1440 · 1366 · 1024 · 768 · 430 · 390 · 375 | không tràn ngang; CTA dính không che nội dung |
| 28 | Lighthouse mobile trang chủ, sản phẩm, thương hiệu (staging) | LCP < 2.5s, CLS < 0.1 |
| 29 | Chỉ dùng bàn phím: header, off-canvas, modal báo giá, builder | đi được hết, thấy focus |
| 30 | Chạy hết checklist | `debug.log` không có lỗi mới; Console sạch |

## 9. Installation

Cập nhật code → mở wp-admin bằng admin (saha-core 1.14.0). Trang chủ: **Trang → Tất cả trang → Tạo trang chủ mẫu (SAHA Builder)** hoặc `wp saha homepage --front`.

## 10. Acceptance criteria — MVP (TECHNICAL-DESIGN §12)

| # | Tiêu chí | Local | Còn lại |
|---|---|---|---|
| 1 | Trang chủ 14 khối dựng bằng builder, không shortcode | ✅ trang #83; QA "Trang chủ dựng bằng builder" + "Không còn trang dùng shortcode UX Builder" | chọn ảnh hero thật |
| 2 | Header/footer bằng builder, 3 thiết bị, sticky, off-canvas `aria-expanded`/`aria-controls` | ✅ (mốc 1.5) | |
| 3 | Đổi màu chính → toàn site đổi | ✅ (mốc 1.1, 1.6 dùng `--saha-*`) | |
| 4 | Tìm "243" → sản phẩm → báo giá **hoặc** giỏ → thanh toán | ✅ "243" chuyển thẳng tới Loctite 243; báo giá #12 (1.6); đơn #79 (1.6); Mua ngay biến thể → Checkout "Màu: Đen" | |
| 5 | Tắt `saha-builder` → trang vẫn đúng | ✅ HTML trang chủ + builder-demo giống hệt khi tắt/bật | |
| 6 | `wp saha qa --strict`; checklist §17; Lighthouse LCP < 2.5s, CLS < 0.1 | `qa` 0 lỗi, 3 cảnh báo môi trường (SEO plugin, Zalo, Redis) → `--strict` chưa đạt ở local; đo nhanh trên local (không throttle): LCP 0.3s, CLS 0, 27 request, 0 asset builder | **staging**: cài Rank Math/Yoast, nhập Zalo, bật Redis → `--strict`; Lighthouse; checklist SCC-01…30 + ma trận trình duyệt |

### Kết quả chạy trên local (01/10/2026)

- Trang chủ mẫu: 9 section, 1 H1, mọi tiêu đề khối H2; modal báo giá mở từ CTA; không tràn ngang ở **80 tổ hợp** (10 trang × 8 độ rộng ở mục 27).
- http-smoke: thêm "không có shortcode thô" — đạt.
- Mua ngay biến thể: chọn "Đen" → Checkout đúng biến thể, 99.000.
- Editor: tài liệu trang chủ #83 render ở chế độ canvas (phía server) không lỗi; nút "Tạo trang chủ mẫu" chỉ hiện với admin. **Mở trang chủ trong ứng dụng builder bằng trình duyệt cần anh/chị kiểm tra** (phiên đăng nhập của trình duyệt kiểm thử đã hết hạn).

### Lỗi phát hiện và đã sửa

- Trang chủ mẫu không lưu được trên site thiếu danh mục `keo-silicone`/thương hiệu `loctite` (control Term từ chối slug không tồn tại) → khối đó tự bỏ, spacer thừa được dọn.
- Tiêu đề có link ("Thương hiệu nổi bật") mang màu + gạch chân của link → giữ màu tiêu đề, gạch chân khi hover.
- CTA báo giá nằm trong subtree tĩnh sẽ bị render cache → lần sau không báo theme in modal → `isDynamic()`.
- `wp saha qa` vẫn coi Flatsome Child là theme chuẩn → cập nhật theo D4.

## 11. Ghi chú & giới hạn

- `docs/layouts/homepage.ux.txt` / `footer-block.ux.txt` chỉ còn cho `flatsome-child` (đã đóng băng).
- Trang khác của site thật (giới thiệu, chính sách…) dựng lại bằng builder khi chuyển nội dung (R12) — `wp saha qa` liệt kê trang còn shortcode UX Builder.
- Chưa có: E2E tự động (Playwright), multisite, kiểm tra nâng cấp từ bản cũ — ghi trong TECHNICAL-DESIGN §17, làm ở Phase 2.
- Dữ liệu thử trên local: trang chủ #83 (đang là trang chủ; trước đó site hiển thị bài viết mới nhất), sản phẩm biến thể #86 đã xoá sau khi thử, catalogue bật lại, COD tắt lại.
