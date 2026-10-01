# SCC — Giao diện theo mẫu, bước D1: nền giao diện + 5 element mới

> Yêu cầu: dựng giao diện giống mẫu ketsat24.webme.vn ("SafeHome", Flatsome) trên SAHA Builder, với nội dung/thương hiệu SAHA.
> Phân tích mẫu và kế hoạch D1–D4: mục 2. Mốc trước: [PHASE-2.2.md](PHASE-2.2.md). Chen trước mốc 2.3 của [PHASE-2-PLAN.md](PHASE-2-PLAN.md).

## 1. Goal

- Theme Options: **font web** (Google Fonts, có dấu tiếng Việt) + màu **Nền phụ**, **Chữ phụ**.
- Element mới cho các khối của mẫu: **Tiêu đề khối** (gạch nhấn + "Xem tất cả →"), **Chữ chạy**, **Danh sách icon**, **Slider/Slide**, **Đánh giá khách hàng**, **Accordion / Hỏi đáp** (dữ liệu FAQ cho Google); **Hộp icon** thêm kiểu thẻ.

Nghiệm thu: mọi khối "còn thiếu element" trong bảng phân tích (trừ phần header, thẻ sản phẩm — D2, D3) dựng được bằng builder.

## 2. Phân tích mẫu & kế hoạch

Mẫu: header 3 tầng (chữ chạy · logo/tìm kiếm/hotline/giỏ · nút "Danh mục sản phẩm" + menu), hero navy (dòng nhỏ vàng, H1, danh sách ✓, 2 nút, slider ảnh), danh mục, sản phẩm nổi bật có tab lọc, 3 banner, "Vì sao chọn" (thẻ icon), giải pháp theo nhu cầu (ô ảnh), thương hiệu (logo), tin tức + đánh giá khách hàng, hỏi đáp, footer 4 cột, nút liên hệ nổi.
Màu: navy `#0E1F3A`/`#13294B`, vàng đồng `#D4A33B`, chữ `#14213A`, nền phụ `#F4F6FA`. Font mẫu SVN‑MarlinSansSQ (thương mại) → thay bằng Be Vietnam Pro.

| Bước | Nội dung |
|---|---|
| **D1** | font web, màu, 5 element + kiểu thẻ Hộp icon (mốc này) |
| D2 | header theo mẫu: chữ chạy, tìm kiếm nút màu nhấn, hotline icon, nút "Danh mục sản phẩm" mở menu dọc, nút liên hệ nổi + lên đầu trang |
| D3 | thẻ sản phẩm kiểu mới (nhãn %, nút giỏ nổi, thông số; "đã bán" chỉ khi có số thật, mặc định tắt), tab lọc danh mục cho element Sản phẩm |
| D4 | trang chủ + footer theo mẫu bằng builder, đưa vào "Tạo trang chủ mẫu", QA |

Không sao chép logo, ảnh, chữ của mẫu.

## 3. Files (saha-core 1.17.0)

| File | Vai trò |
|---|---|
| `includes/ThemeOptions/WebFonts.php` | **mới** — một request Google Fonts cho mọi font web đang chọn, `display=swap`, preconnect `fonts.gstatic.com`; không chọn font web → không request ra ngoài |
| `includes/ThemeOptions/Schema.php` | 7 font web (Be Vietnam Pro, Montserrat, Inter, Roboto, Nunito, Open Sans, Lexend), màu `surface`, `muted` |
| `includes/Builder/Elements/SectionTitle.php` | Tiêu đề khối |
| `includes/Builder/Elements/Marquee.php` | Chữ chạy (CSS, không JS) |
| `includes/Builder/Elements/IconList.php` | Danh sách icon (`<ul>`) |
| `includes/Builder/Elements/Slider.php`, `Slide.php` | Slider (scroll-snap) + slide ảnh |
| `includes/Builder/Elements/Testimonials.php`, `Testimonial.php` | Khối + mục đánh giá |
| `includes/Builder/Elements/Accordion.php`, `AccordionItem.php` | Accordion `<details>` + FAQPage JSON-LD |
| `includes/Builder/Elements/IconBox.php` | Kiểu khối: không khung / thẻ có viền / thẻ có bóng |
| `public/assets/js/elements.js` | **mới** — nút, chấm, tự chạy của slider (~2 KB, defer) |
| `public/assets/css/builder.css` | CSS các element trên |
| `includes/Builder/Frontend.php` | nạp `elements.js` cùng CSS nền (HTML lấy từ render cache vẫn có JS) |
| `includes/class-cli.php` | `wp saha flush-cache` |

## 4. Database

Không đổi. Theme Options thêm `colors.surface`, `colors.muted`; `typography.*.fontFamily` nhận khoá font web.

## 5. Code

Element có con (giống Hàng → Cột): Slider → Slide, Đánh giá khách hàng → Một đánh giá, Accordion → Một mục hỏi đáp. Thêm khối cha từ bảng Thêm sẽ có sẵn 3 mục con; nhân đôi mục (Ctrl+D) để thêm.

Danh sách (chữ chạy, danh sách icon): mỗi dòng một mục trong ô văn bản.

## 6. Hooks

Không thêm hook. Script `saha-builder-elements` (handle) nạp cùng `saha-builder` CSS nền.

## 7. Security

- Mọi chữ escape khi in; nội dung trả lời accordion qua `wp_kses_post`; JSON-LD qua `wp_json_encode` với `JSON_HEX_TAG` (không thoát được khỏi `<script>`).
- Font web: chỉ tải từ `fonts.googleapis.com` khi admin chọn; ghi chú quyền riêng tư (IP khách gửi tới Google) — mặc định font hệ thống.
- Đánh giá khách hàng **không** in dữ liệu cấu trúc Review (Google không cho phép đánh giá tự đăng về chính doanh nghiệp).

## 8. Testing

Tự động:

- `php tests/smoke.php` — **210 passed** (+11: tách dòng có chữ "ễ", bản lặp aria-hidden, danh sách icon, tiêu đề khối + link, slider chỉ nhận slide + tự chạy chỉ ngoài editor, đánh giá + sao có nhãn + không schema, accordion cùng `name` + FAQPage, editor mở mọi mục + không schema, URL Google Fonts, chọn font web; số element = 59).
- `npm run test:js` 40 passed · lint sạch · `php tests/http-smoke.php` 33 passed / 0 failed · `wp saha qa` 91 đạt / 0 lỗi.

Đã thử trên trình duyệt (trang `/d1-element-thu/`, local): chữ chạy (dừng khi rê chuột), tiêu đề H1 + danh sách ✓, slider 3 ảnh (nút, chấm, nhãn "1 / 3"), 3 thẻ icon, 3 đánh giá có sao, hỏi đáp mở một mục + FAQPage; builder hiển thị đủ element mới. Font web: chọn Be Vietnam Pro cho tiêu đề → `<link>` Google Fonts + preconnect + biến `--saha-type-heading-font` (đã khôi phục như cũ).

### Lỗi phát hiện và đã sửa

- Chữ chạy hiện "n phí vận chuyển": `preg_split('/\R/')` không có cờ `u` coi byte `0x85` trong "ễ" (UTF‑8 `E1 BB 85`) là ký tự xuống dòng → dùng `/\R/u`. Đã rà các regex khác: chỉ `\R` bị ảnh hưởng.
- Sửa code element mà không đổi version → HTML cũ còn trong render cache → thêm `wp saha flush-cache`.

## 9. Installation

Cập nhật code (saha-core 1.17.0). Font: **Giao diện → SAHA Theme Options → Chữ** → chọn "Be Vietnam Pro (Google Fonts)" cho Nội dung/Tiêu đề.

## 10. Acceptance criteria

| Khối mẫu | Dựng bằng |
|---|---|
| Thanh chữ chạy | Chữ chạy |
| Hero: dòng nhỏ, H1, ✓, nút, ảnh trượt | Tiêu đề khối / Tiêu đề, Danh sách icon, Nút, Slider |
| Tiêu đề khối + "Xem tất cả" | Tiêu đề khối |
| Vì sao chọn | Hộp icon kiểu thẻ |
| Khách hàng nói | Đánh giá khách hàng |
| Câu hỏi thường gặp | Accordion / Hỏi đáp |

## 11. Ghi chú & giới hạn

- Font web chỉ tải khi chọn ở Theme Options; element builder chọn font web khác sẽ hiển thị bằng font dự phòng.
- Slider trong canvas builder không tự chạy (để sửa thấy slide); vuốt/cuộn ngang được.
- Dữ liệu thử local: trang #121 "D1 — element mới (thử)".
