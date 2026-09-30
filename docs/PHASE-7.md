# PHASE 7 — Performance

## 1. Goal

Mục tiêu spec §25: **LCP < 2.5s, CLS < 0.1, INP < 200ms** — không hy sinh chức năng để gian lận điểm.

Phase này xử lý các điểm đã ghi chú ở các phase trước và những điểm nghẽn phổ biến của site WooCommerce + Flatsome:

| Vấn đề | Ở đâu | Xử lý |
|---|---|---|
| 3 cơ chế cache khác nhau (Brand dùng danh sách key, Catalog dùng thế hệ, Search TTL thuần) | Phase 2, 3, 5 | Gộp về **một** lớp `Cache` |
| Cache thương hiệu không lưu kết quả rỗng → query lại mỗi lần | Phase 2 | Cache phân biệt "chưa có" và "rỗng" |
| Kết quả tìm kiếm chỉ phân trang được 200 sản phẩm đầu, và danh sách ID không được cache | Phase 3 | Nâng lên 1000, có cache |
| Ảnh hero dễ bị lazy-load → LCP cao | Phase 5 | Tự dò ảnh hero, `preload` + `fetchpriority="high"` |
| `wc-cart-fragments` gọi AJAX mỗi lượt tải trang dù không bán hàng | WooCommerce | Bỏ khi ở chế độ catalogue |
| Menu admin đếm báo giá mới bằng query ở mọi trang admin | Phase 4 | Cache 10 phút, xoá khi có báo giá mới/đổi trạng thái |
| Bảng log tăng vô hạn | Phase 1, 3 | Cron dọn hằng đêm, xoá theo lô |
| Trang có thông báo kết quả form có thể bị page cache lưu lại | Phase 4 | `DONOTCACHEPAGE` + header no-cache |

## 2. Architecture

```
                    ┌──────────── saha-core/Cache ────────────┐
Catalog::products ──┤                                         │
Catalog::terms    ──┤   key = saha_{group}_{thế hệ}_{md5}      │
Catalog::related  ──┤   giá trị bọc {'v': …} → cache cả rỗng   │
Brand::get_all    ──┤   Transient API → Redis khi có          │
Search::search    ──┤                                         │
Search::search_ids ─┘                                         │
                    │  bump() ← save_post_product, woocommerce_update_product,
                    │           set_stock_status, set_visibility, save_post_post,
                    │           created/edited/delete_{product_cat, product_tag,
                    │           product_brand, product_application, product_material,
                    │           category}, saha_brand_saved, deleted_post
                    │  → tăng thế hệ tối đa 1 lần/request
                    │  → do_action( 'saha_cache_bumped' )  ← điểm cắm purge page cache
                    └─────────────────────────────────────────┘

Maintenance ── cron 03:00 (giờ site) ── purge logs > 90 ngày, search_logs > 180 ngày
                                        DELETE … LIMIT 5000, tối đa 20 lô / lần
                                        KHÔNG đụng quotes / leads

flatsome-child/inc/performance.php
  ├─ dò [ux_banner bg="ID"] đầu tiên → <link rel="preload" imagesrcset fetchpriority=high>
  │                                  → ảnh đó: loading=eager, class skip-lazy no-lazy
  ├─ dequeue wc-cart-fragments (chế độ catalogue)
  └─ tắt emoji script/style của core
inc/enqueue.php → mọi JS của theme: strategy = defer
```

## 3. Files

Thêm mới:

| File | Vai trò |
|---|---|
| `saha-core/includes/class-cache.php` | cache dùng chung, vô hiệu theo thế hệ, toàn bộ hook invalidation |
| `saha-core/includes/class-maintenance.php` | cron dọn log, dọn option cũ khi nâng cấp |
| `flatsome-child/inc/performance.php` | preload ảnh hero, bỏ cart fragments, tắt emoji |

Sửa: `class-catalog.php`, `class-brand.php`, `class-search.php` (chuyển sang `Cache`), `class-quote.php` + `class-crm.php` (cache số báo giá mới), `class-form-handler.php` (không cache trang kết quả), `class-install.php` (huỷ cron khi tắt), `class-settings.php` + `admin/views/settings.php` (2 setting số ngày lưu log, kiểu `int`), `class-loader.php`, `uninstall.php`, `saha-core.php` (1.6.0), `flatsome-child/functions.php`, `inc/enqueue.php`, `tests/smoke.php`.

## 4. Database

Không đổi schema. Option mới `saha_cache_gen` (autoload = no).
Nâng cấp lên 1.6.0 tự xoá option cũ `saha_catalog_cache_gen`, `saha_brand_cache_keys` và các transient thương hiệu cũ.
Setting mới: `log_retention_days` (90), `search_log_retention_days` (180) — 0 = không xoá.

## 5. Code

77 file PHP pass `php -l` (PHP 8.0), JS pass `node --check`, `php tests/smoke.php` → **64/64**.

## 6. Hooks

Action mới: `saha_cache_bumped` (cắm purge page cache khi dữ liệu catalogue đổi), `saha_core_maintenance_ran`.
Filter mới: `saha_cache_invalidation_events`, `saha_theme_lcp_image_id`, `saha_theme_keep_cart_fragments`, `saha_theme_keep_emoji`.
Cron: `saha_core_daily_maintenance`.

Ví dụ purge LiteSpeed khi sản phẩm đổi:

```php
add_action( 'saha_cache_bumped', static function () {
	do_action( 'litespeed_purge_all' );
} );
```

(Không bật mặc định: purge toàn site mỗi lần sửa sản phẩm là quá tay với catalogue lớn — LiteSpeed Cache tự purge đúng trang sản phẩm/danh mục liên quan.)

## 7. Security

- `Maintenance::purge()` chỉ nhận `logs` / `search_logs` (whitelist) — không thể bị gọi nhầm để xoá quotes/leads (spec §60). Có test.
- Số ngày lưu trữ clamp 0–3650.
- Trang `?saha_form=…` không được page cache lưu → khách sau không thấy thông báo (và không thấy tên sản phẩm) của khách trước.
- Gỡ plugin luôn huỷ cron, kể cả khi giữ dữ liệu.

## 8. Testing

Tự động: `php tests/smoke.php` → 64/64 (thêm 12 case: cache kết quả rỗng, bump đổi key, bump 1 lần/request, độ dài key, purge không đụng quotes/leads, retention 0, dò ảnh hero 4 trường hợp).

Checklist thủ công — đo trên **staging có dữ liệu thật**, bật LiteSpeed Cache (hoặc plugin cache đang dùng):

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | PageSpeed Insights mobile — trang chủ | LCP < 2.5s, CLS < 0.1 |
| 2 | PageSpeed Insights mobile — 1 trang sản phẩm, 1 trang thương hiệu | LCP < 2.5s, CLS < 0.1 |
| 3 | View-source trang chủ | có `<link rel="preload" as="image" … fetchpriority="high">` đúng ảnh hero |
| 4 | DevTools → Network trang chủ | ảnh hero tải trong nhóm đầu, **không** bị lazy |
| 5 | DevTools → Network, chế độ catalogue | **không** có request `?wc-ajax=get_refreshed_fragments` |
| 6 | Tắt chế độ catalogue | cart fragments quay lại, giỏ hàng hoạt động |
| 7 | View-source | JS của theme có `defer`; không có `wp-emoji-release.min.js` |
| 8 | Query Monitor trang chủ, cache nóng | không query sản phẩm/term theo từng card; tổng query giảm rõ so với cache lạnh |
| 9 | Sửa 1 sản phẩm | khối trang chủ, grid thương hiệu, kết quả tìm kiếm cập nhật ở lần tải kế tiếp |
| 10 | Lưu sản phẩm, Query Monitor | `saha_cache_gen` chỉ `UPDATE` **một** lần |
| 11 | Tìm từ khoá có > 200 kết quả, sang trang 10+ | có kết quả (trước đây bị cắt ở 200) |
| 12 | Trang admin bất kỳ, Query Monitor | không có query `COUNT … GROUP BY status` lặp lại (bong bóng menu lấy từ cache) |
| 13 | Gửi báo giá mới | bong bóng menu tăng ngay |
| 14 | `wp cron event list` | có `saha_core_daily_maintenance` lúc 03:00 |
| 15 | `wp cron event run saha_core_daily_maintenance` với log giả cũ 100 ngày | log cũ bị xoá, quotes/leads nguyên vẹn |
| 16 | Tắt plugin | cron biến mất khỏi danh sách |
| 17 | Submit form không-JS → trang `?saha_form=quote_sent` | response header `Cache-Control: no-cache…`; LiteSpeed header `X-LiteSpeed-Cache-Control: no-cache` |
| 18 | Bật Redis Object Cache | transient `saha_*` nằm trong Redis, bảng `wp_options` không phình |
| 19 | Nâng cấp từ 1.5.0 | option `saha_brand_cache_keys`, `saha_catalog_cache_gen` biến mất |
| 20 | INP: gõ vào ô tìm kiếm, bấm lọc, mở modal báo giá trên mobile | phản hồi < 200ms (Chrome DevTools → Performance → Interactions) |

## 9. Installation

1. Pull code (plugin lên 1.6.0), vào admin một lần để chạy dọn option cũ.
2. **LiteSpeed Cache** (khuyến nghị cho hosting LiteSpeed):
   - Page Optimization → Media → *Lazy Load Images*: bật; class `skip-lazy` / `no-lazy` đã được gắn cho ảnh hero.
   - Page Optimization → JS: *Load JS Deferred* có thể **bật**, nhưng **không** đưa `saha-main` vào danh sách "Delay" — trì hoãn tới khi người dùng tương tác sẽ làm autocomplete/nút báo giá không phản hồi ở lần chạm đầu.
   - Cache → Excludes → *Do Not Cache Query Strings*: thêm `saha_form`.
   - Cache → Excludes → *Do Not Cache URIs*: thêm `/wp-json/saha/v1/nonce`.
3. **Redis Object Cache**: cài và bật — không cần cấu hình gì thêm cho SAHA.
4. **Cloudflare**: không cache `/wp-json/*` và `/wp-admin/*` (mặc định Cloudflare không cache HTML; nếu dùng APO/Cache Everything thì thêm rule bypass cho 2 đường dẫn này và cho query `saha_form`).
5. Nếu WP-Cron bị tắt (`DISABLE_WP_CRON`), đảm bảo có cron hệ thống gọi `wp-cron.php` — dọn log phụ thuộc vào nó.

## 10. Acceptance criteria

- [x] Một cơ chế cache duy nhất, invalidation đúng các sự kiện spec §80.
- [x] Cache hoạt động với Redis Object Cache mà không phụ thuộc bắt buộc (spec §27, §72).
- [x] Không phụ thuộc plugin page cache cụ thể; có hướng dẫn LiteSpeed/Cloudflare.
- [x] Ảnh hero được preload, không lazy-load.
- [x] Không tải JS/CSS không dùng (cart fragments, emoji) — có filter để bật lại.
- [x] JS theme `defer`, không chặn render.
- [x] Bỏ giới hạn 200 kết quả tìm kiếm.
- [x] Log được dọn định kỳ theo lô; quotes/leads không bao giờ bị dọn.
- [x] 77 file PHP pass `php -l`, JS pass `node --check`, smoke test 64/64.
- [ ] PageSpeed Insights đạt LCP < 2.5s, CLS < 0.1 trên staging có dữ liệu thật.
- [ ] Checklist 20 test.

## 11. Ghi chú & giới hạn

- **Tôi không đo được Core Web Vitals ở đây** — cần site thật với ảnh, dữ liệu và hosting thật. Các thay đổi ở phase này xử lý đúng những nguyên nhân phổ biến nhất, nhưng con số cuối cùng phụ thuộc vào dung lượng ảnh hero, hosting và cấu hình plugin cache.
- Preload ảnh hero chỉ tự dò được khi hero là `[ux_banner]` có chọn ảnh. Landing page dùng khối khác thì chỉ định bằng filter `saha_theme_lcp_image_id`.
- Giới hạn tìm kiếm mới là 1000 kết quả (hằng `Search::MAX_RESULTS`). Với catalogue keo dán vài nghìn SKU thì không từ khoá thực tế nào chạm mức này; nếu cần vượt, chuyển sang `posts_clauses` hoặc FULLTEXT index.
- CSS vẫn tách `main.css` + `responsive.css` như cấu trúc spec §3. Trên HTTP/2 chênh lệch không đáng kể; nếu PageSpeed báo render-blocking, có thể gộp hoặc để LiteSpeed *CSS Combine* làm.
