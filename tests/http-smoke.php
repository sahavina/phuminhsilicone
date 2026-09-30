<?php
/**
 * HTTP smoke test — chạy từ máy bất kỳ vào một site đang chạy SAHA.
 *
 * Dùng:
 *   php tests/http-smoke.php https://staging.tongkhokeodan.com
 *   php tests/http-smoke.php http://localhost/saha --write
 *
 *   --write     Chạy cả test POST /quote, /contact. Sẽ TẠO 2 báo giá + 1 lead
 *               tên "[Mẫu] QA …" (gỡ bằng `wp saha unseed`). KHÔNG dùng trên production.
 *   --insecure  Bỏ kiểm tra chứng chỉ SSL (staging tự ký).
 *
 * Chỉ cần PHP CLI có curl — không cần WordPress trên máy chạy test.
 * Thoát mã 1 nếu có test FAIL.
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$base = rtrim( (string) ( $argv[1] ?? '' ), '/' );

if ( ! preg_match( '#^https?://#', $base ) ) {
	fwrite( STDERR, "Dùng: php tests/http-smoke.php <URL site> [--write] [--insecure]\n" );
	exit( 2 );
}

$write    = in_array( '--write', $argv, true );
$insecure = in_array( '--insecure', $argv, true );
$api      = $base . '/wp-json/saha/v1';

$pass = 0;
$fail = 0;
$skip = 0;

/**
 * Gửi request.
 *
 * @return array{status: int, headers: array<string, string>, body: string, json: mixed}
 */
function http( string $method, string $url, array $headers = array(), ?array $json = null ): array {
	global $insecure;

	$ch = curl_init( $url );

	$response_headers = array();

	curl_setopt_array(
		$ch,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => ! $insecure,
			CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
			CURLOPT_USERAGENT      => 'SAHA-http-smoke/1.0',
			CURLOPT_HTTPHEADER     => array_merge(
				array( 'Accept: application/json, text/html;q=0.9' ),
				null !== $json ? array( 'Content-Type: application/json' ) : array(),
				$headers
			),
			CURLOPT_POSTFIELDS     => null !== $json ? json_encode( $json ) : null,
			CURLOPT_HEADERFUNCTION => static function ( $ch, string $line ) use ( &$response_headers ): int {
				$parts = explode( ':', $line, 2 );

				if ( 2 === count( $parts ) ) {
					$response_headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				}

				return strlen( $line );
			},
		)
	);

	$body   = curl_exec( $ch );
	$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$error  = curl_error( $ch );

	curl_close( $ch );

	if ( false === $body ) {
		fwrite( STDERR, "Không kết nối được {$url}: {$error}\n" );
		exit( 2 );
	}

	return array(
		'status'  => $status,
		'headers' => $response_headers,
		'body'    => (string) $body,
		'json'    => json_decode( (string) $body, true ),
	);
}

function ok( string $name, bool $cond, string $detail = '' ): void {
	global $pass, $fail;

	if ( $cond ) {
		$pass++;
		echo "  ok    {$name}\n";
		return;
	}

	$fail++;
	echo "  FAIL  {$name}" . ( '' !== $detail ? "\n        {$detail}" : '' ) . "\n";
}

function skip( string $name, string $why ): void {
	global $skip;

	$skip++;
	echo "  skip  {$name} — {$why}\n";
}

function first_item( array $res ): array {
	return (array) ( $res['json']['data']['items'][0] ?? array() );
}

echo "SAHA HTTP smoke test → {$base}\n\n";

/*
 * ---------------------------------------------------------------------------
 * REST đọc (an toàn trên production)
 * ---------------------------------------------------------------------------
 */
echo "REST — nonce (spec §27)\n";
$r     = http( 'GET', $api . '/nonce' );
$nonce = (string) ( $r['json']['data']['nonce'] ?? '' );
ok( 'GET /nonce → 200 + nonce', 200 === $r['status'] && '' !== $nonce, 'status ' . $r['status'] );
ok( '/nonce gửi header no-cache', (bool) preg_match( '/no-cache|no-store|max-age=0/i', $r['headers']['cache-control'] ?? '' ), 'Cache-Control: ' . ( $r['headers']['cache-control'] ?? '(trống)' ) );

echo "REST — tìm kiếm (spec §9, §58)\n";
$r = http( 'GET', $api . '/search?q=243' );
ok( 'q=243 → 200, envelope success', 200 === $r['status'] && true === ( $r['json']['success'] ?? null ) );
$first = first_item( $r );

if ( ! $first ) {
	skip( 'q=243 → Loctite 243 đứng đầu', 'không có sản phẩm khớp (chạy `wp saha seed`)' );
} else {
	ok( 'q=243 → sản phẩm chứa "243" đứng đầu', false !== stripos( ( $first['name'] ?? '' ) . ' ' . ( $first['sku'] ?? '' ), '243' ), 'Đầu tiên: ' . ( $first['name'] ?? '' ) );
	ok( 'kết quả có đủ ảnh/tên/SKU/brand/category/link', count( array_intersect( array( 'thumbnail', 'name', 'sku', 'brand', 'category', 'url' ), array_keys( $first ) ) ) === 6 );
}

$r     = http( 'GET', $api . '/search?q=' . rawurlencode( 'apollo a500' ) );
$first = first_item( $r );

if ( ! $first ) {
	skip( '"apollo a500" → Apollo Silicone A500 đứng đầu', 'không có sản phẩm khớp' );
} else {
	ok( '"apollo a500" → sản phẩm chứa "A500" đứng đầu', false !== stripos( ( $first['name'] ?? '' ) . ' ' . ( $first['sku'] ?? '' ), 'a500' ), 'Đầu tiên: ' . ( $first['name'] ?? '' ) );
}

$lower = http( 'GET', $api . '/search?q=apollo' );
$upper = http( 'GET', $api . '/search?q=APOLLO' );
ok( 'không phân biệt hoa/thường', array_column( (array) ( $lower['json']['data']['items'] ?? array() ), 'id' ) === array_column( (array) ( $upper['json']['data']['items'] ?? array() ), 'id' ) );

$r = http( 'GET', $api . '/search?q=a' );
ok( 'q quá ngắn → 400', 400 === $r['status'], 'status ' . $r['status'] );

$r = http( 'GET', $api . '/search?q=' . rawurlencode( "' OR 1=1 -- " ) );
ok( 'SQL injection trong q → 200, không lỗi', 200 === $r['status'], 'status ' . $r['status'] );

$r = http( 'GET', $api . '/search?q=' . rawurlencode( 'zzqxyw-khong-ton-tai' ) );
ok( 'không có kết quả → items rỗng', 200 === $r['status'] && 0 === (int) ( $r['json']['data']['total'] ?? -1 ) );

echo "REST — sản phẩm & thương hiệu (spec §31, §79)\n";
$r = http( 'GET', $api . '/products' );
ok( 'GET /products (không tham số) → 200', 200 === $r['status'], 'status ' . $r['status'] );

$r = http( 'GET', $api . '/products?brand=loctite&per_page=5' );
$items = (array) ( $r['json']['data']['items'] ?? array() );
ok( 'GET /products?brand=loctite → chỉ sản phẩm Loctite', 200 === $r['status'] && ( ! $items || count( array_filter( $items, static fn( $i ) => 'Loctite' === ( $i['brand'] ?? '' ) ) ) === count( $items ) ), 'status ' . $r['status'] );

$r        = http( 'GET', $api . '/products?per_page=99999' );
$per_page = (int) ( $r['json']['data']['per_page'] ?? 0 );
ok( 'per_page=99999 bị từ chối hoặc bị chặn ≤ 50', 400 === $r['status'] || ( 200 === $r['status'] && $per_page <= 50 ), 'status ' . $r['status'] . ', per_page ' . $per_page );

$r = http( 'GET', $api . '/products/999999999' );
ok( 'sản phẩm không tồn tại → 404 + envelope lỗi', 404 === $r['status'] && false === ( $r['json']['success'] ?? null ), 'status ' . $r['status'] );

$r = http( 'GET', $api . '/brands' );
ok( 'GET /brands → 200', 200 === $r['status'] );
ok( '/brands không lộ attachment ID nội bộ', ! preg_match( '/"(logo|banner)_id"/', $r['body'] ) );

$r = http( 'GET', $api . '/brands/khong-ton-tai-xyz' );
ok( 'thương hiệu không tồn tại → 404', 404 === $r['status'], 'status ' . $r['status'] );

/*
 * ---------------------------------------------------------------------------
 * SEO & cache (spec §22, §27, §51)
 * ---------------------------------------------------------------------------
 */
echo "SEO & cache\n";
$r = http( 'GET', $base . '/robots.txt' );

if ( 404 === $r['status'] && '' !== (string) parse_url( $base, PHP_URL_PATH ) ) {
	// WordPress chỉ phục vụ robots.txt ở gốc domain.
	skip( 'robots.txt', 'site nằm trong thư mục con — robots.txt chỉ có ở gốc domain (dùng `wp saha qa` để kiểm)' );
	$r['body'] = '';
} else {
	ok( 'robots.txt chặn ?s=', false !== strpos( $r['body'], '?s=' ) );
}
$blocks = false;
foreach ( preg_split( '/\r?\n/', $r['body'] ) as $line ) {
	if ( preg_match( '#^\s*Disallow:\s*(\S+)#i', $line, $m )
		&& ! preg_match( '#/uploads/(wc-logs|woocommerce_uploads|woocommerce_transient_files)/#', $m[1] )
		&& preg_match( '#(\.css|\.js)(\$|\*)?$|/wp-includes/?$|/wp-content/?$|/wp-content/(themes|plugins|uploads)/?$#', $m[1] ) ) {
		$blocks = true;
	}
}
ok( 'robots.txt KHÔNG chặn CSS/JS/theme/plugin/uploads', ! $blocks );

$r = http( 'GET', $base . '/?s=keo&post_type=product' );
ok( 'trang tìm kiếm có noindex', (bool) preg_match( '/<meta[^>]+name=["\']robots["\'][^>]+noindex/i', $r['body'] ), 'status ' . $r['status'] );

$r = http( 'GET', $base . '/' );
ok( 'trang chủ → 200', 200 === $r['status'], 'status ' . $r['status'] );
ok( 'không in lỗi PHP ra trang (spec §40)', ! preg_match( '/(Fatal error|Warning|Notice|Deprecated)<\/b>:/', $r['body'] ) );
$theme_active = false !== strpos( $r['body'], 'saha-site' );

if ( $theme_active ) {
	ok( 'không còn wp-emoji-release (Phase 7)', false === strpos( $r['body'], 'wp-emoji-release' ) );
} else {
	skip( 'không còn wp-emoji-release', 'child theme SAHA chưa bật (cần Flatsome)' );
}
ok( 'trang chủ có đúng 1 thẻ H1', 1 === preg_match_all( '/<h1[\s>]/i', $r['body'] ), 'số H1: ' . preg_match_all( '/<h1[\s>]/i', $r['body'] ) );

if ( ! $theme_active ) {
	skip( 'trang chủ preload ảnh hero', 'child theme SAHA chưa bật (cần Flatsome)' );
} elseif ( preg_match( '/<link[^>]+rel=["\']preload["\'][^>]+as=["\']image["\']/i', $r['body'] ) ) {
	ok( 'trang chủ preload ảnh hero (Phase 7)', true );
} else {
	skip( 'trang chủ preload ảnh hero', 'trang chủ chưa có [ux_banner] chọn ảnh' );
}

$r = http( 'GET', $base . '/?saha_form=quote_sent' );
ok( 'trang kết quả form không bị cache', (bool) preg_match( '/no-cache|no-store|max-age=0/i', $r['headers']['cache-control'] ?? '' ), 'Cache-Control: ' . ( $r['headers']['cache-control'] ?? '(trống)' ) );

/*
 * ---------------------------------------------------------------------------
 * REST ghi (spec §57) — chỉ khi --write
 * ---------------------------------------------------------------------------
 */
if ( ! $write ) {
	echo "\nForm POST — bỏ qua (thêm --write để chạy, chỉ trên local/staging)\n";
} else {
	$auth  = array( 'X-WP-Nonce: ' . $nonce );
	$phone = '09' . str_pad( (string) random_int( 0, 99999999 ), 8, '0', STR_PAD_LEFT );
	$valid = array(
		'name'       => '[Mẫu] QA http-smoke',
		'phone'      => $phone,
		'email'      => 'qa@example.com',
		'product_id' => 0,
		'quantity'   => '1 chai',
		'message'    => 'Test tự động — có thể xoá.',
	);

	echo "Form báo giá (spec §57) — rate limit 5 lần / 10 phút\n";
	$r = http( 'POST', $api . '/quote', array(), $valid );

	if ( 429 === $r['status'] ) {
		skip( 'toàn bộ test POST /quote', 'IP đang bị rate limit từ lần chạy trước — chờ 10 phút' );
	} else {
		ok( '1. thiếu nonce → 403', 403 === $r['status'], 'status ' . $r['status'] );

		$r = http( 'POST', $api . '/quote', $auth, array_merge( $valid, array( 'email' => 'khong-phai-email' ) ) );
		ok( '2. email sai → 422 + errors.email', 422 === $r['status'] && isset( $r['json']['errors']['email'] ), 'status ' . $r['status'] );

		$r = http( 'POST', $api . '/quote', $auth, array_merge( $valid, array( 'product_id' => 999999999 ) ) );
		ok( '3. product_id giả → 422 + errors.product_id', 422 === $r['status'] && isset( $r['json']['errors']['product_id'] ), 'status ' . $r['status'] );

		$r = http( 'POST', $api . '/quote', $auth, $valid );
		ok( '4. hợp lệ → 201', 201 === $r['status'], 'status ' . $r['status'] . ' ' . substr( $r['body'], 0, 200 ) );

		$r = http( 'POST', $api . '/quote', $auth, $valid );
		ok( '5. gửi lại ngay → 200 duplicate (chống double submit)', 200 === $r['status'] && true === ( $r['json']['data']['duplicate'] ?? null ), 'status ' . $r['status'] );

		$r = http( 'POST', $api . '/quote', $auth, $valid );
		ok( '6. lần thứ 6 trong 10 phút → 429', 429 === $r['status'], 'status ' . $r['status'] );
	}

	echo "Form liên hệ (spec §32)\n";
	$contact = array(
		'name'    => '[Mẫu] QA http-smoke liên hệ',
		'phone'   => $phone,
		'message' => 'Test tự động — có thể xoá.',
	);

	$r = http( 'POST', $api . '/contact', $auth, array_merge( $contact, array( 'saha_hp_email' => 'bot@spam.test' ) ) );

	if ( 429 === $r['status'] ) {
		skip( 'toàn bộ test POST /contact', 'IP đang bị rate limit — chờ 10 phút' );
	} else {
		ok( 'honeypot bị điền → trả "thành công" giả (200)', 200 === $r['status'], 'status ' . $r['status'] );

		$r = http( 'POST', $api . '/contact', $auth, array_merge( $contact, array( 'message' => '' ) ) );
		ok( 'thiếu nội dung → 422', 422 === $r['status'] && isset( $r['json']['errors']['message'] ), 'status ' . $r['status'] );

		$r = http( 'POST', $api . '/contact', $auth, array_merge( $contact, array( 'name' => '[Mẫu] <script>alert(1)</script>' ) ) );
		ok( 'XSS trong tên → vẫn lưu an toàn (201)', 201 === $r['status'], 'status ' . $r['status'] );
	}
}

echo "\n{$pass} passed, {$fail} failed, {$skip} skipped\n";
exit( $fail > 0 ? 1 : 0 );
