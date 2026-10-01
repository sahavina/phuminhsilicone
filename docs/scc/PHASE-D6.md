# SCC — D6: Khối mẫu + Block đã lưu trong bảng "Thêm"

> Yêu cầu sau mốc 2.4: thêm mục danh sách các khối trong cột "Thêm" của builder, dạng lưới ô như danh sách element (chọn "Cả hai": khối mẫu dựng sẵn + block đã lưu). Mốc trước: [PHASE-2.5.md](PHASE-2.5.md). Giao diện cửa hàng: [PHASE-D4.md](PHASE-D4.md).

## 1. Goal

- Bảng **Thêm** có 3 mục: **Element** (như cũ) · **Khối mẫu** · **Block đã lưu**.
- **Khối mẫu**: section dựng sẵn — bấm để thêm (vị trí tính như khi thêm element theo lựa chọn hiện tại; không chọn gì → cuối trang), hoặc kéo vào canvas / cây Cấu trúc (cây Cấu trúc dùng cùng xử lý thả, chưa thử riêng). Chèn **bản sao**: sửa thoải mái, không ảnh hưởng trang khác.
- **Block đã lưu**: Block dùng chung đã tạo — chèn element "Block dùng chung" trỏ tới block (sửa block một nơi, mọi trang cùng đổi).
- Mỗi trang một H1: trang đã có H1 → H1 trong khối mẫu tự hạ thành H2.

## 2. Architecture

```
saha-core Builder\Patterns::all()            StoreKit::homepage() (9 section kiểu cửa hàng)
        │                                     Starter::homepage() (hero tìm kiếm, CTA cuối)
        │                                     + "Ảnh + nội dung (2 cột)"   + filter saha_builder_patterns
        ▼
Patterns::forClient() — mỗi khối qua Sanitizer (đủ ID, prop hợp lệ; khối lỗi bị bỏ)
        ▼
GET /saha/v1/builder/patterns (quyền edit_saha_builder)
        ▼
saha-builder Inserter: [Element | Khối mẫu | Block đã lưu]  (ARIA tablist, ←/→)
   bấm  → actions.insertCopy( node )            ┐ cloneWithNewIds, hạ H1 nếu trang đã có H1
   kéo  → drag.payload { kind: 'node', node } ──┘ Canvas / Navigator drop → insertCopy( node, vị trí )
   Block đã lưu → node { type: 'block', props: { blockId } } → insertCopy
```

| Khối mẫu | Nhóm | Nguồn |
|---|---|---|
| Hero + ảnh trượt · Danh mục sản phẩm · Sản phẩm nổi bật (tab) · Ba ô quảng bá · Vì sao chọn chúng tôi · Giải pháp theo nhu cầu · Thương hiệu · Tin tức + khách hàng nói · Câu hỏi thường gặp | Kiểu cửa hàng | `StoreKit::homepage()` theo thứ tự section |
| Hero + ô tìm kiếm · Kêu gọi báo giá | Cơ bản | section đầu / cuối của `Starter::homepage()` |
| Ảnh + nội dung (2 cột) | Cơ bản | `Patterns::mediaText()` |

| Quyết định | Lý do |
|---|---|
| Khối mẫu lấy **từ chính bộ mẫu trang chủ** (StoreKit, Starter), không viết lại | Một nguồn: chỉnh mẫu trang chủ thì khối mẫu đổi theo; dữ liệu động (ảnh sản phẩm mới, link shop, danh mục) tính lúc mở editor. |
| Chuẩn hoá qua Sanitizer ở server | Editor nhận node đúng schema (giống dữ liệu đã lưu); khối không hợp lệ không bao giờ tới editor. `wp saha qa` cảnh báo khi có khối bị loại. |
| Chèn bản sao, không liên kết | Khác Block dùng chung: khối mẫu là điểm bắt đầu để sửa riêng từng trang. |
| Hạ H1 → H2 theo **thẻ thực tế** (prop hoặc mặc định của control `tag` / `titleTag`) | Tiêu đề, Tiêu đề khối, Banner, Tiêu đề danh sách / bài viết đều có thể là H1 — chỉ xét element "Tiêu đề" thì sót (đã gặp khi thử: Banner H1). |
| Header / footer: mục Khối mẫu báo không dùng được | Section không đặt được trong header/footer (theo `placeableTypes`). |

## 3. Files

- saha-core: `includes/Builder/Patterns.php` (mới), `includes/Builder/Rest/BuilderController.php` (route), `includes/class-qa.php` (khối mẫu hợp lệ; route `/builder/patterns`, `/quote/list`), icon `clipboard` cho 2 element danh sách báo giá (icon cũ không phải dashicon). SAHA Core **1.25.0**.
- saha-builder: `src/builder/panels/Inserter.js` (3 mục), `src/builder/actions.js` (`insertCopy`), `src/builder/store/headings.js` (mới: `hasH1`, `demoteH1`), `src/builder/api.js` (`patterns()`), `src/builder/canvas/Canvas.js`, `src/builder/panels/Navigator.js` (thả payload `node`), `src/builder/editor.scss`, `build/*`. SAHA Builder **0.6.0**.
- Test: `src/builder/store/test/headings.test.js`, `tests/http-smoke.php` (`/builder/patterns` chưa đăng nhập → 401).

## 4. Accessibility

- 3 mục là `role="tablist"` / `tab` (`aria-selected`, roving `tabindex`, phím ←/→), nội dung `role="tabpanel"`.
- Ô khối mẫu là `<button>` (bấm bằng bàn phím được, không bắt buộc kéo thả); mô tả khối ở `title`.

## 5. Tests

| Kiểm tra | Kết quả |
|---|---|
| `npm run test:js` (thêm 5 test `hasH1` / `demoteH1`: prop, mặc định, Banner `titleTag`, lồng sâu) | 45 passed |
| `npm run lint:js`, `lint:css`, `build` | đạt |
| `tests/smoke.php` / `tests/http-smoke.php` | 247 passed / 36 passed, 2 skipped |
| `wp saha qa` | 95 đạt (thêm: "Khối mẫu hợp lệ — 12 khối", 2 route), 0 lỗi |
| Trình duyệt — trang thử #121 (không lưu) | mục Khối mẫu: 12 ô, 2 nhóm; bấm "Hero + ảnh trượt" → section thêm cuối trang, được chọn, H1 vẫn 1 (khối mẫu hạ H2); "Hero + ô tìm kiếm" cũng hạ H2; kéo "Câu hỏi thường gặp" lên đầu canvas → chèn đúng vị trí |
| Trình duyệt — Block đã lưu | 3 block hiện thành ô; bấm "CTA báo giá (dùng chung)" → element Block dùng chung `blockId` 52, canvas hiện nội dung block |
| Trình duyệt — header #128 | Khối mẫu: thông báo không dùng trong header/footer; Block đã lưu vẫn dùng được |

Sự cố khi làm: chạy `wp-scripts lint-js --fix` không kèm đường dẫn đã tự sửa định dạng **toàn bộ** JS trong repo (cả saha-core, theme, flatsome-child, `webpack.config.js`). Đã khôi phục mọi file ngoài phạm vi bằng `git checkout` (cây làm việc sạch trước đó), build lại và đối chiếu: chỉ còn file của mục này thay đổi.

## 6. Limitations / tiếp theo

- Ô khối mẫu là icon + tên (chưa có ảnh xem trước); mô tả hiện khi rê chuột.
- Khối mẫu "Tin tức + khách hàng nói" có đánh giá **mẫu** — cần thay bằng đánh giá thật trước khi xuất bản (như trang chủ kiểu cửa hàng).
- Thêm khối riêng: filter `saha_builder_patterns` (xem DEV-GUIDE).
- Mốc tiếp: **2.6** Import/Export.
