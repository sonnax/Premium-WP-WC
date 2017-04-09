<?php
/**
 * Plugin Name: WooCommerce Cost of Goods
 * Plugin URI: http://www.woothemes.com/products/woocommerce-cost-of-goods/
 * Description: A full-featured cost of goods management extension for WooCommerce, with detailed reporting for total cost and profit
 * Author: WooThemes / SkyVerge
 * Author URI: http://www.woothemes.com
 * Version: 2.2.7
 * Text Domain: woocommerce-cost-of-goods
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2013-2016 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Cost-of-Goods
 * @author    SkyVerge
 * @category  Inventory
 * @copyright Copyright (c) 2013-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '9908a60a5feefec5e33b38359f5f6964', '185438' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.4.0', __( 'WooCommerce Costs of Goods', 'woocommerce-cost-of-goods' ), __FILE__, 'init_woocommerce_cost_of_goods', array(
	'minimum_wc_version'   => '2.4.13',
	'minimum_wp_version'   => '4.1',
	'backwards_compatible' => '4.4.0',
) );

function init_woocommerce_cost_of_goods() {

/**
 * # WooCommerce Cost of Goods Main Plugin Class
 *
 * ## Plugin Overview
 *
 * This plugin adds a cost meta field to products that allows the admin to enter costs
 * and have the total cost / profit calculated for an order or product
 *
 * ## Admin Considerations
 *
 * Meta fields are added to the product data tab for simple products and variable products.
 * Settings are added under WooCommerce > Settings > Inventory
 * Custom reports are added into a new tab named Profit found under WooCommerce > Reports
 *
 * ## Database
 *
 * ### Options
 *
 * + `wc_cog_version` - the current plugin version, set on install/upgrade
 *
 * ### Global Settings
 *
 * These settings are found under WooCommerce > Settings > Inventory
 *
 * + `wc_cog_profit_report_exclude_gateway_fees` - "yes" to exclude gateway fees when calculating profit
 * + `wc_cog_profit_report_exclude_shipping_costs` - "yes" to exclude shipping costs when calculating profit
 * + `wc_cog_profit_report_exclude_taxes` - "yes" to exclude taxes when calculating profit
 *
 * ### Simple Product Meta
 *
 * + `_wc_cog_cost` - The cost for the product
 *
 * ### Variable/Variation Product Meta
 *
 * + `_wc_cog_cost_variable` - The default cost for the product variations
 * + `_wc_cog_cost` - The minimum cost among the product variations
 * + `_wc_cog_min_variation_cost` - The minimum cost among the product variations
 * + `_wc_cog_max_variation_cost` - The maximum cost among the product variations
 *
 * + `_wc_cog_cost` - The variation cost *or* default cost from parent variable
 * + `_wc_cog_default_cost` - 'yes' indicates that _wc_cog_cost is the default cost from the parent, 'no' indicates that it is a product variation cost
 *
 * ### Order Meta
 *
 * + `_wc_cog_order_total_cost` - The total cost for the order
 *
 * ### Order Item Meta
 *
 * + `_wc_cog_item_cost` - the cost of the item at the time of purchase
 * + `_wc_cog_item_total_cost` - the total cost of the line item, calculated by multiplying the quantity by the cost
 *
 */
class WC_COG extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '2.2.7';

	/** @var WC_COG single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'cog';

	/** plugin text domain, DEPRECATED as of 1.10.0 */
	const TEXT_DOMAIN = 'woocommerce-cost-of-goods';

	/** @var \WC_COG_Admin instance plugin admin */
	protected $admin;

	/** @var \WC_COG_Admin_Reports instance, le reports */
	protected $admin_reports;

	/** @var \WC_COG_Import_Export_Handler instance, adds support for import/export functionality */
	protected $import_export_handler;

	/** @var \WC_COG_REST_API $api REST API integration class instance */
	protected $rest_api;


	/**
	 * Initialize the plugin
	 *
	 * @since 1.0
	 */
	public function __construct() {

		parent::__construct( self::PLUGIN_ID, self::VERSION );

		// include required files
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'includes' ) );

		// set the order meta when an order is placed from standard checkout
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'set_order_cost_meta' ), 10, 1 );

		// set the order meta when an order is created from the API
		add_action( 'woocommerce_api_create_order', array( $this, 'set_order_cost_meta' ), 10, 1 );

		// add support for orders programmatically added by the ebay WP-Lister plugin
		add_action( 'wplister_after_create_order', array( $this, 'set_order_cost_meta' ), 10, 1 );
	}


	/**
	 * Include required files
	 *
	 * @since 1.0
	 */
	public function includes() {

		// COG product functions
		require_once( $this->get_plugin_path() . '/includes/class-wc-cog-product.php' );

		// REST API integration class
		$this->rest_api = $this->load_class( '/includes/class-wc-cog-rest-api.php', 'WC_COG_REST_API' );

		if ( is_admin() ) {
			$this->admin_includes();
		}
	}


	/**
	 * Include required admin files
	 *
	 * @since 1.0
	 */
	private function admin_includes() {

		// admin
		$this->admin = $this->load_class( '/includes/admin/class-wc-cog-admin.php', 'WC_COG_Admin' );

		// reports
		$this->admin_reports = $this->load_class( '/includes/admin/class-wc-cog-admin-reports.php', 'WC_COG_Admin_Reports' );

		// import/export handler
		$this->import_export_handler = $this->load_class( '/includes/class-wc-cog-import-export-handler.php', 'WC_COG_Import_Export_Handler' );
	}


	/**
	 * Return admin class instance
	 *
	 * @since 2.0.0
	 * @return \WC_COG_Admin
	 */
	public function get_admin_instance() {
		return $this->admin;
	}


	/**
	 * Return the admin reports class instance
	 *
	 * @since 2.0.0
	 */
	public function get_admin_reports_instance() {
		return $this->admin_reports;
	}


	/**
	 * Return the import/export handler class instance
	 *
	 * @since 2.0.0
	 * @return \WC_COG_Import_Export_Handler
	 */
	public function get_import_export_handler_instance() {
		return $this->import_export_handler;
	}


	/**
	 * Return the REST API class instance
	 *
	 * @since 2.0.0
	 * @return \WC_COG_REST_API
	 */
	public function get_rest_api_instance() {
		return $this->rest_api;
	}


	/**
	 * Backwards compat for changing the visibility of some class instances.
	 *
	 * @TODO Remove this as part of WC 2.7 compat {BR 2016-05-18}
	 *
	 * @since 2.0.0
	 */
	public function __get( $name ) {

		switch ( $name ) {

			case 'admin':
				_deprecated_function( 'wc_cog()->admin', '2.0.0', 'wc_cog()->get_admin_instance()' );
				return $this->get_admin_instance();

			case 'reports':
				_deprecated_function( 'wc_cog()->reports', '2.0.0', 'wc_cog()->get_admin_reports_instance()' );
				return $this->get_admin_reports_instance();

			case 'import_export_handler':
				_deprecated_function( 'wc_cog()->import_export_handler', '2.0.0', 'wc_cog()->get_import_export_handler_instance()' );
				return $this->get_import_export_handler_instance();
		}

		// you're probably doing it wrong
		trigger_error( 'Call to undefined property ' . __CLASS__ . '::' . $name, E_USER_ERROR );
		return null;
	}


	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {

		load_plugin_textdomain( 'woocommerce-cost-of-goods', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/** Checkout processing methods *******************************************/

	/**
	 * Set the cost of goods meta for a given order.
	 *
	 * @since 1.9.0
	 * @param int $order_id The order ID.
	 */
	public function set_order_cost_meta( $order_id ) {

		// get the order object.
		$order = wc_get_order( $order_id );

		$total_cost = 0;

		// loop through the order items and set their cost meta.
		foreach ( $order->get_items() as $item_id => $item ) {

			$product_id = ( ! empty( $item['variation_id'] ) ) ? $item['variation_id'] : $item['product_id'];

			$item_cost = WC_COG_Product::get_cost( $product_id );

			/**
			 * Order Item Cost Filer.
			 *
			 * Allow actors to modify the item cost before the meta is updated.
			 *
			 * @since 1.9.0
			 * @param float|string $item_cost order item cost to set
			 * @param array $item order item
			 * @param \WC_Order $order order object
			 */
			$item_cost = apply_filters( 'wc_cost_of_goods_set_order_item_cost_meta_item_cost', $item_cost, $item, $order );

			$this->set_item_cost_meta( $item_id, $item_cost, $item['qty'] );

			// add to the item cost to the total order cost.
			$total_cost += ( $item_cost * $item['qty'] );
		}

		/**
		 * Order Total Cost Filter.
		 *
		 * Allow actors to modify the order total cost before the meta is updated.
		 *
		 * @since 1.9.0
		 * @param float|string $total_cost order total cost to set
		 * @param \WC_Order $order order object
		 */
		$total_cost = apply_filters( 'wc_cost_of_goods_set_order_cost_meta', $total_cost, $order );

		$formatted_total_cost = wc_format_decimal( $total_cost, wc_get_price_decimals() );

		// save the order total cost meta.
		update_post_meta( $order_id, '_wc_cog_order_total_cost', $formatted_total_cost );
	}


	/**
	 * Set an item's cost meta.
	 *
	 * @since 1.9.0
	 * @param int $item_id item ID
	 * @param float|string $item_cost item cost
	 * @param int $quantity number of items in the order
	 */
	protected function set_item_cost_meta( $item_id, $item_cost = '0', $quantity ) {

		// format the single item cost
		$formatted_cost = wc_format_decimal( $item_cost );

		// format the total item cost
		$formatted_total = wc_format_decimal( $item_cost * $quantity );

		wc_update_order_item_meta( $item_id, '_wc_cog_item_cost', $formatted_cost );
		wc_update_order_item_meta( $item_id, '_wc_cog_item_total_cost', $formatted_total );
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Cost of Goods Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.6.0
	 * @see wc_cog()
	 * @return WC_COG
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.3
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Cost of Goods', 'woocommerce-cost-of-goods' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.3
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Gets the URL to the settings page
	 *
	 * @since 1.3
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @param string $_ unused
	 * @return string URL to the settings page
	 */
	public function get_settings_url( $_ = '' ) {
		return admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' );
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 1.8.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/cost-of-goods-sold/';
	}


	/**
	 * Returns true if on the plugin settings page
	 *
	 * @since 1.3
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {
		return isset( $_GET['page'] )    && 'wc-settings' === $_GET['page']     &&
		       isset( $_GET['tab'] )     && 'products'    === $_GET['tab']      &&
		       isset( $_GET['section'] ) && 'inventory'   === $_GET['section'];
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.8.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {
		return 'http://support.woothemes.com/';
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @since 1.0
	 */
	public function install() {

		require_once( $this->get_plugin_path() . '/includes/admin/class-wc-cog-admin.php' );

		// install default settings
		foreach ( WC_COG_Admin::get_global_settings() as $setting ) {

			if ( isset( $setting['default'] ) ) {
				update_option( $setting['id'], $setting['default'] );
			}
		}
	}


	/**
	 * Perform any version-related changes
	 *
	 * @since 1.0
	 * @param int $installed_version the currently installed version of the plugin
	 */
	public function upgrade( $installed_version ) {

		$this->installed_version = $installed_version;

		add_action( 'woocommerce_after_register_taxonomy', array( $this, 'delayed_upgrade' ) );
	}


	/**
	 * Performs a delayed upgrade, as the WC taxonomies need to load first
	 *
	 * @since 1.3
	 */
	public function delayed_upgrade() {

		$installed_version = wc_cog()->installed_version;

		// upgrade code

		// in this version we add the cost/min/max costs for variable products
		if ( version_compare( $installed_version, '1.1', '<' ) ) {

			// page through the variable products in blocks to avoid out of memory errors
			$offset         = (int) get_option( 'wc_cog_variable_product_offset', 0 );
			$posts_per_page = 500;

			do {
				// grab a set of variable product ids
				$product_ids = get_posts( array(
					'post_type'      => 'product',
					'fields'         => 'ids',
					'offset'         => $offset,
					'posts_per_page' => $posts_per_page,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => array( 'variable' ),
							'operator' => 'IN',
						),
					),
				) );

				// some sort of bad database error: deactivate the plugin and display an error
				if ( is_wp_error( $product_ids ) ) {
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					deactivate_plugins( 'woocommerce-cost-of-goods/woocommerce-cost-of-goods.php' );

					/* translators: Placeholders: %s - error messages */
					wp_die( sprintf( __( 'Error upgrading <strong>WooCommerce Cost of Goods</strong>: %s', 'woocommerce-cost-of-goods' ), '<ul><li>' . implode( '</li><li>', $product_ids->get_error_messages() ) . '</li></ul>' ) .
						'<a href="' . admin_url( 'plugins.php' ) . '">' . __( '&laquo; Go Back', 'woocommerce-cost-of-goods' ) . '</a>' );
				}

				// otherwise go through the results and set the min/max/cost
				if ( is_array( $product_ids ) ) {

					foreach ( $product_ids as $product_id ) {

						$cost = WC_COG_Product::get_cost( $product_id );

						if ( '' === $cost ) {

							// get the minimum and maximum costs associated with the product
							list( $min_variation_cost, $max_variation_cost ) = WC_COG_Product::get_variable_product_min_max_costs( $product_id );

							update_post_meta( $product_id, '_wc_cog_cost',               wc_format_decimal( $min_variation_cost ) );
							update_post_meta( $product_id, '_wc_cog_min_variation_cost', wc_format_decimal( $min_variation_cost ) );
							update_post_meta( $product_id, '_wc_cog_max_variation_cost', wc_format_decimal( $max_variation_cost ) );

						}
					}
				}

				// increment offset
				$offset += $posts_per_page;

				// and keep track of how far we made it in case we hit a script timeout
				update_option( 'wc_cog_variable_product_offset', $offset );

			} while ( count( $product_ids ) === $posts_per_page );  // while full set of results returned  (meaning there may be more results still to retrieve)

		}

		// in this version we are setting any variable product default costs, at the variation level with an indicator
		if ( version_compare( $installed_version, '1.3.3', '<' ) ) {

			// page through the variable products in blocks to avoid out of memory errors
			$offset         = (int) get_option( 'wc_cog_variable_product_offset2', 0 );
			$posts_per_page = 500;

			do {
				// grab a set of variable product ids
				$product_ids = get_posts( array(
					'post_type'      => 'product',
					'fields'         => 'ids',
					'offset'         => $offset,
					'posts_per_page' => $posts_per_page,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => array( 'variable' ),
							'operator' => 'IN',
						),
					),
				) );

				// some sort of bad database error: deactivate the plugin and display an error
				if ( is_wp_error( $product_ids ) ) {
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					deactivate_plugins( 'woocommerce-cost-of-goods/woocommerce-cost-of-goods.php' );  // hardcode the plugin path so that we can use symlinks in development

					/* translators: Placeholders: %s - error messages */
					wp_die( sprintf( __( 'Error upgrading <strong>WooCommerce Cost of Goods</strong>: %s', 'woocommerce-cost-of-goods' ), '<ul><li>' . implode( '</li><li>', $product_ids->get_error_messages() ) . '</li></ul>' ) .
						'<a href="' . admin_url( 'plugins.php' ) . '">' . __( '&laquo; Go Back', 'woocommerce-cost-of-goods' ) . '</a>' );
				}

				// otherwise go through the results and set the min/max/cost
				if ( is_array( $product_ids ) ) {

					foreach ( $product_ids as $product_id ) {

						$default_cost = get_post_meta( $product_id, '_wc_cog_cost_variable', true );

						// get all child variations
						$children = get_posts( array(
							'post_parent'    => $product_id,
							'posts_per_page' => -1,
							'post_type'      => 'product_variation',
							'fields'         => 'ids',
							'post_status'    => 'publish',
						) );

						if ( $children ) {

							foreach ( $children as $child_product_id ) {

								// cost set at the child level?
								$cost = get_post_meta( $child_product_id, '_wc_cog_cost', true );

								if ( '' === $cost && '' !== $default_cost ) {
									// using the default parent cost
									update_post_meta( $child_product_id, '_wc_cog_cost', wc_format_decimal( $default_cost ) );
									update_post_meta( $child_product_id, '_wc_cog_default_cost', 'yes' );
								} else {
									// otherwise no default cost
									update_post_meta( $child_product_id, '_wc_cog_default_cost', 'no' );
								}
							}
						}

					}
				}

				// increment offset
				$offset += $posts_per_page;

				// and keep track of how far we made it in case we hit a script timeout
				update_option( 'wc_cog_variable_product_offset2', $offset );

			} while ( count( $product_ids ) === $posts_per_page );  // while full set of results returned  (meaning there may be more results still to retrieve)
		}
	}


} // end \WC_COG class


/**
 * Returns the One True Instance of <plugin>
 *
 * @since 1.6.0
 * @return WC_COG
 */
function wc_cog() {
	return WC_COG::instance();
}

// fire it up!
wc_cog();

} // init_woocommerce_cost_of_goods()
