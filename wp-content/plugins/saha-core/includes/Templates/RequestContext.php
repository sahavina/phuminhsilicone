<?php
/**
 * Request hiện tại: loại template + danh sách điều kiện khớp.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * RequestContext — đọc conditional tags của WordPress/WooCommerce (sau `wp`).
 */
final class RequestContext {

	/**
	 * Loại template nội dung của request ('' = không loại nào, ví dụ feed).
	 */
	public static function type(): string {
		$wc = function_exists( 'is_woocommerce' );

		if ( is_404() ) {
			return '404';
		}

		if ( $wc && is_singular( 'product' ) ) {
			return 'single_product';
		}

		if ( $wc && ( is_shop() || is_product_taxonomy() ) && ! is_search() ) {
			return 'product_archive';
		}

		if ( is_search() ) {
			return 'search';
		}

		if ( is_singular( 'post' ) ) {
			return 'single_post';
		}

		if ( is_page() ) {
			// Trang WooCommerce (giỏ, thanh toán, tài khoản) giữ khung của theme/WooCommerce.
			if ( $wc && ( is_cart() || is_checkout() || is_account_page() ) ) {
				return '';
			}

			return 'page';
		}

		if ( is_home() || is_archive() ) {
			return 'archive';
		}

		return '';
	}

	/**
	 * Danh sách khớp `[rule, value, specificity]` của request.
	 *
	 * @return array<int, array{0: string, 1: string, 2: int}>
	 */
	public static function matches(): array {
		$out = array( array( 'all', Conditions::ANY, Conditions::ALL ) );
		$wc  = function_exists( 'is_woocommerce' );

		if ( is_front_page() ) {
			$out[] = array( 'front_page', Conditions::ANY, Conditions::OBJECT );
		}

		if ( is_404() ) {
			$out[] = array( '404', Conditions::ANY, Conditions::ARCHIVE );
			return $out;
		}

		if ( is_search() ) {
			$out[] = array( 'search', Conditions::ANY, Conditions::ARCHIVE );
		}

		if ( is_singular() ) {
			$id   = (int) get_queried_object_id();
			$type = (string) get_post_type( $id );

			if ( in_array( $type, array( 'page', 'post', 'product' ), true ) ) {
				$out[] = array( $type, (string) $id, Conditions::OBJECT );
			}

			$taxonomies = array(
				'post'    => array( 'category', 'post_tag' ),
				'product' => array( 'product_cat', 'product_brand' ),
			);

			foreach ( $taxonomies[ $type ] ?? array() as $taxonomy ) {
				$terms = taxonomy_exists( $taxonomy ) ? get_the_terms( $id, $taxonomy ) : array();

				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					$out = array_merge( $out, self::term( $taxonomy, (int) $term->term_id ) );
				}
			}

			return self::unique( $out );
		}

		$object = get_queried_object();

		if ( $object instanceof \WP_Term && in_array( $object->taxonomy, array( 'category', 'post_tag', 'product_cat', 'product_brand' ), true ) ) {
			$out = array_merge( $out, self::term( $object->taxonomy, (int) $object->term_id ) );
		}

		$archive = '';

		if ( $wc && is_shop() ) {
			$archive = 'shop';
		} elseif ( $wc && is_product_taxonomy() ) {
			$archive = 'product_taxonomy';
		} elseif ( is_home() ) {
			$archive = 'blog';
		} elseif ( is_category() || is_tag() ) {
			$archive = 'post_taxonomy';
		} elseif ( is_author() ) {
			$archive = 'author';
		} elseif ( is_date() ) {
			$archive = 'date';
		}

		if ( '' !== $archive ) {
			$out[] = array( 'archive_type', $archive, Conditions::ARCHIVE );
		}

		return self::unique( $out );
	}

	/**
	 * Term + các term cha (cha cụ thể kém hơn: điều kiện "Keo Silicone" áp cho "Silicone Apollo").
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term_id  Term ID.
	 * @return array<int, array{0: string, 1: string, 2: int}>
	 */
	private static function term( string $taxonomy, int $term_id ): array {
		$out = array( array( $taxonomy, (string) $term_id, Conditions::TERM ) );

		foreach ( get_ancestors( $term_id, $taxonomy, 'taxonomy' ) as $parent ) {
			$out[] = array( $taxonomy, (string) $parent, Conditions::PARENT );
		}

		return $out;
	}

	/**
	 * Bỏ trùng (giữ độ cụ thể cao nhất cho cùng rule:value).
	 *
	 * @param array<int, array{0: string, 1: string, 2: int}> $matches Khớp.
	 * @return array<int, array{0: string, 1: string, 2: int}>
	 */
	private static function unique( array $matches ): array {
		$out = array();

		foreach ( $matches as $match ) {
			$key = $match[0] . ':' . $match[1];

			if ( ! isset( $out[ $key ] ) || $out[ $key ][2] < $match[2] ) {
				$out[ $key ] = $match;
			}
		}

		return array_values( $out );
	}
}
