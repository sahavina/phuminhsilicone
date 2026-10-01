<?php
/**
 * Module Templates (header/footer).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		( new PostType() )->register();
		( new Frontend() )->register();
		( new Loader() )->register();
		( new Preview() )->register();

		if ( is_admin() ) {
			( new AdminScreen() )->register();
			( new ConditionsScreen() )->register();
		}
	}
}
