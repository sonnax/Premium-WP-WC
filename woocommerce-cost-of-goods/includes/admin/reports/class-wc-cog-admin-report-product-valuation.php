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
 * needs please refer to http://docs.woothemes.com/document/cost-of-goods/ for more information.
 *
 * @package     WC-COG/Reports
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2016, SkyVerge, Inc.
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

		if ( 'value_at_retail' === $column_name ) {

			echo wc_price( $item->value_at_retail );

		} elseif ( 'value_at_cost' === $column_name ) {

			echo wc_price( $item->value_at_cost );

		} else {

			return parent::column_default( $item, $column_name );
		}
	}


	/**
	 * Get all products (except parent variable products) that are published,
	 * managing stock = yes, have at least 1 unit in stock and have a non-zero
	 * positive cost
	 *
	 * @since 2.0.0
	 * @param int $current_page
	 * @param int $per_page
	 */
	public function get_items( $current_page, $per_page ) {
		global $wpdb;

		$this->max_items = 0;
		$this->items     = array();

		$query_from = "FROM {$wpdb->posts} as posts
			INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
			INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
			INNER JOIN {$wpdb->postmeta} AS postmeta3 on posts.ID = postmeta3.post_id
			WHERE posts.post_type IN ( 'product', 'product_variation' )
			AND posts.post_status = 'publish'
			AND postmeta.meta_key = '_manage_stock' AND postmeta.meta_value = 'yes'
			AND postmeta2.meta_key = '_stock' AND CAST(postmeta2.meta_value AS SIGNED) > 0
			AND postmeta3.meta_key = '_wc_cog_cost' AND CAST(postmeta3.meta_value AS DECIMAL) > 0
		";

		// add search query if present
		if ( ! empty( $_POST['s'] ) ) {

			$query_from = $wpdb->prepare( $query_from . ' AND posts.post_title LIKE %s', '%' . $wpdb->esc_like( $_POST['s'] ) . '%' );
		}

		$query_limit = $wpdb->prepare( 'LIMIT %d, %d', ( $current_page - 1 ) * $per_page, $per_page );

		$items = $wpdb->get_results( "SELECT posts.ID as id, posts.post_parent as parent {$query_from} GROUP BY posts.ID ORDER BY posts.post_title DESC {$query_limit};" );

		$this->max_items = $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" );

		foreach ( (array) $items as $item ) {

			$product = wc_get_product( $item->id );

			$total_stock = $product->get_total_stock();

			$item->value_at_retail = $product->get_price() * $total_stock;
			$item->value_at_cost   = WC_COG_Product::get_cost( $product ) * $total_stock;

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
