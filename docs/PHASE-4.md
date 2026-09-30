# PHASE 4 — Quote & Lead

## 1. Goal

- Form **Yêu cầu báo giá** (spec §11) và **Liên hệ** (spec §32), không phụ thuộc Contact Form 7.
- Lưu vào `wp_saha_quotes` / `wp_saha_leads`, email thông báo qua `wp_mail()`.
- Admin CRM mini (spec §14): danh sách có search / filter / pagination / bulk action, gán sales, đổi trạng thái, ghi chú, lịch sử khách hàng, báo cáo.
- Dashboard: báo giá mới, lead mới, top sản phẩm được hỏi, từ khoá phổ biến (spec §86).

## 2. Architecture

```
Frontend                                        Backend (saha-core)
─────────                                       ───────────────────
quote/form.php ──┐                              POST /saha/v1/quote ──┐
contact/form.php ┤── quote-form.js ── REST ──▶  POST /saha/v1/contact ┤
quote/modal.php ─┘       │                                            │
                         └ (không có JS) ──▶  admin-post.php          │
                                              Form_Handler ───────────┤
                                                                      ▼
                         rate limit → nonce → honeypot → validate → chống trùng
                                                                      │
                                              Quote::create ──────────┤
                                                 └ saha_quote_created ├─▶ Lead (nguồn quote)
                                                                      ├─▶ Mailer → wp_mail
                                              Lead::create ───────────┘
                                                 └ saha_lead_created ──▶ Mailer (chỉ nguồn contact)

wp-admin → SAHA
  Crm ── Crm_Table (WP_List_Table) ── Repository (prepare, whitelist cột)
      ── crm-detail: trạng thái, sales, ghi chú, Customer::history(phone)
      ── crm-reports
```

Mỗi yêu cầu báo giá **tự sinh một lead nguồn `quote`**, nên danh sách "Khách hàng tiềm năng" là nơi duy nhất sales cần theo dõi mọi nguồn. "Liên hệ" trong menu là lead lọc theo `source=contact` — không có bảng riêng.

## 3. Files

Thêm mới — plugin:

| File | Vai trò |
|---|---|
| `includes/class-quote.php` | validate, chống trùng, create, query, trạng thái, gán, ghi chú, top sản phẩm |
| `includes/class-lead.php` | tương tự cho lead; tự tạo lead từ mỗi báo giá |
| `includes/class-repository.php` | query có lọc/phân trang, update hàng loạt, ghi chú JSON, đếm theo trạng thái |
| `includes/class-customer.php` | chuẩn hoá SĐT, lịch sử quote + lead theo SĐT |
| `includes/class-mailer.php` | `wp_mail` + template, theme override được |
| `includes/class-form-handler.php` | đường dự phòng không-JS qua `admin-post.php` |
| `includes/class-crm.php` | menu, xử lý action (PRG), render list/chi tiết/báo cáo |
| `includes/tables/class-crm-table.php` | `WP_List_Table` dùng chung cho quote và lead |
| `api/routes/005-nonce.php` | cấp nonce `wp_rest` mới cho trang bị page cache |
| `api/routes/040-forms.php` | `POST /quote`, `POST /contact` |
| `database/migrations/005-add-notes-columns.php` | cột `notes` cho quotes và leads |
| `templates/emails/quote-admin.php` · `contact-admin.php` | template email |
| `admin/views/crm-list.php` · `crm-detail.php` · `crm-reports.php` | view |

Thêm mới — theme: `template-parts/quote/form.php`, `quote/modal.php`, `contact/form.php`, `assets/css/form.css`.
Thêm mới — repo: `tests/smoke.php`.

Sửa: `quote-form.js` (thay scaffold bằng bản đầy đủ), `main.js` (sửa nonce + retry, fallback nút báo giá), `sticky-cta.php`, `inc/enqueue.php`, `inc/woocommerce.php`, `inc/shortcodes.php`, `inc/ux-elements.php`, `class-loader.php`, `class-admin.php`, `class-settings.php`, `includes/functions.php`, `uninstall.php`, `admin.css`, `saha-core.php` (1.3.0 / DB 1.2.0).

## 4. Database

Migration `005`: thêm `notes longtext NULL` vào `wp_saha_quotes` và `wp_saha_leads`. Dùng `ALTER TABLE` **có kiểm tra cột trước** — chạy lại nhiều lần vẫn an toàn, không đụng dữ liệu cũ.

Ghi chú lưu dạng JSON `[{user_id, note, created_at}]`, giữ tối đa 100 ghi chú mỗi bản ghi.

Thời gian lưu bằng `current_time('mysql')` và hiển thị bằng `date_i18n()` — theo múi giờ site (spec §91).

`SAHA_CORE_DB_VERSION` → `1.2.0`.

## 5. Code

67 file PHP pass `php -l` (PHP 8.0), 5 file JS pass `node --check`, `tests/smoke.php` 28/28.

## 6. Hooks

Action: `saha_quote_created` (spec §63), `saha_quote_status_changed`, `saha_quote_assigned`, `saha_lead_created`.
Filter: `saha_quote_validate`, `saha_contact_validate`, `saha_mail_enabled`, `saha_mail_headers`, `saha_theme_quote_show_b2b_fields`.

REST: `GET /nonce`, `POST /quote`, `POST /contact`.
Shortcode / UX element: `[saha_quote_form]`, `[saha_contact_form]`.
Setting mới: *Trang yêu cầu báo giá* (`quote_page_url`).
JS event: `saha:quote_submit`, `saha:contact_submit` (spec §49).

Email template override: `{child-theme}/saha-core/emails/quote-admin.php`.

## 7. Security

| Lớp | Cách làm |
|---|---|
| Rate limit | 5 lần / 10 phút mỗi form theo hash(IP + salt) — cả REST lẫn admin-post |
| Nonce (CSRF) | REST: header `X-WP-Nonce` action `wp_rest`. Không-JS: field `saha_nonce`. Nonce **không** được coi là authorization (spec §93) |
| Honeypot | field `saha_hp_email`; bot điền vào sẽ nhận "thành công giả" để không học được cách vượt |
| Validate | họ tên bắt buộc, SĐT 8–15 chữ số (không ép định dạng — spec §65), email qua `is_email()`, giới hạn độ dài từng field |
| Product | chỉ nhận `product_id`; tên + SKU **luôn đọc lại từ DB**; post không phải product đã publish bị từ chối (spec §78) |
| Source URL | chỉ giữ URL cùng domain với site — chặn open redirect ở luồng không-JS |
| Chống trùng | cùng SĐT + cùng sản phẩm trong 10 phút → trả bản ghi cũ, không tạo mới (spec §84) |
| SQL | `$wpdb->insert/update/prepare`; tên cột sort/search lấy từ whitelist trong code |
| Admin | mọi thao tác ghi: capability + nonce, xử lý ở `load-{page}` rồi redirect (PRG) |
| Gán sales | user nhận phải có capability `manage_saha_quotes` / `manage_saha_leads` — kiểm tra theo capability, không theo tên role |
| Email | chặn CRLF injection trong tiêu đề; `Reply-To` chỉ đặt khi email khách hợp lệ; lỗi gửi mail chỉ ghi log, không làm mất dữ liệu đã lưu |

### Lỗi đã sửa trong phase này (ảnh hưởng Phase 1–3)

`main.js` gửi `X-WP-Nonce` với nonce action `saha_public_form`. Core WordPress (`rest_cookie_check_errors`) kiểm tra **mọi** `X-WP-Nonce` theo action `wp_rest`, kể cả với khách chưa đăng nhập → mọi request REST từ frontend, gồm cả autocomplete Phase 3, sẽ bị **403**. Đã đổi sang `wp_rest`.

Kèm theo: trang phục vụ từ LiteSpeed/Cloudflare cache có thể mang nonce đã hết hạn (spec §27). `SAHA.api()` giờ nhận diện 403 do nonce, gọi `GET /nonce` (không kèm header cũ) để lấy nonce mới rồi thử lại **một** lần.

## 8. Testing

Tự động: `php tests/smoke.php` → **28/28 pass**, gồm các case spec §57: valid submit, empty phone, invalid email, XSS input, SQL injection input, product_id giả mạo / không tồn tại / trỏ tới post thường.

Checklist thủ công:

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | Update plugin lên 1.3.0 | migration 005 chạy, có cột `notes`; dữ liệu cũ nguyên vẹn |
| 2 | Chạy lại migration (xoá `005` khỏi `saha_core_migrations_ran`) | không lỗi, không thêm cột trùng |
| 3 | Trang sản phẩm → "Yêu cầu báo giá" | modal mở, hiện tên + SKU sản phẩm, focus vào ô đầu |
| 4 | Esc / click nền / nút × | modal đóng |
| 5 | Submit hợp lệ | 201, thông báo xanh, `wp_saha_quotes` + `wp_saha_leads` mỗi bảng thêm 1 dòng |
| 6 | Submit lần 2 cùng SĐT + sản phẩm trong 10 phút | 200 `duplicate: true`, **không** thêm dòng |
| 7 | Bấm submit liên tục | nút bị disable, chỉ 1 request |
| 8 | Bỏ trống SĐT | lỗi hiện dưới ô, focus nhảy về ô lỗi |
| 9 | Email sai định dạng | lỗi 422 với `errors.email` |
| 10 | Sửa `product_id` trong DevTools thành ID bài viết | 422 `errors.product_id` |
| 11 | Sửa `product_name` trong DevTools | DB vẫn lưu tên thật của sản phẩm |
| 12 | Gửi request không có `X-WP-Nonce` hợp lệ | 403 |
| 13 | Gửi 6 lần trong 10 phút | lần 6 trả 429, JS hiện thông báo thân thiện |
| 14 | Điền field honeypot | trả "thành công", **không** lưu DB |
| 15 | Tắt JS rồi submit | POST admin-post.php → redirect về trang cũ, hiện thông báo |
| 16 | Tắt JS, sửa `source_url` thành domain khác | redirect về trang chủ, không bị open redirect |
| 17 | Purge cache, đợi nonce hết hạn (hoặc sửa nonce trong HTML), submit | tự lấy nonce mới, gửi thành công |
| 18 | Email admin | nhận được, `Reply-To` là email khách, có link mở trong quản trị |
| 19 | Để trống "Email nhận báo giá" | gửi tới admin email |
| 20 | Plugin SMTP lỗi | dữ liệu vẫn lưu, có log `mail` trong `wp_saha_logs` |
| 21 | SAHA → Yêu cầu báo giá | danh sách, bong bóng đếm "Mới" trên menu |
| 22 | Tìm theo SĐT / tên sản phẩm | lọc đúng |
| 23 | Lọc trạng thái + sales + khoảng ngày | kết hợp đúng, giữ lọc khi phân trang |
| 24 | Bulk "Chuyển sang: Đã liên hệ" | cập nhật, thông báo số bản ghi, giữ bộ lọc |
| 25 | Bulk "Gán" với sales đã chọn | cột Sales cập nhật |
| 26 | Gán cho user **không** có `manage_saha_quotes` (sửa DOM) | 0 bản ghi được cập nhật |
| 27 | Chi tiết → thêm ghi chú | hiện tên người ghi + giờ theo múi giờ site |
| 28 | Chi tiết khách đã hỏi giá 2 lần | "Lịch sử khách hàng" hiện lần còn lại |
| 29 | User role Sales | thấy Báo giá, Lead, Liên hệ, Báo cáo; **không** thấy Cấu hình |
| 30 | User role Content Manager mở thẳng URL `saha-quotes` | 403 (spec §57 "unauthorized admin") |
| 31 | Bulk action với `_wpnonce` sai | 403 |
| 32 | Menu "Liên hệ" | lead lọc sẵn `source=contact` |
| 33 | Báo cáo | số theo trạng thái, tỷ lệ chốt, top sản phẩm, top từ khoá (nếu bật log) |
| 34 | Mobile 375px | modal toàn màn hình, form 1 cột, sticky CTA không che nút gửi |
| 35 | Trang không phải sản phẩm, bấm "Báo giá" ở sticky CTA | đi tới trang báo giá (hoặc hotline nếu chưa cấu hình) |
| 36 | Xem source trang chủ | `form.css`, `quote-form.js` **không** được load |

## 9. Installation

1. Pull code (plugin lên 1.3.0), vào admin một lần để migration 005 chạy.
2. Tạo trang `/bao-gia/` với `[saha_quote_form title="Yêu cầu báo giá"]` (hoặc UX element **SAHA Quote Form**).
3. Tạo trang `/lien-he/` với `[saha_contact_form title="Liên hệ tư vấn"]`.
4. SAHA → Cấu hình: điền *Trang yêu cầu báo giá* = URL trang ở bước 2, *Email nhận yêu cầu báo giá*, *Email liên hệ*.
5. Tạo user role **Sales** cho nhân viên kinh doanh để có thể gán việc.
6. Khuyến nghị: cài plugin SMTP (WP Mail SMTP / FluentSMTP) — `wp_mail` mặc định trên hosting thường vào spam.
7. Nếu dùng LiteSpeed/Cloudflare: loại trừ `/wp-json/saha/v1/nonce` khỏi cache (endpoint đã gửi header no-cache, nhưng nên thêm rule rõ ràng).

## 10. Acceptance criteria

- [x] Form báo giá và liên hệ có validate, sanitize, nonce, honeypot, rate limit.
- [x] Backend xác thực sản phẩm tồn tại, không tin hidden field.
- [x] Chống double submit cả frontend (disable nút) lẫn backend (cửa sổ 10 phút).
- [x] Lưu DB, hiện thông báo, gửi email qua `wp_mail`, người nhận lấy từ settings.
- [x] Form hoạt động khi tắt JavaScript.
- [x] Admin: search, filter, status, sales, ngày, pagination, bulk action, gán sales, đổi trạng thái, ghi chú.
- [x] Mọi admin action có capability + nonce.
- [x] Kiểm tra quyền bằng capability, không bằng tên role.
- [x] Asset form chỉ load khi form được render.
- [x] Sửa lỗi nonce REST ảnh hưởng autocomplete Phase 3.
- [x] 67 file PHP pass `php -l`, 5 file JS pass `node --check`, smoke test 28/28.
- [ ] Checklist 36 test chạy trên WordPress + MySQL thật.
- [ ] Kiểm tra email thực tế qua SMTP của hosting.
