<?php
/**
 * Control: nền.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Background — `{color, image{id,size}, position, size, repeat, attachment, overlay{color, opacity}}`.
 */
final class Background extends Control {

	public const POSITIONS   = array( 'center center', 'center top', 'center bottom', 'left top', 'left center', 'left bottom', 'right top', 'right center', 'right bottom' );
	public const SIZES       = array( 'cover', 'contain', 'auto' );
	public const REPEATS     = array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' );
	public const ATTACHMENTS = array( 'scroll', 'fixed' );

	/**
	 * Type.
	 */
	public function type(): string {
		return 'background';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi một thuộc tính sai.
	 */
	public function sanitize( $value, array $def ) {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$out   = array();
		$color = new Color();
		$media = new Media();

		$bg = $color->sanitize( $value['color'] ?? null, array() );
		if ( null !== $bg ) {
			$out['color'] = $bg;
		}

		$image = $media->sanitize( $value['image'] ?? null, array( 'defaultSize' => 'full' ) );
		if ( null !== $image ) {
			$out['image'] = $image;

			$enums = array(
				'position'   => self::POSITIONS,
				'size'       => self::SIZES,
				'repeat'     => self::REPEATS,
				'attachment' => self::ATTACHMENTS,
			);

			foreach ( $enums as $key => $allowed ) {
				$raw = self::scalar( $value[ $key ] ?? '' );

				if ( '' === $raw ) {
					continue;
				}

				if ( ! in_array( $raw, $allowed, true ) ) {
					throw new InvalidValue( __( 'Thiết lập ảnh nền không hợp lệ.', 'saha-core' ) );
				}

				$out[ $key ] = $raw;
			}
		}

		if ( is_array( $value['overlay'] ?? null ) ) {
			$overlay = $color->sanitize( $value['overlay']['color'] ?? null, array() );

			if ( null !== $overlay ) {
				$opacity = $value['overlay']['opacity'] ?? 0.5;

				if ( ! is_numeric( $opacity ) || $opacity < 0 || $opacity > 1 ) {
					throw new InvalidValue( __( 'Độ mờ lớp phủ phải trong khoảng 0–1.', 'saha-core' ) );
				}

				$out['overlay'] = array(
					'color'   => $overlay,
					'opacity' => round( (float) $opacity, 2 ),
				);
			}
		}

		return $out ? $out : null;
	}

	/**
	 * Editor cần danh sách lựa chọn.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$def['positions']   = self::POSITIONS;
		$def['sizes']       = self::SIZES;
		$def['repeats']     = self::REPEATS;
		$def['attachments'] = self::ATTACHMENTS;
		$def['imageSizes']  = Media::sizes();

		return $def;
	}
}
