<?php
/**
 * Control: khoảng cách 4 cạnh (margin/padding).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Spacing — `{top, right, bottom, left}`, mỗi cạnh là Size (có thể bỏ trống).
 *
 * Tuỳ chọn giống Size; margin thường đặt `allowAuto` và `min` âm.
 */
final class Spacing extends Control {

	public const SIDES = array( 'top', 'right', 'bottom', 'left' );

	/**
	 * Type.
	 */
	public function type(): string {
		return 'spacing';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi một cạnh sai.
	 */
	public function sanitize( $value, array $def ) {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$def += array(
			'units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh' ),
			'min'   => 0,
			'max'   => 1000,
		);
		$out  = array();

		foreach ( self::SIDES as $side ) {
			try {
				$clean = Size::clean( $value[ $side ] ?? null, $def );
			} catch ( InvalidValue $e ) {
				throw new InvalidValue( self::sideLabel( $side ) . ': ' . $e->getMessage() );
			}

			if ( null !== $clean ) {
				$out[ $side ] = $clean;
			}
		}

		return $out ? $out : null;
	}

	/**
	 * Tên cạnh.
	 *
	 * @param string $side Cạnh.
	 */
	private static function sideLabel( string $side ): string {
		$labels = array(
			'top'    => __( 'Trên', 'saha-core' ),
			'right'  => __( 'Phải', 'saha-core' ),
			'bottom' => __( 'Dưới', 'saha-core' ),
			'left'   => __( 'Trái', 'saha-core' ),
		);

		return $labels[ $side ];
	}
}
