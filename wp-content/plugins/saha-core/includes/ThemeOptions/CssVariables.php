<?php
/**
 * Theme Options — sinh CSS toàn cục (biến :root).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * CssVariables.
 *
 * Chuyển Theme Options thành biến CSS `--saha-*` (spec SCC §28). Theme và
 * builder chỉ dùng biến — đổi màu/chữ trong admin là toàn site đổi theo,
 * không phải sửa CSS.
 *
 * Breakpoint (spec SCC §55): tablet ≤ 1024px, mobile < 768px.
 */
final class CssVariables {

	public const TABLET_MAX = 1024;
	public const MOBILE_MAX = 767;

	/**
	 * Sinh CSS từ giá trị Theme Options.
	 *
	 * @param array<string, array<string, mixed>> $values Giá trị (Repository::all()).
	 */
	public static function build( array $values ): string {
		$vars = array(
			'desktop' => array(),
			'tablet'  => array(),
			'mobile'  => array(),
		);

		foreach ( Schema::groups() as $group => $definition ) {
			foreach ( (array) $definition['fields'] as $key => $field ) {
				if ( empty( $field['cssVar'] ) ) {
					continue;
				}

				$name  = (string) $field['cssVar'];
				$value = $values[ $group ][ $key ] ?? ( $field['default'] ?? null );

				if ( 'typography' === ( $field['type'] ?? '' ) ) {
					foreach ( self::typography( $name, is_array( $value ) ? $value : array() ) as $var => $css ) {
						$vars['desktop'][ $var ] = $css;
					}
					continue;
				}

				if ( is_array( $value ) ) {
					foreach ( Sanitizer::DEVICES as $device ) {
						if ( isset( $value[ $device ] ) && '' !== (string) $value[ $device ] ) {
							$vars[ $device ][ $name ] = (string) $value[ $device ];
						}
					}
					continue;
				}

				if ( null !== $value && '' !== (string) $value ) {
					$vars['desktop'][ $name ] = (string) $value;
				}
			}
		}

		// Chữ trên nền màu nhấn: trắng hay màu phụ, chọn cái tương phản hơn (nhấn vàng → chữ tối, nhấn đỏ → chữ trắng).
		$on_accent = self::readableOn(
			(string) ( $vars['desktop']['--saha-accent'] ?? '' ),
			(string) ( $vars['desktop']['--saha-secondary'] ?? '' )
		);

		if ( '' !== $on_accent ) {
			$vars['desktop']['--saha-on-accent'] = $on_accent;
		}

		/**
		 * Thêm/sửa biến CSS toàn cục.
		 *
		 * @param array<string, array<string, string>> $vars      device => [ biến => giá trị ].
		 * @param array<string, mixed>                 $values    Theme Options.
		 */
		$vars = (array) apply_filters( 'saha_css_variables', $vars, $values );

		$css = self::block( ':root', (array) ( $vars['desktop'] ?? array() ) );

		if ( ! empty( $vars['tablet'] ) ) {
			$css .= '@media (max-width:' . self::TABLET_MAX . 'px){' . self::block( ':root', (array) $vars['tablet'] ) . '}';
		}

		if ( ! empty( $vars['mobile'] ) ) {
			$css .= '@media (max-width:' . self::MOBILE_MAX . 'px){' . self::block( ':root', (array) $vars['mobile'] ) . '}';
		}

		$custom = (string) ( $values['custom_css']['css'] ?? '' );

		if ( '' !== trim( $custom ) ) {
			// Đã qua Sanitizer::css khi lưu; lọc lần nữa phòng dữ liệu cũ/nhập tay DB.
			$css .= "\n/* SAHA — CSS tuỳ chỉnh */\n" . Sanitizer::css( $custom ) . "\n";
		}

		return $css;
	}

	/**
	 * Màu chữ dễ đọc trên nền `$background`: trắng hoặc `$dark`, lấy cái có độ tương phản WCAG cao hơn.
	 *
	 * @param string $background Màu nền hex.
	 * @param string $dark       Màu chữ tối hex.
	 * @return string '' nếu màu không phải hex 3/6 ký tự.
	 */
	public static function readableOn( string $background, string $dark ): string {
		$bg = self::luminance( $background );
		$dk = self::luminance( $dark );

		if ( null === $bg || null === $dk ) {
			return '';
		}

		$with_white = 1.05 / ( $bg + 0.05 );
		$with_dark  = ( max( $bg, $dk ) + 0.05 ) / ( min( $bg, $dk ) + 0.05 );

		return $with_white >= $with_dark ? '#ffffff' : $dark;
	}

	/**
	 * Độ sáng tương đối (WCAG) của màu hex.
	 *
	 * @param string $hex Màu `#rgb` hoặc `#rrggbb`.
	 */
	private static function luminance( string $hex ): ?float {
		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex, $m ) ) {
			return null;
		}

		$h = 3 === strlen( $m[1] ) ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];
		$c = array_map(
			static function ( string $part ): float {
				$v = hexdec( $part ) / 255;
				return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( $h, 2 )
		);

		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	/**
	 * Biến cho một nhóm typography.
	 *
	 * @param string               $prefix Ví dụ `--saha-type-body`.
	 * @param array<string, mixed> $value  Giá trị typography.
	 * @return array<string, string>
	 */
	private static function typography( string $prefix, array $value ): array {
		$fonts  = Schema::fontStacks();
		$family = (string) ( $value['fontFamily'] ?? 'system' );
		$out    = array(
			$prefix . '-font' => $fonts[ $family ]['stack'] ?? $fonts['system']['stack'],
		);

		$map = array(
			'fontSize'      => '-size',
			'fontWeight'    => '-weight',
			'lineHeight'    => '-line-height',
			'letterSpacing' => '-letter-spacing',
		);

		foreach ( $map as $key => $suffix ) {
			if ( isset( $value[ $key ] ) && '' !== (string) $value[ $key ] ) {
				$out[ $prefix . $suffix ] = (string) $value[ $key ];
			}
		}

		return $out;
	}

	/**
	 * Một khối khai báo CSS.
	 *
	 * Tên biến chỉ gồm [a-z0-9-]; giá trị không được chứa ký tự thoát khỏi
	 * khai báo (`;` `{` `}` `<`) — phòng filter bên thứ ba truyền bậy.
	 *
	 * @param string                $selector Selector.
	 * @param array<string, string> $vars     Biến.
	 */
	private static function block( string $selector, array $vars ): string {
		$lines = array();

		foreach ( $vars as $name => $value ) {
			$name  = (string) $name;
			$value = (string) $value;

			if ( ! preg_match( '/^--saha-[a-z0-9-]+$/', $name ) || preg_match( '/[;{}<>]/', $value ) ) {
				continue;
			}

			$lines[] = $name . ':' . $value;
		}

		return $lines ? $selector . '{' . implode( ';', $lines ) . '}' : '';
	}
}
