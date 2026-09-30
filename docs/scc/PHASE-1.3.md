# SCC Phase 1 — Mốc 1.3: Ứng dụng SAHA Builder

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §1.3, §15. Mốc trước: [PHASE-1.2.md](PHASE-1.2.md).

## 1. Goal

Giao diện soạn thảo trực quan cho runtime ở mốc 1.2:

- Màn hình toàn trang: thanh công cụ, bảng **Thêm** / **Cấu trúc** (trái), **canvas** (giữa), **Thiết lập** (phải).
- Canvas là iframe nạp **CSS thật** của website; HTML do **cùng renderer PHP** với frontend sinh ra.
- Kéo thả (từ bảng Thêm, trong canvas, trong Cấu trúc) + bấm để thêm; tự bọc Section/Hàng khi cần.
- Bảng thiết lập **tự dựng từ định nghĩa control của server** (16 loại control).
- Chuyển thiết bị desktop / tablet / mobile — sửa giá trị riêng từng thiết bị.
- Hoàn tác / làm lại (100 bước), sao chép / dán / nhân bản, phím tắt.
- Khoá chỉnh sửa (một người sửa một lúc), chống ghi đè, cảnh báo rời trang khi chưa lưu.

Nghiệm thu: **dựng được trang bằng Section / Row / Column / Heading / Text / Button / Image.**

## 2. Architecture

```
wp-admin/admin.php?page=saha-builder&post={id}        (saha-builder, React)
┌ Toolbar: thoát · tiêu đề · desktop/tablet/mobile · undo/redo · xem trang · Lưu · ⋯ ┐
│ Thêm | Cấu trúc │            Canvas (iframe)             │ Thiết lập (tab)       │
│  kéo / bấm      │  {permalink}?saha_builder_canvas={id}  │ Nội dung/Kiểu/Nâng cao│
└─────────────────┴────────────────────────────────────────┴───────────────────────┘
        │ state: reducer thuần (tree.js + reducer.js, có unit test)
        ├─ POST /builder/render  mỗi section cấp gốc, chỉ khi JSON của section đó đổi
        │                        (đợi 250 ms sau lần gõ cuối; section mới render ngay)
        ├─ POST /builder/save    {postId, data, baseHash}
        └─ POST /builder/lock    mỗi 60 giây
```

### Quyết định

| # | Quyết định | Lý do |
|---|---|---|
| 1 | **Canvas render bằng PHP** (REST `/builder/render`) thay vì vẽ lại bằng React | Canvas và frontend dùng CÙNG renderer + CÙNG CSS → không bao giờ lệch (rủi ro R3). Không phải viết lại 7 element bằng JS. Server kiểm giá trị ngay khi gõ → báo lỗi dưới field trước cả khi lưu. Đổi lại: xem trước trễ ~0,3–0,6 giây sau khi ngừng gõ. |
| 2 | Cache render theo section cấp gốc | Sửa một section chỉ gọi server cho section đó; trang dài không chậm dần. |
| 3 | iframe cùng origin, ứng dụng chèn HTML vào `#saha-canvas` | CSS theme/Theme Options/builder.css nạp thật qua `wp_head()`; breakpoint là media query thật theo độ rộng iframe (desktop 1280 thu nhỏ vừa khung, tablet 768, mobile 375). HTML chèn bằng `innerHTML` — script trong nội dung không chạy trong editor. |
| 4 | Kéo thả HTML5 gốc (không dùng dnd-kit như thiết kế) | Kéo xuyên ranh giới iframe cần sự kiện gốc của trình duyệt; không thêm thư viện. Payload kéo giữ trong module chung (dataTransfer không đọc được lúc dragover). |
| 5 | State bằng `useReducer` + hàm thuần (không dùng `@wordpress/data` như thiết kế) | Đơn giản, test được không cần DOM (35 test). Một nguồn state duy nhất cho cả app. |
| 6 | Rich text = TinyMCE cổ điển của WordPress (`wp_enqueue_editor`) | Người quản trị đã quen; không phải gõ HTML (spec §100). Server vẫn lọc `wp_kses_post`. |
| 7 | Unit test JS bằng **Vitest** (wp-scripts 36 bỏ Jest khỏi `test-unit-js`) | Thêm devDependency `vitest` 5.0.3 (khoá phiên bản). |

## 3. Files

### saha-builder 0.2.0

| File | Vai trò |
|---|---|
| `includes/Admin/BuilderScreen.php` | trang ẩn `admin.php?page=saha-builder&post=`, kiểm quyền trước khi in, toàn màn hình, cấu hình cho app (bảng màu Theme Options, nội dung cũ…) |
| `includes/Canvas.php` | `?saha_builder_canvas={id}&_wpnonce=` → trang tối giản có `wp_head()`, không admin bar, `X-Frame-Options: SAMEORIGIN`, noindex, không cache |
| `includes/Admin/EntryPoints.php` | link "Dựng/Sửa bằng SAHA Builder" ở danh sách Trang/Bài viết, admin bar, cảnh báo trong block editor |
| `src/builder/store/tree.js` | thao tác cây bất biến: chèn, xoá, di chuyển, bọc, nhân bản ID mới, quy tắc cha–con, vị trí chèn khi bấm |
| `src/builder/store/reducer.js` | lịch sử 100 bước, gộp các lần gõ liên tiếp (800 ms) thành 1 bước, chế độ chỉ xem |
| `src/builder/store/test/*` | 35 unit test (Vitest) |
| `src/builder/canvas/Canvas.js`, `drop.js`, `canvas-ui.js` | iframe, render theo section, chọn/hover, thanh công cụ nổi (kéo, lên/xuống, chọn cha, nhân bản, xoá), vạch chỉ báo thả |
| `src/builder/controls/*` | 16 control; `responsive.js` cùng quy tắc với `Responsive.php` |
| `src/builder/panels/Inserter.js`, `Navigator.js`, `Settings.js` | 3 bảng |
| `src/builder/App.js`, `Toolbar.js`, `actions.js`, `api.js`, `context.js` | tải, lưu, khoá, phím tắt, thông báo |

### saha-core 1.10.0

| File | Thay đổi |
|---|---|
| `Builder/Frontend.php` | `enqueueBase()`; filter `saha_builder_enqueue_layout_css` (canvas tắt CSS đã lưu) |
| `Builder/CssRules.php` | selector hậu tố nhận dấu phẩy **chỉ trong ngoặc** (`:where(h1, h2)`) |
| `Builder/Elements/Section.php`, `Column.php` | màu chữ áp cả cho h1–h6 bên trong |
| `public/assets/css/builder.css` | màu của nút có specificity class (theme không đè được) |

### Khác

`webpack.config.js` (entry `builder`), `package.json` (`vitest`), `.github/workflows/ci.yml` (chạy unit test JS), `tests/smoke.php` (+5), `tests/http-smoke.php` (+1).

## 4. Database

Không đổi. Dùng meta của mốc 1.2 và post lock của WordPress (`_edit_lock`).

## 5. Code

### Phím tắt

| Phím | Tác dụng |
|---|---|
| Ctrl+S | Lưu |
| Ctrl+Z / Ctrl+Shift+Z, Ctrl+Y | Hoàn tác / làm lại |
| Ctrl+C / Ctrl+V | Sao chép / dán element (qua `localStorage` → dán được sang trang khác) |
| Ctrl+D | Nhân bản |
| Delete / Backspace | Xoá element đang chọn |
| Esc | Bỏ chọn |

Phím tắt không chạy khi đang gõ trong ô nhập hoặc TinyMCE.

### Quy tắc thả

1. Sát mép element (≤ 10 px) và cha chứa được → trước/sau element.
2. Element là container chứa được → vào trong, vị trí theo các con (ngang/dọc theo bố cục thật — hàng xếp chồng ở mobile là dọc).
3. Cha chứa được → trước/sau theo nửa element.
4. Leo lên cha; hết → cấp gốc, tự bọc (`Tiêu đề` → Section › Tiêu đề; `Cột` → Section › Hàng › Cột).

Bấm vào element ở bảng Thêm: thêm vào element đang chọn nếu chứa được, ngược lại ngay sau nó; không chọn gì → cuối trang. Hàng mới có sẵn 2 cột.

### Lần đầu mở builder cho trang có nội dung

Nội dung cũ (block editor/classic) được đưa vào **Section › Văn bản** (render block, không chạy shortcode) → không mất gì. Chỉ thành layout khi bấm Lưu.

## 6. Hooks

| Hook | Loại | Mô tả |
|---|---|---|
| `saha_builder_enqueue_layout_css` | filter (saha-core) | `(bool, int $postId)` — false = không nạp CSS đã lưu của layout |

## 7. Security

- Màn hình builder: `edit_saha_builder` + `edit_post` + loại nội dung hỗ trợ — kiểm ở hook `load-*` (trước khi in gì). Chưa đăng nhập → WordPress chuyển tới đăng nhập (302).
- Canvas: nonce riêng theo post + cùng quyền; khách / nonce sai → 403 (có trong http-smoke). `X-Frame-Options: SAMEORIGIN`, `noindex`, `nocache_headers()`.
- Mọi ghi dữ liệu qua REST của saha-core (sanitize lại toàn bộ — mốc 1.2). Editor chỉ giúp nhập đúng.
- HTML trong canvas là output của renderer (đã escape/kses); chèn bằng `innerHTML` nên `<script>` trong nội dung không chạy. Link và form trong canvas bị chặn (không điều hướng, không submit).
- Khoá chỉnh sửa: người thứ hai mở builder → chế độ chỉ xem (đã thử). Lưu với `baseHash` cũ → 409, người dùng chọn tải lại hoặc ghi đè.
- CssRules: dấu phẩy cấp ngoài trong selector (sẽ tạo selector thoát phạm vi `.saha-e-{id}`) → bỏ cả luật (có test).

## 8. Testing

Tự động:

- `npm run test:js` — **35 passed** (cây: quy tắc cha–con, bọc, chèn/xoá/di chuyển bất biến, không thả vào chính mình, nhân bản ID mới, vị trí khi bấm thêm; reducer: undo/redo, gộp gõ, giới hạn 100 bước, chỉ xem, lưu, xoá lỗi khi sửa).
- `php tests/smoke.php` — **137 passed** (+5: selector có dấu phẩy, màu chữ section cho tiêu đề).
- `php tests/http-smoke.php` — canvas từ chối khách (403).
- `npm run lint:js`, `npm run lint:css` — 0 lỗi.

Thủ công:

- [ ] Danh sách Trang → "Dựng bằng SAHA Builder" → builder mở toàn màn hình, không có menu wp-admin.
- [ ] Trang trống: bấm Section → Hàng (có 2 cột) → chọn cột → Tiêu đề, Văn bản, Nút, Ảnh. Lưu. Xem trang: đúng bố cục.
- [ ] Kéo "Tiêu đề" từ bảng Thêm vào giữa hai element trong cột → vạch xanh đúng chỗ, thả đúng chỗ.
- [ ] Kéo "Văn bản" ra ngoài mọi section → tự tạo Section mới.
- [ ] Chọn element → tay nắm ⠿ trên thanh nổi → kéo sang cột khác.
- [ ] Bảng Cấu trúc: kéo dòng lên/xuống; giữa dòng container = thả vào trong.
- [ ] Gõ tiêu đề → canvas đổi sau ~0,5 giây; Ctrl+Z hoàn tác cả đoạn vừa gõ trong một bước.
- [ ] Mobile: đặt cỡ chữ riêng → desktop không đổi; ô tablet/mobile trống hiện giá trị kế thừa mờ.
- [ ] Nhập cỡ chữ `abc` → lỗi đỏ dưới field + ⚠ trên tab, canvas giữ bản cũ; Lưu → bị chặn, chọn đúng element lỗi.
- [ ] Văn bản: thanh công cụ TinyMCE (đậm, nghiêng, link, danh sách); tab "Mã" sửa HTML.
- [ ] Ảnh: "Chọn ảnh" → Media Library → chọn → xem trước; đổi kích thước ảnh.
- [ ] Màu: bấm ô màu Theme Options → lưu `var(--saha-…)`; đổi màu trong Theme Options → trang đổi theo.
- [ ] Ctrl+C trên trang A, Ctrl+V trên trang B → dán được, ID mới.
- [ ] User B mở builder khi A đang sửa → thông báo "A đang chỉnh sửa", chỉ xem.
- [ ] Rời trang khi chưa lưu → trình duyệt hỏi.
- [ ] Mở trang dùng builder trong block editor → cảnh báo vàng + nút "Mở SAHA Builder".
- [ ] ⋯ → "Tắt SAHA Builder cho trang này" → xác nhận → trang hiển thị nội dung tĩnh; mở lại builder vẫn còn layout.
- [ ] Bàn phím: Tab qua thanh công cụ, bảng Thêm (Enter để thêm), Cấu trúc (Enter để chọn), thiết lập.

## 9. Installation

Cập nhật code → mở wp-admin bằng admin (saha-core 1.10.0 nâng cấp). `build/` đã commit — không cần Node trên server.

Máy dev sửa `src/`: `npm ci` (lần đầu, có `vitest`) → `npm run lint:js && npm run test:js && npm run build` → commit cả `build/`.

## 10. Acceptance criteria

- [x] Dựng trang mới hoàn toàn bằng giao diện (trang nháp "Trang thử SAHA Builder"): Section › Hàng › 2 Cột › Tiêu đề, Văn bản, Nút (link `/lien-he/`), Ảnh (chọn qua Media Library) → Lưu → frontend hiển thị đúng; admin bar có "Sửa bằng SAHA Builder"; frontend không nạp JS/CSS của ứng dụng.
- [x] Sửa trang có sẵn: đổi H1, rich text TinyMCE, cỡ chữ riêng mobile (22px) → frontend và file CSS khớp.
- [x] Kéo thả: vào cột, ra cấp gốc (tự bọc Section), di chuyển bằng tay nắm, sắp xếp trong Cấu trúc.
- [x] Undo/redo, chèn theo lựa chọn, lỗi validate trực tiếp, chặn lưu khi lỗi, khoá chỉnh sửa (chỉ xem), cảnh báo block editor, link ở danh sách Trang.
- [x] Hồi quy: `php -l` 0 lỗi · smoke 137/137 · test JS 35/35 · lint JS/SCSS 0 lỗi · `wp saha qa` 74 đạt / 0 lỗi · `http-smoke --write` 39 đạt / 0 lỗi · `debug.log` không lỗi PHP mới.

### Lỗi phát hiện và đã sửa

| # | Lỗi | Sửa |
|---|---|---|
| 1 | Chữ trên nút không thấy ở frontend (xanh trên xanh) | CSS nền bọc `:where()` (specificity 0) thua `a { color }` của theme → màu nút dùng selector class |
| 2 | Màu chữ Section không áp cho tiêu đề bên trong | theme đặt màu riêng cho h1–h6 → Section/Cột sinh thêm `:where(h1…h6)` (tiêu đề tự đặt màu vẫn thắng) |
| 3 | Lỗi validate còn hiện sau khi đã sửa field | reducer bỏ lỗi của field ngay khi field đó đổi |

## 11. Ghi chú & giới hạn

- **Xem trước trễ ~0,3–0,6 giây** (render qua server). Trên hosting chậm có thể lâu hơn — đo lại trên staging. Nếu cần, mốc sau thêm vẽ tạm phía client cho Heading/Text trong lúc chờ.
- Canvas desktop được thu nhỏ khi màn hình hẹp (bố cục vẫn đúng breakpoint desktop) — chữ có thể nhỏ khó đọc; phóng to trình duyệt hoặc xem ở chế độ tablet để sửa chi tiết.
- **Kéo thả cần chuột**; người dùng bàn phím dùng bấm-để-thêm, nút lên/xuống, chọn cha, sao chép/dán.
- Thanh công cụ nổi trên canvas có thể che nội dung sát mép trên của element nhỏ.
- Chưa có: sửa chữ trực tiếp trên canvas, thư viện mẫu (template), xem lịch sử phiên bản trong builder (dùng Revisions của WordPress), giữ khoá thủ công ("tiếp quản").
- Trình duyệt chặn `localStorage` → sao chép/dán giữa các trang không hoạt động (các chức năng khác bình thường).
- Dữ liệu thử trên local: trang `builder-demo` (đã sửa trong lúc test) và trang nháp "Trang thử SAHA Builder" (ID 50) — xoá được.
