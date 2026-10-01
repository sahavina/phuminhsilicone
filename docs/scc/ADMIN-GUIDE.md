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
| Administrator | mọi thứ, gồm **Header, Footer & Templates**, Theme Options, Cấu hình |
| Editor | dựng/sửa trang và Blocks bằng builder; **không** sửa header/footer |
| Quản lý nội dung / SEO / Kho (role SAHA) | sửa sản phẩm WooCommerce theo phạm vi của mình |

Element **HTML** chỉ người có quyền `unfiltered_html` (administrator) mới chèn được mã script; người khác lưu sẽ bị lọc bỏ phần nguy hiểm.

## 2. Màu sắc, chữ, logo — Theme Options

**Giao diện → SAHA Theme Options.**

- **Màu**: màu chính (nút, giá, link), màu phụ (header, footer), màu nhấn ("Mua ngay"), chữ, viền, nền.
- **Chữ**: font, cỡ chữ thân bài và H1–H3. Font có chữ "(Google Fonts)" (Be Vietnam Pro, Montserrat…) được tải từ Google khi chọn; font hệ thống thì không tải gì.
- **Bố cục**: độ rộng khung, khoảng cách, số cột sản phẩm desktop / tablet / mobile.
- **Cửa hàng → Kiểu thẻ sản phẩm**: "Cửa hàng" = nhãn giảm %, nút giỏ (hoặc báo giá) tròn trên ảnh, vài dòng thông số; thanh "Đã bán" chỉ bật khi muốn khoe số bán thật.
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

### Khối mẫu & Block đã lưu

Cột **Thêm** có 3 mục ở trên cùng: **Element · Khối mẫu · Block đã lưu**.

- **Khối mẫu**: section dựng sẵn (hero, danh mục, sản phẩm nổi bật, quảng bá, vì sao chọn, thương hiệu, tin tức + đánh giá, hỏi đáp, CTA báo giá, ảnh + nội dung…). Bấm hoặc kéo vào trang → được **bản sao** để sửa tự do. Trang đã có tiêu đề H1 thì H1 của khối mẫu tự thành H2. Khối "Tin tức + khách hàng nói" có đánh giá **mẫu** — thay bằng đánh giá thật.
- **Block đã lưu**: các Block dùng chung đã tạo. Chèn vào là **liên kết** tới block — sửa block một nơi, mọi trang dùng nó cùng đổi. Tạo block: chọn element → tab **Nội dung** → **Lưu thành block**.

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
| Sản phẩm | nguồn: nổi bật, mới, khuyến mại, theo danh mục / thương hiệu / ứng dụng; **Tab lọc: Theo danh mục** để có hàng tab "Tất cả · Keo Silicone · …" |
| Danh mục sản phẩm | lưới danh mục, **ứng dụng** hoặc **thương hiệu** |
| Bài viết | bài blog mới nhất / theo chuyên mục |
| Block | chèn một Block dùng chung (mục 4) |
| Tiêu đề khối | tiêu đề có gạch màu nhấn + link "Xem tất cả →" bên phải |
| Danh sách icon | các dòng có dấu ✓ (mỗi dòng một mục) |
| Chữ chạy | thanh thông báo chạy ngang (mỗi dòng một mục) |
| Slider | ảnh trượt; thêm ảnh bằng cách nhân đôi Slide (Ctrl+D). Slide đầu của banner đầu trang bật "Ưu tiên tải" |
| Đánh giá khách hàng | thẻ đánh giá; nhân đôi "Một đánh giá" để thêm |
| Accordion / Hỏi đáp | câu hỏi thường gặp, bật "Dữ liệu cấu trúc FAQ" cho Google (mỗi trang một khối FAQ) |

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

**SAHA → Header, Footer & Templates** (chỉ administrator).

- Lần đầu: **Tạo header & footer mặc định** → cả hai hiện **✓ Đang dùng**. Muốn header kiểu cửa hàng (chữ chạy, ô tìm kiếm lớn, hotline, nút "Danh mục sản phẩm"): **Tạo header kiểu cửa hàng** → sửa → **Dùng cho toàn site**.
- Sửa: bấm tên → builder. Header gồm các **hàng** (thanh trên, hàng chính, hàng mobile), mỗi hàng 3 **vùng** trái – giữa – phải.
  - Hàng chỉ hiện trên một loại màn hình: tab Nâng cao → Ẩn trên …
  - **Header** (element ngoài cùng): **Dính khi cuộn** — luôn hiện / chỉ khi cuộn lên / không.
  - **Menu di động**: nội dung của bảng trượt khi bấm ☰.
- **Menu** lấy từ **Giao diện → Menu** (vị trí "Menu chính", "Menu chân trang") — đổi menu không cần mở builder.
- Hotline, email, mạng xã hội lấy từ **SAHA → Cấu hình**.
- Có nhiều header: bấm **Dùng cho toàn site** ở header muốn dùng. Chuyển header đang dùng về Nháp → website quay về header mặc định của theme (không bao giờ trắng).

### Mega menu

**Giao diện → Menu** → mở một mục **cấp 1** → khung **SAHA — kiểu menu**:

| Ô | Chọn |
|---|---|
| Kiểu | **Mega menu** |
| Nội dung | **Menu con chia cột** — mỗi mục cấp 2 thành một cột, mục cấp 3 là danh sách dưới cột; hoặc **Block: …** — nội dung dựng bằng builder ở SAHA → Blocks (ảnh, lưới thương hiệu, nút báo giá…) |
| Độ rộng | bằng khung nội dung / toàn màn hình / tuỳ chỉnh (px) |
| Số cột menu con | 1–6 (khi dùng menu con chia cột) |

Bấm **Lưu menu**. Mega chỉ hiện ở menu ngang trên máy tính; trên điện thoại, menu ☰ vẫn hiện menu con như thường (mục dùng Block thì hiện như một link). Nếu Block bị xoá/chuyển nháp, mục đó tự quay về kiểu menu con chia cột và **Kiểm tra hệ thống** sẽ cảnh báo.

### Template trang sản phẩm, danh mục, bài viết…

Cùng màn **SAHA → Header, Footer & Templates** → **Thêm template:** chọn loại (Trang sản phẩm, Shop / danh mục sản phẩm, Bài viết, Blog / chuyên mục, Trang, Kết quả tìm kiếm, Trang 404) → **Tạo từ mẫu**. Mẫu giống giao diện hiện tại; sửa trong builder bằng nhóm element **Template (động)** và **Trang sản phẩm (động)** — chúng tự lấy tiêu đề, giá, ảnh… của trang đang xem. Trong builder, template được xem trước bằng một sản phẩm/bài thật.

Template mới **chưa áp dụng**. Bấm **Điều kiện** (hoặc "Sửa điều kiện" ở cột Áp dụng):

- **Áp dụng cho**: Tất cả, danh mục/thương hiệu/chuyên mục (danh mục cha áp cho cả danh mục con), sản phẩm/bài/trang cụ thể, loại trang danh sách…
- **Trừ**: những trang không dùng template này.
- **Ưu tiên**: khi hai template cùng khớp, cái **cụ thể hơn** thắng (sản phẩm cụ thể > danh mục > loại trang > tất cả); cùng mức thì ưu tiên số lớn hơn thắng.
- **Xem trước trong builder bằng**: chọn sản phẩm/bài dùng khi dựng.

Ví dụ: một template "Trang sản phẩm" cho tất cả, thêm một template riêng cho danh mục "Keo Silicone" — sản phẩm Silicone dùng template riêng, còn lại dùng template chung. Xoá hoặc chuyển template về Nháp → trang quay về giao diện mặc định của theme.

> Template "Shop / danh mục" áp cho cả trang thương hiệu sẽ thay phần đầu thương hiệu (logo, mô tả) của theme. Muốn giữ phần đầu thương hiệu, chọn điều kiện là "Trang Shop" và các danh mục cụ thể thay vì "Tất cả".

### Nút nổi

**Giao diện → SAHA Theme Options → Nút nổi**: nút liên hệ tròn ở góc màn hình (mở Gọi hotline · Chat Zalo · Yêu cầu báo giá) và nút lên đầu trang. Mặc định không hiện trên điện thoại vì đã có thanh liên hệ ở chân màn hình.

## 6. Trang chủ

**Giao diện kiểu cửa hàng (một nút):** Trang → Tất cả trang → **Áp dụng giao diện kiểu cửa hàng** → xác nhận. Website có header 3 tầng, trang chủ 9 khối (hero, danh mục, sản phẩm có tab, ô quảng bá, vì sao chọn, giải pháp, thương hiệu, tin tức + đánh giá, hỏi đáp), footer 4 cột, màu navy + vàng đồng. Việc cần làm ngay sau đó: thay **đánh giá khách hàng mẫu** và **câu trả lời mẫu** trong hỏi đáp bằng nội dung thật, chọn ảnh hero (Slide), ảnh danh mục/thương hiệu. Muốn quay lại màu/font cũ: nhờ kỹ thuật chạy `wp saha starter-store --restore-options`.

**Trang danh mục kiểu cửa hàng:** nút trên cũng tạo template **Shop & danh mục kiểu cửa hàng**. Muốn đổi template đang có mà không chạy lại cả bộ: SAHA → Templates → mở template Shop/danh mục trong builder →
- element **Tiêu đề danh sách (động)** → Kiểu: **Khung + thẻ số sản phẩm**; ô **Nhãn thêm** (ví dụ “Giao hàng toàn quốc”, để trống = không hiện);
- element **Danh sách sản phẩm (động)** → Bố cục: **Cột lọc bên trái**; bật/tắt **danh sách danh mục** và **khoảng giá**.

Khoảng giá tự chia theo giá thật của sản phẩm trong danh mục (ví dụ “Dưới 3.000.000 đ · 3–6 triệu · …”) và **ẩn ở chế độ catalogue** (không lộ giá). Trên điện thoại cột lọc gập vào nút **Danh mục & bộ lọc**.


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

### Biến thể, thanh mua dính, xem nhanh

**Theme Options → Cửa hàng**:

| Tuỳ chọn | Mặc định | Tác dụng |
|---|---|---|
| **Ô chọn màu / ảnh / chữ cho biến thể** | Bật | Ô chọn biến thể (màu, dung tích…) thành nút bấm. Kiểu của từng thuộc tính đặt ở **Sản phẩm → Thuộc tính → Sửa**: **Kiểu chọn trên trang sản phẩm**: *Danh sách thả xuống*, *Ô chữ*, *Ô màu*, *Ô ảnh*. Ô màu/ảnh: vào **Cấu hình giá trị**, sửa từng giá trị để chọn màu hoặc ảnh. Tổ hợp hết hàng/không tồn tại tự mờ đi. |
| **Thanh "Thêm vào giỏ" dính** | Tắt | Trang sản phẩm: khi cuộn qua nút mua, thanh dưới đáy hiện ảnh + tên + giá + nút. Chưa chọn biến thể → cuộn về form. Chế độ catalogue → nút **Yêu cầu báo giá**. |
| **Nút "Xem nhanh"** | Tắt | Thẻ sản phẩm có nút **Xem nhanh** (thẻ kiểu cửa hàng: nút tròn góc ảnh) mở hộp ảnh + giá + chọn biến thể + thêm vào giỏ, không rời trang. |
| **Bấm icon giỏ / thêm vào giỏ → mở ngăn giỏ hàng** | Bật | Bấm icon giỏ hoặc thêm vào giỏ từ thẻ sản phẩm / Xem nhanh → ngăn bên phải: đổi số lượng, xoá, tạm tính, nút **Xem giỏ hàng** / **Thanh toán**. Không có ở chế độ catalogue. |

**Gợi ý khi gõ**: element **Tìm kiếm** (header) → bật **Gợi ý khi gõ (ảnh, mã, giá)** (mặc định bật). Gõ từ 2 ký tự → tối đa 6 sản phẩm + **Xem tất cả N kết quả**. Chế độ catalogue không hiện giá.

## 8. Báo giá & khách liên hệ

### Danh sách báo giá nhiều sản phẩm

1. **Theme Options → Cửa hàng** → bật **Danh sách báo giá nhiều sản phẩm**. Lần đầu bật, hệ thống tự tạo trang **Danh sách báo giá** (`/danh-sach-bao-gia/`) — sửa bằng SAHA Builder như trang thường.
2. **Header Builder** → thêm element **Icon danh sách báo giá** (nhóm Header) cạnh hotline / giỏ hàng: hiện số sản phẩm khách đã chọn, bấm để mở trang danh sách.
3. Trang sản phẩm và hộp Xem nhanh có nút **Thêm vào danh sách báo giá**. Khách sửa số lượng, ghi chú (quy cách, màu…) rồi gửi **một** yêu cầu.

Trong **SAHA → Yêu cầu báo giá**: yêu cầu nhiều sản phẩm hiện tên "Sản phẩm đầu (+N sản phẩm khác)"; mở chi tiết để xem bảng từng dòng (sản phẩm, SKU, số lượng, ghi chú). Email báo admin và lead cũng có đủ danh sách. **Báo cáo → Top sản phẩm được hỏi giá** đếm cả danh sách và báo giá một sản phẩm cũ.

Danh sách lưu trong trình duyệt của khách cho tới khi gửi (đổi máy thì không còn).

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
| Header về kiểu mặc định | Header đang dùng bị chuyển Nháp/xoá → SAHA → Header, Footer & Templates → Dùng cho toàn site |
| Trang sản phẩm/danh mục không đổi theo template | Template chưa có điều kiện (cột Áp dụng ghi "Chưa áp dụng") → Sửa điều kiện; hoặc template khác cụ thể hơn đang thắng |
| Không nhận email báo giá | Cài/kiểm tra plugin SMTP; báo giá vẫn được lưu ở SAHA → Yêu cầu báo giá |
