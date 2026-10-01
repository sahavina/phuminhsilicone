# SCC — Giao diện theo mẫu, bước D2: header kiểu cửa hàng + nút nổi

> Kế hoạch D1–D4 và phân tích mẫu: [PHASE-D1.md](PHASE-D1.md) mục 2. Mốc trước: D1.

## 1. Goal

Header như mẫu: thanh chữ chạy · logo, ô tìm kiếm lớn có nút màu nhấn, hotline (icon tròn + nhãn + số đậm), tài khoản, giỏ · thanh menu màu phụ có nút **"Danh mục sản phẩm"** mở danh sách dọc và menu chữ in hoa có mũi tên. Nút liên hệ nổi + lên đầu trang.

Nghiệm thu: **"Tạo header kiểu cửa hàng" ra header giống mẫu, mobile gọn, bàn phím dùng được.**

## 2. Architecture

| Thành phần | Thay đổi |
|---|---|
| Element **Tìm kiếm** | kiểu "Ô liền nút": form riêng (label ẩn, ô + nút icon/chữ màu nhấn, `post_type=product`); kiểu cũ giữ làm mặc định |
| Element **Liên hệ** | kiểu "Xếp chồng": icon trong vòng tròn + nhãn nhỏ + số đậm |
| Element **Menu** | chữ in hoa, mũi tên ở mục có menu con; màu chữ chỉ áp **cấp 1** |
| Element **Nút danh mục sản phẩm** (mới) | `<a href=shop role=button aria-expanded aria-controls>` + `<nav hidden>`; nguồn: danh mục cấp 1 (thứ tự kéo-thả WooCommerce, bỏ "Uncategorized") + con bật ra bên phải, hoặc một menu WordPress |
| **Hàng header** | màu chữ không còn tô link trong menu con / mega / danh mục (`:not(:where(…))`) |
| Theme Options → **Nút nổi** (mới) | nút liên hệ (Gọi · Zalo · Yêu cầu báo giá), lên đầu trang, trái/phải, ẩn trên điện thoại (mặc định) |
| `Templates\Defaults::headerStore()` | mẫu header; nút **Tạo header kiểu cửa hàng** ở SAHA → Header, Footer & Templates |

| Quyết định | Lý do |
|---|---|
| Nút danh mục là link tới shop, JS biến thành nút mở bảng | Không JS vẫn tới được danh sách sản phẩm |
| Nút nổi mặc định ẩn trên điện thoại | saha-theme đã có thanh liên hệ dính ở chân màn hình; hai thứ chồng nhau che nội dung |
| Nút nổi thuộc saha-core (Theme Options), không phải element builder | Hiện ở mọi trang kể cả trang không dựng bằng builder; bật/tắt một chỗ |
| Màu chữ hàng header / element Menu không áp vào bảng thả xuống | Bảng có nền trắng — lỗi phát hiện khi thử: chữ trắng trên nền trắng |

## 3. Files (saha-core 1.18.0)

`includes/Builder/Elements/CategoryMenu.php` (mới), `Search.php`, `Contact.php`, `NavMenu.php`, `HeaderRow.php`, `ElementRegistry.php` (60 element); `includes/ThemeOptions/FloatingActions.php` (mới), `Schema.php` (nhóm `floating`), `Module.php`; `includes/Templates/Defaults.php`, `AdminScreen.php`; `public/assets/js/elements.js` (nút danh mục), `public/assets/js/floating.js`, `public/assets/css/floating.css` (mới), `builder.css`, `mega-menu.css`.

## 4. Database

Theme Options thêm `floating.{contact, back_to_top, position, mobile}`.

## 5. Code

Bật mega menu trên thanh menu mới vẫn chạy (D2 kiểm cùng mega "Sản phẩm", "Thương hiệu" của mốc 2.1).

## 6. Hooks

Không thêm hook. Nút nổi gọi `saha_quote_modal_needed` để theme in modal báo giá.

## 7. Security

Form tìm kiếm escape từ khoá đang tìm; link `tel:` qua `esc_url` với giao thức `tel`; Zalo `rel="noopener"`; không dữ liệu người dùng nào được ghi.

## 8. Testing

Tự động: `php tests/smoke.php` **216 passed** (+6: tìm kiếm ô liền nút, liên hệ xếp chồng, màu hàng header không tô bảng thả xuống, màu Menu chỉ cấp 1, header kiểu cửa hàng hợp lệ, nút danh mục aria khớp bảng); test JS 40 · lint sạch · http-smoke 33/0 · `wp saha qa` 91 đạt / 0 lỗi.

Đã thử trên trình duyệt (local, header #123 đang dùng cho toàn site):

- [x] Desktop: chữ chạy · logo · ô tìm kiếm liền nút · "Hotline tư vấn 0966.75.3382" · tài khoản · thanh menu với nút "Danh mục sản phẩm" + menu chữ in hoa có mũi tên.
- [x] Nút danh mục: mở 8 danh mục, "Keo Silicone" bật ra 4 danh mục con khi focus; Esc đóng; bấm ra ngoài đóng.
- [x] Mega "Sản phẩm" trên thanh menu mới: chữ tối trên nền trắng, tiêu đề cột không có mũi tên.
- [x] Nút nổi: mở danh sách (Gọi, Yêu cầu báo giá — chưa nhập Zalo nên không có Zalo), focus vào mục đầu, Esc đóng và trả focus; nút lên đầu trang ẩn khi chưa cuộn.
- [x] Mobile 375: ☰ · logo + ô tìm kiếm; nút nổi ẩn (thanh liên hệ dính của theme); không tràn ngang.

### Lỗi phát hiện và đã sửa

- Danh sách danh mục, menu con, mega trong thanh menu màu tối: chữ trắng trên nền trắng (màu của hàng header và của element Menu áp cho mọi link) → chỉ áp cho link cấp 1 / ngoài bảng thả xuống.
- `builder.css` bị lẫn CRLF khi nối file tạm tạo trên Windows → chuẩn hoá LF, rà toàn repo không còn CR.

## 9. Installation

SAHA → Header, Footer & Templates → **Tạo header kiểu cửa hàng** → sửa trong builder → **Dùng cho toàn site**. Giao diện → SAHA Theme Options → **Nút nổi**.

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Header giống mẫu (3 tầng desktop) | ✅ |
| Nút danh mục sản phẩm | ✅ bàn phím + chuột |
| Nút liên hệ nổi + lên đầu trang | ✅ |
| Mobile gọn | ✅ |

## 11. Ghi chú & giới hạn

- Dữ liệu thử local: header #123 "Header kiểu cửa hàng" đang dùng (header #74 vẫn còn, bỏ "toàn site"); Theme Options bật nút liên hệ + lên đầu trang.
- Màu nhấn đang là màu Theme Options local (đỏ); bộ màu mẫu áp ở D4.
