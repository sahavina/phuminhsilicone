<?php
/**
 * Smoke test cho logic thuần của saha-core — không cần WordPress/MySQL.
 *
 * Chạy:  php tests/smoke.php
 *
 * Dùng stub tối thiểu cho các hàm WordPress. Không thay thế checklist test
 * trên site thật trong docs/PHASE-*.md, nhưng bắt được lỗi validate/sanitize
 * trước khi deploy.
 */
declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'SAHA_CORE_PATH', dirname( __DIR__ ) . '/wp-content/plugins/saha-core/' );

// Đặt option TRƯỚC mọi lần đọc: Settings cache option trong phạm vi request.
$GLOBALS['__options'] = array( 'saha_core_settings' => array( 'search_synonyms' => "keo kính = silicone, keo nhôm kính\nbọt nở = pu foam" ) );
$GLOBALS['__posts']   = array( 42 => array( 'type' => 'product', 'status' => 'publish', 'title' => 'Loctite 243' ), 7 => array( 'type' => 'post', 'status' => 'publish', 'title' => 'Blog' ) );

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value, ...$args ) { return $value; }
function do_action( ...$a ) {}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return preg_replace( '/[^a-z0-9@._+\-]/i', '', (string) $s ); }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function esc_url_raw( $s ) { return filter_var( $s, FILTER_VALIDATE_URL ) ? $s : ''; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://tongkhokeodan.com' . $p; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_kses_post( $s ) { return (string) $s; }
function get_post_type( $id ) { return $GLOBALS['__posts'][ $id ]['type'] ?? false; }
function get_post_status( $id ) { return $GLOBALS['__posts'][ $id ]['status'] ?? false; }
function get_the_title( $id ) { return $GLOBALS['__posts'][ $id ]['title'] ?? ''; }

spl_autoload_register( static function ( $c ) {
	if ( 0 !== strpos( $c, 'Saha\\Core\\' ) ) { return; }
	$rel   = substr( $c, 10 );
	$parts = explode( '\\', $rel );
	$base  = array_pop( $parts );
	$sub   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
	require_once SAHA_CORE_PATH . 'includes/' . $sub . 'class-' . strtolower( str_replace( '_', '-', $base ) ) . '.php';
} );

use Saha\Core\Customer;
use Saha\Core\Quote;
use Saha\Core\Lead;
use Saha\Core\Search;
use Saha\Core\Security;

$fail = 0;
$pass = 0;
function check( string $name, $got, $want ): void {
	global $fail, $pass;
	if ( $got === $want ) { $pass++; echo "  ok   $name\n"; return; }
	$fail++;
	echo "  FAIL $name\n       got:  " . var_export( $got, true ) . "\n       want: " . var_export( $want, true ) . "\n";
}

echo "Customer::normalize_phone\n";
check( 'định dạng có dấu chấm', Customer::normalize_phone( '0966.75.3382' ), '0966753382' );
check( '+84 đổi thành 0', Customer::normalize_phone( '+84 966 75 3382' ), '0966753382' );
check( '84 không dấu +', Customer::normalize_phone( '84966753382' ), '0966753382' );

echo "Security::tel_digits (spec §85)\n";
check( 'bỏ dấu chấm', Security::tel_digits( '0966.75.3382' ), '0966753382' );
check( 'giữ dấu +', Security::tel_digits( '+84 966-75-3382' ), '+84966753382' );
check( 'loại ký tự lạ', Security::tel_digits( '0966<script>' ), '0966' );

echo "Quote::sanitize_source_url\n";
check( 'cùng domain giữ nguyên', Quote::sanitize_source_url( 'https://tongkhokeodan.com/san-pham/loctite-243/' ), 'https://tongkhokeodan.com/san-pham/loctite-243/' );
check( 'domain lạ bị loại', Quote::sanitize_source_url( 'https://evil.example/phish' ), '' );
check( 'javascript: bị loại', Quote::sanitize_source_url( 'javascript:alert(1)' ), '' );

echo "Quote::validate (spec §57)\n";
$ok = Quote::validate( array( 'name' => 'Anh Nam', 'phone' => '0966.75.3382', 'email' => 'nam@example.com', 'product_id' => 42, 'product_name' => 'GIẢ MẠO', 'sku' => 'FAKE' ) );
check( 'valid submit không lỗi', $ok['errors'], array() );
check( 'tên sản phẩm lấy từ DB, không từ client', $ok['data']['product_name'], 'Loctite 243' );
check( 'SKU client gửi bị bỏ qua', $ok['data']['sku'], '' );

$r = Quote::validate( array( 'name' => 'A', 'phone' => '' ) );
check( 'empty phone', isset( $r['errors']['phone'] ), true );

$r = Quote::validate( array( 'name' => 'A', 'phone' => '0966753382', 'email' => 'khong-phai-email' ) );
check( 'invalid email', isset( $r['errors']['email'] ), true );

$r = Quote::validate( array( 'name' => 'A', 'phone' => '0966753382', 'product_id' => 7 ) );
check( 'product_id trỏ tới post thường bị từ chối', isset( $r['errors']['product_id'] ), true );

$r = Quote::validate( array( 'name' => 'A', 'phone' => '0966753382', 'product_id' => 99999 ) );
check( 'product_id không tồn tại bị từ chối', isset( $r['errors']['product_id'] ), true );

$r = Quote::validate( array( 'name' => '<script>alert(1)</script>Nam', 'phone' => '0966753382' ) );
check( 'XSS trong tên bị strip', $r['data']['customer_name'], 'alert(1)Nam' );

$r = Quote::validate( array( 'name' => "Nam' OR 1=1 --", 'phone' => '0966753382' ) );
check( 'SQL injection chỉ là chuỗi thường, không lỗi validate', $r['errors'], array() );

$r = Quote::validate( array( 'name' => 'A', 'phone' => '123' ) );
check( 'SĐT quá ngắn', isset( $r['errors']['phone'] ), true );

echo "Lead::validate (spec §32)\n";
$r = Lead::validate( array( 'name' => 'Chị Lan', 'phone' => '0966793669', 'message' => 'Tư vấn keo', 'source' => 'quote' ) );
check( 'nguồn lạ vẫn hợp lệ với list sources', $r['data']['source'], 'quote' );
$r = Lead::validate( array( 'name' => 'Chị Lan', 'phone' => '0966793669', 'message' => '', 'source' => 'hack' ) );
check( 'thiếu nội dung', isset( $r['errors']['message'] ), true );
check( 'nguồn không hợp lệ về contact', $r['data']['source'], 'contact' );

echo "Search::normalize / tokenize (spec §9, §58)\n";
check( 'lowercase + gộp khoảng trắng', Search::normalize( "  APOLLO   A500 " ), 'apollo a500' );
check( 'strip tag', Search::normalize( '<b>243</b>' ), '243' );
check( 'giữ dấu tiếng Việt', Search::normalize( 'KEO Dán Gạch' ), 'keo dán gạch' );
check( 'tokenize 2 từ', Search::tokenize( 'apollo a500' ), array( 'apollo', 'a500' ) );

check( 'synonym mở rộng token', Search::tokenize( 'keo kính' ), array( 'keo', 'kính', 'silicone', 'keo nhôm kính' ) );
check( 'synonym không áp khi không khớp', Search::tokenize( 'loctite' ), array( 'loctite' ) );

echo "Catalog (spec §35, §36)\n";
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( (string) $s ) ), '-' ); }
}

$saha_clause = new ReflectionMethod( Saha\Core\Catalog::class, 'tax_clause' );
$saha_clause->setAccessible( true );
check( 'slug → field slug', $saha_clause->invoke( null, 'product_cat', 'keo-silicone', true ), array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'keo-silicone', 'include_children' => true ) );
check( 'ID từ termSelect → field term_id', $saha_clause->invoke( null, 'product_brand', '15' ), array( 'taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => 15 ) );

$saha_norm = new ReflectionMethod( Saha\Core\Catalog::class, 'normalize_product_args' );
$saha_norm->setAccessible( true );
$saha_n = $saha_norm->invoke( null, array( 'source' => 'DROP TABLE', 'limit' => 99999, 'orderby' => 'meta_value; --', 'ids' => '3, 5,abc,0' ) );
check( 'source lạ → latest', $saha_n['source'], 'latest' );
check( 'limit bị chặn ở 24', $saha_n['limit'], Saha\Core\Catalog::MAX_LIMIT );
check( 'orderby lạ → date', $saha_n['orderby'], 'date' );
check( 'ids chỉ giữ số dương', $saha_n['ids'], array( 3, 5 ) );

echo "Seo (spec §22, §51)\n";
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return false; }
}
if ( ! function_exists( 'is_search' ) ) {
	function is_search() { return ! empty( $GLOBALS['__is_search'] ); }
}

check( 'không có Rank Math/Yoast → provider none', Saha\Core\Seo::provider(), 'none' );
check( 'auto + không có Rank Math → schema WooCommerce', Saha\Core\Seo::product_schema_source(), 'woocommerce' );

$saha_trim = new ReflectionMethod( Saha\Core\Seo::class, 'trim_description' );
$saha_trim->setAccessible( true );
$saha_long = str_repeat( 'Keo silicone trung tính chống nấm mốc ', 10 );
$saha_cut  = $saha_trim->invoke( null, $saha_long );
check( 'description ≤ 160 ký tự', mb_strlen( $saha_cut ) <= 160, true );
check( 'description không cắt giữa từ', 1 === preg_match( '/\S…$/u', $saha_cut ) && false === strpos( $saha_cut, ' …' ), true );
check( 'description ngắn giữ nguyên', $saha_trim->invoke( null, 'Keo Apollo A500' ), 'Keo Apollo A500' );

$saha_seo   = new Saha\Core\Seo();
$saha_txt   = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: https://tongkhokeodan.com/wp-sitemap.xml\n";
$saha_robot = $saha_seo->filter_robots_txt( $saha_txt, true );
check( 'robots.txt chặn ?s=', false !== strpos( $saha_robot, 'Disallow: /?s=' ), true );
check( 'robots.txt chặn admin-post.php', false !== strpos( $saha_robot, 'Disallow: /wp-admin/admin-post.php' ), true );
check( 'robots.txt KHÔNG chặn URL lọc saha_', false === strpos( $saha_robot, 'saha_brand' ), true );
check( 'robots.txt KHÔNG chặn CSS/JS/uploads', 0 === preg_match( '#Disallow: .*(\.css|\.js|uploads|wp-content)#', $saha_robot ), true );
check( 'dòng SAHA nằm trong nhóm User-agent: *, trước Sitemap', strpos( $saha_robot, '# SAHA' ) < strpos( $saha_robot, 'Sitemap:' ), true );
check( 'site không public → giữ nguyên', $saha_seo->filter_robots_txt( $saha_txt, false ), $saha_txt );

$_GET = array( 'saha_brand' => 'loctite' );
check( 'URL lọc → noindex, follow', $saha_seo->filter_wp_robots( array( 'max-image-preview' => 'large' ) ), array( 'max-image-preview' => 'large', 'noindex' => true, 'follow' => true ) );
check( 'Rank Math robots cho URL lọc', $saha_seo->filter_rank_math_robots( array( 'index' => 'index' ) ), array( 'index' => 'noindex', 'follow' => 'follow' ) );
$_GET = array();
check( 'URL sạch → robots giữ nguyên', $saha_seo->filter_wp_robots( array( 'max-image-preview' => 'large' ) ), array( 'max-image-preview' => 'large' ) );
$GLOBALS['__is_search'] = true;
check( 'trang tìm kiếm → noindex', isset( $saha_seo->filter_wp_robots( array() )['noindex'] ), true );
$GLOBALS['__is_search'] = false;
check( 'sitemap core bỏ provider users', $saha_seo->filter_core_sitemap_providers( 'x', 'users' ), false );

echo "Settings select (sanitize)\n";
$saha_clean = Saha\Core\Settings::sanitize( array( 'product_schema_source' => 'evil<script>' ) );
check( 'giá trị select lạ → mặc định', $saha_clean['product_schema_source'], 'auto' );
$saha_clean = Saha\Core\Settings::sanitize( array( 'product_schema_source' => 'seo_plugin' ) );
check( 'giá trị select hợp lệ giữ nguyên', $saha_clean['product_schema_source'], 'seo_plugin' );

echo "Cache (spec §27, §80)\n";
$GLOBALS['__transients'] = array();
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $v ) { return json_encode( $v ); }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) { return $GLOBALS['__transients'][ $k ] ?? false; }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $k, $v, $autoload = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
}

Saha\Core\Cache::reset_request_state();
$saha_calls = 0;
$saha_fn    = static function () use ( &$saha_calls ): array { $saha_calls++; return array(); };

Saha\Core\Cache::remember( 'products', array( 'a' => 1 ), $saha_fn );
Saha\Core\Cache::remember( 'products', array( 'a' => 1 ), $saha_fn );
check( 'kết quả RỖNG vẫn được cache (không query lại)', $saha_calls, 1 );

$saha_key_before = Saha\Core\Cache::key( 'products', array( 'a' => 1 ) );
Saha\Core\Cache::bump();
$saha_key_after = Saha\Core\Cache::key( 'products', array( 'a' => 1 ) );
check( 'bump đổi key → cache cũ tự vô hiệu', $saha_key_before !== $saha_key_after, true );

Saha\Core\Cache::remember( 'products', array( 'a' => 1 ), $saha_fn );
check( 'sau bump: tính lại đúng 1 lần', $saha_calls, 2 );

$saha_gen = Saha\Core\Cache::generation();
Saha\Core\Cache::bump();
Saha\Core\Cache::bump();
check( 'nhiều hook trong 1 request chỉ tăng thế hệ 1 lần', Saha\Core\Cache::generation(), $saha_gen );
check( 'key transient < 172 ký tự', strlen( Saha\Core\Cache::key( 'search_ids', array( str_repeat( 'x', 500 ) ) ) ) < 172, true );

echo "Maintenance (spec §28, §60)\n";
check( 'không bao giờ xoá quotes', Saha\Core\Maintenance::purge( 'quotes', 1 ), 0 );
check( 'không bao giờ xoá leads', Saha\Core\Maintenance::purge( 'leads', 1 ), 0 );
check( 'retention 0 = không xoá', Saha\Core\Maintenance::purge( 'logs', 0 ), 0 );

echo "Hero LCP (spec §25)\n";
require_once dirname( __DIR__ ) . '/wp-content/themes/flatsome-child/inc/performance.php';
check( 'tìm bg của ux_banner đầu tiên', saha_theme_find_hero_image_id( '[section][ux_banner height="460px" bg="321" bg_size="original"][text_box]…[/ux_banner][ux_banner bg="999"]' ), 321 );
check( 'banner chưa chọn ảnh → 0', saha_theme_find_hero_image_id( '[ux_banner height="460px" bg=""]' ), 0 );
check( 'không có banner → 0', saha_theme_find_hero_image_id( '[row][col]Nội dung[/col][/row]' ), 0 );
check( 'không nhầm thuộc tính bg_color', saha_theme_find_hero_image_id( '[ux_banner bg_color="123"]' ), 0 );

echo "REST — hồi quy lỗi phát hiện khi QA trên WordPress thật\n";
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { public function __construct( ...$a ) {} }
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $s = '' ) { return 'test-salt'; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
}

// Lỗi 1: REST gọi sanitize_callback( $value, $request, $param ); sanitize_title()
// hiểu tham số 2 là $fallback_title → trả về chính WP_REST_Request khi giá trị rỗng.
$saha_req = new WP_REST_Request();
check( 'sanitize_slug với giá trị rỗng + request object → chuỗi rỗng', Saha\Core\Api::sanitize_slug( '', $saha_req, 'brand' ), '' );
check( 'sanitize_slug giữ slug hợp lệ', Saha\Core\Api::sanitize_slug( 'Loctite', $saha_req, 'brand' ), 'loctite' );
check( 'sanitize_slug với mảng → rỗng', Saha\Core\Api::sanitize_slug( array( 'x' ) ), '' );

// Lỗi 2: WordPress gọi permission_callback 2 lần / request (kiểm quyền + header Allow).
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$GLOBALS['__transients'] = array();
Saha\Core\Api::reset_request_state();
$saha_perm = Saha\Core\Api::public_permission( 'quote', 5, 600 );
$saha_perm( $saha_req );
$saha_perm( $saha_req );
$saha_counter = array_values( array_filter( $GLOBALS['__transients'], 'is_int' ) );
check( 'rate limit chỉ đếm 1 lần dù permission_callback bị gọi 2 lần', $saha_counter, array( 1 ) );

// Lỗi 3: lọc tình trạng hàng — sản phẩm không đặt field không có dòng meta.
$saha_contact = Saha\Core\Filter::availability_clause( 'contact' );
check( '"Liên hệ" không fallback sang _stock_status (tránh gộp nhầm hàng có sẵn)', $saha_contact, array( 'key' => '_saha_availability', 'value' => 'contact', 'compare' => '=' ) );

$saha_out  = Saha\Core\Filter::availability_clause( 'out' );
$saha_json = json_encode( $saha_out );
check( '"Hết hàng" fallback dùng NOT EXISTS', false !== strpos( $saha_json, 'NOT EXISTS' ), true );
check( '"Hết hàng" fallback so với outofstock', false !== strpos( $saha_json, 'outofstock' ), true );
check( '"Sẵn hàng" fallback so với instock', false !== strpos( (string) json_encode( Saha\Core\Filter::availability_clause( 'in_stock' ) ), '"instock"' ), true );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
