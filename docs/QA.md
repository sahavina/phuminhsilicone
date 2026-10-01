# QA — Tổng Kho Keo Dán SAHA

> File này được sinh bởi `tests/build-qa-checklist.py` từ mục 8 của các `docs/PHASE-*.md`.
> Không sửa tay — sửa ở PHASE-*.md rồi chạy lại script.

Tổng: **248 test thủ công**, trong đó **61** đã có công cụ tự động kiểm tra (một phần hoặc toàn bộ).

## 1. Công cụ tự động — chạy trước

| Công cụ | Chạy ở đâu | Phủ | Ghi dữ liệu? |
|---|---|---|---|
| `php tests/smoke.php` | máy dev, không cần WordPress | logic thuần: validate, sanitize, tokenizer, cache, robots, SEO; builder (schema, sanitize mọi control, render, CSS), Theme Options, header/footer, trang chủ mẫu, Mua ngay | Không |
| `wp saha qa` hoặc **SAHA → Kiểm tra hệ thống** | trên site | môi trường, bảng + index, quyền, cấu hình, taxonomy, REST route + permission_callback, builder (quyền, file CSS, header/footer, trang chủ, shortcode Flatsome còn sót), WooCommerce (catalogue thật sự chặn mua, template override lỗi thời), relevance tìm kiếm, robots, bảo mật upload/debug, cron, object cache | Không |
| `php tests/http-smoke.php <url>` | máy bất kỳ | REST từ ngoài vào, mã HTTP, robots.txt, noindex, no-cache, lỗi PHP lộ ra trang, số H1, shortcode thô, route builder từ chối khách | Không |
| `php tests/http-smoke.php <url> --write` | máy bất kỳ → **chỉ local/staging** | spec §57: thiếu nonce, email sai, product giả, chống trùng, rate limit, honeypot | Có — 2 báo giá + 1 lead "[Mẫu] QA…" |
| `wp saha seed --with-crm` | trên site **local/staging** | tạo dữ liệu mẫu để các test trên có dữ liệu | Có — gỡ bằng `wp saha unseed` |

Thứ tự khuyến nghị trên staging:

```bash
# chạy tại thư mục gốc WordPress (cũng là gốc repo)
wp saha seed --with-crm
wp saha homepage --front        # trang chủ mẫu dựng bằng SAHA Builder (saha-theme)
wp saha qa --strict
php tests/http-smoke.php https://staging.example.com --write
```

Cột **Tự động** bên dưới ghi công cụ đã phủ mục đó. Mục có công cụ vẫn nên xem lại bằng mắt ở lần QA đầu.

## 2. Ma trận thiết bị & trình duyệt (spec §54, §55)

Chạy các trang: Trang chủ · Danh mục · Thương hiệu · Sản phẩm · Tìm kiếm · Báo giá · Liên hệ · Bài viết · 404.

| Chiều rộng | Thiết bị gợi ý | Chrome | Edge | Firefox | Safari |
|---|---|---|---|---|---|
| 320px | iPhone SE (1st) | ☐ | — | — | ☐ |
| 375px | iPhone 12 mini | ☐ | — | — | ☐ |
| 390px | iPhone 14 | ☐ | — | — | ☐ |
| 768px | iPad | ☐ | ☐ | ☐ | ☐ |
| 1024px | iPad ngang / laptop nhỏ | ☐ | ☐ | ☐ | ☐ |
| 1366px | laptop | ☐ | ☐ | ☐ | ☐ |
| 1440px | desktop | ☐ | ☐ | ☐ | ☐ |
| 1920px | màn lớn | ☐ | ☐ | ☐ | — |

Mỗi ô: không tràn ngang, sticky CTA không che nội dung, chữ đọc được, nút bấm được bằng ngón tay (≥ 40px), bàn phím Tab đi hết các nút/ô nhập (spec §39).

## 3. Checklist theo phase

Kết quả: ☐ chưa chạy · ✅ đạt · ❌ lỗi (ghi chú + link issue) · ➖ không áp dụng.

### P1 — Foundation ([PHASE-1.md](PHASE-1.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P1-01 | Activate plugin | không fatal; 3 bảng `wp_saha_*` được tạo | qa | ☐ | |
| P1-02 | `SHOW INDEX FROM wp_saha_quotes` | có 5 index như thiết kế | qa | ☐ | |
| P1-03 | Deactivate | dữ liệu 3 bảng **còn nguyên**; role custom bị xoá |  | ☐ | |
| P1-04 | Activate lại | migration không chạy lại (option `saha_core_migrations_ran`) |  | ☐ | |
| P1-05 | Kích hoạt child theme | frontend không lỗi, không JS error |  | ☐ | |
| P1-06 | Tắt plugin, giữ child theme | site vẫn render, admin hiện notice thiếu dependency |  | ☐ | |
| P1-07 | Cấu hình → lưu hotline/Zalo | giá trị lưu đúng, cache option được xoá |  | ☐ | |
| P1-08 | Nhập `<script>alert(1)</script>` vào Tên công ty | bị sanitize, không thực thi |  | ☐ | |
| P1-09 | Nhập email sai định dạng | lưu rỗng thay vì rác |  | ☐ | |
| P1-10 | Login user role Sales | thấy menu SAHA, **không** thấy Cấu hình | qa | ☐ | |
| P1-11 | Truy cập trực tiếp `admin.php?page=saha-core-settings` bằng Sales | 403 |  | ☐ | |
| P1-12 | Upload file `.php` vào Media | bị từ chối | qa | ☐ | |
| P1-13 | Mobile 375px | sticky CTA hiện, không che footer (body có padding-bottom) |  | ☐ | |
| P1-14 | Desktop 1366px | sticky CTA ẩn |  | ☐ | |
| P1-15 | Click hotline | `dataLayer` nhận `saha_click_phone` |  | ☐ | |
| P1-16 | `[saha_hotline]` trong UX Builder | render số từ settings, `tel:` chỉ digits |  | ☐ | |
| P1-17 | Bật catalogue mode | giá đổi thành "Liên hệ báo giá", mất nút giỏ hàng |  | ☐ | |
| P1-18 | Tắt catalogue mode | giá và giỏ hàng trở lại |  | ☐ | |
| P1-19 | Trang 404 | có search box + danh mục, **không** redirect về homepage |  | ☐ | |
| P1-20 | Keyboard Tab qua sticky CTA | focus ring hiện rõ |  | ☐ | |

### P2 — Catalogue ([PHASE-2.md](PHASE-2.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P2-01 | Update plugin lên 1.1.0 | rewrite tự flush, không cần Save Permalinks thủ công |  | ☐ | |
| P2-02 | Products → Thương hiệu | thấy menu, thêm được term |  | ☐ | |
| P2-03 | Thêm thương hiệu + logo + banner | media picker mở, ảnh lưu đúng |  | ☐ | |
| P2-04 | Sửa thương hiệu | field hiện lại đúng giá trị đã lưu |  | ☐ | |
| P2-05 | Cột Logo ở danh sách term | hiện thumbnail 48px |  | ☐ | |
| P2-06 | Xem `/thuong-hieu/apollo/` | banner, logo, H1 "Apollo chính hãng", grid sản phẩm |  | ☐ | |
| P2-07 | Nội dung SEO thương hiệu | hiện **dưới** grid, chỉ ở trang 1 |  | ☐ | |
| P2-08 | `/thuong-hieu/apollo/page/2/` | không lặp lại nội dung SEO |  | ☐ | |
| P2-09 | Kiểm tra số H1 trên trang thương hiệu | đúng **1** thẻ H1 |  | ☐ | |
| P2-10 | Sửa sản phẩm | 5 tab SAHA hiện trong Product data |  | ☐ | |
| P2-11 | Thêm 3 dòng thông số, lưu | hiện đúng ở frontend dạng bảng |  | ☐ | |
| P2-12 | Xoá hết dòng thông số, lưu | khối "Thông số kỹ thuật" biến mất khỏi frontend |  | ☐ | |
| P2-13 | Thêm tài liệu PDF | link tải hoạt động, hiện nhãn loại (TDS/SDS…) |  | ☐ | |
| P2-14 | Xoá file PDF khỏi Media Library | mục tài liệu tự biến mất, không lỗi 404 |  | ☐ | |
| P2-15 | Sản phẩm không có field nào | không render khối rỗng nào |  | ☐ | |
| P2-16 | Tình trạng = "Liên hệ" | frontend hiện "Liên hệ" thay vì tồn kho Woo |  | ☐ | |
| P2-17 | Để trống tình trạng | fallback theo stock status của WooCommerce |  | ☐ | |
| P2-18 | CTA mode = "Ẩn CTA" | không hiện nút báo giá trên sản phẩm đó |  | ☐ | |
| P2-19 | Product card ở archive | hiện brand + SKU, title không tràn 2 dòng |  | ☐ | |
| P2-20 | Single product | có khối "Sản phẩm khác của {brand}" |  | ☐ | |
| P2-21 | Sản phẩm không gắn brand | không render khối brand products, không lỗi |  | ☐ | |
| P2-22 | `[saha_brand_grid]` trong page | render grid, `brand.css` được load |  | ☐ | |
| P2-23 | Trang không có shortcode | `brand.css` **không** load (xem view-source) |  | ☐ | |
| P2-24 | Thêm/sửa/xoá thương hiệu | transient brand bị xoá, grid cập nhật ngay |  | ☐ | |
| P2-25 | Login role Sales, sửa thương hiệu | bị chặn (thiếu `manage_saha_brands`) |  | ☐ | |
| P2-26 | POST product form thiếu nonce SAHA | meta cũ **không** bị xoá |  | ☐ | |
| P2-27 | Nhập `<script>` vào thông số | bị escape, không thực thi |  | ☐ | |
| P2-28 | Nhập attachment ID không tồn tại | bị loại, không lưu |  | ☐ | |
| P2-29 | Mobile 375px | bảng thông số cuộn được, CTA xếp dọc |  | ☐ | |
| P2-30 | Rank Math active | breadcrumb 1 lần, không schema trùng |  | ☐ | |

### P3 — Search & Filter ([PHASE-3.md](PHASE-3.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P3-01 | `/wp-json/saha/v1/search?q=243` | trả Loctite 243 ở vị trí đầu | http + qa | ☐ | |
| P3-02 | `?q=apollo a500` | trả Apollo Silicone A500 ở vị trí đầu | http + qa | ☐ | |
| P3-03 | `?q=APOLLO` và `?q=apollo` | kết quả như nhau | http + qa | ☐ | |
| P3-04 | `?q=keo` vs `?q=kéo` | cùng kết quả (collation accent-insensitive) | qa (collation) | ☐ | |
| P3-05 | `?q=a` | 400, thông báo từ khoá quá ngắn | http | ☐ | |
| P3-06 | `?q=zzzzzz` | 200, `items: []`, `total: 0` | http + qa | ☐ | |
| P3-07 | `?q=' OR 1=1 --` | 200, 0 kết quả, **không** lỗi SQL | http + qa | ☐ | |
| P3-08 | `?q=<script>alert(1)</script>` | trả về escape, không thực thi khi render |  | ☐ | |
| P3-09 | Gọi `/search` 31 lần trong 1 phút | lần 31 trả 429 |  | ☐ | |
| P3-10 | `/products?per_page=99999` | clamp còn 50 | http | ☐ | |
| P3-11 | `/products/{id}` với sản phẩm draft | 404 | http | ☐ | |
| P3-12 | `/brands/khong-ton-tai` | 404 | http | ☐ | |
| P3-13 | MySQL 8 bật ONLY_FULL_GROUP_BY | search chạy, không lỗi SQL | qa | ☐ | |
| P3-14 | Gõ vào ô tìm kiếm | chỉ 1 request sau khi ngừng gõ 300ms |  | ☐ | |
| P3-15 | Gõ rồi xoá nhanh | request cũ bị abort, không race |  | ☐ | |
| P3-16 | Không có kết quả | panel hiện "Không tìm thấy sản phẩm phù hợp" |  | ☐ | |
| P3-17 | Ngắt mạng rồi gõ | panel hiện thông báo lỗi thân thiện |  | ☐ | |
| P3-18 | Phím ↓ ↑ Enter Esc trong panel | điều hướng và đóng đúng |  | ☐ | |
| P3-19 | Submit form tìm kiếm | tới trang kết quả, thứ tự theo relevance |  | ☐ | |
| P3-20 | Trang kết quả rỗng | empty state có ô tìm kiếm + danh mục, chỉ **1** thông báo |  | ☐ | |
| P3-21 | Tick 1 thương hiệu ở bộ lọc | URL đổi, danh sách cập nhật, không reload trang |  | ☐ | |
| P3-22 | Copy URL đã lọc, mở tab ẩn danh | ra đúng kết quả đã lọc |  | ☐ | |
| P3-23 | Tắt JavaScript, tick lọc + bấm Áp dụng | vẫn lọc đúng |  | ☐ | |
| P3-24 | Bấm Back sau khi lọc | quay lại trạng thái trước |  | ☐ | |
| P3-25 | Bấm phân trang trong vùng kết quả | AJAX, URL đổi |  | ☐ | |
| P3-26 | Lọc "Liên hệ" | ra sản phẩm có `_saha_availability = contact` |  | ☐ | |
| P3-27 | Lọc tình trạng với sản phẩm để trống field | fallback theo `_stock_status` |  | ☐ | |
| P3-28 | Thêm `?saha_brand=<script>` | bị sanitize, không lọc, không XSS |  | ☐ | |
| P3-29 | Bật Ghi log tìm kiếm rồi tìm | `wp_saha_search_logs` có dòng mới, không có IP |  | ☐ | |
| P3-30 | Tắt Ghi log tìm kiếm | không ghi thêm dòng nào |  | ☐ | |
| P3-31 | Đặt từ đồng nghĩa `keo kính = silicone` rồi tìm "keo kính" | ra cả sản phẩm silicone |  | ☐ | |
| P3-32 | Mobile 375px | bộ lọc xếp dọc, panel autocomplete không tràn màn hình |  | ☐ | |

### P4 — Quote & Lead ([PHASE-4.md](PHASE-4.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P4-01 | Update plugin lên 1.3.0 | migration 005 chạy, có cột `notes`; dữ liệu cũ nguyên vẹn |  | ☐ | |
| P4-02 | Chạy lại migration (xoá `005` khỏi `saha_core_migrations_ran`) | không lỗi, không thêm cột trùng |  | ☐ | |
| P4-03 | Trang sản phẩm → "Yêu cầu báo giá" | modal mở, hiện tên + SKU sản phẩm, focus vào ô đầu |  | ☐ | |
| P4-04 | Esc / click nền / nút × | modal đóng |  | ☐ | |
| P4-05 | Submit hợp lệ | 201, thông báo xanh, `wp_saha_quotes` + `wp_saha_leads` mỗi bảng thêm 1 dòng | http --write | ☐ | |
| P4-06 | Submit lần 2 cùng SĐT + sản phẩm trong 10 phút | 200 `duplicate: true`, **không** thêm dòng | http --write | ☐ | |
| P4-07 | Bấm submit liên tục | nút bị disable, chỉ 1 request |  | ☐ | |
| P4-08 | Bỏ trống SĐT | lỗi hiện dưới ô, focus nhảy về ô lỗi |  | ☐ | |
| P4-09 | Email sai định dạng | lỗi 422 với `errors.email` | http --write | ☐ | |
| P4-10 | Sửa `product_id` trong DevTools thành ID bài viết | 422 `errors.product_id` | http --write | ☐ | |
| P4-11 | Sửa `product_name` trong DevTools | DB vẫn lưu tên thật của sản phẩm |  | ☐ | |
| P4-12 | Gửi request không có `X-WP-Nonce` hợp lệ | 403 | http --write | ☐ | |
| P4-13 | Gửi 6 lần trong 10 phút | lần 6 trả 429, JS hiện thông báo thân thiện | http --write | ☐ | |
| P4-14 | Điền field honeypot | trả "thành công", **không** lưu DB | http --write | ☐ | |
| P4-15 | Tắt JS rồi submit | POST admin-post.php → redirect về trang cũ, hiện thông báo |  | ☐ | |
| P4-16 | Tắt JS, sửa `source_url` thành domain khác | redirect về trang chủ, không bị open redirect |  | ☐ | |
| P4-17 | Purge cache, đợi nonce hết hạn (hoặc sửa nonce trong HTML), submit | tự lấy nonce mới, gửi thành công |  | ☐ | |
| P4-18 | Email admin | nhận được, `Reply-To` là email khách, có link mở trong quản trị |  | ☐ | |
| P4-19 | Để trống "Email nhận báo giá" | gửi tới admin email |  | ☐ | |
| P4-20 | Plugin SMTP lỗi | dữ liệu vẫn lưu, có log `mail` trong `wp_saha_logs` |  | ☐ | |
| P4-21 | SAHA → Yêu cầu báo giá | danh sách, bong bóng đếm "Mới" trên menu |  | ☐ | |
| P4-22 | Tìm theo SĐT / tên sản phẩm | lọc đúng |  | ☐ | |
| P4-23 | Lọc trạng thái + sales + khoảng ngày | kết hợp đúng, giữ lọc khi phân trang |  | ☐ | |
| P4-24 | Bulk "Chuyển sang: Đã liên hệ" | cập nhật, thông báo số bản ghi, giữ bộ lọc |  | ☐ | |
| P4-25 | Bulk "Gán" với sales đã chọn | cột Sales cập nhật |  | ☐ | |
| P4-26 | Gán cho user **không** có `manage_saha_quotes` (sửa DOM) | 0 bản ghi được cập nhật |  | ☐ | |
| P4-27 | Chi tiết → thêm ghi chú | hiện tên người ghi + giờ theo múi giờ site |  | ☐ | |
| P4-28 | Chi tiết khách đã hỏi giá 2 lần | "Lịch sử khách hàng" hiện lần còn lại |  | ☐ | |
| P4-29 | User role Sales | thấy Báo giá, Lead, Liên hệ, Báo cáo; **không** thấy Cấu hình |  | ☐ | |
| P4-30 | User role Content Manager mở thẳng URL `saha-quotes` | 403 (spec §57 "unauthorized admin") |  | ☐ | |
| P4-31 | Bulk action với `_wpnonce` sai | 403 |  | ☐ | |
| P4-32 | Menu "Liên hệ" | lead lọc sẵn `source=contact` |  | ☐ | |
| P4-33 | Báo cáo | số theo trạng thái, tỷ lệ chốt, top sản phẩm, top từ khoá (nếu bật log) |  | ☐ | |
| P4-34 | Mobile 375px | modal toàn màn hình, form 1 cột, sticky CTA không che nút gửi |  | ☐ | |
| P4-35 | Trang không phải sản phẩm, bấm "Báo giá" ở sticky CTA | đi tới trang báo giá (hoặc hotline nếu chưa cấu hình) |  | ☐ | |
| P4-36 | Xem source trang chủ | `form.css`, `quote-form.js` **không** được load |  | ☐ | |

### P5 — Homepage ([PHASE-5.md](PHASE-5.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P5-01 | Dán `docs/layouts/homepage.ux.txt` vào UX Builder | render đủ 14 khối theo thứ tự spec §18 |  | ☐ | |
| P5-02 | Chọn ảnh hero trong UX Builder | ảnh hiện, không có URL ảnh nào hardcode trong code |  | ☐ | |
| P5-03 | Đếm thẻ H1 trang chủ | đúng **1** (trong hero); mọi tiêu đề khối là H2 |  | ☐ | |
| P5-04 | Khối Keo Silicone với slug danh mục sai | khách: khối biến mất; admin: thấy gợi ý sửa |  | ☐ | |
| P5-05 | Kéo SAHA Product Grid mới, chọn danh mục bằng dropdown | ra đúng sản phẩm (term ID được hiểu) |  | ☐ | |
| P5-06 | `source="featured" brand="loctite"` | chỉ sản phẩm Loctite có đánh dấu nổi bật |  | ☐ | |
| P5-07 | Đánh dấu ★ nổi bật một sản phẩm | xuất hiện ngay ở khối Sản phẩm nổi bật (cache vô hiệu) |  | ☐ | |
| P5-08 | Sửa tên danh mục | grid danh mục cập nhật ngay |  | ☐ | |
| P5-09 | Kéo-thả sắp xếp danh mục trong WooCommerce | grid theo đúng thứ tự; danh mục chưa kéo-thả **không** biến mất |  | ☐ | |
| P5-10 | Ẩn sản phẩm khỏi catalogue (Catalog visibility: Hidden) | không xuất hiện ở khối nào |  | ☐ | |
| P5-11 | `hide_out_of_stock="1"` | sản phẩm hết hàng bị ẩn |  | ☐ | |
| P5-12 | Query Monitor trên trang chủ (cache nóng) | không có query sản phẩm lặp theo từng card (không N+1) |  | ☐ | |
| P5-13 | Query Monitor sau khi lưu 1 sản phẩm | lần tải đầu query lại, lần sau lấy từ cache |  | ☐ | |
| P5-14 | Bật Redis Object Cache | vẫn đúng, transient nằm trong Redis |  | ☐ | |
| P5-15 | Bấm "Xem tất cả" ở khối danh mục/thương hiệu | tới đúng archive tương ứng |  | ☐ | |
| P5-16 | Nút Quote CTA trên trang chủ | mở modal báo giá tại chỗ |  | ☐ | |
| P5-17 | Trang chủ có Quote CTA | `form.css`, `quote-form.js` được load; trang không có thì không |  | ☐ | |
| P5-18 | Khối Ứng dụng kiểu chip trên mobile 375px | cuộn ngang, không vỡ layout |  | ☐ | |
| P5-19 | Grid danh mục ở 320 / 375 / 768 / 1024 / 1440px | 2 / 2 / 3 / 4 / 4 cột (với `columns="4"`) |  | ☐ | |
| P5-20 | Ảnh danh mục | có `width/height`, `loading="lazy"`, khung tỉ lệ cố định → CLS < 0.1 |  | ☐ | |
| P5-21 | Ảnh hero | **không** lazy-load (xem ghi chú LCP ở mục 11) |  | ☐ | |
| P5-22 | Footer block | địa chỉ/hotline/email lấy từ SAHA → Cấu hình; đổi setting → footer đổi theo |  | ☐ | |
| P5-23 | `[saha_copyright]` | năm hiện tại theo múi giờ site |  | ☐ | |
| P5-24 | Tắt plugin saha-core | trang chủ không lỗi PHP; các khối SAHA biến mất |  | ☐ | |
| P5-25 | Lighthouse mobile trang chủ | LCP < 2.5s, CLS < 0.1 (spec §25) |  | ☐ | |

### P6 — SEO ([PHASE-6.md](PHASE-6.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P6-01 | View-source trang thương hiệu | đúng **1** `<title>`, 1 meta description, 1 canonical |  | ☐ | |
| P6-02 | Nhập SEO title ở form thương hiệu SAHA, để trống ô Rank Math | `<title>` dùng giá trị SAHA |  | ☐ | |
| P6-03 | Nhập thêm ô title trong Rank Math | Rank Math thắng, SAHA không ghi đè |  | ☐ | |
| P6-04 | Trang 2 của thương hiệu | title có "- Trang 2"; canonical = chính trang 2 |  | ☐ | |
| P6-05 | `/thuong-hieu/loctite/?saha_availability=in_stock` | `noindex, follow`; canonical = `/thuong-hieu/loctite/` |  | ☐ | |
| P6-06 | `/thuong-hieu/loctite/page/2/?saha_brand=x` | canonical = `/thuong-hieu/loctite/page/2/` |  | ☐ | |
| P6-07 | `/?s=keo&post_type=product` | `noindex, follow` | http | ☐ | |
| P6-08 | `/robots.txt` | có `Disallow: /?s=`, `admin-post.php`; **không** chặn `wp-content`, CSS, JS | http + qa | ☐ | |
| P6-09 | Rank Math sửa robots.txt trong giao diện của nó | dòng SAHA vẫn được thêm vào nhóm `User-agent: *` |  | ☐ | |
| P6-10 | Rich Results Test trang sản phẩm (Rank Math) | đúng **1** khối `Product` |  | ☐ | |
| P6-11 | Rich Results Test trang sản phẩm (không plugin SEO) | 1 khối `Product` từ WooCommerce |  | ☐ | |
| P6-12 | Rich Results Test | đúng **1** `BreadcrumbList` |  | ☐ | |
| P6-13 | Trang sản phẩm, bật breadcrumb Rank Math | 1 breadcrumb hiển thị, của Rank Math |  | ☐ | |
| P6-14 | Tắt breadcrumb trong Rank Math | breadcrumb WooCommerce/Flatsome hiện lại (không mất hẳn) |  | ☐ | |
| P6-15 | Sitemap Rank Math/Yoast | có sản phẩm, danh mục, **thương hiệu**, bài viết, trang (bật taxonomy trong plugin — xem mục 9) |  | ☐ | |
| P6-16 | `/wp-sitemap.xml` (không plugin SEO) | có `product_brand`, `product_application`; **không** có users, `product_tag` |  | ☐ | |
| P6-17 | Không plugin SEO: view-source bài viết | meta description, `og:title`, `og:type=article`, `og:image` |  | ☐ | |
| P6-18 | Không plugin SEO: trang danh mục | có canonical + meta description từ mô tả danh mục |  | ☐ | |
| P6-19 | Có Rank Math: view-source | **không** có thẻ meta/OG nào do SAHA xuất (không trùng) |  | ☐ | |
| P6-20 | Bài blog | breadcrumb đầu bài, "Bài viết liên quan" cuối bài, 3 bài cùng chuyên mục |  | ☐ | |
| P6-21 | Bài thuộc chuyên mục chỉ có 1 bài | khối liên quan tự bổ sung bài mới nhất |  | ☐ | |
| P6-22 | Tắt "Bài viết liên quan" trong Cấu hình | khối biến mất |  | ☐ | |
| P6-23 | Trang sản phẩm | tên sản phẩm là **H1 duy nhất** (spec §24) |  | ☐ | |
| P6-24 | Link danh mục / thương hiệu trên trang sản phẩm | là thẻ `<a href>` thật, crawl được |  | ☐ | |
| P6-25 | Search Console → URL Inspection trang thương hiệu | "URL is on Google / can be indexed" |  | ☐ | |

### P7 — Performance ([PHASE-7.md](PHASE-7.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| P7-01 | PageSpeed Insights mobile — trang chủ | LCP < 2.5s, CLS < 0.1 |  | ☐ | |
| P7-02 | PageSpeed Insights mobile — 1 trang sản phẩm, 1 trang thương hiệu | LCP < 2.5s, CLS < 0.1 |  | ☐ | |
| P7-03 | View-source trang chủ | có `<link rel="preload" as="image" … fetchpriority="high">` đúng ảnh hero |  | ☐ | |
| P7-04 | DevTools → Network trang chủ | ảnh hero tải trong nhóm đầu, **không** bị lazy |  | ☐ | |
| P7-05 | DevTools → Network, chế độ catalogue | **không** có request `?wc-ajax=get_refreshed_fragments` |  | ☐ | |
| P7-06 | Tắt chế độ catalogue | cart fragments quay lại, giỏ hàng hoạt động |  | ☐ | |
| P7-07 | View-source | JS của theme có `defer`; không có `wp-emoji-release.min.js` | http | ☐ | |
| P7-08 | Query Monitor trang chủ, cache nóng | không query sản phẩm/term theo từng card; tổng query giảm rõ so với cache lạnh |  | ☐ | |
| P7-09 | Sửa 1 sản phẩm | khối trang chủ, grid thương hiệu, kết quả tìm kiếm cập nhật ở lần tải kế tiếp |  | ☐ | |
| P7-10 | Lưu sản phẩm, Query Monitor | `saha_cache_gen` chỉ `UPDATE` **một** lần |  | ☐ | |
| P7-11 | Tìm từ khoá có > 200 kết quả, sang trang 10+ | có kết quả (trước đây bị cắt ở 200) |  | ☐ | |
| P7-12 | Trang admin bất kỳ, Query Monitor | không có query `COUNT … GROUP BY status` lặp lại (bong bóng menu lấy từ cache) |  | ☐ | |
| P7-13 | Gửi báo giá mới | bong bóng menu tăng ngay |  | ☐ | |
| P7-14 | `wp cron event list` | có `saha_core_daily_maintenance` lúc 03:00 | qa | ☐ | |
| P7-15 | `wp cron event run saha_core_daily_maintenance` với log giả cũ 100 ngày | log cũ bị xoá, quotes/leads nguyên vẹn |  | ☐ | |
| P7-16 | Tắt plugin | cron biến mất khỏi danh sách |  | ☐ | |
| P7-17 | Submit form không-JS → trang `?saha_form=quote_sent` | response header `Cache-Control: no-cache…`; LiteSpeed header `X-LiteSpeed-Cache-Control: no-cache` | http | ☐ | |
| P7-18 | Bật Redis Object Cache | transient `saha_*` nằm trong Redis, bảng `wp_options` không phình |  | ☐ | |
| P7-19 | Nâng cấp từ 1.5.0 | option `saha_brand_cache_keys`, `saha_catalog_cache_gen` biến mất |  | ☐ | |
| P7-20 | INP: gõ vào ô tìm kiếm, bấm lọc, mở modal báo giá trên mobile | phản hồi < 200ms (Chrome DevTools → Performance → Interactions) |  | ☐ | |

### SCC — SAHA Commerce Core: saha-theme + SAHA Builder ([scc/PHASE-1.7.md](scc/PHASE-1.7.md))

Trên `saha-theme`, các mục của P5 nói về UX Builder/Flatsome thay bằng mục SCC tương ứng.

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| SCC-01 | Kích hoạt SAHA Core, SAHA Builder, SAHA Theme rồi chạy `wp saha qa` | 0 lỗi; "Theme SAHA Theme đang bật" | qa | ☐ | |
| SCC-02 | Theme Options: đổi màu chính, font chữ, số cột shop | toàn site đổi (nút, giá, tab sản phẩm, header, footer, thanh toán) — không sửa CSS | smoke | ☐ | |
| SCC-03 | Tắt plugin SAHA Builder, mở trang chủ và một trang dựng bằng builder | HTML giống hệt khi bật; bật lại sửa tiếp được |  | ☐ | |
| SCC-04 | Dựng trang mới: Section › Hàng › Cột › Tiêu đề, Văn bản, Nút, Ảnh → Lưu → Xem trang | frontend giống canvas |  | ☐ | |
| SCC-05 | Undo/redo, copy/paste, nhân đôi, xem theo thiết bị | đúng thao tác; giá trị tablet/mobile kế thừa desktop khi để trống | js | ☐ | |
| SCC-06 | Editor (không `unfiltered_html`) lưu element HTML có `<script>` | script bị lọc | smoke | ☐ | |
| SCC-07 | Hai tài khoản cùng mở một trang trong builder, cả hai bấm Lưu | người sau bị báo đang có người sửa / tải lại bản mới; không ai bị ghi đè |  | ☐ | |
| SCC-08 | Lưu link `javascript:` hoặc dữ liệu sai | không lưu; lỗi chỉ đúng ô | smoke | ☐ | |
| SCC-09 | Gọi REST `/builder/*` khi chưa đăng nhập | 401/403 | http | ☐ | |
| SCC-10 | Mở trang ngoài frontend | không nạp JS/CSS của ứng dụng builder | http | ☐ | |
| SCC-11 | Sửa một Block đang dùng ở 2 trang | cả hai trang cập nhật |  | ☐ | |
| SCC-12 | Block chèn chính nó (trực tiếp/gián tiếp) | không treo; editor báo lỗi vòng lặp |  | ☐ | |
| SCC-13 | SAHA → Header & Footer → Tạo mặc định | header/footer builder "✓ Đang dùng" | qa | ☐ | |
| SCC-14 | Header: dính khi cuộn; mobile ☰ mở off-canvas; Tab/Esc/focus | `aria-expanded`, `aria-controls` đúng; Esc đóng, focus về ☰ | smoke | ☐ | |
| SCC-15 | Đăng nhập editor | không thấy Header & Footer |  | ☐ | |
| SCC-16 | Trang → "Tạo trang chủ mẫu (SAHA Builder)" | mở builder; trang chủ 14 khối, đúng 1 H1, không shortcode | smoke + qa + http | ☐ | |
| SCC-17 | Tạo trang chủ mẫu trên site thiếu danh mục "pu-foam" | vẫn tạo được, khối PU Foam bị bỏ | smoke | ☐ | |
| SCC-18 | CTA "Mở form báo giá" trên trang chủ | modal báo giá mở tại chỗ; gửi được | smoke | ☐ | |
| SCC-19 | Tìm "243" | tới thẳng trang Keo khoá ren Loctite 243 | http | ☐ | |
| SCC-20 | Bật catalogue: trang sản phẩm, shop, gọi `?add-to-cart=ID` | giá "Liên hệ báo giá"; không có nút giỏ; không thêm được vào giỏ | qa | ☐ | |
| SCC-21 | Tắt catalogue, bật COD: Mua ngay sản phẩm đơn giản → đặt đơn | tới thẳng Checkout; "Đơn hàng đã nhận" | smoke | ☐ | |
| SCC-22 | Mua ngay sản phẩm biến thể (chọn thuộc tính) | Checkout đúng biến thể, đúng giá |  | ☐ | |
| SCC-23 | Cart/Checkout block, Tài khoản (đăng nhập, đơn hàng, địa chỉ) | màu theo Theme Options; mobile gọn |  | ☐ | |
| SCC-24 | Trang sản phẩm có 3+ ảnh | ảnh chính, thumbnail, chuyển ảnh, zoom, lightbox |  | ☐ | |
| SCC-25 | `wp saha qa` sau khi cập nhật WooCommerce | không có template override lỗi thời | qa | ☐ | |
| SCC-26 | Trang chủ, liên hệ, báo giá, thương hiệu | không còn shortcode thô `[saha_…]`/`[ux_…]`; QA không liệt kê trang dùng UX Builder | qa + http | ☐ | |
| SCC-27 | Ma trận 1920 · 1440 · 1366 · 1024 · 768 · 430 · 390 · 375 | không tràn ngang; CTA dính không che nội dung |  | ☐ | |
| SCC-28 | Lighthouse mobile trang chủ, sản phẩm, thương hiệu (staging) | LCP < 2.5s, CLS < 0.1 |  | ☐ | |
| SCC-29 | Chỉ dùng bàn phím: header, off-canvas, modal báo giá, builder | đi được hết, thấy focus |  | ☐ | |
| SCC-30 | Chạy hết checklist | `debug.log` không có lỗi mới; Console sạch |  | ☐ | |

### SCC Phase 2 — mega menu, template, cửa hàng, báo giá, import/export, element ([scc/PHASE-2.8.md](scc/PHASE-2.8.md))

| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |
|---|---|---|---|---|---|
| SCC2-01 | Mega menu: mục menu kiểu Mega + Block, rê chuột / Tab / Esc trên desktop; mobile mở được | bảng mega hiện cột + block; Esc đóng; mobile dạng xổ xuống, không tràn |  | ☐ | |
| SCC2-02 | Template trang sản phẩm theo danh mục, trừ một sản phẩm | sản phẩm trong danh mục dùng template, sản phẩm bị trừ dùng mặc định | smoke | ☐ | |
| SCC2-03 | Độ cụ thể / ưu tiên template (sản phẩm cụ thể > danh mục > tất cả) | template cụ thể hơn thắng; cùng mức → ưu tiên cao hơn | smoke | ☐ | |
| SCC2-04 | Header / footer dựng bằng builder theo điều kiện | đúng header / footer; header dính hoạt động |  | ☐ | |
| SCC2-05 | Swatches: chọn màu + dung tích bằng chuột và bàn phím | đúng biến thể, giá, tình trạng; tổ hợp hết hàng bị khoá | smoke | ☐ | |
| SCC2-06 | Thanh "Thêm vào giỏ" dính | hiện khi cuộn qua nút mua; thêm đúng biến thể; chưa chọn → cuộn về form |  | ☐ | |
| SCC2-07 | Xem nhanh từ thẻ sản phẩm (bật / tắt catalogue) | mở hộp, Esc trả focus; thêm giỏ qua Store API; catalogue → nút báo giá | http | ☐ | |
| SCC2-08 | Ngăn giỏ hàng: thêm từ thẻ / Xem nhanh, đổi số lượng, xoá | ngăn mở, số trên icon đúng; không có ở trang giỏ / thanh toán / catalogue |  | ☐ | |
| SCC2-09 | Gợi ý tìm kiếm: gõ "243", ↑↓ Enter, Esc | gợi ý có ảnh / mã / giá; catalogue không lộ giá | http | ☐ | |
| SCC2-10 | Lọc / sắp xếp / bỏ chip ở shop + danh mục, có và không JS | không tải lại trang (JS), URL đổi; không JS vẫn lọc được |  | ☐ | |
| SCC2-11 | Danh sách báo giá: thêm 2 sản phẩm, sửa số lượng / ghi chú, gửi | 1 yêu cầu nhiều dòng; email + lead có đủ dòng | smoke + http --write | ☐ | |
| SCC2-12 | Admin: chi tiết báo giá danh sách + báo cáo sản phẩm được hỏi | bảng từng dòng; báo cáo gộp số yêu cầu + tổng SL |  | ☐ | |
| SCC2-13 | Báo giá một sản phẩm cũ (trước 2.5) | vẫn đọc được ở admin và báo cáo |  | ☐ | |
| SCC2-14 | Xuất site A → nhập site B (chạy thử rồi nhập thật) | cùng giao diện; ảnh tải về; điều kiện template theo slug; báo cáo cảnh báo rõ | smoke | ☐ | |
| SCC2-15 | Nhập file sai / quá lớn / không phải SAHA | báo lỗi, không ghi gì |  | ☐ | |
| SCC2-16 | Builder → Thêm → Khối mẫu, Block đã lưu (trang + header) | chèn bản sao (H1 → H2 khi trang đã có H1); block chèn dạng liên kết; header không có khối mẫu | js + qa | ☐ | |
| SCC2-17 | Builder → Cấu trúc: thu gọn, tên section, nền sọc khi ẩn, "+ Thêm vào …" | đúng như mô tả; chọn trên canvas → cây mở + cuộn tới | js | ☐ | |
| SCC2-18 | Tabs: chuột, ←/→/Home/End; sửa tab 2 trong builder | chuyển đúng tab; builder hiện mọi tab | smoke | ☐ | |
| SCC2-19 | Thư viện ảnh: bấm ảnh, ←/→, Esc | xem lớn, đếm "2 / 4", focus trả về ảnh |  | ☐ | |
| SCC2-20 | Video YouTube / Vimeo / mp4 | chỉ tải iframe khi bấm; không JS → link mở video | smoke | ☐ | |
| SCC2-21 | Logo thương hiệu (tự động + tự chọn, lưới + băng chuyền) | đủ logo, link trang thương hiệu, nút ‹ › |  | ☐ | |
| SCC2-22 | Đếm ngược + khi hết giờ | số giảm mỗi giây; hết giờ hiện chữ / ẩn |  | ☐ | |
| SCC2-23 | Đăng ký nhận tin (khách và khi đang đăng nhập; email trùng) | lead nguồn "Đăng ký nhận tin"; lần 2 báo đã đăng ký | http | ☐ | |
| SCC2-24 | Lưới; Sản phẩm / Bài viết dạng băng chuyền | số cột đúng từng thiết bị; băng chuyền cuộn + nút | smoke | ☐ | |
| SCC2-25 | Ma trận thiết bị (QA.md mục 2) cho trang có element Phase 2 | không tràn ngang; chạm được nút ‹ ›, swatch, ngăn giỏ |  | ☐ | |
| SCC2-26 | Audit trang: H1, alt, tên nút / link, nhãn ô nhập, ID trùng | 1 H1; không thiếu; không trùng ID | http | ☐ | |
| SCC2-27 | Hiệu năng: PageSpeed mobile trang chủ, danh mục, sản phẩm (staging) | LCP < 2.5s, CLS < 0.1; chỉ 1 ảnh `fetchpriority=high` | http | ☐ | |
| SCC2-28 | Bảo mật: quyền route `saha/v1`, chuỗi tấn công trong element, form công khai | khách chỉ dùng được route công khai; không XSS; nonce + rate limit + honeypot | smoke + http + qa | ☐ | |
| SCC2-29 | `wp saha qa --strict` trên staging | 0 lỗi, 0 cảnh báo (staging có plugin SEO, Zalo, object cache) | qa | ☐ | |
| SCC2-30 | Bộ giao diện cửa hàng một nút + "Khôi phục" Theme Options | áp đủ header / footer / trang chủ / bộ màu; khôi phục về bản trước |  | ☐ | |

## 4. Nghiệm thu (spec §98)

Một module chỉ được coi hoàn thành khi:

| Tiêu chí | Kết quả | Người xác nhận | Ngày |
|---|---|---|---|
| Hoạt động đúng — mọi test ❌ đã sửa và chạy lại | ☐ | | |
| Không PHP fatal (debug.log sạch sau khi chạy hết checklist) | ☐ | | |
| Không JS error (Console sạch ở mọi trang trong ma trận mục 2) | ☐ | | |
| Responsive — ma trận mục 2 đạt | ☐ | | |
| Security validation — P1, P4, P6 phần bảo mật đạt | ☐ | | |
| Tắt plugin SAHA Builder → mọi trang đã dựng hiển thị y hệt (SCC-03) | ☐ | | |
| Không còn trang nào dùng shortcode UX Builder/Flatsome (`wp saha qa`) | ☐ | | |
| Không sửa core (`git status` của WordPress/WooCommerce sạch) | ☐ | | |
| `wp saha qa --strict` đạt | ☐ | | |
| `php tests/http-smoke.php <staging> --write` đạt | ☐ | | |
| PageSpeed mobile: LCP < 2.5s, CLS < 0.1 (trang chủ, sản phẩm, thương hiệu) | ☐ | | |
