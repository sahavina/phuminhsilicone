<?php
/**
 * Breadcrumb fallback — chỉ dùng khi không có plugin SEO (spec §71).
 *
 * Không tự sinh BreadcrumbList schema để tránh trùng Rank Math.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_items = array(
	array(
		'label' => __( 'Trang chủ', 'flatsome-child' ),
		'url'   => home_url( '/' ),
	),
);

if ( is_singular( 'product' ) ) {
	$saha_terms = get_the_terms( get_queried_object_id(), 'product_cat' );

	if ( $saha_terms && ! is_wp_error( $saha_terms ) ) {
		$saha_term    = $saha_terms[0];
		$saha_items[] = array(
			'label' => $saha_term->name,
			'url'   => (string) get_term_link( $saha_term ),
		);
	}

	$saha_items[] = array(
		'label' => get_the_title(),
		'url'   => '',
	);
} elseif ( is_tax() || is_category() || is_tag() ) {
	$saha_object = get_queried_object();

	if ( $saha_object instanceof WP_Term ) {
		if ( $saha_object->parent ) {
			$saha_parent = get_term( $saha_object->parent, $saha_object->taxonomy );

			if ( $saha_parent instanceof WP_Term ) {
				$saha_items[] = array(
					'label' => $saha_parent->name,
					'url'   => (string) get_term_link( $saha_parent ),
				);
			}
		}

		$saha_items[] = array(
			'label' => $saha_object->name,
			'url'   => '',
		);
	}
} elseif ( is_singular( 'post' ) ) {
	$saha_cats = get_the_category( get_queried_object_id() );

	if ( $saha_cats ) {
		$saha_items[] = array(
			'label' => $saha_cats[0]->name,
			'url'   => (string) get_category_link( $saha_cats[0] ),
		);
	}

	$saha_items[] = array(
		'label' => get_the_title(),
		'url'   => '',
	);
} elseif ( is_singular() ) {
	$saha_items[] = array(
		'label' => get_the_title(),
		'url'   => '',
	);
} elseif ( is_search() ) {
	$saha_items[] = array(
		'label' => sprintf(
			/* translators: %s: search keyword */
			__( 'Tìm kiếm: %s', 'flatsome-child' ),
			get_search_query()
		),
		'url'   => '',
	);
}

if ( count( $saha_items ) < 2 ) {
	return;
}
?>
<nav class="saha-breadcrumb" aria-label="<?php esc_attr_e( 'Đường dẫn', 'flatsome-child' ); ?>">
	<ol class="saha-breadcrumb__list">
		<?php foreach ( $saha_items as $saha_index => $saha_item ) : ?>
			<li class="saha-breadcrumb__item">
				<?php if ( '' !== $saha_item['url'] && $saha_index < count( $saha_items ) - 1 ) : ?>
					<a href="<?php echo esc_url( $saha_item['url'] ); ?>"><?php echo esc_html( $saha_item['label'] ); ?></a>
				<?php else : ?>
					<span aria-current="page"><?php echo esc_html( $saha_item['label'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
