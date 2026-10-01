<?php
/**
 * Thiết lập mega menu của một mục menu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\MegaMenu;

use Saha\Core\Blocks\PostType as BlockPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Settings — đọc/ghi meta mục menu (TECHNICAL-DESIGN §5.2):
 *
 * - `_saha_menu_type`: `normal` | `mega`
 * - `_saha_mega_settings`: JSON `{width: container|full|custom, customWidth, columns, blockId}`
 *
 * Option `saha_mega_menu` = `{active: bool, blocks: int[]}` biên dịch khi lưu menu, để
 * frontend biết có cần nạp CSS mega và CSS của block nào mà không phải đọc mọi mục menu.
 */
final class Settings {

	public const TYPE_META     = '_saha_menu_type';
	public const SETTINGS_META = '_saha_mega_settings';
	public const OPTION        = 'saha_mega_menu';
	public const WIDTHS        = array( 'container', 'full', 'custom' );

	/**
	 * Mặc định.
	 *
	 * @return array{width: string, customWidth: int, columns: int, blockId: int}
	 */
	public static function defaults(): array {
		return array(
			'width'       => 'container',
			'customWidth' => 800,
			'columns'     => 4,
			'blockId'     => 0,
		);
	}

	/**
	 * Mục menu có bật mega không.
	 *
	 * @param int $item_id ID mục menu.
	 */
	public static function isMega( int $item_id ): bool {
		return 'mega' === get_post_meta( $item_id, self::TYPE_META, true );
	}

	/**
	 * Thiết lập đã chuẩn hoá.
	 *
	 * @param int $item_id ID mục menu.
	 * @return array{width: string, customWidth: int, columns: int, blockId: int}
	 */
	public static function get( int $item_id ): array {
		$raw = json_decode( (string) get_post_meta( $item_id, self::SETTINGS_META, true ), true );

		return self::sanitize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Chuẩn hoá dữ liệu (từ form admin hoặc meta).
	 *
	 * @param array<string, mixed> $input Dữ liệu thô.
	 * @return array{width: string, customWidth: int, columns: int, blockId: int}
	 */
	public static function sanitize( array $input ): array {
		$out   = self::defaults();
		$width = sanitize_key( (string) ( $input['width'] ?? '' ) );

		if ( in_array( $width, self::WIDTHS, true ) ) {
			$out['width'] = $width;
		}

		if ( isset( $input['customWidth'] ) ) {
			$out['customWidth'] = max( 300, min( 1600, absint( $input['customWidth'] ) ) );
		}

		if ( isset( $input['columns'] ) ) {
			$out['columns'] = max( 1, min( 6, absint( $input['columns'] ) ) );
		}

		$block = absint( $input['blockId'] ?? 0 );

		if ( $block > 0 && BlockPostType::NAME === get_post_type( $block ) ) {
			$out['blockId'] = $block;
		}

		return $out;
	}

	/**
	 * Lưu.
	 *
	 * @param int                  $item_id  ID mục menu.
	 * @param string               $type     normal | mega.
	 * @param array<string, mixed> $settings Thiết lập thô.
	 */
	public static function save( int $item_id, string $type, array $settings ): void {
		if ( 'mega' !== $type ) {
			delete_post_meta( $item_id, self::TYPE_META );
			delete_post_meta( $item_id, self::SETTINGS_META );
			return;
		}

		update_post_meta( $item_id, self::TYPE_META, 'mega' );
		update_post_meta( $item_id, self::SETTINGS_META, (string) wp_json_encode( self::sanitize( $settings ) ) );
	}

	/**
	 * Biên dịch option tổng hợp (sau khi lưu menu / xoá block).
	 */
	public static function compile(): void {
		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::TYPE_META, // phpcs:ignore WordPress.DB.SlowDBQuery -- chỉ chạy khi lưu menu.
				'meta_value'     => 'mega', // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'  => true,
			)
		);

		$blocks = array();

		foreach ( $items as $item_id ) {
			$block = self::get( (int) $item_id )['blockId'];

			if ( $block > 0 ) {
				$blocks[] = $block;
			}
		}

		update_option(
			self::OPTION,
			array(
				'active' => ! empty( $items ),
				'blocks' => array_values( array_unique( $blocks ) ),
			),
			true
		);
	}

	/**
	 * Option đã biên dịch.
	 *
	 * @return array{active: bool, blocks: int[]}
	 */
	public static function compiled(): array {
		$value = get_option( self::OPTION, array() );
		$value = is_array( $value ) ? $value : array();

		return array(
			'active' => ! empty( $value['active'] ),
			'blocks' => array_map( 'intval', (array) ( $value['blocks'] ?? array() ) ),
		);
	}
}
