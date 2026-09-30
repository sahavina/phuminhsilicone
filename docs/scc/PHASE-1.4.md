# SCC Phase 1 — Mốc 1.4: Element còn lại + Blocks dùng chung

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §5.1, §6.4, §15. Mốc trước: [PHASE-1.3.md](PHASE-1.3.md).

## 1. Goal

- Đủ **20 element Phase 1** (§6.4): thêm Container, Khoảng trống, Đường kẻ, Icon, Icon Box, HTML, Shortcode, Banner, CTA, Block dùng chung, Sản phẩm, Danh mục sản phẩm, Bài viết.
- **Blocks dùng chung** (post type `saha_block`): dựng bằng builder, chèn vào nhiều trang bằng element Block; **sửa một chỗ → mọi trang đổi**.

Nghiệm thu: **block sửa một chỗ → mọi trang dùng cập nhật.**

## 2. Architecture

```
Trang A ─┐                         saha_block #52 (dựng bằng builder)
Trang B ─┼─ element Block {blockId: 52} ──►  LayoutService::renderBlock()
         │   (dynamic: không cache ở cấp trang)   ├─ chỉ block đã xuất bản (editor xem cả nháp)
         │                                        ├─ chống vòng lặp A→B→A, tối đa 3 cấp lồng
         │                                        └─ nội dung block cache theo JSON của block
         └─ CSS: post-{trang}.css + post-52-{hash}.css (file riêng của block)
```

| Quyết định | Lý do |
|---|---|
| Element Block `dynamic` (không vào render cache của trang) | Nội dung block đổi mà JSON trang không đổi → cache theo JSON trang sẽ cũ. Bản thân block vẫn được cache theo nội dung của nó. |
| CSS của block ở **file riêng**, trang nạp kèm | Sửa block không phải sinh lại CSS mọi trang dùng nó. |
| Nội dung block render **không** có `data-saha-id` trong editor | Bấm vào đâu trong block cũng là chọn element Block; nội dung block sửa ở màn hình của block (nút "Sửa block (tab mới)"). |
| Quay lại tab builder → tự render lại section chứa block | Sửa block ở tab khác xong quay lại là thấy bản mới. |
| Tài liệu block dùng quy tắc cấp gốc như trang (Section, Banner… ở gốc) | Một bộ quy tắc; block đặt trong cột thì Section bên trong bỏ lề ngang. |
| Container / Section / Cột nhận `allowedChildren: ['*']`, element con tự khai báo cha hợp lệ | Thêm element mới không phải sửa các container. |
| Sản phẩm dùng template `content-product.php` của WooCommerce, ID qua `Catalog` | Thẻ sản phẩm tuỳ biến một chỗ cho cả shop và builder; chế độ catalogue giữ nguyên; tái dùng cache Phase 7. |
| Banner dùng `<img>` phủ kín thay cho CSS background | srcset theo màn hình + `fetchpriority="high"` khi bật "ảnh đầu trang" → tốt cho LCP. |
| Icon là SVG nội tuyến (33 icon, filter `saha_builder_icons`) | Không tải font icon, không nháy chữ. |

## 3. Files

### saha-core 1.11.0

| File | Vai trò |
|---|---|
| `includes/Blocks/PostType.php` | post type `saha_block` (không public, có revision), menu SAHA → Blocks, cột "Đang dùng ở", tạo block xong mở thẳng builder |
| `includes/Blocks/Usage.php` | tìm trang/block đang tham chiếu một block |
| `includes/Blocks/Rest/BlocksController.php` | `GET /blocks`, `POST /blocks` (Lưu thành block) |
| `includes/Builder/Icons.php` | bộ icon SVG |
| `includes/Builder/Controls/Icon.php`, `Html.php`, `Term.php`, `BlockRef.php` | control mới |
| `includes/Builder/Elements/{Container, Spacer, Divider, Icon, IconBox, Html, Shortcode, Banner, Cta, Block, Products, ProductCategories, Posts}.php` | 13 element |
| `includes/Builder/LayoutService.php` | `renderBlock()`, `referencedBlocks()` |
| `includes/Builder/Frontend.php` | nạp CSS của block được dùng trên trang |
| `includes/Builder/Rest/BuilderController.php` | `/render` trả kèm CSS block; không trả permalink cho loại không có trang riêng |
| `public/assets/css/builder.css` | CSS nền cho element mới |
| `includes/class-qa.php` | kiểm route `/blocks` |

### saha-builder 0.3.0

| File | Vai trò |
|---|---|
| `src/builder/controls/more.js` | IconField (lưới icon), HtmlField (cảnh báo khi không có quyền unfiltered_html), TermField, BlockRefField (chọn block + "Sửa block (tab mới)") |
| `src/builder/panels/BlockTools.js` | "Lưu thành block" (element cấp gốc), "Tách khỏi block" |
| `src/builder/store/tree.js` | `replaceNode()` (+2 test) |
| `src/builder/canvas/Canvas.js` | render lại section chứa block khi quay lại tab |
| `includes/Canvas.php` | canvas cho block (không có permalink → dùng trang chủ làm gốc, trả 200) |

## 4. Database

| | |
|---|---|
| Post type | `saha_block` — meta builder như trang (mốc 1.2) |
| File | `uploads/saha/css/post-{blockId}-{hash}.css` |

Không có bảng mới.

## 5. Code

### Element

| Element | Nhóm | Ghi chú |
|---|---|---|
| Container | Bố cục | flex dọc/ngang, gap, căn, xuống dòng, nền, bo góc |
| Khoảng trống | Bố cục | chiều cao theo thiết bị |
| Đường kẻ | Bố cục | `<hr>`, kiểu nét, độ dày, độ dài, màu |
| Icon | Nội dung | SVG, link, tên cho trình đọc màn hình, nền tròn |
| Icon Box | Nội dung | icon + tiêu đề (link) + mô tả, icon trên/trái theo thiết bị |
| HTML | Nội dung | nguyên văn nếu người lưu có `unfiltered_html`, ngược lại `wp_kses_post` |
| Shortcode | Nội dung | `do_shortcode`, không cache |
| Banner | Marketing | ảnh nền `<img>`, lớp phủ, tiêu đề (H1 được), mô tả, nút; "ảnh đầu trang" |
| CTA | Marketing | tiêu đề, mô tả, 2 nút, ngang/dọc theo thiết bị |
| Block dùng chung | Marketing | tham chiếu `saha_block` |
| Sản phẩm | Sản phẩm | nguồn: mới / nổi bật / khuyến mại / danh mục / thương hiệu / ứng dụng; sắp xếp (bán chạy = popularity); ẩn hết hàng; số cột theo thiết bị |
| Danh mục sản phẩm | Sản phẩm | danh mục hoặc ứng dụng, cấp cao nhất hoặc con của một danh mục, số sản phẩm |
| Bài viết | Blog | mới nhất / theo chuyên mục, ảnh, ngày, tóm tắt; không hiện chính bài đang xem |

Element động (không vào render cache): Shortcode, Block, Sản phẩm, Bài viết.

### REST

```
GET  /wp-json/saha/v1/blocks   → [{ id, title, status }]          quyền edit_saha_builder
POST /wp-json/saha/v1/blocks   { title, node } → { id, title }    + quyền xuất bản trang
```

## 6. Hooks

| Hook | Loại | Mô tả |
|---|---|---|
| `saha_builder_icons` | filter | thêm icon SVG: `[ name => [ nhãn, nội dung SVG ] ]` |
| `saha_builder_post_types` | filter | `saha_block` được thêm qua filter này |

## 7. Security

- Element HTML: chỉ người có `unfiltered_html` lưu được script/iframe (cùng quy tắc khối "HTML tuỳ chỉnh" của WordPress, rủi ro R5); editor thấy cảnh báo nếu không có quyền. Script không chạy trong canvas (chèn bằng innerHTML). Có test.
- Block nháp/riêng tư không hiện ở frontend (đã thử: chuyển nháp → trang không còn nội dung block; xuất bản lại → hiện).
- Chống vòng lặp block: A→B→A dừng ở lần thứ hai (đã thử: trang render 9–23 ms, nội dung chỉ một lần; mở chính block A cũng chỉ một lần). Tối đa 3 cấp lồng.
- `/blocks`: khách 401 (http-smoke); tạo block cần thêm quyền xuất bản trang; node tạo block đi qua cùng Sanitizer — sai thì xoá bài vừa tạo, trả 422.
- Term, BlockRef kiểm tồn tại khi lưu. Shortcode lưu dạng văn bản thuần; kết quả do plugin sở hữu shortcode escape.
- `Usage` dùng `$wpdb->prepare` + `esc_like`, chỉ chạy trong admin.

## 8. Testing

Tự động:

- `php tests/smoke.php` — **153 passed** (+16: đủ 20 element, mọi control có loại tồn tại, render element mới, quy tắc cha–con mới, icon sai bị từ chối, HTML theo quyền, Banner LCP, danh sách element động).
- `npm run test:js` — **37 passed** (+2 `replaceNode`).
- `php tests/http-smoke.php` — `/blocks` từ chối khách.
- `wp saha qa` — route `/blocks`.

Thủ công:

- [ ] Bảng Thêm có đủ 20 element theo nhóm Bố cục / Nội dung / Marketing / Sản phẩm / Blog.
- [ ] Sản phẩm: đổi nguồn (nổi bật, danh mục X) → lưới đổi; số cột 4/3/2 theo thiết bị; chế độ catalogue không hiện giá.
- [ ] Danh mục sản phẩm: chọn danh mục cha → hiện danh mục con; tắt "số sản phẩm".
- [ ] Bài viết: chọn chuyên mục; đặt trong bài viết → không hiện chính bài đó.
- [ ] Banner: chọn ảnh, bật "ảnh đầu trang" → xem nguồn trang: `<img … fetchpriority="high" loading="eager">`; tiêu đề H1.
- [ ] Icon Box: đổi icon, đặt icon bên trái ở desktop, trên ở mobile.
- [ ] HTML bằng tài khoản editor (không có unfiltered_html) → cảnh báo vàng; lưu `<script>` → bị loại.
- [ ] Chọn một Section → "Lưu thành block" → Section thành element Block; SAHA → Blocks có block mới.
- [ ] Trang khác → thêm "Block dùng chung" → chọn block → Lưu.
- [ ] "Sửa block (tab mới)" → đổi chữ → Lưu → quay lại tab trang: canvas tự cập nhật; xem cả hai trang ngoài website: đều đổi.
- [ ] Chuyển block sang Nháp → các trang không còn hiện block; editor vẫn thấy (ghi "nháp").
- [ ] "Tách khỏi block" → nội dung block chép vào trang, sửa riêng không ảnh hưởng block.
- [ ] SAHA → Blocks → Thêm block → đặt tên → Xuất bản → mở thẳng SAHA Builder.
- [ ] Cột "Đang dùng ở" liệt kê đúng trang.

## 9. Installation

Cập nhật code → mở wp-admin bằng admin (saha-core 1.11.0). Menu mới: **SAHA → Blocks**.

## 10. Acceptance criteria

- [x] Block "CTA báo giá (dùng chung)" tạo bằng "Lưu thành block" trên trang 50, chèn vào trang 47 bằng element Block. Sửa tiêu đề trong builder của block → **cả hai trang** hiện tiêu đề mới; trang nạp CSS riêng của block (`post-52-….css`); cột "Đang dùng ở" = 47, 50.
- [x] 12 element mới thêm được bằng giao diện và render trong canvas (8 sản phẩm, 8 danh mục, 3 bài viết từ dữ liệu thật).
- [x] Block nháp không hiện ở frontend; chống vòng lặp; luồng Thêm block → mở builder; menu SAHA → Blocks đúng vị trí.
- [x] Hồi quy: `php -l` 0 lỗi · smoke 153/153 · test JS 37/37 · lint JS/SCSS 0 lỗi · `wp saha qa` 75 đạt / 0 lỗi · `http-smoke --write` 41 đạt / 0 lỗi · `debug.log` không lỗi PHP mới.

### Lỗi phát hiện và đã sửa

| # | Lỗi | Sửa |
|---|---|---|
| 1 | Mở block A (có tham chiếu B, B tham chiếu A) trong editor: nội dung A lặp lại một lần | chuỗi chống lặp tính cả tài liệu gốc khi nó là block |
| 2 | Menu "Blocks" đứng trước "Dashboard", làm menu SAHA mở vào Blocks | tự đăng ký menu ở vị trí thứ 2 |
| 3 | Canvas của block trả 404 (post type không public) và builder hiện "Xem trang" vô nghĩa | canvas dùng trang chủ làm gốc, trả 200; không trả permalink cho loại không có trang riêng |
| 4 | Ô số cột (mặc định 4/3/2) hiện "[object Object]" | placeholder lấy mặc định theo thiết bị |

## 11. Ghi chú & giới hạn

- `post_content` dự phòng của trang (mốc 1.2) chứa bản HTML của block **tại lúc lưu trang** — không tự cập nhật khi sửa block. Chỉ ảnh hưởng tìm kiếm WordPress/excerpt/khi tắt saha-core; frontend luôn hiện block mới nhất.
- Page cache ngoài (LiteSpeed, Cloudflare…): sửa block không tự xoá cache các trang dùng nó — dùng action `saha_builder_saved` + `Usage::pagesUsing()` để purge (mốc 1.7 cân nhắc làm sẵn).
- Element Sản phẩm dùng thẻ sản phẩm của WooCommerce; giao diện thẻ riêng của SAHA ở mốc 1.6.
- Danh sách term trong control tối đa 500 mục; danh sách block tối đa 200.
- Chưa có: Tabs, Accordion, Slider, Gallery, Video (Phase 2 theo §6.4).
- Dữ liệu thử trên local: block "CTA báo giá (dùng chung)" (52), "USP 4 cam kết" (61, trống), các element thêm vào trang nháp 50.
