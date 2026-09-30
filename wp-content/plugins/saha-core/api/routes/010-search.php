<?php
/**
 * Route: GET /saha/v1/search
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\Search;

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/search',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'search', 30, MINUTE_IN_SECONDS ),
			'args'                => array(
				'q'     => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'limit' => array(
					'type'              => 'integer',
					'default'           => 8,
					'minimum'           => 1,
					'maximum'           => Search::MAX_PER_PAGE,
					'sanitize_callback' => 'absint',
				),
				'page'  => array(
					'type'              => 'integer',
					'default'           => 1,
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$term = (string) $request->get_param( 'q' );

				if ( mb_strlen( Search::normalize( $term ) ) < Search::MIN_LENGTH ) {
					return Api::error(
						sprintf(
							/* translators: %d: số ký tự tối thiểu */
							__( 'Từ khoá phải có ít nhất %d ký tự.', 'saha-core' ),
							Search::MIN_LENGTH
						),
						array( 'q' => __( 'Từ khoá quá ngắn.', 'saha-core' ) ),
						400
					);
				}

				$result = Search::search(
					$term,
					(int) $request->get_param( 'page' ),
					(int) $request->get_param( 'limit' )
				);

				return Api::success(
					array(
						'items'    => $result['items'],
						'total'    => $result['total'],
						'page'     => $result['page'],
						'per_page' => $result['per_page'],
						'query'    => $result['query'],
					)
				);
			},
		)
	);
};
