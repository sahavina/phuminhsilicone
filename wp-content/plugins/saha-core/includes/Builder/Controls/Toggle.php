<?php
/**
 * Control: bật/tắt.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

defined( 'ABSPATH' ) || exit;

/**
 * Toggle.
 */
final class Toggle extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'toggle';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 */
	public function sanitize( $value, array $def ) {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}
}
