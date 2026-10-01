<?php
/**
 * Module Mega menu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\MegaMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Module — mega menu chạy với mọi theme (filter của walker mặc định).
 */
final class Module {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		( new Frontend() )->register();

		if ( is_admin() ) {
			( new AdminFields() )->register();
		}
	}
}
