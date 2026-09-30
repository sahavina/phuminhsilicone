<?php
/**
 * View: danh sách báo giá / lead.
 *
 * @package Saha\Core
 *
 * @var \Saha\Core\Tables\Crm_Table $table   List table.
 * @var string                      $slug    Slug trang.
 * @var string                      $title   Tiêu đề.
 * @var int|null                    $updated Số bản ghi vừa cập nhật.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap saha-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( null !== $updated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: số bản ghi */
					esc_html( _n( 'Đã cập nhật %d bản ghi.', 'Đã cập nhật %d bản ghi.', $updated, 'saha-core' ) ),
					(int) $updated
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $slug ); ?>">
		<?php
		$table->search_box( __( 'Tìm tên, SĐT, email, sản phẩm', 'saha-core' ), 'saha-crm' );
		$table->display();
		?>
	</form>
</div>
