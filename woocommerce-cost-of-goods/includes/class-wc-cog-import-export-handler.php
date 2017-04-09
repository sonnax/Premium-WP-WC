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
 * @package     WC-COG/Import-Export-Handler
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Cost of Goods Import/Export Handler
 *
 * Adds support for:
 *
 * + Product CSV Import/Export fields
 * + Customer / Order CSV Export
 *
 * @since 1.0
 */
class WC_COG_Import_Export_Handler {


	/**
	 * Setup class
	 *
	 * @since 1.0
	 */
	public function __construct() {

		// Product CSV Import Suite: export support
		add_filter( 'woocommerce_csv_product_post_columns',           array( $this, 'add_cost_to_csv_product_export_columns' ) );
		add_filter( 'woocommerce_csv_product_variation_post_columns', array( $this, 'add_cost_to_csv_product_export_columns' ) );

		// Product CSV Import Suite: import support
		add_action( 'woocommerce_csv_product_data_mapping',      array( $this, 'add_cost_to_csv_import_mapping' ) );
		add_filter( 'woocommerce_csv_product_postmeta_defaults', array( $this, 'add_cost_to_csv_import_postmeta_defaults' ) );

		// Customer / Order CSV Export column headers/data + line item data
		add_filter( 'wc_customer_order_csv_export_order_headers',              array( $this, 'add_cost_to_csv_export_column_headers' ), 10, 2 );
		add_filter( 'wc_customer_order_csv_export_order_row',                  array( $this, 'add_cost_to_csv_export_column_data' ), 10, 3 );
		add_filter( 'wc_customer_order_csv_export_order_line_item',            array( $this, 'add_cost_to_csv_export_line_item' ), 10, 2  );
		add_filter( 'wc_customer_order_csv_export_order_row_one_row_per_item', array( $this, 'add_cost_to_csv_export_one_row_per_item' ), 10, 2  );

		// Customer / Order XML Export
		if ( function_exists( 'wc_customer_order_xml_export_suite' ) && version_compare( wc_customer_order_xml_export_suite()->get_version(), '2.0.0', '<' ) ) {
			add_filter( 'wc_customer_order_xml_export_suite_order_export_order_list_format', array( $this, 'add_xml_export_order_cost_total' ), 10, 2 );
			add_filter( 'wc_customer_order_xml_export_suite_order_export_line_item_format',  array( $this, 'add_xml_export_order_line_item_costs' ), 10, 3 );
		} else {
			add_filter( 'wc_customer_order_xml_export_suite_order_data',       array( $this, 'add_xml_export_order_cost_total' ), 10, 2 );
			add_filter( 'wc_customer_order_xml_export_suite_order_line_item',  array( $this, 'add_xml_export_order_line_item_costs' ), 10, 3 );
		}
	}


	/** Product CSV Import/Export compat **************************************/


	/**
	 * Adds support for the Product CSV Import Suite exporting by adding the cost of
	 * goods field to the export columns.  This will cause the cost of good
	 * field to be included in the product CSV export file with the column
	 * 'cost_of_good'
	 *
	 * @since 1.0
	 * @param array $columns associative array of column key to name
	 * @return array associative array of column key to name
	 */
	public function add_cost_to_csv_product_export_columns( $columns ) {

		$columns['_wc_cog_cost'] = 'cost_of_good';

		return $columns;
	}


	/**
	 * Adds support for Product CSV Import Suite importing by adding a Product
	 * Data mapping field for wc_cog_cost -> cost_of_good
	 *
	 * @since 1.0
	 * @param string $key Currently selected field key.
	 */
	public function add_cost_to_csv_import_mapping( $key ) {
		?>
		<option <?php selected( $key, 'cost_of_good' ); ?> value="wc_cog_cost">cost_of_good</option>
		<?php
	}


	/**
	 * Adds support for Product CSV Import Suite importing by adding adding a
	 * default for the 'wc_cog_cost' column
	 *
	 * @since 1.0
	 * @param array $defaults associative array of postmeta key to default value
	 * @return array associative array of postmeta key to default value
	 */
	public function add_cost_to_csv_import_postmeta_defaults( $defaults ) {

		$defaults['wc_cog_cost'] = '';

		return $defaults;
	}


	/** Customer/Order CSV Export compat **************************************/


	/**
	 * Adds support for Customer/Order CSV Export by adding an
	 * `order_cost_total` column header
	 *
	 * @since 1.4
	 * @param array $headers existing array of header key/names for the CSV export
	 * @return array
	 */
	public function add_cost_to_csv_export_column_headers( $headers, $csv_generator ) {

		$headers['order_cost_total'] = 'order_cost_total';

		if ( $this->is_one_row_per_item( $csv_generator ) ) {

			// check if `item_total` header exists and add out `item_cost` header after, o/w add to end
			if ( isset( $headers['item_total'] ) ) {
				$headers = SV_WC_Helper::array_insert_after( $headers, 'item_total', array( 'item_cost' => 'item_cost' ) );
			} else {
				$headers['item_cost'] = 'item_cost';
			}
		}

		return $headers;
	}


	/**
	 * Adds support for Customer/Order CSV Export by adding data for the
	 * `order_cost_total` column header
	 *
	 * @since 1.4
	 * @param array $order_data generated order data matching the column keys in the header
	 * @param WC_Order $order order being exported
	 * @param \WC_Customer_Order_CSV_Export_Generator $csv_generator instance
	 * @return array
	 */
	public function add_cost_to_csv_export_column_data( $order_data, $order, $csv_generator ) {

		$total_cost = SV_WC_Order_Compatibility::get_meta( $order, '_wc_cog_order_total_cost' );
		$cost_data  = array( 'order_cost_total' => ! empty( $total_cost ) ? $total_cost : '' );

		$new_order_data = array();

		if ( $this->is_one_row_per_item( $csv_generator ) ) {

			foreach ( $order_data as $data ) {
				$new_order_data[] = array_merge( (array) $data, $cost_data );
			}

		} else {

			$new_order_data = array_merge( $order_data, $cost_data );
		}

		return $new_order_data;
	}


	/**
	 * Adds support for Customer/Order CSV Export by adding a line item entry
	 * in the format: `item_cost`
	 *
	 * @since 1.4
	 * @param array $line_item line item data being exported
	 * @param array $item the WC order item array for the line item
	 * @return array
	 */
	public function add_cost_to_csv_export_line_item( $line_item, $item ) {

		$line_item['item_cost'] = isset( $item['wc_cog_item_total_cost'] ) ? wc_format_decimal( $item['wc_cog_item_total_cost'], 2 ) : '';

		return $line_item;
	}


	/**
	 * Adds support for Customer/Order CSV Export by adding a data for the
	 * `item_cost` column
	 *
	 * @since 1.5.0
	 * @param array $order_data generated order data matching the column keys in the header
	 * @param array $item the line item used by the CSV generator to add order data
	 * @return array
	 */
	public function add_cost_to_csv_export_one_row_per_item( $order_data, $item ) {

		$order_data['item_cost'] = isset( $item['item_cost'] ) ? $item['item_cost'] : '';

		return $order_data;
	}


	/**
	 * Determine if the CSV Export format/format definition are set to export
	 * one row per item
	 *
	 * @since 2.2.2
	 * @param \WC_Customer_Order_CSV_Export_Generator $csv_generator instance
	 * @return bool
	 */
	private function is_one_row_per_item( $csv_generator ) {

		// sanity check - bail if CSV Export is not active, or if the provided paramater is not as expected
		if ( ! function_exists( 'wc_customer_order_csv_export' ) || ! $csv_generator instanceof WC_Customer_Order_CSV_Export_Generator ) {
			return false;
		}

		$one_row_per_item = false;

		// determine if the selected format is "one row per item"
		if ( version_compare( wc_customer_order_csv_export()->get_version(), '4.0.0', '<' ) ) {

			$one_row_per_item = ( 'default_one_row_per_item' === $csv_generator->order_format || 'legacy_one_row_per_item' === $csv_generator->order_format );

		// v4.0.0 - 4.0.2
		} elseif ( ! isset( $csv_generator->format_definition ) ) {

			// get the CSV Export format definition
			$format_definition = wc_customer_order_csv_export()->get_formats_instance()->get_format( $csv_generator->export_type, $csv_generator->export_format );

			$one_row_per_item = isset( $format_definition['row_type'] ) && 'item' === $format_definition['row_type'];

		// v4.0.3+
		} else {

			$one_row_per_item = 'item' === $csv_generator->format_definition['row_type'];
		}

		return $one_row_per_item;
	}


	/** Customer/Order XML Export compat **************************************/


	/**
	 * Add an OrderCostTotal element to the order XML export file
	 *
	 * @since 2.0.0
	 * @param array $data order data
	 * @param \WC_Order $order
	 * @return array
	 */
	public function add_xml_export_order_cost_total( $data, $order ) {

		// sanity check
		if ( ! is_array( $data ) || ! $order instanceof WC_Order ) {
			return $data;
		}

		$data['OrderCostTotal'] = SV_WC_Order_Compatibility::get_meta( $order, '_wc_cog_order_total_cost', true );

		return $data;
	}


	/**
	 * Adds an ItemCost and LineCostTotal elements to the order XML export file
	 *
	 * @since 2.0.0
	 * @param array $line_item XML line item data
	 * @param \WC_Order $order
	 * @param array $item WC item data
	 * @return array
	 */
	public function add_xml_export_order_line_item_costs( $line_item, $order, $item ) {

		// sanity check
		if ( ! is_array( $line_item ) || ! $order instanceof WC_Order || ! is_array( $item ) ) {
			return $line_item;
		}

		// item cost
		$line_item['ItemCost'] = isset( $item['wc_cog_item_cost'] ) ? wc_format_decimal( $item['wc_cog_item_cost'], wc_get_price_decimals() ) : '';

		// line total cost
		$line_item['LineCostTotal'] = isset( $item['wc_cog_item_total_cost'] ) ? wc_format_decimal( $item['wc_cog_item_total_cost'], wc_get_price_decimals() ) : '';

		return $line_item;
	}


}
