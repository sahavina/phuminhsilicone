<?php
/**
 * Module Builder runtime.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Blocks\PostType as BlockPostType;
use Saha\Core\Blocks\Rest\BlocksController;
use Saha\Core\Builder\Rest\BuilderController;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 *
 * Runtime (schema, sanitize, render, CSS, REST) nằm trong saha-core để trang đã
 * dựng vẫn hiển thị khi tắt saha-builder (ứng dụng soạn thảo).
 */
final class Module {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'init', array( LayoutRepository::class, 'registerMeta' ), 20 );
		add_action( 'rest_api_init', array( new BuilderController(), 'registerRoutes' ) );
		add_action( 'rest_api_init', array( new BlocksController(), 'registerRoutes' ) );
		( new BlockPostType() )->register();
		( new Frontend() )->register();
		( new Starter() )->register();
		( new StoreKit() )->register();
	}
}
