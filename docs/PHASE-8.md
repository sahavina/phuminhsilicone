# PHASE 8 — QA

## 1. Goal

Spec §8 phase 8: QA mobile, browser, security, SEO, performance — và tiêu chí nghiệm thu spec §98.

QA thật **bắt buộc chạy trên site có WordPress + WooCommerce + Flatsome**. Repo này không chứa site, nên phase 8 chia hai phần:

| Phần | Trạng thái |
|---|---|
| **8A — Công cụ QA**: kiểm tra hệ thống trên site, test HTTP từ ngoài vào, seeder dữ liệu mẫu, checklist tổng hợp | ✅ xong (commit này) |
| **8B — Chạy QA** trên local/staging, sửa lỗi phát hiện được, nghiệm thu | ⏳ cần môi trường (mục 11) |

## 2. Architecture

```
                         ┌─────────────────────────── site local / staging ──────────────────────────┐
wp saha seed ──────────▶ │ 5 thương hiệu · 18 danh mục · 6 ứng dụng · 24 sản phẩm (Loctite 243,      │
  (dữ liệu mẫu,          │ Apollo A500…) · trang báo giá/liên hệ/thương hiệu · 4 bài blog ·            │
   đánh dấu _saha_seed)  │ báo giá + lead "[Mẫu]" (không gửi mail)                                    │
                         │                                                                             │
wp saha qa  ───────────▶ │ Qa::run() — CHỈ ĐỌC ─ môi trường · DB + index · quyền · cấu hình ·         │
SAHA → Kiểm tra hệ thống │ taxonomy · REST + permission_callback · relevance · SEO · bảo mật · hiệu năng│
                         └─────────────────────────────────────────────────────────────────────────────┘
                                          ▲ HTTP
php tests/http-smoke.php <url> [--write] ─┘   REST · mã HTTP · robots.txt · noindex · no-cache ·
                                              lỗi PHP lộ ra · số H1 · (--write) spec §57

php tests/smoke.php  ── máy dev, không cần WordPress: logic thuần (64 case)

docs/QA.md  ← tests/build-qa-checklist.py ← mục 8 của PHASE-1…7.md (188 test + ma trận thiết bị + nghiệm thu)
```

## 3. Files

| File | Vai trò |
|---|---|
| `saha-core/includes/class-qa.php` | ~50 kiểm tra tự động, chỉ đọc, an toàn trên production |
| `saha-core/includes/class-seeder.php` | dữ liệu mẫu idempotent + gỡ đúng dữ liệu mẫu |
| `saha-core/includes/class-cli.php` | `wp saha qa`, `seed`, `unseed`, `maintenance` |
| `saha-core/admin/views/qa.php` | trang **SAHA → Kiểm tra hệ thống** |
| `tests/http-smoke.php` | test HTTP từ ngoài vào (~30 case, `--write` thêm 9 case form) |
| `tests/build-qa-checklist.py` | sinh `docs/QA.md` từ các PHASE-*.md |
| `docs/QA.md` | checklist tổng hợp 188 test + ma trận thiết bị + bảng nghiệm thu |

Sửa: `class-admin.php` (menu Kiểm tra hệ thống), `class-loader.php` (đăng ký WP-CLI), `admin.css`, `saha-core.php` (1.7.0).

## 4. Database

Không đổi schema. `wp saha seed` ghi dữ liệu mẫu có đánh dấu:

| Loại | Đánh dấu | Gỡ bằng |
|---|---|---|
| Sản phẩm, trang, bài viết | post meta `_saha_seed` | `wp saha unseed` |
| Thương hiệu, danh mục, ứng dụng, chuyên mục | term meta `_saha_seed` (chỉ term do seeder **tạo mới**) | `wp saha unseed` |
| Báo giá, lead | tên bắt đầu bằng `[Mẫu] ` | `wp saha unseed` |

Term đã tồn tại trước khi seed (ví dụ bạn đã tạo "Loctite") **không** bị đánh dấu → không bị gỡ.

## 5. Code

83 file PHP (gồm `tests/`) pass `php -l`, JS pass `node --check`, `php tests/smoke.php` → 64/64.

## 6. Hooks

Filter mới: `saha_qa_results` (add-on bổ sung kiểm tra).
WP-CLI: `wp saha qa [--format=table|json|csv] [--strict]` · `wp saha seed [--with-crm] [--homepage-layout=<file>] [--set-front]` · `wp saha unseed [--yes]` · `wp saha maintenance`.

## 7. Security

- `Qa` chỉ đọc. Kiểm tra tìm kiếm tạm tắt search log để không làm bẩn thống kê. Chạy được ở production; trang admin cần `manage_saha_settings`.
- Seeder chỉ chạy qua WP-CLI (không có nút trong admin), hỏi xác nhận khi `WP_ENVIRONMENT_TYPE=production`, tắt email trong lúc seed.
- `unseed` chỉ xoá bản ghi có đánh dấu; hỏi xác nhận trừ khi có `--yes`.
- `http-smoke.php --write` ghi dữ liệu thật vào site đích → chỉ dùng local/staging; không có `--write` thì chỉ gửi GET.
- `Qa` kiểm tra thêm các lỗi cấu hình hay gặp khi lên production: debug display bật, "Discourage search engines" còn bật, upload file thực thi, đăng ký tài khoản với role administrator, "Xoá dữ liệu khi gỡ plugin" đang bật.

## 8. Testing

Toàn bộ checklist: **[docs/QA.md](QA.md)** — 188 test (27 có công cụ tự động phủ), ma trận 8 chiều rộng × 4 trình duyệt, bảng nghiệm thu spec §98.

Riêng các công cụ của phase này:

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | `wp saha seed --with-crm` trên site trống | tạo đủ dữ liệu, không gửi email |
| 2 | Chạy `wp saha seed` lần 2 | "Không có gì mới", không trùng sản phẩm/term/trang |
| 3 | `wp saha qa` sau khi seed | không có mục "Lỗi"; nhóm Tìm kiếm không còn "Bỏ qua" |
| 4 | SAHA → Kiểm tra hệ thống | cùng kết quả với `wp saha qa` |
| 5 | Role Sales mở trang Kiểm tra hệ thống | 403 |
| 6 | Bật log tìm kiếm, chạy `wp saha qa` | `wp_saha_search_logs` **không** thêm dòng |
| 7 | `php tests/http-smoke.php <url>` | 0 FAIL |
| 8 | `php tests/http-smoke.php <url> --write` | 0 FAIL; chạy lại trong 10 phút → các test POST báo "skip" do rate limit, không FAIL |
| 9 | `wp saha unseed --yes` | gỡ hết dữ liệu mẫu; term/sản phẩm tạo tay trước đó còn nguyên |
| 10 | `wp saha qa --strict` trên staging đã cấu hình xong | thoát mã 0 |

## 9. Installation

```bash
# Tại thư mục gốc WordPress (cũng là gốc repo)
wp plugin activate woocommerce saha-core
wp theme activate flatsome-child
wp rewrite structure '/%postname%/' --hard

wp saha seed --with-crm --homepage-layout=docs/layouts/homepage.ux.txt
wp saha qa

# Từ máy bất kỳ
php tests/http-smoke.php https://staging.example.com --write
```

Sau đó làm theo `docs/QA.md` mục 2 → 3 → 4.

## 10. Acceptance criteria

8A — công cụ:

- [x] Kiểm tra tự động trên site, chỉ đọc, có cả giao diện admin lẫn CLI.
- [x] Test HTTP từ ngoài vào, phủ các case spec §57 (form) và §58 (tìm kiếm).
- [x] Dữ liệu mẫu idempotent, gỡ được sạch, không đụng dữ liệu thật.
- [x] Checklist tổng hợp có ID, cột kết quả, ma trận thiết bị, bảng nghiệm thu spec §98.
- [x] 83 file PHP pass `php -l`, JS pass `node --check`, smoke 64/64.

8B — nghiệm thu (chưa chạy):

- [ ] `wp saha qa --strict` đạt trên staging.
- [ ] `php tests/http-smoke.php <staging> --write` đạt.
- [ ] 188 test trong `docs/QA.md` đạt hoặc có ghi chú ➖.
- [ ] Ma trận thiết bị & trình duyệt đạt.
- [ ] PageSpeed mobile đạt LCP < 2.5s, CLS < 0.1.

## 11. Để chạy 8B

Cần một site có đủ WordPress + WooCommerce + **Flatsome** (theme trả phí, cần file cài đặt có bản quyền của bạn). Hai cách:

1. **Staging/hosting sẵn có** → cho URL + tài khoản admin tạm thời; chạy `wp saha seed` / `wp saha qa` qua SSH nếu có, rồi `http-smoke.php` từ ngoài vào.
2. **Local trên máy này** — XAMPP đã có sẵn (Apache + MySQL + PHP 8.0). Cần tải WordPress, WooCommerce (wordpress.org) và WP-CLI (github.com/wp-cli), cộng file zip Flatsome của bạn.

Không có Flatsome vẫn test được toàn bộ plugin `saha-core` (REST, CRM, tìm kiếm, SEO, cron, seeder) — chỉ phần giao diện child theme phải chờ.

## 12. Kết quả 8B — plugin trên WordPress thật (30/09/2026)

### Môi trường

| Thành phần | Phiên bản |
|---|---|
| WordPress | 7.1.2 (vi) |
| WooCommerce | 11.1.2 — có `product_brand` native |
| PHP | 8.0.30 (XAMPP) |
| Database | MariaDB 10.4.32, collation `utf8mb4_unicode_520_ci` |
| Theme | Twenty Twenty-Five (**chưa có Flatsome** → child theme chưa test) |
| Plugin SEO | không có (test nhánh fallback) |

### Kết quả sau khi sửa

| Công cụ | Kết quả |
|---|---|
| `php tests/smoke.php` | **72 / 72** |
| `wp saha qa` | **63 đạt · 4 cảnh báo · 0 lỗi** — 4 cảnh báo đúng với môi trường local: chưa có Flatsome, chưa có plugin SEO, chưa nhập Zalo, chưa có Redis |
| `php tests/http-smoke.php http://localhost/saha --write` | **32 đạt · 0 lỗi · 3 bỏ qua** — bỏ qua do site ở thư mục con (robots.txt) và chưa có child theme |
| `debug.log` sau toàn bộ test | sạch (chỉ có log `wp_mail` thất bại có chủ đích — XAMPP không có SMTP) |

Kiểm tra thủ công trên trình duyệt (wp-admin): trang Kiểm tra hệ thống · danh sách báo giá · bulk đổi trạng thái · trang chi tiết (đổi trạng thái, gán sales, ghi chú, lịch sử khách) · form thương hiệu · 5 tab sản phẩm + repeater thông số · cache vô hiệu sau khi lưu sản phẩm · SEO fallback trang thương hiệu · noindex + canonical URL lọc · bộ lọc tình trạng + ứng dụng. Label của form gắn đúng (spec §39).

### Lỗi thật phát hiện và đã sửa

Không lỗi nào dưới đây bị `php -l` hay smoke test (không WordPress) bắt được — đúng lý do QA phải chạy trên site thật.

| # | Lỗi | Hậu quả nếu lên production | Sửa |
|---|---|---|---|
| 1 | `sanitize_callback => 'sanitize_title'` trong REST: tham số thứ 2 của `sanitize_title()` là `$fallback_title`, nhận nhầm `WP_REST_Request` | **`GET /products` fatal 500 ở mọi request** | `Api::sanitize_slug()` + test hồi quy |
| 2 | WordPress gọi `permission_callback` lần 2 trong `rest_send_allow_header()` | Rate limit đếm gấp đôi: khách bị chặn ở lần gửi báo giá thứ 3 thay vì thứ 6; tìm kiếm 15/phút thay vì 30 | Nhớ kết quả theo scope trong request + test hồi quy |
| 3 | Lọc tình trạng so sánh `_saha_availability = ''` trong khi sản phẩm để trống không có dòng meta | Lọc "Sẵn hàng" / "Hết hàng" **luôn rỗng** | `NOT EXISTS` + test hồi quy |
| 4 | (đi kèm #3) "Liên hệ" cũng fallback sang `_stock_status = instock` | Sửa #3 mà không sửa #4 thì lọc "Liên hệ" gộp nhầm mọi hàng có sẵn | "Liên hệ" không fallback |
| 5 | WooCommerce 11 đã có `product_brand` native với URL `/brand/` | Trang thương hiệu ở `/brand/…` thay vì `/thuong-hieu/…` (spec §7) | `register_taxonomy_args` đổi slug khi WooCommerce dùng slug mặc định; tôn trọng slug admin tự đặt |
| 6 | Role nền editor/author không có capability sản phẩm của WooCommerce | Content Manager / SEO Manager / Warehouse **không mở được màn hình sản phẩm** | Cấp `edit_products`… theo từng role; `wp saha qa` kiểm tra thêm |
| 7 | WooCommerce đã có ảnh thương hiệu (`thumbnail_id`), SAHA thêm field Logo thứ hai | Hai chỗ nhập logo, dữ liệu tách đôi | Dùng chung `thumbnail_id`, ẩn field/cột trùng; logo SAHA cũ làm dự phòng |
| 8 | Key mảng `'243'` bị PHP đổi thành int, gặp `strict_types` | `wp saha qa` fatal | Ép `(string)` |
| 9 | Seeder chỉ idempotent trong 10 phút (nhờ chống trùng) và đếm sai | Chạy lại sau 10 phút tạo báo giá mẫu trùng | Kiểm tra theo tên trước khi tạo |
| 10 | Lead sinh từ báo giá không có sản phẩm có khoảng trắng kép | Hiển thị xấu trong CRM | Ghép chuỗi theo phần có dữ liệu |

Và 3 báo sai của chính bộ kiểm tra đã sửa: robots.txt (WooCommerce chủ động chặn thư mục log riêng tư — đúng), route index `/saha/v1` của core, `has_shortcode()` khi shortcode do theme đăng ký.

### Ghi nhận khác

- **WooCommerce 11 bật "Store coming soon" mặc định** trên site mới → khách chưa đăng nhập không thấy sản phẩm. Phải tắt (WooCommerce → Settings → Site visibility) trước khi QA từ bên ngoài và trước khi go-live.
- REST `per_page` vượt giới hạn trả **400** (WordPress kiểm `maximum` của schema) thay vì bị kẹp về 50 như PHASE-3 mô tả. Kết quả tương đương về an toàn; `http-smoke.php` chấp nhận cả hai.
- `AbortError: Transition was skipped` trong console wp-admin đến từ View Transitions của WordPress 7.x khi chuyển trang nhanh, xuất hiện cả ở trang không nạp JS của SAHA.

### Còn lại của 8B

| Hạng mục | Cần |
|---|---|
| Toàn bộ test giao diện child theme (P1-05/06/13–20, P2 frontend, P3 autocomplete/filter UI, P4 modal/form, P5, P6 breadcrumb, P7 preload/emoji/defer) | File cài đặt **Flatsome** |
| Ma trận thiết bị × trình duyệt (`docs/QA.md` mục 2) | Flatsome |
| Rank Math / Yoast (P6) | Cài plugin SEO trên site local |
| Email thật | Plugin SMTP + tài khoản mail |
| PageSpeed / Core Web Vitals | Staging có domain công khai |

## 13. Site QA local

| | |
|---|---|
| URL | http://localhost/saha |
| Thư mục | `C:\xampp\htdocs\saha` |
| Database | `saha_local` (MariaDB của XAMPP, user `root`) |
| Tài khoản admin | trong `.env.local` ở gốc repo (không commit) |
| Plugin / theme | junction trỏ về repo — sửa code trong repo là site thấy ngay |
| WP-CLI | `C:\xampp\php\php.exe C:\xampp\php\wp-cli.phar` (chạy trong `C:\xampp\htdocs\saha`) |

Bật lại sau khi khởi động máy: mở XAMPP Control Panel → Start **Apache** và **MySQL** (hoặc chạy `C:\xampp\mysql\bin\mysqld.exe --standalone` và `C:\xampp\apache\bin\httpd.exe`).
