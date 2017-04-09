<?php
/**
 * WooCommerce Cost of Goods
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Cost of Goods to newer
 * versions in the future. If you wish to customize WooCommerce Cost of Goods for your
 * needs please refer to http://docs.woocommerce.com/document/cost-of-goods/ for more information.
 *
 * @package     WC-COG/Reports
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// WC lazy-loads the report stock class so we have to load it ourselves ¯\_(ツ)_/¯
if ( ! class_exists( 'WC_Report_Stock' ) ) {
	require_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-stock.php' );
}

/**
 * Cost of Goods Product Valuation Admin Report Class
 *
 * Handles generating and rendering the Product Valuation report
 *
 * @since 2.0.0
 */
class WC_COG_Admin_Report_Product_Valuation extends WC_Report_Stock {


	/**
	 * Get the column value for each row
	 *
	 * @since 2.0.0
	 * @see WC_Report_Stock::column_default()
	 * @param \stdClass $item
	 * @param string $column_name
	 */
	public function column_default( $item, $column_name ) {
		$GLOBALS['product'] = $item->product;

		if ( 'value_at_retail' === $column_name ) {

			echo wc_price( $item->value_at_retail );

		} elseif ( 'value_at_cost' === $column_name ) {

			echo wc_price( $item->value_at_cost );

		} else {

			parent::column_default( $item, $column_name );
		}
	}


	/**
	 * Get all simple & variation products that are published and managing stock = yes
	 * and calculate the value at retail & value at cost.
	 *
	 * @since 2.0.0
	 * @param int $current_page
	 * @param int $per_page
	 */
	public function get_items( $current_page, $per_page ) {
		$this->max_items = 0;
		$this->items     = array();

		$args = array(
			'post_type'      => array( 'product', 'product_variation' ),
			'fields'         => 'ids',
			'posts_per_page' => $per_page,
			'offset'         => ( $current_page - 1 ) * $per_page,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		// search query
		if ( ! empty( $_POST['s'] ) ) {
			$args['s'] = $_POST['s'];
		}

		$query = new WP_Query( $args );

		$this->max_items = $query->found_posts;

		foreach ( $query->posts as $product_id ) {

			$product = wc_get_product( $product_id );

			if ( ! $product->managing_stock() || $product->is_type( 'variable' ) ) {
				continue;
			}

			$item          = new stdClass();
			$item->id      = $product->get_id();
			$item->product = $product;

			if ( $product->is_type( 'variation' ) ) {
				$item->parent  = SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ? $product->get_parent_id() : $product->parent->id;
			} else {
				$item->parent = 0;
			}

			$stock_qty = (int) $product->get_stock_quantity();
			$cost      = (float) WC_COG_Product::get_cost( $product );
			$price     = (float) $product->get_price();

			$item->value_at_retail = $price * $stock_qty;
			$item->value_at_cost   = $cost * $stock_qty;

			$this->items[] = $item;
		}
	}


	/**
	 * Define additional columns for the report, "Value at Retail" and "Value at
	 * Cost"
	 *
	 * @since 2.0.0
	 * @see WC_Report_Stock::get_columns()
	 * @return array
	 */
	public function get_columns() {

		$columns = parent::get_columns();

		$new_columns = array(
			'value_at_retail' => __( 'Value at Retail', 'woocommerce-cost-of-goods' ),
			'value_at_cost'   => __( 'Value at Cost', 'woocommerce-cost-of-goods' ),
		);

		$columns = SV_WC_Helper::array_insert_after( $columns, 'parent', $new_columns );

		return $columns;
	}


	/**
	 * Render a product search box for the table
	 *
	 * @since 2.1.0
	 * @param string $context
	 */
	public function display_tablenav( $context ) {

		if ( 'top' !== $context ) {
			parent::display_tablenav( $context );
			return;
		}

		echo '<form method="post"><style type="text/css">#wc-cog-product-valuation-search-input {margin-bottom: 5px;}</style>';
			$this->search_box( __( 'Search for a product', 'woocommerce-cost-of-goods' ), 'wc-cog-product-valuation' );
		echo '</form>';
	}


}
