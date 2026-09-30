<?php
/**
 * Control: HTML tuỳ ý.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

defined( 'ABSPATH' ) || exit;

/**
 * Html — giữ nguyên HTML (kể cả script, iframe) CHỈ khi người lưu có quyền
 * `unfiltered_html` (admin site đơn; super admin trên multisite) — cùng quy tắc
 * với khối "HTML tuỳ chỉnh" của WordPress. Người khác → `wp_kses_post` (rủi ro R5).
 *
 * Quyền xét tại thời điểm LƯU: editor không có unfiltered_html lưu lại một
 * trang có script của admin thì script bị lọc (an toàn, nhưng mất đoạn script).
 */
final class Html extends Control {

	public const MAX_LENGTH = 100000;

	/**
	 * Type.
	 */
	public function type(): string {
		return 'html';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 */
	public function sanitize( $value, array $def ) {
		$html = is_scalar( $value ) ? (string) $value : '';
		$html = trim( mb_substr( $html, 0, self::MAX_LENGTH ) );

		if ( '' === $html ) {
			return null;
		}

		return current_user_can( 'unfiltered_html' ) ? $html : trim( wp_kses_post( $html ) );
	}

	/**
	 * Editor cần biết HTML có bị lọc không để cảnh báo.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$def['unfiltered'] = current_user_can( 'unfiltered_html' );

		return $def;
	}
}
