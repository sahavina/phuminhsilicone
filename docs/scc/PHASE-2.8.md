# SCC Phase 2 — Mốc 2.8: QA Phase 2

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §17 (testing); kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.7.md](PHASE-2.7.md). Phase 1: [PHASE-1.7.md](PHASE-1.7.md).

## 1. Goal

- Chạy lại **toàn bộ công cụ tự động** và một vòng **kiểm tra trang** (desktop + mobile) sau Phase 2.
- Sửa lỗi tìm thấy; viết **checklist Phase 2** (đưa vào `docs/QA.md`) cho lần chạy trên staging.
- Cập nhật DEV-GUIDE / ADMIN-GUIDE.

Nghiệm thu: **checklist Phase 2 đạt trên local + staging** — local: xong (mục 2–5); staging: chưa có môi trường (mục 6).

## 2. Kết quả công cụ tự động (local, SAHA Core 1.29.0 · Builder 0.7.0 · Theme 0.2.6)

| Công cụ | Kết quả |
|---|---|
| `php -l` mọi file PHP của saha-core, saha-builder, saha-theme | 271 file, 0 lỗi |
| `node --check` mọi JS không qua build (saha-core, theme) | 0 lỗi |
| `php tests/smoke.php` | **260 passed**, 0 failed |
| `npm run test:js` / `lint:js` / `lint:css` | 49 passed / đạt / đạt |
| `npm run build` → `git diff` thư mục build | không khác (build đã commit khớp src) |
| `php tests/http-smoke.php <local> --write` | 51 passed, 0 failed (skip: robots.txt ở thư mục con; 1 mục bị rate limit vì vừa chạy) |
| `wp saha qa --strict` | 96 đạt, 0 lỗi, 3 cảnh báo **môi trường local**: chưa có plugin SEO, chưa nhập số Zalo, chưa có object cache |
| `debug.log` trong lúc QA | không có PHP warning / notice / fatal mới (chỉ log nghiệp vụ: gửi mail lỗi do local không có mail server, honeypot, import) |

## 3. Kiểm tra trang (audit tự động trong trình duyệt)

14 trang × 2 chiều rộng (1280px, 375px) — trang chủ, shop, danh mục, sản phẩm thường, sản phẩm biến thể, thương hiệu, tìm kiếm, blog, bài viết, liên hệ, danh sách báo giá, trang element Phase 2, giỏ hàng, 404. Mỗi trang kiểm: mã HTTP, số H1, tràn ngang, ảnh thiếu `alt`, nút / link không có tên, ô nhập không có nhãn, ID trùng, lỗi PHP lộ ra HTML.

| Kết quả | |
|---|---|
| Mã HTTP | đúng (200; trang không tồn tại 404) |
| H1 | đúng 1 ở cả 28 lượt |
| Tràn ngang 375px | không |
| Ảnh thiếu alt / nút, link không tên / ô không nhãn | không (link ảnh trùng của thẻ bài viết là `aria-hidden` + `tabindex=-1` — chủ đích) |
| ID trùng | **1 lỗi** → đã sửa (mục 5) |
| Tài nguyên lỗi (Network) | không (404 chỉ là URL thử 404) |

Hiệu năng (local, không page cache / object cache — số tương đối):

| Trang | Thời gian server | HTML | File CSS + JS |
|---|---|---|---|
| Trang chủ | 0,21 s | 163 KB | 43 |
| Shop | 0,31 s | 130 KB | 44 |
| Danh mục | 0,17 s | 94 KB | 44 |
| Sản phẩm (thường / biến thể) | 0,22 / 0,18 s | 107 / 82 KB | 54 / 53 |
| Tìm kiếm | 0,14 s | 77 KB | 44 |

Kiểm lại chức năng Phase 2 trên code hiện tại: mega menu có mặt; gợi ý tìm "243" → Loctite 243; Xem nhanh mở, có ô biến thể + nút danh sách báo giá; lọc thương hiệu bằng AJAX (không tải lại, URL `?saha_brand[]=apollo`, 4 sản phẩm); template sản phẩm theo danh mục áp đúng và trừ đúng sản phẩm #21.

Bảo mật: liệt kê mọi route `saha/v1` + thử quyền khách — chỉ route đọc công khai và 4 form POST (đều rate limit + nonce + honeypot) mở cho khách; builder / blocks / settings từ chối khách. Thêm test chuỗi tấn công vào mọi ô chữ của element Phase 2 (mục 5).

## 4. Lỗi tìm thấy & đã sửa

| # | Lỗi | Sửa |
|---|---|---|
| 1 | **Hiệu năng**: trang chủ có 4 ảnh `fetchpriority="high"` — element Logo luôn đặt ưu tiên cao và header kiểu cửa hàng in logo 3 lần (desktop, mobile, menu trượt) → tranh băng thông với ảnh hero (LCP) | Logo giữ `loading="eager"`, bỏ `fetchpriority`. Trang chủ còn đúng 1 (slide hero "Ưu tiên tải"). http-smoke: nhận `fetchpriority="high"` là hero (SCC không dùng `<link rel=preload>`) và **báo lỗi khi > 1** |
| 2 | **A11y / HTML**: trang Liên hệ có 2 phần tử `id="saha-form-result"` (form liên hệ + form báo giá trong modal) | ID riêng: `saha-form-result-contact`, `saha-form-result-quote`, `saha-form-result-quote-modal`; redirect không JS neo đúng form theo loại (`class-form-handler.php`) |
| 3 | **CI**: JS không qua build (saha-core, theme) không được kiểm — `lint:js` chỉ quét `src/` | Thêm bước CI `node --check` cho mọi file đó; ghi chú trong DEV-GUIDE |

Phiên bản: SAHA Core **1.29.0**, SAHA Theme **0.2.6**.

## 5. Test thêm

- `tests/smoke.php`: chuỗi `"><script>…<img onerror>` vào mọi ô chữ của Tabs / Tab / Video / Đếm ngược / Nhận tin / Lưới → HTML không có thẻ hay thuộc tính thực thi; `Video::parse` từ chối `javascript:` / `data:`.
- `tests/http-smoke.php`: chỉ 1 ảnh `fetchpriority="high"` ở trang chủ.
- `tests/build-qa-checklist.py`: thêm mục **SCC Phase 2** (bảng dưới) vào `docs/QA.md`.

### Checklist SCC Phase 2

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | Mega menu: mục menu kiểu Mega + Block, rê chuột / Tab / Esc trên desktop; mobile mở được | bảng mega hiện cột + block; Esc đóng; mobile dạng xổ xuống, không tràn |
| 2 | Template trang sản phẩm theo danh mục, trừ một sản phẩm | sản phẩm trong danh mục dùng template, sản phẩm bị trừ dùng mặc định |
| 3 | Độ cụ thể / ưu tiên template (sản phẩm cụ thể > danh mục > tất cả) | template cụ thể hơn thắng; cùng mức → ưu tiên cao hơn |
| 4 | Header / footer dựng bằng builder theo điều kiện | đúng header / footer; header dính hoạt động |
| 5 | Swatches: chọn màu + dung tích bằng chuột và bàn phím | đúng biến thể, giá, tình trạng; tổ hợp hết hàng bị khoá |
| 6 | Thanh "Thêm vào giỏ" dính | hiện khi cuộn qua nút mua; thêm đúng biến thể; chưa chọn → cuộn về form |
| 7 | Xem nhanh từ thẻ sản phẩm (bật / tắt catalogue) | mở hộp, Esc trả focus; thêm giỏ qua Store API; catalogue → nút báo giá |
| 8 | Ngăn giỏ hàng: thêm từ thẻ / Xem nhanh, đổi số lượng, xoá | ngăn mở, số trên icon đúng; không có ở trang giỏ / thanh toán / catalogue |
| 9 | Gợi ý tìm kiếm: gõ "243", ↑↓ Enter, Esc | gợi ý có ảnh / mã / giá; catalogue không lộ giá |
| 10 | Lọc / sắp xếp / bỏ chip ở shop + danh mục, có và không JS | không tải lại trang (JS), URL đổi; không JS vẫn lọc được |
| 11 | Danh sách báo giá: thêm 2 sản phẩm, sửa số lượng / ghi chú, gửi | 1 yêu cầu nhiều dòng; email + lead có đủ dòng |
| 12 | Admin: chi tiết báo giá danh sách + báo cáo sản phẩm được hỏi | bảng từng dòng; báo cáo gộp số yêu cầu + tổng SL |
| 13 | Báo giá một sản phẩm cũ (trước 2.5) | vẫn đọc được ở admin và báo cáo |
| 14 | Xuất site A → nhập site B (chạy thử rồi nhập thật) | cùng giao diện; ảnh tải về; điều kiện template theo slug; báo cáo cảnh báo rõ |
| 15 | Nhập file sai / quá lớn / không phải SAHA | báo lỗi, không ghi gì |
| 16 | Builder → Thêm → Khối mẫu, Block đã lưu (trang + header) | chèn bản sao (H1 → H2 khi trang đã có H1); block chèn dạng liên kết; header không có khối mẫu |
| 17 | Builder → Cấu trúc: thu gọn, tên section, nền sọc khi ẩn, "+ Thêm vào …" | đúng như mô tả; chọn trên canvas → cây mở + cuộn tới |
| 18 | Tabs: chuột, ←/→/Home/End; sửa tab 2 trong builder | chuyển đúng tab; builder hiện mọi tab |
| 19 | Thư viện ảnh: bấm ảnh, ←/→, Esc | xem lớn, đếm "2 / 4", focus trả về ảnh |
| 20 | Video YouTube / Vimeo / mp4 | chỉ tải iframe khi bấm; không JS → link mở video |
| 21 | Logo thương hiệu (tự động + tự chọn, lưới + băng chuyền) | đủ logo, link trang thương hiệu, nút ‹ › |
| 22 | Đếm ngược + khi hết giờ | số giảm mỗi giây; hết giờ hiện chữ / ẩn |
| 23 | Đăng ký nhận tin (khách và khi đang đăng nhập; email trùng) | lead nguồn "Đăng ký nhận tin"; lần 2 báo đã đăng ký |
| 24 | Lưới; Sản phẩm / Bài viết dạng băng chuyền | số cột đúng từng thiết bị; băng chuyền cuộn + nút |
| 25 | Ma trận thiết bị (QA.md mục 2) cho trang có element Phase 2 | không tràn ngang; chạm được nút ‹ ›, swatch, ngăn giỏ |
| 26 | Audit trang: H1, alt, tên nút / link, nhãn ô nhập, ID trùng | 1 H1; không thiếu; không trùng ID |
| 27 | Hiệu năng: PageSpeed mobile trang chủ, danh mục, sản phẩm (staging) | LCP < 2.5s, CLS < 0.1; chỉ 1 ảnh `fetchpriority=high` |
| 28 | Bảo mật: quyền route `saha/v1`, chuỗi tấn công trong element, form công khai | khách chỉ dùng được route công khai; không XSS; nonce + rate limit + honeypot |
| 29 | `wp saha qa --strict` trên staging | 0 lỗi, 0 cảnh báo (staging có plugin SEO, Zalo, object cache) |
| 30 | Bộ giao diện cửa hàng một nút + "Khôi phục" Theme Options | áp đủ header / footer / trang chủ / bộ màu; khôi phục về bản trước |

## 6. Chưa chạy được (cần staging)

- Ma trận thiết bị thật (Safari iOS, Firefox, Edge) — mục 25.
- PageSpeed / Core Web Vitals với page cache + CDN — mục 27.
- Xuất site local → nhập vào site **khác** thật (ảnh tải qua mạng) — mục 14 đã chạy giả lập (đổi `source`) ở 2.6.
- `wp saha qa --strict` không cảnh báo — cần plugin SEO, object cache, số Zalo trên staging.

## 7. Dữ liệu thử còn trên local (tạo trong Phase 2)

| Dữ liệu | Ở đâu |
|---|---|
| Trang thử | #121 (D1), #125 (D3), #174 (2.7) |
| Template nhập thử | #159–#167 (nháp, tên "[Thử import 2.6] …"); trang #157 trong thùng rác |
| Ảnh tải lại khi thử import | #155, #156 |
| Báo giá / lead mẫu | "[Mẫu] QA http-smoke…", "QA Khách thử" (#16), 2 lead nhận tin `qa-nl-*@example.com` |

Dọn trước khi đưa dữ liệu local lên đâu khác: `wp saha unseed` (dữ liệu seed) + xoá các mục trên.
