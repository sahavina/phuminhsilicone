<?php
/**
 * Danh sách element của builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Controls\ControlRegistry;
use Saha\Core\Builder\Elements\Banner;
use Saha\Core\Builder\Elements\Block;
use Saha\Core\Builder\Elements\Button;
use Saha\Core\Builder\Elements\Column;
use Saha\Core\Builder\Elements\Container;
use Saha\Core\Builder\Elements\Cta;
use Saha\Core\Builder\Elements\Divider;
use Saha\Core\Builder\Elements\Element;
use Saha\Core\Builder\Elements\Heading;
use Saha\Core\Builder\Elements\Html;
use Saha\Core\Builder\Elements\Icon;
use Saha\Core\Builder\Elements\IconBox;
use Saha\Core\Builder\Elements\Image;
use Saha\Core\Builder\Elements\Posts;
use Saha\Core\Builder\Elements\ProductCategories;
use Saha\Core\Builder\Elements\Products;
use Saha\Core\Builder\Elements\Row;
use Saha\Core\Builder\Elements\Section;
use Saha\Core\Builder\Elements\Shortcode;
use Saha\Core\Builder\Elements\Spacer;
use Saha\Core\Builder\Elements\Text;

defined( 'ABSPATH' ) || exit;

/**
 * ElementRegistry.
 */
final class ElementRegistry {

	/**
	 * Singleton.
	 *
	 * @var ElementRegistry|null
	 */
	private static ?ElementRegistry $instance = null;

	/**
	 * Element theo type.
	 *
	 * @var array<string, Element>
	 */
	private array $elements = array();

	/**
	 * Lấy registry.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();

			$core = array(
				// Bố cục.
				new Section(),
				new Container(),
				new Row(),
				new Column(),
				new Spacer(),
				new Divider(),
				// Nội dung.
				new Heading(),
				new Text(),
				new Button(),
				new Image(),
				new Icon(),
				new IconBox(),
				new Html(),
				new Shortcode(),
				// Marketing.
				new Banner(),
				new Cta(),
				new Block(),
				// WooCommerce, blog.
				new Products(),
				new ProductCategories(),
				new Posts(),
			);

			foreach ( $core as $element ) {
				self::$instance->register( $element );
			}

			/**
			 * Thêm/bớt element (spec §78).
			 *
			 * add_action( 'saha_builder_elements', fn( $registry ) => $registry->register( new My_Element() ) );
			 *
			 * @param ElementRegistry $registry Registry.
			 */
			do_action( 'saha_builder_elements', self::$instance );
		}

		return self::$instance;
	}

	/**
	 * Xoá singleton (test).
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Đăng ký element.
	 *
	 * @param Element $element Element.
	 */
	public function register( Element $element ): void {
		$type = $element->type();

		if ( preg_match( '/^[a-z][a-z0-9_-]{0,39}$/', $type ) ) {
			$this->elements[ $type ] = $element;
		}
	}

	/**
	 * Gỡ element.
	 *
	 * @param string $type Type.
	 */
	public function unregister( string $type ): void {
		unset( $this->elements[ $type ] );
	}

	/**
	 * Lấy element.
	 *
	 * @param string $type Type.
	 */
	public function get( string $type ): ?Element {
		return $this->elements[ $type ] ?? null;
	}

	/**
	 * Mọi element.
	 *
	 * @return array<string, Element>
	 */
	public function all(): array {
		return $this->elements;
	}

	/**
	 * Control của tab "Nâng cao" — dùng chung cho mọi element.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function advancedControls(): array {
		return array(
			'margin'      => array(
				'type'       => 'spacing',
				'label'      => __( 'Lề ngoài (margin)', 'saha-core' ),
				'section'    => 'advanced',
				'responsive' => true,
				'allowAuto'  => true,
				'min'        => -500,
				'max'        => 1000,
			),
			'padding'     => array(
				'type'       => 'spacing',
				'label'      => __( 'Lề trong (padding)', 'saha-core' ),
				'section'    => 'advanced',
				'responsive' => true,
				'min'        => 0,
				'max'        => 1000,
			),
			'cssId'       => array(
				'type'    => 'htmlId',
				'label'   => __( 'ID (dùng làm neo #)', 'saha-core' ),
				'section' => 'advanced',
			),
			'cssClass'    => array(
				'type'    => 'classList',
				'label'   => __( 'Class CSS', 'saha-core' ),
				'section' => 'advanced',
			),
			'hideDesktop' => array(
				'type'    => 'toggle',
				'label'   => __( 'Ẩn trên desktop', 'saha-core' ),
				'section' => 'advanced',
			),
			'hideTablet'  => array(
				'type'    => 'toggle',
				'label'   => __( 'Ẩn trên tablet', 'saha-core' ),
				'section' => 'advanced',
			),
			'hideMobile'  => array(
				'type'    => 'toggle',
				'label'   => __( 'Ẩn trên mobile', 'saha-core' ),
				'section' => 'advanced',
			),
		);
	}

	/**
	 * Định nghĩa gửi cho editor (GET /builder/elements).
	 *
	 * @return array<string, mixed>
	 */
	public function forClient(): array {
		$controls = ControlRegistry::instance();
		$elements = array();

		foreach ( $this->elements as $type => $element ) {
			$def   = $element->def();
			$items = array();

			foreach ( (array) $def['controls'] as $key => $control ) {
				$items[] = array( 'key' => (string) $key ) + $controls->forClient( (array) $control );
			}

			$elements[] = array(
				'type'            => $type,
				'name'            => (string) $def['name'],
				'icon'            => (string) $def['icon'],
				'category'        => (string) $def['category'],
				'allowedParents'  => array_values( (array) $def['allowedParents'] ),
				'allowedChildren' => array_values( (array) $def['allowedChildren'] ),
				'dynamic'         => (bool) $def['dynamic'],
				'controls'        => $items,
			);
		}

		$advanced = array();

		foreach ( self::advancedControls() as $key => $control ) {
			$advanced[] = array( 'key' => $key ) + $controls->forClient( $control );
		}

		return array(
			'schemaVersion' => Schema\SchemaMigrator::CURRENT,
			'elements'      => $elements,
			'advanced'      => $advanced,
			'breakpoints'   => array(
				'tablet' => Limits::TABLET_MAX,
				'mobile' => Limits::MOBILE_MAX,
			),
			'limits'        => array(
				'maxDepth'    => Limits::MAX_DEPTH,
				'maxElements' => Limits::MAX_ELEMENTS,
				'maxBytes'    => Limits::MAX_BYTES,
			),
		);
	}
}
