<?php
/**
 * Control: chọn một term (danh mục, thương hiệu…).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Term — lưu slug (ổn định khi xuất/nhập giữa các site, không như ID).
 * `taxonomy` bắt buộc trong định nghĩa.
 */
final class Term extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'term';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi term không tồn tại.
	 */
	public function sanitize( $value, array $def ) {
		$slug     = sanitize_title( self::scalar( $value ) );
		$taxonomy = (string) ( $def['taxonomy'] ?? '' );

		if ( '' === $slug ) {
			return null;
		}

		if ( ! taxonomy_exists( $taxonomy ) || ! term_exists( $slug, $taxonomy ) ) {
			throw new InvalidValue( __( 'Mục đã chọn không còn tồn tại.', 'saha-core' ) );
		}

		return $slug;
	}

	/**
	 * Danh sách term cho editor (tối đa 500 — đủ cho catalogue; nhiều hơn cần ô tìm kiếm).
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$taxonomy = (string) ( $def['taxonomy'] ?? '' );
		$options  = array();

		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 500,
					'orderby'    => 'name',
				)
			);

			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( $term instanceof \WP_Term ) {
					$options[] = array(
						'value' => $term->slug,
						'label' => $term->parent ? '— ' . $term->name : $term->name,
					);
				}
			}
		}

		$def['options'] = $options;

		return $def;
	}
}
