<?php
/**
 * Theme Options — sanitize theo loại field.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer.
 *
 * Mỗi hàm nhận giá trị thô từ client và định nghĩa field, trả về giá trị sạch
 * hoặc ném InvalidValue. Không tin bất kỳ thứ gì client gửi — kể cả khi UI
 * đã chặn (TECHNICAL-DESIGN §15).
 *
 * Các giá trị này đi thẳng vào CSS (biến :root), nên whitelist chặt: không
 * cho ký tự nào có thể thoát khỏi khai báo CSS (`;`, `}`, `<`, `url(`…).
 */
final class Sanitizer {

	/**
	 * Breakpoint hợp lệ cho giá trị responsive.
	 */
	public const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	/**
	 * Sanitize một field.
	 *
	 * @param mixed                $value Giá trị thô.
	 * @param array<string, mixed> $field Định nghĩa field (Schema).
	 * @return mixed
	 * @throws InvalidValue Khi giá trị không hợp lệ.
	 */
	public static function field( $value, array $field ) {
		$type = (string) ( $field['type'] ?? 'text' );

		if ( ! empty( $field['responsive'] ) ) {
			return self::responsive( $value, $field );
		}

		switch ( $type ) {
			case 'color':
				return self::color( $value );
			case 'size':
				return self::size( $value, $field );
			case 'number':
				return self::number( $value, $field );
			case 'select':
				return self::select( $value, $field );
			case 'toggle':
				return self::toggle( $value );
			case 'media':
				return self::media( $value );
			case 'typography':
				return self::typography( $value );
			case 'css':
				return self::css( $value );
			case 'textarea':
				return sanitize_textarea_field( self::scalar( $value ) );
			case 'text':
			default:
				return sanitize_text_field( self::scalar( $value ) );
		}
	}

	/**
	 * Giá trị responsive: scalar hoặc {desktop, tablet, mobile}.
	 *
	 * Thiếu breakpoint thì bỏ trống → CSS kế thừa từ breakpoint lớn hơn.
	 *
	 * @param mixed                $value Giá trị thô.
	 * @param array<string, mixed> $field Định nghĩa field.
	 * @return array<string, mixed>
	 * @throws InvalidValue Khi desktop trống hoặc một breakpoint sai.
	 */
	public static function responsive( $value, array $field ): array {
		$single = $field;
		unset( $single['responsive'] );

		if ( ! is_array( $value ) ) {
			return array( 'desktop' => self::field( $value, $single ) );
		}

		$out = array();

		foreach ( self::DEVICES as $device ) {
			if ( ! isset( $value[ $device ] ) || '' === $value[ $device ] ) {
				continue;
			}

			$out[ $device ] = self::field( $value[ $device ], $single );
		}

		if ( ! isset( $out['desktop'] ) ) {
			throw new InvalidValue( __( 'Cần giá trị cho desktop.', 'saha-core' ) );
		}

		return $out;
	}

	/**
	 * Màu: #rgb, #rrggbb, #rrggbbaa, rgb()/rgba(), hoặc biến var(--saha-*).
	 *
	 * @param mixed $value Giá trị thô.
	 * @throws InvalidValue Khi không phải màu hợp lệ.
	 */
	public static function color( $value ): string {
		$value = strtolower( trim( self::scalar( $value ) ) );

		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
			return preg_replace( '/\s+/', '', $value ) ?? $value;
		}

		if ( preg_match( '/^var\(--saha-[a-z0-9-]+\)$/', $value ) || 'transparent' === $value ) {
			return $value;
		}

		throw new InvalidValue( __( 'Màu không hợp lệ.', 'saha-core' ) );
	}

	/**
	 * Kích thước CSS có đơn vị, trong khoảng min–max của field.
	 *
	 * Số trần (`48`) được hiểu là đơn vị đầu tiên của field (thường px).
	 *
	 * @param mixed                $value Giá trị thô.
	 * @param array<string, mixed> $field Định nghĩa field.
	 * @throws InvalidValue Khi sai định dạng, sai đơn vị hoặc ngoài khoảng.
	 */
	public static function size( $value, array $field ): string {
		$units = (array) ( $field['units'] ?? array( 'px' ) );
		$raw   = strtolower( trim( self::scalar( $value ) ) );

		if ( '' === $raw ) {
			throw new InvalidValue( __( 'Cần nhập kích thước.', 'saha-core' ) );
		}

		if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)([a-z%]*)$/', $raw, $m ) ) {
			throw new InvalidValue( __( 'Kích thước không hợp lệ (ví dụ: 24px).', 'saha-core' ) );
		}

		$number = (float) $m[1];
		$unit   = '' !== $m[2] ? $m[2] : (string) $units[0];

		if ( ! in_array( $unit, $units, true ) ) {
			/* translators: %s: danh sách đơn vị */
			throw new InvalidValue( sprintf( __( 'Đơn vị không được hỗ trợ. Dùng: %s', 'saha-core' ), implode( ', ', $units ) ) );
		}

		// min/max khai báo theo px; rem/em quy đổi 16px để so sánh.
		$px = in_array( $unit, array( 'rem', 'em' ), true ) ? $number * 16 : $number;

		if ( ( isset( $field['min'] ) && $px < (float) $field['min'] ) || ( isset( $field['max'] ) && $px > (float) $field['max'] ) ) {
			/* translators: 1: min, 2: max */
			throw new InvalidValue( sprintf( __( 'Giá trị phải trong khoảng %1$s–%2$spx.', 'saha-core' ), (string) ( $field['min'] ?? '' ), (string) ( $field['max'] ?? '' ) ) );
		}

		return self::trimNumber( $number ) . $unit;
	}

	/**
	 * Số nguyên trong khoảng min–max.
	 *
	 * @param mixed                $value Giá trị thô.
	 * @param array<string, mixed> $field Định nghĩa field.
	 * @throws InvalidValue Khi không phải số hoặc ngoài khoảng.
	 */
	public static function number( $value, array $field ): int {
		if ( ! is_numeric( $value ) || (float) $value !== floor( (float) $value ) ) {
			throw new InvalidValue( __( 'Cần một số nguyên.', 'saha-core' ) );
		}

		$number = (int) $value;
		$min    = (int) ( $field['min'] ?? PHP_INT_MIN );
		$max    = (int) ( $field['max'] ?? PHP_INT_MAX );

		if ( $number < $min || $number > $max ) {
			/* translators: 1: min, 2: max */
			throw new InvalidValue( sprintf( __( 'Giá trị phải trong khoảng %1$d–%2$d.', 'saha-core' ), $min, $max ) );
		}

		return $number;
	}

	/**
	 * Một giá trị trong danh sách options.
	 *
	 * @param mixed                $value Giá trị thô.
	 * @param array<string, mixed> $field Định nghĩa field.
	 * @throws InvalidValue Khi không nằm trong danh sách.
	 */
	public static function select( $value, array $field ): string {
		$value = self::scalar( $value );

		if ( ! array_key_exists( $value, (array) ( $field['options'] ?? array() ) ) ) {
			throw new InvalidValue( __( 'Lựa chọn không hợp lệ.', 'saha-core' ) );
		}

		return $value;
	}

	/**
	 * Bật/tắt.
	 *
	 * @param mixed $value Giá trị thô.
	 */
	public static function toggle( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}

	/**
	 * Attachment ảnh; 0 = không chọn.
	 *
	 * @param mixed $value Giá trị thô.
	 * @throws InvalidValue Khi ID không phải ảnh trong Media Library.
	 */
	public static function media( $value ): int {
		$id = absint( is_scalar( $value ) ? $value : 0 );

		if ( 0 === $id ) {
			return 0;
		}

		if ( ! wp_attachment_is_image( $id ) ) {
			throw new InvalidValue( __( 'Ảnh không tồn tại trong thư viện.', 'saha-core' ) );
		}

		return $id;
	}

	/**
	 * Typography: fontFamily (key font stack), fontSize (tuỳ chọn), fontWeight,
	 * lineHeight, letterSpacing.
	 *
	 * @param mixed $value Giá trị thô.
	 * @return array<string, string>
	 * @throws InvalidValue Khi một thành phần sai.
	 */
	public static function typography( $value ): array {
		$value = is_array( $value ) ? $value : array();
		$fonts = Schema::fontStacks();

		$family = self::scalar( $value['fontFamily'] ?? 'system' );

		if ( ! isset( $fonts[ $family ] ) ) {
			throw new InvalidValue( __( 'Font không hợp lệ.', 'saha-core' ) );
		}

		$size = trim( self::scalar( $value['fontSize'] ?? '' ) );

		if ( '' !== $size ) {
			$size = self::size(
				$size,
				array(
					'units' => array( 'px', 'rem' ),
					'min'   => 10,
					'max'   => 48,
				)
			);
		}

		$weight = self::scalar( $value['fontWeight'] ?? '400' );

		if ( ! in_array( $weight, array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ), true ) ) {
			throw new InvalidValue( __( 'Độ đậm phải từ 100 đến 900.', 'saha-core' ) );
		}

		$line = self::scalar( $value['lineHeight'] ?? '1.5' );

		if ( ! is_numeric( $line ) || (float) $line < 0.8 || (float) $line > 3 ) {
			throw new InvalidValue( __( 'Giãn dòng phải từ 0.8 đến 3.', 'saha-core' ) );
		}

		$spacing = strtolower( trim( self::scalar( $value['letterSpacing'] ?? '0' ) ) );

		if ( '0' !== $spacing ) {
			if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)(px|em)$/', $spacing, $m ) || abs( (float) $m[1] ) > ( 'em' === $m[2] ? 1 : 10 ) ) {
				throw new InvalidValue( __( 'Giãn chữ không hợp lệ (ví dụ: 0.02em hoặc 1px).', 'saha-core' ) );
			}
		}

		return array(
			'fontFamily'    => $family,
			'fontSize'      => $size,
			'fontWeight'    => $weight,
			'lineHeight'    => self::trimNumber( (float) $line ),
			'letterSpacing' => $spacing,
		);
	}

	/**
	 * CSS tuỳ chỉnh: bỏ mọi thứ có thể thoát khỏi thẻ <style> hoặc chạy script.
	 *
	 * @param mixed $value Giá trị thô.
	 */
	public static function css( $value ): string {
		$css = self::scalar( $value );
		$css = wp_strip_all_tags( $css );

		// Không cho đóng thẻ style / mở thẻ HTML, kể cả sau khi strip.
		$css = str_replace( array( '<', '>' ), '', $css );

		// Vectơ chạy mã trong CSS cũ, và @import tải stylesheet ngoài.
		$css = preg_replace( '/expression\s*\(|javascript\s*:|vbscript\s*:|-moz-binding|behavior\s*:/i', '', $css ) ?? '';
		$css = preg_replace( '/@import[^;]*;?/i', '', $css ) ?? '';

		return trim( mb_substr( $css, 0, 50000 ) );
	}

	/**
	 * Ép về chuỗi; mảng/đối tượng thành chuỗi rỗng.
	 *
	 * @param mixed $value Giá trị thô.
	 */
	private static function scalar( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * 24.0 → "24", 1.50 → "1.5".
	 *
	 * @param float $number Số.
	 */
	private static function trimNumber( float $number ): string {
		return rtrim( rtrim( number_format( $number, 4, '.', '' ), '0' ), '.' ) ?: '0';
	}
}
