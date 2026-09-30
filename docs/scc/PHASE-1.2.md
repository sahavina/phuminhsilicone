# SCC Phase 1 — Mốc 1.2: Builder runtime

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §6–§7, §15. Mốc trước: [PHASE-1.1.md](PHASE-1.1.md).

## 1. Goal

Phần "máy" của SAHA Builder, chưa có giao diện kéo thả (mốc 1.3):

- **Schema** JSON của layout (`Document` → `Node`), có phiên bản và migrate.
- **Element** định nghĩa một lần ở PHP — nguồn duy nhất cho editor (qua REST) và cho sanitizer.
- **Sanitizer** theo định nghĩa: client gửi gì cũng không vượt được schema.
- **Renderer** JSON → HTML, **CssGenerator** → file CSS riêng từng trang, **RenderCache** cho phần tĩnh.
- **LayoutRepository** (post meta + revision) và **REST** `/saha/v1/builder/*`.
- Frontend hiển thị layout với **mọi theme**; `saha-theme` hiển thị tràn khung.

7 element: Section, Row, Column, Heading, Text, Button, Image (đủ cho nghiệm thu mốc 1.3).

Nghiệm thu: **lưu JSON qua REST → frontend render đúng; có test sanitize.**

## 2. Architecture

```
POST /builder/save {postId, data, baseHash}
  │  quyền edit_saha_builder + edit_post
  ▼
LayoutService::save
  ├─ khoá chỉnh sửa (post lock WP)      người khác đang sửa → 409 locked
  ├─ baseHash ≠ hash hiện tại           → 409 conflict (chống ghi đè)
  ├─ Sanitizer::document                lỗi → 422, KHÔNG lưu gì
  ├─ LayoutRepository::save             meta _saha_builder_* (JSON, version, hash, enabled)
  ├─ CssGenerator → CssFileStore        uploads/saha/css/post-{id}-{hash}.css
  ├─ post_content dự phòng              HTML tĩnh; tạo revision (kèm meta builder)
  └─ do_action saha_builder_saved

Request frontend (bất kỳ theme)
  the_content (priority 999) ── post bật builder → LayoutService::renderPost
      Renderer: node cấp gốc toàn element tĩnh → RenderCache (transient, theo thế hệ cache SAHA)
  wp_enqueue_scripts ── builder.css (nền) + post-{id}-{hash}.css
      CSS lệch layout/phiên bản/domain hoặc mất file → tự sinh lại
```

Quyết định chính:

- **Runtime ở saha-core, không ở saha-builder** → tắt saha-builder, trang đã dựng vẫn hiển thị (đã thử). Tắt cả saha-core → trang vẫn có `post_content` dự phòng (đã thử).
- **Tài liệu là một khối**: một lỗi → từ chối cả lần lưu (khác Theme Options lưu từng field). Lỗi trả theo đường dẫn `{nodeId}.{prop}` để editor tô đúng chỗ.
- **Hai lớp bảo vệ**: control sanitizer khi lưu + escape/lọc khi render (`esc_html`, `wp_kses_post`, `CssRules::cleanValue`). Dữ liệu sửa tay trong DB cũng không gây XSS hay thoát khỏi CSS (có test).
- **Element lạ** (add-on đã tắt) được giữ nguyên khi lưu, không render → không mất dữ liệu.
- **Độ rộng cột**: biến `--saha-col` (0–1), `flex-basis = col × 100% − gap × (1 − col)` → tổng đúng 100% kể cả có khoảng cách. Row tự chia phần còn lại cho cột chưa đặt độ rộng và xếp chồng ở tablet/mobile (cột có độ rộng riêng cho breakpoint đó thì giữ).
- **CSS nền** bọc `:where()` (specificity 0): theme và CSS từng trang luôn đè được.
- `post_content` dự phòng giúp tìm kiếm WordPress, excerpt và plugin SEO đọc được nội dung. Frontend **không** đọc nó khi builder bật.

### Sai khác so với thiết kế

- Thiết kế ghi filter `saha_builder_elements`; code dùng **action** cùng tên nhận registry (`$registry->register(...)`) — dễ dùng hơn filter trả về đối tượng. Thêm action `saha_builder_register_controls` cho loại control mới.
- `saha_builder_settings` (breakpoint, bật/tắt element) chưa làm: breakpoint cố định 1024/767, trùng Theme Options. Cần khi có màn hình cài đặt builder.
- Chưa có `saha_block` / element Block (mốc 1.4) — `RenderContext::blockStack` đã chừa chỗ chống vòng lặp.

## 3. Files

### saha-core 1.9.0

| File | Vai trò |
|---|---|
| `includes/Builder/Limits.php` | độ sâu 12, 2.000 element, 1 MB, breakpoint |
| `includes/Builder/Responsive.php` | giá trị `scalar` hoặc `{desktop, tablet, mobile}` |
| `includes/Builder/InvalidValue.php` | exception (kế thừa của Theme Options) |
| `includes/Builder/Schema/Node.php`, `Document.php`, `SchemaMigrator.php` | cấu trúc, JSON, hash, migrate |
| `includes/Builder/Controls/*` | 16 loại control: text, textarea, richtext, number, select, toggle, color, size, spacing, typography, align, media, link, background, htmlId, classList |
| `includes/Builder/Elements/*` | `Element` (lớp nền) + 7 element |
| `includes/Builder/ElementRegistry.php` | đăng ký element, control tab Nâng cao, `forClient()` |
| `includes/Builder/Sanitizer.php` | sanitize tài liệu / node lẻ |
| `includes/Builder/Renderer.php`, `RenderContext.php`, `RenderCache.php` | render + cache |
| `includes/Builder/CssRules.php`, `CssGenerator.php` | sinh CSS có lọc giá trị |
| `includes/Builder/LayoutRepository.php`, `LayoutService.php` | lưu trữ, nghiệp vụ lưu, khoá, CSS |
| `includes/Builder/Frontend.php`, `Module.php`, `Rest/BuilderController.php` | the_content, enqueue, REST |
| `public/assets/css/builder.css` | CSS nền layout (section, row/column, nút, ẩn theo thiết bị) |
| `includes/functions.php` | `saha_builder_is_active( $post_id )` |
| `includes/class-qa.php` | nhóm kiểm tra **Builder** + 6 route mới |
| `includes/ThemeOptions/InvalidValue.php` | bỏ `final` để builder kế thừa |
| `uninstall.php` | xoá meta builder khi bật "xoá dữ liệu" (giữ `post_content` dự phòng) |

### saha-theme

`inc/helpers.php` (`saha_theme_builder_active()`), `page.php`, `front-page.php`: trang dùng builder hiển thị tràn khung, không in tiêu đề/breadcrumb (layout tự đặt). `single.php` giữ khung bài viết (tiêu đề, ngày, tác giả) — layout chỉ thay phần thân.

### tests

`tests/smoke.php` +40 case builder (tổng 132) · `tests/http-smoke.php` +5 case builder.

## 4. Database

Post meta (page, post — mở rộng qua `saha_builder_post_types`):

| Key | Nội dung |
|---|---|
| `_saha_builder_enabled` | `'1'` / `'0'` |
| `_saha_builder_data` | JSON tài liệu (nguồn chính) |
| `_saha_builder_version` | phiên bản schema |
| `_saha_builder_hash` | 12 hex — khoá cache, chống ghi đè |
| `_saha_css_file` | `{file, inline, layout, env}` — trạng thái file CSS |

4 key đầu đăng ký `revisions_enabled` (WordPress ≥ 6.4): khôi phục revision là khôi phục layout; CSS tự sinh lại do `layout` lệch hash. Ghi meta chỉ qua REST builder (`auth_callback`).

File: `uploads/saha/css/post-{id}-{hash}.css` (giữ bản mới nhất). Render cache: transient `saha_builder_{thế hệ}_{md5}` (TTL 1 ngày).

## 5. Code

### Tài liệu

```json
{
  "version": 1,
  "elements": [
    { "id": "hero0001", "type": "section",
      "props": { "minHeight": { "desktop": "420px", "mobile": "280px" }, "background": { "color": "var(--saha-secondary)" } },
      "advanced": { "padding": { "desktop": { "top": "64px", "bottom": "64px" } } },
      "children": [ { "id": "herorow1", "type": "row", "children": [ … ] } ] }
  ]
}
```

- `props`: chỉ key có đặt; giá trị mặc định lấy từ định nghĩa control lúc render.
- `advanced` (mọi element): `margin`, `padding` (responsive), `cssId`, `cssClass`, `hideDesktop|Tablet|Mobile`.
- Cha–con: `section` (gốc) → `row` | nội dung; `row` → `column`; `column` → `row` | nội dung.

### REST

| Method | Route | Kết quả |
|---|---|---|
| GET | `/builder/elements` | `{schemaVersion, elements[], advanced[], breakpoints, limits}` |
| GET | `/builder/{id}` | `{postId, postType, title, status, enabled, document, hash, lockedBy, permalink, previewUrl}` |
| POST | `/builder/save` | 200 `{hash, document, cssUrl}` · 409 `locked`/`conflict` (+`data.hash`) · 422 `validation_failed` + `errors` · 400 `unsupported` |
| POST | `/builder/render` | `{html, css, assets}` — HTML có `data-saha-id` cho canvas |
| POST | `/builder/lock/{id}` | `{locked: true}` · 409 `locked` |

### Thêm element (plugin/child theme)

```php
final class My_Notice extends \Saha\Core\Builder\Elements\Element {
    protected function definition(): array {
        return array(
            'type'           => 'my-notice',
            'name'           => 'Thông báo',
            'allowedParents' => array( 'column', 'section' ),
            'controls'       => array(
                'text'  => array( 'type' => 'text', 'label' => 'Nội dung', 'section' => 'content' ),
                'color' => array( 'type' => 'color', 'label' => 'Màu nền', 'section' => 'style' ),
            ),
        );
    }
    public function render( $node, $ctx, string $content ): string {
        return '<div' . $this->rootAttributes( $node, $ctx, array( 'my-notice' ) ) . '>' . esc_html( (string) $this->prop( $node, 'text' ) ) . '</div>';
    }
    public function styles( $node, $css ): void {
        $css->set( '', 'background-color', $node->prop( 'color' ) );
    }
}
add_action( 'saha_builder_elements', fn( $registry ) => $registry->register( new My_Notice() ) );
```

## 6. Hooks

| Hook | Loại | Mô tả |
|---|---|---|
| `saha_builder_elements` | action | `(ElementRegistry)` — đăng ký/gỡ element |
| `saha_builder_register_controls` | action | `(ControlRegistry)` — thêm loại control |
| `saha_builder_element_definition` | filter | `(array $def, string $type)` |
| `saha_builder_render_before` / `_after` | action | `(Node, RenderContext)` |
| `saha_builder_render_element` | filter | `(string $html, Node, RenderContext)` |
| `saha_builder_node_classes` | filter | `(string[] $classes, Node)` |
| `saha_builder_render_cache` | filter | `(bool, Node)` — tắt cache theo node |
| `saha_builder_saved` | action | `(int $postId, Document)` — purge page cache/CDN |
| `saha_builder_post_types` | filter | `(string[])` — mặc định page, post |
| `saha_builder_migrate_document` | filter | `(array $data, int $version)` |

## 7. Security

- Mọi route: `edit_saha_builder`; route theo post thêm `edit_post`. Đã thử: khách 401 (5 route), user thiếu `edit_saha_builder` 403, thiếu `edit_post` 403.
- Không có tham số nào đi thẳng vào hàm WordPress làm `sanitize_callback` (bài học Phase 8).
- Sanitize theo định nghĩa control; prop lạ bị bỏ; type/ID/cha–con/độ sâu/số lượng/kích thước được kiểm.
- Rich text qua `wp_kses_post` cho mọi user (kể cả admin) — nhập script tuỳ ý là việc của element HTML (mốc 1.4, cần `unfiltered_html`).
- Link: chỉ http(s), mailto, tel, `/đường-dẫn`, `#neo`; `javascript:` → lỗi 422 (không lặng lẽ bỏ). `target=_blank` luôn kèm `rel=noopener`.
- Ảnh: attachment ID phải là ảnh; render qua `wp_get_attachment_image` (escape, srcset).
- CSS: tên thuộc tính `[a-z-]`, selector ký tự an toàn, giá trị bỏ nếu có `; { } < > \`, `/*`, `expression`, `javascript:`, `@import`, `url(` ngoài URL http(s) sạch.
- Chống ghi đè: `baseHash` + post lock của WordPress (dùng chung với block editor: ai đang mở trang trong block editor thì builder không ghi được).
- Render cache chỉ cho subtree mà mọi element `dynamic: false` (rủi ro R6).

## 8. Testing

Tự động:

- `php tests/smoke.php` — **132 passed** (40 case builder: sanitize từng loại lỗi, XSS rich text/heading/tag, link, giới hạn, ID trùng, element lạ, render, CSS responsive, độ rộng cột, dữ liệu bẩn trong DB).
- `wp saha qa` — nhóm Builder: quyền admin/editor, ghi được file CSS, render thử layout mẫu, 6 route builder có `permission_callback`.
- `php tests/http-smoke.php <url>` — route builder từ chối khách (401), frontend không nạp asset của `saha-builder`.

Thủ công (chưa có UI — dùng REST; mốc 1.3 thay bằng editor):

- [ ] Lưu layout qua `POST /builder/save` → mở trang: đúng thứ tự section/row/column, màu, chữ, ảnh.
- [ ] Desktop / tablet (768) / mobile (375): cột đúng tỉ lệ, xếp chồng đúng breakpoint, không cuộn ngang.
- [ ] Đổi Màu chính trong Theme Options → element dùng `var(--saha-primary)` đổi theo.
- [ ] Gửi link `javascript:` → 422, layout cũ không đổi.
- [ ] Hai người: A lưu → B lưu với `baseHash` cũ → 409 `conflict`.
- [ ] Mở trang trong block editor bằng user A → user B lưu qua builder → 409 `locked`.
- [ ] Revisions: khôi phục bản cũ → frontend về layout cũ (CSS tự sinh lại).
- [ ] Tắt saha-builder → trang vẫn hiển thị đúng. Tắt saha-core → trang còn nội dung dự phòng.
- [ ] Xem nguồn trang: chỉ `builder.css` + `post-{id}-{hash}.css`, không có JS nào của builder.
- [ ] Mỗi trang dùng builder có đúng 1 H1 (layout phải tự đặt H1 — theme không in tiêu đề).

## 9. Installation

Cập nhật code → mở wp-admin một lần bằng admin (saha-core 1.9.0 chạy nâng cấp). Không có bảng mới.

Thử nhanh không cần UI (WP-CLI, user có quyền builder):

```bash
wp eval '$r = new WP_REST_Request( "POST", "/saha/v1/builder/save" );
$r->set_param( "postId", 123 );
$r->set_param( "data", json_decode( file_get_contents( "layout.json" ), true ) );
print_r( rest_do_request( $r )->get_data() );' --user=admin
```

## 10. Acceptance criteria

- [x] Lưu JSON qua REST (trang `builder-demo` trên local: hero có nền + lớp phủ, H1, nút, ảnh; hàng 3 cột USP) → frontend render đúng (kiểm bằng trình duyệt).
- [x] Độ rộng cột khớp công thức: desktop 565 + 364 + gap 40 = 969px (60/40); tablet 407 + 258 + 40 = 705px; hàng "xếp chồng từ tablet" 705px; mobile mọi cột 343px, không cuộn ngang.
- [x] Typography responsive: H1 44 / 34 / 28px ở desktop / tablet / mobile.
- [x] REST: 422 không lưu gì · 409 `conflict` · 409 `locked` · render node · lock · revision có meta builder.
- [x] Test sanitize: 40 case, 0 lỗi.
- [x] Hồi quy: `php -l` 0 lỗi · smoke 132/132 · `wp saha qa` 74 đạt / 4 cảnh báo / 0 lỗi · `http-smoke --write` 38 đạt / 0 lỗi / 2 bỏ qua · `debug.log` không có lỗi mới từ code.

### Lỗi phát hiện và đã sửa

| # | Lỗi | Sửa |
|---|---|---|
| 1 | `Builder\InvalidValue` không kế thừa được | `ThemeOptions\InvalidValue` là `final` → bỏ `final` |
| 2 | Khối nút bị tô nền xanh cả hàng | class wrapper `saha-button` trùng class nút chung của theme → đổi `saha-button-wrap` |

## 11. Ghi chú & giới hạn

- **Chưa có giao diện soạn thảo** — mốc 1.3. Hiện chỉ lưu được qua REST/WP-CLI.
- **Block editor**: trang bật builder vẫn mở được trong block editor và thấy `post_content` dự phòng (một khối Classic). Sửa ở đó **không có tác dụng** trên frontend và bị ghi đè ở lần lưu builder sau. Mốc 1.3 thêm nút "Sửa bằng SAHA Builder" và cảnh báo trong block editor.
- **Tắt saha-core** trên trang dùng `saha-theme`: theme in tiêu đề trang + H1 trong nội dung dự phòng → 2 H1 (chỉ xảy ra khi plugin lõi bị tắt).
- Ảnh nền section chưa được preload (LCP) — làm cùng element Banner ở mốc 1.4.
- Render cache dùng transient: không có object cache thì mỗi section tĩnh là một truy vấn `wp_options`. Đo lại trên staging; có thể gộp cache theo trang nếu cần.
- CSS trang dùng URL tuyệt đối cho ảnh nền; đổi domain → tự sinh lại khi trang được xem (so `home_url`).
- Code sửa element trong lúc phát triển mà không tăng phiên bản plugin → render cache giữ HTML cũ tới 1 ngày; xoá bằng `wp eval 'Saha\Core\Cache::bump();'`.
