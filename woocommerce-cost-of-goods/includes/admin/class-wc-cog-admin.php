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
 * @package     WC-COG/Admin
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2016, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Cost of Goods Admin Class
 *
 * Adds general COG settings and loads the orders/product admin classes
 *
 * @since 1.0
 */
class WC_COG_Admin {


	/** @var \WC_COG_Admin_Orders class instance */
	protected $orders;

	/** @var \WC_COG_Admin_Products class instance */
	protected $products;


	/**
	 * Bootstrap class
	 *
	 * @since 1.0
	 */
	public function __construct() {

		$this->init_hooks();

		$this->load_classes();
	}


	/**
	 * Initialize hooks
	 *
	 * @since 2.0.0
	 */
	protected function init_hooks() {

		// add general settings
		add_filter( 'woocommerce_inventory_settings', array( $this, 'add_global_settings' ) );

		// Add a apply costs woocommerce_admin_fields() field type
		add_action( 'woocommerce_admin_field_wc_cog_apply_costs_to_previous_orders', array( $this, 'render_apply_costs_section' ) );

		// render the "apply costs" javascript handler
		add_action( 'woocommerce_settings_start', array( $this, 'render_apply_costs_javascript' ) );

		// handle any settings page actions (apply costs to previous orders)
		add_action( 'admin_init', array( $this, 'handle_settings_actions' ) );

		// show messages for settings page actions (apply costs to previous orders)
		add_action( 'woocommerce_settings_start', array( $this, 'render_settings_actions_messages' ) );

		// load styles/scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'load_styles_scripts' ) );

		if ( ! is_ajax() ) {
			// load admin message handler
			add_action( 'init', array( $this, 'load_message_handler' ), 5 );
		}
	}


	/**
	 * Load Orders/Products admin classes
	 *
	 * @since 2.0.0
	 */
	protected function load_classes() {

		$this->orders   = wc_cog()->load_class( '/includes/admin/class-wc-cog-admin-orders.php', 'WC_COG_Admin_Orders' );
		$this->products = wc_cog()->load_class('/includes/admin/class-wc-cog-admin-products.php', 'WC_COG_Admin_Products' );
	}


	/**
	 * Return the admin orders class instance
	 *
	 * @since 2.0.0
	 * @return \WC_COG_Admin_Orders
	 */
	public function get_orders_instance() {

		return $this->orders;
	}


	/**
	 * Return the admin products class instance
	 *
	 * @since 2.0.0
	 * @return \WC_COG_Admin_Products
	 */
	public function get_products_instance() {

		return $this->products;
	}


	/**
	 * Inject global settings into the Settings > Products > Inventory page, immediately after the 'Inventory Options' section
	 *
	 * @since 1.0
	 * @param array $settings associative array of WooCommerce settings
	 * @return array associative array of WooCommerce settings
	 */
	public function add_global_settings( $settings ) {

		$updated_settings = array();

		foreach ( $settings as $setting ) {

			$updated_settings[] = $setting;

			// add settings after `product_inventory_options` section
			if ( isset( $setting['id'] ) && 'product_inventory_options' === $setting['id']
				 && isset( $setting['type'] ) && 'sectionend' === $setting['type'] ) {
				$updated_settings = array_merge( $updated_settings, $this->get_global_settings() );
			}
		}

		return $updated_settings;
	}


	/**
	 * Returns the global settings array for the plugin
	 *
	 * @since 1.0
	 * @return array the global settings
	 */
	public static function get_global_settings() {

		return apply_filters( 'wc_cog_global_settings', array(

			// section start
			array(
				'name' => __( 'Cost of Goods Options', 'woocommerce-cost-of-goods' ),
				'type' => 'title',
				'id'   => 'wc_cog_global_settings',
			),

			// include fees
			array(
				'title'         => __( 'Exclude these item(s) from income when calculating profit. ', 'woocommerce-cost-of-goods' ),
				'desc'          => __( 'Fees charged to customer (e.g. Checkout Add-Ons, Payment Gateway Based Fees)', 'woocommerce-cost-of-goods' ),
				'id'            => 'wc_cog_profit_report_exclude_gateway_fees',
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
			),

			// include shipping costs
			array(
				'desc'          => __( 'Shipping charged to customer', 'woocommerce-cost-of-goods' ),
				'id'            => 'wc_cog_profit_report_exclude_shipping_costs',
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
			),

			// include taxes
			array(
				'desc'          => __( 'Tax charged to customer', 'woocommerce-cost-of-goods' ),
				'id'            => 'wc_cog_profit_report_exclude_taxes',
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => 'end',
			),

			// custom section for applying costs to previous orders
			array(
				'id'          => 'wc_cog_apply_costs_to_previous_orders',
				'type'        => 'wc_cog_apply_costs_to_previous_orders',
			),

			// section end
			array( 'type' => 'sectionend', 'id' => 'wc_cog_profit_reports' ),

		) );
	}


	/**
	 * Render the 'Apply Costs to all previous orders' section
	 *
	 * @since 1.1
	 * @param array $field associative array of field parameters
	 */
	public function render_apply_costs_section( $field ) {

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php esc_html_e( 'Apply Costs to Previous Orders', 'woocommerce-cost-of-goods' ); ?></label>
				<?php echo SV_WC_Plugin_Compatibility::wc_help_tip( __( 'This will apply costs to previous orders based on your selection when "Apply Costs" is clicked and cannot be reversed.', 'woocommerce-cost-of-goods' ) ); ?>
			</th>
			<td class="forminp forminp-<?php echo sanitize_html_class( $field['type'] ) ?>">
				<fieldset>
					<ul>
						<li><label><input name="wc_cog_apply_costs_option" id="wc_cog_apply_cost_option" type="radio" checked="checked" /><?php esc_html_e( 'Apply costs to orders that do not have costs set', 'woocommerce-cost-of-goods' );?></label></li>
						<li><label><input name="wc_cog_apply_costs_option" id="wc_cog_apply_costs_option_all" type="radio" /><?php esc_html_e( 'Apply costs to all orders, overriding previous costs.', 'woocommerce-cost-of-goods' );?></label></li>
					</ul>
					<span class="description"><?php esc_html_e( 'Choose carefully, there is no reversing either action!', 'woocommerce-cost-of-goods' ); ?></span>
				</fieldset>
				<fieldset>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'apply_costs' ) ) ); ?>" class="button" id="<?php echo esc_attr( $field['id'] ); ?>"><?php esc_html_e( 'Apply Costs', 'woocommerce-cost-of-goods' ); ?></a>
				</fieldset>
			</td>
		</tr>
		<?php
	}


	/**
	 * Render the cost of goods "Apply Costs" button handler JS
	 *
	 * @since 1.1
	 */
	public function render_apply_costs_javascript() {

		if ( wc_cog()->is_plugin_settings() ) {

			$confirm_message = __( 'Are you sure you want to apply costs to all previous orders that have not already had costs generated? This cannot be reversed! Note that this can take some time in shops with a large number of orders, if an error occurs, simply Apply Costs again to continue the process.', 'woocommerce-cost-of-goods' );
			$confirm_message_all = __( 'Are you sure you want to apply costs to all previous orders, overriding those with existing costs? This cannot be reversed! Note that this can take some time in shops with a large number of orders, if an error occurs, simply Apply Costs again to continue the process.', 'woocommerce-cost-of-goods' );

			wc_enqueue_js( "
				// confirm admin wants to apply costs to all previous orders
				$( '#wc_cog_apply_costs_to_previous_orders' ).click( function() {

					var confirmMessage = '" . esc_js( $confirm_message ) . "';
					var confirmMessageAll = '" . esc_js( $confirm_message_all ) . "';

					if ( $( '#wc_cog_apply_costs_option_all' ).is( ':checked' ) ) {
						confirmMessage = confirmMessageAll;
					}

					if ( ! confirm( confirmMessage ) ) {
						return false;
					} else if ( $( '#wc_cog_apply_costs_option_all' ).is( ':checked' ) ) {
						$( '#wc_cog_apply_costs_to_previous_orders' ).attr( 'href', $( '#wc_cog_apply_costs_to_previous_orders' ).attr( 'href' ) + '_all' );
					}
				} );
			" );
		}
	}


	/**
	 * Handles any cost of goods setting page actions.  The only available
	 * action is to apply costs to previous orders, useful when the plugin
	 * is first installed
	 *
	 * @since 1.1
	 */
	public function handle_settings_actions() {

		$current_action = ( empty( $_REQUEST['action'] ) )  ? null : sanitize_text_field( urldecode( $_REQUEST['action'] ) );

		if ( wc_cog()->is_plugin_settings() ) {

			if ( 'apply_costs' === $current_action || 'apply_costs_all' === $current_action ) {

				// try and avoid timeouts as best we can
				@set_time_limit( 0 );

				// perform the action in manageable chunks
				$success_count  = 0;
				$offset         = get_option( 'wc_cog_apply_costs_offset', 0 );
				$posts_per_page = 500;

				do {

					// grab a set of order ids for existing orders with no costs set
					$query_args = array(
						'post_type'      => 'shop_order',
						'fields'         => 'ids',
						'offset'         => $offset,
						'posts_per_page' => $posts_per_page,
						'post_status'    => 'any',
					);

					// if we're only applying costs to orders that don't already have a cost
					if ( 'apply_costs' === $current_action ) {

						$query_args['meta_query'] = array(
							array(
								'key'     => '_wc_cog_order_total_cost',
								'compare' => 'NOT EXISTS'
							),
						);
					}

					$order_ids = get_posts( $query_args );

					// some sort of database error
					if ( is_wp_error( $order_ids ) ) {

						wc_cog()->get_message_handler()->add_error( __( 'Database error while applying product costs.', 'woocommerce-cost-of-goods' ) );

						$redirect_url = remove_query_arg( array( 'action' ), stripslashes( $_SERVER['REQUEST_URI'] ) );
						wp_redirect( esc_url_raw( $redirect_url ) );
						exit;

					}

					// otherwise go through the results and set the order cost
					if ( is_array( $order_ids ) ) {

						foreach ( $order_ids as $order_id ) {

							// set costs
							wc_cog()->set_order_cost_meta( $order_id );

							// account for refunds
							$order = wc_get_order( $order_id );

							foreach ( $order->get_refunds() as $refund ) {

								$this->get_orders_instance()->add_refund_order_costs( $refund->id );
							}

							$success_count++;
						}
					}

					// increment offset if we're applying costs to *all* existing orders, paging through them set by set
					if ( 'apply_costs_all' === $current_action ) {
						$offset += $posts_per_page;
						update_option( 'wc_cog_apply_costs_offset', $offset );
					}

				} while ( count( $order_ids ) === $posts_per_page );  // while full set of results returned  (meaning there may be more results still to retrieve)

				delete_option( 'wc_cog_apply_costs_offset' );

				// success message
				/* translators: Placeholders: %d - number of orders updated. */
				wc_cog()->get_message_handler()->add_message( sprintf( _n( '%d order updated.', '%d orders updated.', $success_count, 'woocommerce-cost-of-goods' ), $success_count ) );

				// clear report transients
				wc_cog()->get_admin_reports_instance()->clear_report_transients();

				$redirect_url = remove_query_arg( array( 'action' ), stripslashes( $_SERVER['REQUEST_URI'] ) );
				wp_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		}
	}


	/**
	 * Render any messages set by applying costs to all orders
	 *
	 * @since 1.3
	 */
	public function render_settings_actions_messages() {

		if ( wc_cog()->is_plugin_settings() ) {

			wc_cog()->get_message_handler()->show_messages();
		}
	}


	/**
	 * Load admin message handler class. This is done on `init` so messages
	 * can be loaded properly via the transient. Once COGs is upgraded to 4.3.0+
	 * of the framework, this can be removed.
	 *
	 * @since 2.0.1
	 */
	public function load_message_handler() {

		wc_cog()->get_message_handler();
	}


	/**
	 * Load admin styles and scripts
	 *
	 * @since 1.8.0
	 * @param string $hook_suffix the current URL filename, ie edit.php, post.php, etc
	 */
	public function load_styles_scripts( $hook_suffix ) {
		global $post_type;

		if ( in_array( $post_type, array( 'product', 'shop_order' ) ) && in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php' ) ) ) {

			$dependencies = 'products' === $post_type ? array( 'jquery', 'wc-admin-product-meta-boxes', 'woocommerce_admin' ) : array( 'jquery', 'woocommerce_admin' );

			wp_enqueue_script( 'wc-cog-admin', wc_cog()->get_plugin_url() . '/assets/js/admin/wc-cog-admin.min.js', $dependencies, WC_COG::VERSION );

			wp_localize_script( 'wc-cog-admin', 'wc_cog_admin', array(
				'ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'get_cost_of_goods_nonce'    => wp_create_nonce( 'get-cost-of-goods' ),
				'update_cost_of_goods_nonce' => wp_create_nonce( 'update-cost-of-goods' ),
			) );
		}
	}


}
