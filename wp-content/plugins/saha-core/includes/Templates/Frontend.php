<?php
/**
 * Hiển thị header/footer dựng bằng builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

use Saha\Core\Builder\Elements\Cart;
use Saha\Core\Builder\Frontend as BuilderFrontend;
use Saha\Core\Builder\LayoutRepository;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend.
 *
 * Theme gọi filter `saha_render_header` / `saha_render_footer` (saha-theme,
 * inc/template-functions.php): có template đang dùng → in ra và trả true; không có
 * → theme dùng header/footer PHP mặc định (không bao giờ trang trắng phần đầu).
 */
final class Frontend {

	public const SCRIPT = 'saha-builder-header';

	/**
	 * Template đã resolve trong request.
	 *
	 * @var array<string, int|null>
	 */
	private array $resolved = array();

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'saha_render_header', array( $this, 'renderHeader' ) );
		add_filter( 'saha_render_footer', array( $this, 'renderFooter' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'bodyClass' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cartFragment' ) );
	}

	/**
	 * Template đang dùng (có cache trong request).
	 *
	 * @param string $type header | footer.
	 */
	private function template( string $type ): ?int {
		if ( ! array_key_exists( $type, $this->resolved ) ) {
			$id = Repository::resolve( $type );

			$this->resolved[ $type ] = null !== $id && null !== LayoutRepository::get( $id ) ? $id : null;
		}

		return $this->resolved[ $type ];
	}

	/**
	 * Filter saha_render_header.
	 *
	 * @param bool $rendered Đã có nơi khác render chưa.
	 */
	public function renderHeader( $rendered ) {
		if ( $rendered ) {
			return $rendered;
		}

		$id = $this->template( 'header' );

		if ( null === $id ) {
			return false;
		}

		echo $this->html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- output của Renderer đã escape từng element.

		return true;
	}

	/**
	 * Filter saha_render_footer.
	 *
	 * @param bool $rendered Đã có nơi khác render chưa.
	 */
	public function renderFooter( $rendered ) {
		if ( $rendered ) {
			return $rendered;
		}

		$id = $this->template( 'footer' );

		if ( null === $id ) {
			return false;
		}

		echo '<footer class="saha-site-footer saha-builder-content saha-builder-' . (int) $id . '">' . $this->html( $id ) . '</footer>'; // phpcs:ignore WordPress.Security.EscapeOutput -- như trên.

		return true;
	}

	/**
	 * HTML của template.
	 *
	 * @param int $id Template ID.
	 */
	private function html( int $id ): string {
		$document = LayoutRepository::get( $id );

		return null === $document ? '' : ( new Renderer() )->document( $document, new RenderContext( $id ) );
	}

	/**
	 * CSS + JS của header/footer (trên mọi trang).
	 */
	public function enqueue(): void {
		$any = false;

		foreach ( array( 'header', 'footer' ) as $type ) {
			$id = $this->template( $type );

			if ( null !== $id ) {
				$any = true;
				BuilderFrontend::enqueueBase();
				BuilderFrontend::enqueueDocumentCss( $id );
			}
		}

		if ( ! $any ) {
			return;
		}

		wp_register_script( self::SCRIPT, SAHA_CORE_URL . 'public/assets/js/header.js', array(), SAHA_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		if ( null !== $this->template( 'header' ) ) {
			wp_enqueue_script( self::SCRIPT );
		}
	}

	/**
	 * Class body để theme biết header/footer do builder dựng.
	 *
	 * @param string[] $classes Class.
	 * @return string[]
	 */
	public function bodyClass( array $classes ): array {
		if ( null !== $this->template( 'header' ) ) {
			$classes[] = 'saha-has-builder-header';
		}

		if ( null !== $this->template( 'footer' ) ) {
			$classes[] = 'saha-has-builder-footer';
		}

		return $classes;
	}

	/**
	 * Cập nhật số trên icon giỏ sau "Thêm vào giỏ" (AJAX của WooCommerce).
	 *
	 * @param array<string, string> $fragments Fragment.
	 * @return array<string, string>
	 */
	public function cartFragment( $fragments ): array {
		$fragments = (array) $fragments;

		$fragments['span.saha-cart-count'] = '<span class="saha-cart-count" aria-hidden="true">' . Cart::count() . '</span>';

		return $fragments;
	}
}
