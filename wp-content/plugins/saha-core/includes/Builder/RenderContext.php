<?php
/**
 * Ngữ cảnh của một lần render.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * RenderContext.
 */
final class RenderContext {

	/**
	 * Handle script/style mà element trên trang cần (AssetManager nạp — mốc 1.4).
	 *
	 * @var array{script: string[], style: string[]}
	 */
	public array $assets = array(
		'script' => array(),
		'style'  => array(),
	);

	/**
	 * Chuỗi block đang render (chống vòng lặp block — mốc 1.4).
	 *
	 * @var int[]
	 */
	public array $blockStack = array();

	/**
	 * Độ sâu node đang render (0 = cấp gốc).
	 *
	 * @var int
	 */
	public int $depth = 0;

	/**
	 * Tạo ngữ cảnh.
	 *
	 * @param int  $postId   Post chứa layout (0 nếu không có — ví dụ render thử).
	 * @param bool $editor   Render cho canvas editor: thêm data-saha-id, không cache.
	 * @param bool $useCache Cho phép render cache.
	 */
	public function __construct(
		public readonly int $postId = 0,
		public readonly bool $editor = false,
		public readonly bool $useCache = true
	) {}

	/**
	 * Ghi nhận asset element cần.
	 *
	 * @param array<string, string[]> $assets ['script' => [...], 'style' => [...]].
	 */
	public function addAssets( array $assets ): void {
		foreach ( array( 'script', 'style' ) as $kind ) {
			foreach ( (array) ( $assets[ $kind ] ?? array() ) as $handle ) {
				if ( is_string( $handle ) && ! in_array( $handle, $this->assets[ $kind ], true ) ) {
					$this->assets[ $kind ][] = $handle;
				}
			}
		}
	}
}
