<?php
/**
 * Control: kiểu chữ.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;
use Saha\Core\Builder\Responsive;
use Saha\Core\ThemeOptions\Schema as ThemeSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Typography — `{fontFamily, fontSize, fontWeight, lineHeight, letterSpacing, textTransform, fontStyle}`.
 *
 * fontSize, lineHeight, letterSpacing tự nhận giá trị responsive (control này
 * không khai báo `responsive` ở cấp ngoài). fontFamily: rỗng = kế thừa,
 * `body`/`heading` = font của Theme Options, hoặc key font stack.
 */
final class Typography extends Control {

	public const WEIGHTS         = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );
	public const TRANSFORMS      = array( 'none', 'uppercase', 'lowercase', 'capitalize' );
	public const STYLES          = array( 'normal', 'italic' );
	public const THEME_FAMILIES  = array( 'body', 'heading' );

	/**
	 * Type.
	 */
	public function type(): string {
		return 'typography';
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

		$out = array();

		$family = self::scalar( $value['fontFamily'] ?? '' );
		if ( '' !== $family ) {
			if ( ! in_array( $family, self::families(), true ) ) {
				throw new InvalidValue( __( 'Font không hợp lệ.', 'saha-core' ) );
			}
			$out['fontFamily'] = $family;
		}

		$sizes = array(
			'fontSize'      => array(
				'units' => array( 'px', 'rem', 'em', 'vw' ),
				'min'   => 6,
				'max'   => 200,
				'label' => __( 'Cỡ chữ', 'saha-core' ),
			),
			'letterSpacing' => array(
				'units' => array( 'px', 'em' ),
				'min'   => -10,
				'max'   => 50,
				'label' => __( 'Giãn chữ', 'saha-core' ),
			),
		);

		foreach ( $sizes as $key => $rule ) {
			try {
				$clean = Responsive::map( $value[ $key ] ?? null, static fn( $v ) => Size::clean( $v, $rule ) );
			} catch ( InvalidValue $e ) {
				throw new InvalidValue( $rule['label'] . ' — ' . $e->getMessage() );
			}
			if ( null !== $clean ) {
				$out[ $key ] = $clean;
			}
		}

		try {
			$line = Responsive::map( $value['lineHeight'] ?? null, array( self::class, 'lineHeight' ) );
		} catch ( InvalidValue $e ) {
			throw new InvalidValue( __( 'Giãn dòng', 'saha-core' ) . ' — ' . $e->getMessage() );
		}
		if ( null !== $line ) {
			$out['lineHeight'] = $line;
		}

		$enums = array(
			'fontWeight'    => self::WEIGHTS,
			'textTransform' => self::TRANSFORMS,
			'fontStyle'     => self::STYLES,
		);

		foreach ( $enums as $key => $allowed ) {
			$raw = self::scalar( $value[ $key ] ?? '' );

			if ( '' === $raw ) {
				continue;
			}

			if ( ! in_array( $raw, $allowed, true ) ) {
				throw new InvalidValue( __( 'Giá trị kiểu chữ không hợp lệ.', 'saha-core' ) );
			}

			$out[ $key ] = $raw;
		}

		return $out ? $out : null;
	}

	/**
	 * Giãn dòng: số không đơn vị 0.5–4, hoặc px/em.
	 *
	 * @param mixed $value Giá trị.
	 * @throws InvalidValue Khi không hợp lệ.
	 */
	public static function lineHeight( $value ): ?string {
		$raw = strtolower( self::scalar( $value ) );

		if ( '' === $raw ) {
			return null;
		}

		if ( is_numeric( $raw ) ) {
			$number = (float) $raw;

			if ( $number < 0.5 || $number > 4 ) {
				throw new InvalidValue( __( 'Giá trị phải trong khoảng 0.5–4.', 'saha-core' ) );
			}

			return rtrim( rtrim( number_format( $number, 3, '.', '' ), '0' ), '.' );
		}

		return Size::clean(
			$raw,
			array(
				'units' => array( 'px', 'em', 'rem' ),
				'min'   => 0,
				'max'   => 300,
			)
		);
	}

	/**
	 * Giá trị fontFamily hợp lệ.
	 *
	 * @return string[]
	 */
	public static function families(): array {
		return array_merge( self::THEME_FAMILIES, array_keys( ThemeSchema::fontStacks() ) );
	}

	/**
	 * Giá trị CSS của một fontFamily.
	 *
	 * @param string $family Key.
	 */
	public static function familyCss( string $family ): string {
		if ( in_array( $family, self::THEME_FAMILIES, true ) ) {
			return 'var(--saha-type-' . $family . '-font)';
		}

		return (string) ( ThemeSchema::fontStacks()[ $family ]['stack'] ?? '' );
	}

	/**
	 * Gửi danh sách font cho editor.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$fonts = array(
			array(
				'value' => '',
				'label' => __( 'Kế thừa', 'saha-core' ),
			),
			array(
				'value' => 'body',
				'label' => __( 'Font nội dung (Theme Options)', 'saha-core' ),
			),
			array(
				'value' => 'heading',
				'label' => __( 'Font tiêu đề (Theme Options)', 'saha-core' ),
			),
		);

		foreach ( ThemeSchema::fontStacks() as $key => $font ) {
			$fonts[] = array(
				'value' => (string) $key,
				'label' => (string) $font['label'],
			);
		}

		$def['fonts']      = $fonts;
		$def['weights']    = self::WEIGHTS;
		$def['transforms'] = self::TRANSFORMS;

		return $def;
	}
}
