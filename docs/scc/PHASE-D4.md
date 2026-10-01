# SCC — Giao diện theo mẫu, bước D4: trang chủ + footer + bộ màu kiểu cửa hàng

> Kế hoạch D1–D4: [PHASE-D1.md](PHASE-D1.md) mục 2. Mốc trước: [PHASE-D3.md](PHASE-D3.md).

## 1. Goal

Toàn bộ giao diện theo mẫu ketsat24.webme.vn dựng bằng SAHA Builder với nội dung SAHA, áp dụng bằng **một nút** (hoặc một lệnh): header kiểu cửa hàng (D2) + trang chủ 9 section + footer 4 cột + bộ màu navy/vàng đồng + font Be Vietnam Pro + thẻ sản phẩm kiểu cửa hàng (D3) + nút nổi.

Nghiệm thu: **website local dựng hoàn toàn bằng builder, bố cục và phong cách như mẫu, QA đạt.**

## 2. Architecture

`Builder\StoreKit`:

| Hàm | Làm gì |
|---|---|
| `install( $front, $palette )` | tạo header (`Defaults::headerStore`), footer, trang chủ; dùng header/footer cho toàn site; đặt trang chủ; đổi Theme Options |
| `applyOptions()` / `restoreOptions()` | lưu Theme Options hiện tại vào `saha_theme_options_before_store` (một lần) rồi áp bộ màu, font, kiểu thẻ, nút nổi / khôi phục |
| `homepage()`, `footer()` | tài liệu builder |

Trang chủ (theo thứ tự mẫu):

| # | Khối | Element |
|---|---|---|
| 1 | Hero navy: dòng nhỏ vàng chữ hoa, H1 chữ hoa, mô tả, 3 dòng ✓, nút "Xem sản phẩm" (vàng) + "Nhận báo giá" (viền), slider ảnh sản phẩm | Văn bản, Tiêu đề, Danh sách icon, Hộp (ngang) + Nút, Slider |
| 2 | Danh mục sản phẩm (ảnh + tên + mô tả ngắn) | Tiêu đề khối, Danh mục sản phẩm (mô tả ngắn — mới) |
| 3 | Sản phẩm nổi bật, tab theo danh mục, 5 cột | Tiêu đề khối, Sản phẩm (tab) |
| 4 | 3 ô quảng bá nền navy, nút vàng | Banner (nút kiểu Nhấn — mới) |
| 5 | Vì sao chọn SAHA — 8 thẻ icon | Hộp icon kiểu thẻ |
| 6 | Giải pháp theo nhu cầu | Danh mục sản phẩm › Ứng dụng |
| 7 | Thương hiệu nổi bật | Danh mục sản phẩm › Thương hiệu |
| 8 | Kiến thức & kinh nghiệm (2×2) + Khách hàng nói (cột phải) | Bài viết, Đánh giá khách hàng |
| 9 | Câu hỏi thường gặp | Accordion + FAQPage |

Footer: logo + giới thiệu + mạng xã hội · Liên kết nhanh (menu chân trang) · Thông tin (chính sách, liên hệ, báo giá) · Liên hệ (hotline Bắc/Nam, email) · dòng bản quyền.

| Quyết định | Lý do |
|---|---|
| Đánh giá khách hàng và 2 câu trả lời FAQ là **nội dung mẫu ghi rõ "mẫu"** | Không đặt đánh giá/cam kết bịa vào website; admin thay bằng nội dung thật |
| Slider hero lấy ảnh đại diện sản phẩm, không lấy ảnh bất kỳ trong Thư viện; không có → hero một cột | Lần thử đầu slider lấy nhầm ảnh logo |
| Theme Options cũ được lưu trước khi đổi, có lệnh khôi phục | Áp bộ giao diện là thay đổi toàn site |
| Nút kiểu **Nhấn** (màu nhấn) cho Nút và Banner; nút **Viền** lấy viền theo màu chữ | Nút màu chính (navy) chìm trên nền navy; nút viền trắng trên nền tối không thấy viền |

## 3. Files

saha-core 1.20.0: `includes/Builder/StoreKit.php` (mới), `includes/Builder/Module.php`, `includes/class-cli.php` (`wp saha starter-store`), `includes/Builder/Elements/{Button,Banner,Slider,ProductCategories}.php`, `public/assets/css/builder.css`.

## 4. Database

Option `saha_theme_options_before_store` (bản Theme Options trước khi áp, không autoload). Không đổi schema.

## 5. Code

```bash
wp saha starter-store --front          # tạo + áp dụng, đặt trang chủ
wp saha starter-store --no-palette     # không đổi Theme Options
wp saha starter-store --restore-options
```

## 6. Hooks

Không thêm hook.

## 7. Security

Nút admin: nonce + `edit_saha_builder` + `manage_saha_templates` + `manage_options` + `edit_theme_options`, hộp xác nhận trước khi chạy. Mọi tài liệu qua `LayoutService::save()` (Sanitizer).

## 8. Testing

Tự động: smoke **226 passed** (+7: trang chủ hợp lệ/9 section/1 H1/không shortcode, không ảnh → không slider rỗng, có tab + 3 đánh giá ghi "mẫu", footer hợp lệ, nút Nhấn, slider 1 slide không điều khiển, bộ màu); test JS 40 · lint sạch · http-smoke 33/0 · `wp saha qa` 91 đạt / 0 lỗi / 3 cảnh báo môi trường.

Trên trình duyệt (local, `wp saha starter-store --front` → header #128, footer #130, trang chủ #132):

- [x] Trang chủ đủ 9 khối như mẫu; header 3 tầng; footer 4 cột; màu navy + vàng đồng; font Be Vietnam Pro.
- [x] Không tràn ngang: trang chủ, shop, sản phẩm, thương hiệu, chuyên mục × 1920/1366/1024/768/390/375; mỗi trang đúng 1 H1.
- [x] Đo nhanh trên local (không giả lập mạng chậm): LCP 0,65 s, CLS 0,0008, 55 request (13 file font của Google), HTML 149 KB.
- [x] Mobile 375: hero chữ hoa, nút vàng + viền, ô tìm kiếm; thanh liên hệ dính.

### Lỗi phát hiện và đã sửa

- Slider hero lấy ảnh mới nhất trong Thư viện (là logo) → ảnh đại diện sản phẩm.
- Slider một ảnh vẫn hiện nút trước/sau, chấm, tự chạy → chỉ khi ≥ 2 slide.
- Nút ô quảng bá (màu chính navy) chìm trên nền navy → kiểu Nhấn; nút Viền trắng không thấy viền → viền theo màu chữ.
- Footer: cột "Liên hệ" rớt dòng (tổng độ rộng cột > 100%) → đặt 30/20/20/30%.

## 9. Installation

**Trang → Tất cả trang → Áp dụng giao diện kiểu cửa hàng** (xác nhận) → mở trang chủ mới trong builder. Sau đó: thay ảnh hero (Slide), ảnh danh mục/thương hiệu/ứng dụng, đánh giá khách hàng thật, câu trả lời FAQ thật; Theme Options → nhập số Zalo.

## 10. Acceptance criteria

| Tiêu chí | Kết quả |
|---|---|
| Bố cục + phong cách như mẫu | ✅ 9 khối trang chủ, header, footer, bộ màu, font |
| Dựng hoàn toàn bằng builder, sửa được | ✅ không shortcode |
| QA | ✅ không tràn ngang, 1 H1, test tự động đạt |

## 11. Ghi chú & giới hạn

- Font Google: 13 file trên trang chủ (5 độ đậm × bộ ký tự). Có thể giảm độ đậm ở `ThemeOptions\WebFonts::WEIGHTS` nếu PageSpeed trên staging báo.
- HTML trang chủ 149 KB do 7 tab sản phẩm dựng sẵn (70 thẻ); giảm "Số tab" hoặc "Số sản phẩm" nếu cần.
- Ảnh danh mục/thương hiệu/ứng dụng trên local chưa có → thẻ chỉ có chữ.
- Dữ liệu local: header #128, footer #130 đang dùng; trang chủ #132; header/footer/trang chủ cũ vẫn còn. Khôi phục Theme Options: `wp saha starter-store --restore-options`.
