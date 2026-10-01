<?php
/**
 * Màn hình "Điều kiện hiển thị" của một template.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

use Saha\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * ConditionsScreen — form cố định theo loại template (không cần JS):
 * mỗi rule dùng được là một dòng (ô chọn / danh sách nhiều lựa chọn / ô ID),
 * hai nhóm "Áp dụng cho" và "Trừ", ưu tiên, đối tượng xem trước trong builder.
 */
final class ConditionsScreen {

	public const PAGE   = 'saha-template-conditions';
	public const ACTION = 'saha_template_conditions';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'page' ), 30 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
	}

	/**
	 * Term theo thứ tự cây (con ngay dưới cha).
	 *
	 * @param \WP_Term[] $terms Term.
	 * @param int        $parent Cha.
	 * @param int        $depth  Độ sâu.
	 * @return array<int, array{0: \WP_Term, 1: int}>
	 */
	private static function tree( array $terms, int $parent = 0, int $depth = 0 ): array {
		$out = array();

		foreach ( $terms as $term ) {
			if ( (int) $term->parent === $parent && $depth < 5 ) {
				$out[] = array( $term, $depth );
				$out   = array_merge( $out, self::tree( $terms, (int) $term->term_id, $depth + 1 ) );
			}
		}

		return $out;
	}

	/**
	 * URL màn hình điều kiện.
	 *
	 * @param int $post_id Template ID.
	 */
	public static function url( int $post_id ): string {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'post' => $post_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Trang ẩn (không có mục menu riêng).
	 */
	public function page(): void {
		// Cha `options.php`: trang không hiện trong menu nhưng vẫn có tiêu đề (cha rỗng → admin-header lỗi Deprecated).
		add_submenu_page( 'options.php', __( 'Điều kiện hiển thị', 'saha-core' ), __( 'Điều kiện hiển thị', 'saha-core' ), Roles::CAP_TEMPLATES, self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Nhãn rule.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'all'           => __( 'Tất cả', 'saha-core' ),
			'front_page'    => __( 'Trang chủ', 'saha-core' ),
			'page'          => __( 'Trang cụ thể', 'saha-core' ),
			'post'          => __( 'Bài viết cụ thể (ID)', 'saha-core' ),
			'product'       => __( 'Sản phẩm cụ thể (ID)', 'saha-core' ),
			'category'      => __( 'Chuyên mục', 'saha-core' ),
			'post_tag'      => __( 'Thẻ bài viết', 'saha-core' ),
			'product_cat'   => __( 'Danh mục sản phẩm', 'saha-core' ),
			'product_brand' => __( 'Thương hiệu', 'saha-core' ),
			'archive_type'  => __( 'Loại trang danh sách', 'saha-core' ),
			'search'        => __( 'Trang tìm kiếm', 'saha-core' ),
			'404'           => __( 'Trang 404', 'saha-core' ),
		);
	}

	/**
	 * Nhãn giá trị archive_type.
	 *
	 * @return array<string, string>
	 */
	public static function archiveLabels(): array {
		return array(
			'shop'             => __( 'Trang Shop', 'saha-core' ),
			'product_taxonomy' => __( 'Danh mục / thương hiệu sản phẩm', 'saha-core' ),
			'blog'             => __( 'Trang blog', 'saha-core' ),
			'post_taxonomy'    => __( 'Chuyên mục / thẻ bài viết', 'saha-core' ),
			'author'           => __( 'Trang tác giả', 'saha-core' ),
			'date'             => __( 'Lưu trữ theo ngày', 'saha-core' ),
		);
	}

	/**
	 * Tóm tắt điều kiện (cột "Áp dụng" trong danh sách template).
	 *
	 * @param int $post_id Template ID.
	 */
	public static function summary( int $post_id ): string {
		$conditions = Repository::conditions( $post_id );
		$labels     = self::labels();
		$parts      = array();

		foreach ( $conditions['include'] as $rule ) {
			$parts[] = self::describe( $rule, $labels );
		}

		if ( ! $parts ) {
			return __( 'Chưa áp dụng — đặt điều kiện', 'saha-core' );
		}

		$out = implode( '; ', $parts );

		if ( $conditions['exclude'] ) {
			$out .= ' — ' . __( 'trừ', 'saha-core' ) . ' ' . implode( '; ', array_map( static fn( array $rule ): string => self::describe( $rule, $labels ), $conditions['exclude'] ) );
		}

		return $out;
	}

	/**
	 * Mô tả một rule.
	 *
	 * @param array<string, mixed>  $rule   Rule.
	 * @param array<string, string> $labels Nhãn.
	 */
	private static function describe( array $rule, array $labels ): string {
		$name  = (string) $rule['rule'];
		$label = $labels[ $name ] ?? $name;

		if ( empty( $rule['value'] ) ) {
			return $label;
		}

		$def    = Conditions::rules()[ $name ];
		$values = array();

		foreach ( (array) $rule['value'] as $value ) {
			if ( 'term' === $def['value'] ) {
				$term     = get_term( (int) $value, (string) $def['source'] );
				$values[] = $term instanceof \WP_Term ? $term->name : '#' . $value;
			} elseif ( 'post' === $def['value'] ) {
				$values[] = get_the_title( (int) $value ) . ' (#' . $value . ')';
			} else {
				$values[] = self::archiveLabels()[ $value ] ?? (string) $value;
			}
		}

		return preg_replace( '/ \(ID\)$/', '', $label ) . ': ' . implode( ', ', $values );
	}

	/**
	 * Ô nhập cho một rule.
	 *
	 * @param string             $group include | exclude.
	 * @param string             $name  Rule.
	 * @param array<int, mixed>  $value Giá trị đang có (null = chưa chọn rule).
	 * @param bool               $on    Rule không giá trị đang bật.
	 */
	private function field( string $group, string $name, array $value, bool $on ): void {
		$def   = Conditions::rules()[ $name ];
		$field = 'saha_cond[' . $group . '][' . $name . ']';
		$id    = 'saha-cond-' . $group . '-' . $name;

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( self::labels()[ $name ] ) . '</label></th><td>';

		switch ( $def['value'] ) {
			case 'none':
				printf( '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>', esc_attr( $id ), esc_attr( $field ), checked( $on, true, false ) );
				break;

			case 'term':
				$terms = taxonomy_exists( (string) $def['source'] ) ? get_terms(
					array(
						'taxonomy'   => (string) $def['source'],
						'hide_empty' => false,
						'number'     => 500,
					)
				) : array();
				printf( '<select id="%1$s" name="%2$s[]" multiple size="6" style="min-width:320px">', esc_attr( $id ), esc_attr( $field ) );
				foreach ( self::tree( is_array( $terms ) ? $terms : array() ) as list( $term, $depth ) ) {
					$prefix = str_repeat( '— ', $depth );
					printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $term->term_id, selected( in_array( (int) $term->term_id, $value, true ), true, false ), esc_html( $prefix . $term->name ) );
				}
				echo '</select><p class="description">' . esc_html__( 'Giữ Ctrl (⌘ trên Mac) để chọn nhiều. Danh mục cha áp cho cả danh mục con.', 'saha-core' ) . '</p>';
				break;

			case 'post':
				if ( 'page' === $def['source'] ) {
					printf( '<select id="%1$s" name="%2$s[]" multiple size="6" style="min-width:320px">', esc_attr( $id ), esc_attr( $field ) );
					foreach ( get_pages( array( 'number' => 300 ) ) as $page ) {
						printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $page->ID, selected( in_array( (int) $page->ID, $value, true ), true, false ), esc_html( get_the_title( $page ) ) );
					}
					echo '</select>';
				} else {
					printf( '<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" placeholder="123, 456">', esc_attr( $id ), esc_attr( $field ), esc_attr( implode( ', ', $value ) ) );
					echo '<p class="description">' . esc_html__( 'ID cách nhau bởi dấu phẩy (xem ID khi rê chuột lên tên trong danh sách).', 'saha-core' ) . '</p>';
				}
				break;

			case 'enum':
				foreach ( self::archiveLabels() as $key => $label ) {
					if ( ! in_array( $key, $this->archiveFor( $name ), true ) ) {
						continue;
					}
					printf( '<label style="margin-right:16px"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>', esc_attr( $field ), esc_attr( $key ), checked( in_array( $key, $value, true ), true, false ), esc_html( $label ) );
				}
				break;
		}

		echo '</td></tr>';
	}

	/**
	 * Giá trị archive_type hợp với loại template đang sửa.
	 *
	 * @param string $name Rule.
	 * @return string[]
	 */
	private function archiveFor( string $name ): array {
		unset( $name );
		$type = Repository::typeOf( absint( wp_unslash( $_GET['post'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ đọc để hiển thị.

		return match ( $type ) {
			'product_archive' => array( 'shop', 'product_taxonomy' ),
			'archive'         => array( 'blog', 'post_taxonomy', 'author', 'date' ),
			default           => Conditions::archiveTypes(),
		};
	}

	/**
	 * Giá trị đang có của một rule trong một nhóm.
	 *
	 * @param array<int, array<string, mixed>> $rules Rule.
	 * @param string                           $name  Tên rule.
	 * @return array{0: bool, 1: array<int, mixed>}
	 */
	private static function current( array $rules, string $name ): array {
		foreach ( $rules as $rule ) {
			if ( $name === $rule['rule'] ) {
				return array( true, (array) ( $rule['value'] ?? array() ) );
			}
		}

		return array( false, array() );
	}

	/**
	 * In màn hình.
	 */
	public function render(): void {
		$post_id = absint( wp_unslash( $_GET['post'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ hiển thị.
		$type    = Repository::typeOf( $post_id );

		if ( '' === $type || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Template không hợp lệ.', 'saha-core' ), 400 );
		}

		$conditions = Repository::conditions( $post_id );
		$rules      = Conditions::rulesByType()[ $type ];
		$priority   = (int) get_post_meta( $post_id, Repository::PRIORITY_META, true );
		$saved      = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ hiển thị.

		echo '<div class="wrap"><h1>' . esc_html(
			sprintf(
				/* translators: 1: tên template, 2: loại */
				__( 'Điều kiện hiển thị: %1$s (%2$s)', 'saha-core' ),
				get_the_title( $post_id ),
				Repository::types()[ $type ]
			)
		) . '</h1>';

		if ( $saved ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Đã lưu điều kiện.', 'saha-core' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Template áp cho trang khớp "Áp dụng cho" và không khớp "Trừ". Nhiều template cùng khớp: điều kiện cụ thể hơn thắng (sản phẩm/trang cụ thể > danh mục > loại trang > tất cả), rồi tới ưu tiên cao hơn.', 'saha-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="post" value="' . (int) $post_id . '">';
		wp_nonce_field( self::ACTION . '_' . $post_id );

		foreach ( array(
			'include' => __( 'Áp dụng cho', 'saha-core' ),
			'exclude' => __( 'Trừ', 'saha-core' ),
		) as $group => $title ) {
			echo '<h2>' . esc_html( $title ) . '</h2><table class="form-table" role="presentation"><tbody>';

			foreach ( $rules as $name ) {
				if ( 'exclude' === $group && 'all' === $name ) {
					continue;
				}

				list( $on, $value ) = self::current( $conditions[ $group ], $name );
				$this->field( $group, $name, $value, $on );
			}

			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Khác', 'saha-core' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="saha-cond-priority">' . esc_html__( 'Ưu tiên', 'saha-core' ) . '</label></th><td><input type="number" id="saha-cond-priority" name="saha_priority" min="-100" max="100" value="' . (int) $priority . '" class="small-text"><p class="description">' . esc_html__( 'Số lớn hơn thắng khi hai template khớp cùng mức cụ thể.', 'saha-core' ) . '</p></td></tr>';
		$this->previewField( $post_id, $type );
		echo '</tbody></table>';

		submit_button( __( 'Lưu điều kiện', 'saha-core' ) );
		echo '</form><p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . Repository::POST_TYPE ) ) . '">← ' . esc_html__( 'Danh sách template', 'saha-core' ) . '</a></p></div>';
	}

	/**
	 * Ô chọn đối tượng xem trước trong builder.
	 *
	 * @param int    $post_id Template ID.
	 * @param string $type    Loại.
	 */
	private function previewField( int $post_id, string $type ): void {
		$current   = (int) get_post_meta( $post_id, Preview::META, true );
		$post_type = Preview::postTypes()[ $type ] ?? '';

		if ( '' !== $post_type ) {
			$items = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'no_found_rows'  => true,
				)
			);
		} elseif ( 'product_archive' === $type && taxonomy_exists( 'product_cat' ) ) {
			$items = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
				)
			);
		} else {
			return;
		}

		echo '<tr><th scope="row"><label for="saha-cond-preview">' . esc_html__( 'Xem trước trong builder bằng', 'saha-core' ) . '</label></th><td><select id="saha-cond-preview" name="saha_preview"><option value="0">' . esc_html__( '— Mới nhất —', 'saha-core' ) . '</option>';

		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$id    = $item instanceof \WP_Term ? (int) $item->term_id : (int) $item->ID;
			$label = $item instanceof \WP_Term ? $item->name : get_the_title( $item );
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $id, selected( $current, $id, false ), esc_html( $label ) );
		}

		echo '</select></td></tr>';
	}

	/**
	 * Lưu.
	 */
	public function save(): void {
		$post_id = absint( wp_unslash( $_POST['post'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- kiểm ngay dưới.

		check_admin_referer( self::ACTION . '_' . $post_id );

		if ( '' === Repository::typeOf( $post_id ) || ! current_user_can( Roles::CAP_TEMPLATES ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Bạn không có quyền sửa template này.', 'saha-core' ), 403 );
		}

		$raw        = isset( $_POST['saha_cond'] ) && is_array( $_POST['saha_cond'] ) ? map_deep( wp_unslash( $_POST['saha_cond'] ), 'sanitize_text_field' ) : array();
		$conditions = array(
			'include' => array(),
			'exclude' => array(),
		);

		foreach ( array( 'include', 'exclude' ) as $group ) {
			foreach ( (array) ( $raw[ $group ] ?? array() ) as $name => $value ) {
				$kind = Conditions::rules()[ $name ]['value'] ?? '';

				if ( 'none' === $kind ) {
					$conditions[ $group ][] = array( 'rule' => $name );
				} elseif ( '' !== $kind ) {
					$values                 = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
					$conditions[ $group ][] = array(
						'rule'  => $name,
						'value' => array_values( array_filter( (array) $values, static fn( $v ): bool => '' !== (string) $v ) ),
					);
				}
			}
		}

		Repository::saveConditions( $post_id, $conditions, (int) sanitize_text_field( wp_unslash( $_POST['saha_priority'] ?? '0' ) ) );
		update_post_meta( $post_id, Preview::META, absint( wp_unslash( $_POST['saha_preview'] ?? 0 ) ) );

		wp_safe_redirect( add_query_arg( 'saved', '1', self::url( $post_id ) ) );
		exit;
	}
}
