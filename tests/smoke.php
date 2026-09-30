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
function esc_url_raw( $s, $protocols = null ) {
	$s = trim( (string) $s );
	if ( '' === $s ) { return ''; }
	if ( '/' === $s[0] && ( ! isset( $s[1] ) || '/' !== $s[1] ) ) { return $s; }
	if ( ! preg_match( '/^([a-z][a-z0-9+.-]*):/i', $s, $m ) ) { return ''; }
	$allowed = $protocols ?? array( 'http', 'https', 'mailto', 'tel' );
	if ( ! in_array( strtolower( $m[1] ), $allowed, true ) ) { return ''; }
	return in_array( strtolower( $m[1] ), array( 'http', 'https' ), true ) && ! filter_var( $s, FILTER_VALIDATE_URL ) ? '' : $s;
}
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://tongkhokeodan.com' . $p; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
// Gần với kses thật ở những điểm test quan tâm: bỏ script/style/iframe, thuộc tính on*, javascript:.
function wp_kses_post( $s ) {
	$s = preg_replace( '#<(script|style|iframe)\b[^>]*>.*?</\1>#is', '', (string) $s );
	$s = preg_replace( '#<(script|style|iframe)\b[^>]*>#i', '', $s );
	$s = preg_replace( '#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $s );
	return preg_replace( '#javascript:#i', '', $s );
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $u, $p = null ) { return esc_attr( esc_url_raw( $u, $p ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'medium_large', 'large' ); }
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	if ( 99 !== (int) $id ) { return ''; }
	$html = '<img src="https://tongkhokeodan.com/wp-content/uploads/img-' . (int) $id . '-' . $size . '.jpg" width="800" height="600"';
	foreach ( (array) $attr as $k => $v ) { $html .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }
	return $html . '>';
}
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return 99 === (int) $id ? 'https://tongkhokeodan.com/wp-content/uploads/img-99.jpg' : false; }
function get_post_type( $id ) { return $GLOBALS['__posts'][ $id ]['type'] ?? false; }
function get_post_status( $id ) { return $GLOBALS['__posts'][ $id ]['status'] ?? false; }
function get_the_title( $id ) { return $GLOBALS['__posts'][ $id ]['title'] ?? ''; }
function current_user_can( ...$a ) { return true; }
function wp_attachment_is_image( $id ) { return 99 === (int) $id; }

spl_autoload_register( static function ( $c ) {
	if ( 0 !== strpos( $c, 'Saha\\Core\\' ) ) { return; }
	$rel = substr( $c, 10 );
	// Code mới (SCC): PSR-4 includes/{Namespace/Path}.php.
	$psr4 = SAHA_CORE_PATH . 'includes/' . str_replace( '\\', '/', $rel ) . '.php';
	if ( is_readable( $psr4 ) ) { require_once $psr4; return; }
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

// ---- SCC mốc 1.1: Theme Options ----------------------------------------
use Saha\Core\ThemeOptions\CssVariables;
use Saha\Core\ThemeOptions\InvalidValue;
use Saha\Core\ThemeOptions\Sanitizer;
use Saha\Core\ThemeOptions\Schema;

/** 'INVALID' nếu hàm ném InvalidValue, ngược lại giá trị trả về. */
function saha_try( callable $fn ) {
	try { return $fn(); } catch ( InvalidValue $e ) { return 'INVALID'; }
}

echo "ThemeOptions\\Sanitizer\n";
check( 'màu hex viết hoa → thường', Sanitizer::color( '#127A3F' ), '#127a3f' );
check( 'rgba bỏ khoảng trắng', Sanitizer::color( 'rgba(0, 0, 0, .5)' ), 'rgba(0,0,0,.5)' );
check( 'màu chèn CSS bị từ chối', saha_try( fn() => Sanitizer::color( 'red;}body{display:none' ) ), 'INVALID' );
check( 'var() ngoài --saha-* bị từ chối', saha_try( fn() => Sanitizer::color( 'var(--wp-x)' ) ), 'INVALID' );
$saha_size = array( 'type' => 'size', 'units' => array( 'px' ), 'min' => 16, 'max' => 160 );
check( 'số trần hiểu là px', Sanitizer::size( '48', $saha_size ), '48px' );
check( '48.50px → 48.5px', Sanitizer::size( '48.50px', $saha_size ), '48.5px' );
check( 'ngoài khoảng bị từ chối', saha_try( fn() => Sanitizer::size( '5000px', $saha_size ) ), 'INVALID' );
check( 'đơn vị lạ bị từ chối', saha_try( fn() => Sanitizer::size( '3em', $saha_size ) ), 'INVALID' );
check( 'responsive thiếu desktop bị từ chối', saha_try( fn() => Sanitizer::field( array( 'mobile' => '20px' ), $saha_size + array( 'responsive' => true ) ) ), 'INVALID' );
$saha_css = Sanitizer::css( 'a{color:red}</style><script>alert(1)</script>@import url(//evil.example/x.css);b{width:expression(alert(1))}' );
check( 'CSS: không còn ký tự < (không thoát khỏi <style>)', false === strpos( $saha_css, '<' ), true );
check( 'CSS: bỏ @import', false === stripos( $saha_css, '@import' ), true );
check( 'CSS: bỏ expression(', false === stripos( $saha_css, 'expression(' ), true );
check( 'CSS: giữ luật hợp lệ', false !== strpos( $saha_css, 'a{color:red}' ), true );
check( 'media: ID không phải ảnh bị từ chối', saha_try( fn() => Sanitizer::media( 5 ) ), 'INVALID' );
check( 'media: ảnh hợp lệ', Sanitizer::media( '99' ), 99 );

echo "ThemeOptions\\Schema + CssVariables\n";
check( 'font serif không dùng Georgia (thiếu glyph tiếng Việt)', false === stripos( Schema::fontStacks()['serif']['stack'], 'georgia' ), true );
$saha_vals                      = Schema::defaults();
$saha_vals['colors']['primary'] = '#127a3f';
$saha_vals['layout']['gutter']  = array( 'desktop' => '24px', 'mobile' => '12px' );
$saha_out                       = CssVariables::build( $saha_vals );
check( 'CSS có màu chính', false !== strpos( $saha_out, '--saha-primary:#127a3f' ), true );
check( 'breakpoint mobile 767px chứa gutter mobile', (bool) preg_match( '/@media \(max-width:767px\)\{:root\{[^}]*--saha-gutter:12px/', $saha_out ), true );
check( 'typography sinh biến --saha-type-body-font', false !== strpos( $saha_out, '--saha-type-body-font:' ), true );
$saha_vals['colors']['primary'] = 'red;}body{display:none';
check( 'giá trị bẩn trong DB không thoát khỏi khai báo', false === strpos( CssVariables::build( $saha_vals ), 'display:none' ), true );

// ---- SCC mốc 1.2: Builder runtime --------------------------------------
if ( ! defined( 'SAHA_CORE_VERSION' ) ) { define( 'SAHA_CORE_VERSION', 'test' ); }

use Saha\Core\Builder\CssGenerator;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Renderer;
use Saha\Core\Builder\Sanitizer as BuilderSanitizer;
use Saha\Core\Builder\Schema\Document;

/** Tài liệu mẫu: section > row > 2 column > heading/text/button/image. */
function saha_doc( array $override = array() ): array {
	return array_replace_recursive(
		array(
			'version'  => 1,
			'elements' => array(
				array(
					'id'       => 'sec00001',
					'type'     => 'section',
					'props'    => array( 'background' => array( 'color' => '#F5F5F5' ), 'minHeight' => array( 'desktop' => '480px', 'mobile' => '320px' ) ),
					'advanced' => array( 'padding' => array( 'desktop' => array( 'top' => '64px', 'bottom' => '64px' ) ) ),
					'children' => array(
						array(
							'id'       => 'row00001',
							'type'     => 'row',
							'props'    => array( 'gap' => array( 'desktop' => '32px', 'mobile' => '16px' ) ),
							'children' => array(
								array(
									'id'       => 'col00001',
									'type'     => 'column',
									'props'    => array( 'width' => array( 'desktop' => '60%' ) ),
									'children' => array(
										array( 'id' => 'hea00001', 'type' => 'heading', 'props' => array( 'text' => 'Tổng kho <keo> & dán', 'tag' => 'h1', 'color' => 'var(--saha-heading)', 'typography' => array( 'fontSize' => array( 'desktop' => '40px', 'mobile' => '26px' ), 'fontWeight' => '700' ) ) ),
										array( 'id' => 'txt00001', 'type' => 'text', 'props' => array( 'content' => '<p>Keo <strong>chính hãng</strong></p><script>alert(1)</script><p onclick="x()">b</p>' ) ),
										array( 'id' => 'btn00001', 'type' => 'button', 'props' => array( 'text' => 'Báo giá', 'link' => array( 'url' => '/bao-gia/', 'newTab' => true ) ) ),
									),
								),
								array(
									'id'       => 'col00002',
									'type'     => 'column',
									'children' => array(
										array( 'id' => 'img00001', 'type' => 'image', 'props' => array( 'image' => array( 'id' => 99, 'size' => 'large' ), 'alt' => 'Keo "243"' ) ),
									),
								),
							),
						),
					),
				),
			),
		),
		$override
	);
}

/** Sanitize nhanh. */
function saha_bs( $input ): array { return ( new BuilderSanitizer() )->document( $input ); }

echo "Builder\\Sanitizer\n";
$saha_ok = saha_bs( saha_doc() );
check( 'tài liệu hợp lệ không lỗi', $saha_ok['errors'], array() );
$saha_arr = $saha_ok['document']->toArray();
$saha_h1  = $saha_arr['elements'][0]['children'][0]['children'][0]['children'][0]['props'];
check( 'màu hex chuẩn hoá chữ thường', $saha_arr['elements'][0]['props']['background']['color'], '#f5f5f5' );
check( 'rich text: bỏ <script>', false === strpos( $saha_arr['elements'][0]['children'][0]['children'][0]['children'][1]['props']['content'], '<script' ), true );
check( 'rich text: bỏ onclick', false === strpos( $saha_arr['elements'][0]['children'][0]['children'][0]['children'][1]['props']['content'], 'onclick' ), true );
check( 'rich text: giữ <strong>', false !== strpos( $saha_arr['elements'][0]['children'][0]['children'][0]['children'][1]['props']['content'], '<strong>' ), true );
check( 'heading text: bỏ thẻ HTML', $saha_h1['text'], 'Tổng kho & dán' );

$saha_bad = saha_doc();
$saha_bad['elements'][0]['children'][0]['children'][0]['children'][0]['props']['unknownProp'] = 'x';
$saha_r = saha_bs( $saha_bad );
check( 'prop lạ bị bỏ im lặng', array_key_exists( 'unknownProp', $saha_r['document']->toArray()['elements'][0]['children'][0]['children'][0]['children'][0]['props'] ), false );

$saha_bad = saha_doc();
$saha_bad['elements'][0]['children'][0]['children'][0]['children'][2]['props']['link']['url'] = 'javascript:alert(1)';
$saha_r = saha_bs( $saha_bad );
check( 'link javascript: bị từ chối (lỗi theo node.prop)', isset( $saha_r['errors']['btn00001.link'] ) && null === $saha_r['document'], true );

$saha_bad = saha_doc();
$saha_bad['elements'][0]['props']['background']['color'] = 'red;}body{display:none';
check( 'màu chèn CSS bị từ chối', isset( saha_bs( $saha_bad )['errors']['sec00001.background'] ), true );

$saha_bad = saha_doc();
$saha_bad['elements'][0]['props']['minHeight'] = array( 'desktop' => '99999px' );
check( 'kích thước ngoài khoảng bị từ chối', isset( saha_bs( $saha_bad )['errors']['sec00001.minHeight'] ), true );

check( 'heading ở cấp gốc bị từ chối (allowedParents)', isset( saha_bs( array( 'elements' => array( array( 'id' => 'hhhhhhhh', 'type' => 'heading' ) ) ) )['errors']['hhhhhhhh'] ), true );
check( 'heading trong row bị từ chối (allowedChildren)', count( saha_bs( array( 'elements' => array( array( 'id' => 'ssssssss', 'type' => 'section', 'children' => array( array( 'id' => 'rrrrrrrr', 'type' => 'row', 'children' => array( array( 'id' => 'hhhhhhhh', 'type' => 'heading' ) ) ) ) ) ) ) )['errors'] ) > 0, true );
check( 'element lá có con bị từ chối', isset( saha_bs( array( 'elements' => array( array( 'id' => 'ssssssss', 'type' => 'section', 'children' => array( array( 'id' => 'hhhhhhhh', 'type' => 'heading', 'children' => array( array( 'type' => 'text' ) ) ) ) ) ) ) )['errors']['hhhhhhhh'] ), true );

$saha_dup = saha_doc();
$saha_dup['elements'][0]['children'][0]['children'][1]['id'] = 'col00001';
$saha_dup['elements'][0]['children'][0]['id']                = 'BAD ID!';
$saha_r   = saha_bs( $saha_dup )['document']->toArray();
$saha_ids = array( $saha_r['elements'][0]['children'][0]['id'], $saha_r['elements'][0]['children'][0]['children'][0]['id'], $saha_r['elements'][0]['children'][0]['children'][1]['id'] );
check( 'ID sai định dạng được cấp mới', (bool) preg_match( '/^[a-z0-9]{8}$/', $saha_ids[0] ) && 'BAD ID!' !== $saha_ids[0], true );
check( 'ID trùng được cấp mới', $saha_ids[1] !== $saha_ids[2], true );

$saha_unknown = saha_bs( array( 'elements' => array( array( 'id' => 'xxxxxxxx', 'type' => 'addon-slider', 'props' => array( 'speed' => 3 ), 'custom' => array( 'a' => 1 ) ) ) ) );
check( 'type không đăng ký: giữ nguyên dữ liệu', $saha_unknown['document']->toArray()['elements'][0], array( 'id' => 'xxxxxxxx', 'type' => 'addon-slider', 'props' => array( 'speed' => 3 ), 'custom' => array( 'a' => 1 ) ) );

$saha_deep = array( 'id' => 'deep0000', 'type' => 'column' );
for ( $i = 0; $i < 14; $i++ ) {
	$saha_deep = array( 'type' => 'row', 'children' => array( array( 'type' => 'column', 'children' => array( $saha_deep ) ) ) );
}
check( 'lồng quá sâu bị từ chối', isset( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( $saha_deep ) ) ) ) )['errors']['document'] ), true );

$saha_many = array();
for ( $i = 0; $i < 2001; $i++ ) { $saha_many[] = array( 'type' => 'section' ); }
check( 'quá 2000 element bị từ chối', isset( saha_bs( array( 'elements' => $saha_many ) )['errors']['document'] ), true );
check( 'JSON quá 1 MB bị từ chối', isset( saha_bs( str_repeat( ' ', 1048577 ) )['errors']['document'] ), true );
check( 'tài liệu từ phiên bản mới hơn bị từ chối', isset( saha_bs( array( 'version' => 99, 'elements' => array() ) )['errors']['document'] ), true );
check( 'chuỗi JSON hợp lệ được nhận', saha_bs( json_encode( saha_doc() ) )['errors'], array() );

echo "Builder\\Renderer\n";
$saha_docobj = saha_ok_doc();
function saha_ok_doc(): Document { return saha_bs( saha_doc() )['document']; }
$saha_html = ( new Renderer() )->document( $saha_docobj, new RenderContext( 0, false, false ) );
check( 'heading render đúng thẻ h1', (bool) preg_match( '#<h1 class="saha-e saha-e-hea00001 saha-heading">Tổng kho &amp; dán</h1>#u', $saha_html ), true );
check( 'text không còn script khi render', false === stripos( $saha_html, '<script' ), true );
check( 'button link newTab có rel=noopener', false !== strpos( $saha_html, 'href="/bao-gia/" target="_blank" rel="noopener"' ), true );
check( 'ảnh render qua wp_get_attachment_image, alt được escape', false !== strpos( $saha_html, 'alt="Keo &quot;243&quot;"' ), true );
check( 'frontend không có data-saha-id', false === strpos( $saha_html, 'data-saha-id' ), true );
$saha_ed = ( new Renderer() )->document( $saha_docobj, new RenderContext( 0, true, false ) );
check( 'editor có data-saha-id', false !== strpos( $saha_ed, 'data-saha-id="hea00001"' ), true );
$saha_miss = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'image', 'props' => array() ) ) ) ) ) )['document'];
check( 'ảnh chưa chọn: frontend không in gì', false === strpos( ( new Renderer() )->document( $saha_miss, new RenderContext( 0, false, false ) ), 'saha-image' ), true );
check( 'type không đăng ký: không render', ( new Renderer() )->document( $saha_unknown['document'], new RenderContext( 0, false, false ) ), '' );
$saha_evil = new Document( array( Saha\Core\Builder\Schema\Node::fromArray( array( 'id' => 'eeeeeeee', 'type' => 'section', 'props' => array( 'tag' => 'script' ), 'children' => array( array( 'id' => 'ffffffff', 'type' => 'heading', 'props' => array( 'text' => '<img src=x onerror=alert(1)>', 'tag' => 'script' ) ) ) ) ) ) );
$saha_evil_html = ( new Renderer() )->document( $saha_evil, new RenderContext( 0, false, false ) );
check( 'dữ liệu bẩn trong DB: tag lạ thành mặc định, text được escape', false === stripos( $saha_evil_html, '<script' ) && false === strpos( $saha_evil_html, '<img' ), true );

echo "Builder\\CssGenerator\n";
$saha_css = ( new CssGenerator() )->document( $saha_docobj );
check( 'padding nâng cao → .saha-e-{id}', false !== strpos( $saha_css, '.saha-e-sec00001{padding-top:64px;padding-bottom:64px' ), true );
check( 'min-height responsive vào @media mobile', (bool) preg_match( '/@media \(max-width:767px\)\{[^@]*\.saha-e-sec00001\{min-height:320px/', $saha_css ), true );
check( 'typography: font-size mobile', (bool) preg_match( '/@media \(max-width:767px\)\{[^@]*\.saha-e-hea00001\{font-size:26px/', $saha_css ), true );
check( 'màu dùng biến Theme Options', false !== strpos( $saha_css, 'color:var(--saha-heading)' ), true );
check( 'cột 60% → --saha-col:0.6', false !== strpos( $saha_css, '.saha-e-col00001{--saha-col:0.6}' ), true );
check( 'cột không đặt độ rộng chia phần còn lại (0.4)', false !== strpos( $saha_css, '.saha-e-row00001 > .saha-e-col00002{--saha-col:0.4}' ), true );
check( 'xếp chồng mobile → --saha-col:1', (bool) preg_match( '/@media \(max-width:767px\)\{[^@]*\.saha-e-row00001 > \.saha-e-col00001\{--saha-col:1\}/', $saha_css ), true );
$saha_dirty = new Document( array( Saha\Core\Builder\Schema\Node::fromArray( array( 'id' => 'dddddddd', 'type' => 'heading', 'props' => array( 'color' => 'red;}body{display:none', 'align' => '</style><script>' ) ) ) ) );
$saha_dirty_css = ( new CssGenerator() )->document( $saha_dirty );
check( 'giá trị CSS bẩn trong DB bị bỏ', false === strpos( $saha_dirty_css, 'display:none' ) && false === strpos( $saha_dirty_css, '<' ), true );
check( 'url() lạ trong giá trị bị bỏ', Saha\Core\Builder\CssRules::cleanValue( 'url("javascript:alert(1)")' ), '' );
check( 'url() ảnh hợp lệ được giữ', Saha\Core\Builder\CssRules::cleanValue( 'url("https://tongkhokeodan.com/a.jpg")' ), 'url("https://tongkhokeodan.com/a.jpg")' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
