<?php
/**
 * Đối tượng xem trước khi dựng template trong builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Preview — trong editor, template "Trang sản phẩm" cần một sản phẩm thật để element
 * động (gallery, giá…) hiện đúng; template danh mục cần một danh sách thật.
 *
 * Meta `_saha_template_preview`: ID bài/sản phẩm (template đơn) hoặc ID term (template
 * danh mục). Không đặt → mới nhất cùng loại.
 */
final class Preview {

	public const META = '_saha_template_preview';

	/**
	 * Gắn hook (canvas của builder).
	 */
	public function register(): void {
		add_filter( 'saha_builder_canvas_body_class', array( $this, 'canvasBody' ), 10, 2 );
		add_filter( 'saha_builder_canvas_classes', array( $this, 'canvasArea' ), 10, 2 );
	}

	/**
	 * Body của canvas: class WooCommerce để CSS của WooCommerce áp như trang thật.
	 *
	 * @param string[] $classes Class.
	 * @param int      $post_id Bài đang dựng.
	 * @return string[]
	 */
	public function canvasBody( $classes, $post_id ): array {
		$classes = (array) $classes;
		$type    = Repository::typeOf( (int) $post_id );

		if ( in_array( $type, array( 'single_product', 'product_archive' ), true ) ) {
			array_push( $classes, 'woocommerce', 'woocommerce-page' );
		}

		if ( 'single_product' === $type ) {
			$classes[] = 'single-product';
		}

		return $classes;
	}

	/**
	 * Vùng canvas: trang sản phẩm là `div.product` như Loader in ở frontend.
	 *
	 * @param string[] $classes Class.
	 * @param int      $post_id Bài đang dựng.
	 * @return string[]
	 */
	public function canvasArea( $classes, $post_id ): array {
		$classes = (array) $classes;

		if ( 'single_product' === Repository::typeOf( (int) $post_id ) ) {
			array_push( $classes, 'product', 'saha-template-product' );
		}

		return $classes;
	}

	/**
	 * Post type của đối tượng theo loại template.
	 *
	 * @return array<string, string>
	 */
	public static function postTypes(): array {
		return array(
			'single_product' => 'product',
			'single_post'    => 'post',
			'page'           => 'page',
		);
	}

	/**
	 * Bài/sản phẩm xem trước của template đơn (0 nếu không có).
	 *
	 * @param int $template_id Template ID.
	 */
	public static function subject( int $template_id ): int {
		$post_type = self::postTypes()[ Repository::typeOf( $template_id ) ] ?? '';

		if ( '' === $post_type ) {
			return 0;
		}

		$chosen = (int) get_post_meta( $template_id, self::META, true );

		if ( $chosen > 0 && get_post_type( $chosen ) === $post_type && 'publish' === get_post_status( $chosen ) ) {
			return $chosen;
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		// Chưa chọn: lấy đối tượng khớp điều kiện của template (sản phẩm thuộc danh mục đã chọn…).
		foreach ( Repository::conditions( $template_id )['include'] as $rule ) {
			$def = Conditions::rules()[ $rule['rule'] ] ?? array();

			if ( empty( $rule['value'] ) ) {
				continue;
			}

			if ( 'post' === ( $def['value'] ?? '' ) ) {
				$args['post__in'] = array_map( 'intval', (array) $rule['value'] );
				break;
			}

			if ( 'term' === ( $def['value'] ?? '' ) && taxonomy_exists( (string) $def['source'] ) ) {
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery -- chỉ trong editor.
					array(
						'taxonomy' => (string) $def['source'],
						'terms'    => array_map( 'intval', (array) $rule['value'] ),
					),
				);
				break;
			}
		}

		$latest = get_posts( $args );

		return (int) ( $latest[0] ?? 0 );
	}

	/**
	 * Truy vấn xem trước cho template danh sách (danh mục, blog, tìm kiếm).
	 *
	 * @param int $template_id Template ID.
	 */
	public static function archiveQuery( int $template_id ): ?\WP_Query {
		$type = Repository::typeOf( $template_id );
		$args = array(
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
		);

		if ( 'product_archive' === $type ) {
			$args['post_type']      = 'product';
			$args['posts_per_page'] = function_exists( 'wc_get_default_products_per_row' ) ? wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() : 12;
			$term                   = (int) get_post_meta( $template_id, self::META, true );

			if ( $term > 0 && term_exists( $term, 'product_cat' ) ) {
				$args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'terms' => $term ) ); // phpcs:ignore WordPress.DB.SlowDBQuery -- chỉ trong editor.
			}
		} elseif ( in_array( $type, array( 'archive', 'search' ), true ) ) {
			$args['post_type']      = 'post';
			$args['posts_per_page'] = (int) get_option( 'posts_per_page', 10 );
		} else {
			return null;
		}

		return new \WP_Query( $args );
	}
}
