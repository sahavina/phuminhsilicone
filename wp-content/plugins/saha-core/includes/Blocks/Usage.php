<?php
/**
 * Tra cứu nơi dùng block.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Blocks;

use Saha\Core\Builder\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Usage — trang/bài/block nào đang tham chiếu một block.
 *
 * Tìm theo chuỗi `"blockId":{id}` trong JSON layout (LIKE trên postmeta). Chỉ dùng
 * trong admin (danh sách Blocks), không chạy ở frontend.
 */
final class Usage {

	/**
	 * ID post đang dùng block (tối đa 50).
	 *
	 * @param int $block_id Block ID.
	 * @return int[]
	 */
	public static function pagesUsing( int $block_id ): array {
		global $wpdb;

		$needle = '%' . $wpdb->esc_like( '"blockId":' . $block_id ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- chỉ trong admin, không có API meta nào tìm theo nội dung JSON.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.post_type <> 'revision' AND p.post_status <> 'trash'
				LIMIT 50",
				LayoutRepository::META_DATA,
				$needle
			)
		);

		// "blockId":12 cũng khớp "blockId":123 — lọc lại chính xác.
		return array_values(
			array_filter(
				array_map( 'intval', (array) $ids ),
				static function ( int $post_id ) use ( $block_id ): bool {
					$raw = (string) get_post_meta( $post_id, LayoutRepository::META_DATA, true );
					return (bool) preg_match( '/"blockId":' . $block_id . '(?!\d)/', $raw );
				}
			)
		);
	}
}
