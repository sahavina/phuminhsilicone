# PHASE 6 — SEO

## 1. Goal

- Tương thích **Rank Math** hoặc **Yoast SEO** — không tự xây lại SEO plugin (spec §22).
- Dữ liệu SEO của thương hiệu (title, meta description) chảy vào plugin SEO (spec §7, §23).
- URL lọc / sắp xếp / tìm kiếm nội bộ không tạo nội dung trùng lặp: `noindex, follow` + canonical về archive gốc, giữ đúng số trang (spec §10, §23, §51).
- Chỉ **một** nguồn Product schema và **một** breadcrumb (spec §24, §71).
- robots.txt và sitemap đúng spec §50, §51.
- Trang bài viết có breadcrumb và bài viết liên quan (spec §21).
- Khi không có plugin SEO nào: fallback tối thiểu để trang không trống meta.

## 2. Architecture

```
                  ┌──────────────── Rank Math ────────────────┐
saha-core/Seo ────┤  rank_math/frontend/{title,description,   │
 (cầu nối)        │     robots,canonical}                     │
                  ├──────────────── Yoast ────────────────────┤
                  │  wpseo_{title,metadesc,robots_array,       │
                  │     canonical}                             │
                  ├──────────────── Không có plugin SEO ───────┤
                  │  wp_robots, pre_get_document_title,         │
                  │  wp_head fallback (description, canonical   │
                  │  archive, OpenGraph cơ bản), core sitemap   │
                  └────────────────────────────────────────────┘
                  + woocommerce_structured_data_product (chọn 1 nguồn schema)
                  + robots_txt

flatsome-child
  woocommerce_breadcrumb()  ← override hàm pluggable của WooCommerce
      → Rank Math | Yoast | WooCommerce (đúng MỘT nguồn)
  the_content (post)        → breadcrumb + bài viết liên quan
```

### Thứ tự ưu tiên title / description thương hiệu

```
ô nhập trong Rank Math / Yoast của term   (người làm SEO chỉnh trực tiếp)
        ▼ nếu trống
ô "SEO title" / "Meta description" SAHA   (nhập cùng lúc với logo, banner)
        ▼ nếu trống
mô tả ngắn → mô tả term → mặc định của plugin SEO
```

SAHA **không bao giờ ghi đè** giá trị người dùng đã nhập trong plugin SEO.
Trang 2+ của thương hiệu tự thêm "- Trang N" để không trùng title.

### Vì sao URL lọc dùng noindex chứ không chặn trong robots.txt

Nếu `Disallow` trong robots.txt, Google không được crawl URL đó nên **không đọc được** thẻ `noindex` và canonical — URL đã lỡ index sẽ nằm lại mãi. Vì vậy:

| Loại URL | Cách xử lý |
|---|---|
| `?saha_brand=…`, `?saha_availability=…`, `?orderby=…`, `?filter_pa_…` | `noindex, follow` + canonical về archive gốc (giữ `/page/N/`) |
| `?s=…` (tìm kiếm nội bộ) | `noindex, follow` **và** `Disallow` trong robots.txt |
| `wp-admin/admin-post.php`, `?saha_form=…` | `Disallow` — endpoint form, không có nội dung |
| CSS, JS, uploads | **không** chặn — Google cần để render (spec §51) |

### Breadcrumb một nguồn

`woocommerce_breadcrumb()` được WooCommerce khai báo trong `if ( ! function_exists() )` và chỉ nạp ở `after_setup_theme` — **sau** khi child theme đã nạp. Child theme định nghĩa lại hàm này là cách override chính thức, không sửa core. Mọi chỗ Flatsome/WooCommerce gọi breadcrumb đều đi qua hàm này và chỉ render **một** nguồn: Rank Math (nếu đã bật breadcrumb trong Rank Math) → Yoast (nếu đã bật) → WooCommerce. Khi dùng Rank Math/Yoast, luồng WooCommerce không chạy nên BreadcrumbList schema của WooCommerce cũng không sinh ra — không trùng schema.

### Product schema một nguồn

| Cấu hình (SAHA → Cấu hình → SEO) | Ai xuất Product schema |
|---|---|
| Tự động + Rank Math | Rank Math (tắt schema Product của WooCommerce) |
| Tự động + Yoast / không plugin | WooCommerce (Yoast bản miễn phí không xuất Product) |
| WooCommerce | WooCommerce |
| Plugin SEO | Plugin SEO — dùng khi có Yoast WooCommerce SEO (bản trả phí) |

## 3. Files

Thêm mới:

| File | Vai trò |
|---|---|
| `saha-core/includes/class-seo.php` | cầu nối Rank Math/Yoast, robots, canonical, fallback meta, schema, robots.txt, sitemap core |
| `flatsome-child/template-parts/blog/related.php` | bài viết liên quan |

Sửa: `class-catalog.php` (bài viết liên quan có cache), `class-settings.php` (kiểu `select`, nhóm SEO, 2 setting), `admin/views/settings.php` (render `select`), `class-loader.php`, `includes/functions.php` (3 helper), `inc/woocommerce.php` (override `woocommerce_breadcrumb`), `inc/hooks.php` (breadcrumb dùng chung nguồn, hook bài viết), `template-parts/common/breadcrumb.php` (bài viết có chuyên mục), `sections.css`, `saha-core.php` (1.5.0), `tests/smoke.php`.

## 4. Database

Không đổi schema. 2 setting mới trong `saha_core_settings`: `product_schema_source` (`auto`), `blog_related_posts` (`true`).

## 5. Code

74 file PHP pass `php -l` (PHP 8.0), JS pass `node --check`, `php tests/smoke.php` → **52/52**.

## 6. Hooks

Filter mới: `saha_seo_fallback_enabled`, `saha_robots_txt_lines`, `saha_sitemap_excluded_taxonomies`, `saha_theme_post_breadcrumb`.

Helper mới: `saha_seo_provider()`, `saha_breadcrumb_provider()`, `saha_related_post_ids()`.

Hook của bên thứ ba được dùng: `rank_math/frontend/{title,description,robots,canonical}`, `wpseo_{title,metadesc,robots_array,canonical}`, `wp_robots`, `pre_get_document_title`, `woocommerce_structured_data_product`, `robots_txt`, `wp_sitemaps_add_provider`, `wp_sitemaps_taxonomies`.

## 7. Security

- Mọi output trong `<head>` qua `esc_attr` / `esc_url`; description qua `wp_strip_all_tags` trước khi cắt.
- Sitemap core bỏ provider `users` — không lộ username quản trị (spec §50: không sitemap dữ liệu nội bộ).
- Quote / lead / log nằm trong bảng riêng, không phải post type → không bao giờ vào sitemap.
- `select` trong settings chỉ nhận giá trị trong danh sách option.

## 8. Testing

Tự động: `php tests/smoke.php` → 52/52 (thêm 18 case: nhận diện provider, nguồn schema, cắt description, robots.txt, robots cho URL lọc / tìm kiếm / URL sạch, sitemap users, sanitize select).

Checklist thủ công (chạy lần lượt với **Rank Math**, **Yoast**, và **không plugin SEO**):

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | View-source trang thương hiệu | đúng **1** `<title>`, 1 meta description, 1 canonical |
| 2 | Nhập SEO title ở form thương hiệu SAHA, để trống ô Rank Math | `<title>` dùng giá trị SAHA |
| 3 | Nhập thêm ô title trong Rank Math | Rank Math thắng, SAHA không ghi đè |
| 4 | Trang 2 của thương hiệu | title có "- Trang 2"; canonical = chính trang 2 |
| 5 | `/thuong-hieu/loctite/?saha_availability=in_stock` | `noindex, follow`; canonical = `/thuong-hieu/loctite/` |
| 6 | `/thuong-hieu/loctite/page/2/?saha_brand=x` | canonical = `/thuong-hieu/loctite/page/2/` |
| 7 | `/?s=keo&post_type=product` | `noindex, follow` |
| 8 | `/robots.txt` | có `Disallow: /?s=`, `admin-post.php`; **không** chặn `wp-content`, CSS, JS |
| 9 | Rank Math sửa robots.txt trong giao diện của nó | dòng SAHA vẫn được thêm vào nhóm `User-agent: *` |
| 10 | Rich Results Test trang sản phẩm (Rank Math) | đúng **1** khối `Product` |
| 11 | Rich Results Test trang sản phẩm (không plugin SEO) | 1 khối `Product` từ WooCommerce |
| 12 | Rich Results Test | đúng **1** `BreadcrumbList` |
| 13 | Trang sản phẩm, bật breadcrumb Rank Math | 1 breadcrumb hiển thị, của Rank Math |
| 14 | Tắt breadcrumb trong Rank Math | breadcrumb WooCommerce/Flatsome hiện lại (không mất hẳn) |
| 15 | Sitemap Rank Math/Yoast | có sản phẩm, danh mục, **thương hiệu**, bài viết, trang (bật taxonomy trong plugin — xem mục 9) |
| 16 | `/wp-sitemap.xml` (không plugin SEO) | có `product_brand`, `product_application`; **không** có users, `product_tag` |
| 17 | Không plugin SEO: view-source bài viết | meta description, `og:title`, `og:type=article`, `og:image` |
| 18 | Không plugin SEO: trang danh mục | có canonical + meta description từ mô tả danh mục |
| 19 | Có Rank Math: view-source | **không** có thẻ meta/OG nào do SAHA xuất (không trùng) |
| 20 | Bài blog | breadcrumb đầu bài, "Bài viết liên quan" cuối bài, 3 bài cùng chuyên mục |
| 21 | Bài thuộc chuyên mục chỉ có 1 bài | khối liên quan tự bổ sung bài mới nhất |
| 22 | Tắt "Bài viết liên quan" trong Cấu hình | khối biến mất |
| 23 | Trang sản phẩm | tên sản phẩm là **H1 duy nhất** (spec §24) |
| 24 | Link danh mục / thương hiệu trên trang sản phẩm | là thẻ `<a href>` thật, crawl được |
| 25 | Search Console → URL Inspection trang thương hiệu | "URL is on Google / can be indexed" |

## 9. Installation

1. Pull code (plugin lên 1.5.0). Không có migration.
2. Cài **một** trong hai: Rank Math hoặc Yoast. Không bật cả hai.
3. **Rank Math**:
   - General Settings → Breadcrumbs → **bật** (để SAHA dùng breadcrumb Rank Math).
   - Sitemap Settings → Taxonomies → bật **Thương hiệu**, **Ứng dụng**, **Danh mục sản phẩm**; tắt **Thẻ sản phẩm**.
   - Titles & Meta → Thương hiệu → *Robots meta* để **index**.
   - Module **WooCommerce** bật (để Rank Math xuất Product schema).
4. **Yoast**:
   - Settings → Advanced → Breadcrumbs → **bật**.
   - Content types / Taxonomies → Thương hiệu, Ứng dụng: *Show in search results* = **bật**.
5. SAHA → Cấu hình → SEO: giữ **Tự động** trừ khi có Yoast WooCommerce SEO (khi đó chọn *Plugin SEO*).
6. Nếu Flatsome đang tự hiện breadcrumb trên trang bài viết, tắt breadcrumb chèn thêm của SAHA:
   `add_filter( 'saha_theme_post_breadcrumb', '__return_false' );`
7. Gửi sitemap lên Google Search Console.

## 10. Acceptance criteria

- [x] Tương thích Rank Math và Yoast, không phụ thuộc bắt buộc vào plugin nào (spec §72).
- [x] Không tự xây lại SEO plugin; fallback chỉ chạy khi không có plugin SEO.
- [x] Không ghi đè giá trị SEO người dùng nhập trong plugin SEO.
- [x] URL lọc/sắp xếp/tìm kiếm: noindex + canonical về archive gốc, giữ số trang.
- [x] robots.txt không chặn CSS/JS; không chặn URL cần đọc noindex.
- [x] Một nguồn Product schema, một breadcrumb, một BreadcrumbList.
- [x] Sitemap không có user / dữ liệu nội bộ.
- [x] Bài viết có breadcrumb + bài viết liên quan (spec §21).
- [x] 74 file PHP pass `php -l`, JS pass `node --check`, smoke test 52/52.
- [ ] Checklist 25 test với cả 3 cấu hình (Rank Math / Yoast / không plugin).
- [ ] Rich Results Test trên URL thật.

## 11. Ghi chú & giới hạn

- Tên hàm kiểm tra breadcrumb của Rank Math (`Helper::is_breadcrumbs_enabled`) và Yoast (`WPSEO_Options::get`) được gọi qua `method_exists` — nếu phiên bản plugin đổi API, SAHA tự rơi về breadcrumb WooCommerce thay vì lỗi hay mất breadcrumb. Test 13–14 để xác nhận trên phiên bản đang dùng.
- Breadcrumb bài viết được chèn qua `the_content` để không phụ thuộc hook riêng của Flatsome — nên nó nằm **dưới** tiêu đề bài. Nếu muốn đặt trên tiêu đề, cần hook vị trí của Flatsome (việc giao diện, để khi có site thật).
- Cài đặt sitemap/robots trong Rank Math/Yoast là cấu hình của plugin đó; SAHA không ép bằng code để không xung đột khi người làm SEO chỉnh (mục 9 liệt kê đúng những gì cần bật).
