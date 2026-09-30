<?php
/**
 * View: panel field sản phẩm trong WooCommerce product data.
 *
 * @package Saha\Core
 *
 * @var int                                       $product_id Product ID.
 * @var array<string, array<string, mixed>>       $panels     Panel definition.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Product;

Product::nonce_field();

foreach ( $panels as $saha_panel_key => $saha_panel ) :
	?>
	<div id="saha_<?php echo esc_attr( $saha_panel_key ); ?>_panel" class="panel woocommerce_options_panel saha-product-panel">
		<div class="options_group">
			<?php
			foreach ( (array) $saha_panel['fields'] as $saha_key => $saha_field ) :
				$saha_type  = (string) ( $saha_field['type'] ?? 'text' );
				$saha_label = (string) ( $saha_field['label'] ?? $saha_key );
				$saha_hint  = (string) ( $saha_field['hint'] ?? '' );
				$saha_id    = 'saha-product-' . sanitize_key( $saha_key );
				$saha_name  = 'saha_product[' . $saha_key . ']';
				$saha_value = Product::get_meta( $product_id, $saha_key );

				if ( 'repeater' === $saha_type ) :
					$saha_rows = Product::get_specs( $product_id );
					?>
					<div class="saha-repeater" data-saha-repeater>
						<p class="saha-repeater__title"><strong><?php echo esc_html( $saha_label ); ?></strong></p>

						<table class="saha-repeater__table widefat">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Tên thông số', 'saha-core' ); ?></th>
									<th><?php esc_html_e( 'Giá trị', 'saha-core' ); ?></th>
									<th class="saha-repeater__action"></th>
								</tr>
							</thead>
							<tbody data-saha-repeater-body>
								<?php foreach ( $saha_rows as $saha_index => $saha_row ) : ?>
									<tr data-saha-repeater-row>
										<td>
											<input
												type="text"
												name="saha_product[specs][<?php echo esc_attr( (string) $saha_index ); ?>][label]"
												value="<?php echo esc_attr( $saha_row['label'] ); ?>"
												class="widefat"
											>
										</td>
										<td>
											<input
												type="text"
												name="saha_product[specs][<?php echo esc_attr( (string) $saha_index ); ?>][value]"
												value="<?php echo esc_attr( $saha_row['value'] ); ?>"
												class="widefat"
											>
										</td>
										<td class="saha-repeater__action">
											<button type="button" class="button-link saha-remove" data-saha-repeater-remove>
												<?php esc_html_e( 'Xoá', 'saha-core' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<p>
							<button type="button" class="button" data-saha-repeater-add>
								<?php esc_html_e( 'Thêm thông số', 'saha-core' ); ?>
							</button>
						</p>

						<?php if ( '' !== $saha_hint ) : ?>
							<p class="description"><?php echo esc_html( $saha_hint ); ?></p>
						<?php endif; ?>
					</div>

				<?php elseif ( 'documents' === $saha_type ) : ?>
					<?php $saha_docs = Product::get_documents( $product_id ); ?>
					<div class="saha-docs" data-saha-docs>
						<p class="saha-repeater__title"><strong><?php echo esc_html( $saha_label ); ?></strong></p>

						<ul class="saha-docs__list" data-saha-docs-list>
							<?php foreach ( $saha_docs as $saha_index => $saha_doc ) : ?>
								<li data-saha-docs-row>
									<input
										type="hidden"
										name="saha_product[docs][<?php echo esc_attr( (string) $saha_index ); ?>][id]"
										value="<?php echo esc_attr( (string) $saha_doc['id'] ); ?>"
									>
									<select name="saha_product[docs][<?php echo esc_attr( (string) $saha_index ); ?>][type]">
										<?php foreach ( Product::document_types() as $saha_type_key => $saha_type_label ) : ?>
											<option
												value="<?php echo esc_attr( $saha_type_key ); ?>"
												<?php selected( $saha_doc['type'], $saha_type_key ); ?>
											>
												<?php echo esc_html( $saha_type_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<input
										type="text"
										name="saha_product[docs][<?php echo esc_attr( (string) $saha_index ); ?>][title]"
										value="<?php echo esc_attr( (string) $saha_doc['title'] ); ?>"
										placeholder="<?php esc_attr_e( 'Tên hiển thị', 'saha-core' ); ?>"
									>
									<a href="<?php echo esc_url( (string) $saha_doc['url'] ); ?>" target="_blank" rel="noopener">
										<?php esc_html_e( 'Xem', 'saha-core' ); ?>
									</a>
									<button type="button" class="button-link saha-remove" data-saha-docs-remove>
										<?php esc_html_e( 'Xoá', 'saha-core' ); ?>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>

						<p>
							<button type="button" class="button" data-saha-docs-add>
								<?php esc_html_e( 'Thêm tài liệu từ Media Library', 'saha-core' ); ?>
							</button>
						</p>

						<?php if ( '' !== $saha_hint ) : ?>
							<p class="description"><?php echo esc_html( $saha_hint ); ?></p>
						<?php endif; ?>
					</div>

				<?php elseif ( 'select' === $saha_type ) : ?>
					<p class="form-field">
						<label for="<?php echo esc_attr( $saha_id ); ?>"><?php echo esc_html( $saha_label ); ?></label>
						<select id="<?php echo esc_attr( $saha_id ); ?>" name="<?php echo esc_attr( $saha_name ); ?>">
							<?php foreach ( Product::options( (string) ( $saha_field['options'] ?? '' ) ) as $saha_opt => $saha_opt_label ) : ?>
								<option value="<?php echo esc_attr( $saha_opt ); ?>" <?php selected( (string) $saha_value, $saha_opt ); ?>>
									<?php echo esc_html( $saha_opt_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( '' !== $saha_hint ) : ?>
							<span class="description"><?php echo esc_html( $saha_hint ); ?></span>
						<?php endif; ?>
					</p>

				<?php elseif ( 'html' === $saha_type ) : ?>
					<div class="saha-editor-field">
						<p><strong><?php echo esc_html( $saha_label ); ?></strong></p>
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
						<?php if ( '' !== $saha_hint ) : ?>
							<p class="description"><?php echo esc_html( $saha_hint ); ?></p>
						<?php endif; ?>
					</div>

				<?php else : ?>
					<p class="form-field">
						<label for="<?php echo esc_attr( $saha_id ); ?>"><?php echo esc_html( $saha_label ); ?></label>
						<input
							type="text"
							id="<?php echo esc_attr( $saha_id ); ?>"
							name="<?php echo esc_attr( $saha_name ); ?>"
							value="<?php echo esc_attr( (string) $saha_value ); ?>"
							class="short"
						>
						<?php if ( '' !== $saha_hint ) : ?>
							<span class="description"><?php echo esc_html( $saha_hint ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
endforeach;
?>

<script type="text/html" id="tmpl-saha-repeater-row">
	<tr data-saha-repeater-row>
		<td><input type="text" name="saha_product[specs][{{data.index}}][label]" value="" class="widefat"></td>
		<td><input type="text" name="saha_product[specs][{{data.index}}][value]" value="" class="widefat"></td>
		<td class="saha-repeater__action">
			<button type="button" class="button-link saha-remove" data-saha-repeater-remove>
				<?php esc_html_e( 'Xoá', 'saha-core' ); ?>
			</button>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-saha-docs-row">
	<li data-saha-docs-row>
		<input type="hidden" name="saha_product[docs][{{data.index}}][id]" value="{{data.id}}">
		<select name="saha_product[docs][{{data.index}}][type]">
			<?php foreach ( Product::document_types() as $saha_tpl_key => $saha_tpl_label ) : ?>
				<option value="<?php echo esc_attr( $saha_tpl_key ); ?>"><?php echo esc_html( $saha_tpl_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="text" name="saha_product[docs][{{data.index}}][title]" value="{{data.title}}" placeholder="<?php esc_attr_e( 'Tên hiển thị', 'saha-core' ); ?>">
		<button type="button" class="button-link saha-remove" data-saha-docs-remove>
			<?php esc_html_e( 'Xoá', 'saha-core' ); ?>
		</button>
	</li>
</script>
