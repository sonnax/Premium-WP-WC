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
				<h3 class="amount"><?php echo wc_price( $valuation->cost ); ?></h3>
			</div>
			<div>
				<span class="title"><?php esc_html_e( 'at retail', 'woocommerce-cost-of-goods' ); ?></span>
				<h3 class="amount"><?php echo wc_price( $valuation->retail ); ?></h3>
			</div>
		</div>
		<?php
	}


	/**
	 * Get the inventory valuation totals a standard class, with properties
	 * 'cost' and 'retail'
	 *
	 * @since 2.1.0
	 * @return stdClass
	 */
	public function get_valuation() {
		global $wpdb;

		return $wpdb->get_row( "
			SELECT sum(stock.meta_value * cost.meta_value) AS cost, sum(stock.meta_value * price.meta_value) AS retail
			FROM {$wpdb->posts} AS posts
				INNER JOIN {$wpdb->postmeta} AS stock ON posts.ID = stock.post_id
				INNER JOIN {$wpdb->postmeta} AS price ON posts.ID = price.post_id
				INNER JOIN {$wpdb->postmeta} AS cost ON posts.ID = cost.post_id
			WHERE posts.post_type IN ( 'product', 'product_variation' )
			AND posts.post_status = 'publish'
			AND stock.meta_key = '_stock' AND CAST(stock.meta_value AS SIGNED) > 0
			AND cost.meta_key = '_wc_cog_cost' AND CAST(cost.meta_value AS DECIMAL) > 0
			AND price.meta_key = '_price' AND CAST(price.meta_value AS DECIMAL) > 0
		" );
	}


}
