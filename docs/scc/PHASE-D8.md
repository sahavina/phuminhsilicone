# SCC — D8: Đổi kích thước Hộp (Container)

> Yêu cầu: "Tạo thêm chức năng thay đổi kích thước container" — chọn: **thiết lập + tay kéo trên canvas**, chỉ cho element **Hộp**. Mốc trước: [PHASE-2.8.md](PHASE-2.8.md).

## 1. Chức năng

- Hộp → tab **Kiểu**: **Độ rộng** (%, px, vw), **Chiều cao tối thiểu** (px, vh), **Chiều cao cố định** (px, vh), **Phần tràn** (hiện / cắt / cuộn) — tất cả theo từng thiết bị.
- Canvas: chọn một Hộp → 2 tay kéo màu xanh: **giữa cạnh phải** (độ rộng) và **giữa cạnh dưới** (chiều cao tối thiểu).
  - Kéo: Hộp đổi kích thước ngay, nhãn nhỏ hiện giá trị (vd `48.5%`, `320px`).
  - Thả: lưu vào **thiết bị đang xem** (desktop / tablet / mobile) — một bước Hoàn tác (Ctrl+Z).
  - Đơn vị giữ theo giá trị đang có; chưa có → độ rộng tính **% của vùng element cha**, chiều cao **px**. Độ rộng không vượt vùng cha, tối thiểu 20px.
  - **Bấm đúp** tay kéo: bỏ giá trị của thiết bị đang xem (quay về kế thừa / mặc định).
- Chiều cao kéo là **tối thiểu** (nội dung dài hơn vẫn hiện đủ); muốn cố định dùng ô "Chiều cao cố định" + "Phần tràn".

## 2. Thay đổi

| Phần | Nội dung |
|---|---|
| `saha-core/includes/Builder/Elements/Container.php` | control `width`, `minHeight`, `height`, `overflow` (responsive) + CSS |
| `saha-builder/src/builder/canvas/Canvas.js` | tay kéo (`RESIZABLE = { container: { x: 'width', y: 'minHeight' } }` — thêm element khác bằng một dòng), xem trước bằng style inline, thả → `UPDATE` qua `setAt( prop, device, value )`, style inline gỡ khi CSS mới từ server về; bấm vào tay kéo không đổi lựa chọn |
| `saha-builder/src/builder/canvas/resize.js` (mới) | `resizeUnit`, `resizeValue` — thuần, có test |
| `saha-builder/src/builder/canvas/canvas-ui.js` | kiểu tay kéo + nhãn kích thước |

SAHA Core **1.30.0**, SAHA Builder **0.8.0**.

## 3. Tests

| Kiểm tra | Kết quả |
|---|---|
| `npm run test:js` (thêm 4: chọn đơn vị, % theo vùng cha + chặn 100%, px tối thiểu / vw, chiều cao px / vh) | 53 passed |
| `tests/smoke.php` (thêm 2: 4 thiết lập hợp lệ ra CSS đúng thiết bị; đơn vị sai / giá trị lạ bị từ chối) | 262 passed |
| lint / build / http-smoke / `wp saha qa` | đạt |
| Trình duyệt — trang chủ #132 (không lưu) | tay kéo nằm đúng giữa cạnh phải / dưới; kéo trái 200px → nhãn `84.4%` lúc kéo, thả → Hộp 638 → 439px theo CSS server, ô "Độ rộng" = `68.7%`, vẫn chọn Hộp; Ctrl+Z trả lại; kéo cạnh dưới +100px → cao 151px; ở chế độ Mobile kéo → ô "Độ rộng" mobile `69.5%`, desktop vẫn trống; bấm đúp → xoá giá trị |

## 4. Giới hạn

- Tay kéo dùng chuột / bút / cảm ứng; bàn phím dùng các ô ở tab Kiểu.
- Chưa có tay kéo cho Cột / Section (chọn "chỉ Hộp") — thêm vào `RESIZABLE` khi cần.
