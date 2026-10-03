# Hướng dẫn lập trình viên — SAHA Commerce Core

> Spec §115. Thiết kế đầy đủ: [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md); chi tiết từng mốc: `PHASE-1.0.md` … `PHASE-1.7.md`.
> Hướng dẫn cho người quản trị: [ADMIN-GUIDE.md](ADMIN-GUIDE.md).

## 1. Ba thành phần

| Thành phần | Thư mục | Vai trò | Quy tắc |
|---|---|---|---|
| `saha-core` | `wp-content/plugins/saha-core` | dữ liệu, nghiệp vụ, **runtime builder** (schema, sanitize, render, CSS, REST), Theme Options, template header/footer, WooCommerce (catalogue, Mua ngay) | mọi thứ phải chạy khi đổi theme hoặc tắt `saha-builder` |
| `saha-builder` | `wp-content/plugins/saha-builder` | ứng dụng soạn thảo React (builder + màn Theme Options) | chỉ admin; **không có code render frontend** |
| `saha-theme` | `wp-content/themes/saha-theme` | khung trang, template WooCommerce, lớp trình bày catalogue | chỉ trình bày; không lưu dữ liệu nghiệp vụ |
| `flatsome-child` | `wp-content/themes/flatsome-child` | giao diện cũ | **đóng băng** — không sửa |

Luồng render một trang builder:

```
post meta _saha_builder_data (JSON)
  → Schema\Document (migrate) → Renderer (+ RenderCache cho subtree tĩnh) → HTML
  → CssGenerator → uploads/saha/css/post-{id}-{hash}.css (fallback inline)
the_content (999) của saha-core Builder\Frontend thay nội dung bằng HTML trên
```

## 2. Môi trường

| Thứ | Phiên bản |
|---|---|
| PHP | **8.2+** (`SAHA_CORE_MIN_PHP`) — local: `C:/php82/php.exe` |
| WordPress / WooCommerce | 7.1 / 11.1 (đã thử) |
| Node | theo `engines` của `package.json` (`npm run check-engines`) |

```bash
npm ci
npm run build        # saha-builder/build + saha-theme/assets/build (đã commit — CI so sánh)
npm run start        # watch khi phát triển
```

Site local mẫu: XAMPP, WordPress ở `C:\xampp\htdocs\saha`, các plugin/theme là junction tới repo. Mật khẩu tài khoản local chỉ để trong `.env.local` (gitignore).

## 3. Kiểm thử

| Lệnh | Cần WordPress? | Phủ |
|---|---|---|
| `php tests/smoke.php` | Không | logic thuần: sanitize mọi control, schema, renderer, CSS, Theme Options, header/footer, trang chủ mẫu, Mua ngay… |
| `npm run test:js` | Không | store builder (thêm/xoá/di chuyển, undo/redo, responsive, copy/paste) — Vitest |
| `npm run lint:js` · `npm run lint:css` | Không | ESLint / Stylelint theo chuẩn WordPress — **chỉ** `saha-builder/src` và `saha-theme/src` |
| `node --check <file>` | Không | JS không qua build (`saha-core/public/assets/js`, `saha-theme/assets/js`): kiểm cú pháp (CI chạy cho mọi file) |
| `wp saha qa [--strict]` | Có | môi trường, DB, quyền, REST + `permission_callback`, builder, WooCommerce, SEO, bảo mật, hiệu năng |
| `php tests/http-smoke.php <url> [--write]` | site chạy | HTTP từ ngoài: REST, quyền route builder, H1, shortcode thô, form báo giá/liên hệ (`--write` chỉ local/staging) |
| `python tests/build-qa-checklist.py` | Không | sinh lại `docs/QA.md` (checklist thủ công) |

Trên Windows/Git Bash, chạy WP-CLI với tham số bắt đầu bằng `/` (ví dụ `wp rewrite structure '/%postname%/'`) cần `MSYS_NO_PATHCONV=1`, nếu không Git Bash đổi thành đường dẫn `C:/Program Files/Git/…`.

CI (`.github/workflows/ci.yml`): `php -l` toàn bộ, smoke, lint, test JS, build và `git diff --exit-code` trên thư mục build — quên build lại là CI đỏ.

Trước khi commit: `php -l` file sửa · smoke · `npm run lint:js lint:css test:js build` · `node --check` JS saha-core đã sửa · `wp saha qa` · http-smoke trên local · `debug.log` không có lỗi mới.

**Không** chạy `wp-scripts lint-js --fix` (hay `npx eslint --fix`) không kèm đường dẫn: nó định dạng lại mọi JS trong repo, kể cả file ES5 của saha-core. Luôn chỉ rõ file.

`npm start` (watch) ghi bản build **dev** (có `.map`) vào `build/` — trước khi commit chạy `npm run build` để `build/` là bản production (CI so khớp).

## 4. Builder

### Tài liệu JSON

```json
{ "version": 1, "elements": [
  { "id": "a1b2c3d4", "type": "section", "props": {…}, "advanced": {…}, "children": [ … ] }
] }
```

- `props`: theo `controls` của element. Giá trị responsive: `{ "desktop": …, "tablet": …, "mobile": … }` (tablet/mobile kế thừa nếu thiếu).
- `advanced`: chung mọi element — padding, margin, ẩn theo thiết bị, `htmlId`, class.
- Giới hạn (`Builder\Limits`): sâu 12 cấp, 2000 element, 1 MB, block lồng 3 cấp.
- **Mọi dữ liệu vào đều qua `Builder\Sanitizer`** (REST, CLI, import). Không bao giờ lưu HTML làm nguồn.
- Đổi cấu trúc JSON → tăng `SchemaMigrator::CURRENT` và thêm `step{N}`; không sửa tài liệu cũ bằng SQL.

### Thêm element

1. Class trong `saha-core/includes/Builder/Elements/` kế thừa `Element`:
   - `definition()`: `type`, `name`, `icon`, `category`, `allowedParents` (thường `self::CONTENT_PARENTS`), `allowedChildren`, `controls`, `dynamic`.
   - `render( Node $node, RenderContext $ctx, string $content )`: escape mọi đầu ra; `$ctx->editor` = đang trong canvas.
   - `styles( Node $node, CssRules $css )`: CSS theo thiết lập (`$css->set( ' .child', 'prop', $value )`, responsive tự xử lý).
   - `isDynamic( Node $node )`: ghi đè khi chỉ một số thiết lập làm HTML phụ thuộc request (ví dụ CTA "Mở form báo giá").
2. Đăng ký: element lõi thêm vào `ElementRegistry`; add-on dùng
   `add_action( 'saha_builder_elements', fn( $r ) => $r->register( new My_Element() ) );`
3. Thêm test sanitize + render vào `tests/smoke.php`.
4. Editor tự dựng bảng thiết lập từ `controls` — không cần code React cho element mới.

`dynamic: true` (giá, tồn kho, user, nonce…) → không vào render cache. Cache tĩnh xoá theo thế hệ SAHA khi lưu sản phẩm/term.

### Thêm khối mẫu

Khối mẫu (bảng Thêm → **Khối mẫu**) lấy từ `Builder\Patterns::all()`. Thêm khối bằng filter — `node` là một section theo schema tài liệu (không cần ID, Sanitizer tự cấp; khối sai schema bị bỏ và `wp saha qa` cảnh báo):

```php
add_filter( 'saha_builder_patterns', function ( array $patterns ): array {
	$patterns[] = array(
		'id'          => 'my-banner',
		'name'        => 'Banner khuyến mãi',
		'group'       => 'basic',          // store | basic | nhóm mới (hiện theo tên)
		'icon'        => 'megaphone',      // dashicon
		'description' => 'Banner 1 cột + nút.',
		'node'        => array( 'type' => 'section', 'children' => array( /* … */ ) ),
	);
	return $patterns;
} );
```

### Thêm loại control

PHP: class kế thừa `Builder\Controls\Control` (`type()`, `sanitize()` ném `InvalidValue` khi sai, `forClient()`), đăng ký qua `saha_builder_register_controls`. JS: component trong `saha-builder/src/builder/controls/` và nhánh tương ứng ở `Field.js`.

### CSS

- Base element: `saha-core/public/assets/css/builder.css` — dùng `:where()` để theme dễ ghi đè.
- **Bài học**: màu link/nút cần độ cụ thể class (`.saha-heading a`), vì `:where()` thua `a { color }` của theme.
- Màu luôn là biến `--saha-*` của Theme Options (`var(--saha-primary)`), không hardcode.

## 5. Theme Options

Schema: `saha-core/includes/ThemeOptions/Schema.php` → sanitize → option → biến CSS `--saha-*` in ở `<head>`. Thêm tuỳ chọn = thêm vào schema (UI React tự dựng). Filter: `saha_theme_options_schema`, `saha_css_variables`.

## 6. Template Builder, Header/Footer & Blocks

- Template nội dung: `Templates\Loader` thay file template qua `template_include` khi `Repository::resolve( RequestContext::type() )` có kết quả; khung ở `saha-core/templates/builder-template.php`. Điều kiện + độ cụ thể: `Templates\Conditions` (thuần PHP, có test). Chỉ mục `saha_template_map` v2 biên dịch khi lưu/xoá template.
- Element động: kế thừa `Builder\Elements\DynamicElement` (`subject()`, `withSubject()`, `withArchive()`, `placeholder()`) hoặc `ProductElement` (chỉ cần `output()` gọi hàm template WooCommerce). Luôn `dynamic`. Trong editor, đối tượng xem trước do `Templates\Preview` chọn.

- `saha_template` (header/footer): meta loại + điều kiện; option `saha_template_map` được biên dịch khi lưu → lúc render chỉ đọc một option. saha-theme gọi filter `saha_render_header` / `saha_render_footer`; không có template → header PHP của theme.
- `saha_block`: element Block render bản mới nhất; chống vòng lặp; tối đa 3 cấp.

### Mega menu

`saha-core/includes/MegaMenu/`: meta mục menu → filter của walker mặc định (`nav_menu_css_class`, `nav_menu_item_attributes`, `nav_menu_link_attributes`, `walker_nav_menu_start_el`, `wp_nav_menu_args`). Bật cho một lời gọi menu: `wp_nav_menu( [ …, 'saha_mega' => true ] )`; phần tử header bao ngoài cần `position`. Style link menu luôn nhắm `.menu-item > a` để không đè nội dung Block trong bảng mega.

## 7. WooCommerce

| Ở đâu | Làm gì |
|---|---|
| `saha-core/includes/WooCommerce/CatalogMode.php` | catalogue phía server: `is_purchasable` false (chặn cả Store API), giá → "Liên hệ báo giá" |
| `saha-core/includes/WooCommerce/BuyNow.php` | nút Mua ngay = submit thứ hai của form WC → chuyển thanh toán. Filter `saha_buy_now_enabled` |
| `WooCommerce/Swatches.php` + `js/swatches.js` | ô chữ / màu / ảnh điều khiển `<select>` gốc (kiểu ở option `saha_woocommerce_settings`, màu / ảnh ở term meta) |
| `WooCommerce/StickyCart.php` | thanh mua dính — bấm **nút gốc** của form (một đường thêm giỏ) |
| `WooCommerce/QuickView.php` + `GET /products/{id}/quick-view` | HTML hộp xem nhanh; thêm giỏ qua Store API |
| `WooCommerce/MiniCart.php` + `js/mini-cart.js` | ngăn giỏ hàng từ Store API (`cart`, `update-item`, `remove-item`); mở khi `added_to_cart` hoặc `sahaMiniCart.open( cart )` |
| `WooCommerce/QuoteList.php` + `js/quote-list.js` | danh sách báo giá ở localStorage → `POST /quote/list`; trang tự tạo (option `saha_quote_list_page`) |

Tuỳ chọn cửa hàng (Theme Options → Cửa hàng): `swatches`, `sticky_cart`, `quick_view`, `mini_cart`, `quote_list`. JS của các tính năng này nằm trong `saha-core/public/assets/js/` (ES5, không qua build; kiểm cú pháp bằng `node --check`).
| `saha-theme/inc/woocommerce.php`, `inc/catalog.php` | hook trình bày (thương hiệu, SKU, CTA báo giá, bộ lọc, modal) |
| `saha-theme/src/scss/_woocommerce.scss` | giao diện; selector phải cụ thể bằng `woocommerce.css` |

**Không override template WooCommerce** nếu hook làm được. Nếu buộc phải override: đặt ở `saha-theme/woocommerce/`, giữ dòng `@version`; `wp saha qa` cảnh báo khi bản gốc mới hơn.

## 8. Hook chính

| Hook | Loại | Dùng để |
|---|---|---|
| `saha_core_modules` | filter | thêm/bớt module saha-core |
| `saha_builder_elements` | action | đăng ký element |
| `saha_builder_register_controls` | action | đăng ký control |
| `saha_builder_render_element` | filter | sửa HTML một element |
| `saha_builder_post_types` | filter | post type dùng được builder |
| `saha_builder_saved` · `saha_template_saved` | action | sau khi lưu layout / template |
| `saha_template_resolved` | filter | đổi header/footer cho một request |
| `saha_quote_modal_needed` | action | element báo theme cần in modal báo giá |
| `saha_buy_now_enabled` | filter | bật/tắt Mua ngay theo sản phẩm |
| `saha_builder_patterns` | filter | thêm / bớt Khối mẫu của bảng Thêm |
| `saha_quote_created` | action | sau khi tạo báo giá — `$data['items']` có các dòng khi là danh sách nhiều sản phẩm |
| `saha_search_results` | filter | kết quả `/search` (giá thêm ở `Search::with_prices`, ngoài cache) |
| `saha_theme_script_config` | filter (theme) | `SAHA_CONFIG` cho JS catalogue |
| `saha_cf_purge_post_urls` | filter | thêm URL cần xoá cache Cloudflare khi một bài đổi (`Performance\CloudflarePurge`) |
| `saha_cf_purge_everything_post_types` | filter | post type mà lưu là xoá toàn bộ cache Cloudflare (mặc định template, block…) |

Danh sách đủ: README của `saha-core`.

## 9. REST

`/wp-json/saha/v1/` — mọi route có `permission_callback` (`wp saha qa` kiểm).

| Nhóm | Route |
|---|---|
| Builder (quyền `edit_saha_builder`) | `builder/elements`, `builder/patterns`, `builder/{id}`, `builder/save` (khoá bài + `baseHash` → 409, sai dữ liệu → 422 kèm lỗi theo `node.prop`), `builder/render`, `builder/lock/{id}`; `blocks` |
| Public đọc (rate limit) | `nonce`, `search` (kèm `price`, rỗng ở catalogue), `products`, `products/{id}`, `products/{id}/quick-view` (404 nếu không công khai), `brands`, `brands/{slug}` |
| Public ghi (rate limit + nonce + honeypot) | `quote`, `contact`, `quote/list`, `newsletter` |

Nonce cho form trên trang có thể bị cache: `GET /nonce` trả `nonce` (`wp_rest`, gửi header `X-WP-Nonce`) và `form` (`saha_public_form`, gửi trong body `saha_nonce` **không kèm** header — request chạy như khách nên dùng được cả khi trình duyệt đang đăng nhập).

## 9b. Import / Export

`saha-core/includes/ImportExport/`: `Exporter` (gói `saha-export` v1), `Importer` (validate → ảnh → block → trang → template → Theme Options), `Walker` (đổi ID ảnh `{id,size}` và `blockId`, thuần PHP). Đổi định dạng gói → tăng `Exporter::VERSION` và giữ đọc được bản cũ. Element mới có prop chứa ID bài / term / ảnh khác dạng `{id,size}` → bổ sung `Walker`, nếu không ID sẽ sai khi chuyển site.

## 10. CLI

```bash
wp saha qa [--strict]          # kiểm tra hệ thống (exit 1 khi lỗi; --strict: cả cảnh báo)
wp saha seed [--with-crm]      # dữ liệu mẫu (local/staging) — gỡ: wp saha unseed
wp saha homepage [--front]     # trang chủ mẫu 14 khối dựng bằng builder
wp saha flush-cache            # xoá cache SAHA + render cache (sửa element mà không đổi version)
wp saha cf-purge [<url>...]    # xoá cache Cloudflare (không URL = toàn bộ); cần SAHA_CF_ZONE_ID + SAHA_CF_API_TOKEN
wp saha starter-store [--front] [--no-palette] [--restore-options]   # bộ giao diện kiểu cửa hàng
wp saha maintenance …          # dọn log, cache
wp saha export <file> [--pages=<ids|none>] [--templates=…] [--blocks=…] [--no-theme-options]
wp saha import <file> [--dry-run] [--keep-page-status] [--front-page] [--replace-templates] [--no-theme-options] [--only=blocks,pages,templates] --user=<admin>
```

## 11. Phát hành

1. Tăng version: `saha-core.php` (header + `SAHA_CORE_VERSION`), `saha-builder.php`, `saha-theme/style.css` + `SAHA_THEME_VERSION`.
2. Đổi DB → migration trong `Install`/`Migrator` (chạy khi admin mở wp-admin); đổi JSON builder → `SchemaMigrator`.
3. `npm run build`, chạy đủ mục 3, cập nhật `docs/scc/PHASE-*.md` và bảng trạng thái trong `README.md`.
4. Trên staging: `wp saha qa --strict`, `php tests/http-smoke.php <staging> --write`, checklist `docs/QA.md`, Lighthouse mobile (LCP < 2.5s, CLS < 0.1).

## 12. Quy ước

- PHP: `declare( strict_types=1 )`, chuẩn WordPress Coding Standards, namespace `Saha\Core\…` (PSR-4) cho code mới; không đặt thư mục PSR-4 trùng tên (không phân biệt hoa thường) với thư mục cũ.
- Escape khi in, sanitize khi nhận, nonce + capability cho mọi thao tác ghi.
- Text domain: `saha-core`, `saha-builder`, `saha` (theme). Chuỗi giao diện tiếng Việt.
- Không commit `wp-config.php`, `.env*`, `uploads/`, `*.sql`, `debug.log`.
