<?php
/**
 * Ô tìm kiếm có autocomplete.
 *
 * Form vẫn submit bình thường khi JS lỗi/tắt — autocomplete chỉ là lớp tăng cường.
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args placeholder, autofocus.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Conditional enqueue: chỉ load khi ô tìm kiếm thực sự được render (spec §73).
wp_enqueue_style( 'saha-search' );
wp_enqueue_script( 'saha-search' );

$saha_placeholder = (string) ( $args['placeholder'] ?? __( 'Tìm theo tên hoặc mã sản phẩm…', 'saha' ) );
$saha_autofocus   = ! empty( $args['autofocus'] );
$saha_id          = 'saha-search-' . wp_unique_id();
$saha_panel_id    = $saha_id . '-panel';
?>
<div class="saha-search" data-saha-search>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="saha-search__form">
		<label class="saha-visually-hidden" for="<?php echo esc_attr( $saha_id ); ?>">
			<?php esc_html_e( 'Tìm kiếm sản phẩm', 'saha' ); ?>
		</label>

		<input
			type="search"
			id="<?php echo esc_attr( $saha_id ); ?>"
			class="saha-search__input"
			name="s"
			value="<?php echo esc_attr( get_search_query() ); ?>"
			placeholder="<?php echo esc_attr( $saha_placeholder ); ?>"
			aria-controls="<?php echo esc_attr( $saha_panel_id ); ?>"
			<?php echo $saha_autofocus ? 'autofocus' : ''; ?>
			data-saha-search-input
		>

		<input type="hidden" name="post_type" value="product">

		<button type="submit" class="saha-search__submit">
			<?php esc_html_e( 'Tìm', 'saha' ); ?>
		</button>
	</form>

	<div
		class="saha-search__panel"
		id="<?php echo esc_attr( $saha_panel_id ); ?>"
		data-saha-search-panel
		hidden
	></div>
</div>
