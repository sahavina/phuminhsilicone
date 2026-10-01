# SCC Phase 2 — Mốc 2.7: Element Phase 2

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §8 (danh sách element theo phase); kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.6.md](PHASE-2.6.md), [PHASE-D7.md](PHASE-D7.md).

## 1. Goal

Kế hoạch 2.7: Tabs, Accordion, Gallery, Video, Slider, Logo / Logo Slider, Testimonial, Countdown, Product Slider, Post Slider, Newsletter, Grid / Stack — **mỗi element có sanitize + render + test**.

| Element | Trạng thái |
|---|---|
| Accordion, Slider, Testimonial(s) | **đã có từ D1** (giao diện cửa hàng) |
| **Tabs** + **Tab** | mới — mỗi tab chứa element bất kỳ |
| **Thư viện ảnh** (`gallery`) | mới — lưới / so le, bấm phóng to |
| **Video** | mới — YouTube / Vimeo (tải khi bấm) / mp4 |
| **Logo thương hiệu / đối tác** (`logo-cloud`) | mới — tự động từ thương hiệu hoặc ảnh tự chọn; lưới hoặc **băng chuyền** (= Logo Slider) |
| **Đếm ngược** (`countdown`) | mới |
| **Product Slider**, **Post Slider** | tuỳ chọn **Hiển thị: Băng chuyền** của element Sản phẩm / Bài viết |
| **Đăng ký nhận tin** (`newsletter`) | mới — lưu thành lead nguồn "Đăng ký nhận tin" |
| **Lưới** (`grid`) | mới. **Stack** = element Hộp (Container) đã có (hướng dọc / ngang, khoảng cách, xuống dòng) |

Builder: **70 element** (62 + 8).

## 2. Chi tiết

| Element | Ghi chú kỹ thuật |
|---|---|
| Tabs / Tab | ARIA tabs dùng chung JS với tab của Sản phẩm (`[data-saha-tabs]`; đã giới hạn cho đúng nhóm khi Tabs lồng nhau). Tab đầu mở, tab khác `hidden` sẵn (HTML tĩnh, cache được). Panel ARIA ở thẻ trong → ID tự đặt (Nâng cao) không trùng. **Trong editor hiện mọi tab xếp chồng, có nhãn "Tab: …"** để sửa trên canvas. Kiểu: gạch chân / nút bo / thẻ khung; căn trái / giữa / giãn đều. |
| Thư viện ảnh | Con là element Ảnh (alt, chú thích, link riêng). Phóng to: ảnh không gắn link được bọc `<button>` → `<dialog>` (ảnh lớn nhất trong srcset, ←/→, Esc, focus trả về ảnh). Lưới đều có tỉ lệ ô; "So le" dùng CSS columns. |
| Video | `Video::parse()` chỉ nhận URL YouTube (watch / youtu.be / shorts / embed / live), Vimeo, `.mp4/.webm` qua https — còn lại không in gì. YouTube/Vimeo: ảnh bìa + nút phát là **link tới video gốc** (không JS vẫn xem được); JS thay bằng iframe `youtube-nocookie.com` / `player.vimeo.com?dnt=1` khi bấm. Ảnh bìa trống → ảnh `i.ytimg.com` (Vimeo: nền tối). mp4: `<video controls preload="none">`. |
| Logo | Nguồn thương hiệu: `Brand::get_all()` (cache theo thế hệ), logo → link trang thương hiệu, thương hiệu chưa có logo hiện tên. Băng chuyền dùng track / nút của Slider. Tuỳ chọn logo xám → màu khi rê chuột / focus. |
| Đếm ngược | Giờ nhập `YYYY-MM-DD HH:MM` theo múi giờ website (`wp_timezone`), sai định dạng → editor báo, website không in. Server in sẵn số còn lại (không nhảy bố cục), JS cập nhật mỗi giây; `role="timer"` (không đọc mỗi giây). Hết giờ: chữ thông báo hoặc ẩn. `dynamic` (không cache HTML). |
| Băng chuyền Sản phẩm / Bài viết | Control chung `displayControl()` + `carouselArrows()` ở `Element`; lưới thành track cuộn ngang có snap, số mục mỗi lần = "Số cột" từng thiết bị. Sản phẩm: áp cho chế độ không có tab danh mục. |
| Đăng ký nhận tin | `POST /saha/v1/newsletter`: rate limit 5 / 10 phút, honeypot, nonce. `Lead::subscribe()` — một lead mỗi email (đăng ký lại → "đã đăng ký"). Lead không số điện thoại không còn bị gộp nhầm theo số rỗng. |
| Lưới | CSS grid, số cột từng thiết bị, khoảng cách, căn dọc trong ô; tạo sẵn 3 Hộp. `tab` và `grid` được thêm vào danh sách cha hợp lệ của element nội dung (`CONTENT_PARENTS`); Hàng đặt được trong Tab. |

### Nonce cho form công khai trên trang cache

`GET /saha/v1/nonce` trả thêm `form` (nonce `saha_public_form`). Đăng ký nhận tin gửi nonce này trong body (`saha_nonce`) và **không** gửi header `X-WP-Nonce` → request chạy như khách nên nonce khớp cả khi trình duyệt đang đăng nhập (trước đó: admin đăng nhập bị "Kiểm tra cookie không thành công" vì nonce `wp_rest` của khách).

### Lệch so với thiết kế

- Thiết kế §15: "JS element chỉ nạp khi element có trên trang". Cơ chế `assets` của element đã có trong định nghĩa nhưng chưa được nạp ra website, và render cache bỏ qua `render()` của khối tĩnh → JS mới (phóng to, video, đếm ngược, nhận tin) nằm trong `elements.js` chung như Slider / Tabs (≈ +250 dòng, `defer`). Tách file theo element để lại cho mốc tối ưu.

## 3. Files

- Mới: `saha-core/includes/Builder/Elements/{Tabs,Tab,Gallery,Video,LogoCloud,Countdown,Newsletter,Grid}.php`, `api/routes/050-newsletter.php`.
- Sửa: `Elements/{Element,Products,Posts,Image,Row}.php`, `Builder/{ElementRegistry,Icons}.php` (icon `play`), `class-lead.php` (nguồn newsletter, `subscribe()`), `api/routes/005-nonce.php` (`form`), `class-qa.php` (route `/newsletter`), `public/assets/js/elements.js`, `public/assets/css/builder.css`. SAHA Core **1.28.0**.

## 4. Tests

| Kiểm tra | Kết quả |
|---|---|
| `tests/smoke.php` (thêm 8: nhận dạng link video + chặn link lạ, chia thời gian đếm ngược, giờ sai định dạng, Tabs ARIA + tab ẩn + bỏ thẻ HTML ở tiêu đề, Tabs trong editor không ẩn, quy tắc cha–con Tab / Ảnh / Lưới, Thư viện ảnh từ chối element khác; cập nhật số element 70 + danh sách element động) | 258 passed |
| `tests/http-smoke.php` (thêm: `/newsletter` thiếu nonce 403, email sai 422) | 39 passed, 2 skipped |
| `wp saha qa` | 96 đạt, 0 lỗi |
| Trang thử `/p27-element-thu/` (#174) | 1 H1; Tabs: → chuyển tab, panel đúng; Thư viện: 4 ảnh, phóng to "2 / 4", → "3 / 4", Esc trả focus; Video: bấm → iframe youtube-nocookie, có title; Logo: 5 thương hiệu dạng băng chuyền; Đếm ngược chạy (giây giảm); Lưới 3 cột; Sản phẩm 10 + Bài viết 5 dạng băng chuyền; Nhận tin: email sai báo tại chỗ, đăng ký đúng → thành công (cả khi đăng nhập và khách qua curl), lần 2 → "đã đăng ký"; mobile 375px không tràn ngang |
| Editor | 8 element có trong bảng Thêm; Tabs hiện cả 3 tab có nhãn |

Dữ liệu thử trên local: trang #174 "2.7 — element Phase 2 (thử)"; 2 lead "Đăng ký nhận tin" (`qa-nl-27@example.com`, `qa-nl-guest@example.com`).

## 5. Giới hạn

- Thư viện ảnh / Video / Đếm ngược không chạy JS trong canvas editor (canvas dựng HTML sau khi trang tải) — xem trên website / "Xem trang".
- Đăng ký nhận tin chỉ lưu email thành lead; chưa gửi email xác nhận / kết nối dịch vụ email marketing.
- Mốc tiếp: **2.8** QA Phase 2.
