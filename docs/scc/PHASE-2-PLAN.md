# SCC Phase 2 — Kế hoạch mốc

> Phạm vi theo [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §11 (Phase 2, 8–12 tuần) và §6.4 (element Phase 2).
> Mỗi mốc: `docs/scc/PHASE-2.x.md` cùng mẫu 11 mục, commit trên branch, **dừng chờ duyệt**.

Phase 1 đã nghiệm thu trên local ([PHASE-1.7.md](PHASE-1.7.md)); phần staging (`wp saha qa --strict`, Lighthouse, ma trận trình duyệt) chạy song song khi có staging — không chặn Phase 2 vì Phase 2 không đổi những phần đó.

## Thứ tự

Nguyên tắc: mốc nhỏ, tự đứng được, phần dùng chung đi trước phần dùng nó.

| Mốc | Nội dung | Kết thúc khi | Phụ thuộc |
|---|---|---|---|
| 2.1 | **Mega menu**: meta mục menu (`_saha_menu_type`, `_saha_mega_settings`), ô cài đặt ở Giao diện → Menu, nội dung = Block dùng chung hoặc cột menu con; chạy với element Menu (ngang) và header PHP của theme; bàn phím + `aria` | menu "Sản phẩm" mở mega 4 cột / một Block; mobile vẫn là menu thường | Blocks (1.4), Header (1.5) |
| 2.2 | **Template Builder + điều kiện**: loại `single_product`, `product_archive`, `single_post`, `archive`, `search`, `404`, `page`; điều kiện theo trang/loại bài/danh mục/thương hiệu, độ cụ thể + `priority`; element động (Tiêu đề bài, Nội dung, Breadcrumb, Ảnh đại diện, Vòng lặp archive, Phân trang; sản phẩm: Gallery, Giá, Thêm vào giỏ/Báo giá, Meta, Tabs, Liên quan) | trang sản phẩm và danh mục dựng bằng builder, điều kiện chọn đúng template | 1.2–1.6 |
| 2.3 | **Trang sản phẩm nâng cao**: Swatches (màu/ảnh/nhãn, giữ `<select>` gốc), Sticky add to cart (điều khiển form gốc), Quick view (`GET /products/{id}/quick-view` → `<dialog>`, Store API) | biến thể chọn bằng swatch, sticky bar và quick view thêm vào giỏ đúng biến thể | 2.2 (element Thêm vào giỏ) |
| 2.4 | **Giỏ & tìm kiếm**: mini cart drawer (Store API, fallback link), Live search UI (dùng `Search` hiện có + giá), Product Filter UI (element + AJAX trên `Filter` hiện có) | thêm vào giỏ mở drawer; gõ "243" ra gợi ý; lọc không tải lại trang | 2.3 |
| 2.5 | **Danh sách báo giá nhiều sản phẩm**: migration 006 `wp_saha_quote_items`, nút "Thêm vào danh sách báo giá", trang danh sách, admin xem theo dòng, báo cáo "sản phẩm được hỏi nhiều" | một yêu cầu gồm nhiều sản phẩm + số lượng; dữ liệu cũ vẫn đọc được | Quote (Phase 4 cũ) |
| 2.6 | **Import/Export**: layout trang, Blocks, Header/Footer/Template, Theme Options → file JSON; import kiểm schema + Sanitizer, ảnh qua `media_sideload_image`, chỉ `manage_options` | xuất site A → nhập site B ra cùng giao diện | 2.2 |
| 2.7 | **Element Phase 2**: Tabs, Accordion, Gallery, Video, Slider, Logo/Logo Slider, Testimonial, Countdown, Product Slider, Post Slider, Newsletter; Grid/Stack | mỗi element có sanitize + render + test | 2.2 |
| 2.8 | **QA Phase 2** + cập nhật DEV/ADMIN-GUIDE | checklist Phase 2 đạt trên local + staging | tất cả |

## Rủi ro riêng Phase 2

| Rủi ro | Giảm thiểu |
|---|---|
| Template Builder (2.2) lớn nhất — element động phụ thuộc vòng lặp WordPress/WooCommerce | Element động luôn `dynamic: true`; render qua hàm template của WooCommerce (`woocommerce_template_single_*`) thay vì viết lại; không có template → PHP mặc định (như header/footer) |
| Swatches/sticky/quick view nhân bản logic giỏ hàng | Chỉ điều khiển form gốc + `wc-add-to-cart-variation`; Store API cho quick view |
| Import file độc hại | Chỉ `manage_options`, giới hạn kích thước, mọi tài liệu qua Sanitizer, không import user/đơn hàng |
