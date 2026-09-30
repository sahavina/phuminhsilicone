<?php
/**
 * Control: văn bản có định dạng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

defined( 'ABSPATH' ) || exit;

/**
 * RichText — HTML qua `wp_kses_post` cho MỌI user (kể cả có unfiltered_html).
 *
 * Muốn chèn script/iframe tuỳ ý thì dùng element HTML (mốc 1.4, cần unfiltered_html)
 * — tách riêng để rich text không bao giờ là đường vào của XSS (rủi ro R5).
 */
final class RichText extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'richtext';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 */
	public function sanitize( $value, array $def ) {
		$html = is_scalar( $value ) ? (string) $value : '';
		$html = mb_substr( $html, 0, (int) ( $def['maxLength'] ?? 50000 ) );

		return trim( wp_kses_post( $html ) );
	}
}
