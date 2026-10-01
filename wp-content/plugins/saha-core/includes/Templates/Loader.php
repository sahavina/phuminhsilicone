<?php
/**
 * Dùng template dựng bằng builder cho trang sản phẩm, danh mục, bài viết…
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

use Saha\Core\Builder\Frontend as BuilderFrontend;
use Saha\Core\Builder\LayoutRepository;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Loader — `template_include`: request có template khớp điều kiện → file
 * `templates/builder-template.php` của saha-core (get_header + layout + get_footer).
 * Không khớp → theme giữ nguyên (single.php, woocommerce.php…).
 *
 * Chạy với mọi theme có header.php/footer.php chuẩn. Trang sản phẩm được bọc trong
 * `div.product` của WooCommerce (script gallery/biến thể và CSS của WooCommerce cần).
 */
final class Loader {

	/**
	 * Template của request (null = không dùng; chưa xác định = false).
	 *
	 * @var int|null|false
	 */
	private static $current = false;

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'include' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'bodyClass' ) );
	}

	/**
	 * Template nội dung áp cho request (null nếu không có).
	 */
	public static function current(): ?int {
		if ( false === self::$current ) {
			$type = RequestContext::type();
			$id   = '' === $type ? null : Repository::resolve( $type );

			self::$current = null !== $id && null !== LayoutRepository::get( $id ) ? $id : null;
		}

		return self::$current;
	}

	/**
	 * Đổi file template.
	 *
	 * @param string $template File theme chọn.
	 */
	public function include( $template ) {
		if ( is_embed() || is_feed() || null === self::current() ) {
			return $template;
		}

		return SAHA_CORE_PATH . 'templates/builder-template.php';
	}

	/**
	 * In layout của template đang dùng (gọi trong templates/builder-template.php).
	 */
	public static function render(): void {
		$id       = self::current();
		$document = null === $id ? null : LayoutRepository::get( $id );

		if ( null === $document ) {
			return;
		}

		$html = ( new Renderer() )->document( $document, new RenderContext( (int) $id ) );

		if ( function_exists( 'is_product' ) && is_product() ) {
			echo '<div id="product-' . (int) get_queried_object_id() . '" class="' . esc_attr( implode( ' ', wc_get_product_class( 'saha-template-product', (int) get_queried_object_id() ) ) ) . '">';
			do_action( 'woocommerce_before_single_product' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce (thông báo, …).
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- output của Renderer đã escape từng element.
			do_action( 'woocommerce_after_single_product' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			echo '</div>';
			return;
		}

		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			echo '<div class="woocommerce saha-template-archive">';
			woocommerce_output_all_notices();
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- như trên.
			echo '</div>';
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- như trên.
	}

	/**
	 * CSS của template (+ block bên trong).
	 */
	public function enqueue(): void {
		$id = self::current();

		if ( null !== $id ) {
			BuilderFrontend::enqueueBase();
			BuilderFrontend::enqueueDocumentCss( $id );
		}
	}

	/**
	 * Body class: theme biết nội dung do template builder dựng (bỏ khung/padding mặc định).
	 *
	 * @param string[] $classes Class.
	 * @return string[]
	 */
	public function bodyClass( array $classes ): array {
		$id = self::current();

		if ( null !== $id ) {
			$classes[] = 'saha-has-template';
			$classes[] = 'saha-template-' . Repository::typeOf( $id );
			$classes[] = 'saha-builder-page';
		}

		return $classes;
	}
}
