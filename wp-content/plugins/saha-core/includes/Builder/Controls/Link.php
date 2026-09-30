<?php
/**
 * Control: đường dẫn.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Link — `{url, newTab, nofollow}`.
 *
 * URL nhận: http(s), mailto:, tel:, đường dẫn tương đối `/…`, neo `#…`.
 * `javascript:` và giao thức lạ bị từ chối (báo lỗi, không lặng lẽ bỏ).
 */
final class Link extends Control {

	public const PROTOCOLS = array( 'http', 'https', 'mailto', 'tel' );

	/**
	 * Type.
	 */
	public function type(): string {
		return 'link';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị: URL hoặc {url, newTab, nofollow}.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi URL không an toàn.
	 */
	public function sanitize( $value, array $def ) {
		$data = is_array( $value ) ? $value : array( 'url' => $value );
		$url  = self::url( $data['url'] ?? '' );

		if ( '' === $url ) {
			return null;
		}

		$out = array( 'url' => $url );

		if ( ! empty( $data['newTab'] ) ) {
			$out['newTab'] = true;
		}

		if ( ! empty( $data['nofollow'] ) ) {
			$out['nofollow'] = true;
		}

		return $out;
	}

	/**
	 * Làm sạch URL.
	 *
	 * @param mixed $value URL.
	 * @throws InvalidValue Khi không an toàn.
	 */
	public static function url( $value ): string {
		$raw = self::scalar( $value );

		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '/^#[A-Za-z][\w\-:.]*$/', $raw ) ) {
			return $raw;
		}

		$clean = esc_url_raw( $raw, self::PROTOCOLS );

		if ( '' === $clean ) {
			throw new InvalidValue( __( 'Đường dẫn không hợp lệ hoặc không an toàn.', 'saha-core' ) );
		}

		return $clean;
	}

	/**
	 * Thuộc tính HTML của thẻ <a>.
	 *
	 * @param array<string, mixed> $link Giá trị đã sanitize.
	 * @return string Chuỗi đã escape, bắt đầu bằng khoảng trắng.
	 */
	public static function attributes( array $link ): string {
		$html = ' href="' . esc_url( (string) ( $link['url'] ?? '' ), self::PROTOCOLS ) . '"';
		$rel  = array();

		if ( ! empty( $link['newTab'] ) ) {
			$html .= ' target="_blank"';
			$rel[] = 'noopener';
		}

		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}

		if ( $rel ) {
			$html .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}

		return $html;
	}
}
