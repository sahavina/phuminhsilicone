<?php
/**
 * Route: GET /saha/v1/products, GET /saha/v1/products/{id}
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\Product;
use Saha\Core\Taxonomies;

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/products',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'products', 60, MINUTE_IN_SECONDS ),
			'args'                => array_merge(
				Api::pagination_args( 50 ),
				array(
					'brand'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_title',
					),
					'category' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_title',
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				if ( ! post_type_exists( 'product' ) ) {
					return Api::error( __( 'WooCommerce chưa được kích hoạt.', 'saha-core' ), array(), 503 );
				}

				$paging = Api::pagination( $request, 50 );

				$args = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => $paging['per_page'],
					'paged'          => $paging['page'],
					'fields'         => 'ids',
				);

				$search = (string) $request->get_param( 'search' );

				if ( '' !== $search ) {
					$args['s'] = $search;
				}

				$tax_query = array();

				$brand = (string) $request->get_param( 'brand' );

				if ( '' !== $brand && taxonomy_exists( Taxonomies::BRAND ) ) {
					$tax_query[] = array(
						'taxonomy' => Taxonomies::BRAND,
						'field'    => 'slug',
						'terms'    => $brand,
					);
				}

				$category = (string) $request->get_param( 'category' );

				if ( '' !== $category && taxonomy_exists( 'product_cat' ) ) {
					$tax_query[] = array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => $category,
					);
				}

				if ( $tax_query ) {
					if ( count( $tax_query ) > 1 ) {
						$tax_query['relation'] = 'AND';
					}

					$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- có pagination.
				}

				$query = new WP_Query( $args );
				$ids   = array_map( 'absint', $query->posts );

				if ( $ids ) {
					_prime_post_caches( $ids, true, true );
				}

				$items = array();

				foreach ( $ids as $id ) {
					$card = Product::get_card_data( $id );

					if ( $card ) {
						$items[] = $card;
					}
				}

				return Api::success(
					array(
						'items'       => $items,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
						'page'        => $paging['page'],
						'per_page'    => $paging['per_page'],
					)
				);
			},
		)
	);

	register_rest_route(
		$namespace,
		'/products/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'products', 60, MINUTE_IN_SECONDS ),
			'args'                => array(
				'id' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$id = absint( $request->get_param( 'id' ) );

				if ( $id <= 0 || 'product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
					return Api::error( __( 'Không tìm thấy sản phẩm.', 'saha-core' ), array(), 404 );
				}

				$card = Product::get_card_data( $id );

				if ( ! $card ) {
					return Api::error( __( 'Không tìm thấy sản phẩm.', 'saha-core' ), array(), 404 );
				}

				$data = array_merge(
					$card,
					array(
						'description'  => get_the_excerpt( $id ),
						'specs'        => Product::get_specs( $id ),
						'documents'    => Product::get_documents( $id ),
						'product_line' => (string) Product::get_meta( $id, 'product_line' ),
					)
				);

				return Api::success( $data );
			},
		)
	);
};
