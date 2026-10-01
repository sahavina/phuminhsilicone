<?php
/**
 * Điều kiện hiển thị template: rule, độ cụ thể, chỉ mục, chọn template.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Conditions (TECHNICAL-DESIGN §5.5).
 *
 * Điều kiện của một template: `{include: Rule[], exclude: Rule[]}`, Rule = `{rule, value?: (int|string)[]}`.
 * Chỉ mục (option `saha_template_map`):
 *
 * ```
 * { v: 2,
 *   types:    { type: { rule: { value: [templateId…] } } },   // value "*" khi rule không có giá trị
 *   priority: { templateId: int },
 *   exclude:  { templateId: ["rule:value", …] } }
 * ```
 *
 * Một request được mô tả bằng danh sách "khớp" `[rule, value, specificity]` (RequestContext).
 * Chọn template: duyệt từ độ cụ thể cao xuống; ở mức đầu tiên có ứng viên (không bị loại trừ)
 * → ưu tiên cao hơn → ID nhỏ hơn. Không cần WordPress — test được trong tests/smoke.php.
 */
final class Conditions {

	public const VERSION = 2;

	/** Độ cụ thể (spec §54). */
	public const OBJECT   = 30;
	public const TERM     = 20;
	public const PARENT   = 15;
	public const ARCHIVE  = 10;
	public const ALL      = 0;
	public const ANY      = '*';

	/**
	 * Rule: độ cụ thể + loại giá trị (`none`, `post`, `term`, `enum`).
	 *
	 * @return array<string, array{specificity: int, value: string, source?: string}>
	 */
	public static function rules(): array {
		return array(
			'all'           => array( 'specificity' => self::ALL, 'value' => 'none' ),
			'front_page'    => array( 'specificity' => self::OBJECT, 'value' => 'none' ),
			'page'          => array( 'specificity' => self::OBJECT, 'value' => 'post', 'source' => 'page' ),
			'post'          => array( 'specificity' => self::OBJECT, 'value' => 'post', 'source' => 'post' ),
			'product'       => array( 'specificity' => self::OBJECT, 'value' => 'post', 'source' => 'product' ),
			'category'      => array( 'specificity' => self::TERM, 'value' => 'term', 'source' => 'category' ),
			'post_tag'      => array( 'specificity' => self::TERM, 'value' => 'term', 'source' => 'post_tag' ),
			'product_cat'   => array( 'specificity' => self::TERM, 'value' => 'term', 'source' => 'product_cat' ),
			'product_brand' => array( 'specificity' => self::TERM, 'value' => 'term', 'source' => 'product_brand' ),
			'archive_type'  => array( 'specificity' => self::ARCHIVE, 'value' => 'enum' ),
			'search'        => array( 'specificity' => self::ARCHIVE, 'value' => 'none' ),
			'404'           => array( 'specificity' => self::ARCHIVE, 'value' => 'none' ),
		);
	}

	/**
	 * Giá trị của rule `archive_type`.
	 *
	 * @return string[]
	 */
	public static function archiveTypes(): array {
		return array( 'shop', 'product_taxonomy', 'blog', 'post_taxonomy', 'author', 'date' );
	}

	/**
	 * Rule dùng được cho từng loại template (giao diện chỉ hiện những rule này).
	 *
	 * @return array<string, string[]>
	 */
	public static function rulesByType(): array {
		$site = array( 'all', 'front_page', 'page', 'post', 'product', 'category', 'product_cat', 'product_brand', 'archive_type', 'search', '404' );

		return array(
			'header'          => $site,
			'footer'          => $site,
			'single_product'  => array( 'all', 'product', 'product_cat', 'product_brand' ),
			'product_archive' => array( 'all', 'product_cat', 'product_brand', 'archive_type' ),
			'single_post'     => array( 'all', 'post', 'category', 'post_tag' ),
			'archive'         => array( 'all', 'category', 'post_tag', 'archive_type' ),
			'page'            => array( 'all', 'front_page', 'page' ),
			'search'          => array( 'all' ),
			'404'             => array( 'all' ),
		);
	}

	/**
	 * Chuẩn hoá điều kiện cho một loại template (bỏ rule lạ, giá trị sai kiểu, trùng lặp).
	 *
	 * @param mixed  $input Điều kiện thô.
	 * @param string $type  Loại template.
	 * @return array{include: array<int, array{rule: string, value?: array<int, int|string>}>, exclude: array<int, array{rule: string, value?: array<int, int|string>}>}
	 */
	public static function sanitize( $input, string $type ): array {
		$input   = is_array( $input ) ? $input : array();
		$allowed = self::rulesByType()[ $type ] ?? array( 'all' );
		$out     = array(
			'include' => array(),
			'exclude' => array(),
		);

		foreach ( array( 'include', 'exclude' ) as $group ) {
			$seen = array();

			foreach ( (array) ( $input[ $group ] ?? array() ) as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$name = strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) ( $rule['rule'] ?? '' ) ) );

				if ( ! in_array( $name, $allowed, true ) || ( 'exclude' === $group && 'all' === $name ) ) {
					continue;
				}

				$kind  = self::rules()[ $name ]['value'];
				$entry = array( 'rule' => $name );

				if ( 'none' !== $kind ) {
					$values = array();

					foreach ( (array) ( $rule['value'] ?? array() ) as $value ) {
						if ( 'enum' === $kind ) {
							$value = (string) $value;
							if ( in_array( $value, self::archiveTypes(), true ) ) {
								$values[] = $value;
							}
						} elseif ( is_numeric( $value ) && (int) $value > 0 ) {
							$values[] = (int) $value;
						}
					}

					$values = array_values( array_unique( $values ) );

					if ( ! $values ) {
						continue;
					}

					sort( $values );
					$entry['value'] = $values;
				}

				$key = (string) json_encode( $entry );

				if ( ! isset( $seen[ $key ] ) ) {
					$seen[ $key ]    = true;
					$out[ $group ][] = $entry;
				}
			}
		}

		return $out;
	}

	/**
	 * Cặp "rule:value" của một rule (value "*" khi không có giá trị).
	 *
	 * @param array<string, mixed> $rule Rule đã chuẩn hoá.
	 * @return string[]
	 */
	public static function keys( array $rule ): array {
		$name = (string) ( $rule['rule'] ?? '' );

		if ( empty( $rule['value'] ) ) {
			return array( $name . ':' . self::ANY );
		}

		return array_map( static fn( $value ): string => $name . ':' . $value, (array) $rule['value'] );
	}

	/**
	 * Biên dịch chỉ mục từ danh sách template đã xuất bản.
	 *
	 * @param array<int, array{id: int, type: string, priority: int, conditions: array<string, mixed>}> $templates Template.
	 * @return array<string, mixed>
	 */
	public static function compile( array $templates ): array {
		$map = array(
			'v'        => self::VERSION,
			'types'    => array(),
			'priority' => array(),
			'exclude'  => array(),
		);

		foreach ( $templates as $template ) {
			$id                     = (int) $template['id'];
			$map['priority'][ $id ] = (int) $template['priority'];

			foreach ( (array) ( $template['conditions']['include'] ?? array() ) as $rule ) {
				foreach ( self::keys( (array) $rule ) as $key ) {
					list( $name, $value )                                  = explode( ':', $key, 2 );
					$map['types'][ $template['type'] ][ $name ][ $value ][] = $id;
				}
			}

			$exclude = array();
			foreach ( (array) ( $template['conditions']['exclude'] ?? array() ) as $rule ) {
				$exclude = array_merge( $exclude, self::keys( (array) $rule ) );
			}

			if ( $exclude ) {
				$map['exclude'][ $id ] = array_values( array_unique( $exclude ) );
			}
		}

		foreach ( $map['types'] as $type => $rules ) {
			foreach ( $rules as $name => $values ) {
				foreach ( $values as $value => $ids ) {
					$map['types'][ $type ][ $name ][ $value ] = self::order( array_values( array_unique( $ids ) ), $map['priority'] );
				}
			}
		}

		return $map;
	}

	/**
	 * Ưu tiên cao trước, cùng ưu tiên thì ID nhỏ trước.
	 *
	 * @param int[]            $ids      ID.
	 * @param array<int, int>  $priority Ưu tiên.
	 * @return int[]
	 */
	private static function order( array $ids, array $priority ): array {
		usort( $ids, static fn( int $a, int $b ): int => array( $priority[ $b ] ?? 0, $a ) <=> array( $priority[ $a ] ?? 0, $b ) );

		return $ids;
	}

	/**
	 * Chọn template cho request.
	 *
	 * @param array<string, mixed>                          $map     Chỉ mục đã biên dịch.
	 * @param string                                        $type    Loại template.
	 * @param array<int, array{0: string, 1: string, 2: int}> $context Danh sách khớp của request.
	 */
	public static function resolve( array $map, string $type, array $context ): ?int {
		$index = $map['types'][ $type ] ?? array();

		if ( ! $index ) {
			return null;
		}

		$present = array();
		foreach ( $context as $match ) {
			$present[ $match[0] . ':' . $match[1] ] = true;
		}

		usort( $context, static fn( array $a, array $b ): int => $b[2] <=> $a[2] );

		$level      = null;
		$candidates = array();

		foreach ( $context as $match ) {
			if ( null !== $level && $match[2] < $level ) {
				break;
			}

			foreach ( $index[ $match[0] ][ (string) $match[1] ] ?? array() as $id ) {
				$excluded = false;

				foreach ( $map['exclude'][ $id ] ?? array() as $key ) {
					if ( isset( $present[ $key ] ) ) {
						$excluded = true;
						break;
					}
				}

				if ( ! $excluded ) {
					$candidates[] = (int) $id;
					$level        = $match[2];
				}
			}
		}

		if ( ! $candidates ) {
			return null;
		}

		return self::order( array_values( array_unique( $candidates ) ), (array) ( $map['priority'] ?? array() ) )[0];
	}
}
