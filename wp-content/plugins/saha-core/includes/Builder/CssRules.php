<?php
/**
 * Tập luật CSS của một layout.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Controls\Spacing;
use Saha\Core\Builder\Controls\Typography;

defined( 'ABSPATH' ) || exit;

/**
 * CssRules.
 *
 * Element khai báo style qua đây, không tự nối chuỗi CSS. Mọi giá trị được lọc
 * lần nữa (lớp bảo vệ thứ hai sau control sanitizer): dữ liệu sửa tay trong DB
 * cũng không thoát được khỏi khai báo hay chèn `</style>`.
 */
final class CssRules {

	/**
	 * device → selector → property → value.
	 *
	 * @var array<string, array<string, array<string, string>>>
	 */
	private array $rules = array(
		'desktop' => array(),
		'tablet'  => array(),
		'mobile'  => array(),
	);

	/**
	 * Selector gốc của element đang khai báo, ví dụ `.saha-e-k3f9a2c1`.
	 *
	 * @var string
	 */
	private string $scope = '';

	/**
	 * Đặt element đang khai báo.
	 *
	 * @param string $id ID node.
	 */
	public function forNode( string $id ): self {
		$this->scope = '.saha-e-' . preg_replace( '/[^a-z0-9]/', '', $id );

		return $this;
	}

	/**
	 * Khai báo một thuộc tính (giá trị scalar hoặc responsive).
	 *
	 * @param string        $selector Hậu tố selector: '' (chính element), ' .con', '::before', ':hover'.
	 * @param string        $property Thuộc tính CSS hoặc custom property `--x`.
	 * @param mixed         $value    Giá trị.
	 * @param callable|null $map      Biến đổi giá trị trước khi ghi, trả null để bỏ.
	 */
	public function set( string $selector, string $property, $value, ?callable $map = null ): self {
		foreach ( Responsive::DEVICES as $device ) {
			$raw = Responsive::at( $value, $device );

			if ( null === $raw || '' === $raw || is_array( $raw ) ) {
				continue;
			}

			$css = $map ? $map( $raw ) : (string) $raw;

			if ( null !== $css ) {
				$this->put( $device, $selector, $property, (string) $css );
			}
		}

		return $this;
	}

	/**
	 * Khai báo trực tiếp cho một breakpoint.
	 *
	 * @param string $device   Breakpoint.
	 * @param string $selector Hậu tố selector.
	 * @param string $property Thuộc tính.
	 * @param string $value    Giá trị.
	 */
	public function put( string $device, string $selector, string $property, string $value ): self {
		$value = self::cleanValue( $value );

		if ( '' === $value || ! isset( $this->rules[ $device ] ) || ! preg_match( '/^-{0,2}[a-z][a-z0-9-]*$/', $property ) ) {
			return $this;
		}

		$selector = self::cleanSelector( $selector );

		if ( null === $selector ) {
			return $this;
		}

		$this->rules[ $device ][ $this->scope . $selector ][ $property ] = $value;

		return $this;
	}

	/**
	 * Spacing responsive → margin-top… / padding-top….
	 *
	 * @param string $selector Hậu tố selector.
	 * @param string $property `margin` | `padding`.
	 * @param mixed  $value    Giá trị control spacing (có thể responsive).
	 */
	public function spacing( string $selector, string $property, $value ): self {
		foreach ( Responsive::DEVICES as $device ) {
			$sides = Responsive::isResponsive( $value ) ? ( $value[ $device ] ?? null ) : ( 'desktop' === $device ? $value : null );

			if ( ! is_array( $sides ) ) {
				continue;
			}

			foreach ( Spacing::SIDES as $side ) {
				if ( isset( $sides[ $side ] ) && is_string( $sides[ $side ] ) ) {
					$this->put( $device, $selector, $property . '-' . $side, $sides[ $side ] );
				}
			}
		}

		return $this;
	}

	/**
	 * Typography → font-*.
	 *
	 * @param string $selector Hậu tố selector.
	 * @param mixed  $value    Giá trị control typography.
	 */
	public function typography( string $selector, $value ): self {
		if ( ! is_array( $value ) ) {
			return $this;
		}

		if ( ! empty( $value['fontFamily'] ) ) {
			$this->put( 'desktop', $selector, 'font-family', Typography::familyCss( (string) $value['fontFamily'] ) );
		}

		$this->set( $selector, 'font-size', $value['fontSize'] ?? null );
		$this->set( $selector, 'line-height', $value['lineHeight'] ?? null );
		$this->set( $selector, 'letter-spacing', $value['letterSpacing'] ?? null );
		$this->set( $selector, 'font-weight', $value['fontWeight'] ?? null );
		$this->set( $selector, 'text-transform', $value['textTransform'] ?? null );
		$this->set( $selector, 'font-style', $value['fontStyle'] ?? null );

		return $this;
	}

	/**
	 * Gộp luật của tập khác (render subtree cho editor).
	 *
	 * @param CssRules $other Tập khác.
	 */
	public function merge( CssRules $other ): self {
		foreach ( $other->rules as $device => $selectors ) {
			foreach ( $selectors as $selector => $props ) {
				$this->rules[ $device ][ $selector ] = array_merge( $this->rules[ $device ][ $selector ] ?? array(), $props );
			}
		}

		return $this;
	}

	/**
	 * Có luật nào không.
	 */
	public function isEmpty(): bool {
		return ! array_filter( $this->rules );
	}

	/**
	 * Xuất CSS (desktop trước, rồi tablet, mobile — kế thừa nhờ max-width).
	 */
	public function toCss(): string {
		$css = self::block( $this->rules['desktop'] );

		if ( $this->rules['tablet'] ) {
			$css .= '@media (max-width:' . Limits::TABLET_MAX . 'px){' . self::block( $this->rules['tablet'] ) . '}';
		}

		if ( $this->rules['mobile'] ) {
			$css .= '@media (max-width:' . Limits::MOBILE_MAX . 'px){' . self::block( $this->rules['mobile'] ) . '}';
		}

		return $css;
	}

	/**
	 * Lọc giá trị CSS.
	 *
	 * Cho phép `url("…")` chỉ khi là URL http(s) sạch (ảnh nền từ attachment).
	 *
	 * @param string $value Giá trị.
	 */
	public static function cleanValue( string $value ): string {
		$value = trim( $value );

		// url(...) hợp lệ: tách riêng, kiểm tra, thay bằng placeholder rồi lọc phần còn lại.
		$urls  = array();
		$value = (string) preg_replace_callback(
			'/url\(\s*"([^"]*)"\s*\)/i',
			static function ( array $m ) use ( &$urls ): string {
				$url = esc_url_raw( $m[1], array( 'http', 'https' ) );

				if ( '' === $url || preg_match( '/["\\\\()\s<>]/', $url ) ) {
					return 'INVALID_URL';
				}

				$urls[] = $url;

				return '__URL' . ( count( $urls ) - 1 ) . '__';
			},
			$value
		);

		if ( false !== strpos( $value, 'INVALID_URL' ) || preg_match( '/[;{}<>\\\\]|\/\*|expression|javascript:|url\s*\(|@import/i', $value ) ) {
			return '';
		}

		foreach ( $urls as $i => $url ) {
			$value = str_replace( '__URL' . $i . '__', 'url("' . $url . '")', $value );
		}

		return $value;
	}

	/**
	 * Selector hậu tố: chỉ ký tự an toàn.
	 *
	 * Dấu phẩy chỉ được nằm trong ngoặc (`:where(h1, h2)`) — dấu phẩy cấp ngoài
	 * sẽ tạo selector thứ hai KHÔNG có phạm vi `.saha-e-{id}` (áp cho cả trang).
	 *
	 * @param string $selector Selector.
	 * @return string|null Null nếu không hợp lệ.
	 */
	private static function cleanSelector( string $selector ): ?string {
		$selector = (string) preg_replace( '/[^a-zA-Z0-9 _\-.:>*(),]/', '', $selector );
		$outer    = (string) preg_replace( '/\([^()]*\)/', '', $selector );

		if ( false !== strpos( $outer, ',' ) || substr_count( $selector, '(' ) !== substr_count( $selector, ')' ) ) {
			return null;
		}

		return $selector;
	}

	/**
	 * Khối selector { prop: value }.
	 *
	 * @param array<string, array<string, string>> $selectors Luật.
	 */
	private static function block( array $selectors ): string {
		$css = '';

		foreach ( $selectors as $selector => $props ) {
			if ( ! $props ) {
				continue;
			}

			$lines = array();

			foreach ( $props as $property => $value ) {
				$lines[] = $property . ':' . $value;
			}

			$css .= $selector . '{' . implode( ';', $lines ) . '}';
		}

		return $css;
	}
}
