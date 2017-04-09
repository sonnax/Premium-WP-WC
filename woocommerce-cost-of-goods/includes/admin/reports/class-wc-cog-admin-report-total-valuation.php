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

/**
 * Cost of Goods Total Valuation Admin Report Class
 *
 * Handles generating and rendering the Total Valuation report
 *
 * @since 2.1.0
 */
class WC_COG_Admin_Report_Total_Valuation {


	/**
	 * Render the totals
	 *
	 * @since 2.1.0
	 */
	public function output_report() {

		$valuation = $this->get_valuation();

		if ( empty( $valuation ) ) {
			return;
		}

		?>
		<style type="text/css">
			.wc-cogs-total-valuation div { background-color:#323742; width: 200px; max-width: 48%; padding: 10px; border-radius: 3px; color: #FFF; float: left; margin: 5px; }
			.wc-cogs-total-valuation span.title { font-size: 90%; letter-spacing: .15em; text-transform: uppercase; }
			.wc-cogs-total-valuation h3 { color: inherit; font-size: 22px; }
		</style>
		<div id="poststuff" class="woocommerce-reports-wide wc-cogs-total-valuation">
			<div>
				<span class="title"><?php esc_html_e( 'at cost', 'woocommerce-cost-of-goods' ); ?></span>
				<h3 class="amount"><?php echo wc_price( $valuation['at_cost'] ); ?></h3>
			</div>
			<div>
				<span class="title"><?php esc_html_e( 'at retail', 'woocommerce-cost-of-goods' ); ?></span>
				<h3 class="amount"><?php echo wc_price( $valuation['at_retail'] ); ?></h3>
			</div>
		</div>
		<?php
	}


	/**
	 * Get the inventory valuation totals
	 *
	 * @since 2.1.0
	 * @return array
	 */
	public function get_valuation() {

		$product_ids = get_posts( array(
			'post_type'      => array( 'product', 'product_variation' ),
			'fields'         => 'ids',
			'nopaging'       => true,
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
		) );

		$valuation = array( 'at_cost' => 0, 'at_retail' => 0 );

		if ( ! empty( $product_ids ) ) {

			foreach ( $product_ids as $product_id ) {

				$product = wc_get_product( $product_id );

				if ( ! $product->managing_stock() || $product->is_type( 'variable' ) ) {
					continue;
				}

				$stock_qty = (int) $product->get_stock_quantity();
				$cost      = (float) WC_COG_Product::get_cost( $product );
				$price     = (float) $product->get_price();

				$valuation['at_cost']   += $cost * $stock_qty;
				$valuation['at_retail'] += $price * $stock_qty;
			}
		}

		return $valuation;
	}


}
