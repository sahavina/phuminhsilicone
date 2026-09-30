# PHASE 5 — Homepage

## 1. Goal

Trang chủ dựng hoàn toàn bằng **UX Builder** (spec §18, §35), đủ các khối:

Hero Banner → Danh mục chính → Thương hiệu nổi bật → Sản phẩm nổi bật → Sản phẩm mới → Keo Silicone → Keo công nghiệp → PU Foam → Loctite → Ứng dụng → Lý do chọn SAHA → Blog → CTA → Footer.

- Mọi nội dung chỉnh được trong UX Builder; **không hardcode banner**, không hardcode danh mục hay thương hiệu vào template.
- Custom UX element theo spec §35: Product Grid, Brand Grid, Quote CTA, Featured Products, Brand Products (+ Category Grid, Application Grid, footer).
- UX element chỉ render; dữ liệu lấy từ service của plugin (spec §36).
- Có layout mẫu để dán vào UX Builder thay vì dựng từ đầu.

## 2. Architecture

```
UX Builder (post_content)
  [saha_products source=category category=keo-silicone …]
        │  shortcode chỉ chuẩn hoá tham số
        ▼
flatsome-child/inc/shortcodes.php
        │  gọi service
        ▼
saha-core  Catalog::product_ids()  ── cache transient theo "thế hệ"
           Catalog::terms()             │
                                        └─ save_post_product, edited_product_cat,
                                           edited_product_brand, delete … → bump_generation()
        │  trả về mảng ID
        ▼
template-parts/product/grid.php ── loop chuẩn WooCommerce (content-product.php)
                                   → product card giống hệt archive (brand + SKU từ Phase 2)
```

### Cache theo thế hệ (generation)

Mỗi key cache chứa số thế hệ hiện tại (`saha_catalog_cache_gen`). Bất kỳ thay đổi sản phẩm / danh mục / thương hiệu / ứng dụng / tồn kho nào cũng tăng số này một lần (có chặn tăng lặp trong cùng request) → toàn bộ cache catalog cũ tự vô hiệu, không cần theo dõi hay xoá từng key (spec §80). Cache cũ hết hạn theo TTL 6 giờ. Chỉ dùng transient API → tự chạy trên Redis Object Cache khi có (spec §27).

### Dùng lại thay vì viết lại

| Khối | Dùng |
|---|---|
| Hero banner | `[ux_banner]` của Flatsome — ảnh chọn trong UX Builder |
| Lý do chọn SAHA | `[featured_box]` của Flatsome |
| Blog | `[blog_posts]` của Flatsome |
| Product card | `content-product.php` của WooCommerce + hook brand/SKU từ Phase 2 |
| Footer | UX Block của Flatsome + shortcode SAHA cho dữ liệu động |

## 3. Files

Thêm mới — plugin:

| File | Vai trò |
|---|---|
| `includes/class-catalog.php` | query ID sản phẩm theo nguồn, term cho grid, cache theo thế hệ |

Thêm mới — theme:

| File | Vai trò |
|---|---|
| `template-parts/product/grid.php` | grid sản phẩm bằng loop WooCommerce, prime cache chống N+1 |
| `template-parts/common/term-grid.php` | Category Card cho danh mục / ứng dụng (kiểu ảnh hoặc chip) |
| `template-parts/common/section-heading.php` | tiêu đề khối + "Xem tất cả" |
| `template-parts/common/quote-cta.php` | dải CTA báo giá + hotline + Zalo |
| `assets/css/sections.css` | style khối trang chủ, chỉ load khi có khối |
| `docs/layouts/homepage.ux.txt` | layout trang chủ mẫu, dán vào UX Builder |
| `docs/layouts/footer-block.ux.txt` | layout footer mẫu cho UX Block |

Sửa: `inc/shortcodes.php` (10 shortcode mới, `[saha_brand_grid]` thêm tiêu đề), `inc/ux-elements.php` (10 UX element mới), `inc/enqueue.php`, `inc/woocommerce.php` (modal ở trang có Quote CTA), `main.css` (footer), `class-loader.php`, `includes/functions.php`, `uninstall.php`, `saha-core.php` (1.4.0), `tests/smoke.php`.

## 4. Database

Không thêm bảng, không đổi schema — `SAHA_CORE_DB_VERSION` giữ `1.2.0`.
Thêm option `saha_catalog_cache_gen` (autoload = no).

## 5. Code

72 file PHP pass `php -l` (PHP 8.0), JS pass `node --check`, `php tests/smoke.php` → **34/34**.

## 6. Hooks

Shortcode / UX element mới (nhóm **SAHA** trong UX Builder):

| Shortcode | UX element | Dùng cho |
|---|---|---|
| `[saha_products source=…]` | SAHA Product Grid | mọi khối sản phẩm: mới, khuyến mại, theo danh mục/thương hiệu/ứng dụng, chọn tay |
| `[saha_featured_products]` | SAHA Featured Products | Sản phẩm nổi bật (sao ★ trong WooCommerce) |
| `[saha_brand_products brand=…]` | SAHA Brand Products | Loctite, Apollo… |
| `[saha_category_grid]` | SAHA Category Grid | Danh mục chính |
| `[saha_application_grid]` | SAHA Application Grid | Ứng dụng |
| `[saha_quote_cta]` | SAHA Quote CTA | dải kêu gọi báo giá |
| `[saha_term_links taxonomy=…]` | SAHA Footer Links | cột Danh mục / Thương hiệu ở footer |
| `[saha_social]` | SAHA Social | Facebook + Zalo từ settings |
| `[saha_copyright]` | SAHA Copyright | © năm hiện tại + tên công ty |

`source` hỗ trợ: `featured`, `latest`, `sale`, `category`, `brand`, `application`, `ids`. Có thể kết hợp — ví dụ `source="featured" brand="loctite"`.
`category` / `brand` / `application` nhận **slug** (gõ tay trong shortcode) hoặc **term ID** (dropdown `termSelect` của UX Builder).
`view_all`: để trống = tự sinh link theo nguồn; nhập URL tương đối (`/thuong-hieu/`) để tuỳ chỉnh; `none` để ẩn.

Filter mới: `saha_catalog_product_query`, `saha_catalog_terms`.
Helper mới: `saha_catalog_product_ids`, `saha_catalog_terms`, `saha_catalog_sources`.

## 7. Security

- Tham số shortcode/UX element là input của người biên tập → vẫn whitelist toàn bộ: `source`, `orderby` chỉ nhận giá trị liệt kê; `limit` clamp ≤ 24; `ids` chỉ giữ số dương; slug qua `sanitize_title`; URL qua `esc_url_raw`.
- Taxonomy của grid term bị giới hạn ở `product_cat` / `product_application`; footer links chỉ nhận 3 taxonomy liệt kê.
- Output escape đầy đủ (`esc_html`, `esc_attr`, `esc_url`).
- Khối rỗng: khách **không** thấy gì; chỉ người có quyền `edit_pages` thấy gợi ý sửa khối — không lộ thông tin cấu hình cho khách.

## 8. Testing

Tự động: `php tests/smoke.php` → 34/34 (thêm 6 case Catalog: slug vs term ID, whitelist `source` / `orderby`, clamp `limit`, lọc `ids`).

Checklist thủ công:

| # | Test | Kỳ vọng |
|---|---|---|
| 1 | Dán `docs/layouts/homepage.ux.txt` vào UX Builder | render đủ 14 khối theo thứ tự spec §18 |
| 2 | Chọn ảnh hero trong UX Builder | ảnh hiện, không có URL ảnh nào hardcode trong code |
| 3 | Đếm thẻ H1 trang chủ | đúng **1** (trong hero); mọi tiêu đề khối là H2 |
| 4 | Khối Keo Silicone với slug danh mục sai | khách: khối biến mất; admin: thấy gợi ý sửa |
| 5 | Kéo SAHA Product Grid mới, chọn danh mục bằng dropdown | ra đúng sản phẩm (term ID được hiểu) |
| 6 | `source="featured" brand="loctite"` | chỉ sản phẩm Loctite có đánh dấu nổi bật |
| 7 | Đánh dấu ★ nổi bật một sản phẩm | xuất hiện ngay ở khối Sản phẩm nổi bật (cache vô hiệu) |
| 8 | Sửa tên danh mục | grid danh mục cập nhật ngay |
| 9 | Kéo-thả sắp xếp danh mục trong WooCommerce | grid theo đúng thứ tự; danh mục chưa kéo-thả **không** biến mất |
| 10 | Ẩn sản phẩm khỏi catalogue (Catalog visibility: Hidden) | không xuất hiện ở khối nào |
| 11 | `hide_out_of_stock="1"` | sản phẩm hết hàng bị ẩn |
| 12 | Query Monitor trên trang chủ (cache nóng) | không có query sản phẩm lặp theo từng card (không N+1) |
| 13 | Query Monitor sau khi lưu 1 sản phẩm | lần tải đầu query lại, lần sau lấy từ cache |
| 14 | Bật Redis Object Cache | vẫn đúng, transient nằm trong Redis |
| 15 | Bấm "Xem tất cả" ở khối danh mục/thương hiệu | tới đúng archive tương ứng |
| 16 | Nút Quote CTA trên trang chủ | mở modal báo giá tại chỗ |
| 17 | Trang chủ có Quote CTA | `form.css`, `quote-form.js` được load; trang không có thì không |
| 18 | Khối Ứng dụng kiểu chip trên mobile 375px | cuộn ngang, không vỡ layout |
| 19 | Grid danh mục ở 320 / 375 / 768 / 1024 / 1440px | 2 / 2 / 3 / 4 / 4 cột (với `columns="4"`) |
| 20 | Ảnh danh mục | có `width/height`, `loading="lazy"`, khung tỉ lệ cố định → CLS < 0.1 |
| 21 | Ảnh hero | **không** lazy-load (xem ghi chú LCP ở mục 11) |
| 22 | Footer block | địa chỉ/hotline/email lấy từ SAHA → Cấu hình; đổi setting → footer đổi theo |
| 23 | `[saha_copyright]` | năm hiện tại theo múi giờ site |
| 24 | Tắt plugin saha-core | trang chủ không lỗi PHP; các khối SAHA biến mất |
| 25 | Lighthouse mobile trang chủ | LCP < 2.5s, CLS < 0.1 (spec §25) |

## 9. Installation

1. Pull code (plugin lên 1.4.0). Không có migration.
2. **Settings → Permalinks** → Custom structure `/tin-tuc/%postname%/` để blog nằm ở `/tin-tuc/` (spec §21); tạo 4 chuyên mục: *Kiến thức keo, Hướng dẫn, Tư vấn, Tin doanh nghiệp*.
3. Tạo slug danh mục sản phẩm khớp layout mẫu, hoặc sửa slug trong layout: `keo-silicone`, `keo-cong-nghiep`, `pu-foam`; thương hiệu `loctite`.
4. Tạo page **Trang chủ** → *Edit with UX Builder* → mở trình soạn thảo nội dung dạng text, dán `docs/layouts/homepage.ux.txt` → chọn ảnh hero → Lưu.
5. **Settings → Reading** → Homepage = trang vừa tạo.
6. **UX Blocks** → tạo block "Footer SAHA" với nội dung `docs/layouts/footer-block.ux.txt` → **Flatsome → Footer** chọn block này.
7. Tạo menu tên `chinh-sach` (các trang chính sách) để cột Chính sách ở footer hiện link.
8. Đánh dấu ★ vài sản phẩm nổi bật trong **Products**.

## 10. Acceptance criteria

- [x] Trang chủ quản trị hoàn toàn bằng UX Builder, đủ 14 khối spec §18.
- [x] Không hardcode banner, danh mục, thương hiệu, hotline, năm copyright.
- [x] Đủ 5 custom UX element spec §35 (+ category, application, footer).
- [x] UX element không chứa SQL, chỉ gọi service plugin.
- [x] Cache có invalidation theo `save_product`, `edited_product_cat`, `edited_product_brand`, `delete_product` (spec §80).
- [x] Không N+1 khi render grid (prime post/meta/term cache).
- [x] Asset khối trang chủ chỉ load khi khối được render.
- [x] Footer chỉnh được qua UX Block, dữ liệu động từ settings.
- [x] 72 file PHP pass `php -l`, JS pass `node --check`, smoke test 34/34.
- [ ] Checklist 25 test trên WordPress + Flatsome thật.
- [ ] Lighthouse mobile đạt LCP < 2.5s, CLS < 0.1 với ảnh hero thật.

## 11. Ghi chú & giới hạn

- **LCP của hero:** ảnh hero là ứng viên LCP. Nếu bật *Flatsome → Advanced → Performance → Lazy load images*, hãy kiểm tra ảnh banner đầu trang **không** bị lazy-load; nếu có, tắt lazy-load cho banner đầu hoặc dùng tuỳ chọn của plugin cache (LiteSpeed: *Exclude first N images from lazy load*). Phase 7 đã thêm preload + `skip-lazy` tự động cho ảnh hero.
- **`orderby="rand"`** được cache theo TTL để tránh `ORDER BY RAND()` mỗi request — nghĩa là thứ tự "ngẫu nhiên" chỉ đổi khi cache hết hạn hoặc dữ liệu thay đổi. Đây là đánh đổi có chủ đích.
- ~~Cache thương hiệu của Phase 2 vẫn dùng cơ chế danh sách key riêng.~~ → **đã gộp ở Phase 7** vào lớp `Cache` chung.
- Layout mẫu dùng cú pháp shortcode của Flatsome (`ux_banner`, `featured_box`, `blog_posts`, `ux_menu`). Tên tham số có thể khác nhẹ giữa các phiên bản Flatsome — nếu một khối native không hiện đúng, kéo lại khối đó từ UX Builder; các khối `saha_*` không phụ thuộc phiên bản Flatsome.
