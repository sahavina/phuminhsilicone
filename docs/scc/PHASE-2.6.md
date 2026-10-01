# SCC Phase 2 — Mốc 2.6: Import / Export giao diện

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) (spec §83, §84; checklist bảo mật §15); kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.5.md](PHASE-2.5.md), [PHASE-D6.md](PHASE-D6.md).

## 1. Goal

- Xuất **trang dựng bằng builder, Block dùng chung, header / footer / template (kèm điều kiện hiển thị), Theme Options** và ảnh được dùng ra **một file JSON**.
- Nhập file đó ở site khác: kiểm schema + Sanitizer, ảnh tải về bằng `media_sideload_image`, chỉ `manage_options`.
- Không chuyển user, đơn hàng, sản phẩm, khách hàng / báo giá, menu (spec §84).

Nghiệm thu: **xuất site A → nhập site B ra cùng giao diện.**

## 2. Architecture

```
Exporter::build()                                   Importer::run()
  trang / template / block đã chọn                    validate (format, version, giới hạn) ──► lỗi → dừng
  + block được dùng tới (đệ quy)                      ảnh:  cùng site → giữ ID · đã nhập (meta _saha_import_source) → dùng lại
  Walker::collect → ID ảnh {id,size}, blockId               · còn lại media_sideload_image → map cũ→mới
  điều kiện template → refs (ID → slug)               block: tạo post → (sau khi đủ map) lưu layout
  Theme Options (+ logo)                              trang: tạo (mặc định nháp) → lưu layout → (tuỳ chọn) trang chủ
        │                                             template: (tuỳ chọn) template cùng loại → nháp; tạo → layout
        ▼                                                       → điều kiện: ID → slug → ID site nhận
  saha-export v1 (JSON)  ───────────────────────►     Theme Options: sao lưu → ThemeOptions\Repository::save
                                                      Templates::compile · MegaMenu::compile · Cache::bump
Mọi layout: Walker::remap (ảnh / block thiếu → bỏ giá trị, cảnh báo) → LayoutService::save → Sanitizer
```

| Thành phần | Vai trò |
|---|---|
| `ImportExport\Exporter` | danh sách xuất được, dựng gói, tên file |
| `ImportExport\Walker` | gom / đổi ID ảnh (`{id,size}` ở mọi độ sâu, kể cả responsive và `background.image`) và `blockId`; thuần PHP |
| `ImportExport\Importer` | kiểm tra, chạy thử, nhập, báo cáo (đã tạo / cảnh báo / lỗi) |
| `ImportExport\Module` + `admin/views/import-export.php` | **SAHA → Import / Export** (xuất: tải file; nhập: upload → báo cáo) |
| `Cli::export`, `Cli::import` | `wp saha export <file>`, `wp saha import <file> [--dry-run] …` |

| Quyết định | Lý do |
|---|---|
| Nhập luôn **tạo mới**, không ghi đè | Không mất trang / template đang có; nhập nhầm thì xoá bản mới là xong. |
| Trang nhập vào mặc định **nháp** | Không lộ trang chưa kiểm tra; tuỳ chọn "giữ trạng thái như file". |
| "Thay template đang dùng" = template cùng loại → **nháp** (không xoá) | Template không vào thùng rác được; chuyển nháp đảo lại được. Không chọn → template cũ (ID nhỏ hơn) vẫn thắng khi cùng điều kiện. |
| Điều kiện template đổi theo **slug** | ID trang / danh mục khác nhau giữa site; slug thường giống (cùng dữ liệu mẫu). Không tìm thấy → bỏ điều kiện đó + cảnh báo. |
| Term / menu trong element giữ nguyên | Controls\Term đã lưu bằng slug. |
| Ảnh hỏng / thiếu → bỏ giá trị, không bỏ cả layout | Controls\Media từ chối ID không phải ảnh → nếu giữ ID cũ cả trang bị loại. |
| Theme Options đi qua `Repository::save` | Sanitize từng trường, trường quyền riêng (Custom CSS cần `edit_css`) vẫn được kiểm; bản cũ ở `saha_theme_options_before_import`. |
| admin-post + WP-CLI thay cho REST `/export`, `/import` (thiết kế §9) | Tải file / upload multipart đơn giản hơn qua admin-post; cùng quyền `manage_options` + nonce. CLI dùng cho chuyển site hàng loạt và kiểm thử. |

## 3. Bảo mật (checklist §15)

- Quyền `manage_options` (màn hình, admin-post, CLI import); nonce cho cả xuất và nhập.
- File ≤ 10 MB, chỉ `.json`, `is_uploaded_file`; JSON độ sâu ≤ 512; ≤ 500 phần tử mỗi loại, ≤ 200 ảnh.
- Tiêu đề `sanitize_text_field`, slug `sanitize_title`, trạng thái chỉ publish / draft / private; layout qua Sanitizer; Theme Options qua sanitizer từng trường.
- Ảnh: chỉ URL http / https; `media_sideload_image` (WordPress kiểm mime) qua `wp_safe_remote_get` (chặn địa chỉ nội bộ khác site).

## 4. Files

- Mới: `saha-core/includes/ImportExport/{Exporter,Importer,Walker,Module}.php`, `admin/views/import-export.php`.
- Sửa: `includes/class-loader.php` (module), `includes/class-cli.php` (`export`, `import`). SAHA Core **1.26.0**.

## 5. Tests

| Kiểm tra | Kết quả |
|---|---|
| `tests/smoke.php` (thêm 4: gom ảnh/block kể cả responsive + nền, nhận dạng `{id,size}`, đổi ID + bỏ giá trị thiếu + cảnh báo, chạy thử bỏ ảnh/block) | 251 passed |
| `tests/http-smoke.php` (thêm: xuất khi chưa đăng nhập → 400, không có JSON) | 37 passed, 2 skipped |
| `wp saha qa` | đạt, 0 lỗi |
| CLI — xuất toàn bộ | 7 trang, 12 template, 2 block, 5 ảnh, Theme Options (225 KB) |
| CLI — nhập gói "site khác" (đổi `source`, 1 trang + 4 template) | tạo 5 mục, 0 cảnh báo; 2 ảnh tải lại thành attachment mới, layout trỏ ảnh mới (80 → 155); điều kiện `product_cat` / `product` / `page` đổi qua slug đúng; header / footer đang chạy vẫn là bản cũ (128 / 130) |
| CLI — nhập cùng site + Theme Options + "thay template" (404) | template 404 cũ → nháp, bản mới chạy; Theme Options giữ nguyên giá trị, bản sao lưu được ghi |
| Màn hình admin | hiện đủ danh sách; xuất (fetch POST) → 200, `Content-Disposition` đúng tên file, gói đúng mục đã chọn; nhập chạy thử qua form → báo cáo "chưa ghi gì"; file sai định dạng → thông báo lỗi |

Dọn dữ liệu thử trên local: trang nhập (#157) vào thùng rác; 5 template nhập (#159–#167) chuyển **nháp**, tên có tiền tố "[Thử import 2.6]" (template không vào thùng rác được — xoá hẳn nếu không cần); template 404 gốc (#115) xuất bản lại; 2 ảnh tải lại (#155, #156) còn trong Thư viện.

## 6. Limitations / tiếp theo

- Ảnh phải tải được từ site nguồn lúc nhập (site nguồn đang chạy, truy cập được từ máy chủ site nhận).
- Menu (và cài đặt mega menu gắn trên mục menu) không được chuyển — tạo menu cùng slug ở site nhận; element Menu tìm theo slug.
- Link tuyệt đối trong nội dung (ví dụ nút trỏ `https://site-a/...`) giữ nguyên — sửa trong builder nếu khác domain.
- Mốc tiếp: **khung Cấu trúc dạng khối** (yêu cầu mới), sau đó **2.7** element Phase 2.
