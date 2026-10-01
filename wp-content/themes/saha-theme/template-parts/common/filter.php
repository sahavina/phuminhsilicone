<?php
/**
 * Bộ lọc sản phẩm.
 *
 * Là một form GET thật: hoạt động đầy đủ khi không có JavaScript.
 * product-filter.js chỉ tăng cường bằng AJAX + pushState (spec §10).
 *
 * @package Saha\Theme
 *
 * Bố cục "sidebar" (cột lọc kiểu cửa hàng, element Danh sách sản phẩm của builder): mỗi nhóm
 * một hộp, danh sách dọc, khoảng giá chọn sẵn (`saha_price`, tính từ giá thật của danh mục).
 *
 * @var array<string, mixed> $args show_brand, show_application, show_availability, show_price,
 *                                 layout (stack | sidebar), price_ranges (sidebar).
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! saha_theme_has_core() ) {
	return;
}

$saha_opts = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'show_brand'        => true,
		'show_application'  => true,
		'show_availability' => true,
		'show_price'        => false,
		'layout'            => 'stack',
		'price_ranges'      => array(),
	)
);

$saha_sidebar = 'sidebar' === $saha_opts['layout'];
$saha_box     = $saha_sidebar ? ' saha-shop__box' : '';
$saha_legend  = $saha_sidebar ? ' saha-shop__box-title' : '';

$saha_current = saha_filter_current();
$saha_action  = saha_filter_base_url();

$saha_brands = $saha_opts['show_brand'] ? saha_get_brands( array( 'hide_empty' => true ) ) : array();

$saha_applications = array();

if ( $saha_opts['show_application'] && taxonomy_exists( 'product_application' ) ) {
	$saha_terms = get_terms(
		array(
			'taxonomy'   => 'product_application',
			'hide_empty' => true,
		)
	);

	if ( ! is_wp_error( $saha_terms ) ) {
		$saha_applications = $saha_terms;
	}
}

$saha_has_any = $saha_brands || $saha_applications || $saha_opts['show_availability'] || $saha_opts['show_price'];

if ( ! $saha_has_any ) {
	return;
}

// Chỉ load JS khi bộ lọc thực sự được render.
wp_enqueue_script( 'saha-catalog-product-filter' );

/**
 * Giá trị đã chọn của một query var dạng mảng slug.
 *
 * @param array<string, mixed> $current Filter hiện tại.
 * @param string               $var     Query var.
 * @return string[]
 */
$saha_selected = static function ( array $current, string $var ): array {
	$value = $current[ $var ] ?? array();

	return is_array( $value ) ? array_map( 'strval', $value ) : array( (string) $value );
};
?>
<form
	class="saha-filter<?php echo $saha_sidebar ? ' saha-filter--sidebar' : ''; ?>"
	method="get"
	action="<?php echo esc_url( $saha_action ); ?>"
	data-saha-filter
	data-saha-filter-base="<?php echo esc_url( $saha_action ); ?>"
>
	<h2 class="saha-filter__title<?php echo $saha_sidebar ? ' saha-visually-hidden' : ''; ?>"><?php esc_html_e( 'Lọc sản phẩm', 'saha' ); ?></h2>

	<?php if ( $saha_sidebar && $saha_opts['price_ranges'] && ! saha_theme_catalogue_mode() ) : ?>
		<fieldset class="saha-filter__group<?php echo esc_attr( $saha_box ); ?>">
			<legend class="saha-filter__legend<?php echo esc_attr( $saha_legend ); ?>"><?php esc_html_e( 'Khoảng giá', 'saha' ); ?></legend>
			<?php $saha_price = (string) ( $saha_current['saha_price'] ?? '' ); ?>
			<ul class="saha-filter__list saha-filter__list--options">
				<li>
					<label>
						<input type="radio" name="saha_price" value="" <?php checked( '', $saha_price ); ?>>
						<span><?php esc_html_e( 'Tất cả mức giá', 'saha' ); ?></span>
					</label>
				</li>
				<?php foreach ( (array) $saha_opts['price_ranges'] as $saha_range ) : ?>
					<li>
						<label>
							<input type="radio" name="saha_price" value="<?php echo esc_attr( (string) $saha_range['value'] ); ?>" <?php checked( (string) $saha_range['value'], $saha_price ); ?>>
							<span><?php echo esc_html( Saha\Core\Filter::price_label( (int) $saha_range['min'], (int) $saha_range['max'] ) ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php endif; ?>

	<?php if ( $saha_brands ) : ?>
		<fieldset class="saha-filter__group<?php echo esc_attr( $saha_box ); ?>">
			<legend class="saha-filter__legend<?php echo esc_attr( $saha_legend ); ?>"><?php esc_html_e( 'Thương hiệu', 'saha' ); ?></legend>
			<?php $saha_checked = $saha_selected( $saha_current, 'saha_brand' ); ?>
			<ul class="saha-filter__list">
				<?php foreach ( $saha_brands as $saha_brand ) : ?>
					<li>
						<label>
							<input
								type="checkbox"
								name="saha_brand[]"
								value="<?php echo esc_attr( (string) $saha_brand['slug'] ); ?>"
								<?php checked( in_array( (string) $saha_brand['slug'], $saha_checked, true ) ); ?>
							>
							<span><?php echo esc_html( (string) $saha_brand['name'] ); ?></span>
							<span class="saha-filter__count">(<?php echo esc_html( (string) $saha_brand['count'] ); ?>)</span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php endif; ?>

	<?php if ( $saha_applications ) : ?>
		<fieldset class="saha-filter__group<?php echo esc_attr( $saha_box ); ?>">
			<legend class="saha-filter__legend<?php echo esc_attr( $saha_legend ); ?>"><?php esc_html_e( 'Ứng dụng', 'saha' ); ?></legend>
			<?php $saha_checked = $saha_selected( $saha_current, 'saha_application' ); ?>
			<ul class="saha-filter__list">
				<?php foreach ( $saha_applications as $saha_app ) : ?>
					<li>
						<label>
							<input
								type="checkbox"
								name="saha_application[]"
								value="<?php echo esc_attr( $saha_app->slug ); ?>"
								<?php checked( in_array( $saha_app->slug, $saha_checked, true ) ); ?>
							>
							<span><?php echo esc_html( $saha_app->name ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php endif; ?>

	<?php if ( $saha_opts['show_availability'] ) : ?>
		<fieldset class="saha-filter__group<?php echo esc_attr( $saha_box ); ?>">
			<legend class="saha-filter__legend<?php echo esc_attr( $saha_legend ); ?>"><?php esc_html_e( 'Tình trạng', 'saha' ); ?></legend>
			<?php $saha_availability = (string) ( $saha_current['saha_availability'] ?? '' ); ?>
			<ul class="saha-filter__list">
				<?php if ( $saha_sidebar ) : ?>
					<li>
						<label>
							<input type="radio" name="saha_availability" value="" <?php checked( '', $saha_availability ); ?>>
							<span><?php esc_html_e( 'Tất cả', 'saha' ); ?></span>
						</label>
					</li>
				<?php endif; ?>
				<?php foreach ( saha_availability_options() as $saha_key => $saha_label ) : ?>
					<?php if ( '' === $saha_key ) { continue; } ?>
					<li>
						<label>
							<input
								type="radio"
								name="saha_availability"
								value="<?php echo esc_attr( $saha_key ); ?>"
								<?php checked( $saha_availability, $saha_key ); ?>
							>
							<span><?php echo esc_html( $saha_label ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php endif; ?>

	<?php if ( $saha_opts['show_price'] && ! $saha_sidebar && ! saha_theme_catalogue_mode() ) : ?>
		<fieldset class="saha-filter__group">
			<legend class="saha-filter__legend"><?php esc_html_e( 'Khoảng giá', 'saha' ); ?></legend>
			<div class="saha-filter__price">
				<label class="saha-visually-hidden" for="saha-min-price"><?php esc_html_e( 'Giá thấp nhất', 'saha' ); ?></label>
				<input
					type="number"
					id="saha-min-price"
					name="saha_min_price"
					min="0"
					step="1000"
					value="<?php echo esc_attr( (string) ( $saha_current['saha_min_price'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Từ', 'saha' ); ?>"
				>
				<label class="saha-visually-hidden" for="saha-max-price"><?php esc_html_e( 'Giá cao nhất', 'saha' ); ?></label>
				<input
					type="number"
					id="saha-max-price"
					name="saha_max_price"
					min="0"
					step="1000"
					value="<?php echo esc_attr( (string) ( $saha_current['saha_max_price'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Đến', 'saha' ); ?>"
				>
			</div>
		</fieldset>
	<?php endif; ?>

	<div class="saha-filter__actions">
		<button type="submit" class="button primary"><?php esc_html_e( 'Áp dụng', 'saha' ); ?></button>

		<?php if ( $saha_current ) : ?>
			<a class="saha-filter__reset" href="<?php echo esc_url( $saha_action ); ?>">
				<?php esc_html_e( 'Xoá lọc', 'saha' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<p class="saha-filter__status" data-saha-filter-status role="status" aria-live="polite"></p>
</form>
