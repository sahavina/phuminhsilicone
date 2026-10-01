<?php
/**
 * Element động: danh sách sản phẩm của shop / danh mục / thương hiệu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\Filter;

defined( 'ABSPATH' ) || exit;

/**
 * ProductArchive — vòng lặp WooCommerce trên main query: hook `woocommerce_before_shop_loop`
 * (số kết quả, sắp xếp, bộ lọc của saha-theme), thẻ sản phẩm, phân trang, trạng thái rỗng.
 * Số cột/số sản phẩm theo Theme Options → WooCommerce (như shop mặc định).
 *
 * Bố cục "Cột lọc bên trái" (kiểu cửa hàng): cột trái gồm danh mục + bộ lọc (theme in qua hook
 * `saha_product_archive_sidebar`), cột phải gồm thanh "Đang hiện x / y · Sắp xếp", điều kiện đang
 * lọc, lưới, phân trang. Mobile: cột lọc thành ngăn trượt mở bằng nút "Bộ lọc" (elements.js).
 */
final class ProductArchive extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'product-archive',
				'name'     => __( 'Danh sách sản phẩm (động)', 'saha-core' ),
				'icon'     => 'products',
				'category' => 'product-template',
				'controls' => array(
					'layout'     => array(
						'type'    => 'select',
						'label'   => __( 'Bố cục', 'saha-core' ),
						'section' => 'content',
						'default' => 'stack',
						'options' => array(
							'stack'   => __( 'Bộ lọc phía trên danh sách', 'saha-core' ),
							'sidebar' => __( 'Cột lọc bên trái (kiểu cửa hàng)', 'saha-core' ),
						),
					),
					'toolbar'    => array(
						'type'    => 'toggle',
						'label'   => __( 'Thanh trên danh sách (số kết quả, sắp xếp, bộ lọc)', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'categories' => array(
						'type'    => 'toggle',
						'label'   => __( 'Cột lọc: danh sách danh mục', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'price'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Cột lọc: khoảng giá (tự chia theo giá sản phẩm; ẩn ở chế độ catalogue)', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
				),
			)
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		if ( ! function_exists( 'woocommerce_product_loop' ) ) {
			return $this->placeholder( $node, $ctx, __( 'Cần bật WooCommerce.', 'saha-core' ) );
		}

		if ( 'sidebar' === $this->prop( $node, 'layout' ) ) {
			return $this->renderSidebar( $node, $ctx );
		}

		$toolbar = (bool) $this->prop( $node, 'toolbar' );
		$html    = self::withArchive(
			$ctx,
			static function () use ( $toolbar ): string {
				return self::capture(
					static function () use ( $toolbar ): void {
						if ( ! woocommerce_product_loop() ) {
							do_action( 'woocommerce_no_products_found' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce.
							return;
						}

						if ( $toolbar ) {
							do_action( 'woocommerce_before_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						}

						self::loop();
					}
				);
			}
		);

		return '' === trim( $html )
			? $this->placeholder( $node, $ctx, __( 'Danh sách sản phẩm', 'saha-core' ) )
			: '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-product-archive', 'woocommerce' ) ) . '>' . $html . '</div>';
	}

	/**
	 * Lưới sản phẩm + phân trang (main query đang là danh sách cần in).
	 */
	private static function loop(): void {
		woocommerce_product_loop_start();

		while ( have_posts() ) {
			the_post();
			do_action( 'woocommerce_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			wc_get_template_part( 'content', 'product' );
		}

		woocommerce_product_loop_end();
		do_action( 'woocommerce_after_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Bố cục cột lọc bên trái.
	 *
	 * @param Node          $node Node.
	 * @param RenderContext $ctx  Ngữ cảnh.
	 */
	private function renderSidebar( Node $node, RenderContext $ctx ): string {
		$panel      = 'saha-shop-panel-' . $node->id;
		$categories = (bool) $this->prop( $node, 'categories' );
		$price      = (bool) $this->prop( $node, 'price' );
		$toolbar    = (bool) $this->prop( $node, 'toolbar' );

		$html = self::withArchive(
			$ctx,
			static function () use ( $panel, $categories, $price, $toolbar ): string {
				$term = $GLOBALS['wp_query'] instanceof \WP_Query ? $GLOBALS['wp_query']->get_queried_object() : null;
				$term = $term instanceof \WP_Term ? $term : null;

				$sidebar = self::capture(
					static function () use ( $term, $price ): void {
						/**
						 * Nội dung cột lọc (sau danh sách danh mục). saha-theme in bộ lọc ở đây.
						 *
						 * @param array{term: \WP_Term|null, price: bool} $args Danh mục đang xem; có hiện khoảng giá không.
						 */
						do_action(
							'saha_product_archive_sidebar',
							array(
								'term'  => $term,
								'price' => $price,
							)
						);
					}
				);

				$main = self::capture(
					static function () use ( $toolbar ): void {
						$has = woocommerce_product_loop();

						// Thông báo WooCommerce (đã thêm vào giỏ…) và plugin khác vẫn chạy; số kết quả,
						// sắp xếp và bộ lọc phía trên thì bố cục này tự in ở chỗ riêng.
						$restore = self::detachToolbar();
						do_action( 'woocommerce_before_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						$restore();

						if ( $toolbar && $has ) {
							self::toolbar();
						}

						self::activeFilters();

						if ( ! $has ) {
							do_action( 'woocommerce_no_products_found' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
							return;
						}

						self::loop();
					}
				);

				$active = count( Filter::active_items() );

				return '<button type="button" class="saha-shop__toggle" aria-controls="' . esc_attr( $panel ) . '" aria-expanded="false" data-saha-shop-toggle>'
					. self::filterIcon()
					. '<span>' . esc_html__( 'Danh mục & bộ lọc', 'saha-core' ) . '</span>'
					. '<span class="saha-shop__toggle-count" data-saha-shop-count' . ( $active ? '' : ' hidden' ) . '>' . (int) $active . '</span>'
					. '</button>'
					. '<div class="saha-shop__layout">'
					. '<aside class="saha-shop__sidebar" id="' . esc_attr( $panel ) . '" aria-label="' . esc_attr__( 'Danh mục và bộ lọc', 'saha-core' ) . '" data-saha-shop-panel>'
					. '<div class="saha-shop__panel-head">'
					. '<span class="saha-shop__panel-title">' . esc_html__( 'Danh mục & bộ lọc', 'saha-core' ) . '</span>'
					. '<button type="button" class="saha-shop__close" data-saha-shop-close aria-label="' . esc_attr__( 'Đóng bộ lọc', 'saha-core' ) . '">' . Icons::svg( 'close' ) . '</button>'
					. '</div>'
					. '<div class="saha-shop__panel-body">'
					. ( $categories ? self::categories( $term ) : '' )
					. $sidebar
					. '</div>'
					. '<div class="saha-shop__panel-foot"><button type="button" class="saha-shop__done" data-saha-shop-close>' . esc_html__( 'Xem kết quả', 'saha-core' ) . '</button></div>'
					. '</aside>'
					. '<div class="saha-shop__main" data-saha-filter-results>' . $main . '</div>'
					. '</div>';
			}
		);

		return '' === trim( $html )
			? $this->placeholder( $node, $ctx, __( 'Danh sách sản phẩm', 'saha-core' ) )
			: '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-product-archive', 'saha-shop', 'woocommerce' ) ) . ' data-saha-shop>' . $html . '</div>';
	}

	/**
	 * Tạm gỡ số kết quả, sắp xếp và bộ lọc phía trên của `woocommerce_before_shop_loop`.
	 *
	 * @return callable Hàm gắn lại.
	 */
	private static function detachToolbar(): callable {
		$count  = has_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count' );
		$order  = has_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering' );
		$no_bar = '__return_false';

		if ( false !== $count ) {
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', (int) $count );
		}

		if ( false !== $order ) {
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', (int) $order );
		}

		add_filter( 'saha_theme_show_archive_filter', $no_bar );

		return static function () use ( $count, $order, $no_bar ): void {
			if ( false !== $count ) {
				add_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', (int) $count );
			}

			if ( false !== $order ) {
				add_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', (int) $order );
			}

			remove_filter( 'saha_theme_show_archive_filter', $no_bar );
		};
	}

	/**
	 * Thanh "Đang hiện x / y sản phẩm" + sắp xếp (chỉ khi có sản phẩm).
	 */
	private static function toolbar(): void {
		$total    = (int) wc_get_loop_prop( 'total', 0 );
		$per_page = max( 1, (int) wc_get_loop_prop( 'per_page', $total ) );
		$current  = max( 1, (int) wc_get_loop_prop( 'current_page', 1 ) );
		$first    = ( $current - 1 ) * $per_page + 1;
		$last     = min( $total, $current * $per_page );
		$shown    = $total <= $per_page ? number_format_i18n( $total ) : number_format_i18n( $first ) . '–' . number_format_i18n( $last );

		echo '<div class="saha-shop__toolbar">';

		printf(
			'<p class="saha-shop__count" role="status">%s</p>',
			wp_kses(
				/* translators: 1: số đang hiện (hoặc khoảng "1–24"), 2: tổng số sản phẩm */
				sprintf( __( 'Đang hiện <strong>%1$s</strong> / %2$s sản phẩm', 'saha-core' ), $shown, number_format_i18n( $total ) ),
				array( 'strong' => array() )
			)
		);

		if ( $total > 1 ) {
			echo '<div class="saha-shop__sort"><span class="saha-shop__sort-label" aria-hidden="true">' . esc_html__( 'Sắp xếp theo', 'saha-core' ) . '</span>';
			woocommerce_catalog_ordering();
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Các điều kiện đang lọc, mỗi điều kiện có nút bỏ; kèm "Xoá tất cả".
	 */
	private static function activeFilters(): void {
		$items = Filter::active_items();

		if ( ! $items ) {
			return;
		}

		echo '<div class="saha-shop__active"><span class="saha-shop__active-label">' . esc_html__( 'Đang lọc:', 'saha-core' ) . '</span><ul>';

		foreach ( $items as $item ) {
			printf(
				'<li><a class="saha-shop__chip" href="%1$s" aria-label="%2$s">%3$s %4$s</a></li>',
				esc_url( $item['url'] ),
				/* translators: %s: điều kiện lọc */
				esc_attr( sprintf( __( 'Bỏ lọc: %s', 'saha-core' ), $item['label'] ) ),
				esc_html( $item['label'] ),
				Icons::svg( 'x' ) // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh.
			);
		}

		printf( '</ul><a class="saha-shop__clear" href="%1$s">%2$s</a></div>', esc_url( Filter::keep_orderby( Filter::current_base_url() ) ), esc_html__( 'Xoá tất cả', 'saha-core' ) );
	}

	/**
	 * Hộp danh mục: "Tất cả sản phẩm" + danh mục gốc; danh mục đang xem (và cha của nó) mở danh mục con.
	 *
	 * @param \WP_Term|null $current Danh mục / thương hiệu đang xem.
	 */
	private static function categories( ?\WP_Term $current ): string {
		$top = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
				'orderby'    => 'menu_order',
			)
		);

		if ( is_wp_error( $top ) || ! $top ) {
			return '';
		}

		$active = array();

		if ( $current instanceof \WP_Term && 'product_cat' === $current->taxonomy ) {
			$active = array_map( 'intval', array_merge( array( $current->term_id ), get_ancestors( $current->term_id, 'product_cat', 'taxonomy' ) ) );
		}

		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$is_shop  = function_exists( 'is_shop' ) && is_shop();
		$all      = (int) ( wp_count_posts( 'product' )->publish ?? 0 );

		$html  = '<nav class="saha-shop__box saha-shop-cats" aria-label="' . esc_attr__( 'Danh mục sản phẩm', 'saha-core' ) . '">';
		$html .= '<h2 class="saha-shop__box-title">' . esc_html__( 'Danh mục', 'saha-core' ) . '</h2><ul class="saha-shop-cats__list">';
		$html .= self::categoryItem( __( 'Tất cả sản phẩm', 'saha-core' ), (string) $shop_url, $all, $is_shop );

		foreach ( $top as $term ) {
			$open     = in_array( (int) $term->term_id, $active, true );
			$children = '';

			if ( $open ) {
				$subs = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'parent'     => $term->term_id,
						'hide_empty' => true,
						'orderby'    => 'menu_order',
					)
				);

				if ( ! is_wp_error( $subs ) && $subs ) {
					$children = '<ul class="saha-shop-cats__sub">';

					foreach ( $subs as $sub ) {
						$children .= self::categoryItem( $sub->name, (string) get_term_link( $sub ), self::termCount( $sub ), $current instanceof \WP_Term && $current->term_id === $sub->term_id );
					}

					$children .= '</ul>';
				}
			}

			$html .= self::categoryItem( $term->name, (string) get_term_link( $term ), self::termCount( $term ), $current instanceof \WP_Term && $current->term_id === $term->term_id, $children, $open );
		}

		return $html . '</ul></nav>';
	}

	/**
	 * Một dòng danh mục.
	 *
	 * @param string $name     Tên.
	 * @param string $url      Link.
	 * @param int    $count    Số sản phẩm.
	 * @param bool   $current  Đang xem.
	 * @param string $children HTML danh mục con.
	 * @param bool   $open     Nhánh đang mở.
	 */
	private static function categoryItem( string $name, string $url, int $count, bool $current, string $children = '', bool $open = false ): string {
		$class = 'saha-shop-cats__item' . ( $current ? ' is-current' : '' ) . ( $open ? ' is-open' : '' );

		return '<li class="' . esc_attr( $class ) . '"><a href="' . esc_url( $url ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>'
			. '<span class="saha-shop-cats__name">' . esc_html( $name ) . '</span>'
			. '<span class="saha-shop-cats__count">' . esc_html( number_format_i18n( $count ) ) . '</span>'
			. '</a>' . $children . '</li>';
	}

	/**
	 * Số sản phẩm của danh mục, gồm danh mục con (WooCommerce đếm sẵn trong term meta).
	 *
	 * @param \WP_Term $term Danh mục.
	 */
	public static function termCount( \WP_Term $term ): int {
		$count = get_term_meta( $term->term_id, 'product_count_' . $term->taxonomy, true );

		return '' !== $count && false !== $count ? (int) $count : (int) $term->count;
	}

	/**
	 * Icon "bộ lọc" (thanh trượt).
	 */
	private static function filterIcon(): string {
		return '<svg class="saha-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>';
	}
}
