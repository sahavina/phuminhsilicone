# Tổng Kho Keo Dán SAHA

Website catalogue sản phẩm keo dán công nghiệp: tìm kiếm theo SKU / thương hiệu / công dụng, nhận yêu cầu báo giá, quản lý khách hàng tiềm năng.

**Công ty TNHH Thương mại Dịch vụ Trực tuyến SAHA** · tongkhokeodan.com

WordPress · WooCommerce · Flatsome + Child Theme · UX Builder · PHP 8 · MySQL/MariaDB

---

## Mục lục

1. [Repo này chứa gì](#1-repo-này-chứa-gì)
2. [Yêu cầu](#2-yêu-cầu)
3. [Cài môi trường local (Windows + XAMPP)](#3-cài-môi-trường-local-windows--xampp)
4. [Cài lên site có sẵn (staging / production)](#4-cài-lên-site-có-sẵn-staging--production)
5. [Cấu hình sau khi cài](#5-cấu-hình-sau-khi-cài)
6. [Kiểm tra cài đặt](#6-kiểm-tra-cài-đặt)
7. [Cập nhật code](#7-cập-nhật-code)
8. [Quy trình làm việc của team](#8-quy-trình-làm-việc-của-team)
9. [Xử lý sự cố thường gặp](#9-xử-lý-sự-cố-thường-gặp)
10. [Tài liệu](#10-tài-liệu)
11. [Trạng thái dự án](#11-trạng-thái-dự-án)

---

## 1. Repo này chứa gì

Repo **chỉ** chứa code do dự án viết. WordPress, WooCommerce, Flatsome và dữ liệu site cài riêng.

```
wp-content/
  plugins/saha-core/        Business layer: taxonomy, sản phẩm, tìm kiếm, báo giá, lead,
                            CRM, REST API, SEO, cache, WP-CLI. Chạy được không cần Flatsome.
  themes/flatsome-child/    Giao diện: template, CSS, JS, UX Builder element. CẦN Flatsome.
docs/                       Kiến trúc, tài liệu từng phase, checklist QA, layout UX Builder mẫu
tests/                      Smoke test, HTTP test, script sinh checklist
```

**Không bao giờ commit:** `wp-config.php`, `.env*`, `wp-content/uploads/`, file `.sql`, `debug.log`, WordPress core, plugin/theme của bên thứ ba. `.gitignore` đã chặn sẵn — đừng dùng `git add -f` cho các file này.

---

## 2. Yêu cầu

| Thành phần | Tối thiểu | Đã kiểm tra |
|---|---|---|
| PHP | 8.0 | 8.0.30 |
| WordPress | 6.0 | 7.1.2 |
| WooCommerce | 9.6 (có thương hiệu native) | 11.1.2 |
| MySQL / MariaDB | collation `utf8mb4_*_ci` (không dùng `_bin`) | MariaDB 10.4.32, `utf8mb4_unicode_520_ci` |
| Flatsome | theme **trả phí**, cần file cài đặt có bản quyền | chưa kiểm tra (xem [mục 11](#11-trạng-thái-dự-án)) |
| WP-CLI | khuyến nghị | 2.12.0 |
| Git | — | — |

PHP extension cần: `mysqli`, `curl`, `mbstring`, `zip`, `openssl`. Nên bật thêm `gd` hoặc `imagick` để WordPress tạo ảnh thumbnail.

> Collation `_ci` quan trọng: nó giúp tìm "keo" ra cả "kéo". Nếu database dùng `utf8mb4_bin`, tìm kiếm tiếng Việt sẽ phân biệt dấu.

---

## 3. Cài môi trường local (Windows + XAMPP)

Các bước dưới đây đã chạy thật để dựng site QA của dự án. Mất khoảng 15 phút.

### 3.1. Clone repo

```bash
git clone git@github.com:sahavina/phuminhsilicone.git C:/Projects/phuminhsilicone
```

### 3.2. Bật Apache + MySQL

Mở **XAMPP Control Panel** → Start **Apache** và **MySQL**.

### 3.3. Cài WP-CLI (một lần cho máy)

Tải `wp-cli.phar` từ [wp-cli.org](https://wp-cli.org/#installing) vào `C:\xampp\php\`. Từ đây gọi tắt là `wp`:

```powershell
# PowerShell — dán vào profile để dùng lâu dài
function wp { & C:\xampp\php\php.exe C:\xampp\php\wp-cli.phar @args }
```

```bash
# Git Bash
alias wp='/c/xampp/php/php.exe /c/xampp/php/wp-cli.phar'
```

### 3.4. Tạo database

```bash
C:/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE saha_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

### 3.5. Cài WordPress

Chạy trong thư mục site mới, ví dụ `C:\xampp\htdocs\saha`:

```bash
mkdir C:/xampp/htdocs/saha && cd C:/xampp/htdocs/saha
wp core download --locale=vi
wp config create --dbname=saha_local --dbuser=root --dbpass= --dbhost=127.0.0.1 --dbcharset=utf8mb4 --locale=vi
```

Mở `wp-config.php`, thêm **trước** dòng `/* That's all, stop editing! */`:

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );       // ghi lỗi vào wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );  // không in lỗi ra trang
```

Tạo `wp-cli.yml` trong thư mục site để WP-CLI ghi được `.htaccess`:

```yaml
path: .
apache_modules:
  - mod_rewrite
```

Cài site — **tự đặt mật khẩu mạnh**, không dùng lại mật khẩu thật, lưu vào `.env.local` của repo (file này đã bị `.gitignore` chặn):

```bash
wp core install --url=http://localhost/saha --title="SAHA (local)" \
  --admin_user=<tên-đăng-nhập> --admin_password=<mật-khẩu> --admin_email=you@saha.test --skip-email
wp option update timezone_string 'Asia/Ho_Chi_Minh'
wp rewrite structure '/%postname%/' --hard
```

### 3.6. Cài WooCommerce và tắt chế độ "coming soon"

```bash
wp plugin install woocommerce --activate
wp option update woocommerce_coming_soon no
```

> WooCommerce ≥ 9 bật **"Store coming soon"** mặc định: khách chưa đăng nhập chỉ thấy trang "sắp ra mắt", không thấy sản phẩm. Luôn tắt khi dev/test — và **nhớ tắt khi go-live**.

### 3.7. Nối plugin và theme từ repo vào site

Dùng **liên kết thư mục** thay vì copy — sửa code trong repo là site thấy ngay, không có hai bản code lệch nhau.

**Windows (PowerShell, không cần quyền admin):**

```powershell
$repo = 'C:\Projects\phuminhsilicone\wp-content'
$site = 'C:\xampp\htdocs\saha\wp-content'
New-Item -ItemType Junction -Path "$site\plugins\saha-core"     -Target "$repo\plugins\saha-core"
New-Item -ItemType Junction -Path "$site\themes\flatsome-child" -Target "$repo\themes\flatsome-child"
```

**macOS / Linux:**

```bash
ln -s ~/Projects/phuminhsilicone/wp-content/plugins/saha-core     <site>/wp-content/plugins/saha-core
ln -s ~/Projects/phuminhsilicone/wp-content/themes/flatsome-child <site>/wp-content/themes/flatsome-child
```

### 3.8. Kích hoạt

```bash
wp plugin activate saha-core
```

Kích hoạt plugin sẽ tự tạo 4 bảng `wp_saha_*`, 4 role, cấu hình mặc định và lịch dọn log.

**Nếu có Flatsome:** cài theme cha rồi mới bật child theme:

```bash
wp theme install <đường-dẫn>/flatsome.zip
wp theme activate flatsome-child
```

Chưa có Flatsome vẫn làm việc được với toàn bộ plugin (REST, CRM, tìm kiếm, SEO…) trên theme mặc định — chỉ phần giao diện là không hiện.

### 3.9. Tạo dữ liệu mẫu

```bash
wp saha seed --with-crm
```

Tạo 5 thương hiệu, cây danh mục, 6 ứng dụng, 24 sản phẩm (có Loctite 243, Apollo Silicone A500 để test tìm kiếm), trang Báo giá / Liên hệ / Thương hiệu, 4 bài blog, vài báo giá và lead tên `[Mẫu] …`. Chạy lại không tạo trùng. Không gửi email.

Có Flatsome thì tạo luôn trang chủ từ layout mẫu:

```bash
wp saha seed --homepage-layout=C:/Projects/phuminhsilicone/docs/layouts/homepage.ux.txt --set-front
```

Gỡ toàn bộ dữ liệu mẫu (chỉ gỡ thứ có đánh dấu mẫu, không đụng dữ liệu thật):

```bash
wp saha unseed
```

### 3.10. Xong

Mở http://localhost/saha/wp-admin → menu **SAHA** → **Kiểm tra hệ thống**. Không có mục **Lỗi** là cài đúng. Xem thêm [mục 6](#6-kiểm-tra-cài-đặt).

---

## 4. Cài lên site có sẵn (staging / production)

Chỉ đưa lên server **hai thư mục** — không đưa `docs/`, `tests/`, `.git/`:

```
wp-content/plugins/saha-core/
wp-content/themes/flatsome-child/
```

Ví dụ với `rsync` qua SSH:

```bash
rsync -az --delete wp-content/plugins/saha-core/     user@server:/path/to/site/wp-content/plugins/saha-core/
rsync -az --delete wp-content/themes/flatsome-child/ user@server:/path/to/site/wp-content/themes/flatsome-child/
```

Hoặc nén hai thư mục thành zip và cài qua **Plugins → Add New → Upload** / **Appearance → Themes → Upload**.

Trên server:

1. **Backup database** trước khi kích hoạt hoặc cập nhật (plugin có migration tạo/sửa bảng).
2. Đảm bảo đã có **WooCommerce ≥ 9.6** và **Flatsome**.
3. Kích hoạt **SAHA Core**, rồi kích hoạt **Flatsome Child — SAHA**.
4. Settings → Permalinks → chọn *Post name* → **Save** (bắt buộc để `/thuong-hieu/…` hoạt động).
5. Làm tiếp [mục 5](#5-cấu-hình-sau-khi-cài).

Production **không** chạy `wp saha seed` (lệnh sẽ hỏi xác nhận khi `WP_ENVIRONMENT_TYPE=production`).

`wp-config.php` production:

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
```

---

## 5. Cấu hình sau khi cài

### 5.1. SAHA → Cấu hình

| Mục | Ghi chú |
|---|---|
| Hotline miền Bắc / Nam | dùng ở header, CTA, sticky mobile, footer — không sửa trong template |
| Số Zalo | để trống thì nút Zalo tự ẩn |
| Email nhận yêu cầu báo giá | để trống sẽ gửi về email admin |
| Trang yêu cầu báo giá | URL trang có `[saha_quote_form]` (seed tạo sẵn `/bao-gia/`) |
| Chế độ catalogue | bật = ẩn giá và giỏ hàng, dùng nút báo giá |
| Từ đồng nghĩa tìm kiếm | mỗi dòng: `keo kính = silicone, keo nhôm kính` |
| Nguồn schema sản phẩm | để **Tự động** |

### 5.2. Trang và nội dung

| Việc | Cách làm |
|---|---|
| Trang báo giá | page với `[saha_quote_form title="Yêu cầu báo giá"]` |
| Trang liên hệ | page với `[saha_contact_form title="Liên hệ tư vấn"]` |
| Trang thương hiệu | page `/thuong-hieu/` với `[saha_brand_grid]` |
| Trang chủ | page mới → *Edit with UX Builder* → dán [docs/layouts/homepage.ux.txt](docs/layouts/homepage.ux.txt) → chọn ảnh hero → Settings → Reading → đặt làm Homepage |
| Footer | UX Blocks → dán [docs/layouts/footer-block.ux.txt](docs/layouts/footer-block.ux.txt) → Flatsome → Footer chọn block này |
| Blog | Settings → Permalinks → Custom `/tin-tuc/%postname%/` |

Danh sách đầy đủ shortcode / UX element: [flatsome-child/README.md](wp-content/themes/flatsome-child/README.md).

### 5.3. Tài khoản cho nhân viên

| Role | Dùng cho | Quyền |
|---|---|---|
| Sales | nhân viên kinh doanh | báo giá, lead, báo cáo — không sửa cấu hình |
| Content Manager | biên tập nội dung | sản phẩm, thương hiệu, danh mục |
| SEO Manager | SEO | sửa sản phẩm/thương hiệu, báo cáo — không xoá sản phẩm |
| Warehouse | kho | cập nhật sản phẩm, tồn kho — không xoá, không sửa danh mục |

Users → Add New → chọn role. Mỗi người một tài khoản riêng; không dùng chung tài khoản admin.

### 5.4. Plugin khuyến nghị (production)

| Plugin | Vì sao | Cấu hình cần làm |
|---|---|---|
| **Rank Math** *hoặc* Yoast (chỉ một) | meta, sitemap, schema | bật Breadcrumbs; bật sitemap cho *Thương hiệu* và *Ứng dụng*, tắt *Thẻ sản phẩm* |
| Plugin SMTP (WP Mail SMTP / FluentSMTP) | email báo giá mặc định từ hosting thường vào spam | cấu hình SMTP, gửi thử |
| LiteSpeed Cache (nếu hosting LiteSpeed) | page cache, tối ưu ảnh | xem [PHASE-7 §9](docs/PHASE-7.md#9-installation): loại trừ `saha_form` và `/wp-json/saha/v1/nonce`, **không** delay JS `saha-main` |
| Redis Object Cache | cache nhanh hơn | không cần cấu hình gì thêm cho SAHA |

---

## 6. Kiểm tra cài đặt

| Lệnh | Chạy ở đâu | Kiểm tra gì |
|---|---|---|
| **SAHA → Kiểm tra hệ thống** hoặc `wp saha qa` | trên site | môi trường, bảng + index, quyền, cấu hình, REST, tìm kiếm, SEO, bảo mật, cron — chỉ đọc, an toàn trên production |
| `php tests/smoke.php` | máy dev, chỉ cần PHP | 72 case logic: validate, sanitize, tìm kiếm, cache, SEO, rate limit |
| `php tests/http-smoke.php <url>` | máy bất kỳ có PHP + curl | REST, mã HTTP, robots, noindex, no-cache từ ngoài vào — chỉ GET |
| `php tests/http-smoke.php <url> --write` | **chỉ local/staging** | thêm test form báo giá/liên hệ (tạo 2–3 bản ghi `[Mẫu] QA`) |

Trên Windows, nếu `php` chưa có trong PATH, thay `php` bằng `C:/xampp/php/php.exe`.

Kết quả mong đợi trên site local có dữ liệu mẫu:

```
wp saha qa                                    → Lỗi: 0
php tests/smoke.php                           → 72 passed, 0 failed
php tests/http-smoke.php http://localhost/saha --write → 0 failed
```

Checklist QA đầy đủ cho nghiệm thu: [docs/QA.md](docs/QA.md).

---

## 7. Cập nhật code

```bash
git pull
```

Với site dùng liên kết thư mục (mục 3.7) thì code mới có hiệu lực ngay. Sau đó **mở wp-admin một lần bằng tài khoản admin**: plugin tự chạy migration database, cập nhật role và flush permalink khi phát hiện phiên bản mới.

Trên staging/production: **backup database → rsync (mục 4) → mở wp-admin → `wp saha qa`**.

Rollback: `git revert` / deploy lại bản cũ + khôi phục bản backup database chụp trước khi cập nhật.

---

## 8. Quy trình làm việc của team

### Branch

```bash
git checkout main && git pull
git checkout -b feature/ten-ngan-gon      # hoặc fix/…, docs/…
```

- Không commit thẳng lên `main`.
- Một branch = một việc. Tên tiếng Anh không dấu, gạch nối.
- Mở Pull Request vào `main`; ít nhất một người review trước khi merge.

### Trước khi mở Pull Request

```bash
# 1. Cú pháp PHP (Git Bash)
for f in $(git diff --name-only main -- '*.php'); do php -l "$f"; done

# 2. Smoke test
php tests/smoke.php

# 3. Trên site local
wp saha qa
php tests/http-smoke.php http://localhost/saha --write

# 4. debug.log không có lỗi mới
tail -n 50 <site>/wp-content/debug.log
```

Sửa checklist test của một phase → sửa trong `docs/PHASE-*.md`, rồi chạy `python tests/build-qa-checklist.py` để sinh lại `docs/QA.md` (không sửa tay file này).

### Quy ước code

- **Không sửa** WordPress, WooCommerce, Flatsome core. Tuỳ biến bằng hook, filter, template override.
- Business logic → plugin `saha-core`. Giao diện → `flatsome-child`. `functions.php` chỉ nạp file.
- Hàm prefix `saha_`, class trong namespace `Saha\Core`, CSS class prefix `.saha-`, text domain `saha-core` / `flatsome-child`.
- Mọi SQL có input → `$wpdb->prepare()`. Mọi output → `esc_html` / `esc_attr` / `esc_url`.
- Mọi thao tác ghi trong admin → kiểm tra capability **và** nonce. Kiểm tra quyền bằng `current_user_can( 'manage_saha_…' )`, không so tên role.
- Không hardcode domain, hotline, Zalo, email — đọc từ SAHA → Cấu hình.
- Thay đổi database → thêm file mới `database/migrations/00N-*.php` + tăng `SAHA_CORE_DB_VERSION`. Không sửa migration đã phát hành.
- Chi tiết: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) mục Q (những điểm cần tránh).

---

## 9. Xử lý sự cố thường gặp

| Hiện tượng | Nguyên nhân | Cách xử lý |
|---|---|---|
| Khách không thấy sản phẩm, chỉ thấy trang "Sắp ra mắt" | WooCommerce "Store coming soon" | `wp option update woocommerce_coming_soon no` hoặc WooCommerce → Settings → Site visibility |
| `/thuong-hieu/…` hoặc `/ung-dung/…` báo 404 | permalink chưa flush | Settings → Permalinks → Save, hoặc `wp rewrite flush --hard` |
| Thương hiệu nằm ở `/brand/…` thay vì `/thuong-hieu/…` | permalink chưa flush sau khi cập nhật plugin | mở wp-admin bằng tài khoản admin một lần (plugin tự flush), hoặc Settings → Permalinks → Save |
| Content Manager / Warehouse không mở được màn hình sản phẩm | role chưa được cập nhật quyền | như trên — mở wp-admin bằng admin để plugin cập nhật role |
| Gửi báo giá báo "Bạn thao tác quá nhanh" khi đang test | rate limit 5 lần / 10 phút / IP | chờ 10 phút hoặc `wp transient delete --all` (chỉ trên local) |
| Báo giá lưu được nhưng không có email | server không gửi được mail | cài plugin SMTP; lỗi được ghi ở log kênh `mail` |
| Trang trắng / "lỗi nghiêm trọng" | lỗi PHP | xem `wp-content/debug.log` (cần `WP_DEBUG_LOG`) |
| `robots.txt` 404 trên local | site nằm trong thư mục con (`/saha/`); WordPress chỉ phục vụ robots.txt ở gốc domain | bình thường; dùng `wp saha qa` để kiểm robots |
| Tìm "keo" không ra "kéo" | database collation `_bin` | chuyển bảng sang `utf8mb4_unicode_ci` (backup trước) |
| `python tests/build-qa-checklist.py` lỗi `UnicodeEncodeError` | console Windows cp1252 | dùng bản script mới nhất trong repo (đã xử lý), hoặc `set PYTHONIOENCODING=utf-8` |

---

## 10. Tài liệu

| File | Nội dung |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | kiến trúc tổng thể, data model, taxonomy, API, bảo mật, SEO, hiệu năng, điểm cần tránh |
| [docs/PHASE-1.md](docs/PHASE-1.md) … [docs/PHASE-8.md](docs/PHASE-8.md) | từng phase: mục tiêu, file, database, hook, bảo mật, test, cài đặt, nghiệm thu |
| [docs/QA.md](docs/QA.md) | checklist QA tổng hợp: 188 test, ma trận thiết bị, bảng nghiệm thu |
| [docs/layouts/](docs/layouts/) | layout UX Builder mẫu: trang chủ, footer |
| [saha-core/README.md](wp-content/plugins/saha-core/README.md) | plugin: file, hook, REST API, database, capability |
| [flatsome-child/README.md](wp-content/themes/flatsome-child/README.md) | theme: file, shortcode, UX element, JS API, asset |

REST API: `/wp-json/saha/v1/` — `search`, `products`, `brands`, `quote`, `contact`, `nonce`. Chi tiết ở [saha-core/README.md](wp-content/plugins/saha-core/README.md#api).

WP-CLI: `wp saha qa [--strict]` · `wp saha seed [--with-crm] [--homepage-layout=<file>] [--set-front]` · `wp saha unseed` · `wp saha maintenance` · `wp help saha`.

---

## 11. Trạng thái dự án

| Phase | Nội dung | Trạng thái |
|---|---|---|
| 1 | Foundation: plugin, child theme, cấu hình, role, bảo mật, migration | ✅ |
| 2 | Catalogue: thương hiệu, ứng dụng, field sản phẩm, trang sản phẩm/thương hiệu | ✅ |
| 3 | Tìm kiếm + bộ lọc | ✅ |
| 4 | Báo giá, lead, email, CRM trong admin | ✅ |
| 5 | Trang chủ UX Builder | ✅ |
| 6 | SEO | ✅ |
| 7 | Hiệu năng | ✅ |
| 8 | QA | 🟡 xem dưới |

**Đã QA trên WordPress 7.1.2 + WooCommerce 11.1.2 thật:** toàn bộ plugin `saha-core` — REST, form, CRM, tìm kiếm, bộ lọc, SEO fallback, cache, quyền, cron. 10 lỗi phát hiện khi chạy thật đã được sửa ([PHASE-8 §12](docs/PHASE-8.md#12-kết-quả-8b--plugin-trên-wordpress-thật-30092026)).

**Chưa QA:**

- Giao diện child theme trên Flatsome (khoảng 70 test trong [docs/QA.md](docs/QA.md) + ma trận thiết bị/trình duyệt) — **cần file cài đặt Flatsome**. Tên hook và markup của Flatsome trong code chưa được đối chiếu với bản thật; hãy coi phần giao diện là chưa nghiệm thu.
- Rank Math / Yoast, email qua SMTP thật, PageSpeed trên staging có domain công khai.

Plugin: **SAHA Core 1.7.2** · Database schema **1.2.0**.
