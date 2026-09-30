<?php
/**
 * View: trang Cấu hình.
 *
 * @package Saha\Core
 *
 * @var array<string, array<string, mixed>> $schema   Schema field.
 * @var array<string, string>               $sections Section.
 * @var array<string, mixed>                $values   Giá trị hiện tại.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Settings;

$option = Settings::OPTION;
?>
<div class="wrap saha-admin">
	<h1><?php esc_html_e( 'SAHA — Cấu hình', 'saha-core' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Các giá trị dưới đây được dùng ở toàn bộ frontend (hotline, Zalo, CTA, email báo giá). Không sửa trực tiếp trong template.', 'saha-core' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
		<?php settings_fields( Settings::GROUP ); ?>

		<?php foreach ( $sections as $section_key => $section_label ) : ?>
			<h2 class="saha-section-title"><?php echo esc_html( $section_label ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
				<?php
				foreach ( $schema as $key => $field ) :
					if ( ( $field['section'] ?? '' ) !== $section_key ) {
						continue;
					}

					$type        = (string) ( $field['type'] ?? 'text' );
					$value       = $values[ $key ] ?? '';
					$field_id    = 'saha-field-' . sanitize_key( $key );
					$field_name  = $option . '[' . $key . ']';
					$description = (string) ( $field['description'] ?? '' );
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $field_id ); ?>">
								<?php echo esc_html( (string) ( $field['label'] ?? $key ) ); ?>
							</label>
						</th>
						<td>
							<?php if ( 'bool' === $type ) : ?>
								<label for="<?php echo esc_attr( $field_id ); ?>">
									<input
										type="checkbox"
										id="<?php echo esc_attr( $field_id ); ?>"
										name="<?php echo esc_attr( $field_name ); ?>"
										value="1"
										<?php checked( (bool) $value ); ?>
									>
									<?php esc_html_e( 'Bật', 'saha-core' ); ?>
								</label>
							<?php elseif ( 'select' === $type ) : ?>
								<select
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
								>
									<?php foreach ( (array) ( $field['options'] ?? array() ) as $opt_value => $opt_label ) : ?>
										<option value="<?php echo esc_attr( (string) $opt_value ); ?>" <?php selected( (string) $value, (string) $opt_value ); ?>>
											<?php echo esc_html( (string) $opt_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'textarea' === $type ) : ?>
								<textarea
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
									rows="3"
									class="large-text"
								><?php echo esc_textarea( (string) $value ); ?></textarea>
							<?php else : ?>
								<?php
								$input_type = 'email' === $type ? 'email' : ( 'url' === $type ? 'url' : 'text' );
								?>
								<input
									type="<?php echo esc_attr( $input_type ); ?>"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
									value="<?php echo esc_attr( (string) $value ); ?>"
									class="regular-text"
								>
							<?php endif; ?>

							<?php if ( '' !== $description ) : ?>
								<p class="description"><?php echo esc_html( $description ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<?php submit_button(); ?>
	</form>
</div>
