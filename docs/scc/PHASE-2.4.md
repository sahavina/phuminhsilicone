# SCC Phase 2 — Mốc 2.4: Giỏ & tìm kiếm

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md); kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.3.md](PHASE-2.3.md).

## 1. Goal

- **Ngăn giỏ hàng (mini cart drawer)**: bấm icon giỏ hoặc thêm vào giỏ → ngăn bên phải với danh sách, đổi số lượng, xoá, tạm tính, Xem giỏ hàng / Thanh toán. Không JS → icon vẫn là link trang giỏ.
- **Gợi ý khi gõ (live search)** cho element Tìm kiếm: ảnh, tên, mã, thương hiệu, **giá** + "Xem tất cả N kết quả".
- **Bộ lọc sản phẩm AJAX**: đã làm ở [D5](PHASE-D5.md) (cột lọc, khoảng giá, sắp xếp, chip — AJAX, không JS vẫn chạy). Mốc này không làm thêm.

Nghiệm thu: **thêm vào giỏ mở ngăn; gõ "243" ra gợi ý; lọc không tải lại trang (D5).**

## 2. Architecture

```
Icon giỏ [data-saha-mini-cart] ─click─┐
added_to_cart (wc-add-to-cart.js) ────┼─► mini-cart.js: <dialog class="saha-mc"> ◄─ GET  /wc/store/v1/cart
Xem nhanh: sahaMiniCart.open(cart) ───┘                                         ─► POST cart/update-item, cart/remove-item
                                          → cập nhật .saha-cart-count + chữ đọc màn hình

Element Tìm kiếm (live) [data-saha-live-search] ─► live-search.js ─► GET /saha/v1/search?q=&limit=6
                                                                     (+ price: Search::with_prices, ngoài cache)
```

| Thành phần | Vai trò |
|---|---|
| `WooCommerce\MiniCart` | bật/tắt (`shop.mini_cart`, catalogue, trang giỏ/thanh toán), nạp `mini-cart.js/css`, cấu hình Store API + nonce + chữ |
| `Builder\Elements\Cart` | thêm `data-saha-mini-cart` + `aria-haspopup="dialog"` khi bật; chữ đọc màn hình có class `saha-cart-sr` để JS cập nhật |
| `Builder\Elements\Search` | tuỳ chọn **Gợi ý khi gõ** (`live`, mặc định bật); nạp `live-search.js/css` khi element được in |
| `Search::with_prices()` | thêm `price` (chữ thuần) vào kết quả REST `/saha/v1/search`; catalogue → rỗng |
| `quick-view.js` | thêm giỏ thành công + có ngăn giỏ → đóng hộp Xem nhanh, mở ngăn (focus trả về nút Xem nhanh khi đóng) |
| `HeaderRow` | màu chữ hàng header không tô link trong bảng gợi ý (`.saha-ls a`) |

| Quyết định | Lý do |
|---|---|
| Ngăn giỏ dựng từ **Store API**, không dùng wc-cart-fragments | Fragments gọi AJAX ở mỗi lượt tải trang; Store API chỉ gọi khi mở ngăn / sửa giỏ. |
| Giá trong gợi ý tính **lúc trả về**, không nằm trong cache tìm kiếm | Đổi giá hoặc bật/tắt catalogue có hiệu lực ngay; catalogue không bao giờ lộ giá qua API. |
| Giá tự dựng (khoảng giá biến thể, giá hiển thị) thay vì `get_price_html()` | Bản HTML có chữ ẩn cho trình đọc màn hình ("Giá gốc là…") — bỏ thẻ sẽ lộ ra thành chữ thừa. |
| Single product vẫn gửi form như cũ (tải lại trang, thông báo WooCommerce) | Giữ một đường thêm giỏ chuẩn cho biến thể / Mua ngay / plugin khác; ngăn giỏ mở từ thẻ sản phẩm và Xem nhanh. |

## 3. Files

- `saha-core/includes/WooCommerce/MiniCart.php` (mới), `WooCommerce/Module.php`.
- `saha-core/public/assets/js/{mini-cart,live-search}.js`, `css/{mini-cart,live-search}.css` (mới); `js/quick-view.js`.
- `saha-core/includes/Builder/Elements/{Cart,Search,HeaderRow}.php`, `includes/class-search.php`, `api/routes/010-search.php`.
- `saha-core/includes/ThemeOptions/Schema.php` — `shop.mini_cart` (mặc định bật).
- SAHA Core **1.23.0**.

## 4. Accessibility

- Ngăn giỏ: `<dialog>` + `showModal()` (Esc, giữ focus), `aria-labelledby` tiêu đề; mở → focus tiêu đề; đóng → focus về icon / nút đã bấm. Sau khi đổi số lượng, focus quay lại đúng nút của dòng đó; xoá dòng → focus tiêu đề. `role="status"` báo "Đã thêm / Đã cập nhật giỏ hàng". Nút −/+/xoá có nhãn kèm tên sản phẩm.
- Gợi ý tìm kiếm: combobox ARIA 1.2 (`aria-expanded`, `aria-controls`, `aria-activedescendant`), `role="listbox"` / `option`, mũi tên lên/xuống, Enter mở, Esc đóng; `role="status"` đọc số gợi ý.
- `prefers-reduced-motion`: bỏ hiệu ứng trượt.

## 5. Tests

| Kiểm tra | Kết quả |
|---|---|
| `tests/smoke.php` (thêm: mặc định `mini_cart`, `live`; giá rỗng ở catalogue; selector HeaderRow) | 240 passed |
| `tests/http-smoke.php` (thêm: `/search` trả `price` cho từng kết quả) | 35 passed, 2 skipped |
| `npm run lint:js`, `lint:css`, `test:js` | đạt |
| `wp saha qa` | 91 đạt, 3 cảnh báo (môi trường local), 0 lỗi; debug.log không có dòng mới |
| Trình duyệt — gợi ý | gõ "243" → "Keo khoá ren Loctite 243", mũi tên chọn (`aria-activedescendant`), Enter mở trang sản phẩm; catalogue → không có giá, tắt catalogue → có giá |
| Trình duyệt — ngăn giỏ (tắt catalogue tạm thời) | thêm từ thẻ → ngăn mở; + → số lượng 2, tạm tính và số trên icon cập nhật, focus giữ ở nút +; xoá → "Giỏ hàng đang trống", icon 0; đóng → focus về icon; Xem nhanh → thêm → hộp đóng, ngăn mở, đóng ngăn → focus về nút Xem nhanh |
| Mobile 375px | ngăn rộng 100%, không tràn ngang; bảng gợi ý bằng ô tìm kiếm |
| Trang giỏ hàng / catalogue | không nạp `mini-cart.js`; icon giỏ ẩn ở catalogue như cũ |

Sau khi thử, chế độ catalogue đã bật lại trên local.

## 6. Limitations / tiếp theo

- Trang sản phẩm đơn vẫn tải lại trang khi thêm giỏ (không mở ngăn).
- Ngăn giỏ chưa có mã giảm giá / phí giao hàng — dùng trang giỏ hàng.
- Mốc tiếp: **2.5** báo giá nhiều sản phẩm.
