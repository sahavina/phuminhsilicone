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
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_url( $u, $p = null ) { return esc_attr( esc_url_raw( $u, $p ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'medium_large', 'large' ); }
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	if ( 99 !== (int) $id ) { return ''; }
	$html = '<img src="https://tongkhokeodan.com/wp-content/uploads/img-' . (int) $id . '-' . $size . '.jpg" width="800" height="600"';
	foreach ( (array) $attr as $k => $v ) { $html .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }
	return $html . '>';
}
function get_registered_nav_menus() { return array( 'primary' => 'Menu chính', 'footer' => 'Menu chân trang' ); }
function has_nav_menu( $l ) { return 'primary' === $l; }
function wp_nav_menu( $a ) { return '<ul class="' . $a['menu_class'] . '"><li class="current-menu-item"><a href="/">Trang chủ</a></li></ul>'; }
function get_bloginfo( $k = '' ) { return 'description' === $k ? '' : 'Tổng Kho Keo Dán SAHA'; }
function get_theme_mod( $k, $d = false ) { return $d; }
function wp_date( $f ) { return gmdate( $f ); }
function antispambot( $e ) { return $e; }
function get_search_form( $a = array() ) { return '<form role="search"><input type="search" name="s"></form>'; }
function wp_login_url() { return 'https://tongkhokeodan.com/wp-login.php'; }
function _n( $s, $p, $n, $d = null ) { return 1 === $n ? $s : $p; }
function saha_get_setting( $k, $d = '' ) { return array( 'hotline_north' => '0966.75.3382', 'email' => 'sales@tongkhokeodan.com' )[ $k ] ?? $d; }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return 99 === (int) $id ? 'https://tongkhokeodan.com/wp-content/uploads/img-99.jpg' : false; }
function get_post_type( $id ) { return $GLOBALS['__posts'][ $id ]['type'] ?? false; }
function get_post_status( $id ) { return $GLOBALS['__posts'][ $id ]['status'] ?? false; }
function get_the_title( $id ) { return $GLOBALS['__posts'][ $id ]['title'] ?? ''; }
function current_user_can( $cap, ...$a ) { return ! in_array( $cap, $GLOBALS['__caps_denied'] ?? array(), true ); }
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
check( 'chữ trên nền nhấn vàng: dùng màu tối', CssVariables::readableOn( '#f5a623', '#1f2937' ), '#1f2937' );
check( 'chữ trên nền nhấn đỏ: dùng trắng', CssVariables::readableOn( '#d9323e', '#1e2066' ), '#ffffff' );
check( 'màu không phải hex: không sinh --saha-on-accent', CssVariables::readableOn( 'var(--x)', '#000' ), '' );
check( 'CSS có --saha-on-accent', false !== strpos( $saha_out, '--saha-on-accent:' ), true );
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

echo "Builder\\CssRules — selector (SCC 1.3)\n";
$saha_rules = ( new Saha\Core\Builder\CssRules() )->forNode( 'aaaaaaaa' );
$saha_rules->put( 'desktop', ' :where(h1, h2)', 'color', 'red' );
$saha_rules->put( 'desktop', ' a, body', 'display', 'none' );
$saha_rules->put( 'desktop', ' :where(a', 'color', 'blue' );
$saha_sel_css = $saha_rules->toCss();
check( 'dấu phẩy trong :where() được giữ', false !== strpos( $saha_sel_css, '.saha-e-aaaaaaaa :where(h1, h2){color:red}' ), true );
check( 'dấu phẩy cấp ngoài (thoát phạm vi) bị bỏ cả luật', false === strpos( $saha_sel_css, 'display:none' ), true );
check( 'ngoặc không cân bị bỏ', false === strpos( $saha_sel_css, 'blue' ), true );
$saha_sec = saha_bs( array( 'elements' => array( array( 'id' => 'secccccc', 'type' => 'section', 'props' => array( 'textColor' => '#ffffff' ), 'children' => array( array( 'id' => 'hhhhhhhh', 'type' => 'heading', 'props' => array( 'color' => '#ff0000' ) ) ) ) ) ) )['document'];
$saha_sec_css = ( new CssGenerator() )->document( $saha_sec );
check( 'màu chữ section áp cho tiêu đề bên trong', false !== strpos( $saha_sec_css, '.saha-e-secccccc :where(h1, h2, h3, h4, h5, h6){color:#ffffff}' ), true );
check( 'màu riêng của tiêu đề đứng sau (thắng)', strpos( $saha_sec_css, '.saha-e-hhhhhhhh{' ) > strpos( $saha_sec_css, ':where(h1' ), true );

echo "Builder — element mốc 1.4\n";
$saha_reg   = Saha\Core\Builder\ElementRegistry::instance();
$saha_ctrls = Saha\Core\Builder\Controls\ControlRegistry::instance();
$saha_types = array_keys( $saha_reg->all() );
check( 'đủ 70 element (20 nội dung + 15 header/footer + 17 động + 9 giao diện D1 + danh sách báo giá + 8 element Phase 2)', count( $saha_types ), 70 );
$saha_bad_ctrl = array();
foreach ( $saha_reg->all() as $saha_t => $saha_el ) {
	foreach ( (array) $saha_el->def()['controls'] as $saha_k => $saha_c ) {
		if ( null === $saha_ctrls->get( (string) $saha_c['type'] ) ) {
			$saha_bad_ctrl[] = $saha_t . '.' . $saha_k;
		}
	}
}
check( 'mọi control của element đều có loại tồn tại', $saha_bad_ctrl, array() );

// Element không cần WordPress đầy đủ: render với giá trị mặc định, trong cha hợp lệ.
$saha_leafs = array( 'spacer', 'divider', 'icon', 'iconbox', 'html', 'shortcode', 'banner', 'cta' );
$saha_kids  = array_map( static fn( $t ) => array( 'type' => $t ), $saha_leafs );
$saha_kids[] = array( 'type' => 'container', 'children' => array( array( 'type' => 'heading' ) ) );
$saha_r = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => $saha_kids ) ) ) );
check( 'element mới hợp lệ trong section', $saha_r['errors'], array() );
$saha_html = ( new Renderer() )->document( $saha_r['document'], new RenderContext( 0, false, false ) );
check( 'icon: SVG nội tuyến aria-hidden', (bool) preg_match( '/<svg class="saha-icon[^"]*"[^>]*aria-hidden="true"/', $saha_html ), true );
check( 'divider → <hr>', false !== strpos( $saha_html, '<hr class="saha-e' ), true );
check( 'CTA có nút mặc định', false !== strpos( $saha_html, 'Yêu cầu báo giá' ), true );
check( 'container chứa được heading', false !== strpos( $saha_html, 'saha-container-el' ) && false !== strpos( $saha_html, 'saha-heading' ), true );
check( 'html/shortcode trống: frontend không in gì', false === strpos( $saha_html, 'saha-html' ) && false === strpos( $saha_html, 'saha-shortcode' ), true );

check( 'cột không được đặt trong container', count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'container', 'children' => array( array( 'type' => 'column' ) ) ) ) ) ) ) )['errors'] ) > 0, true );
check( 'banner, block đặt được ở cấp gốc', saha_bs( array( 'elements' => array( array( 'type' => 'banner' ), array( 'type' => 'block' ) ) ) )['errors'], array() );
check( 'icon không tồn tại bị từ chối', count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'id' => 'iiiiiiii', 'type' => 'icon', 'props' => array( 'icon' => 'khong-co' ) ) ) ) ) ) )['errors'] ), 1 );

$saha_html_doc = array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'id' => 'hhhhhhh1', 'type' => 'html', 'props' => array( 'html' => '<p>Bản đồ</p><script>track()</script>' ) ) ) ) ) );
$saha_kept = saha_bs( $saha_html_doc )['document']->toArray()['elements'][0]['children'][0]['props']['html'];
check( 'HTML: người có unfiltered_html giữ được script', false !== strpos( $saha_kept, '<script>' ), true );
$GLOBALS['__caps_denied'] = array( 'unfiltered_html' );
$saha_strip = saha_bs( $saha_html_doc )['document']->toArray()['elements'][0]['children'][0]['props']['html'];
$GLOBALS['__caps_denied'] = array();
check( 'HTML: người không có unfiltered_html → script bị lọc', false === strpos( $saha_strip, '<script' ) && false !== strpos( $saha_strip, '<p>Bản đồ</p>' ), true );

$saha_banner = saha_bs( array( 'elements' => array( array( 'type' => 'banner', 'props' => array( 'image' => array( 'id' => 99 ), 'priority' => true, 'title' => 'Hero', 'titleTag' => 'h1' ) ) ) ) )['document'];
$saha_banner_html = ( new Renderer() )->document( $saha_banner, new RenderContext( 0, false, false ) );
check( 'banner ưu tiên: ảnh fetchpriority=high, loading=eager', false !== strpos( $saha_banner_html, 'fetchpriority="high"' ) && false !== strpos( $saha_banner_html, 'loading="eager"' ), true );
check( 'banner: ảnh nền alt rỗng, tiêu đề H1', false !== strpos( $saha_banner_html, 'alt=""' ) && false !== strpos( $saha_banner_html, '<h1 class="saha-banner__title">Hero</h1>' ), true );
check( 'element động (shortcode, block, sản phẩm, bài viết, menu, tìm kiếm, giỏ + mọi element Template Builder) không vào render cache', array_values( array_filter( $saha_types, static fn( $t ) => ! empty( $saha_reg->get( $t )->def()['dynamic'] ) ) ), array( 'shortcode', 'block', 'products', 'posts', 'nav-menu', 'search', 'cart', 'quote-list-link', 'countdown', 'post-title', 'post-content', 'post-excerpt', 'featured-image', 'post-meta', 'breadcrumb', 'archive-title', 'archive-posts', 'product-gallery', 'product-price', 'product-add-to-cart', 'product-meta', 'product-tabs', 'product-related', 'product-summary', 'product-after-summary', 'product-archive' ) );

echo "Header & footer (mốc 1.5)\n";
require_once SAHA_CORE_PATH . 'includes/functions.php'; // saha_hotline(), saha_tel_href()…
$saha_hdr_doc = Saha\Core\Templates\Defaults::header();
$saha_hdr     = ( new BuilderSanitizer() )->document( $saha_hdr_doc, 'header-root' );
check( 'header mặc định hợp lệ với gốc header', $saha_hdr['errors'], array() );
check( 'header mặc định: không hợp lệ ở gốc trang thường', count( saha_bs( $saha_hdr_doc )['errors'] ) > 0, true );
check( 'Section không đặt được ở gốc header', count( ( new BuilderSanitizer() )->document( array( 'elements' => array( array( 'type' => 'section' ) ) ), 'header-root' )['errors'] ) > 0, true );
$saha_ftr = saha_bs( Saha\Core\Templates\Defaults::footer() );
check( 'footer mặc định hợp lệ (gốc trang thường)', $saha_ftr['errors'], array() );

$saha_hdr_html = ( new Renderer() )->document( $saha_hdr['document'], new RenderContext( 77, false, false ) );
check( 'header: thẻ <header> dính "always"', (bool) preg_match( '/<header class="saha-e [^"]*saha-hb--sticky-always[^"]*"[^>]*data-saha-sticky="always"/', $saha_hdr_html ), true );
check( 'nút menu trỏ đúng bảng off-canvas (aria-controls)', false !== strpos( $saha_hdr_html, 'aria-controls="saha-offcanvas-77"' ) && false !== strpos( $saha_hdr_html, 'id="saha-offcanvas-77"' ), true );
check( 'off-canvas: hidden, role=dialog, aria-modal', (bool) preg_match( '/id="saha-offcanvas-77" hidden><div class="saha-hb-offcanvas__backdrop" data-saha-close><\/div><div class="saha-hb-offcanvas__panel" role="dialog" aria-modal="true"/', $saha_hdr_html ), true );
check( 'nút menu có aria-expanded=false', false !== strpos( $saha_hdr_html, 'aria-expanded="false"' ), true );
check( 'hotline từ SAHA → Cấu hình, link tel:', false !== strpos( $saha_hdr_html, 'href="tel:0966753382"' ) && false !== strpos( $saha_hdr_html, '0966.75.3382' ), true );
check( 'menu trong <nav aria-label>', (bool) preg_match( '/<nav [^>]*aria-label="Menu chính"/', $saha_hdr_html ), true );
check( 'logo chưa có ảnh → tên website, link trang chủ rel=home', false !== strpos( $saha_hdr_html, 'rel="home"' ) && false !== strpos( $saha_hdr_html, 'Tổng Kho Keo Dán SAHA' ), true );
check( 'hàng desktop ẩn ở tablet/mobile, hàng mobile ẩn ở desktop', false !== strpos( $saha_hdr_html, 'saha-hide-tablet saha-hide-mobile' ) && false !== strpos( $saha_hdr_html, 'saha-hide-desktop' ), true );
$saha_hdr_editor = ( new Renderer() )->document( $saha_hdr['document'], new RenderContext( 77, true, false ) );
check( 'editor: off-canvas hiện tĩnh để kéo thả, không hidden', false !== strpos( $saha_hdr_editor, 'saha-hb-offcanvas is-editor' ) && false === strpos( $saha_hdr_editor, 'hidden>' ), true );
$saha_ftr_html = ( new Renderer() )->document( $saha_ftr['document'], new RenderContext( 78, false, false ) );
check( 'footer: bản quyền có năm hiện tại', false !== strpos( $saha_ftr_html, '© ' . gmdate( 'Y' ) . ' Tổng Kho Keo Dán SAHA' ), true );
check( 'footer: email mailto', false !== strpos( $saha_ftr_html, 'href="mailto:sales@tongkhokeodan.com"' ), true );

echo "WooCommerce (mốc 1.6)\n";
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wc_get_checkout_url() { return 'https://tongkhokeodan.com/checkout/'; }
$saha_bn  = new Saha\Core\WooCommerce\BuyNow();
$_REQUEST = array( 'saha-buy-now' => '34' );
$saha_bn->prepare();
check( 'Mua ngay (sp đơn giản): tự đặt add-to-cart = ID', $_REQUEST['add-to-cart'] ?? null, 34 );
check( 'Mua ngay: chuyển sang trang thanh toán', $saha_bn->redirect( 'https://tongkhokeodan.com/cart/' ), 'https://tongkhokeodan.com/checkout/' );
$_REQUEST = array( 'saha-buy-now' => '34', 'add-to-cart' => '35' );
$saha_bn->prepare();
check( 'Mua ngay (biến thể): giữ add-to-cart của form', $_REQUEST['add-to-cart'], '35' );
$_REQUEST = array( 'saha-buy-now' => 'abc' );
$saha_bn->prepare();
check( 'Mua ngay: ID không hợp lệ → không thêm gì', isset( $_REQUEST['add-to-cart'] ), false );
$_REQUEST = array();
check( 'Thêm vào giỏ thường: không đổi nơi chuyển hướng', $saha_bn->redirect( 'https://tongkhokeodan.com/cart/' ), 'https://tongkhokeodan.com/cart/' );
check( 'Catalogue: nhãn thay giá', Saha\Core\WooCommerce\CatalogMode::priceHtml(), '<span class="saha-price-hidden">Liên hệ báo giá</span>' );

echo "Trang chủ mẫu (mốc 1.7)\n";
function get_page_by_path( $p ) { return null; }
function taxonomy_exists( $t ) { return true; }
function term_exists( $slug, $tax = '' ) { return ! in_array( $slug, $GLOBALS['__missing_terms'] ?? array(), true ); }
$saha_home = saha_bs( Saha\Core\Builder\Starter::homepage() );
check( 'trang chủ mẫu hợp lệ (qua Sanitizer)', $saha_home['errors'], array() );
$saha_home_json = (string) json_encode( $saha_home['document']->toArray(), JSON_UNESCAPED_UNICODE );
check( 'trang chủ mẫu: đúng 1 H1', substr_count( $saha_home_json, '"tag":"h1"' ), 1 );
check( 'trang chủ mẫu: không dùng shortcode', false === strpos( $saha_home_json, '"shortcode"' ) && false === strpos( $saha_home_json, '[saha_' ), true );
check( 'trang chủ mẫu: 9 section chứa đủ 14 khối của PHASE-5', count( $saha_home['document']->toArray()['elements'] ), 9 );
$GLOBALS['__missing_terms'] = array( 'loctite', 'pu-foam' );
$saha_home_min = saha_bs( Saha\Core\Builder\Starter::homepage() );
$saha_home_min_json = (string) json_encode( $saha_home_min['document']->toArray(), JSON_UNESCAPED_UNICODE );
$GLOBALS['__missing_terms'] = array();
check( 'site thiếu danh mục/thương hiệu: vẫn tạo được, bỏ đúng khối đó', array( $saha_home_min['errors'], false !== strpos( $saha_home_min_json, 'Keo Loctite' ), false !== strpos( $saha_home_min_json, 'PU Foam"' ), false !== strpos( $saha_home_min_json, 'Keo Silicone' ) ), array( array(), false, false, true ) );
$saha_cta_doc = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'cta', 'props' => array( 'action1' => 'quote', 'button1' => 'Báo giá' ) ) ) ) ) ) )['document'];
$saha_cta_html = ( new Renderer() )->document( $saha_cta_doc, new RenderContext( 0, false, false ) );
check( 'CTA "Mở form báo giá": nút data-saha-open-quote, không có href', (bool) preg_match( '/<button type="button" class="saha-btn[^"]*" data-saha-open-quote="1">Báo giá<\/button>/', $saha_cta_html ), true );
check( 'CTA báo giá không vào render cache, CTA link thì có', array( $saha_reg->get( 'cta' )->isDynamic( $saha_cta_doc->elements[0]->children[0] ), $saha_reg->get( 'cta' )->isDynamic( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'cta' ) ) ) ) ) )['document']->elements[0]->children[0] ) ), array( true, false ) );

echo "Mega menu (mốc 2.1)\n";
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['__meta'][ $id ][ $key ] ?? ''; }
use Saha\Core\MegaMenu\Settings as MegaSettings;
use Saha\Core\MegaMenu\Frontend as MegaFrontend;
$GLOBALS['__posts'][102] = array( 'type' => 'saha_block', 'status' => 'publish', 'title' => 'Mega' );
$GLOBALS['__posts'][7]   = array( 'type' => 'page', 'status' => 'publish', 'title' => 'Trang' );
check( 'mega: thiết lập lạ → mặc định; cột/độ rộng bị kẹp', MegaSettings::sanitize( array( 'width' => 'x', 'columns' => 99, 'customWidth' => 5 ) ), array( 'width' => 'container', 'customWidth' => 300, 'columns' => 6, 'blockId' => 0 ) );
check( 'mega: blockId phải là saha_block', array( MegaSettings::sanitize( array( 'blockId' => '102' ) )['blockId'], MegaSettings::sanitize( array( 'blockId' => 7 ) )['blockId'] ), array( 102, 0 ) );
$GLOBALS['__meta'] = array(
	64 => array( '_saha_menu_type' => 'mega', '_saha_mega_settings' => '{"width":"custom","customWidth":900,"columns":3}' ),
	65 => array(),
);
$saha_mf   = new MegaFrontend();
$saha_item = (object) array( 'ID' => 64 );
$saha_args = (object) array( 'saha_mega' => true );
check( 'mega: class cột + độ rộng trên mục cấp 1', array_slice( $saha_mf->classes( array( 'menu-item' ), $saha_item, $saha_args, 0 ), 1 ), array( 'saha-mega-item', 'saha-mega-item--custom', 'saha-mega-item--cols-3' ) );
check( 'mega: độ rộng tuỳ chỉnh qua biến CSS', $saha_mf->itemAttributes( array(), $saha_item, $saha_args, 0 ), array( 'style' => '--saha-mega-width:900px' ) );
check( 'mega: link cấp 1 có aria-expanded', $saha_mf->linkAttributes( array( 'href' => '/' ), $saha_item, $saha_args, 0 )['aria-expanded'] ?? null, 'false' );
check( 'mega: menu không bật cờ (dọc/mobile) → giữ nguyên', $saha_mf->classes( array( 'menu-item' ), $saha_item, (object) array(), 0 ), array( 'menu-item' ) );
check( 'mega: chỉ mục cấp 1; mục thường không đổi', array( $saha_mf->classes( array( 'a' ), $saha_item, $saha_args, 1 ), $saha_mf->classes( array( 'a' ), (object) array( 'ID' => 65 ), $saha_args, 0 ) ), array( array( 'a' ), array( 'a' ) ) );
$GLOBALS['__posts'][103] = array( 'type' => 'saha_block', 'status' => 'draft', 'title' => 'Nháp' );
$GLOBALS['__meta'][66]   = array( '_saha_menu_type' => 'mega', '_saha_mega_settings' => '{"blockId":102}' );
$GLOBALS['__meta'][67]   = array( '_saha_menu_type' => 'mega', '_saha_mega_settings' => '{"blockId":103}' );
check( 'mega: block đã xuất bản → kiểu block; block nháp → quay về chia cột', array( in_array( 'saha-mega-item--block', $saha_mf->classes( array(), (object) array( 'ID' => 66 ), $saha_args, 0 ), true ), in_array( 'saha-mega-item--cols-4', $saha_mf->classes( array(), (object) array( 'ID' => 67 ), $saha_args, 0 ), true ) ), array( true, true ) );
$GLOBALS['__options']['saha_mega_menu'] = array( 'active' => true, 'blocks' => array( 102 ) );
check( 'mega: menu có cờ lấy ít nhất 3 cấp; menu khác giữ nguyên', array( $saha_mf->depth( array( 'saha_mega' => true, 'depth' => 2 ) )['depth'], $saha_mf->depth( array( 'depth' => 2 ) )['depth'], $saha_mf->depth( array( 'saha_mega' => true, 'depth' => 0 ) )['depth'] ), array( 3, 2, 0 ) );

echo "Template Builder — điều kiện (mốc 2.2)\n";
use Saha\Core\Templates\Conditions as TplCond;
check( 'điều kiện: bỏ rule lạ/không hợp loại, giá trị sai kiểu, "all" trong Trừ', TplCond::sanitize( array( 'include' => array( array( 'rule' => 'product_cat', 'value' => array( '21', 'x', 0, 21 ) ), array( 'rule' => 'page', 'value' => array( 5 ) ), array( 'rule' => 'evil' ) ), 'exclude' => array( array( 'rule' => 'all' ), array( 'rule' => 'product', 'value' => array() ) ) ), 'single_product' ), array( 'include' => array( array( 'rule' => 'product_cat', 'value' => array( 21 ) ) ), 'exclude' => array() ) );
check( 'điều kiện: archive_type chỉ nhận giá trị đã biết', TplCond::sanitize( array( 'include' => array( array( 'rule' => 'archive_type', 'value' => array( 'shop', 'hack' ) ) ) ), 'product_archive' )['include'], array( array( 'rule' => 'archive_type', 'value' => array( 'shop' ) ) ) );
$saha_map = TplCond::compile(
	array(
		array( 'id' => 10, 'type' => 'single_product', 'priority' => 0, 'conditions' => array( 'include' => array( array( 'rule' => 'all' ) ) ) ),
		array( 'id' => 11, 'type' => 'single_product', 'priority' => 0, 'conditions' => array( 'include' => array( array( 'rule' => 'product_cat', 'value' => array( 21 ) ) ), 'exclude' => array( array( 'rule' => 'product', 'value' => array( 99 ) ) ) ) ),
		array( 'id' => 12, 'type' => 'single_product', 'priority' => 5, 'conditions' => array( 'include' => array( array( 'rule' => 'product_brand', 'value' => array( 7 ) ) ) ) ),
		array( 'id' => 13, 'type' => 'single_product', 'priority' => 0, 'conditions' => array( 'include' => array( array( 'rule' => 'product', 'value' => array( 50 ) ) ) ) ),
		array( 'id' => 14, 'type' => 'single_product', 'priority' => 0, 'conditions' => array( 'include' => array( array( 'rule' => 'product_cat', 'value' => array( 30 ) ) ) ) ),
	)
);
$saha_ctx = static fn( array $extra ): array => array_merge( array( array( 'all', '*', 0 ) ), $extra );
check( 'chọn template: chỉ khớp "tất cả" → template chung', TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product', '1', 30 ) ) ) ), 10 );
check( 'chọn template: danh mục (20) thắng "tất cả" (0)', TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product', '2', 30 ), array( 'product_cat', '21', 20 ) ) ) ), 11 );
check( 'chọn template: sản phẩm cụ thể (30) thắng danh mục', TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product', '50', 30 ), array( 'product_cat', '21', 20 ) ) ) ), 13 );
check( 'chọn template: cùng mức → ưu tiên cao hơn (thương hiệu p5 > danh mục p0)', TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product', '3', 30 ), array( 'product_cat', '21', 20 ), array( 'product_brand', '7', 20 ) ) ) ), 12 );
check( 'chọn template: bị "Trừ" → xuống mức sau', TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product', '99', 30 ), array( 'product_cat', '21', 20 ) ) ) ), 10 );
check( 'chọn template: danh mục con khớp qua cha (15) nhưng danh mục trực tiếp (20) thắng', array( TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product_cat', '22', 20 ), array( 'product_cat', '21', 15 ) ) ) ), TplCond::resolve( $saha_map, 'single_product', $saha_ctx( array( array( 'product_cat', '30', 20 ), array( 'product_cat', '21', 15 ) ) ) ) ), array( 11, 14 ) );
check( 'chọn template: loại khác / không có → null', array( TplCond::resolve( $saha_map, 'single_post', $saha_ctx( array() ) ), TplCond::resolve( array(), 'header', $saha_ctx( array() ) ) ), array( null, null ) );

echo "Element giao diện (D1)\n";
$saha_r1 = static function ( array $element, bool $editor = false ): string {
	$doc = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( $element ) ) ) ) );
	return null === $doc['document'] ? 'INVALID ' . json_encode( $doc['errors'] ) : ( new Renderer() )->document( $doc['document'], new RenderContext( 0, $editor, false ) );
};
$saha_mq = $saha_r1( array( 'type' => 'marquee', 'props' => array( 'items' => "Miễn phí vận chuyển\nTư vấn miễn phí" ) ) );
check( 'chữ chạy: tách dòng đúng với chữ có dấu (ễ chứa byte 0x85)', array( substr_count( $saha_mq, '<li class="saha-marquee__item">' ), false !== strpos( $saha_mq, '>Miễn phí vận chuyển<' ) ), array( 4, true ) );
check( 'chữ chạy: bản lặp aria-hidden (đọc một lần)', 1, substr_count( $saha_mq, '<ul class="saha-marquee__list" aria-hidden="true">' ) );
$saha_il = $saha_r1( array( 'type' => 'icon-list', 'props' => array( 'items' => "Một\n\nHai" ) ) );
check( 'danh sách icon: <ul> thật, bỏ dòng trống, icon aria-hidden', array( substr_count( $saha_il, '<li class="saha-icon-list__item">' ), false !== strpos( $saha_il, '<ul class="saha-e ' ), false !== strpos( $saha_il, 'saha-icon-list__mark" aria-hidden="true"' ) ), array( 2, true, true ) );
$saha_st = $saha_r1( array( 'type' => 'section-title', 'props' => array( 'title' => 'Sản phẩm', 'link' => array( 'url' => 'https://tongkhokeodan.com/shop/' ) ) ) );
check( 'tiêu đề khối: H2 + link Xem tất cả, mặc định không gạch dưới', array( false !== strpos( $saha_st, '<h2 class="saha-stitle__title">Sản phẩm</h2>' ), false !== strpos( $saha_st, 'saha-stitle--bar' ), false !== strpos( $saha_st, 'href="https://tongkhokeodan.com/shop/"' ) ), array( true, false, true ) );
check( 'tiêu đề khối: chọn "Gạch ngắn" vẫn có gạch', false !== strpos( $saha_r1( array( 'type' => 'section-title', 'props' => array( 'title' => 'A', 'decoration' => 'bar' ) ) ), 'saha-stitle--bar' ), true );
$saha_sl = $saha_r1( array( 'type' => 'slider', 'children' => array( array( 'type' => 'slide' ), array( 'type' => 'slide' ) ) ) );
check( 'slider: chỉ nhận Slide, tự chạy ngoài editor, nút có aria-label', array( substr_count( $saha_sl, 'class="saha-e saha-e-' ) >= 3, false !== strpos( $saha_sl, 'data-autoplay="5000"' ), false !== strpos( $saha_sl, 'aria-label="Slide trước"' ) ), array( true, true, true ) );
check( 'slider: editor không tự chạy; Slide đặt ngoài Slider bị từ chối', array( false === strpos( $saha_r1( array( 'type' => 'slider' ), true ), 'data-autoplay' ), 0 === strpos( $saha_r1( array( 'type' => 'slide' ) ), 'INVALID' ) ), array( true, true ) );
$saha_tm = $saha_r1( array( 'type' => 'testimonials', 'children' => array( array( 'type' => 'testimonial', 'props' => array( 'rating' => 's5', 'name' => 'Anh A' ) ) ) ) );
check( 'đánh giá: figure/blockquote, 5 sao có nhãn, không có schema Review', array( false !== strpos( $saha_tm, '<blockquote class="saha-testimonial__quote">' ), false !== strpos( $saha_tm, 'aria-label="5 trên 5 sao"' ), false === strpos( $saha_tm, 'ld+json' ) ), array( true, true, true ) );
$saha_ac = $saha_r1(
	array(
		'type'     => 'accordion',
		'children' => array(
			array( 'type' => 'accordion-item', 'props' => array( 'title' => 'Có giao hàng?', 'content' => '<p>Có, <script>x</script>toàn quốc.</p>' ) ),
			array( 'type' => 'accordion-item', 'props' => array( 'title' => 'Có VAT?', 'content' => '<p>Có.</p>' ) ),
		),
	)
);
check( 'accordion: <details> cùng name (mở một mục), FAQPage có 2 câu hỏi', array( substr_count( $saha_ac, '<details name="saha-acc-' ), substr_count( $saha_ac, '"@type":"Question"' ), false !== strpos( $saha_ac, '"@type":"FAQPage"' ) ), array( 2, 2, true ) );
$saha_ac_ed = $saha_r1( array( 'type' => 'accordion', 'children' => array( array( 'type' => 'accordion-item' ), array( 'type' => 'accordion-item' ) ) ), true );
check( 'accordion: editor mở mọi mục, không in schema, không đặt name', array( substr_count( $saha_ac_ed, ' open="open"' ), false === strpos( $saha_ac_ed, 'ld+json' ), false === strpos( $saha_ac_ed, 'details name=' ) ), array( 2, true, true ) );
check( 'font web: URL Google Fonts một request, display=swap; không dùng → rỗng', array( Saha\Core\ThemeOptions\WebFonts::url( array( 'Be Vietnam Pro', 'Inter' ) ), Saha\Core\ThemeOptions\WebFonts::url( array() ) ), array( 'https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap', '' ) );
check( 'font web: chỉ lấy font Google đang chọn, bỏ trùng', Saha\Core\ThemeOptions\WebFonts::families( array( 'typography' => array( 'body' => array( 'fontFamily' => 'be-vietnam-pro' ), 'heading' => array( 'fontFamily' => 'be-vietnam-pro' ), 'menu' => array( 'fontFamily' => 'system' ) ) ) ), array( 'Be Vietnam Pro' ) );

echo "Header theo mẫu (D2)\n";
function get_search_query() { return 'keo <b>'; }
$saha_se = $saha_r1( array( 'type' => 'search', 'props' => array( 'style' => 'joined', 'buttonText' => '' ) ) );
check( 'tìm kiếm ô liền nút: form GET, có label, escape từ khoá, nút icon có chữ ẩn', array( false !== strpos( $saha_se, '<form role="search" method="get" class="saha-search-el__form"' ), false !== strpos( $saha_se, '<label class="screen-reader-text"' ), false !== strpos( $saha_se, 'value="keo &lt;b&gt;"' ), false !== strpos( $saha_se, 'screen-reader-text">Tìm<' ) ), array( true, true, true, true ) );
$saha_ct = $saha_r1( array( 'type' => 'contact', 'props' => array( 'style' => 'stacked', 'label' => 'Hotline tư vấn', 'value' => '0966.75.3382' ) ) );
check( 'liên hệ xếp chồng: tel:, nhãn nhỏ + số', array( false !== strpos( $saha_ct, 'href="tel:0966753382"' ), false !== strpos( $saha_ct, 'saha-contact-el__label">Hotline tư vấn<' ), false !== strpos( $saha_ct, 'saha-contact-el--stacked' ) ), array( true, true, true ) );
$saha_hr_css = ( new CssGenerator() )->document( ( new BuilderSanitizer() )->document( array( 'elements' => array( array( 'type' => 'site-header', 'children' => array( array( 'type' => 'header-row', 'props' => array( 'textColor' => '#ffffff' ), 'children' => array( array( 'type' => 'header-zone' ) ) ) ) ) ) ), 'header-root' )['document'] );
check( 'hàng header: màu chữ không tô link trong menu con / mega / danh mục / gợi ý tìm kiếm', false !== strpos( (string) $saha_hr_css, 'a:not(.saha-btn):not(:where(.sub-menu a, .saha-mega a, .saha-catmenu__panel a, .saha-ls a))' ), true );
$saha_nav_css = ( new CssGenerator() )->document( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'nav-menu', 'props' => array( 'color' => '#ffffff' ) ) ) ) ) ) )['document'] );
check( 'menu: màu chữ chỉ cho mục cấp 1', array( false !== strpos( $saha_nav_css, '.saha-nav__list > .menu-item > a{color:#ffffff' ), (bool) preg_match( '/saha-e-[a-z0-9]+ \.menu-item > a\{color/', $saha_nav_css ) ), array( true, false ) );
check( 'header kiểu cửa hàng hợp lệ với gốc header', ( new BuilderSanitizer() )->document( Saha\Core\Templates\Defaults::headerStore(), 'header-root' )['errors'], array() );
$saha_cm = $saha_r1( array( 'type' => 'category-menu', 'props' => array( 'source' => 'menu', 'menu' => 'menu-chinh' ) ) );
check( 'nút danh mục: link shop khi không JS, aria-expanded/aria-controls khớp bảng nav ẩn', array( false !== strpos( $saha_cm, 'role="button" aria-expanded="false" aria-controls="saha-catmenu-' ), (bool) preg_match( '/aria-controls="(saha-catmenu-[a-z0-9]+)".*<nav class="saha-catmenu__panel" id="\1"[^>]*hidden>/s', $saha_cm ) ), array( true, true ) );

echo "Thẻ sản phẩm + tab lọc (D3)\n";
$saha_pdef = $saha_reg->get( 'products' )->def()['controls'];
check( 'sản phẩm: tab lọc mặc định tắt, 2–8 tab', array( $saha_pdef['tabs']['default'], $saha_pdef['tabsLimit']['min'], $saha_pdef['tabsLimit']['max'] ), array( 'none', 2, 8 ) );
check( 'sản phẩm: giá trị tab lạ bị từ chối', count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'products', 'props' => array( 'tabs' => 'evil' ) ) ) ) ) ) )['errors'] ) > 0, true );
$saha_shop = Saha\Core\ThemeOptions\Schema::groups()['shop']['fields'];
check( 'Theme Options: thẻ mặc định, thanh "Đã bán" tắt mặc định', array( $saha_shop['card_style']['default'], $saha_shop['card_sold']['default'] ), array( 'default', false ) );

echo "Giao diện kiểu cửa hàng (D4)\n";
function wc_get_page_permalink( $p ) { return 'https://tongkhokeodan.com/shop/'; }
function get_posts( $a = array() ) { return array(); }
function get_post_thumbnail_id( $id ) { return 0; }
$saha_store = saha_bs( Saha\Core\Builder\StoreKit::homepage() );
$saha_store_json = (string) json_encode( $saha_store['document'] ? $saha_store['document']->toArray() : array(), JSON_UNESCAPED_UNICODE );
check( 'trang chủ kiểu cửa hàng hợp lệ, 9 section, đúng 1 H1, không shortcode', array( $saha_store['errors'], count( $saha_store['document']->toArray()['elements'] ), substr_count( $saha_store_json, '"tag":"h1"' ), false === strpos( $saha_store_json, '"shortcode"' ) ), array( array(), 9, 1, true ) );
check( 'trang chủ kiểu cửa hàng: không có ảnh sản phẩm → hero một cột, không slider rỗng', false === strpos( $saha_store_json, '"slider"' ), true );
check( 'trang chủ kiểu cửa hàng: tab danh mục + đánh giá mẫu ghi rõ là mẫu', array( false !== strpos( $saha_store_json, '"tabs":"categories"' ), substr_count( $saha_store_json, 'Đánh giá mẫu' ) ), array( true, 3 ) );
check( 'footer kiểu cửa hàng hợp lệ', saha_bs( Saha\Core\Builder\StoreKit::footer() )['errors'], array() );
check( 'nút kiểu Nhấn (màu nhấn)', false !== strpos( $saha_r1( array( 'type' => 'button', 'props' => array( 'text' => 'Xem', 'variant' => 'accent' ) ) ), 'saha-btn--accent' ), true );
$saha_one = $saha_r1( array( 'type' => 'slider', 'children' => array( array( 'type' => 'slide' ) ) ) );
check( 'slider 1 slide: không nút trước/sau, không chấm, không tự chạy', array( false === strpos( $saha_one, 'saha-slider__arrow' ), false === strpos( $saha_one, 'data-saha-slider-dots' ), false === strpos( $saha_one, 'data-autoplay' ) ), array( true, true, true ) );
check( 'bộ màu cửa hàng: navy + vàng đồng', array_intersect_key( Saha\Core\Builder\StoreKit::palette(), array_flip( array( 'secondary', 'accent', 'surface' ) ) ), array( 'secondary' => '#0e1f3a', 'accent' => '#d4a33b', 'surface' => '#f4f6fa' ) );

echo "Swatches, thanh dính, xem nhanh (mốc 2.3)\n";
use Saha\Core\WooCommerce\Swatches;
check( 'swatch: mã màu hợp lệ #rgb/#rrggbb, chữ hoa → thường, giá trị lạ → rỗng', array( Swatches::color( '#FFF' ), Swatches::color( '#1a2B3c' ), Swatches::color( 'red' ), Swatches::color( '#12345' ), Swatches::color( 'javascript:x' ) ), array( '#fff', '#1a2b3c', '', '', '' ) );
check( 'swatch: kiểu chưa đặt → danh sách thả xuống (giữ nguyên WooCommerce)', Swatches::type( 'pa_khong-co' ), 'select' );
$GLOBALS['__options'][ Swatches::OPTION ] = array( 'swatches' => array( 'pa_mau' => 'color', 'pa_x' => 'evil' ) );
check( 'swatch: đọc kiểu đã lưu; kiểu lạ → thả xuống', array( Swatches::type( 'pa_mau' ), Swatches::type( 'pa_x' ) ), array( 'color', 'select' ) );
check( 'Theme Options: swatches bật; thanh dính, xem nhanh tắt mặc định', array( Saha\Core\ThemeOptions\Schema::groups()['shop']['fields']['swatches']['default'], Saha\Core\ThemeOptions\Schema::groups()['shop']['fields']['sticky_cart']['default'], Saha\Core\ThemeOptions\Schema::groups()['shop']['fields']['quick_view']['default'] ), array( true, false, false ) );

echo "Trang danh mục kiểu cửa hàng (D5)\n";
use Saha\Core\Filter as SahaFilter;
$saha_bk = SahaFilter::buckets( array( 1200000, 2500000, 3100000, 5000000, 6150000, 6200000, 8000000, 12000000, 25890000 ) );
check( 'khoảng giá: mốc tròn tăng dần, khoảng đầu "Dưới", khoảng cuối "Trên"', array( array_column( $saha_bk, 'value' ), $saha_bk[0]['min'], end( $saha_bk )['max'] ), array( array( '-3000000', '3000000-6000000', '6000000-8000000', '8000000-' ), 0, 0 ) );
check( 'khoảng giá: ít hơn 2 mức giá → không có khoảng', array( SahaFilter::buckets( array() ), SahaFilter::buckets( array( 50000, 50000 ) ) ), array( array(), array() ) );
check( 'khoảng giá: nhãn', array( SahaFilter::price_label( 0, 3000000 ), SahaFilter::price_label( 3000000, 6000000 ), SahaFilter::price_label( 10000000, 0 ) ), array( 'Dưới 3.000.000 đ', '3.000.000 đ – 6.000.000 đ', 'Trên 10.000.000 đ' ) );
$saha_get = $_GET;
$_GET     = array( 'saha_price' => '6000000-3000000' );
$saha_p1  = SahaFilter::current();
$_GET     = array( 'saha_price' => '-3000000' );
$saha_p2  = SahaFilter::current();
$_GET     = array( 'saha_price' => '1 OR 1=1' );
$saha_p3  = SahaFilter::current();
$_GET     = array( 'saha_price' => array( 'x' ) );
$saha_p4  = SahaFilter::current();
$_GET     = $saha_get;
check( 'khoảng giá trên URL: đảo min/max, chỉ số, giá trị lạ bị bỏ', array( $saha_p1, $saha_p2, $saha_p3, $saha_p4 ), array( array( 'saha_price' => '3000000-6000000' ), array( 'saha_price' => '-3000000' ), array(), array() ) );
$saha_padef = $saha_reg->get( 'product-archive' )->def()['controls'];
$saha_atdef = $saha_reg->get( 'archive-title' )->def()['controls'];
check( 'danh sách sản phẩm: bố cục mặc định giữ như cũ; tiêu đề danh sách mặc định chữ thường', array( $saha_padef['layout']['default'], $saha_atdef['style']['default'], $saha_atdef['badge']['default'] ), array( 'stack', 'plain', '' ) );
check( 'danh sách sản phẩm: bố cục lạ bị từ chối', count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'product-archive', 'props' => array( 'layout' => 'evil' ) ) ) ) ) ) )['errors'] ) > 0, true );
$saha_arch      = saha_bs( Saha\Core\Builder\StoreKit::archive() );
$saha_arch_json = (string) json_encode( $saha_arch['document'] ? $saha_arch['document']->toArray() : array(), JSON_UNESCAPED_UNICODE );
check( 'shop & danh mục kiểu cửa hàng hợp lệ: tiêu đề khung + cột lọc', array( $saha_arch['errors'], false !== strpos( $saha_arch_json, '"layout":"sidebar"' ), false !== strpos( $saha_arch_json, '"style":"card"' ) ), array( array(), true, true ) );

echo "Ngăn giỏ hàng, gợi ý tìm kiếm (mốc 2.4)\n";
check( 'Theme Options: ngăn giỏ hàng bật mặc định', Saha\Core\ThemeOptions\Schema::groups()['shop']['fields']['mini_cart']['default'], true );
check( 'element Tìm kiếm: gợi ý khi gõ bật mặc định', $saha_reg->get( 'search' )->def()['controls']['live']['default'], true );
check( 'gợi ý tìm kiếm: chế độ catalogue → giá rỗng (không lộ giá)', Search::with_prices( array( array( 'id' => 42, 'name' => 'Loctite 243' ) ) ), array( array( 'id' => 42, 'name' => 'Loctite 243', 'price' => '' ) ) );

echo "Danh sách báo giá nhiều sản phẩm (mốc 2.5)\n";
check( 'Theme Options: danh sách báo giá tắt mặc định', Saha\Core\ThemeOptions\Schema::groups()['shop']['fields']['quote_list']['default'], false );
check( 'danh sách: không phải mảng / rỗng → lỗi items', array( isset( Saha\Core\Quote::validate_items( 'x' )['errors']['items'] ), isset( Saha\Core\Quote::validate_items( array() )['errors']['items'] ) ), array( true, true ) );
check( 'danh sách: quá số dòng tối đa → lỗi, không xử lý dòng nào', array( isset( Saha\Core\Quote::validate_items( array_fill( 0, Saha\Core\Quote::MAX_ITEMS + 1, array( 'product_id' => 42 ) ) )['errors']['items'] ), Saha\Core\Quote::validate_items( array_fill( 0, Saha\Core\Quote::MAX_ITEMS + 1, array( 'product_id' => 42 ) ) )['items'] ), array( true, array() ) );
$saha_l1 = array( array( 'product_id' => 5, 'variation_id' => 0, 'quantity' => 2, 'product_name' => 'Keo A', 'sku' => 'A' ), array( 'product_id' => 7, 'variation_id' => 9, 'quantity' => 1, 'product_name' => 'Keo B', 'sku' => 'B' ) );
check( 'danh sách: tên tóm tắt "dòng đầu (+N sản phẩm khác)"', array( Saha\Core\Quote::summary( $saha_l1 ), Saha\Core\Quote::summary( array( $saha_l1[0] ) ), Saha\Core\Quote::summary( array() ) ), array( 'Keo A (+1 sản phẩm khác)', 'Keo A', '' ) );
check( 'danh sách: chữ ký chống trùng không phụ thuộc thứ tự dòng', Saha\Core\Quote::signature( $saha_l1 ) === Saha\Core\Quote::signature( array_reverse( $saha_l1 ) ), true );
check( 'danh sách: đổi số lượng → chữ ký khác', Saha\Core\Quote::signature( $saha_l1 ) === Saha\Core\Quote::signature( array( array_merge( $saha_l1[0], array( 'quantity' => 3 ) ), $saha_l1[1] ) ), false );
check( 'element danh sách báo giá + icon header đã đăng ký', array( null !== $saha_reg->get( 'quote-list' ), null !== $saha_reg->get( 'quote-list-link' ) ), array( true, true ) );

echo "Import / Export (mốc 2.6)\n";
require_once SAHA_CORE_PATH . 'includes/ImportExport/Walker.php';
use Saha\Core\ImportExport\Walker as SahaWalker;
$saha_ie_doc = array(
	'version'  => 1,
	'elements' => array(
		array(
			'id'       => 'a1',
			'type'     => 'section',
			'props'    => array( 'background' => array( 'color' => '#fff', 'image' => array( 'id' => 10, 'size' => 'large' ) ) ),
			'children' => array(
				array( 'id' => 'a2', 'type' => 'image', 'props' => array( 'image' => array( 'id' => 11, 'size' => 'full' ), 'alt' => 'x' ) ),
				array( 'id' => 'a3', 'type' => 'logo', 'props' => array( 'image' => array( 'desktop' => array( 'id' => 12, 'size' => 'medium' ) ) ) ),
				array( 'id' => 'a4', 'type' => 'block', 'props' => array( 'blockId' => 52 ) ),
				array( 'id' => 'a5', 'type' => 'block', 'props' => array( 'blockId' => 99 ) ),
			),
		),
	),
);
$saha_ie_m = array();
$saha_ie_b = array();
SahaWalker::collect( $saha_ie_doc, $saha_ie_m, $saha_ie_b );
sort( $saha_ie_m );
sort( $saha_ie_b );
check( 'xuất: gom ảnh (nền, ảnh, responsive) + block được dùng', array( $saha_ie_m, $saha_ie_b ), array( array( 10, 11, 12 ), array( 52, 99 ) ) );
check( 'giá trị ảnh {id,size}; node có type không bị nhầm', array( SahaWalker::isMedia( array( 'id' => 3, 'size' => 'full' ) ), SahaWalker::isMedia( array( 'id' => 'a1', 'type' => 'section', 'size' => 'x' ) ), SahaWalker::isMedia( array( 'id' => 3 ) ) ), array( true, false, false ) );
$saha_ie_w = array();
$saha_ie_r = SahaWalker::remap( $saha_ie_doc, array( 10 => 110, 11 => 0, 12 => 112 ), array( 52 => 152 ), $saha_ie_w, 'Trang A' );
$saha_ie_c = $saha_ie_r['elements'][0]['children'];
check(
	'nhập: đổi ID ảnh / block; ảnh tải lỗi + block thiếu → bỏ giá trị, có cảnh báo',
	array( $saha_ie_r['elements'][0]['props']['background'], $saha_ie_c[0]['props'], $saha_ie_c[1]['props']['image']['desktop']['id'], $saha_ie_c[2]['props']['blockId'], $saha_ie_c[3]['props'], count( $saha_ie_w ) ),
	array( array( 'color' => '#fff', 'image' => array( 'id' => 110, 'size' => 'large' ) ), array( 'alt' => 'x' ), 112, 152, array(), 2 )
);
check( 'chạy thử: strip bỏ mọi ảnh / block, giữ cấu trúc', array( SahaWalker::strip( $saha_ie_doc )['elements'][0]['props'], count( SahaWalker::strip( $saha_ie_doc )['elements'][0]['children'] ) ), array( array( 'background' => array( 'color' => '#fff' ) ), 4 ) );

echo "Element Phase 2 (mốc 2.7)\n";
if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() { return new DateTimeZone( 'Asia/Ho_Chi_Minh' ); }
}
use Saha\Core\Builder\Elements\Video as SahaVideo;
use Saha\Core\Builder\Elements\Countdown as SahaCountdown;
check(
	'video: nhận YouTube (watch, youtu.be, shorts) → nocookie; Vimeo; mp4; link lạ → null',
	array(
		SahaVideo::parse( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10' )['embed'] ?? null,
		SahaVideo::parse( 'https://youtu.be/dQw4w9WgXcQ' )['id'] ?? null,
		SahaVideo::parse( 'https://www.youtube.com/shorts/dQw4w9WgXcQ' )['provider'] ?? null,
		SahaVideo::parse( 'https://vimeo.com/123456789' )['embed'] ?? null,
		SahaVideo::parse( 'https://cdn.example.com/a.mp4' )['provider'] ?? null,
		SahaVideo::parse( 'javascript:alert(1)//youtube.com/watch?v=dQw4w9WgXcQ' ),
		SahaVideo::parse( 'https://evil.example/youtube.com/watch?v=dQw4w9WgXcQ' ),
	),
	array( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0', 'dQw4w9WgXcQ', 'youtube', 'https://player.vimeo.com/video/123456789?autoplay=1&dnt=1', 'file', null, null )
);
check( 'đếm ngược: chia ngày/giờ/phút/giây; không hiện ngày → dồn vào giờ', array( SahaCountdown::split( 2 * 86400 + 3 * 3600 + 4 * 60 + 5, true ), SahaCountdown::split( 86400 + 60, false ), SahaCountdown::split( -5, true ) ), array( array( 'd' => 2, 'h' => 3, 'm' => 4, 's' => 5 ), array( 'd' => 0, 'h' => 24, 'm' => 1, 's' => 0 ), array( 'd' => 0, 'h' => 0, 'm' => 0, 's' => 0 ) ) );
check( 'đếm ngược: thời điểm sai định dạng → null', array( SahaCountdown::target( '31/12/2026' ), SahaCountdown::target( '2026-13-40 25:61' ), SahaCountdown::target( '' ) ), array( null, null, null ) );
$saha_tabs = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'tabs', 'children' => array(
	array( 'type' => 'tab', 'props' => array( 'title' => 'Mô tả' ), 'children' => array( array( 'type' => 'heading', 'props' => array( 'text' => 'A' ) ) ) ),
	array( 'type' => 'tab', 'props' => array( 'title' => '<b>Thông số</b>' ) ),
) ) ) ) ) ) );
$saha_tabs_html = ( new Renderer() )->document( $saha_tabs['document'], new RenderContext( 0, false, false ) );
check(
	'tabs: hợp lệ; ARIA tablist / tab / tabpanel; tab 2 ẩn sẵn; thẻ HTML trong tiêu đề bị bỏ',
	array( $saha_tabs['errors'], substr_count( $saha_tabs_html, 'role="tab"' ), 1 === preg_match( '/id="saha-tp-[a-z0-9]+" hidden role="tabpanel"/', $saha_tabs_html ), false !== strpos( $saha_tabs_html, '>Thông số</button>' ), false === strpos( $saha_tabs_html, '<b>' ) ),
	array( array(), 2, true, true, true )
);
$saha_tabs_ed = ( new Renderer() )->document( $saha_tabs['document'], new RenderContext( 0, true, false ) );
check( 'tabs trong editor: không ẩn tab nào (sửa được trên canvas)', false === strpos( $saha_tabs_ed, ' hidden role="tabpanel"' ), true );
check( 'tab chỉ đặt được trong tabs; ảnh đặt được trong thư viện ảnh / logo; element nội dung đặt được trong lưới và tab', array( count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'tab' ) ) ) ) ) )['errors'] ) > 0, saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'gallery', 'children' => array( array( 'type' => 'image' ) ) ), array( 'type' => 'grid', 'children' => array( array( 'type' => 'heading' ), array( 'type' => 'button' ) ) ) ) ) ) ) )['errors'] ), array( true, array() ) );
check( 'thư viện ảnh / logo: element khác ảnh bị từ chối', count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'gallery', 'children' => array( array( 'type' => 'heading' ) ) ) ) ) ) ) )['errors'] ) > 0, true );

echo "Hộp: kích thước (D8)\n";
$saha_box = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'container', 'props' => array(
	'width'     => array( 'desktop' => '68.7%', 'mobile' => '100%' ),
	'minHeight' => array( 'desktop' => '320px' ),
	'height'    => '50vh',
	'overflow'  => 'hidden',
) ) ) ) ) ) );
$saha_box_css = $saha_box['document'] ? ( new CssGenerator() )->document( $saha_box['document'] ) : '';
check(
	'hộp: độ rộng / chiều cao tối thiểu / cao / phần tràn hợp lệ, ra CSS theo thiết bị',
	array( $saha_box['errors'], false !== strpos( $saha_box_css, 'width:68.7%' ), false !== strpos( $saha_box_css, 'min-height:320px' ), false !== strpos( $saha_box_css, 'height:50vh' ), false !== strpos( $saha_box_css, 'overflow:hidden' ), 1 === preg_match( '/@media[^{]*\{[^}]*width:100%/', $saha_box_css ) ),
	array( array(), true, true, true, true, true )
);
check(
	'hộp: đơn vị sai / phần tràn lạ bị từ chối',
	array(
		count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'container', 'props' => array( 'width' => '20em' ) ) ) ) ) ) )['errors'] ) > 0,
		count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'container', 'props' => array( 'minHeight' => '50%' ) ) ) ) ) ) )['errors'] ) > 0,
		count( saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'container', 'props' => array( 'overflow' => 'scroll; x' ) ) ) ) ) ) )['errors'] ) > 0,
	),
	array( true, true, true )
);

echo "QA Phase 2 (mốc 2.8)\n";
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) { return 'https://tongkhokeodan.com/wp-json/' . ltrim( (string) $path, '/' ); }
}
$saha_xss = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
$saha_x   = saha_bs( array( 'elements' => array( array( 'type' => 'section', 'children' => array(
	array( 'type' => 'tabs', 'props' => array( 'label' => $saha_xss ), 'children' => array( array( 'type' => 'tab', 'props' => array( 'title' => $saha_xss ) ) ) ),
	array( 'type' => 'video', 'props' => array( 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => $saha_xss ) ),
	array( 'type' => 'countdown', 'props' => array( 'until' => '2099-01-01 00:00', 'doneText' => $saha_xss ) ),
	array( 'type' => 'newsletter', 'props' => array( 'placeholder' => $saha_xss, 'button' => $saha_xss, 'note' => $saha_xss ) ),
	array( 'type' => 'grid', 'children' => array( array( 'type' => 'heading', 'props' => array( 'text' => $saha_xss ) ) ) ),
) ) ) ) );
$saha_x_html = $saha_x['document'] ? ( new Renderer() )->document( $saha_x['document'], new RenderContext( 0, false, false ) ) : '';
check( 'element Phase 2: chuỗi tấn công trong mọi ô chữ không thành thẻ / thuộc tính HTML', array( '' !== $saha_x_html, false === stripos( $saha_x_html, '<script' ), false === stripos( $saha_x_html, '<img src=x' ), 0 === preg_match( '/\sonerror=/i', $saha_x_html ) ), array( true, true, true, true ) );
check( 'video: link javascript: / data: không được nhận', array( SahaVideo::parse( 'javascript:alert(1)' ), SahaVideo::parse( 'data:text/html,<script>' ) ), array( null, null ) );

echo "Performance\\CloudflarePurge\n";
$saha_cf_urls = array_map( static fn( $i ) => 'https://siliconephuminh.com/p-' . $i . '/', range( 1, 65 ) );
$saha_cf_pay  = \Saha\Core\Performance\CloudflarePurge::payloads( array_merge( $saha_cf_urls, array( $saha_cf_urls[0], '' ) ), false );
check( 'purge theo URL: bỏ trùng/rỗng, chia lô ≤ 30', array( count( $saha_cf_pay ), count( $saha_cf_pay[0]['files'] ), count( $saha_cf_pay[2]['files'] ) ), array( 3, 30, 5 ) );
check( 'purge toàn bộ: một lệnh purge_everything', \Saha\Core\Performance\CloudflarePurge::payloads( $saha_cf_urls, true ), array( array( 'purge_everything' => true ) ) );
check( 'không có URL: không gửi gì', \Saha\Core\Performance\CloudflarePurge::payloads( array(), false ), array() );
check( 'chưa định nghĩa hằng số: module tắt', \Saha\Core\Performance\CloudflarePurge::configured(), false );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
