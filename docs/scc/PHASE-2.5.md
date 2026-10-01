# SCC Phase 2 — Mốc 2.5: Danh sách báo giá nhiều sản phẩm

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §7 (bảng `wp_saha_quote_items`), §9 (`POST /quote/list`), §10 (Quote list); kế hoạch: [PHASE-2-PLAN.md](PHASE-2-PLAN.md). Mốc trước: [PHASE-2.4.md](PHASE-2.4.md).

## 1. Goal

- Khách gom **nhiều sản phẩm + số lượng + ghi chú** vào một danh sách rồi gửi **một** yêu cầu báo giá.
- Admin xem yêu cầu theo **từng dòng sản phẩm**; email / lead có đủ danh sách.
- Báo cáo **"sản phẩm được hỏi nhiều"** đếm cả dòng của danh sách lẫn báo giá một sản phẩm cũ.
- **Dữ liệu cũ vẫn đọc được**: báo giá một sản phẩm (trước mốc này) không cần chuyển đổi.

Nghiệm thu: **một yêu cầu gồm nhiều sản phẩm + số lượng; dữ liệu cũ vẫn đọc được.**

## 2. Architecture

```
Nút "Thêm vào danh sách báo giá" [data-saha-ql-add]       ┐
  (trang sản phẩm, element Thêm vào giỏ ở chế độ báo giá,  ├─► quote-list.js ─► localStorage `saha_quote_list_v1`
   hộp Xem nhanh)                                          ┘        │                 (đồng bộ giữa các tab)
Icon header (element quote-list-link) .saha-ql-count ◄──────────────┤
Trang "Danh sách báo giá" (element quote-list) ◄────────────────────┘
   sửa SL / ghi chú, xoá, form liên hệ ──► POST /saha/v1/quote/list  (X-WP-Nonce; 403 → lấy nonce mới → gửi lại 1 lần)
                                              │ rate limit 5/10 phút · honeypot · Quote::validate + validate_items
                                              ▼
                       Quote::create( $data, $items ) ─► wp_saha_quotes (dòng đầu + tên tóm tắt)
                                                      └► wp_saha_quote_items (mỗi dòng)  ─► saha_quote_created
                                                                                             ├ Mailer (bảng dòng)
                                                                                             └ Lead (mỗi dòng 1 hàng)
```

| Thành phần | Vai trò |
|---|---|
| `database/migrations/006-create-quote-items.php` | bảng `wp_saha_quote_items` (`quote_id`, `product_id`, `variation_id`, `sku`, `product_name`, `quantity`, `note`, `created_at`; index `quote_id`, `product_id`). DB schema **1.3.0** |
| `Quote::validate_items()` | tối đa 50 dòng, SL 1–100000, ghi chú ≤ 255; chỉ tin ID — tên/SKU đọc lại từ DB; biến thể phải thuộc đúng sản phẩm cha; dòng trùng gộp; sản phẩm không còn bán → `invalid[]` |
| `Quote::create( $data, $items )` | lưu dòng **trước** `saha_quote_created`; cột sản phẩm của `quotes` = dòng đầu + "(+N sản phẩm khác)" → danh sách admin, tìm kiếm, tiêu đề email cũ vẫn chạy; chống gửi trùng theo chữ ký danh sách |
| `Quote::items()` / `list_rows()` | đọc dòng; báo giá cũ → dựng 1 dòng từ bản ghi `quotes` |
| `Quote::top_products()` | `UNION ALL` dòng danh sách + báo giá cũ không có dòng; đếm số yêu cầu + tổng SL |
| `api/routes/045-quote-list.php` | `POST /saha/v1/quote/list` |
| `WooCommerce\QuoteList` | tuỳ chọn `shop.quote_list` (mặc định **tắt**), nút thêm, tự tạo trang khi bật, nạp JS/CSS |
| `Builder\Elements\QuoteList` (`quote-list`), `QuoteListLink` (`quote-list-link`) | trang danh sách; icon header có số |
| `admin/views/crm-detail.php`, `crm-reports.php`, `templates/emails/quote-admin.php`, `class-lead.php` | bảng dòng; cột "Tổng SL"; email có bảng dòng; lead ghi mỗi dòng một hàng |

| Quyết định | Lý do |
|---|---|
| Danh sách ở **localStorage**, không ở session / cookie server | Trang vẫn cache được; không tạo phiên WooCommerce cho khách chỉ xem; gửi một lần nên không cần đồng bộ server. Đổi thiết bị → danh sách không theo (ghi ở giới hạn). |
| Bảng 1-n `quote_items`, **không** nhét JSON vào `quotes` | Thiết kế §7: cần truy vấn "sản phẩm nào được hỏi nhiều". |
| Giữ cột sản phẩm của `quotes` (dòng đầu + tóm tắt) | Màn danh sách admin, tìm kiếm, email, CRM table không phải sửa; báo giá cũ và mới cùng một bảng. |
| Báo cáo đếm **số yêu cầu**, SL chỉ cộng của danh sách | Báo giá cũ ghi SL dạng chữ ("50 thùng") — không cộng được. |
| Tuỳ chọn mặc định **tắt** | Thêm nút mới vào trang sản phẩm là thay đổi giao diện; admin chủ động bật. |

## 3. Files

- Mới: `saha-core/database/migrations/006-create-quote-items.php`, `api/routes/045-quote-list.php`, `includes/WooCommerce/QuoteList.php`, `includes/Builder/Elements/{QuoteList,QuoteListLink}.php`, `public/assets/{js,css}/quote-list.{js,css}`.
- Sửa: `includes/class-quote.php`, `class-lead.php`, `class-qa.php` (kiểm tra bảng mới), `admin/views/{crm-detail,crm-reports}.php`, `templates/emails/quote-admin.php`, `Builder/ElementRegistry.php` (62 element), `Builder/Elements/ProductAddToCart.php`, `WooCommerce/{Module,QuickView}.php`, `ThemeOptions/Schema.php`.
- SAHA Core **1.24.0**, DB schema **1.3.0** (migration tự chạy khi vào wp-admin).

## 4. Accessibility

- Nút thêm là `<button>`; kết quả báo bằng thông báo nổi `role="status"` kèm link "Xem danh sách".
- Trang danh sách: ô SL / ghi chú có nhãn kèm tên sản phẩm; nút xoá có nhãn; xoá dòng → focus sang nút xoá của dòng kế; "Xoá tất cả" hỏi xác nhận.
- Form: `<label for>`, trường bắt buộc có dấu * + chữ cho trình đọc màn hình, lỗi gắn `aria-invalid` + `aria-describedby`, focus vào ô lỗi đầu tiên; kết quả gửi ở vùng `role="alert"` được focus.

## 5. Tests

| Kiểm tra | Kết quả |
|---|---|
| `tests/smoke.php` (thêm: mặc định tuỳ chọn, validate_items đầu vào sai / quá 50 dòng, tên tóm tắt, chữ ký chống trùng, 2 element mới; cập nhật số element 62 và danh sách element động) | 247 passed |
| `tests/http-smoke.php --write` (thêm: thiếu nonce 403, danh sách rỗng 422, sản phẩm giả 422 + `data.invalid`, hợp lệ 201 count 2, gửi lại 200 duplicate) | 5/5 đạt (xem §5.1) |
| JS của saha-core | `node --check` (script `lint:js` của dự án chỉ quét theme + saha-builder — áp dụng cho cả mốc 2.3, 2.4) |
| `wp saha qa` | 92 đạt (thêm: DB version 1.3.0, bảng `quote_items` + 2 index), 0 lỗi |
| Trình duyệt | thêm từ trang sản phẩm (thông báo + dấu ✓), thêm sản phẩm thứ 2; trang danh sách: sửa SL 25 + ghi chú lưu ngay; gửi thiếu tên → lỗi tại ô, focus ô; gửi đủ → 201, danh sách làm trống, thông báo được focus; mobile 375px không tràn, 1 H1 |
| Dữ liệu | báo giá #16: 2 dòng (SL 25, ghi chú lưu đúng), cột tóm tắt "Keo khoá ren Loctite 243 (+1 sản phẩm khác)"; báo giá cũ #12 đọc ra 1 dòng; báo cáo gộp; lead ghi mỗi dòng một hàng; email (render template) có bảng dòng |
| Admin | chi tiết báo giá: bảng "Danh sách báo giá (2 sản phẩm)" — sản phẩm, SKU, SL, ghi chú |

### 5.1 http-smoke --write

Lần chạy đầu: 4/5 test danh sách đạt, test thứ 5 bị **429** vì cùng IP vừa gửi tay một danh sách trên trình duyệt (giới hạn 5 lần / 10 phút tính cả lần đó) — không phải lỗi code. Chạy lại sau 10 phút: **5/5 đạt** (403, 422 rỗng, 422 + `data.invalid`, 201 count 2, 200 duplicate). `http-smoke` không ghi: 35 passed, 2 skipped.

## 6. Limitations / tiếp theo

- Danh sách nằm trong trình duyệt: đổi máy / xoá dữ liệu trình duyệt thì mất.
- Chế độ catalogue không có form biến thể → thêm sản phẩm cha (khách ghi biến thể ở ô ghi chú). Khi tắt catalogue, biến thể đang chọn được thêm đúng `variation_id`.
- Thẻ sản phẩm (lưới) chưa có nút thêm — dùng trang sản phẩm hoặc Xem nhanh.
- Icon header không tự thêm vào header kiểu cửa hàng: thêm element **Icon danh sách báo giá** trong Header Builder.
- Mốc tiếp: mục **Khối mẫu + Block đã lưu** trong cột "Thêm" của builder (yêu cầu mới), sau đó **2.6** Import/Export.
