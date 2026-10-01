<?php
/**
 * Ô cài đặt mega menu trong Giao diện → Menu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\MegaMenu;

use Saha\Core\Blocks\PostType as BlockPostType;

defined( 'ABSPATH' ) || exit;

/**
 * AdminFields — thêm vào mỗi mục menu: Kiểu (Thường / Mega), độ rộng, số cột, Block nội dung.
 *
 * Lưu cùng nút "Lưu menu" của WordPress (nonce `update-nav_menu` của màn hình đó).
 * Customizer cũng gọi `wp_update_nav_menu_item` nhưng không gửi các ô này → giữ nguyên.
 */
final class AdminFields {

	/**
	 * Danh sách block (cache trong request).
	 *
	 * @var array<int, string>|null
	 */
	private ?array $blocks = null;

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'fields' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save' ), 10, 2 );
		add_action( 'wp_update_nav_menu', array( Settings::class, 'compile' ) );
		add_action( 'wp_delete_nav_menu', array( Settings::class, 'compile' ) );
		add_action( 'admin_head-nav-menus.php', array( $this, 'style' ) );
	}

	/**
	 * Block đã xuất bản để chọn làm nội dung mega.
	 *
	 * @return array<int, string>
	 */
	private function blocks(): array {
		if ( null === $this->blocks ) {
			$this->blocks = array();

			foreach ( get_posts(
				array(
					'post_type'      => BlockPostType::NAME,
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			) as $post ) {
				$this->blocks[ (int) $post->ID ] = get_the_title( $post );
			}
		}

		return $this->blocks;
	}

	/**
	 * In ô cài đặt.
	 *
	 * @param int|string $item_id ID mục menu.
	 * @param \WP_Post   $item    Mục menu.
	 */
	public function fields( $item_id, $item ): void {
		unset( $item );

		$item_id  = (int) $item_id;
		$is_mega  = Settings::isMega( $item_id );
		$settings = Settings::get( $item_id );
		$name     = static fn( string $key ): string => 'saha_mega[' . $item_id . '][' . $key . ']';
		$id       = static fn( string $key ): string => 'saha-mega-' . $key . '-' . $item_id;
		?>
		<fieldset class="saha-mega-fields description-wide">
			<legend><?php esc_html_e( 'SAHA — kiểu menu', 'saha-core' ); ?></legend>
			<p>
				<label for="<?php echo esc_attr( $id( 'type' ) ); ?>"><?php esc_html_e( 'Kiểu', 'saha-core' ); ?></label>
				<select id="<?php echo esc_attr( $id( 'type' ) ); ?>" name="<?php echo esc_attr( $name( 'type' ) ); ?>">
					<option value="normal" <?php selected( ! $is_mega ); ?>><?php esc_html_e( 'Thường (menu thả xuống)', 'saha-core' ); ?></option>
					<option value="mega" <?php selected( $is_mega ); ?>><?php esc_html_e( 'Mega menu', 'saha-core' ); ?></option>
				</select>
				<span class="description"><?php esc_html_e( 'Chỉ áp dụng cho mục cấp 1 của menu ngang (desktop). Menu di động vẫn hiện menu con như thường.', 'saha-core' ); ?></span>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id( 'block' ) ); ?>"><?php esc_html_e( 'Nội dung', 'saha-core' ); ?></label>
				<select id="<?php echo esc_attr( $id( 'block' ) ); ?>" name="<?php echo esc_attr( $name( 'blockId' ) ); ?>">
					<option value="0"><?php esc_html_e( '— Menu con chia cột —', 'saha-core' ); ?></option>
					<?php foreach ( $this->blocks() as $block_id => $title ) : ?>
						<option value="<?php echo esc_attr( (string) $block_id ); ?>" <?php selected( $settings['blockId'], $block_id ); ?>>
							<?php
							/* translators: %s: tên block */
							echo esc_html( sprintf( __( 'Block: %s', 'saha-core' ), $title ) );
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="description"><?php esc_html_e( 'Dựng nội dung ở SAHA → Blocks (ảnh, danh mục, sản phẩm…).', 'saha-core' ); ?></span>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id( 'width' ) ); ?>"><?php esc_html_e( 'Độ rộng', 'saha-core' ); ?></label>
				<select id="<?php echo esc_attr( $id( 'width' ) ); ?>" name="<?php echo esc_attr( $name( 'width' ) ); ?>">
					<option value="container" <?php selected( $settings['width'], 'container' ); ?>><?php esc_html_e( 'Bằng khung nội dung', 'saha-core' ); ?></option>
					<option value="full" <?php selected( $settings['width'], 'full' ); ?>><?php esc_html_e( 'Toàn màn hình', 'saha-core' ); ?></option>
					<option value="custom" <?php selected( $settings['width'], 'custom' ); ?>><?php esc_html_e( 'Tuỳ chỉnh', 'saha-core' ); ?></option>
				</select>
				<input type="number" min="300" max="1600" step="10" class="small-text" aria-label="<?php esc_attr_e( 'Độ rộng tuỳ chỉnh (px)', 'saha-core' ); ?>" name="<?php echo esc_attr( $name( 'customWidth' ) ); ?>" value="<?php echo esc_attr( (string) $settings['customWidth'] ); ?>"> px
				<span class="description"><?php esc_html_e( 'Ô px chỉ dùng khi chọn "Tuỳ chỉnh".', 'saha-core' ); ?></span>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id( 'columns' ) ); ?>"><?php esc_html_e( 'Số cột menu con', 'saha-core' ); ?></label>
				<input type="number" min="1" max="6" class="small-text" id="<?php echo esc_attr( $id( 'columns' ) ); ?>" name="<?php echo esc_attr( $name( 'columns' ) ); ?>" value="<?php echo esc_attr( (string) $settings['columns'] ); ?>">
				<span class="description"><?php esc_html_e( 'Khi nội dung là "Menu con chia cột": mỗi mục cấp 2 là một cột, mục cấp 3 là danh sách bên dưới.', 'saha-core' ); ?></span>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Lưu khi bấm "Lưu menu".
	 *
	 * @param int $menu_id ID menu.
	 * @param int $item_id ID mục menu.
	 */
	public function save( $menu_id, $item_id ): void {
		unset( $menu_id );

		$item_id = (int) $item_id;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- kiểm ngay dưới (nonce của màn hình Menu).
		if ( ! isset( $_POST['saha_mega'][ $item_id ] ) || ! is_array( $_POST['saha_mega'][ $item_id ] ) ) {
			return;
		}

		$nonce = isset( $_POST['update-nav-menu-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['update-nav-menu-nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'update-nav_menu' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$input = map_deep( wp_unslash( $_POST['saha_mega'][ $item_id ] ), 'sanitize_text_field' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		Settings::save( $item_id, (string) ( $input['type'] ?? 'normal' ), (array) $input );
	}

	/**
	 * CSS nhỏ cho ô cài đặt.
	 */
	public function style(): void {
		echo '<style>.saha-mega-fields{margin:8px 0;padding:8px 10px;border:1px solid #dcdcde;border-radius:4px}.saha-mega-fields legend{padding:0 4px;font-weight:600}.saha-mega-fields label{margin-right:12px}.saha-mega-fields select{max-width:100%}.saha-mega-fields .description{display:block}</style>';
	}
}
