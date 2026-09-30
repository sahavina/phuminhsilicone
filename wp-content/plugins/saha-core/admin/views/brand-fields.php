<?php
/**
 * View: field term meta cho thương hiệu.
 *
 * @package Saha\Core
 *
 * @var array<string, array<string, string>> $schema Schema meta.
 * @var array<string, mixed>                 $values Giá trị hiện tại.
 * @var string                               $mode   add|edit.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Brand;

$saha_is_edit = 'edit' === $mode;

Brand::nonce_field();

foreach ( $schema as $saha_key => $saha_field ) :
	$saha_type  = (string) ( $saha_field['type'] ?? 'text' );
	$saha_label = (string) ( $saha_field['label'] ?? $saha_key );
	$saha_hint  = (string) ( $saha_field['description'] ?? '' );
	$saha_id    = 'saha-brand-' . sanitize_key( $saha_key );
	$saha_name  = 'saha_brand[' . $saha_key . ']';
	$saha_value = $values[ $saha_key ] ?? '';

	// Trang "Add new" dùng div.form-field, trang "Edit" dùng tr.form-field.
	$saha_open  = $saha_is_edit ? '<tr class="form-field saha-term-field"><th scope="row">' : '<div class="form-field saha-term-field">';
	$saha_mid   = $saha_is_edit ? '</th><td>' : '';
	$saha_close = $saha_is_edit ? '</td></tr>' : '</div>';

	echo $saha_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup tĩnh.
	?>
	<label for="<?php echo esc_attr( $saha_id ); ?>"><?php echo esc_html( $saha_label ); ?></label>
	<?php
	echo $saha_mid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup tĩnh.

	if ( 'attachment' === $saha_type ) :
		$saha_attachment_id = (int) $saha_value;
		?>
		<div class="saha-media-field" data-saha-media>
			<div class="saha-media-field__preview" data-saha-media-preview>
				<?php
				if ( $saha_attachment_id > 0 ) {
					echo wp_get_attachment_image( $saha_attachment_id, 'medium', false, array( 'style' => 'max-width:200px;height:auto;' ) );
				}
				?>
			</div>
			<input
				type="hidden"
				id="<?php echo esc_attr( $saha_id ); ?>"
				name="<?php echo esc_attr( $saha_name ); ?>"
				value="<?php echo esc_attr( (string) $saha_attachment_id ); ?>"
				data-saha-media-input
			>
			<button type="button" class="button" data-saha-media-select>
				<?php esc_html_e( 'Chọn ảnh', 'saha-core' ); ?>
			</button>
			<button type="button" class="button-link saha-media-field__remove" data-saha-media-remove>
				<?php esc_html_e( 'Xoá', 'saha-core' ); ?>
			</button>
		</div>
	<?php elseif ( 'textarea' === $saha_type ) : ?>
		<textarea
			id="<?php echo esc_attr( $saha_id ); ?>"
			name="<?php echo esc_attr( $saha_name ); ?>"
			rows="3"
			class="large-text"
		><?php echo esc_textarea( (string) $saha_value ); ?></textarea>
	<?php elseif ( 'html' === $saha_type ) : ?>
		<?php
		wp_editor(
			(string) $saha_value,
			$saha_id,
			array(
				'textarea_name' => $saha_name,
				'textarea_rows' => 8,
				'media_buttons' => false,
				'teeny'         => true,
			)
		);
		?>
	<?php else : ?>
		<input
			type="<?php echo 'url' === $saha_type ? 'url' : 'text'; ?>"
			id="<?php echo esc_attr( $saha_id ); ?>"
			name="<?php echo esc_attr( $saha_name ); ?>"
			value="<?php echo esc_attr( (string) $saha_value ); ?>"
			class="regular-text"
		>
	<?php endif; ?>

	<?php if ( '' !== $saha_hint ) : ?>
		<p class="description"><?php echo esc_html( $saha_hint ); ?></p>
	<?php endif; ?>
	<?php
	echo $saha_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup tĩnh.
endforeach;
