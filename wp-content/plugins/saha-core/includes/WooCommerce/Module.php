<?php
/**
 * Module WooCommerce của SCC.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Module — chỉ chạy khi WooCommerce hoạt động.
 */
final class Module {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		( new CatalogMode() )->register();
		( new BuyNow() )->register();
		( new Swatches() )->register();
		( new StickyCart() )->register();
		( new QuickView() )->register();
	}
}
