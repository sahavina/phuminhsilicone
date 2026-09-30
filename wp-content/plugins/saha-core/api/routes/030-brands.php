<?php
/**
 * Route: GET /saha/v1/brands, GET /saha/v1/brands/{slug}
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\Brand;
use Saha\Core\Taxonomies;

if ( ! function_exists( 'saha_api_prepare_brand' ) ) {
	/**
	 * Chuẩn hoá dữ liệu thương hiệu cho API: thay attachment ID bằng URL.
	 *
	 * @param array<string, mixed> $brand Dữ liệu thương hiệu.
	 * @return array<string, mixed>
	 */
	function saha_api_prepare_brand( array $brand ): array {
		$logo_id   = absint( $brand['logo_id'] ?? 0 );
		$banner_id = absint( $brand['banner_id'] ?? 0 );

		unset( $brand['logo_id'], $brand['banner_id'] );

		$brand['logo']   = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'saha-brand-logo' ) : '';
		$brand['banner'] = $banner_id > 0 ? (string) wp_get_attachment_image_url( $banner_id, 'full' ) : '';

		return $brand;
	}
}

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/brands',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'brands', 60, MINUTE_IN_SECONDS ),
			'args'                => array(
				'number'     => array(
					'type'              => 'integer',
					'default'           => 0,
					'minimum'           => 0,
					'maximum'           => 200,
					'sanitize_callback' => 'absint',
				),
				'hide_empty' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				if ( ! taxonomy_exists( Taxonomies::BRAND ) ) {
					return Api::error( __( 'Taxonomy thương hiệu chưa sẵn sàng.', 'saha-core' ), array(), 503 );
				}

				$brands = Brand::get_all(
					array(
						'number'     => (int) $request->get_param( 'number' ),
						'hide_empty' => (bool) $request->get_param( 'hide_empty' ),
					)
				);

				return Api::success(
					array(
						'items' => array_map( 'saha_api_prepare_brand', $brands ),
						'total' => count( $brands ),
					)
				);
			},
		)
	);

	register_rest_route(
		$namespace,
		'/brands/(?P<slug>[a-z0-9\-_]+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'brands', 60, MINUTE_IN_SECONDS ),
			'args'                => array(
				'slug' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_title',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$brand = Brand::get( (string) $request->get_param( 'slug' ) );

				if ( ! $brand ) {
					return Api::error( __( 'Không tìm thấy thương hiệu.', 'saha-core' ), array(), 404 );
				}

				return Api::success( saha_api_prepare_brand( $brand ) );
			},
		)
	);
};
