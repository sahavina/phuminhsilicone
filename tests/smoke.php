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

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
