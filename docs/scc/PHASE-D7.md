# SCC — D7: Bảng Cấu trúc dạng khối

> Yêu cầu sau D6: "Cấu trúc hiển thị theo các khối" — theo ảnh tham chiếu (cây dạng thẻ: Section có tên, thu gọn, ⚙, section ẩn có nền sọc, "+ Add to Section", "+ Add elements"). Mốc trước: [PHASE-2.6.md](PHASE-2.6.md), [PHASE-D6.md](PHASE-D6.md).

## 1. Goal

- Mỗi element là một **thẻ**; con lồng thụt vào trong, có đường dẫn bên trái.
- **Section mặc định thu gọn**, hiện tên dễ nhận: **tên tự đặt** (Nâng cao → "Tên trong Cấu trúc") hoặc **chữ đầu tiên bên trong** ("Section · Danh mục sản phẩm").
- **⚙** mở thiết lập; element **ẩn trên mọi thiết bị** → nền sọc + nhãn "Ẩn"; ẩn một phần → nhãn "Ẩn: mobile".
- **"+ Thêm vào Section / Hàng / …"** dưới khối đang mở và **"+ Thêm element"** cuối danh sách → chọn đúng chỗ rồi mở bảng Thêm.
- Chọn element trên canvas → nhánh chứa nó tự mở, dòng được cuộn tới. Thanh công cụ: Mở hết · Thu gọn hết · Bỏ chọn.

## 2. Thay đổi

| Phần | Nội dung |
|---|---|
| `saha-builder/src/builder/panels/Navigator.js` | dòng dạng thẻ (loại + mô tả + nhãn ẩn + ⚙), mặc định thu gọn cấp ngoài cùng, tự mở + cuộn tới element đang chọn, nút "+ Thêm vào …" (cấp 0–1, khối đang mở hoặc chưa có con) và "+ Thêm element"; kéo thả giữ nguyên |
| `saha-builder/src/builder/store/outline.js` (mới) | `nodeCaption` (tên tự đặt → chữ đầu tiên trong cây: title / text / content / label, bỏ HTML, rút gọn), `hasCustomLabel`, `hiddenState` |
| `saha-builder/src/builder/App.js` | Navigator nhận `onAdd` → chuyển sang tab Thêm |
| `saha-builder/src/builder/editor.scss` | kiểu thẻ, nền sọc, nhãn, nút thêm |
| `saha-core/includes/Builder/ElementRegistry.php` | control Nâng cao mới **`label`** ("Tên trong Cấu trúc", text ≤ 60) — chỉ dùng trong builder, **không in ra website** |

SAHA Core **1.27.0**, SAHA Builder **0.7.0**.

| Quyết định | Lý do |
|---|---|
| Tên tự đặt lưu ở `advanced.label` của node | Đi cùng tài liệu (Import / Export, copy / paste, khối mẫu giữ tên); qua Sanitizer như mọi control (bỏ thẻ HTML, giới hạn độ dài). |
| Không có tên → chữ đầu tiên bên trong | Section mẫu đã có tiêu đề khối → tên có sẵn, không bắt nhập. |
| ⚙ không vào thứ tự Tab (`tabIndex -1`, `aria-hidden`) | Cùng việc với bấm tên dòng — tránh hai điểm dừng giống nhau cho người dùng bàn phím / trình đọc màn hình. |
| "+ Thêm vào …" chỉ ở cấp 0–1 | Như ảnh tham chiếu (Section, Hàng); nhiều cấp hơn làm cây rối. Cấp sâu hơn vẫn thêm được: chọn element → bảng Thêm. |

## 3. Tests

| Kiểm tra | Kết quả |
|---|---|
| `npm run test:js` (thêm 4 test `outline`: chữ đầu tiên lồng sâu + bỏ HTML, tên tự đặt ưu tiên, rút gọn / rỗng, trạng thái ẩn) | 49 passed |
| `npm run lint:js`, `lint:css`, `build` (production) | đạt |
| `tests/smoke.php` / `http-smoke.php` / `wp saha qa` | 251 / 37 (2 skipped) / đạt |
| Sanitizer + Renderer với `advanced.label` | không lỗi, thẻ HTML bị bỏ, tên **không** có trong HTML website |
| Trình duyệt — trang chủ kiểu cửa hàng #132 (không lưu) | 11 section thu gọn có tên ("Danh mục sản phẩm", "Vì sao chọn SAHA?"…); mở section → "+ Thêm vào Section" chọn section + chuyển tab Thêm; chọn mục hỏi đáp cuối trang trên canvas → mở tab Cấu trúc thấy nhánh đã mở, dòng nằm trong vùng nhìn; đặt tên "Hero trang chủ" + bật ẩn 3 thiết bị → dòng hiện tên, nền sọc, nhãn "Ẩn" |

## 4. Giới hạn

- Section không có chữ nào bên trong (chỉ ảnh / khoảng trống) hiện tên loại — đặt "Tên trong Cấu trúc" để dễ nhận.
- Mốc tiếp: **2.7** element Phase 2.
