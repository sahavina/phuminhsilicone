# PHASE 3 — Search & Filter

## 1. Goal

- Tìm sản phẩm theo tên, SKU, thương hiệu, danh mục, mô tả ngắn, nội dung, từ đồng nghĩa.
- `243` → Loctite 243. `apollo a500` → Apollo Silicone A500.
- Autocomplete AJAX có debounce, loading/empty/error state, keyboard nav.
- REST `/wp-json/saha/v1/` cho search, products, brands — có rate limit và pagination.
- Bộ lọc sản phẩm chạy **server-side trước**, AJAX chỉ là lớp tăng cường; state luôn nằm trên URL.

Chưa làm: quote/lead form (Phase 4), homepage (Phase 5).

## 2. Architecture

```
Search          relevance scoring bằng SQL prepare, có cache 5 phút
  ├─ pre_get_posts  → trang kết quả /?s=&post_type=product dùng đúng relevance
  └─ saha_search_performed → search log (nếu admin bật)

Filter          query var → pre_get_posts (tax_query / meta_query)
  └─ build_url()    → giữ state trên URL, không phụ thuộc JS

Api             saha/v1, nạp route từ api/routes/*.php theo thứ tự tên file
  ├─ 010-search.php    GET /search
  ├─ 020-products.php  GET /products, GET /products/{id}
  └─ 030-brands.php    GET /brands, GET /brands/{slug}

flatsome-child
  ├─ template-parts/search/form.php    ô tìm kiếm, tự enqueue search.js
  ├─ template-parts/common/filter.php  form GET thật, tự enqueue product-filter.js
  ├─ search.js          autocomplete (đã viết ở Phase 1, giờ có endpoint)
  └─ product-filter.js  AJAX + pushState, fallback điều hướng thường
```

### Thuật toán relevance

Một query duy nhất, chấm điểm bằng `CASE`:

| Khớp ở | Điểm |
|---|---|
| SKU trùng khít | 100 |
| SKU chứa từ khoá | 60 |
| Tiêu đề chứa nguyên cụm | 50 |
| Tiêu đề chứa từng token | 12 mỗi token |
| Thuộc term (brand/danh mục/ứng dụng) khớp | 15 |
| Mô tả ngắn chứa cụm | 8 |
| Nội dung chứa cụm | 4 |

`243` khớp SKU hoặc tiêu đề → lên đầu. `apollo a500` có 2 token cùng khớp tiêu đề (12+12) cộng điểm term Apollo (15) và cụm đầy đủ nếu có → vượt các sản phẩm chỉ khớp một phần.

Biểu thức trên `sku.meta_value` được bọc `MAX()`: `GROUP BY p.ID` chỉ bảo đảm functional dependency cho cột của `wp_posts`, không cho cột join — thiếu `MAX()` thì MySQL bật `ONLY_FULL_GROUP_BY` (mặc định từ 5.7) sẽ từ chối query.

Dấu tiếng Việt dựa vào collation `utf8mb4_*_ci` của MySQL (accent-insensitive), nên "keo" khớp cả "kéo"; không cần cột chuẩn hoá riêng.

## 3. Files

Thêm mới — plugin:

| File | Vai trò |
|---|---|
| `includes/class-search.php` | relevance scoring, cache, search log, tích hợp `pre_get_posts` |
| `includes/class-filter.php` | query var, sanitize, tax_query/meta_query, `build_url()` |
| `includes/class-api.php` | namespace, nạp route, envelope, rate limit, pagination helper |
| `api/routes/010-search.php` · `020-products.php` · `030-brands.php` | endpoint |
| `database/migrations/004-create-search-logs.php` | bảng log tìm kiếm |

Thêm mới — theme:

| File | Vai trò |
|---|---|
| `template-parts/search/form.php` | ô tìm kiếm + panel autocomplete |
| `template-parts/common/filter.php` | form lọc (brand, ứng dụng, tình trạng, giá) |

Sửa: `class-loader.php` (3 module), `class-settings.php` (field từ đồng nghĩa), `includes/functions.php` (5 helper), `product-filter.js` (thay scaffold bằng bản đầy đủ), `inc/shortcodes.php`, `inc/woocommerce.php`, `inc/ux-elements.php`, `product.css`, `search.css`, `saha-core.php` (1.2.0 / DB 1.1.0).

## 4. Database

Thêm `wp_saha_search_logs` — `query`, `result_count`, `created_at`, index `query` + `created_at`.
**Không** lưu user, không lưu IP (spec §87). Chỉ ghi khi bật *SAHA → Cấu hình → Ghi log tìm kiếm* (mặc định tắt).

`SAHA_CORE_DB_VERSION` lên `1.1.0`; migration 001–003 không chạy lại (`saha_core_migrations_ran`).

## 5. Code

Xem mục 3. 48 file PHP pass `php -l` (PHP 8.0).

## 6. Hooks

REST: `GET /saha/v1/search?q=&limit=&page=`, `GET /saha/v1/products?page=&per_page=&brand=&category=&search=`, `GET /saha/v1/products/{id}`, `GET /saha/v1/brands`, `GET /saha/v1/brands/{slug}`.

Action: `saha_search_performed`, `saha_filter_applied`, `saha_core_register_routes`.

Filter: `saha_search_results`, `saha_filter_allowed_vars`, `saha_theme_show_archive_filter`, `saha_rate_limit` (đã có từ Phase 1, giờ được dùng thật).

Shortcode / UX element: `[saha_search]`, `[saha_product_filter]`.

Helper: `saha_search`, `saha_filter_current`, `saha_filter_url`, `saha_filter_base_url`, `saha_availability_options`.

JS event: `saha:search` (đã có), `saha:filter:updated`.

## 7. Security

- Toàn bộ SQL của Search qua `$wpdb->prepare()`; token và cụm từ đều đi qua `esc_like()`. Không nối chuỗi input (spec §77).
- Mọi REST route có `permission_callback` (spec §29). Rate limit: search 30/phút, products & brands 60/phút — theo hash(IP + salt), không lưu IP thô.
- `per_page` clamp ≤ 50, `limit` search ≤ 50 — không cho request 100000 bản ghi (spec §79).
- Filter chỉ nhận query var trong whitelist; slug qua `sanitize_title`, attribute phải là taxonomy `pa_*` tồn tại thật, tình trạng phải nằm trong danh sách option. Số slug mỗi filter clamp 20.
- Output API không lộ dữ liệu nội bộ: chỉ trả field đã chọn, attachment ID được đổi thành URL.
- Từ đồng nghĩa do admin nhập, sanitize dạng textarea và chỉ dùng làm token LIKE.

## 8. Testing

Đã chạy: `php -l` trên 48 file — 0 lỗi.

Checklist thủ công:

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | `/wp-json/saha/v1/search?q=243` | trả Loctite 243 ở vị trí đầu |
| 2 | `?q=apollo a500` | trả Apollo Silicone A500 ở vị trí đầu |
| 3 | `?q=APOLLO` và `?q=apollo` | kết quả như nhau |
| 4 | `?q=keo` vs `?q=kéo` | cùng kết quả (collation accent-insensitive) |
| 5 | `?q=a` | 400, thông báo từ khoá quá ngắn |
| 6 | `?q=zzzzzz` | 200, `items: []`, `total: 0` |
| 7 | `?q=' OR 1=1 --` | 200, 0 kết quả, **không** lỗi SQL |
| 8 | `?q=<script>alert(1)</script>` | trả về escape, không thực thi khi render |
| 9 | Gọi `/search` 31 lần trong 1 phút | lần 31 trả 429 |
| 10 | `/products?per_page=99999` | clamp còn 50 |
| 11 | `/products/{id}` với sản phẩm draft | 404 |
| 12 | `/brands/khong-ton-tai` | 404 |
| 13 | MySQL 8 bật ONLY_FULL_GROUP_BY | search chạy, không lỗi SQL |
| 14 | Gõ vào ô tìm kiếm | chỉ 1 request sau khi ngừng gõ 300ms |
| 15 | Gõ rồi xoá nhanh | request cũ bị abort, không race |
| 16 | Không có kết quả | panel hiện "Không tìm thấy sản phẩm phù hợp" |
| 17 | Ngắt mạng rồi gõ | panel hiện thông báo lỗi thân thiện |
| 18 | Phím ↓ ↑ Enter Esc trong panel | điều hướng và đóng đúng |
| 19 | Submit form tìm kiếm | tới trang kết quả, thứ tự theo relevance |
| 20 | Trang kết quả rỗng | empty state có ô tìm kiếm + danh mục, chỉ **1** thông báo |
| 21 | Tick 1 thương hiệu ở bộ lọc | URL đổi, danh sách cập nhật, không reload trang |
| 22 | Copy URL đã lọc, mở tab ẩn danh | ra đúng kết quả đã lọc |
| 23 | Tắt JavaScript, tick lọc + bấm Áp dụng | vẫn lọc đúng |
| 24 | Bấm Back sau khi lọc | quay lại trạng thái trước |
| 25 | Bấm phân trang trong vùng kết quả | AJAX, URL đổi |
| 26 | Lọc "Liên hệ" | ra sản phẩm có `_saha_availability = contact` |
| 27 | Lọc tình trạng với sản phẩm để trống field | fallback theo `_stock_status` |
| 28 | Thêm `?saha_brand=<script>` | bị sanitize, không lọc, không XSS |
| 29 | Bật Ghi log tìm kiếm rồi tìm | `wp_saha_search_logs` có dòng mới, không có IP |
| 30 | Tắt Ghi log tìm kiếm | không ghi thêm dòng nào |
| 31 | Đặt từ đồng nghĩa `keo kính = silicone` rồi tìm "keo kính" | ra cả sản phẩm silicone |
| 32 | Mobile 375px | bộ lọc xếp dọc, panel autocomplete không tràn màn hình |

## 9. Installation

1. Pull code (plugin lên 1.2.0).
2. Vào admin một lần → migration 004 chạy, tạo `wp_saha_search_logs`.
3. Đặt `[saha_search]` vào header (Flatsome → Header Builder → HTML block) hoặc dùng UX element **SAHA Search**.
4. Bộ lọc tự hiện trên archive sản phẩm; tắt bằng filter `saha_theme_show_archive_filter` nếu muốn đặt thủ công bằng `[saha_product_filter]`.
5. Tuỳ chọn: SAHA → Cấu hình → *Từ đồng nghĩa tìm kiếm* và *Ghi log tìm kiếm*.

## 10. Acceptance criteria

- [x] Tìm được theo SKU, tên, thương hiệu, danh mục, mô tả, nội dung, từ đồng nghĩa.
- [x] Relevance đúng với 2 ví dụ trong spec.
- [x] Autocomplete có debounce, không gửi request mỗi keystroke.
- [x] Có loading / empty / error state.
- [x] Mọi SQL dùng `prepare()`, không nối chuỗi.
- [x] Mọi REST route có `permission_callback` + rate limit.
- [x] `per_page` có giới hạn trên.
- [x] Filter giữ state trên URL, hoạt động khi tắt JS.
- [x] Không render 2 empty state trên archive.
- [x] Search log không lưu user/IP và mặc định tắt.
- [x] 48 file PHP pass `php -l`.
- [ ] Checklist 32 test chạy trên WordPress + MySQL thật.
- [ ] Đo lại relevance với dữ liệu sản phẩm thật (có thể cần chỉnh trọng số trong `class-search.php`).

## 11. Giới hạn đã biết

- `pre_get_posts` của trang kết quả dùng `post__in` giới hạn **200 sản phẩm** đầu theo relevance. Với catalogue vài nghìn SKU thì đủ cho trang 1–8; nếu cần sâu hơn phải chuyển sang `posts_clauses` (ghi chú lại cho Phase 7).
- Relevance dựa trên `LIKE`, không dùng FULLTEXT index. Với ~5.000 sản phẩm vẫn nhanh nhờ cache 5 phút; vượt ngưỡng đó nên cân nhắc FULLTEXT hoặc bảng index riêng.
- Accent-insensitive phụ thuộc collation của database. Nếu site dùng `utf8mb4_bin` thì "keo" sẽ không khớp "kéo" — cần kiểm tra ở test 4.
