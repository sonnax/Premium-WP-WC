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

/**
 * Cost of Goods Admin Reports Class
 *
 * @since 2.0.0
 */
class WC_COG_Admin_Reports {


	/**
	 * Bootstrap class
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		// add reports to WC
		add_filter( 'woocommerce_admin_reports', array( $this, 'add_reports' ) );

		// clear report transients when orders are updated
		add_action( 'woocommerce_delete_shop_order_transients', array( $this, 'clear_report_transients' ) );
	}


	/**
	 * Adds a 'Profit' tab with associated reports to the WC admin reports area,
	 * as well as inventory valuation reports under the 'Stock' tab
	 *
	 * @since 2.0.0
	 * @param array $core_reports
	 * @return array
	 */
	public function add_reports( $core_reports ) {

		$profit_reports = array(
			'profit' => array(
				'title'   => __( 'Profit', 'woocommerce-cost-of-goods' ),
				'reports' => array(
					'profit_by_date'     => array(
						'title'       => __( 'Profit by date', 'woocommerce-cost-of-goods' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( $this, 'load_report' )
					),
					'profit_by_product'  => array(
						'title'       => __( 'Profit by product', 'woocommerce-cost-of-goods' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( $this, 'load_report' )
					),
					'profit_by_category' => array(
						'title'       => __( 'Profit by category', 'woocommerce-cost-of-goods' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( $this, 'load_report' )
					),
				),
			),
		);

		$stock_reports = array(
			'product_valuation' => array(
				'title'       => __( 'Product Valuation', 'woocommerce-cost-of-goods' ),
				'description' => '',
				'hide_title'  => false,
				'function'    => array( $this, 'load_report' ),
			),
			'total_valuation' => array(
				'title'       => __( 'Total Valuation', 'woocommerce-cost-of-goods' ),
				'description' => __( 'Total valuation provides the value of all inventory within your store at both the cost of the good, as well as the total value of inventory at the retail price (regular price, or sale price if set). Stock count must be set to be included in this valuation.', 'woocommerce-cost-of-goods' ),
				'hide_title'  => false,
				'function'    => array( $this, 'load_report' ),
			),
		);

		// add Profit reports tab immediately after Orders
		$core_reports = SV_WC_Helper::array_insert_after( $core_reports, 'orders', $profit_reports );

		// add inventory valuation chart
		if ( isset( $core_reports['stock']['reports'] ) ) {
			$core_reports['stock']['reports'] = array_merge( $core_reports['stock']['reports'], $stock_reports );
		}

		return $core_reports;
	}


	/**
	 * Callback to load and output the given report
	 *
	 * @since 2.0.0
	 * @param string $name report name, as defined in the add_reports() array above
	 */
	public function load_report( $name ) {

		$name     = sanitize_title( $name );
		$filename = sprintf( 'class-wc-cog-admin-report-%s.php', str_replace( '_', '-', $name ) );

		// abstract class first
		require_once( wc_cog()->get_plugin_path() . '/includes/admin/reports/abstract-wc-cog-admin-report.php' );

		// then report class
		$report = wc_cog()->load_class( "/includes/admin/reports/$filename", 'WC_COG_Admin_Report_' . $name );

		$report->output_report();
	}


	/**
	 * Clear report transients when shop order transients are cleared, e.g. order
	 * update/save, etc. This is also called directly when an order line item cost
	 * is edited manually from the edit order screen.
	 *
	 * @since 2.0.0
	 */
	public function clear_report_transients() {

		foreach ( array( 'date', 'product', 'category' ) as $report ) {

			delete_transient( "wc_cog_admin_report_profit_by_{$report}" );
		}
	}


}
