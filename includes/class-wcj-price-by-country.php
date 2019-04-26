<?php
/**
 * Booster for WooCommerce - Module - Prices and Currencies by Country
 *
 * @version 4.1.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WCJ_Price_By_Country' ) ) :

class WCJ_Price_By_Country extends WCJ_Module {

	/**
	 * @var WCJ_Price_by_Country_Core
	 *
	 * @since 4.1.0
	 */
	public $core;

	/**
	 * Constructor.
	 *
	 * @version 4.1.0
	 */
	function __construct() {

		$this->id         = 'price_by_country';
		$this->short_desc = __( 'Prices and Currencies by Country', 'woocommerce-jetpack' );
		$this->desc       = __( 'Change product price and currency automatically by customer\'s country.', 'woocommerce-jetpack' );
		$this->link_slug  = 'woocommerce-prices-and-currencies-by-country';
		parent::__construct();

		global $wcj_notice;
		$wcj_notice = '';

		if ( $this->is_enabled() ) {

			if ( wcj_is_frontend() ) {
				$do_load_core = true;
				if ( ! defined( 'DOING_AJAX' ) && '/wc-api/WC_Gateway_Paypal/' == $_SERVER['REQUEST_URI'] ) {
					// "Wrong currency in emails" bug fix
					$do_load_core = false;
				}
				if ( $do_load_core ) {
					// Frontend
					$this->core = include_once( 'price-by-country/class-wcj-price-by-country-core.php' );
				}
			}
			if ( is_admin() ) {
				// Backend
				include_once( 'reports/class-wcj-currency-reports.php' );
				if ( 'yes' === get_option( 'wcj_price_by_country_local_enabled', 'yes' ) ) {
					$backend_user_roles = get_option( 'wcj_price_by_country_backend_user_roles', '' );
					if ( empty( $backend_user_roles ) || wcj_is_user_role( $backend_user_roles ) ) {
						if ( 'inline' === get_option( 'wcj_price_by_country_local_options_style', 'inline' ) ) {
							include_once( 'price-by-country/class-wcj-price-by-country-local.php' );
						} else {
							add_action( 'add_meta_boxes',    array( $this, 'add_meta_box' ) );
							add_action( 'save_post_product', array( $this, 'save_meta_box' ), PHP_INT_MAX, 2 );
						}
					}
				}

				// Reset Price Filter
				add_action( 'init', array( $this, 'recalculate_price_filter_products_prices' ) );
			}

			// Price Filter Widget
			if ( 'yes' === get_option( 'wcj_price_by_country_price_filter_widget_support_enabled', 'no' ) ) {
				if ( 'yes' === get_option( 'wcj_price_by_country_local_enabled', 'yes' ) ) {
					add_action( 'save_post_product', array( $this, 'update_products_price_by_country_product_saved' ), PHP_INT_MAX, 2 );
					add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'update_products_price_by_country_product_saved_ajax' ), PHP_INT_MAX, 1 );
				}
			}
		}

		// Price Filter Widget
		if ( 'yes' === get_option( 'wcj_price_by_country_price_filter_widget_support_enabled', 'no' ) ) {
			add_action( 'woojetpack_after_settings_save', array( $this, 'update_products_price_by_country_module_saved' ), PHP_INT_MAX, 2  );
		}

		if ( is_admin() ) {
			include_once( 'price-by-country/class-wcj-price-by-country-group-generator.php' );
		}
	}

	/**
	 * recalculate_price_filter_products_prices.
	 *
	 * @version 2.5.6
	 * @since   2.5.6
	 */
	function recalculate_price_filter_products_prices() {
		if ( isset( $_GET['recalculate_price_filter_products_prices'] ) && ( wcj_is_user_role( 'administrator' ) || is_shop_manager() ) ) {
			wcj_update_products_price_by_country();
			global $wcj_notice;
			$wcj_notice = __( 'Price filter widget product prices recalculated.', 'woocommerce-jetpack' );
		}
	}

	/**
	 * update_products_price_by_country_module_saved.
	 *
	 * @version 2.5.3
	 * @since   2.5.3
	 */
	function update_products_price_by_country_module_saved( $all_sections, $current_section ) {
		if ( 'price_by_country' === $current_section && wcj_is_module_enabled( 'price_by_country' ) ) {
			wcj_update_products_price_by_country();
		}
	}

	/**
	 * update_products_price_by_country_product_saved_ajax.
	 *
	 * @version 2.5.3
	 * @since   2.5.3
	 */
	function update_products_price_by_country_product_saved_ajax( $post_id ) {
		wcj_update_products_price_by_country_for_single_product( $post_id );
	}

	/**
	 * update_products_price_by_country_product_saved.
	 *
	 * @version 2.5.3
	 * @since   2.5.3
	 */
	function update_products_price_by_country_product_saved( $post_id, $post ) {
		wcj_update_products_price_by_country_for_single_product( $post_id );
	}

}

endif;

return new WCJ_Price_By_Country();
