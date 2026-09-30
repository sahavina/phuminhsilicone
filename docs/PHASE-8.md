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
