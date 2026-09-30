<?php
/**
 * Tiêu đề khối + link "Xem tất cả".
 *
 * Heading mặc định là h2 — trang chủ để H1 cho hero (spec §22).
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args title, subtitle, view_all_url, view_all_label, tag.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_title = trim( (string) ( $args['title'] ?? '' ) );

if ( '' === $saha_title ) {
	return;
}

$saha_tag      = in_array( $args['tag'] ?? 'h2', array( 'h2', 'h3' ), true ) ? (string) $args['tag'] : 'h2';
$saha_subtitle = trim( (string) ( $args['subtitle'] ?? '' ) );
$saha_url      = (string) ( $args['view_all_url'] ?? '' );
$saha_label    = (string) ( $args['view_all_label'] ?? '' );
$saha_label    = '' !== $saha_label ? $saha_label : __( 'Xem tất cả', 'flatsome-child' );
?>
<div class="saha-section-heading">
	<div class="saha-section-heading__text">
		<<?php echo esc_html( $saha_tag ); ?> class="saha-section-heading__title"><?php echo esc_html( $saha_title ); ?></<?php echo esc_html( $saha_tag ); ?>>

		<?php if ( '' !== $saha_subtitle ) : ?>
			<p class="saha-section-heading__subtitle"><?php echo esc_html( $saha_subtitle ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $saha_url ) : ?>
		<a class="saha-section-heading__more" href="<?php echo esc_url( $saha_url ); ?>">
			<?php echo esc_html( $saha_label ); ?>
			<span class="saha-visually-hidden"><?php echo esc_html( ': ' . $saha_title ); ?></span>
			<span aria-hidden="true">&rarr;</span>
		</a>
	<?php endif; ?>
</div>
