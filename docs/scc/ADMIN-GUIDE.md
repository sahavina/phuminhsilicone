# Hướng dẫn quản trị — SAHA Commerce Core

> Dành cho người quản trị website Tổng Kho Keo Dán SAHA (spec §116). Không cần biết code.
> Tài liệu kỹ thuật cho lập trình viên: [DEV-GUIDE.md](DEV-GUIDE.md).

Website gồm 3 phần:

| Phần | Là gì | Tắt đi thì sao |
|---|---|---|
| **SAHA Core** (plugin) | dữ liệu & nghiệp vụ: thương hiệu, báo giá, liên hệ, tìm kiếm, chế độ catalogue, hiển thị trang dựng sẵn | website mất chức năng — **không bao giờ tắt** |
| **SAHA Builder** (plugin) | trình soạn thảo kéo thả | trang vẫn hiển thị bình thường, chỉ không sửa được bằng kéo thả |
| **SAHA Theme** (giao diện) | khung trang, WooCommerce, màu sắc | — |

## 1. Quyền

| Vai trò | Làm được |
|---|---|
| Administrator | mọi thứ, gồm **Header & Footer**, Theme Options, Cấu hình |
| Editor | dựng/sửa trang và Blocks bằng builder; **không** sửa header/footer |
| Quản lý nội dung / SEO / Kho (role SAHA) | sửa sản phẩm WooCommerce theo phạm vi của mình |

Element **HTML** chỉ người có quyền `unfiltered_html` (administrator) mới chèn được mã script; người khác lưu sẽ bị lọc bỏ phần nguy hiểm.

## 2. Màu sắc, chữ, logo — Theme Options

**Giao diện → SAHA Theme Options.**

- **Màu**: màu chính (nút, giá, link), màu phụ (header, footer), màu nhấn ("Mua ngay"), chữ, viền, nền.
- **Chữ**: font, cỡ chữ thân bài và H1–H3.
- **Bố cục**: độ rộng khung, khoảng cách, số cột sản phẩm desktop / tablet / mobile.
- **Logo**, chiều cao header, header dính.

Bấm **Lưu** → toàn website đổi theo, không cần sửa CSS. Mỗi ô có nút đặt lại mặc định.

## 3. Dựng trang bằng SAHA Builder

### Mở builder

**Trang → Tất cả trang →** rê chuột lên một trang → **Dựng bằng SAHA Builder**.

### Màn hình

| Vùng | Dùng để |
|---|---|
| Thanh trên | Hoàn tác / Làm lại, **Xem theo thiết bị** (Desktop / Tablet / Mobile), **Xem trang**, **Lưu** |
| Trái: **Thêm** | danh sách element — kéo vào trang |
| Trái: **Cấu trúc** | cây Section → Hàng → Cột → element; kéo để sắp xếp |
| Giữa | trang thật (giống hệt ngoài website) |
| Phải: **Thiết lập** | 3 tab **Nội dung · Kiểu · Nâng cao** của element đang chọn |

### Bố cục cơ bản

**Section** (dải ngang toàn trang) → **Hàng** (chia cột) → **Cột** → nội dung (Tiêu đề, Văn bản, Nút, Ảnh, Sản phẩm…).

- Thêm Hàng: tự có 2 cột; chỉnh độ rộng cột ở tab **Kiểu** của Cột.
- Hàng có thiết lập **Xếp chồng cột trên**: Mobile / Tablet — để cột xuống dòng trên màn nhỏ.

### Element hay dùng

| Element | Ghi chú |
|---|---|
| Tiêu đề | chọn thẻ H1–H6. **Mỗi trang chỉ một H1** (tốt cho SEO) |
| Văn bản | soạn như Word: đậm, nghiêng, link, danh sách |
| Nút | kiểu chính / phụ / viền; liên kết có "mở tab mới" |
| Ảnh | chọn từ Thư viện; nhớ điền **Alt** |
| Banner | ảnh nền + tiêu đề + nút. Banner đầu trang bật **Ảnh đầu trang (ưu tiên tải)** — ảnh hiện nhanh hơn |
| Hộp icon | icon + tiêu đề + mô tả ("Vì sao chọn SAHA") |
| CTA | khối kêu gọi; nút chính chọn **Mở form báo giá** để hiện form ngay tại trang |
| Sản phẩm | nguồn: nổi bật, mới, khuyến mại, theo danh mục / thương hiệu / ứng dụng |
| Danh mục sản phẩm | lưới danh mục, **ứng dụng** hoặc **thương hiệu** |
| Bài viết | bài blog mới nhất / theo chuyên mục |
| Block | chèn một Block dùng chung (mục 4) |

### Responsive

Ô có biểu tượng màn hình (cỡ chữ, khoảng cách, số cột…) đặt riêng được cho Tablet / Mobile: chuyển thiết bị trên thanh trên rồi sửa. Không đặt riêng thì Tablet lấy theo Desktop, Mobile lấy theo Tablet.

Tab **Nâng cao**: khoảng cách trong/ngoài, **Ẩn trên Desktop/Tablet/Mobile**, ID và class (cho người biết CSS).

### Phím tắt

| Phím | Tác dụng |
|---|---|
| Ctrl + S | Lưu |
| Ctrl + Z / Ctrl + Y (hoặc Ctrl + Shift + Z) | Hoàn tác / Làm lại |
| Ctrl + C / Ctrl + V | Sao chép / Dán element |
| Ctrl + D | Nhân đôi element |
| Delete / Backspace | Xoá element đang chọn |
| Esc | Bỏ chọn |

(Trên Mac dùng ⌘ thay Ctrl.)

### Hai người cùng sửa

Khi người khác đang mở trang trong builder, bấm Lưu sẽ báo **"… đang chỉnh sửa trang này"**. Nếu trang vừa được người khác lưu, bạn được báo **tải lại để xem bản mới trước khi lưu** — không ai bị ghi đè âm thầm. Bản cũ xem ở **Bản sửa đổi** của trang.

## 4. Blocks — nội dung dùng chung

**SAHA → Blocks.** Dùng cho phần lặp lại ở nhiều trang (dải CTA, khối cam kết…).

- **Thêm mới** → mở builder để dựng.
- Trong builder của một trang: kéo element **Block** → chọn block. Sửa block **một lần** → mọi trang dùng nó cập nhật.
- Chọn một Section trong trang → **Lưu thành block** để tái sử dụng. **Tách khỏi block** → biến thành nội dung riêng của trang đó.
- Cột **Đang dùng ở** cho biết block nằm ở trang nào trước khi xoá.

## 5. Header & Footer

**SAHA → Header & Footer** (chỉ administrator).

- Lần đầu: **Tạo header & footer mặc định** → cả hai hiện **✓ Đang dùng**.
- Sửa: bấm tên → builder. Header gồm các **hàng** (thanh trên, hàng chính, hàng mobile), mỗi hàng 3 **vùng** trái – giữa – phải.
  - Hàng chỉ hiện trên một loại màn hình: tab Nâng cao → Ẩn trên …
  - **Header** (element ngoài cùng): **Dính khi cuộn** — luôn hiện / chỉ khi cuộn lên / không.
  - **Menu di động**: nội dung của bảng trượt khi bấm ☰.
- **Menu** lấy từ **Giao diện → Menu** (vị trí "Menu chính", "Menu chân trang") — đổi menu không cần mở builder.
- Hotline, email, mạng xã hội lấy từ **SAHA → Cấu hình**.
- Có nhiều header: bấm **Dùng cho toàn site** ở header muốn dùng. Chuyển header đang dùng về Nháp → website quay về header mặc định của theme (không bao giờ trắng).

## 6. Trang chủ

Tạo nhanh trang chủ mẫu 14 khối: **Trang → Tất cả trang → Tạo trang chủ mẫu (SAHA Builder)**. Trang được đặt làm trang chủ và mở ngay trong builder để sửa chữ, chọn ảnh.

- Khối theo danh mục/thương hiệu (Keo Silicone, Loctite…) chỉ được tạo khi danh mục/thương hiệu đó có trên website.
- Ảnh đầu trang: chọn ảnh nền cho Section đầu tiên (tab Kiểu → Nền), hoặc thay bằng element **Banner** bật **Ảnh đầu trang (ưu tiên tải)**.

## 7. Bán hàng hay báo giá — chế độ catalogue

**SAHA → Cấu hình → Chế độ catalogue** (hoặc Theme Options).

| Bật (mặc định) | Tắt |
|---|---|
| Giá thay bằng **"Liên hệ báo giá"** | Hiện giá |
| Không có nút giỏ hàng; không mua được kể cả gọi thẳng | Nút **Thêm vào giỏ** + **Mua ngay** (đi thẳng tới thanh toán) |
| Nút **Yêu cầu báo giá** mở form, có sẵn tên + mã sản phẩm | Giỏ hàng, thanh toán, tài khoản hoạt động |

Trước khi tắt để bán hàng: bật ít nhất một phương thức ở **WooCommerce → Cài đặt → Thanh toán**, kiểm tra **tiền tệ** (VNĐ) và **giao hàng**.

## 8. Báo giá & khách liên hệ

**SAHA → Yêu cầu báo giá / Liên hệ / Khách hàng tiềm năng / Báo cáo.**

- Mỗi yêu cầu có sản phẩm, mã, số lượng, trang khách gửi; đổi trạng thái (Mới → Đã liên hệ → Đã báo giá → Thành công / Thất bại) và ghi chú nội bộ.
- Email thông báo gửi tới địa chỉ ở **SAHA → Cấu hình**. Website cần plugin SMTP để email tới được hộp thư.
- Form có chống spam (ô ẩn, giới hạn 5 lần / 10 phút mỗi máy).

## 9. Kiểm tra hệ thống

**SAHA → Kiểm tra hệ thống** — chạy sau mỗi lần cập nhật plugin/theme hoặc đổi cấu hình lớn.

- **Lỗi** (đỏ): phải sửa — làm theo dòng hướng dẫn bên cạnh.
- **Cảnh báo** (vàng): nên sửa — ví dụ chưa nhập số Zalo, chưa có plugin SEO, chưa có Redis, còn trang dùng shortcode Flatsome cũ.

## 10. Sự cố thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| Sửa trong builder nhưng ngoài website chưa đổi | Xoá cache của plugin cache / LiteSpeed / CDN |
| Trang hiện chữ dạng `[saha_…]` hoặc `[ux_…]` | Trang còn shortcode cũ — dựng lại bằng builder (Kiểm tra hệ thống liệt kê các trang này) |
| Không thấy "Dựng bằng SAHA Builder" | Plugin SAHA Builder đang tắt, hoặc tài khoản không có quyền |
| "… đang chỉnh sửa trang này" | Người khác đang mở builder; chờ hoặc nhờ họ đóng builder |
| Header về kiểu mặc định | Header đang dùng bị chuyển Nháp/xoá → SAHA → Header & Footer → Dùng cho toàn site |
| Không nhận email báo giá | Cài/kiểm tra plugin SMTP; báo giá vẫn được lưu ở SAHA → Yêu cầu báo giá |
