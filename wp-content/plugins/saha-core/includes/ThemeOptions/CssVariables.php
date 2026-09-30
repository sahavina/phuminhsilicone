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
