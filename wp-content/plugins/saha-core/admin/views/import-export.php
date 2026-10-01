<?php
/**
 * View: SAHA → Import / Export giao diện.
 *
 * @package Saha\Core
 *
 * @var array<string, mixed>|false $result    Báo cáo lần nhập vừa rồi (report, error) hoặc false.
 * @var array<string, array>       $available Danh sách xuất được (blocks, templates, pages).
 * @var array<string, string>      $types     Loại template.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\ImportExport\Importer;

$saha_kind_labels = array(
	'block'         => __( 'Block', 'saha-core' ),
	'page'          => __( 'Trang', 'saha-core' ),
	'template'      => __( 'Template', 'saha-core' ),
	'theme_options' => __( 'Theme Options', 'saha-core' ),
);

$saha_edit_url = static function ( int $id ): string {
	return defined( 'SAHA_BUILDER_VERSION' )
		? admin_url( 'admin.php?page=saha-builder&post=' . $id )
		: (string) get_edit_post_link( $id );
};

$saha_checklist = static function ( string $name, array $items, array $types = array() ): void {
	if ( ! $items ) {
		echo '<p class="description">' . esc_html__( 'Chưa có mục nào.', 'saha-core' ) . '</p>';
		return;
	}

	echo '<ul class="saha-ie__list">';

	foreach ( $items as $item ) {
		$type = (string) ( $item['type'] ?? '' );

		printf(
			'<li><label><input type="checkbox" name="%1$s[]" value="%2$d" checked> %3$s%4$s</label></li>',
			esc_attr( $name ),
			(int) $item['id'],
			esc_html( (string) $item['title'] ),
			'' !== $type && isset( $types[ $type ] ) ? ' <span class="description">— ' . esc_html( $types[ $type ] ) . '</span>' : ''
		);
	}

	echo '</ul>';
};
?>
<div class="wrap saha-admin saha-ie">
	<h1><?php esc_html_e( 'Import / Export giao diện', 'saha-core' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Chuyển giao diện dựng bằng SAHA Builder giữa các website: trang, Block dùng chung, header / footer / template (kèm điều kiện hiển thị), Theme Options và ảnh được dùng. Không chuyển sản phẩm, đơn hàng, khách hàng, menu.', 'saha-core' ); ?>
	</p>

	<?php if ( is_array( $result ) && '' !== (string) ( $result['error'] ?? '' ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( (string) $result['error'] ); ?></p></div>
	<?php elseif ( is_array( $result ) && is_array( $result['report'] ?? null ) ) : ?>
		<?php
		$saha_report = $result['report'];
		$saha_dry    = ! empty( $saha_report['dry_run'] );
		$saha_class  = $saha_report['errors'] ? 'notice-error' : ( $saha_report['warnings'] ? 'notice-warning' : 'notice-success' );
		?>
		<div class="notice <?php echo esc_attr( $saha_class ); ?> saha-ie__report" role="status">
			<p>
				<strong>
					<?php
					echo esc_html(
						$saha_dry
							? __( 'Chạy thử xong — chưa ghi gì.', 'saha-core' )
							/* translators: %d: số mục đã tạo */
							: sprintf( __( 'Đã nhập: tạo %d mục.', 'saha-core' ), count( $saha_report['created'] ) )
					);
					?>
				</strong>
				<?php
				/* translators: 1: file, 2: block, 3: trang, 4: template, 5: ảnh */
				echo esc_html( sprintf( __( 'File %1$s: %2$d block, %3$d trang, %4$d template, %5$d ảnh tham chiếu.', 'saha-core' ), (string) ( $saha_report['file'] ?? '' ), (int) $saha_report['planned']['blocks'], (int) $saha_report['planned']['pages'], (int) $saha_report['planned']['templates'], (int) $saha_report['planned']['media'] ) );
				?>
			</p>

			<?php if ( $saha_report['created'] ) : ?>
				<ul class="saha-ie__created">
					<?php foreach ( $saha_report['created'] as $saha_row ) : ?>
						<li>
							<?php echo esc_html( $saha_kind_labels[ $saha_row['kind'] ] ?? $saha_row['kind'] ); ?>:
							<?php if ( (int) $saha_row['id'] > 0 ) : ?>
								<a href="<?php echo esc_url( $saha_edit_url( (int) $saha_row['id'] ) ); ?>"><?php echo esc_html( (string) $saha_row['title'] ); ?></a>
								<span class="description">(#<?php echo (int) $saha_row['id']; ?>, <?php echo esc_html( (string) $saha_row['status'] ); ?>)</span>
							<?php else : ?>
								<?php echo esc_html( (string) $saha_row['title'] ); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php foreach ( array( 'errors', 'warnings' ) as $saha_key ) : ?>
				<?php if ( $saha_report[ $saha_key ] ) : ?>
					<p><strong><?php echo esc_html( 'errors' === $saha_key ? __( 'Lỗi', 'saha-core' ) : __( 'Cảnh báo', 'saha-core' ) ); ?></strong></p>
					<ul class="saha-ie__messages">
						<?php foreach ( $saha_report[ $saha_key ] as $saha_message ) : ?>
							<li><?php echo esc_html( (string) $saha_message ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="saha-ie__grid">
		<form class="saha-panel" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<h2><?php esc_html_e( 'Xuất', 'saha-core' ); ?></h2>
			<input type="hidden" name="action" value="saha_export">
			<?php wp_nonce_field( 'saha_export' ); ?>

			<fieldset>
				<legend><strong><?php esc_html_e( 'Theme Options', 'saha-core' ); ?></strong></legend>
				<label><input type="checkbox" name="theme_options" value="1" checked> <?php esc_html_e( 'Màu, font, logo, header dính, cửa hàng…', 'saha-core' ); ?></label>
			</fieldset>

			<fieldset>
				<legend><strong><?php esc_html_e( 'Header / Footer / Template', 'saha-core' ); ?></strong></legend>
				<?php $saha_checklist( 'templates', $available['templates'], $types ); ?>
			</fieldset>

			<fieldset>
				<legend><strong><?php esc_html_e( 'Trang dựng bằng builder', 'saha-core' ); ?></strong></legend>
				<?php $saha_checklist( 'pages', $available['pages'] ); ?>
			</fieldset>

			<fieldset>
				<legend><strong><?php esc_html_e( 'Block dùng chung', 'saha-core' ); ?></strong></legend>
				<p class="description"><?php esc_html_e( 'Block mà trang / template đã chọn dùng tới luôn được xuất kèm.', 'saha-core' ); ?></p>
				<?php $saha_checklist( 'blocks', $available['blocks'] ); ?>
			</fieldset>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Tải file xuất (.json)', 'saha-core' ); ?></button></p>
		</form>

		<form class="saha-panel" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<h2><?php esc_html_e( 'Nhập', 'saha-core' ); ?></h2>
			<input type="hidden" name="action" value="saha_import">
			<?php wp_nonce_field( 'saha_import' ); ?>

			<p>
				<label for="saha-ie-file"><strong><?php esc_html_e( 'File xuất từ SAHA (.json, tối đa 10 MB)', 'saha-core' ); ?></strong></label><br>
				<input type="file" id="saha-ie-file" name="saha_file" accept=".json,application/json" required>
			</p>

			<fieldset>
				<legend><strong><?php esc_html_e( 'Nhập những gì', 'saha-core' ); ?></strong></legend>
				<label><input type="checkbox" name="kinds[templates]" value="1" checked> <?php esc_html_e( 'Header / Footer / Template', 'saha-core' ); ?></label><br>
				<label><input type="checkbox" name="kinds[pages]" value="1" checked> <?php esc_html_e( 'Trang', 'saha-core' ); ?></label><br>
				<label><input type="checkbox" name="kinds[blocks]" value="1" checked> <?php esc_html_e( 'Block dùng chung', 'saha-core' ); ?></label><br>
				<label><input type="checkbox" name="kinds[theme_options]" value="1" checked> <?php esc_html_e( 'Theme Options (bản hiện tại được sao lưu trước)', 'saha-core' ); ?></label>
			</fieldset>

			<fieldset>
				<legend><strong><?php esc_html_e( 'Tuỳ chọn', 'saha-core' ); ?></strong></legend>
				<label><input type="radio" name="page_status" value="draft" checked> <?php esc_html_e( 'Trang nhập vào là bản nháp (an toàn)', 'saha-core' ); ?></label><br>
				<label><input type="radio" name="page_status" value="keep"> <?php esc_html_e( 'Giữ trạng thái trang như trong file', 'saha-core' ); ?></label><br>
				<label><input type="checkbox" name="front_page" value="1"> <?php esc_html_e( 'Đặt trang chủ theo file', 'saha-core' ); ?></label><br>
				<label><input type="checkbox" name="replace_templates" value="1"> <?php esc_html_e( 'Thay template đang dùng: template cùng loại hiện có chuyển sang nháp', 'saha-core' ); ?></label><br>
				<label><input type="checkbox" name="dry_run" value="1" checked> <?php esc_html_e( 'Chỉ chạy thử (kiểm tra, không ghi gì)', 'saha-core' ); ?></label>
			</fieldset>

			<p class="description">
				<?php
				/* translators: %d: số ảnh tối đa */
				echo esc_html( sprintf( __( 'Nhập luôn tạo mới — không ghi đè trang / block / template đang có. Ảnh được tải từ website nguồn (tối đa %d ảnh; website nguồn phải truy cập được).', 'saha-core' ), Importer::MAX_MEDIA ) );
				?>
			</p>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Nhập', 'saha-core' ); ?></button></p>
		</form>
	</div>
</div>
<style>
	.saha-ie__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-top: 16px; }
	.saha-ie fieldset { margin: 0 0 16px; }
	.saha-ie__list { max-height: 220px; margin: 6px 0 0; overflow: auto; }
	.saha-ie__list li, .saha-ie__created li, .saha-ie__messages li { margin: 2px 0; }
	.saha-ie__messages { margin-left: 18px; list-style: disc; }
</style>
